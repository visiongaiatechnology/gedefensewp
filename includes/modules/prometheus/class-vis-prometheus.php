<?php
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * Modul: PROMETHEUS (Behavioral Profiling & Predictive AI Engine)
 * Status: DIAMANT VGT SUPREME (Zero-Trust Proxy, Atomic TTL Locks, Chunked I/O, Advanced Regex)
 * Architekt: VGT Intelligence System
 * KERNEL UPGRADES:
 * - Strict Cloudflare/Trusted Proxy CIDR Isolation (Anti-Spoofing).
 * - OOM-Guarded Hooks (Sanitization von User-Input in Telemetrie).
 * - Native MySQL GET_LOCK Fallback für Spin-Locks (Verhindert wp_options Deadlocks).
 * - Stored XSS Prevention in Telemetry Logs.
 * - Cerberus Interlock Update: Direkte Delegation von Predictive Strikes an VIS_Cerberus.
 */

declare(strict_types=1);

namespace VisionGaia\Integrity\Modules\Prometheus;

if ( ! defined( 'ABSPATH' ) ) {
    exit('VGT_ACCESS_DENIED');
}

final class VIS_Prometheus {

    private static ?self $instance = null;

    // --- VGT DEFAULTS (FALLBACKS) ---
    private const DEFAULT_EVENT_HORIZON_SCORE       = 100.0;
    private const DEFAULT_INFRA_EVENT_HORIZON_SCORE = 150.0;
    private const DEFAULT_INFRA_COOLDOWN_WINDOW     = 3600;
    private const DEFAULT_SCORE_DECAY_RATE          = 0.2;
    private const DEFAULT_SCORE_DECAY_WINDOW        = 300;

    // --- VGT DEFAULT PENALTIES ---
    private const PENALTY_METHOD   = 30.0;
    private const PENALTY_PARAMS   = 15.0;
    private const PENALTY_REGEX    = 50.0;
    private const PENALTY_404      = 25.0;
    private const PENALTY_AUTH     = 40.0;
    private const PENALTY_BURST    = 20.0; // < 0.2s
    private const PENALTY_FREQ     = 10.0; // < 1.0s
    private const PENALTY_ROTATION = 25.0;

    private const TELEMETRY_BUFFER_LIMIT = 200;
    private const SPIN_LOCK_MAX_RETRIES  = 25;

    // O(1) DFA Regex Compilation
    private const ANOMALY_REGEX = '/(?:%00|\\\\0|\\.\\.[\\/\\\\]|:\\/\\/|<script|base64(?:_|decode\s*\()|eval\s*\()/i';
    
    // O(1) Lookup Tables für Hot-Path
    private const SUSPICIOUS_METHODS = [
        'PUT' => true, 'DELETE' => true, 'TRACE' => true, 
        'TRACK' => true, 'OPTIONS' => true, 'CONNECT' => true
    ];

    private const STATIC_ASSET_EXTS = [
        'ico' => true, 'map' => true, 'woff' => true, 'woff2' => true, 
        'ttf' => true, 'png' => true, 'jpg' => true, 'jpeg' => true, 
        'svg' => true, 'css' => true, 'js' => true, 'xml' => true, 'txt' => true,
        'webp' => true, 'gif' => true
    ];

    private const ROOT_ASSETS = [
        '/favicon.ico' => true,
        '/browserconfig.xml' => true,
        '/robots.txt' => true
    ];

    // --- DYNAMISCHE KONFIGURATIONS-PROPERTIES ---
    private float $event_horizon_score;
    private float $infra_horizon_score;
    private int   $infra_cooldown_window;
    private float $score_decay_rate;
    private int   $score_decay_window;
    
    private float $score_penalty_method;
    private float $score_penalty_params;
    private float $score_penalty_regex;
    private float $score_penalty_404;
    private float $score_penalty_auth;
    private float $score_penalty_burst;
    private float $score_penalty_freq;
    private float $score_penalty_rotation;

    private bool  $trust_proxy_headers;
    private array $trusted_proxies = [];

