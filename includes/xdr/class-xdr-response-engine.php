<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class ResponseEngine {
    public const DEFAULT_TTL = 900; // 15 minutes hard semantic TTL

    public static function boot(): void {
        add_action('vis_xdr_response_cleanup', [self::class, 'rollbackExpired']);
        if (!wp_next_scheduled('vis_xdr_response_cleanup')) {
            wp_schedule_event(time() + 300, 'hourly', 'vis_xdr_response_cleanup');
        }
    }

    /**
     * Resolves configurable XDR TTLs based on presets (CONSERVATIVE, BALANCED, AGGRESSIVE, CUSTOM).
     *
     * @return array{actor_ban:int, subnet:int, zeus_route:int, zeus_admission:int, morpheus_overlay:int, styx_overlay:int}
     */
    public static function getTtls(): array {
        $cfg = get_option('vis_xdr_config', []);
        $preset = is_array($cfg) && isset($cfg['ttl_preset']) ? strtoupper(sanitize_key($cfg['ttl_preset'])) : 'BALANCED';

        $presets = [
            'CONSERVATIVE' => [
                'actor_ban'        => 300,
                'subnet'           => 600,
                'zeus_route'       => 300,
                'zeus_admission'   => 300,
                'morpheus_overlay' => 300,
                'styx_overlay'     => 300,
            ],
            'BALANCED' => [
                'actor_ban'        => 900,
                'subnet'           => 1800,
                'zeus_route'       => 900,
                'zeus_admission'   => 900,
                'morpheus_overlay' => 900,
                'styx_overlay'     => 900,
            ],
            'AGGRESSIVE' => [
                'actor_ban'        => 3600,
                'subnet'           => 7200,
                'zeus_route'       => 1800,
                'zeus_admission'   => 1800,
                'morpheus_overlay' => 1800,
                'styx_overlay'     => 1800,
            ],
        ];

        if ($preset !== 'CUSTOM' && isset($presets[$preset])) {
            return $presets[$preset];
        }

        return [
            'actor_ban'        => max(60, min(86400, (int)($cfg['ttl_actor_ban'] ?? 900))),
            'subnet'           => max(120, min(86400, (int)($cfg['ttl_subnet'] ?? 1800))),
            'zeus_route'       => max(60, min(86400, (int)($cfg['ttl_zeus_route'] ?? 900))),
            'zeus_admission'   => max(60, min(86400, (int)($cfg['ttl_zeus_admission'] ?? 900))),
            'morpheus_overlay' => max(60, min(86400, (int)($cfg['ttl_morpheus_overlay'] ?? 900))),
            'styx_overlay'     => max(60, min(86400, (int)($cfg['ttl_styx_overlay'] ?? 900))),
        ];
    }

    public static function evaluate(string $incidentId): void {
        global $wpdb;
        if (preg_match('/^[a-f0-9]{32}$/D', $incidentId) !== 1) return;

        $incidents = $wpdb->prefix . 'vis_xdr_incidents';
        $responses = $wpdb->prefix . 'vis_xdr_responses';

        $incident = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$incidents} WHERE incident_uuid = %s LIMIT 1", $incidentId), \ARRAY_A);
        if (!is_array($incident)) return;

        $decision = PolicyEngine::decide((int)$incident['confidence'], (int)$incident['category_count']);
        $config = get_option('vis_xdr_config', []);
        $auto = is_array($config) && !empty($config['auto_response_enabled']);

        if (!$auto || !$decision['containment_allowed']) {
            $wpdb->update($incidents, ['response_state' => $decision['tier']], ['incident_uuid' => $incidentId]);
            return;
        }

        $ttls = self::getTtls();
        $now = gmdate('Y-m-d H:i:s');
        $actor = (string)($incident['primary_actor'] ?? '');
        $ip = str_starts_with($actor, 'ip:') ? substr($actor, 3) : '';

        // ESCALATION POLICY: Repeat offenders within 24h receive 4x TTL multiplier
        $escalationEnabled = !empty($config['escalation_enabled']);
        if ($escalationEnabled && $actor !== '') {
            $twentyFourHoursAgo = gmdate('Y-m-d H:i:s', time() - 86400);
            $priorCount = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$incidents} WHERE primary_actor = %s AND incident_uuid != %s AND started_at >= %s",
                $actor, $incidentId, $twentyFourHoursAgo
            ));
            if ($priorCount >= 1) {
                foreach ($ttls as $k => $v) {
                    $ttls[$k] = min(86400 * 7, $v * 4);
                }
            }
        }

        // FIX 1: Extract real component key and target route from Attack Story
        $attackStory = self::decode((string)($incident['attack_story'] ?? '[]'));
        $targetPlugin = '';
        $targetRoute = '';

        foreach (array_reverse($attackStory) as $node) {
            if ($targetPlugin === '' && !empty($node['component_key']) && is_string($node['component_key'])) {
                $targetPlugin = XdrEvent::sanitizeComponentKey($node['component_key']);
            }
            if ($targetRoute === '' && !empty($node['route']) && is_string($node['route']) && str_starts_with($node['route'], '/')) {
                $r = (string)parse_url($node['route'], PHP_URL_PATH);
                if (strlen($r) > 1) {
                    $targetRoute = $r;
                }
            }
        }
        if ($targetPlugin === '') {
            $primaryEntityType = strtoupper((string)($incident['primary_entity_type'] ?? ''));
            $primaryEntityId = (string)($incident['primary_entity_id'] ?? '');
            if (($primaryEntityType === 'PLUGIN' || $primaryEntityType === 'WORDPRESS_COMPONENT') && !str_contains($primaryEntityId, ':')) {
                $targetPlugin = XdrEvent::sanitizeComponentKey($primaryEntityId);
            }
        }

        $actionsApplied = 0;
        $actionsPlanned = 0;
        $actionsFailed = 0;

        // 1. CERBERUS IP CONTAINMENT (Idempotent with stable logical identity)
        if ((!isset($config['actuator_cerberus']) || !empty($config['actuator_cerberus'])) && filter_var($ip, FILTER_VALIDATE_IP) && !self::whitelisted($ip)) {
            $actionsPlanned++;
            $actionType = 'CONTAIN_IP';
            $targetType = 'IP';
            $targetId = $ip;
            $respId = substr(hash('sha256', implode('|', [$incidentId, $actionType, $targetType, $targetId])), 0, 32);
            $banTtl = $ttls['actor_ban'];
            $banExpires = gmdate('Y-m-d H:i:s', time() + $banTtl);

            $existingResponse = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$responses} WHERE incident_uuid = %s AND action_type = %s AND target_type = %s AND target_id = %s AND status = 'APPLIED' LIMIT 1",
                $incidentId, $actionType, $targetType, $targetId
            ), \ARRAY_A);

            if (is_array($existingResponse)) {
                $wpdb->update($responses, [
                    'expires_at' => $banExpires,
                    'confidence' => (int)$incident['confidence'],
                    'started_at' => $now
                ], ['id' => (int)$existingResponse['id']]);
                if (class_exists('\VIS_Cerberus')) {
                    \VIS_Cerberus::instance()->ban_ip($ip, 'TRINITY_XDR:' . $incidentId);
                }
                $actionsApplied++;
            } else {
                $existingBan = self::currentBan($ip);
                $isPreExistingAdminBan = !empty($existingBan) && !str_starts_with((string)($existingBan['reason'] ?? ''), 'TRINITY_XDR:');

                if ($isPreExistingAdminBan) {
                    $wpdb->insert($responses, [
                        'response_uuid' => $respId,
                        'incident_uuid' => $incidentId,
                        'owner'         => 'TRINITY_XDR',
                        'action_type'   => $actionType,
                        'target_type'   => $targetType,
                        'target_id'     => $targetId,
                        'reason_code'   => 'PRE_EXISTING_ADMIN_BAN',
                        'confidence'    => (int)$incident['confidence'],
                        'authorized_by' => 'XDR_POLICY',
                        'started_at'    => $now,
                        'expires_at'    => null,
                        'status'        => 'ALREADY_CONTAINED',
                        'rollback_json' => wp_json_encode($existingBan, JSON_THROW_ON_ERROR),
                        'evidence_ref'  => (string)$incident['evidence_root'],
                    ]);
                    $actionsApplied++;
                } else {
                    if (class_exists('\VIS_Cerberus')) {
                        \VIS_Cerberus::instance()->ban_ip($ip, 'TRINITY_XDR:' . $incidentId);
                        $wpdb->insert($responses, [
                            'response_uuid' => $respId,
                            'incident_uuid' => $incidentId,
                            'owner'         => 'TRINITY_XDR',
                            'action_type'   => $actionType,
                            'target_type'   => $targetType,
                            'target_id'     => $targetId,
                            'reason_code'   => $decision['reason'],
                            'confidence'    => (int)$incident['confidence'],
                            'authorized_by' => 'XDR_MULTI_SENSOR_POLICY',
                            'started_at'    => $now,
                            'expires_at'    => $banExpires,
                            'status'        => 'APPLIED',
                            'rollback_json' => wp_json_encode($existingBan, JSON_THROW_ON_ERROR),
                            'evidence_ref'  => (string)$incident['evidence_root'],
                        ]);
                        $actionsApplied++;
                    } else {
                        $actionsFailed++;
                    }
                }
            }
        }

        // 2. ZEUS VIRTUAL ROUTE CONTAINMENT (P1: Trinity -> Zeus live integration)
        if ((!isset($config['actuator_zeus_route']) || !empty($config['actuator_zeus_route'])) && $targetRoute !== '' && class_exists('\VisionGaia\GeDefense\Modules\Zeus\Zeus_Xdr_Bridge')) {
            $actionsPlanned++;
            $actionType = 'CONTAIN_ROUTE';
            $targetType = 'ROUTE';
            $targetId = $targetRoute;
            $respId = substr(hash('sha256', implode('|', [$incidentId, $actionType, $targetType, $targetId])), 0, 32);
            $routeTtl = $ttls['zeus_route'];
            $routeExpires = gmdate('Y-m-d H:i:s', time() + $routeTtl);

            $existingResponse = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$responses} WHERE incident_uuid = %s AND action_type = %s AND target_type = %s AND target_id = %s AND status = 'APPLIED' LIMIT 1",
                $incidentId, $actionType, $targetType, $targetId
            ), \ARRAY_A);

            try {
                \VisionGaia\GeDefense\Modules\Zeus\Zeus_Xdr_Bridge::containRoute($targetRoute, $incidentId, $routeTtl, $respId);

                if (is_array($existingResponse)) {
                    $wpdb->update($responses, [
                        'expires_at' => $routeExpires,
                        'confidence' => (int)$incident['confidence'],
                        'started_at' => $now
                    ], ['id' => (int)$existingResponse['id']]);
                } else {
                    $wpdb->insert($responses, [
                        'response_uuid' => $respId,
                        'incident_uuid' => $incidentId,
                        'owner'         => 'TRINITY_XDR',
                        'action_type'   => $actionType,
                        'target_type'   => $targetType,
                        'target_id'     => $targetRoute,
                        'reason_code'   => 'XDR_ROUTE_CONTAINMENT',
                        'confidence'    => (int)$incident['confidence'],
                        'authorized_by' => 'XDR_MULTI_SENSOR_POLICY',
                        'started_at'    => $now,
                        'expires_at'    => $routeExpires,
                        'status'        => 'APPLIED',
                        'rollback_json' => wp_json_encode(['target_route' => $targetRoute], JSON_THROW_ON_ERROR),
                        'evidence_ref'  => (string)$incident['evidence_root'],
                    ]);
                }
                $actionsApplied++;
            } catch (\Throwable) {
                $actionsFailed++;
            }
        }

        // 3. MORPHEUS TEMPORARY XDR OVERLAY (Idempotent & Response-Owned)
        if ((!isset($config['actuator_morpheus']) || !empty($config['actuator_morpheus'])) && $targetPlugin !== '' && class_exists('\VisionGaia\GeDefense\Modules\Morpheus\Morpheus')) {
            $actionsPlanned++;
            $actionType = 'RESTRICT_CAPABILITIES';
            $targetType = 'PLUGIN';
            $targetId = $targetPlugin;
            $respId = substr(hash('sha256', implode('|', [$incidentId, $actionType, $targetType, $targetId])), 0, 32);
            $morphTtl = $ttls['morpheus_overlay'];
            $morphExpires = gmdate('Y-m-d H:i:s', time() + $morphTtl);

            $existingResponse = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$responses} WHERE incident_uuid = %s AND action_type = %s AND target_type = %s AND target_id = %s AND status = 'APPLIED' LIMIT 1",
                $incidentId, $actionType, $targetType, $targetId
            ), \ARRAY_A);

            try {
                \VisionGaia\GeDefense\Modules\Morpheus\Morpheus::get_instance()->add_xdr_overlay(
                    $incidentId,
                    $respId,
                    $targetPlugin,
                    ['network_denied' => true, 'db_write_denied' => true],
                    $morphTtl
                );

                if (is_array($existingResponse)) {
                    $wpdb->update($responses, [
                        'expires_at' => $morphExpires,
                        'confidence' => (int)$incident['confidence'],
                        'started_at' => $now
                    ], ['id' => (int)$existingResponse['id']]);
                } else {
                    $wpdb->insert($responses, [
                        'response_uuid' => $respId,
                        'incident_uuid' => $incidentId,
                        'owner'         => 'TRINITY_XDR',
                        'action_type'   => $actionType,
                        'target_type'   => $targetType,
                        'target_id'     => $targetPlugin,
                        'reason_code'   => 'COMPROMISED_COMPONENT_CONTAINMENT',
                        'confidence'    => (int)$incident['confidence'],
                        'authorized_by' => 'XDR_MULTI_SENSOR_POLICY',
                        'started_at'    => $now,
                        'expires_at'    => $morphExpires,
                        'status'        => 'APPLIED',
                        'rollback_json' => wp_json_encode(['target_plugin' => $targetPlugin], JSON_THROW_ON_ERROR),
                        'evidence_ref'  => (string)$incident['evidence_root'],
                    ]);
                }
                $actionsApplied++;
            } catch (\Throwable) {
                $actionsFailed++;
            }
        }

        // 4. STYX COMPONENT-AWARE EGRESS OVERLAY (Idempotent & Response-Owned)
        if ((!isset($config['actuator_styx']) || !empty($config['actuator_styx'])) && $targetPlugin !== '' && class_exists('\VisionGaia\GeDefense\Modules\Styx\Styx')) {
            $actionsPlanned++;
            $actionType = 'BLOCK_EGRESS';
            $targetType = 'PLUGIN';
            $targetId = $targetPlugin;
            $respId = substr(hash('sha256', implode('|', [$incidentId, $actionType, $targetType, $targetId])), 0, 32);
            $styxTtl = $ttls['styx_overlay'];
            $styxExpires = gmdate('Y-m-d H:i:s', time() + $styxTtl);

            $existingResponse = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$responses} WHERE incident_uuid = %s AND action_type = %s AND target_type = %s AND target_id = %s AND status = 'APPLIED' LIMIT 1",
                $incidentId, $actionType, $targetType, $targetId
            ), \ARRAY_A);

            try {
                \VisionGaia\GeDefense\Modules\Styx\Styx::get_instance()->add_xdr_overlay(
                    $incidentId,
                    $respId,
                    $targetPlugin,
                    ['*'],
                    $styxTtl
                );

                if (is_array($existingResponse)) {
                    $wpdb->update($responses, [
                        'expires_at' => $styxExpires,
                        'confidence' => (int)$incident['confidence'],
                        'started_at' => $now
                    ], ['id' => (int)$existingResponse['id']]);
                } else {
                    $wpdb->insert($responses, [
                        'response_uuid' => $respId,
                        'incident_uuid' => $incidentId,
                        'owner'         => 'TRINITY_XDR',
                        'action_type'   => $actionType,
                        'target_type'   => $targetType,
                        'target_id'     => $targetPlugin,
                        'reason_code'   => 'COMPROMISED_COMPONENT_EGRESS_CONTAINMENT',
                        'confidence'    => (int)$incident['confidence'],
                        'authorized_by' => 'XDR_MULTI_SENSOR_POLICY',
                        'started_at'    => $now,
                        'expires_at'    => $styxExpires,
                        'status'        => 'APPLIED',
                        'rollback_json' => wp_json_encode(['target_plugin' => $targetPlugin], JSON_THROW_ON_ERROR),
                        'evidence_ref'  => (string)$incident['evidence_root'],
                    ]);
                }
                $actionsApplied++;
            } catch (\Throwable) {
                $actionsFailed++;
            }
        }

        // Coordinated Response State Determination
        $finalState = 'OBSERVE';
        if ($actionsPlanned > 0) {
            if ($actionsApplied === $actionsPlanned && $actionsFailed === 0) {
                $finalState = 'CONTAINED';
            } elseif ($actionsApplied > 0) {
                $finalState = 'PARTIAL';
            } else {
                $finalState = 'FAILED';
            }
        }

        $wpdb->update($incidents, ['response_state' => $finalState], ['incident_uuid' => $incidentId]);
        if ($actionsApplied > 0 && function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + min($ttls), 'vis_xdr_response_cleanup');
        }
    }

    public static function rollbackExpired(): void {
        global $wpdb;
        $responses = $wpdb->prefix . 'vis_xdr_responses';
        $now = gmdate('Y-m-d H:i:s');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$responses} WHERE owner = 'TRINITY_XDR' AND status = 'APPLIED' AND expires_at IS NOT NULL AND expires_at <= %s ORDER BY id ASC LIMIT 100",
            $now
        ), \ARRAY_A);

        foreach (is_array($rows) ? $rows : [] as $row) {
            $respId = (string)$row['response_uuid'];
            $target = (string)$row['target_id'];
            $incident = (string)$row['incident_uuid'];
            $type = (string)$row['target_type'];

            $hasActiveNewer = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$responses} WHERE target_type = %s AND target_id = %s AND status = 'APPLIED' AND (expires_at IS NULL OR expires_at > %s) AND response_uuid != %s LIMIT 1",
                $type, $target, $now, $respId
            ));

            if ($type === 'IP' && filter_var($target, FILTER_VALIDATE_IP)) {
                if (!is_numeric($hasActiveNewer)) {
                    $reason = self::banReason($target);
                    if (is_string($reason) && hash_equals($reason, 'TRINITY_XDR:' . $incident)) {
                        $previous = self::decode((string)$row['rollback_json']);
                        self::removeBan($target);
                        if (!empty($previous['reason']) && is_string($previous['reason']) && class_exists('\VIS_Cerberus')) {
                            \VIS_Cerberus::instance()->ban_ip($target, substr($previous['reason'], 0, 500));
                        }
                    }
                }
                if (class_exists('\VIS_Cerberus')) {
                    wp_cache_delete('vis_ban_status_' . md5($target), 'visiongaia_cerberus');
                    wp_cache_delete('vis_ban_status_' . md5($target) . '_type', 'visiongaia_cerberus');
                    \VIS_Cerberus::instance()->sync_os_firewall_rules();
                }
            } elseif ($type === 'ROUTE') {
                if (!is_numeric($hasActiveNewer)) {
                    if (class_exists('\VisionGaia\GeDefense\Modules\Zeus\Zeus_Xdr_Bridge')) {
                        \VisionGaia\GeDefense\Modules\Zeus\Zeus_Xdr_Bridge::removeRouteContainment($incident, $respId);
                    }
                }
            } elseif ($type === 'PLUGIN') {
                if (!is_numeric($hasActiveNewer)) {
                    if (class_exists('\VisionGaia\GeDefense\Modules\Morpheus\Morpheus')) {
                        \VisionGaia\GeDefense\Modules\Morpheus\Morpheus::get_instance()->remove_xdr_overlay($incident, $respId);
                    }
                    if (class_exists('\VisionGaia\GeDefense\Modules\Styx\Styx')) {
                        \VisionGaia\GeDefense\Modules\Styx\Styx::get_instance()->remove_xdr_overlay($incident, $respId);
                    }
                }
            }
            $wpdb->update($responses, ['status' => 'ROLLED_BACK'], ['id' => (int)$row['id']]);
        }
    }

    public static function isIpRestricted(string $ip): bool {
        global $wpdb;
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        $table = $wpdb->prefix . 'vis_xdr_responses';
        $now = gmdate('Y-m-d H:i:s');
        $active = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE target_type = 'IP' AND target_id = %s AND status = 'APPLIED' AND (expires_at IS NULL OR expires_at > %s) LIMIT 1",
            $ip,
            $now
        ));
        return is_numeric($active);
    }

    private static function whitelisted(string $ip): bool {
        $config = get_option('vis_xdr_config', []);
        $whitelist = is_array($config) && isset($config['whitelist']) && is_string($config['whitelist'])
            ? array_filter(array_map('trim', explode("\n", $config['whitelist'])))
            : [];
        return in_array($ip, $whitelist, true);
    }

    /** @return array<string, mixed> */
    private static function currentBan(string $ip): array {
        global $wpdb;
        $table = $wpdb->prefix . (defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_omega_bans');
        $row = $wpdb->get_row($wpdb->prepare("SELECT reason, banned_at, request_uri FROM {$table} WHERE ip = %s LIMIT 1", $ip), \ARRAY_A);
        return is_array($row) ? $row : [];
    }

    private static function banReason(string $ip): ?string {
        global $wpdb;
        $table = $wpdb->prefix . (defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_omega_bans');
        return $wpdb->get_var($wpdb->prepare("SELECT reason FROM {$table} WHERE ip = %s LIMIT 1", $ip));
    }

    private static function removeBan(string $ip): void {
        global $wpdb;
        $table = $wpdb->prefix . (defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_omega_bans');
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE ip = %s", $ip));
    }

    /** @return array<string, mixed> */
    private static function decode(string $json): array {
        try {
            $arr = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            return is_array($arr) ? $arr : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
