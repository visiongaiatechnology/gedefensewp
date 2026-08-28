<?php
// STATUS: PLATIN
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * Produces a portable, zero-dependency Sentinel telemetry snapshot.
 * Network identifiers are export-scoped pseudonyms; secrets never leave WordPress.
 */
final class VIS_Sentinel_Export {
    private const ACTION = 'vgt_export_sentinel_data';
    private const NONCE_ACTION = 'vgt_sentinel_export';
    private const BATCH_ROWS = 500;

    private string $pseudonym_key;

    public function __construct(string $export_id) {
        $this->pseudonym_key = hash_hmac('sha256', $export_id, wp_salt('auth'), true);
    }

    public static function mount(): void {
        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
    }

    public static function action(): string {
        return self::ACTION;
    }

    public static function nonce_action(): string {
        return self::NONCE_ACTION;
    }

    public static function handle(): void {
        $export_file = null;
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new SecurityException('Export method validation failed.');
            }
            if (!is_user_logged_in() || !current_user_can('manage_options')) {
                throw new SecurityException('Export authorization validation failed.');
            }

            $nonce = isset($_POST['_wpnonce']) && is_string($_POST['_wpnonce'])
                ? sanitize_text_field(wp_unslash($_POST['_wpnonce']))
                : '';
            if ($nonce === '' || wp_verify_nonce($nonce, self::NONCE_ACTION) !== 1) {
                throw new SecurityException('CSRF token validation failed.');
            }

            $user_id = get_current_user_id();
            $rate_key = 'vgt_export_lock_' . $user_id;
            if (get_transient($rate_key)) {
                throw new ValidationException('Please wait before generating another export.');
            }
            set_transient($rate_key, 1, 10);

            $export_id = bin2hex(random_bytes(16));
            $exporter = new self($export_id);
            $export_file = $exporter->generate($export_id);
            $real_size = filesize($export_file);
            if ($real_size === false || $real_size === 0) {
                throw new StorageException('Export artifact size validation failed.');
            }

