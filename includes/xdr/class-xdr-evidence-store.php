<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class EvidenceStore {
    private const DOMAIN_SEPARATOR = 'GEDEFENSE:XDR:EVIDENCE:v1';

    public static function attach(string $incidentId, XdrEvent $event): string {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) return '';

        $table = $wpdb->prefix . 'vis_xdr_evidence';
        $digest = $event->hash();
        $now = gmdate('Y-m-d H:i:s');

        // Bounded sequence retry for concurrency safety
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $lastRow = $wpdb->get_row($wpdb->prepare(
                "SELECT sequence_num, current_root FROM {$table} WHERE incident_uuid = %s ORDER BY sequence_num DESC LIMIT 1",
                $incidentId
            ), \ARRAY_A);

            $seq = is_array($lastRow) && isset($lastRow['sequence_num']) ? ((int)$lastRow['sequence_num']) + 1 : 0;
            $prevRoot = is_array($lastRow) && isset($lastRow['current_root']) ? (string)$lastRow['current_root'] : '';

            $currentRoot = $seq === 0
                ? hash('sha256', self::DOMAIN_SEPARATOR . '|' . $incidentId . '|' . $digest . '|0')
                : hash('sha256', self::DOMAIN_SEPARATOR . '|' . $prevRoot . '|' . $digest . '|' . $seq);

            $evidenceUuid = bin2hex(random_bytes(16));
            $inserted = $wpdb->insert($table, [
                'evidence_uuid' => $evidenceUuid,
                'incident_uuid' => $incidentId,
                'event_uuid' => $event->eventId,
                'evidence_type' => 'EVENT_DIGEST',
                'sequence_num' => $seq,
                'previous_root' => $prevRoot,
                'current_root' => $currentRoot,
                'digest' => $digest,
                'created_at' => $now,
                'validity' => 'VALID',
            ], ['%s','%s','%s','%s','%d','%s','%s','%s','%s','%s']);

            if ($inserted === 1) {
                return $currentRoot;
            }
        }
        return '';
    }

    /** @return array{status:string,count:int,root:string,reason?:string,sequence?:int} */
    public static function verify(string $incidentId, int $budget = 500): array {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) {
            return ['status' => 'INVALID', 'count' => 0, 'root' => '', 'reason' => 'DATABASE_UNAVAILABLE'];
        }

        $table = $wpdb->prefix . 'vis_xdr_evidence';
        $eventsTable = $wpdb->prefix . 'vis_xdr_events';

        $limit = max(1, min(1000, $budget));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT sequence_num, previous_root, current_root, digest, event_uuid FROM {$table} WHERE incident_uuid = %s ORDER BY sequence_num ASC LIMIT %d",
            $incidentId,
            $limit + 1
        ), \ARRAY_A);

        if (!is_array($rows) || empty($rows)) {
            return ['status' => 'INVALID', 'count' => 0, 'root' => '', 'reason' => 'NO_EVIDENCE_RECORDS'];
        }

        if (count($rows) > $limit) {
            $lastRow = $rows[$limit - 1];
            return [
                'status' => 'INCOMPLETE',
                'count' => $limit,
                'root' => (string)($lastRow['current_root'] ?? ''),
                'reason' => 'VERIFICATION_BUDGET_EXCEEDED'
            ];
        }

        $expectedPrev = '';
        $lastRoot = '';

        foreach ($rows as $expectedSeq => $row) {
            $seq = (int)($row['sequence_num'] ?? -1);
            if ($seq !== $expectedSeq) {
                return ['status' => 'INVALID', 'count' => $expectedSeq, 'root' => $lastRoot, 'reason' => 'SEQUENCE_GAP', 'sequence' => $expectedSeq];
            }

            $prev = (string)($row['previous_root'] ?? '');
            $curr = (string)($row['current_root'] ?? '');
            $digest = (string)($row['digest'] ?? '');
            $eventUuid = (string)($row['event_uuid'] ?? '');

            if ($expectedSeq > 0 && !hash_equals($expectedPrev, $prev)) {
                return ['status' => 'INVALID', 'count' => $expectedSeq, 'root' => $lastRoot, 'reason' => 'CHAIN_BROKEN', 'sequence' => $seq];
            }

            $calculatedRoot = $expectedSeq === 0
                ? hash('sha256', self::DOMAIN_SEPARATOR . '|' . $incidentId . '|' . $digest . '|0')
                : hash('sha256', self::DOMAIN_SEPARATOR . '|' . $prev . '|' . $digest . '|' . $expectedSeq);

            if (!hash_equals($calculatedRoot, $curr)) {
                return ['status' => 'INVALID', 'count' => $expectedSeq, 'root' => $lastRoot, 'reason' => 'DIGEST_MISMATCH', 'sequence' => $seq];
            }

            // Verify referenced event exists and hash matches if events table available
            if ($eventUuid !== '') {
                $storedEventHash = $wpdb->get_var($wpdb->prepare(
                    "SELECT event_hash FROM {$eventsTable} WHERE event_uuid = %s LIMIT 1",
                    $eventUuid
                ));
                if (is_string($storedEventHash) && !hash_equals($storedEventHash, $digest)) {
                    return ['status' => 'INVALID', 'count' => $expectedSeq, 'root' => $lastRoot, 'reason' => 'EVENT_INTEGRITY_MISMATCH', 'sequence' => $seq];
                }
            }

            $expectedPrev = $curr;
            $lastRoot = $curr;
        }

        return ['status' => 'VALID', 'count' => count($rows), 'root' => $lastRoot];
    }
}
