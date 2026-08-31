<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Styx;

if (!defined('ABSPATH')) exit;

/**
 * MODULE: STYX (Outbound Executioner & Exfiltration Shield)
 * STATUS: DIAMANT VGT SUPREME (Adversarial Fixes Applied & Core Telemetry Control)
 * ARCHITECT: VGT Intelligence System
 */
final class Styx {

    private static ?self $instance = null;
    
    // O(1) Lookup Tables & State Registry
    private array $exact_hosts = [];
    private array $wildcard_hosts = [];
    private bool $is_initialized = false;
    private bool $is_enabled = false;
    private bool $audit_mode = false;
    private bool $block_wp_telemetry = false;
    
    // Memory Buffer (Deferred I/O)
    private array $log_buffer = [];
    private const MAX_BUFFER_SIZE = 100; // Verhindert RAM-Exhaustion bei Outbound-Endlosschleifen

    private function __construct() {
        // Priority Hijacking Defense (-9999 macht STYX zum absoluten First Responder)
        add_filter('pre_http_request', [$this, 'intercept_outbound'], -9999, 3);
        
        // Deferred I/O Logging am Ende des PHP-Lebenszyklus
        add_action('shutdown', [$this, 'flush_logs']);
    }

    public static function get_instance(): self {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    // Lazy Loading State-Engine
    private function initialize(): void {
        if ($this->is_initialized) return;

        // Lade Master-Config und setze System-State
        $opt = get_option('vis_config', []);
        $this->is_enabled = !empty($opt['styx_enabled']);
        $this->audit_mode = !empty($opt['styx_audit_mode']);
        $this->block_wp_telemetry = !empty($opt['styx_block_wp_telemetry']); // VGT: Telemetry Interlock
        
        $this->is_initialized = true;

        // O(1) Short-Circuit: Abbruch der Initialisierung, falls das System auf STANDBY steht
        if (!$this->is_enabled) return;

        // Frontend-sicheres Schema-Enforcement gegen Race-Conditions
        if (!get_option('vgt_styx_schema_ready')) {
            $this->enforce_schema();
        }

        // VGT KOGNITION: Native WP-Hosts nur zulassen, wenn das Block-Flag nicht gesetzt ist
        $hosts = [];
        if (!$this->block_wp_telemetry) {
            $hosts = ['api.wordpress.org', 'downloads.wordpress.org'];
        }
        
        if (defined('WP_ACCESSIBLE_HOSTS')) {
            $extra = explode(',', WP_ACCESSIBLE_HOSTS);
            $hosts = array_merge($hosts, array_map('trim', $extra));
        }

        if (!empty($opt['styx_whitelist'])) {
            $hosts = array_merge($hosts, array_filter(array_map('trim', explode("\n", $opt['styx_whitelist']))));
        }

        $unique_hosts = array_unique($hosts);

        foreach ($unique_hosts as $host) {
            $host = strtolower(trim($host));
            if (str_starts_with($host, '*.')) {
                $this->wildcard_hosts[] = substr($host, 2);
            } else {
                $this->exact_hosts[$host] = true;
            }
        }
    }

    public function intercept_outbound($preempt, array $args, string $url) {
        $this->initialize();

        // MASTER KILL-SWITCH: Absolute Ignoranz, wenn STYX deaktiviert ist
        if (!$this->is_enabled) return $preempt;

        // SSRF-Bypass & Obfuscation Defense (wp_parse_url & Sanitization)
        $decoded_url = urldecode($url);
        $parsed_host = wp_parse_url($decoded_url, PHP_URL_HOST);
        
        if (!$parsed_host) return $preempt;

        // Normalisierung gegen Trailing-Dots, Case-Mismatch und Octal/Hex/Null-Byte Injections
        $host = preg_replace('/[^a-zA-Z0-9.-]/', '', $parsed_host);
        $host = rtrim(strtolower(trim($host)), '.');
        
        if (empty($host)) return $preempt;

        $is_allowed = $this->check_host($host);
        $origin = $this->detect_origin();
        
        // Logge das Intent: BLOCKED wird für korrekte UI-Metriken genutzt
        $status = $is_allowed ? 'ALLOWED' : 'BLOCKED';
        $this->queue_log($host, $url, $origin, $status);

        if (!$is_allowed) {
            // AUDIT-PASS-THROUGH: Loggen, aber den Request ungehindert durchschleusen
            if ($this->audit_mode) return $preempt;

            // VGT NEMESIS INTEGRATION: Poisoned Response bei Exfiltration
            if (class_exists('\VisionGaia\GeDefense\Modules\Nemesis\Nemesis')) {
                 return $this->poison_response();
            }
            return new \WP_Error('vgt_styx_blocked', "VGT STYX: Outbound request to $host is strictly forbidden.");
        }

        return $preempt;
    }

    private function check_host(string $host): bool {
        // O(1) Exact Match
        if (isset($this->exact_hosts[$host])) return true;
        
        // Strict Wildcard Subdomain Matching (*.example.com matches sub.example.com and example.com, but NOT evil-example.com)
        foreach ($this->wildcard_hosts as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) return true;
        }
        
        return false;
    }

