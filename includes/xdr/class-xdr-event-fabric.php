<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class EventFabric {
    private static bool $booted = false;
    private static bool $ingesting = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;
        RequestContext::id();
        ResponseEngine::boot();
        add_action('vis_xdr_retention_cleanup', [self::class, 'cleanup']);
        if (!wp_next_scheduled('vis_xdr_retention_cleanup')) {
            wp_schedule_event(time() + 1800, 'daily', 'vis_xdr_retention_cleanup');
        }
    }

    /** @param array<string, mixed> $legacy */
    public static function ingestLegacy(array $legacy): ?string {
        if (self::$ingesting) return null;
        $context = is_array($legacy['context'] ?? null) ? $legacy['context'] : [];
        
        // Strict Whitelisted Promotion Layer (DO NOT blindly merge arbitrary context)
        $promoted = [
            'sensor'     => (string)($legacy['module'] ?? 'SYSTEM'),
            'event_type' => (string)($legacy['type'] ?? 'EVENT'),
            'severity'   => (int)($legacy['severity'] ?? 1),
            'actor_ip'   => (string)($legacy['ip'] ?? ''),
            'metadata'   => $context,
        ];

        $whitelist = [
            'role', 'category', 'entity_type', 'entity_id',
            'component_key', 'request_id', 'execution_chain_id',
            'causal_edge', 'causal_parent_id', 'attribution_confidence',
            'route', 'user_id'
        ];

        foreach ($whitelist as $key) {
            if (isset($legacy[$key])) {
                $promoted[$key] = $legacy[$key];
            } elseif (isset($context[$key])) {
                $promoted[$key] = $context[$key];
            }
        }

        // Component Key alias promotion
        if (empty($promoted['component_key'])) {
            if (!empty($context['plugin']) && is_string($context['plugin'])) {
                $promoted['component_key'] = $context['plugin'];
            } elseif (!empty($context['component']) && is_string($context['component'])) {
                $promoted['component_key'] = $context['component'];
            } elseif (!empty($context['theme']) && is_string($context['theme'])) {
                $promoted['component_key'] = $context['theme'];
            }
        }

        return self::ingest($promoted);
    }

    /** @param array<string, mixed> $signal */
    public static function ingest(array $signal): ?string {
        if (self::$ingesting) return null;
        self::$ingesting = true;
        try {
            $event = XdrEvent::fromArray($signal);
            $stored = (new EventRepository())->persist($event);
            if ($stored['coalesced']) {
                (new IncidentEngine())->recordCoalescedEvent($event, $stored['id'], (int)$stored['occurrence_count']);
                return null;
            }
            return (new IncidentEngine())->correlate($event, $stored['id']);
        } catch (\ValidationException $e) {
            error_log('[VGT XDR VALIDATION] ' . $e->getMessage());
        } catch (\SecurityException $e) {
            error_log('[VGT XDR SECURITY] ' . $e->getMessage());
        } catch (\StorageException $e) {
            error_log('[VGT XDR STORAGE] ' . $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[VGT XDR FATAL] ' . $e->getMessage());
        } finally {
            self::$ingesting = false;
        }
        return null;
    }

    public static function cleanup(): void {
        global $wpdb;
        $config = get_option('vis_xdr_config', []);
        $days = is_array($config) ? max(7, min(180, (int)($config['retention_days'] ?? 30))) : 30;
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $events = $wpdb->prefix . 'vis_xdr_events';
        $links = $wpdb->prefix . 'vis_xdr_incident_events';
        $incidents = $wpdb->prefix . 'vis_xdr_incidents';
        $responses = $wpdb->prefix . 'vis_xdr_responses';
        $evidence = $wpdb->prefix . 'vis_xdr_evidence';

        $staleIncidentSet = "SELECT incident_uuid FROM (SELECT i.incident_uuid FROM {$incidents} i WHERE i.updated_at < %s AND NOT EXISTS (SELECT 1 FROM {$responses} r WHERE r.incident_uuid = i.incident_uuid AND r.status = 'APPLIED') ORDER BY i.id ASC LIMIT 200) AS stale_incidents";
        $wpdb->query($wpdb->prepare("DELETE FROM {$evidence} WHERE incident_uuid IN ({$staleIncidentSet})", $cutoff));
        $wpdb->query($wpdb->prepare("DELETE FROM {$responses} WHERE incident_uuid IN ({$staleIncidentSet})", $cutoff));
        $wpdb->query($wpdb->prepare("DELETE FROM {$links} WHERE incident_uuid IN ({$staleIncidentSet})", $cutoff));
        $wpdb->query($wpdb->prepare("DELETE FROM {$incidents} WHERE updated_at < %s AND incident_uuid NOT IN (SELECT incident_uuid FROM {$responses} WHERE status = 'APPLIED') ORDER BY id ASC LIMIT 200", $cutoff));

        $staleEventSet = "SELECT id FROM (SELECT id FROM {$events} WHERE last_seen < %s ORDER BY id ASC LIMIT 1000) AS stale_events";
        $wpdb->query($wpdb->prepare("DELETE FROM {$links} WHERE event_id IN ({$staleEventSet})", $cutoff));
        $wpdb->query($wpdb->prepare("DELETE FROM {$events} WHERE last_seen < %s ORDER BY id ASC LIMIT 1000", $cutoff));
    }

    /** @return array<string, string> */
    public static function health(): array {
        global $wpdb;
        $tables = ['events','incidents','incident_events','responses','evidence'];
        $status = [];
        $allTablesOk = true;

        foreach ($tables as $name) {
            $table = $wpdb->prefix . 'vis_xdr_' . $name;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            $status[strtoupper($name)] = $exists ? 'HEALTHY' : 'FAILED';
            if (!$exists) $allTablesOk = false;
        }

        $status['EVENT_FABRIC'] = self::$booted ? 'HEALTHY' : 'DISABLED';
        $status['CORRELATION_ENGINE'] = $allTablesOk ? 'HEALTHY' : 'DEGRADED';
        $status['RESPONSE_ENGINE'] = $allTablesOk ? 'HEALTHY' : 'DEGRADED';
        $status['EVIDENCE_CHAIN'] = $allTablesOk ? 'HEALTHY' : 'DEGRADED';
        return $status;
    }
}