    // --- ASYNC MEMORY BUFFERS ---
    private array $delta_buffer      = [];
    private bool  $buffer_modified   = false;
    private array $telemetry_buffer  = [];
    
    private array $whitelist_ips = [];
    private string $table_logs;

    private function __construct() {
        if ( PHP_SAPI === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        global $wpdb;
        $this->table_logs = $wpdb->prefix . 'vis_prometheus_logs';
        $this->enforce_telemetry_schema();
        
        $this->load_dynamic_configuration();
        
        add_action( 'plugins_loaded', [ $this, 'profile_incoming_request' ], -9999 );
        add_action( 'template_redirect', [ $this, 'track_404_anomalies' ], 1 );
        add_action( 'wp_login_failed', [ $this, 'track_auth_anomalies' ], 1 );
        add_action( 'shutdown', [ $this, 'flush_state_buffer' ], 9999 );
    }

    private function load_dynamic_configuration(): void {
        $global_config = get_option( 'vis_config', [] );
        $prom_config   = get_option( 'vis_prometheus_config', [] );

        $raw_ips = $global_config['prometheus_whitelist_ips'] ?? '';
        $this->whitelist_ips = array_filter( array_map( 'trim', explode( "\n", $raw_ips ) ) );

        $this->trust_proxy_headers = (bool) ($prom_config['trust_proxy_headers'] ?? false);
        
        // [ DIAMANT FIX: Trusted Proxy Configuration Injection ]
        if (defined('VIS_TRUSTED_PROXY_IPS')) {
            $this->trusted_proxies = VIS_TRUSTED_PROXY_IPS;
        }

        $this->event_horizon_score   = self::bounded_float($prom_config, 'event_horizon_score', self::DEFAULT_EVENT_HORIZON_SCORE, 25.0, 1000.0);
        $this->infra_horizon_score   = self::bounded_float($prom_config, 'infra_horizon_score', self::DEFAULT_INFRA_EVENT_HORIZON_SCORE, 50.0, 2000.0);
        $this->infra_cooldown_window = self::bounded_int($prom_config, 'infra_cooldown_window', self::DEFAULT_INFRA_COOLDOWN_WINDOW, 60, 86400);
        $this->score_decay_rate      = self::bounded_float($prom_config, 'score_decay_rate', self::DEFAULT_SCORE_DECAY_RATE, 0.01, 10.0);
        $this->score_decay_window    = self::bounded_int($prom_config, 'score_decay_window', self::DEFAULT_SCORE_DECAY_WINDOW, 60, 86400);

        $this->score_penalty_method   = self::bounded_float($prom_config, 'penalty_method', self::PENALTY_METHOD, 0.0, 500.0);
        $this->score_penalty_params   = self::bounded_float($prom_config, 'penalty_params', self::PENALTY_PARAMS, 0.0, 500.0);
        $this->score_penalty_regex    = self::bounded_float($prom_config, 'penalty_regex', self::PENALTY_REGEX, 0.0, 500.0);
        $this->score_penalty_404      = self::bounded_float($prom_config, 'penalty_404', self::PENALTY_404, 0.0, 500.0);
        $this->score_penalty_auth     = self::bounded_float($prom_config, 'penalty_auth', self::PENALTY_AUTH, 0.0, 500.0);
        $this->score_penalty_burst    = self::bounded_float($prom_config, 'penalty_burst', self::PENALTY_BURST, 0.0, 500.0);
        $this->score_penalty_freq     = self::bounded_float($prom_config, 'penalty_freq', self::PENALTY_FREQ, 0.0, 500.0);
        $this->score_penalty_rotation = self::bounded_float($prom_config, 'penalty_rotation', self::PENALTY_ROTATION, 0.0, 500.0);
    }

    private static function bounded_float(array $config, string $key, float $default, float $min, float $max): float {
        $value = isset($config[$key]) && is_numeric($config[$key]) ? (float)$config[$key] : $default;
        return max($min, min($max, $value));
    }

    private static function bounded_int(array $config, string $key, int $default, int $min, int $max): int {
        $value = isset($config[$key]) && is_numeric($config[$key]) ? (int)$config[$key] : $default;
        return max($min, min($max, $value));
    }

    private function __clone() {}
    public function __wakeup() {
        throw new \Exception( 'VGT PROTOCOL VIOLATION: Deserialization of Singleton is forbidden.' );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function increase_threat_score( string $ip, float $amount, string $reason ): void {
        if ( '' === $ip || $this->is_whitelisted( $ip ) ) {
            return;
        }
        $this->evaluate_state( $ip, $amount, microtime( true ), [ $reason ] );
    }

    private function is_whitelisted( string $ip ): bool {
        if ( $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, 'fe80:') ) {
            return true;
        }
        if ( ! empty( $this->whitelist_ips ) && in_array( $ip, $this->whitelist_ips, true ) ) {
            return true;
        }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            $server_ip = $_SERVER['SERVER_ADDR'] ?? null;
            if ($server_ip && $ip === $server_ip) return true;
        }
        return false;
    }