    private function detect_origin(): string {
        // Deep-Recursion Protection: Maximal 20 Frames werden analysiert
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        
        // Dynamische Architektur-Auflösung (Bedrock/Trellis Support)
        $plugin_dir = defined('WP_PLUGIN_DIR') ? wp_normalize_path(WP_PLUGIN_DIR) : wp_normalize_path(WP_CONTENT_DIR . '/plugins');
        $theme_dir  = function_exists('get_theme_root') ? wp_normalize_path(get_theme_root()) : wp_normalize_path(WP_CONTENT_DIR . '/themes');
        
        foreach ($trace as $step) {
            if (!isset($step['file'])) continue;
            
            $file = wp_normalize_path($step['file']);
            
            if (str_starts_with($file, $plugin_dir)) {
                $rel_path = trim(str_replace($plugin_dir, '', $file), '/');
                $parts = explode('/', $rel_path);
                return 'PLUGIN: ' . $parts[0];
            }
            
            if (str_starts_with($file, $theme_dir)) {
                $rel_path = trim(str_replace($theme_dir, '', $file), '/');
                $parts = explode('/', $rel_path);
                return 'THEME: ' . $parts[0];
            }
        }
        return 'WP_CORE / UNKNOWN';
    }

    private function poison_response(): array {
        return [
            'headers'  => [],
            'body'     => json_encode([
                'status'  => 'VGT_TRAPPED',
                'message' => 'Your exfiltration attempt was intercepted and neutralized by VGT Sentinel.',
                'canary'  => bin2hex(random_bytes(32))
            ]),
            'response' => ['code' => 403, 'message' => 'Forbidden'],
            'cookies'  => [],
            'filename' => null
        ];
    }

    private function queue_log(string $host, string $url, string $origin, string $status): void {
        if (count($this->log_buffer) >= self::MAX_BUFFER_SIZE) return;
        
        $this->log_buffer[] = [
            'timestamp' => current_time('mysql'),
            'host'      => $host,
            'url'       => substr($url, 0, 255),
            'origin'    => $origin,
            'status'    => $status
        ];
    }

    public function flush_logs(): void {
        if (empty($this->log_buffer)) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'vis_styx_logs';
        $suppress = $wpdb->suppress_errors();
        
        // Bulk Insert für O(1) Datenbank-Transaktion (Type-Casting gehärtet)
        $query = "INSERT INTO {$table} (timestamp, host, url, origin, status) VALUES ";
        $values = [];
        $placeholders = [];
        
        foreach ($this->log_buffer as $log) {
            $placeholders[] = "(%s, %s, %s, %s, %s)";
            // Striktes Type-Casting zur Vermeidung von SQL-Prepare Instabilitäten
            $values[] = (string) $log['timestamp'];
            $values[] = (string) $log['host'];
            $values[] = (string) $log['url'];
            $values[] = (string) $log['origin'];
            $values[] = (string) $log['status'];
        }
        
        $query .= implode(', ', $placeholders);
        $prepared_query = $wpdb->prepare($query, $values);
        
        if ($prepared_query) {
            $wpdb->query($prepared_query);
        }
        
        $wpdb->suppress_errors($suppress);
        $this->log_buffer = []; // Clear RAM
    }

    public function enforce_schema(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'vis_styx_logs';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            host varchar(255) NOT NULL,
            url varchar(255) NOT NULL,
            origin varchar(100) NOT NULL,
            status varchar(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY host_idx (host),
            KEY status_idx (status)
        ) $charset;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('vgt_styx_schema_ready', true);
    }
}

Styx::get_instance();
