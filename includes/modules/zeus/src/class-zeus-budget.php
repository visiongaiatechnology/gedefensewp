<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Budget {

    public const DEFAULT_IP_RATE_LIMIT = 180; // req / 60s
    public const DEFAULT_SUBNET_RATE_LIMIT = 450; // req / 60s
    public const DEFAULT_AUTH_RATE_LIMIT = 10; // auth fails / 3600s
    public const DEFAULT_WINDOW = 60;

    /**
     * Evaluates request rate and resource ceilings using zero-allocation APCu / bounded file cache.
     * ZERO PHP sleep tarpits (avoids worker starvation self-DoS).
     *
     * @param string $ip
     * @param string $path
     * @param string $method
     * @param array<string, mixed> $config
     * @param string $vaultDir
     * @return array{rule_id: string, reason: string, severity: int, event_type: string, status_code: int}|null
     */
    public static function evaluate(
        string $ip,
        string $path,
        string $method,
        array $config,
        string $vaultDir
    ): ?array {
        if (!($config['budget_enabled'] ?? true)) {
            return null;
        }

        $now = time();
        $ipKey = 'vgt_zb_ip_' . md5($ip);
        $subnet = (str_contains($ip, ':'))
            ? implode(':', array_slice(explode(':', $ip), 0, 4)) . '::'
            : (string)preg_replace('/\.\d+$/', '.0', $ip);
        $subnetKey = 'vgt_zb_sub_' . md5($subnet);

        $ipLimit = (int)($config['budget_ip_limit'] ?? self::DEFAULT_IP_RATE_LIMIT);
        $subnetLimit = (int)($config['budget_subnet_limit'] ?? self::DEFAULT_SUBNET_RATE_LIMIT);

        // 1. IP RATE CEILING
        $ipCount = self::incrementCounter($ipKey, self::DEFAULT_WINDOW, $vaultDir);
        if ($ipCount > $ipLimit) {
            $mode = (string)($config['budget_action_mode'] ?? 'THROTTLE');
            return [
                'rule_id' => 'BUDGET_ACTOR_CEILING_EXCEEDED',
                'reason' => sprintf('IP request rate (%d/%ds) exceeded allocation budget (%d).', $ipCount, self::DEFAULT_WINDOW, $ipLimit),
                'severity' => 7,
                'event_type' => 'ZEUS.ROUTE_BUDGET_EXCEEDED',
                'status_code' => $mode === 'TEMPORARY_REJECT' ? 403 : 429
            ];
        }

        // 2. SUBNET RATE CEILING
        $subnetCount = self::incrementCounter($subnetKey, self::DEFAULT_WINDOW, $vaultDir);
        if ($subnetCount > $subnetLimit) {
            $mode = (string)($config['budget_action_mode'] ?? 'THROTTLE');
            return [
                'rule_id' => 'BUDGET_SUBNET_CEILING_EXCEEDED',
                'reason' => sprintf('Subnet request rate (%d/%ds) exceeded allocation budget (%d).', $subnetCount, self::DEFAULT_WINDOW, $subnetLimit),
                'severity' => 7,
                'event_type' => 'ZEUS.ROUTE_BUDGET_EXCEEDED',
                'status_code' => $mode === 'TEMPORARY_REJECT' ? 403 : 429
            ];
        }

        return null;
    }

    /**
     * Bounded atomic counter increment with TTL.
     */
    public static function incrementCounter(string $key, int $ttl, string $vaultDir): int {
        if (function_exists('apcu_inc')) {
            $success = false;
            $val = apcu_inc($key, 1, $success, $ttl);
            if ($success && is_int($val)) {
                return $val;
            }
            apcu_store($key, 1, $ttl);
            return 1;
        }

        // Bounded file-based fallback
        $cacheDir = $vaultDir . 'cache/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0700, true);
        }

        $file = $cacheDir . $key . '.php';
        $now = time();
        $count = 1;

        $fh = @fopen($file, 'c+b');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                $stat = fstat($fh);
                $mtime = $stat['mtime'] ?? $now;
                if (($now - $mtime) < $ttl) {
                    $size = $stat['size'] ?? 12;
                    $count = max(0, $size - 12) + 1;
                }
                ftruncate($fh, 0);
                rewind($fh);
                $payload = "<?php die;?>" . str_repeat('.', min($count, 99999));
                fwrite($fh, $payload);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
            @chmod($file, 0600);
        }

        return $count;
    }
}
