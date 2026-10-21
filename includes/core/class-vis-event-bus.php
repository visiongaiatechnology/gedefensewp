<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Event_Bus {

    private const MAX_MESSAGE_BYTES = 4000;
    private static bool $mounted = false;
    private static bool $retention_scheduled = false;
    /** @var array<string, bool> */
    private static array $request_seen = [];

    public static function init(): void {
        if (self::$mounted) {
            return;
        }

        self::$mounted = true;
        add_action('vgt_sentinel_event', [self::class, 'ingest'], 10, 1);
        add_action('vis_log_retention_cleanup', [self::class, 'cleanup']);
        if (function_exists('wp_next_scheduled') && !wp_next_scheduled('vis_log_retention_cleanup')) {
            wp_schedule_event(time() + 3600, 'daily', 'vis_log_retention_cleanup');
        }
    }

    /**
     * Bounded retention for high-volume human-readable log tables. XDR has its
     * own immutable retention; these tables are operational noise and otherwise
     * grow unbounded (observed: >576k Prometheus rows on one production site).
     */
    public static function cleanup(): void {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) return;

        $days = (int) apply_filters('vis_log_retention_days', 30);
        $days = max(7, min(365, $days));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * 86400));

        foreach (['vis_omega_logs', 'vis_prometheus_logs', 'vis_styx_logs', 'vis_nemesis_logs', 'vis_oracle_patterns'] as $suffix) {
            $table = $wpdb->prefix . $suffix;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) continue;
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE timestamp < %s LIMIT 20000", $cutoff));
        }
    }

    public static function emit(string $module, string $type, string $message, array $context = [], int $severity = 1): void {
        if (!self::$retention_scheduled && function_exists('wp_next_scheduled')) {
            self::$retention_scheduled = true;
            add_action('vis_log_retention_cleanup', [self::class, 'cleanup']);
            if (!wp_next_scheduled('vis_log_retention_cleanup')) {
                wp_schedule_event(time() + 3600, 'daily', 'vis_log_retention_cleanup');
            }
        }

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

        $ip = self::ip((string)($event['ip'] ?? ''));
        $module = self::token((string)($event['module'] ?? 'SYSTEM'), 32);
        $type = self::token((string)($event['type'] ?? 'EVENT'), 32);
        $severity = max(1, min(10, (int)($event['severity'] ?? 1)));
        $message = self::message((string)($event['message'] ?? ''));
        $context = is_array($event['context'] ?? null) ? $event['context'] : [];

        // A single request can pass the same finding through AEGIS, Trinity and
        // the legacy logger. Persist it once per module/type/message/IP.
        $dedupe_key = hash('sha256', $module . '|' . $type . '|' . $message . '|' . $ip . '|' . $severity);
        if (isset(self::$request_seen[$dedupe_key])) {
            return;
        }
        if (count(self::$request_seen) < 200) {
            self::$request_seen[$dedupe_key] = true;
        }

        // 1. DIRECT XDR EVENT FABRIC DELIVERY (Zero Loss / Own Dedupe Engine)
        $fabric = '\VisionGaia\GeDefense\Xdr\EventFabric';
        if (class_exists($fabric)) {
            $xdrPayload = $event;
            $xdrPayload['module'] = $module;
            $xdrPayload['type'] = $type;
            $xdrPayload['severity'] = $severity;
            $xdrPayload['ip'] = $ip;
            $xdrPayload['context'] = $context;
            $fabric::ingestLegacy($xdrPayload);
        }

        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) {
            return;
        }

        // 2. LEGACY HUMAN LOG FLOOD PROTECTION (Database Denial of Service prevention)
        $limit_key = 'vgt_log_flood_' . md5($ip);
        $attempts = (int) get_transient($limit_key);
        if ($attempts >= 10) {
            $notice_key = 'vgt_log_flood_notice_' . md5($ip);
            if (!get_transient($notice_key)) {
                set_transient($notice_key, 1, 3600);
                $wpdb->insert(
                    $wpdb->prefix . (defined('VIS_TABLE_LOGS') ? VIS_TABLE_LOGS : 'vis_omega_logs'),
                    [
                        'module'    => 'SYSTEM',
                        'type'      => 'THROTTLE',
                        'message'   => 'Log flood limit reached for IP. Subsequent legacy events suppressed for 60 seconds.',
                        'ip'        => $ip,
                        'severity'  => 5,
                        'timestamp' => current_time('mysql'),
                    ],
                    ['%s', '%s', '%s', '%s', '%d', '%s']
                );
            }
            return;
        }
        set_transient($limit_key, $attempts + 1, 60);

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

        // Security telemetry is written by GeDefense itself. Mark it as an
        // internal Morpheus action so the capability guard cannot flag its own
        // log tables as an "unassigned table" write of the requesting plugin.
        $morpheus_class = '\VisionGaia\GeDefense\Modules\Morpheus\Morpheus';
        $previous_internal = false;
        if (class_exists($morpheus_class)) {
            $previous_internal = $morpheus_class::$is_internal_action;
            $morpheus_class::$is_internal_action = true;
        }

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

        if (class_exists($morpheus_class)) {
            $morpheus_class::$is_internal_action = $previous_internal;
        }
    }

    private static function token(string $value, int $limit): string {
        $value = strtoupper(preg_replace('/[^A-Z0-9_:-]/i', '_', $value) ?? 'EVENT');
        $value = trim($value, '_');
        return substr($value !== '' ? $value : 'EVENT', 0, $limit);
    }

    private static function ip(string $value): string {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
        if ((defined('WP_CLI') && WP_CLI) || (defined('DOING_CRON') && DOING_CRON)) {
            return 'CLI';
        }
        return '0.0.0.0';
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
