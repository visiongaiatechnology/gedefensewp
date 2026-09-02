<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class IncidentEngine {
    private const WINDOW_SECONDS = 1800; // 30 min sliding incident window

    public function correlate(XdrEvent $event, int $eventId): ?string {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) return null;

        $incidents = $wpdb->prefix . 'vis_xdr_incidents';
        $links = $wpdb->prefix . 'vis_xdr_incident_events';

        $actor = $event->userId > 0 ? 'user:' . $event->userId : ($event->actorIp !== '' ? 'ip:' . $event->actorIp : 'session:' . $event->sessionId);
        $entityFamily = self::entityFamily($event);
        $classificationFamily = self::classificationFamily($event->vector);
        $campaignKey = hash('sha256', implode('|', [$actor, $entityFamily, $classificationFamily]));
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);

        $wpdb->query('START TRANSACTION');
        try {
            // LAYER 1: Execution Chain Correlation (Exact request/chain match)
            $incident = null;
            if ($event->executionChainId !== '') {
                $incident = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$incidents} WHERE execution_chain_id = %s AND status IN ('OPEN','INVESTIGATING','CONTAINED','MONITORING') AND updated_at >= %s ORDER BY id DESC LIMIT 1 FOR UPDATE",
                    $event->executionChainId,
                    $cutoff
                ), \ARRAY_A);
            }

            // LAYER 2: Campaign Correlation (Actor + Entity Family + Classification Family within 30 min)
            if (!is_array($incident)) {
                $incident = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$incidents} WHERE correlation_key = %s AND status IN ('OPEN','INVESTIGATING','CONTAINED','MONITORING') AND updated_at >= %s ORDER BY id DESC LIMIT 1 FOR UPDATE",
                    $campaignKey,
                    $cutoff
                ), \ARRAY_A);
            }

            $now = gmdate('Y-m-d H:i:s');

            if (!is_array($incident)) {
                if ($event->isContext()) {
                    $wpdb->query('COMMIT');
                    return null;
                }
                $incidentId = bin2hex(random_bytes(16));
                $sensors = [$event->sensor];
                $categories = $event->isDetection() || $event->isConfirmation() ? [$event->category] : [];
                $initialConfidence = $event->isDetection() || $event->isConfirmation() ? $event->confidence : 0;

                $storyNode = [
                    'timestamp' => $event->timestamp,
                    'sensor' => $event->sensor,
                    'category' => $event->category,
                    'event_type' => $event->eventType,
                    'role' => $event->role,
                    'entity_type' => $event->entityType,
                    'entity_id' => $event->entityId,
                    'component_key' => $event->componentKey,
                    'actor' => $actor,
                    'severity' => $event->severity,
                    'confidence' => $event->confidence,
                    'causal_edge' => $event->causalEdge,
                    'causal_parent_id' => $event->causalParentId,
                    'event_uuid' => $event->eventId,
                ];
                $attackStory = [$storyNode];

                $inserted = $wpdb->insert($incidents, [
                    'incident_uuid' => $incidentId,
                    'correlation_key' => $campaignKey,
                    'execution_chain_id' => $event->executionChainId,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'status' => 'OPEN',
                    'classification' => $classificationFamily,
                    'severity' => $event->severity,
                    'confidence' => $initialConfidence,
                    'primary_actor' => substr($actor, 0, 191),
                    'primary_entity_type' => $event->entityType,
                    'primary_entity_id' => $event->entityId,
                    'event_count' => 1,
                    'sensor_count' => 1,
                    'category_count' => count($categories),
                    'sensor_set' => wp_json_encode($sensors, JSON_THROW_ON_ERROR),
                    'category_set' => wp_json_encode($categories, JSON_THROW_ON_ERROR),
                    'response_state' => 'OBSERVE',
                    'evidence_root' => '',
                    'attack_story' => wp_json_encode($attackStory, JSON_THROW_ON_ERROR),
                    'related_entities' => wp_json_encode([$event->entityType . ':' . $event->entityId], JSON_THROW_ON_ERROR),
                    'resolution' => '',
                ]);
                if ($inserted !== 1) throw new \StorageException('XDR incident persistence failed.');
            } else {
                $incidentId = (string)$incident['incident_uuid'];
                $sensors = self::set((string)($incident['sensor_set'] ?? '[]'), $event->sensor);

                // Sensor Independence Rule: Responses and Context MUST NOT increment category count or confidence!
                $categories = self::setList((string)($incident['category_set'] ?? '[]'));
                $newCategoryCount = count($categories);
                if (($event->isDetection() || $event->isConfirmation()) && !in_array($event->category, $categories, true)) {
                    $categories[] = $event->category;
                    sort($categories, SORT_STRING);
                    $newCategoryCount = count($categories);
                }

                $prevConfidence = (int)$incident['confidence'];
                $confidence = $prevConfidence;
                if ($event->isDetection() || $event->isConfirmation()) {
                    $sensorBonus = (count($sensors) - (int)$incident['sensor_count']) * 8;
                    $categoryBonus = ($newCategoryCount - (int)$incident['category_count']) * 14;
                    $confidence = min(100, max($prevConfidence, $event->confidence) + max(0, $sensorBonus) + max(0, $categoryBonus));
                }

                $classification = $newCategoryCount >= 3 ? 'MULTI_VECTOR_INTRUSION' : (string)$incident['classification'];

                // Entity Promotion: Higher semantic entity promotes primary affected entity
                $curEntityType = (string)($incident['primary_entity_type'] ?? 'IP');
                $curEntityId = (string)($incident['primary_entity_id'] ?? '');
                $promotedType = $curEntityType;
                $promotedId = $curEntityId;
                if (self::entityRank($event->entityType) > self::entityRank($curEntityType)) {
                    $promotedType = $event->entityType;
                    $promotedId = $event->entityId;
                }

                // Append to Attack Story
                $attackStory = self::loadStory((string)($incident['attack_story'] ?? '[]'));
                $attackStory[] = [
                    'timestamp' => $event->timestamp,
                    'sensor' => $event->sensor,
                    'category' => $event->category,
                    'event_type' => $event->eventType,
                    'role' => $event->role,
                    'entity_type' => $event->entityType,
                    'entity_id' => $event->entityId,
                    'component_key' => $event->componentKey,
                    'actor' => $actor,
                    'severity' => $event->severity,
                    'confidence' => $event->confidence,
                    'causal_edge' => $event->causalEdge,
                    'causal_parent_id' => $event->causalParentId,
                    'event_uuid' => $event->eventId,
                ];
                if (count($attackStory) > 100) $attackStory = array_slice($attackStory, -100);

                // Update Related Entities
                $entities = self::setList((string)($incident['related_entities'] ?? '[]'));
                $entityKey = $event->entityType . ':' . $event->entityId;
                if (!in_array($entityKey, $entities, true)) {
                    $entities[] = $entityKey;
                    if (count($entities) > 32) $entities = array_slice($entities, -32);
                }

                $wpdb->update($incidents, [
                    'updated_at' => $now,
                    'classification' => $classification,
                    'severity' => max((int)$incident['severity'], $event->severity),
                    'confidence' => $confidence,
                    'primary_entity_type' => $promotedType,
                    'primary_entity_id' => $promotedId,
                    'event_count' => min(4294967295, (int)$incident['event_count'] + 1),
                    'sensor_count' => count($sensors),
                    'category_count' => $newCategoryCount,
                    'sensor_set' => wp_json_encode($sensors, JSON_THROW_ON_ERROR),
                    'category_set' => wp_json_encode($categories, JSON_THROW_ON_ERROR),
                    'attack_story' => wp_json_encode($attackStory, JSON_THROW_ON_ERROR),
                    'related_entities' => wp_json_encode($entities, JSON_THROW_ON_ERROR),
                ], ['incident_uuid' => $incidentId]);
            }

            // Link Event to Incident
            $wpdb->insert($links, [
                'incident_uuid' => $incidentId,
                'event_id' => $eventId,
                'linked_at' => $now,
            ], ['%s', '%d', '%s']);

            // Attach rolling cryptographic evidence
            $rootHash = EvidenceStore::attach($incidentId, $event);
            if ($rootHash !== '') {
                $wpdb->update($incidents, ['evidence_root' => $rootHash], ['incident_uuid' => $incidentId]);
            }

            $wpdb->query('COMMIT');
            ResponseEngine::evaluate($incidentId);
            return $incidentId;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function recordCoalescedEvent(XdrEvent $event, int $eventId, int $occurrenceCount): void {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) return;

        $incidents = $wpdb->prefix . 'vis_xdr_incidents';
        $links = $wpdb->prefix . 'vis_xdr_incident_events';

        $incidentUuid = $wpdb->get_var($wpdb->prepare(
            "SELECT incident_uuid FROM {$links} WHERE event_id = %d LIMIT 1",
            $eventId
        ));

        if (!is_string($incidentUuid) || $incidentUuid === '') return;

        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$incidents} SET updated_at = %s, event_count = event_count + 1 WHERE incident_uuid = %s",
            $now,
            $incidentUuid
        ));
    }

    /** @return array<int, object> */
    public static function latest(int $limit = 50): array {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) return [];
        $limit = max(1, min(100, $limit));
        $table = $wpdb->prefix . 'vis_xdr_incidents';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT incident_uuid,created_at,updated_at,status,classification,severity,confidence,primary_actor,primary_entity_type,primary_entity_id,event_count,sensor_count,category_count,response_state,evidence_root FROM {$table} ORDER BY id DESC LIMIT %d",
            $limit
        ));
        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public static function detail(string $incidentUuid): ?array {
        if (preg_match('/^[a-f0-9]{32}$/D', $incidentUuid) !== 1) throw new \ValidationException('Invalid incident identifier.');
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) return null;
        $incidents = $wpdb->prefix . 'vis_xdr_incidents';
        $links = $wpdb->prefix . 'vis_xdr_incident_events';
        $events = $wpdb->prefix . 'vis_xdr_events';
        $responses = $wpdb->prefix . 'vis_xdr_responses';
        $evidence = $wpdb->prefix . 'vis_xdr_evidence';
        $incident = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$incidents} WHERE incident_uuid = %s LIMIT 1", $incidentUuid), \ARRAY_A);
        if (!is_array($incident)) return null;
        $eventRows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.event_uuid,e.first_seen,e.last_seen,e.sensor,e.category,e.event_type,e.role,e.severity,e.confidence,e.attribution_confidence,e.actor_ip,e.user_id,e.entity_type,e.entity_id,e.route,e.vector,e.action_type,e.outcome,e.causal_edge,e.occurrence_count FROM {$events} e INNER JOIN {$links} l ON l.event_id=e.id WHERE l.incident_uuid=%s ORDER BY l.id ASC LIMIT 100",
            $incidentUuid
        ), \ARRAY_A);
        $responseRows = $wpdb->get_results($wpdb->prepare("SELECT response_uuid,action_type,target_type,target_id,confidence,authorized_by,started_at,expires_at,status,rollback_json,evidence_ref FROM {$responses} WHERE incident_uuid=%s ORDER BY id DESC LIMIT 50", $incidentUuid), \ARRAY_A);
        $evidenceRows = $wpdb->get_results($wpdb->prepare("SELECT evidence_uuid,event_uuid,created_at,evidence_type,digest,previous_root,current_root,sequence_num,validity FROM {$evidence} WHERE incident_uuid=%s ORDER BY sequence_num ASC LIMIT 100", $incidentUuid), \ARRAY_A);
        return [
            'incident' => $incident,
            'story' => self::loadStory((string)($incident['attack_story'] ?? '[]')),
            'entities' => self::setList((string)($incident['related_entities'] ?? '[]')),
            'events' => is_array($eventRows) ? $eventRows : [],
            'responses' => is_array($responseRows) ? $responseRows : [],
            'evidence' => is_array($evidenceRows) ? $evidenceRows : [],
        ];
    }

    private static function entityRank(string $type): int {
        return match(strtoupper(trim($type))) {
            'PLUGIN', 'THEME', 'COMPONENT', 'WORDPRESS_COMPONENT' => 40,
            'FILE', 'UPLOAD', 'ARTIFACT' => 30,
            'ROUTE', 'HOST', 'URL', 'ENDPOINT' => 20,
            'IP', 'SUBNET', 'SESSION' => 10,
            default => 5,
        };
    }

    private static function entityFamily(XdrEvent $event): string {
        if ($event->entityType === 'PLUGIN' || $event->entityType === 'THEME') {
            return $event->entityType . ':' . $event->entityId;
        }
        if (str_starts_with($event->route, '/wp-json/')) {
            $parts = explode('/', trim($event->route, '/'));
            return 'route_family:' . ($parts[1] ?? 'rest');
        }
        return 'entity_family:' . ($event->entityType ?: 'generic');
    }

    private static function classificationFamily(string $vector): string {
        $v = strtoupper($vector);
        if (str_contains($v, 'SQLI')) return 'SQL_INJECTION';
        if (str_contains($v, 'XSS')) return 'CROSS_SITE_SCRIPTING';
        if (str_contains($v, 'RCE') || str_contains($v, 'PAYLOAD') || str_contains($v, 'MALWARE')) return 'REMOTE_EXECUTION';
        if (str_contains($v, 'AUTH') || str_contains($v, 'LOGIN') || str_contains($v, 'BRUTE')) return 'AUTHENTICATION_ATTACK';
        if (str_contains($v, 'EGRESS') || str_contains($v, 'EXFILTRATION')) return 'DATA_EXFILTRATION';
        return $vector;
    }

    /** @return list<string> */
    private static function set(string $json, string $item): array {
        $list = self::setList($json);
        if (!in_array($item, $list, true)) {
            $list[] = $item;
            sort($list, SORT_STRING);
        }
        return $list;
    }

    /** @return list<string> */
    private static function setList(string $json): array {
        try {
            $arr = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            return is_array($arr) ? array_values(array_filter($arr, 'is_string')) : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    private static function loadStory(string $json): array {
        try {
            $arr = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            return is_array($arr) ? $arr : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