            nocache_headers();
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="vgt-sentinel-export-' . gmdate('Ymd-His') . '.json"');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: ' . $real_size);
            $sent = readfile($export_file);
            @unlink($export_file);
            if ($sent === false || $sent !== $real_size) {
                throw new StorageException('Export artifact transfer failed.');
            }
            exit;
        } catch (ValidationException $e) {
            self::remove_export_file($export_file);
            wp_die(esc_html($e->getMessage()), 'VGT Export', ['response' => 429]);
        } catch (SecurityException $e) {
            self::remove_export_file($export_file);
            error_log('[SEC] ' . $e->getMessage());
            wp_die('Request rejected for security reasons.', 'VGT Export', ['response' => 403]);
        } catch (StorageException $e) {
            self::remove_export_file($export_file);
            error_log('[STORAGE] ' . $e->getMessage());
            wp_die('A server error occurred.', 'VGT Export', ['response' => 500]);
        } catch (Throwable $e) {
            self::remove_export_file($export_file);
            error_log('[FATAL] ' . $e->getMessage());
            wp_die('Critical system fault.', 'VGT Export', ['response' => 500]);
        }
    }

    private static function remove_export_file(?string $path): void {
        if ($path === null || !defined('VIS_VAULT_DIR')) {
            return;
        }
        $resolved_dir = realpath(VIS_VAULT_DIR);
        $resolved_file = realpath($path);
        if ($resolved_dir !== false
            && $resolved_file !== false
            && str_starts_with($resolved_file, $resolved_dir . DIRECTORY_SEPARATOR)
            && is_file($resolved_file)) {
            @unlink($resolved_file);
        }
    }

    private function generate(string $export_id): string {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
            throw new StorageException('Export database adapter unavailable.');
        }

        $resolved_dir = realpath(VIS_VAULT_DIR);
        if ($resolved_dir === false || !is_dir($resolved_dir) || !is_writable($resolved_dir)) {
            throw new StorageException('Export vault unavailable.');
        }
        $export_file = $resolved_dir . DIRECTORY_SEPARATOR . 'sentinel-export-' . bin2hex(random_bytes(16)) . '.json';
        if (!str_starts_with($export_file, $resolved_dir . DIRECTORY_SEPARATOR)) {
            throw new SecurityException('Export path escaped jail.');
        }

        $tables = [
            'events'     => $wpdb->prefix . (defined('VIS_TABLE_LOGS') ? VIS_TABLE_LOGS : 'vis_omega_logs'),
            'bans'       => $wpdb->prefix . (defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_apex_bans'),
            'oracle'     => $wpdb->prefix . 'vis_oracle_patterns',
            'nemesis'    => $wpdb->prefix . 'vis_nemesis_logs',
            'prometheus' => $wpdb->prefix . 'vis_prometheus_logs',
            'styx'       => $wpdb->prefix . 'vis_styx_logs',
        ];

        $header = [
            'schema' => 'vgt-sentinel-export/1',
            'export' => [
                'id'               => $export_id,
                'generated_at_utc' => gmdate('c'),
                'privacy_mode'     => 'pseudonymized',
                'completeness'     => 'all_rows',
            ],
            'environment' => [
                'sentinel_version' => defined('VIS_VERSION') ? VIS_VERSION : 'unknown',
                'wordpress_version' => get_bloginfo('version'),
                'php_version'       => PHP_VERSION,
                'multisite'         => is_multisite(),
                'timezone'          => wp_timezone_string(),
            ],
            'configuration' => $this->sanitize([
                'sentinel'       => get_option('vis_config', []),
                'zeus'           => get_option('vis_zeus_config', []),
                'trinity'        => get_option('vis_trinity_config', []),
                'prometheus'     => get_option('vis_prometheus_config', []),
                'learning_regex' => get_option('vgt_learning_regex', ''),
            ]),
            'integrity' => $this->sanitize(get_option('vis_scan_report', [])),
        ];

        $stream = @fopen($export_file, 'xb');
        if ($stream === false) {
            throw new StorageException('Export artifact creation failed.');
        }

        try {
            if (!@chmod($export_file, 0600) || !flock($stream, LOCK_EX)) {
                throw new StorageException('Export artifact permission enforcement failed.');
            }

            $header_json = wp_json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->write($stream, substr($header_json, 0, -1) . ',"datasets":{');
            $first_table = true;
            foreach ($tables as $name => $table) {
                if (!$first_table) {
                    $this->write($stream, ',');
                }
                $first_table = false;
                $this->write($stream, wp_json_encode($name, JSON_THROW_ON_ERROR) . ':');
                $this->write_table($stream, $table);
            }
            $this->write($stream, '}}');

            if (!fflush($stream)) {
                throw new StorageException('Export artifact flush failed.');
            }
            if (function_exists('fsync') && !fsync($stream)) {
                throw new StorageException('Export artifact synchronization failed.');
            }
            flock($stream, LOCK_UN);
            fclose($stream);
            return $export_file;
        } catch (Throwable $e) {
            flock($stream, LOCK_UN);
            fclose($stream);
            @unlink($export_file);
            throw $e;
        }
    }

    /** @param resource $stream */
    private function write_table($stream, string $table): void {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if (!is_string($exists) || !hash_equals($table, $exists)) {
            $this->write($stream, '{"available":false,"total_rows":0,"rows":[],"exported_rows":0}');
            return;
        }

        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
        $this->write($stream, '{"available":true,"total_rows":' . $total . ',"rows":[');

        $cursor = PHP_INT_MAX;
        $exported = 0;
        $first_row = true;
        do {
            $query = $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE `id` < %d ORDER BY `id` DESC LIMIT %d",
                $cursor,
                self::BATCH_ROWS
            );
            $rows = $wpdb->get_results($query, ARRAY_A);
            if (!is_array($rows)) {
                throw new StorageException('Export dataset query failed.');
            }

            foreach ($rows as $row) {
                $safe_row = $this->sanitize(is_array($row) ? $row : []);
                if (!$first_row) {
                    $this->write($stream, ',');
                }
                $first_row = false;
                $this->write(
                    $stream,
                    wp_json_encode($safe_row, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                );
                $cursor = min($cursor, (int)($row['id'] ?? 0));
                $exported++;
            }
        } while (count($rows) === self::BATCH_ROWS && $cursor > 0);

        $this->write($stream, '],"exported_rows":' . $exported . '}');
    }

    /** @param resource $stream */
    private function write($stream, string $data): void {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $result = fwrite($stream, substr($data, $written));
            if ($result === false || $result === 0) {
                throw new StorageException('Export artifact write failed.');
            }
            $written += $result;
        }
    }

    private function sanitize(mixed $value, string $key = ''): mixed {
        if (preg_match('/(?:api[_-]?key|secret|password|authorization|nonce|token|salt|private[_-]?key)/i', $key)) {
            return '[redacted]';
        }
        if (preg_match('/^(?:ip|ip_address)$/i', $key) && is_scalar($value)) {
            return $this->pseudonymize((string)$value, 'ip');
        }
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $child_key => $child_value) {
                $safe[(string)$child_key] = $this->sanitize($child_value, (string)$child_key);
            }
            return $safe;
        }
        if (!is_string($value)) {
            return $value;
        }

        $value = preg_replace_callback(
            '/(?<![A-Fa-f0-9:])(?:\d{1,3}\.){3}\d{1,3}(?![A-Fa-f0-9:])/',
            fn(array $match): string => filter_var($match[0], FILTER_VALIDATE_IP)
                ? $this->pseudonymize($match[0], 'ip')
                : $match[0],
            $value
        ) ?? $value;
        $value = preg_replace_callback(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            fn(array $match): string => $this->pseudonymize(strtolower($match[0]), 'email'),
            $value
        ) ?? $value;

        if (preg_match('/(?:uri|url)$/i', $key)) {
            $value = explode('?', $value, 2)[0];
            $value = explode('#', $value, 2)[0];
        }
        return strlen($value) > 8000 ? substr($value, 0, 8000) : $value;
    }

    private function pseudonymize(string $value, string $domain): string {
        return $domain . '_' . substr(hash_hmac('sha256', $domain . "\0" . $value, $this->pseudonym_key), 0, 20);
    }
}
