<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Event_Bus {

    private const MAX_MESSAGE_BYTES = 4000;
    private static bool $mounted = false;

    public static function init(): void {
        if (self::$mounted) {
            return;
        }

        self::$mounted = true;
        add_action('vgt_sentinel_event', [self::class, 'ingest'], 10, 1);
    }

    public static function emit(string $module, string $type, string $message, array $context = [], int $severity = 1): void {
        self::ingest([
            'module'   => $module,
            'type'     => $type,
            'message'  => $message,
            'context'  => $context,
            'severity' => $severity,
            'ip'       => class_exists('VIS_Security') ? VIS_Security::client_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }

    public static function ingest(array $event): void {
        global $wpdb;

        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) {
            return;
        }

        $ip = self::ip((string)($event['ip'] ?? ''));

        // VGT-OMNI-GUARD: Log Flooding Protection (Database Denial of Service prevention)
        $limit_key = 'vgt_log_flood_' . md5($ip);
        $attempts = (int) get_transient($limit_key);
        if ($attempts >= 10) {
            if ($attempts === 10) {
                // Log warning once that this IP is throttled
                $wpdb->insert(
                    $wpdb->prefix . (defined('VIS_TABLE_LOGS') ? VIS_TABLE_LOGS : 'vis_omega_logs'),
                    [
                        'module'    => 'SYSTEM',
                        'type'      => 'THROTTLE',
                        'message'   => 'Log flood limit reached for IP. Subsequent events suppressed for 60 seconds.',
                        'ip'        => $ip,
                        'severity'  => 5,
                        'timestamp' => current_time('mysql'),
                    ],
                    ['%s', '%s', '%s', '%s', '%d', '%s']
                );
                set_transient($limit_key, 11, 60); 
            }
            return;
        }
        set_transient($limit_key, $attempts + 1, 60);

        $module = self::token((string)($event['module'] ?? 'SYSTEM'), 32);
        $type = self::token((string)($event['type'] ?? 'EVENT'), 32);
        $severity = max(1, min(10, (int)($event['severity'] ?? 1)));
        $message = self::message((string)($event['message'] ?? ''));
        $context = is_array($event['context'] ?? null) ? $event['context'] : [];

        if ($context !== []) {
            try {
                $encoded = wp_json_encode(self::scrub_context($context), JSON_THROW_ON_ERROR);
                $message .= ' | ctx=' . $encoded;
            } catch (\Throwable $e) {
                $message .= ' | ctx=unavailable';
            }
        }

        if (strlen($message) > self::MAX_MESSAGE_BYTES) {
            $message = substr($message, 0, self::MAX_MESSAGE_BYTES);
        }

        // Strict XSS Sanitization: Strip HTML/Script tags to prevent stored script execution in logging dashboards
        $message = wp_strip_all_tags($message);

        $table = $wpdb->prefix . (defined('VIS_TABLE_LOGS') ? VIS_TABLE_LOGS : 'vis_omega_logs');
        $wpdb->insert(
            $table,
            [
                'module'    => $module,
                'type'      => $type,
                'message'   => $message,
                'ip'        => $ip,
                'severity'  => $severity,
                'timestamp' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s']
        );
    }

    private static function token(string $value, int $limit): string {
        $value = strtoupper(preg_replace('/[^A-Z0-9_:-]/i', '_', $value) ?? 'EVENT');
        $value = trim($value, '_');
        return substr($value !== '' ? $value : 'EVENT', 0, $limit);
    }

    private static function ip(string $value): string {
        return filter_var($value, FILTER_VALIDATE_IP) ? $value : '0.0.0.0';
    }

    private static function message(string $value): string {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
        return trim($value) !== '' ? trim($value) : 'Event recorded.';
    }

    private static function scrub_context(array $context): array {
        $safe = [];
        foreach ($context as $key => $value) {
            $safe_key = self::token((string)$key, 48);
            if (is_scalar($value) || $value === null) {
                $safe[$safe_key] = self::scalar_value($value);
                continue;
            }
            if (is_array($value)) {
                $safe[$safe_key] = self::scrub_context(array_slice($value, 0, 20, true));
            }
        }
        return $safe;
    }

    private static function scalar_value(mixed $value): string|int|float|bool|null {
        if (!is_string($value)) {
            return $value;
        }

        $redacted = preg_replace('/(api[_-]?key|token|secret|password|authorization)[^,\s]*/i', '$1:[redacted]', $value) ?? $value;
        return strlen($redacted) > 256 ? substr($redacted, 0, 256) : $redacted;
    }
}
