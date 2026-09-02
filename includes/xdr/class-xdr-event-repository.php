<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class EventRepository {
    /** @return array{id:int,coalesced:bool,occurrence_count:int} */
    public function persist(XdrEvent $event): array {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) throw new \StorageException('XDR event storage unavailable.');

        $table = $wpdb->prefix . 'vis_xdr_events';
        $dedupe = hash('sha256', implode('|', [
            $event->sensor,
            $event->eventType,
            $event->role,
            $event->actorIp,
            $event->entityType,
            $event->entityId,
            $event->vector
        ]));

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, event_uuid, occurrence_count FROM {$table} WHERE dedupe_hash = %s AND last_seen >= %s ORDER BY id DESC LIMIT 1",
            $dedupe,
            gmdate('Y-m-d H:i:s', time() - 60)
        ), \ARRAY_A);

        if (is_array($existing) && isset($existing['id'])) {
            $newCount = min(4294967295, ((int)($existing['occurrence_count'] ?? 1)) + 1);
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET occurrence_count = %d, last_seen = %s WHERE id = %d",
                $newCount,
                $event->timestamp,
                (int)$existing['id']
            ));
            return ['id' => (int)$existing['id'], 'coalesced' => true, 'occurrence_count' => $newCount, 'event_uuid' => (string)($existing['event_uuid'] ?? '')];
        }

        $metadata = wp_json_encode($event->metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($metadata) > 8192) throw new \ValidationException('XDR metadata boundary exceeded.');

        $inserted = $wpdb->insert($table, [
            'event_uuid' => $event->eventId,
            'schema_version' => XdrEvent::SCHEMA_VERSION,
            'first_seen' => $event->timestamp,
            'last_seen' => $event->timestamp,
            'sensor' => $event->sensor,
            'category' => $event->category,
            'event_type' => $event->eventType,
            'role' => $event->role,
            'severity' => $event->severity,
            'confidence' => $event->confidence,
            'score' => $event->score,
            'attribution_confidence' => $event->attributionConfidence,
            'actor_ip' => $event->actorIp,
            'actor_hash' => hash('sha256', $event->actorIp !== '' ? $event->actorIp : $event->sessionId),
            'user_id' => $event->userId,
            'session_hash' => $event->sessionId,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'request_id' => $event->requestId,
            'correlation_id' => $event->correlationId,
            'execution_chain_id' => $event->executionChainId,
            'route' => $event->route,
            'vector' => $event->vector,
            'action_type' => $event->actionType,
            'outcome' => $event->outcome,
            'privacy_class' => $event->privacyClass,
            'causal_parent_id' => $event->causalParentId,
            'causal_edge' => $event->causalEdge,
            'metadata_json' => $metadata,
            'event_hash' => $event->hash(),
            'dedupe_hash' => $dedupe,
            'occurrence_count' => 1,
        ], [
            '%s','%d','%s','%s','%s','%s','%s','%s','%d','%d','%f','%d','%s','%s','%d','%s',
            '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%d'
        ]);

        if ($inserted !== 1 || !is_numeric($wpdb->insert_id)) throw new \StorageException('XDR event persistence failed.');
        return ['id' => (int)$wpdb->insert_id, 'coalesced' => false, 'occurrence_count' => 1];
    }

    /** @return array<int, object> */
    public static function latest(int $limit = 50): array {
        global $wpdb;
        $limit = max(1, min(100, $limit));
        $table = $wpdb->prefix . 'vis_xdr_events';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_uuid,last_seen,sensor,category,event_type,role,severity,confidence,actor_ip,entity_type,entity_id,vector,occurrence_count FROM {$table} ORDER BY id DESC LIMIT %d",
            $limit
        ));
        return is_array($rows) ? $rows : [];
    }
}