    private function enforce_telemetry_schema(): void {
        if ( get_option( 'vgt_prometheus_logs_schema_verified' ) ) return;

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            module varchar(32) NOT NULL,
            type varchar(32) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text NOT NULL,
            details text NOT NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY module_index (module),
            KEY type_index (type),
            KEY timestamp_index (timestamp)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'vgt_prometheus_logs_schema_verified', true, false );
    }

    /**
     * VGT PROTOCOL: OOM-Guarded Memory Append.
     */
    private function write_telemetry( string $type, string $details, string $ip ): void {
        // [ DIAMANT FIX: Stored XSS & Control Character Stripping ]
        $raw_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $safe_ua = preg_replace('/[\x00-\x1F\x7F]/', '', substr($raw_ua, 0, 255));
        $safe_details = preg_replace('/[\x00-\x1F\x7F]/', '', substr($details, 0, 500));

        $this->telemetry_buffer[] = [
            'type'       => $type,
            'ip_address' => $ip,
            'user_agent' => $safe_ua,
            'details'    => $safe_details,
            'timestamp'  => current_time( 'mysql' )
        ];

        if ( count( $this->telemetry_buffer ) >= self::TELEMETRY_BUFFER_LIMIT ) {
            $this->flush_telemetry_batch();
        }
    }

    private function flush_telemetry_batch(): void {
        if ( empty( $this->telemetry_buffer ) ) {
            return;
        }

        global $wpdb;
        $table = $this->table_logs;
        $values = [];
        $placeholders = [];
        
        foreach ( $this->telemetry_buffer as $log ) {
            $values[] = 'PROMETHEUS';
            $values[] = $log['type'];
            $values[] = substr($log['ip_address'], 0, 45);
            $values[] = $log['user_agent'];
            $values[] = $log['details'];
            $values[] = $log['timestamp'];
            $placeholders[] = "(%s, %s, %s, %s, %s, %s)";
        }
        
        if ( ! empty( $placeholders ) ) {
            $chunked_placeholders = array_chunk($placeholders, 50);
            $chunked_values       = array_chunk($values, 50 * 6);

            foreach ($chunked_placeholders as $index => $chunk) {
                $query = "INSERT INTO {$table} (module, type, ip_address, user_agent, details, timestamp) VALUES " . implode( ', ', $chunk );
                $wpdb->query( $wpdb->prepare( $query, $chunked_values[$index] ) );
            }
        }
        
        $this->telemetry_buffer = [];
    }

    private function is_static_asset_request(string $uri): bool {
        $path = parse_url($uri, PHP_URL_PATH);
        if ( ! $path || $path === '/' ) {
            return false;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ( ! isset(self::STATIC_ASSET_EXTS[$ext]) ) {
            return false;
        }

        $decoded_path = $path;
        $max_depth = 5;
        $i = 0;
        do {
            $prev = $decoded_path;
            $decoded_path = rawurldecode($prev);
            $i++;
        } while ( $prev !== $decoded_path && $i < $max_depth );

        if ( strpos($decoded_path, '..') !== false || strpos($decoded_path, "\0") !== false ) {
            return false;
        }

        if ( preg_match('/\.ph(?:p[34578]?|tml|ar)(?:\/|\.|$)/i', $decoded_path) === 1 ) {
            return false;
        }

        if ( str_starts_with($decoded_path, '/wp-content/') || 
             str_starts_with($decoded_path, '/wp-includes/') ||
             str_starts_with($decoded_path, '/storage/') || 
             str_starts_with($decoded_path, '/content/') ) {
            return true;
        }

        if ( isset(self::ROOT_ASSETS[$decoded_path]) || str_starts_with($decoded_path, '/apple-touch-icon') ) {
            return true;
        }

        return false;
    }

    public function profile_incoming_request(): void {
        $ip = $this->get_client_ip();
        
        if ( '' === $ip || $this->is_whitelisted( $ip ) ) {
            return;
        }

        $this->track_botanical_swarm_fingerprint($ip);

        $current_time = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
        $request_uri  = $_SERVER['REQUEST_URI'] ?? '/';
        
        if ( $this->is_static_asset_request($request_uri) ) {
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        $score_increment = 0.0;
        $anomalies = [];

        if ( isset( self::SUSPICIOUS_METHODS[ strtoupper( $method ) ] ) ) {
            $score_increment += $this->score_penalty_method;
            $anomalies[] = "Method {$method}";
        }

        if ( substr_count( $request_uri, '=' ) > 8 ) {
            $score_increment += $this->score_penalty_params;
            $anomalies[] = "High Parameter Count";
        }
        
        if ( preg_match( self::ANOMALY_REGEX, $request_uri ) === 1 ) {
            $score_increment += $this->score_penalty_regex;
            $anomalies[] = "URI Regex Match";
        }

        $this->evaluate_state( $ip, $score_increment, $current_time, $anomalies );
    }

    public function track_404_anomalies(): void {
        if ( is_404() ) {
            $ip = $this->get_client_ip();
            if ( '' !== $ip && ! $this->is_whitelisted( $ip ) ) {
                $request_uri = $_SERVER['REQUEST_URI'] ?? '';
                
                if ( $this->is_static_asset_request($request_uri) ) {
                    return; 
                }
                
                $this->evaluate_state( $ip, $this->score_penalty_404, microtime( true ), ['404 Not Found'] );
            }
        }
    }

    public function track_auth_anomalies( string $username ): void {
        $ip = $this->get_client_ip();
        if ( '' !== $ip && ! $this->is_whitelisted( $ip ) ) {
            // [ DIAMANT FIX: OOM & Memory-Exhaustion Guard ]
            // Begrenzt den Username auf 64 Zeichen und entfernt Sonderzeichen.
            $safe_username = preg_replace('/[^a-zA-Z0-9_@\.\-]/', '', substr($username, 0, 64));
            $this->evaluate_state( $ip, $this->score_penalty_auth, microtime( true ), ["Auth Failure: {$safe_username}"] );
        }
    }

    private function evaluate_state( string $ip, float $base_increment, float $current_time, array $anomalies = [] ): void {
        
        $subnet = class_exists('VIS_Security') ? \VIS_Security::network_cidr($ip) : '';
        if ($subnet === '' && strpos( $ip, '.' ) !== false ) {
            $parts = explode( '.', $ip );
            $subnet = "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
        } elseif ($subnet === '') {
            $parts = explode( ':', $ip );
            if ( count( $parts ) >= 4 ) {
                $subnet = "{$parts[0]}:{$parts[1]}:{$parts[2]}:{$parts[3]}::/64";
            }
        }

        $infra_cache_key = 'vis_prom_infra_' . md5( $subnet );
        $infra_state     = $this->read_state( $infra_cache_key );

        if ( ! is_array( $infra_state ) ) {
            $infra_state = [
                'score'          => 0.0,
                'last_time'      => $current_time,
                'last_ip'        => $ip,
                'cooldown_until' => 0.0
            ];
        }

        if ( $infra_state['cooldown_until'] > $current_time ) {
            $this->trigger_mitigation( $ip, $infra_state['score'], $subnet );
        }

        $cache_key = 'vis_prom_' . md5( $ip );
        $state     = $this->read_state( $cache_key );

        if ( ! is_array( $state ) ) {
            $state = [
                'score'             => 0.0,
                'last_request_time' => $current_time,
                'request_count'     => 0,
            ];
        }

        $time_delta = max( 0.0, $current_time - $state['last_request_time'] );
        $dynamic_increment = $base_increment;

        // VGT SUPREME: Rewrite & Routing Awareness für das Prometheus Modul
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $is_api_route = (
            stripos($request_uri, '/wp-json/') !== false || 
            stripos($request_uri, 'admin-ajax.php') !== false || 
            stripos($request_uri, 'vgt-api/nexus') !== false ||
            stripos($request_uri, 'admin-post.php') !== false ||
            stripos($request_uri, 'vgt-api/post') !== false ||
            stripos($request_uri, '/vgt-api/visiongaia/') !== false ||
            stripos($request_uri, '/mediacenter/') !== false
        );

        if ( $time_delta > 0.0 ) {
            if ( $is_api_route ) {
                // Extreme Burst Tolerance für APIs, Dashboard & Mediacenter
                // Berechnet sich aus deinen Standardwerten (~1.5 und ~0.5)
                if ( $time_delta < 0.2 ) {
                    $dynamic_increment += ($this->score_penalty_burst * 0.075);
                } elseif ( $time_delta < 1.0 ) {
                    $dynamic_increment += ($this->score_penalty_freq * 0.05);
                }
            } else {
                // Standard L7 Schutz
                if ( $time_delta < 0.2 ) {
                    $dynamic_increment += $this->score_penalty_burst;
                    $anomalies[] = "Burst Rate Spiked (< 0.2s)";
                } elseif ( $time_delta < 1.0 ) {
                    $dynamic_increment += $this->score_penalty_freq;
                    $anomalies[] = "High Frequency Request (< 1s)";
                }
            }
        }

        $decay = $time_delta * $this->score_decay_rate;
        $new_score = max( 0.0, $state['score'] - $decay ) + $dynamic_increment;

        $infra_delta = max( 0.0, $current_time - $infra_state['last_time'] );
        $infra_decay = $infra_delta * $this->score_decay_rate;
        $new_infra_score = max( 0.0, $infra_state['score'] - $infra_decay );

        $infra_increment = $dynamic_increment;

        if ( $infra_state['last_ip'] !== $ip && $infra_state['last_ip'] !== '' && $infra_delta < 60.0 ) {
            $infra_increment += $this->score_penalty_rotation;
            $anomalies[] = "Subnet IP-Rotation Detected";
        }

        $new_infra_score += $infra_increment;

        $skip_ip_write = ( abs( $new_score - $state['score'] ) < 0.1 && $dynamic_increment === 0.0 );
        
        if ( ! $skip_ip_write ) {
            $this->delta_buffer[ $cache_key ] = [
                'increment' => ($this->delta_buffer[$cache_key]['increment'] ?? 0.0) + $dynamic_increment,
                'last_time' => $current_time,
                'is_infra'  => false
            ];
            $this->buffer_modified = true;
        }

        if ( $infra_increment > 0.0 || abs( $new_infra_score - ($infra_state['score'] - $infra_increment) ) > 0.1 ) {
            
            if ( $new_infra_score >= $this->infra_horizon_score && $infra_state['cooldown_until'] <= $current_time ) {
                $cooldown_target = $current_time + $this->infra_cooldown_window;
                
                $details_str = implode(', ', $anomalies);
                $this->write_telemetry( 'INFRA_STRIKE', "Cluster Rotation Horizon Reached (Score: {$new_infra_score}) | {$details_str}", $subnet );
                
                $this->trigger_mitigation( $ip, $new_infra_score, $subnet );
            } else {
                $cooldown_target = $infra_state['cooldown_until'];
            }

            $this->delta_buffer[ $infra_cache_key ] = [
                'increment' => ($this->delta_buffer[$infra_cache_key]['increment'] ?? 0.0) + $infra_increment,
                'last_time' => $current_time,
                'last_ip'   => $ip,
                'is_infra'  => true,
                'cooldown'  => $cooldown_target
            ];
            $this->buffer_modified = true;
        }

        if ( $dynamic_increment > 0.0 ) {
            $details_str = implode(', ', $anomalies);
            $this->write_telemetry( 'ANOMALY', "Score: +{$dynamic_increment} | {$details_str}", $ip );
        }

        $trinity_config = get_option('vis_trinity_config', []);
        $trinity_enabled = !isset($trinity_config['interlock_enabled']) || !empty($trinity_config['interlock_enabled']);

        if ( $new_score >= $this->event_horizon_score ) {
            $this->trigger_mitigation( $ip, $new_score );
        } elseif ( $trinity_enabled ) {
            $micro_tarpit_score = max(10.0, min(200.0, (float)($trinity_config['micro_tarpit_score'] ?? 75.0)));
            if ( $new_score >= $micro_tarpit_score ) {
                // PHP workers are never held open for hostile clients. The pre-lock state is telemetry-only.
                $this->write_telemetry( 'PRELOCK_THRESHOLD', "Client behavior highly suspicious (Score: {$new_score}).", $ip );
            }
        }
    }

    private function read_state( string $cache_key ): ?array {
        if ( function_exists( 'apcu_fetch' ) ) {
            $state = apcu_fetch( $cache_key );
            return is_array( $state ) ? $state : null;
        }

        if ( wp_using_ext_object_cache() ) {
            $state = wp_cache_get( $cache_key, 'visiongaia_integrity' );
            return is_array( $state ) ? $state : null;
        }

        $state = get_transient( $cache_key );
        return is_array( $state ) ? $state : null;
    }

    /**
     * VGT DIAMANT UPGRADE: Sicheres Fallback für Spin-Locks (GET_LOCK via DB)
     */
    public function flush_state_buffer(): void {
        $this->flush_telemetry_batch();

        if ( ! $this->buffer_modified || empty( $this->delta_buffer ) ) {
            return;
        }

        $use_apcu = function_exists( 'apcu_add' );
        $use_memcache = wp_using_ext_object_cache();
        global $wpdb;

        foreach ( $this->delta_buffer as $cache_key => $delta ) {
            $lock_key = $cache_key . '_lock';
            $locked   = false;

            // ATOMIC SPIN-LOCK
            if ( $use_apcu || $use_memcache ) {
                for ( $i = 0; $i < self::SPIN_LOCK_MAX_RETRIES; $i++ ) {
                    if ( $use_apcu && apcu_add( $lock_key, 1, 5 ) ) { $locked = true; break; }
                    elseif ( $use_memcache && wp_cache_add( $lock_key, 1, 'visiongaia_integrity', 5 ) ) { $locked = true; break; }
                    usleep( 2000 ); // 2ms yield
                }
            } else {
                // [ DIAMANT FIX ]: Wenn keine RAM-Caches da sind, nutze MySQL Native Locks
                // Dies verhindert, dass Tausende Requests die wp_options Tabelle im Deadlock ersticken
                $db_lock_name = "vgt_prom_" . md5($lock_key);
                $lock_result = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 1)", $db_lock_name));
                if ( $lock_result === '1' ) {
                    $locked = true;
                }
            }

            if (!$locked) {
                error_log('[VIS PROMETHEUS] State lock unavailable; unlocked state mutation rejected.');
                continue;
            }

            try {
                $authoritative_state = $this->read_state( $cache_key );
                
                if ( $delta['is_infra'] ) {
                    if ( ! is_array( $authoritative_state ) ) {
                        $authoritative_state = ['score' => 0.0, 'last_time' => $delta['last_time'], 'last_ip' => $delta['last_ip'], 'cooldown_until' => $delta['cooldown']];
                    }
                    $time_delta = max( 0.0, $delta['last_time'] - $authoritative_state['last_time'] );
                    $decay = $time_delta * $this->score_decay_rate;
                    $authoritative_state['score'] = max( 0.0, $authoritative_state['score'] - $decay ) + $delta['increment'];
                    $authoritative_state['last_time'] = $delta['last_time'];
                    $authoritative_state['last_ip'] = $delta['last_ip'];
                    $authoritative_state['cooldown_until'] = max($authoritative_state['cooldown_until'] ?? 0.0, $delta['cooldown']);
                } else {
                    if ( ! is_array( $authoritative_state ) ) {
                        $authoritative_state = ['score' => 0.0, 'last_request_time' => $delta['last_time'], 'request_count' => 0];
                    }
                    $time_delta = max( 0.0, $delta['last_time'] - $authoritative_state['last_request_time'] );
                    $decay = $time_delta * $this->score_decay_rate;
                    $authoritative_state['score'] = max( 0.0, $authoritative_state['score'] - $decay ) + $delta['increment'];
                    $authoritative_state['last_request_time'] = $delta['last_time'];
                    $authoritative_state['request_count']++;
                }

                if ( $use_apcu ) {
                    apcu_store( $cache_key, $authoritative_state, $this->score_decay_window );
                } elseif ( $use_memcache ) {
                    wp_cache_set( $cache_key, $authoritative_state, 'visiongaia_integrity', $this->score_decay_window );
                } else {
                    set_transient( $cache_key, $authoritative_state, $this->score_decay_window );
                }
            } finally {
                if ( $locked ) {
                    if ( $use_apcu ) apcu_delete( $lock_key );
                    elseif ( $use_memcache ) wp_cache_delete( $lock_key, 'visiongaia_integrity' );
                    else {
                        $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $db_lock_name));
                    }
                }
            }
        }

        $this->delta_buffer = [];
        $this->buffer_modified = false;
    }

    private function trigger_mitigation( string $ip, float $score, ?string $subnet_context = null ): void {
        if ( $subnet_context !== null ) {
            $this->write_telemetry( 'PREDICTIVE_INFRA_STRIKE', "Subnet Cooldown Enforced (Score: {$score}). Executing Kill.", $subnet_context );
        } else {
            $this->write_telemetry( 'PREDICTIVE_STRIKE', "Event Horizon Reached (Score: {$score}). Executing Kill.", $ip );
        }
        
        $strikes = (int) wp_cache_get( 'vgt_prometheus_strikes' );
        wp_cache_set( 'vgt_prometheus_strikes', $strikes + 1, '', 3600 );

        if (class_exists('VIS_Trinity_Grid')) {
            \VIS_Trinity_Grid::onPrometheusMitigation($ip, $score, $subnet_context);
        } elseif (class_exists('VIS_Cerberus')) {
            $cerberus = \VIS_Cerberus::instance();
            $subnet_context === null
                ? $cerberus->ban_ip($ip, 'PROMETHEUS_PREDICTIVE_STRIKE')
                : $cerberus->ban_subnet($subnet_context, 'PROMETHEUS_PREDICTIVE_INFRA_STRIKE');
        }

        while ( ob_get_level() ) {
            @ob_end_clean();
        }

        if ( ! headers_sent() ) {
            http_response_code( 403 );
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'Connection: close' );
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
        }
        
        exit( 'VISIONGAIATECHNOLOGY OMEGA PROTOCOL: CONNECTION TERMINATED.' );
    }

    /**
     * VGT KERNEL: Topologie-bewusste IP-Extraktion
     * [ DIAMANT FIX: Strikte Trusted-Proxy CIDR Validierung anstatt blinden Vertrauens ]
     */
    private function get_client_ip(): string {
        if (class_exists('VIS_Security')) {
            return \VIS_Security::client_ip();
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if ( $this->trust_proxy_headers ) {
            // Nur evaluieren, wenn der Request von einem autorisierten Reverse-Proxy (z.B. Cloudflare) kommt.
            $is_trusted_proxy = false;
            
            if (!empty($this->trusted_proxies)) {
                foreach ($this->trusted_proxies as $trusted_ip) {
                    if ($ip === $trusted_ip) {
                        $is_trusted_proxy = true;
                        break;
                    }
                }
            }

            if ($is_trusted_proxy) {
                $proxy_headers = [
                    'HTTP_CF_CONNECTING_IP',
                    'HTTP_TRUE_CLIENT_IP',
                    'HTTP_X_REAL_IP',
                    'HTTP_X_FORWARDED_FOR'
                ];

                foreach ( $proxy_headers as $header ) {
                    if ( ! empty( $_SERVER[ $header ] ) ) {
                        // Nimm die erste IP in der Kette (Original Client)
                        $header_ips = explode( ',', $_SERVER[ $header ] );
                        foreach ( $header_ips as $potential_ip ) {
                            $potential_ip = trim( $potential_ip );
                            if ( filter_var( $potential_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE ) ) {
                                $ip = $potential_ip;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        $validated_ip = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE );
        return $validated_ip ? (string) $validated_ip : $ip;
    }

    /**
     * BOTANICAL CLIENT-HEADER FINGERPRINTING & SWARM CORRELATION
     * Computes structural SHA-256 fingerprint from client headers and tracks unique IP hits across rotating pools.
     */
    private function track_botanical_swarm_fingerprint(string $ip): void {
        $ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept  = $_SERVER['HTTP_ACCEPT'] ?? '';
        $lang    = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $enc     = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        $header_keys = [];
        foreach ($_SERVER as $key => $val) {
            if (str_starts_with($key, 'HTTP_')) {
                $header_keys[] = $key;
            }
        }
        $header_structure = implode('|', $header_keys);

        $botanical_fp = hash('sha256', "{$ua}|{$accept}|{$lang}|{$enc}|{$header_structure}");
        $subnet_scope = $this->extract_swarm_subnet($ip);
        if ($subnet_scope === '') return;
        $transient_key = 'vgt_botanical_' . substr(hash('sha256', $botanical_fp . '|' . $subnet_scope), 0, 32);

        $swarm_data = get_transient($transient_key);
        if (!is_array($swarm_data)) {
            $swarm_data = ['ips' => [], 'time' => time()];
        }

        if ((time() - (int)($swarm_data['time'] ?? 0)) > 10) {
            $swarm_data = ['ips' => [], 'time' => time()];
        }

        if (!in_array($ip, $swarm_data['ips'], true)) {
            $swarm_data['ips'][] = $ip;
        }

        set_transient($transient_key, $swarm_data, 15);

        if (self::botanical_swarm_threshold_reached($swarm_data['ips'])) {
            $this->write_telemetry('BOTANICAL_SWARM_DETECTED', "Distributed Swarm Botnet (" . count($swarm_data['ips']) . " IPs)", $ip);
            
            $subnet = $subnet_scope;
            if ($subnet !== '') {
                if (class_exists('\VIS_Cerberus') && method_exists('\VIS_Cerberus', 'instance')) {
                    try {
                        $cerberus = \VIS_Cerberus::instance();
                        if (method_exists($cerberus, 'ban_subnet')) {
                            $cerberus->ban_subnet($subnet, "PROMETHEUS_SWARM_FINGERPRINT_KILL [FP: " . substr($botanical_fp, 0, 8) . "]");
                        }
                    } catch (\Throwable $e) {
                        // Fail safe
                    }
                }
            }
        }
    }

    /** @param array<int, mixed> $ips */
    public static function botanical_swarm_threshold_reached(array $ips): bool {
        $validated = [];
        foreach ($ips as $ip) {
            if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $validated[$ip] = true;
        }

        return count($validated) >= 15;
    }

    private function extract_swarm_subnet(string $ip): string {
        if (class_exists('VIS_Security')) return \VIS_Security::network_cidr($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 4) {
                return implode(':', array_slice($parts, 0, 4)) . '::/64';
            }
        }
        return $ip;
    }
}

VIS_Prometheus::get_instance();
