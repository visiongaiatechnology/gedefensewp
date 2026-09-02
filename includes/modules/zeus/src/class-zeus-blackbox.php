<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Blackbox {

    public const MAX_SPOOL_BYTES = 2097152; // 2 MB max spool size
    public const MAX_SPOOL_RECORDS = 5000;
    public const COALESCE_WINDOW_SEC = 300; // 5-minute flood coalescing window

    /**
     * Resolves the master signing secret without fake fallback strings.
     */
    public static function getMasterSecret(string $vaultDir): ?string {
        if (defined('VGT_MASTER_KEY') && is_string(VGT_MASTER_KEY) && VGT_MASTER_KEY !== '') {
            return VGT_MASTER_KEY;
        }
        $keyFile = $vaultDir . 'vgt-master.php';
        if (is_file($keyFile) && is_readable($keyFile)) {
            @include_once $keyFile;
            if (defined('VGT_MASTER_KEY') && is_string(VGT_MASTER_KEY) && VGT_MASTER_KEY !== '') {
                return VGT_MASTER_KEY;
            }
        }
        return null;
    }

    /**
     * Records a pre-boot security event into the tamper-evident, flood-resistant spool.
     */
    public static function record(
        string $eventType,
        string $ruleId,
        string $reason,
        int $severity,
        string $actorIp,
        string $route,
        string $method,
        string $decision,
        string $vaultDir
    ): void {
        $spoolFile = $vaultDir . 'blackbox.spool';
        if (!is_dir($vaultDir)) {
            @mkdir($vaultDir, 0700, true);
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $nowTs = time();
        $actorMasked = self::maskActor($actorIp);
        $routeFamily = (string)parse_url($route, PHP_URL_PATH);
        if (strlen($routeFamily) > 64) {
            $routeFamily = substr($routeFamily, 0, 64);
        }

        $fh = @fopen($spoolFile, 'c+b');
        if (!$fh) return;

        if (flock($fh, LOCK_EX)) {
            $fstat = fstat($fh);
            $fileSize = (int)($fstat['size'] ?? 0);

            // Bounded rotation (Windows-safe delete old before rename)
            if ($fileSize > self::MAX_SPOOL_BYTES) {
                flock($fh, LOCK_UN);
                fclose($fh);
                $oldSpool = $vaultDir . 'blackbox.spool.old';
                if (is_file($oldSpool)) @unlink($oldSpool);
                @rename($spoolFile, $oldSpool);
                $fh = @fopen($spoolFile, 'c+b');
                if (!$fh || !flock($fh, LOCK_EX)) {
                    if ($fh) fclose($fh);
                    return;
                }
                $fileSize = 0;
            }

            // Read tail to check for coalescing & chain hash
            $prevHash = '0000000000000000000000000000000000000000000000000000000000000000';
            $lastLinePos = -1;
            $lastRecord = null;

            if ($fileSize > 0) {
                $readLen = min($fileSize, 2048);
                fseek($fh, $fileSize - $readLen);
                $tail = (string)fread($fh, $readLen);
                $lines = explode("\n", $tail);
                $lines = array_values(array_filter($lines, static fn($l) => trim($l) !== ''));

                if (!empty($lines)) {
                    $lastLineRaw = end($lines);
                    $decoded = @json_decode($lastLineRaw, true);
                    if (is_array($decoded)) {
                        $lastRecord = $decoded;
                        if (!empty($decoded['hash'])) {
                            $prevHash = (string)$decoded['hash'];
                        }
                    }
                    $lastLinePos = $fileSize - strlen($lastLineRaw) - 1;
                    if ($lastLinePos < 0) $lastLinePos = 0;
                }
            }

            // TRUE FLOOD COALESCING: Same actor + rule + route within window -> increment count
            $canCoalesce = ($lastRecord !== null)
                && ($lastRecord['actor'] ?? '') === $actorMasked
                && ($lastRecord['rule_id'] ?? '') === $ruleId
                && ($lastRecord['route'] ?? '') === $routeFamily
                && ($lastRecord['decision'] ?? '') === $decision
                && ($nowTs - (int)strtotime($lastRecord['last_seen'] ?? '1970-01-01')) < self::COALESCE_WINDOW_SEC;

            $mKey = self::getMasterSecret($vaultDir);

            if ($canCoalesce && $lastLinePos >= 0) {
                $lastRecord['count'] = ((int)($lastRecord['count'] ?? 1)) + 1;
                $lastRecord['last_seen'] = $now;

                $prevOfLast = (string)($lastRecord['prev_hash'] ?? '0000000000000000000000000000000000000000000000000000000000000000');
                unset($lastRecord['hash']);
                $canonicalJson = json_encode($lastRecord, JSON_UNESCAPED_SLASHES);

                $updatedHash = ($mKey !== null)
                    ? hash_hmac('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:v1|' . $prevOfLast . '|' . $canonicalJson, $mKey)
                    : hash('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:UNAUTHENTICATED:v1|' . $prevOfLast . '|' . $canonicalJson);

                $lastRecord['hash'] = $updatedHash;

                fseek($fh, $lastLinePos);
                ftruncate($fh, $lastLinePos);
                fwrite($fh, json_encode($lastRecord, JSON_UNESCAPED_SLASHES) . "\n");
            } else {
                $record = [
                    'event_id'   => bin2hex(random_bytes(16)),
                    'timestamp'  => $now,
                    'sensor'     => 'ZEUS',
                    'event_type' => $eventType,
                    'severity'   => $severity,
                    'actor'      => $actorMasked,
                    'route'      => $routeFamily,
                    'method'     => strtoupper($method),
                    'rule_id'    => $ruleId,
                    'decision'   => $decision,
                    'reason'     => substr($reason, 0, 160),
                    'count'      => 1,
                    'first_seen' => $now,
                    'last_seen'  => $now,
                    'prev_hash'  => $prevHash
                ];

                $canonicalJson = json_encode($record, JSON_UNESCAPED_SLASHES);
                $recordHash = ($mKey !== null)
                    ? hash_hmac('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:v1|' . $prevHash . '|' . $canonicalJson, $mKey)
                    : hash('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:UNAUTHENTICATED:v1|' . $prevHash . '|' . $canonicalJson);

                $record['hash'] = $recordHash;

                fseek($fh, 0, SEEK_END);
                fwrite($fh, json_encode($record, JSON_UNESCAPED_SLASHES) . "\n");
            }

            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @chmod($spoolFile, 0600);
    }

    public static function getMetrics(string $vaultDir): array {
        $spoolFile = $vaultDir . 'blackbox.spool';
        if (!file_exists($spoolFile)) {
            return [
                'total_events' => 0,
                'block_decisions' => 0,
                'unique_actors' => 0,
                'spool_bytes' => 0,
                'chain_valid' => self::verifyChain($vaultDir),
                'recent_records' => []
            ];
        }

        $lines = array_filter(explode("\n", (string)file_get_contents($spoolFile)));
        $total = count($lines);
        $blocks = 0;
        $actors = [];
        $recent = [];

        foreach (array_slice($lines, -50) as $line) {
            $rec = @json_decode($line, true);
            if (is_array($rec)) {
                if (($rec['decision'] ?? '') === 'BLOCK') $blocks += (int)($rec['count'] ?? 1);
                $actors[$rec['actor'] ?? ''] = true;
                $recent[] = $rec;
            }
        }

        return [
            'total_events' => $total,
            'block_decisions' => $blocks,
            'unique_actors' => count($actors),
            'spool_bytes' => (int)@filesize($spoolFile),
            'chain_valid' => self::verifyChain($vaultDir),
            'recent_records' => array_reverse($recent)
        ];
    }

    private static function maskActor(string $ip): string {
        $packed = @inet_pton($ip);
        if ($packed === false) return $ip;

        if (strlen($packed) === 4) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
        }
        if (strlen($packed) === 16) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . '::';
        }
        return $ip;
    }

    /**
     * Drains spool records to WordPress Event Bus / XDR upon boot.
     */
    public static function drainToEventBus(string $vaultDir): int {
        $spoolFile = $vaultDir . 'blackbox.spool';
        if (!file_exists($spoolFile)) return 0;

        $fh = @fopen($spoolFile, 'c+b');
        if (!$fh) return 0;

        $drained = 0;
        if (flock($fh, LOCK_EX)) {
            $contents = (string)stream_get_contents($fh);
            $lines = array_values(array_filter(explode("\n", $contents), static fn($l) => trim($l) !== ''));

            $isValid = self::verifyLinesChain($lines, $vaultDir);
            if (!$isValid && !empty($lines)) {
                if (class_exists('\\VIS_Event_Bus')) {
                    \VIS_Event_Bus::emit(
                        'ZEUS',
                        'BLACKBOX_INTEGRITY_COMPROMISED',
                        'Pre-boot Blackbox rolling hash chain verification failed. Spool quarantined.',
                        ['vault_dir' => $vaultDir],
                        10
                    );
                }
                $quarantineFile = $vaultDir . 'blackbox.spool.corrupted_' . time();
                fseek($fh, 0);
                ftruncate($fh, 0);
                flock($fh, LOCK_UN);
                fclose($fh);
                @rename($spoolFile, $quarantineFile);
                return 0;
            }

            foreach ($lines as $line) {
                $rec = @json_decode($line, true);
                if (is_array($rec)) {
                    if (class_exists('\\VIS_Event_Bus')) {
                        \VIS_Event_Bus::emit(
                            'ZEUS',
                            $rec['rule_id'] ?? 'ADMISSION_VIOLATION',
                            $rec['reason'] ?? '',
                            [
                                'actor' => $rec['actor'] ?? '',
                                'route' => $rec['route'] ?? '',
                                'method' => $rec['method'] ?? '',
                                'decision' => $rec['decision'] ?? 'BLOCK',
                                'count' => (int)($rec['count'] ?? 1),
                                'hash' => $rec['hash'] ?? ''
                            ],
                            (int)($rec['severity'] ?? 7)
                        );
                    }
                    $drained++;
                }
            }

            fseek($fh, 0);
            ftruncate($fh, 0);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return $drained;
    }

    /**
     * Cryptographically verifies the tamper-evident hash chain of the spool.
     */
    public static function verifyChain(string $vaultDir): bool {
        $spoolFile = $vaultDir . 'blackbox.spool';
        if (!file_exists($spoolFile)) return true;

        $fh = @fopen($spoolFile, 'rb');
        if (!$fh) return true;
        $contents = '';
        if (flock($fh, LOCK_SH)) {
            $contents = (string)stream_get_contents($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);

        $lines = array_values(array_filter(explode("\n", $contents), static fn($l) => trim($l) !== ''));
        return self::verifyLinesChain($lines, $vaultDir);
    }

    /**
     * Verifies array of decoded spool lines for hash chain integrity.
     */
    public static function verifyLinesChain(array $lines, string $vaultDir): bool {
        if (empty($lines)) return true;

        $expectedPrev = '0000000000000000000000000000000000000000000000000000000000000000';
        $mKey = self::getMasterSecret($vaultDir);

        foreach ($lines as $line) {
            $rec = @json_decode($line, true);
            if (!is_array($rec) || empty($rec['hash'])) return false;

            $recordHash = $rec['hash'];
            $prevHash = (string)($rec['prev_hash'] ?? '');
            if (!hash_equals($expectedPrev, $prevHash)) return false;

            unset($rec['hash']);
            $canonicalJson = json_encode($rec, JSON_UNESCAPED_SLASHES);
            $computedHash = ($mKey !== null)
                ? hash_hmac('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:v1|' . $prevHash . '|' . $canonicalJson, $mKey)
                : hash('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:UNAUTHENTICATED:v1|' . $prevHash . '|' . $canonicalJson);

            if (!hash_equals($computedHash, $recordHash)) return false;
            $expectedPrev = $recordHash;
        }

        return true;
    }
}
