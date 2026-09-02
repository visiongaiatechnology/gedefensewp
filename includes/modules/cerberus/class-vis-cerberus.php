<?php
/**
 * VISIONGAIA TECHNOLOGY CODE ARTIFACT
 * -----------------------------------
 * MODULE: CERBERUS (OMEGA SENTINEL V4.4 - SUPREME)
 * STATUS: DIAMANT VGT SUPREME / DEPLOYMENT READY
 * ARCHITECTURE: Singleton Pattern, IPv6/IPv4 Dual Stack CIDR, Global Perimeter Lockdown.
 * KERNEL UPGRADES:
 * - O(1) L1 Memory Cache für Global Perimeter (Verhindert DB-DDoS).
 * - Atomares SQL-Tracking für Brute-Force (Verhindert TOCTOU & wp_options Bloat).
 * - Graceful Session Degradation (Sicheres Logout statt White-Screen).
 * - Boot-Priority Fix (-9999) für echte Pre-Flight Checks.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VISIONGAIA: ACCESS DENIED');
}

final class VIS_Cerberus {

    private static ?self $instance = null;

    private int $max_retries = 3;
    private int $lockout_time = 3600; 
    
    private ?string $cached_ip = null;
    private ?bool $is_banned_memory_cache = null; // L1 Request Cache

    private string $table_strikes;
    private string $table_bans;

    private const CF_RANGES = [
        'v4' => [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22'
        ],
        'v6' => [
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32'
        ]
    ];

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function __callStatic(string $name, array $arguments): mixed {
        $inst = self::instance();
        if ($name === 'unban_ip') {
            return $inst->unban_target(...$arguments);
        }
        if (method_exists($inst, $name)) {
            return $inst->$name(...$arguments);
        }
        throw new BadMethodCallException("Method VIS_Cerberus::{$name} does not exist.");
    }

    public function __construct() {
        if (self::$instance !== null) {
            return;
        }

        self::$instance = $this;
        
        global $wpdb;
        $prefix = isset($wpdb) && isset($wpdb->prefix) ? (string)$wpdb->prefix : 'wp_';
        $this->table_bans = $prefix . (defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_omega_bans');
        $this->table_strikes = $prefix . (defined('VIS_TABLE_STRIKES') ? VIS_TABLE_STRIKES : 'vis_omega_strikes');

        $this->init_hooks();
    }

    private function init_hooks(): void {
        // [ DIAMANT FIX ]: Execute strictly before ANYTHING else (-9999)
        if (did_action('plugins_loaded')) {
            $this->enforce_global_perimeter();
        } else {
            add_action('plugins_loaded', [$this, 'enforce_global_perimeter'], -9999);
            add_action('plugins_loaded', [$this, 'verify_strike_schema'], -9998);
        }

        // LAYER 1: Auth Guard
        add_action('login_init', [$this, 'block_banned_ip_early'], 0);
        add_filter('authenticate', [$this, 'check_pre_auth'], 1, 3);
        
        // LAYER 2: Failure Tracking (Atomic Brute Force Logic)
        add_action('wp_login_failed', [$this, 'handle_failed_login']);

        // LAYER 3: Obfuscation
        add_filter('login_errors', fn() => "<strong>VISIONGAIA CERBERUS:</strong> Authentication failed. Vector logged.");

        // LAYER 4: Session Shield
        add_action('wp_login', [$this, 'hook_rotate_session'], 10, 2);
        add_action('init', [$this, 'validate_session_integrity'], 1);
        
        // LAYER 5: Hardening
        add_filter('xmlrpc_enabled', '__return_false');
        add_action('vis_cerberus_sync_firewall', [$this, 'sync_os_firewall_rules']);
    }

    /**
     * VGT KERNEL: The Global Execution Path.
     * Nutzt L1 und L2 Caching zur absoluten Eliminierung von Datenbank-DDoS.
     */
    public function enforce_global_perimeter(): void {
        if (defined('WP_CLI') && WP_CLI) return;
        if (defined('DOING_CRON') && DOING_CRON) return;

        if ($this->is_ip_banned()) {
            $this->terminate_request("GLOBAL PERIMETER LOCKDOWN. Threat neutralized at the gates.");
        }
    }

    public function block_banned_ip_early(): void {
        if ($this->is_ip_banned()) {
            $this->terminate_request("Access to authentication kernel terminated by OMEGA Protocol.");
        }
    }

    public function check_pre_auth(mixed $user, string $username, string $password): mixed {
        if (is_wp_error($user)) {
            return $user;
        }

        if ($this->is_ip_banned()) {
            return new \WP_Error(
                'vis_banned', 
                "<strong>VISIONGAIA CERBERUS:</strong> Access Denied. Threat Active."
            );
        }
        return $user;
    }

    /**
     * [ DIAMANT FIX ]: O(1) Memory Cache & Hard Semantic TTL Enforcement.
     * Temporary XDR bans expire immediately at enforcement time without Cron dependency.
     */
    public function is_ip_banned(?string $custom_ip = null): bool {
        $ip = $custom_ip !== null ? trim($custom_ip) : $this->get_validated_ip();
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $cache_key = 'vis_ban_status_' . md5($ip);
        $cached_type = wp_cache_get($cache_key . '_type', 'visiongaia_cerberus');
        $cached_status = wp_cache_get($cache_key, 'visiongaia_cerberus');

        if ($cached_status !== false) {
            if ((int)$cached_status === 0) {
                if ($custom_ip === null) $this->is_banned_memory_cache = false;
                return false;
            }
            if ($cached_type === 'XDR') {
                // Hard Semantic TTL: re-verify XDR status
                if (class_exists('\VisionGaia\GeDefense\Xdr\ResponseEngine') && !\VisionGaia\GeDefense\Xdr\ResponseEngine::isIpRestricted($ip)) {
                    wp_cache_delete($cache_key, 'visiongaia_cerberus');
                    wp_cache_delete($cache_key . '_type', 'visiongaia_cerberus');
                    if ($custom_ip === null) $this->is_banned_memory_cache = false;
                    return false;
                }
            }
            if ($custom_ip === null) $this->is_banned_memory_cache = true;
            return true;
        }

        global $wpdb;
        $banRow = $wpdb->get_row($wpdb->prepare("SELECT id, reason FROM {$this->table_bans} WHERE ip = %s LIMIT 1", $ip), \ARRAY_A);
        $is_banned = is_array($banRow) && isset($banRow['id']);
        $is_xdr = false;

        if ($is_banned) {
            $reason = (string)($banRow['reason'] ?? '');
            if (str_starts_with($reason, 'TRINITY_XDR:')) {
                $is_xdr = true;
                // [ DIAMANT VGT SUPREME ]: Hard Semantic TTL check at enforcement time
                if (class_exists('\VisionGaia\GeDefense\Xdr\ResponseEngine')) {
                    if (!\VisionGaia\GeDefense\Xdr\ResponseEngine::isIpRestricted($ip)) {
                        $is_banned = false;
                    }
                }
            }
        }

        if (!$is_banned) {
            $cidr_bans = wp_cache_get('vis_cidr_bans', 'visiongaia_cerberus');
            if (!is_array($cidr_bans)) {
                $cidr_bans = $wpdb->get_col("SELECT ip FROM {$this->table_bans} WHERE ip LIKE '%/%' LIMIT 1000");
                if (!is_array($cidr_bans)) $cidr_bans = [];
                wp_cache_set('vis_cidr_bans', $cidr_bans, 'visiongaia_cerberus', 300);
            }
            $ip_bin = @inet_pton($ip);
            if ($ip_bin !== false) {
                foreach ($cidr_bans as $network) {
                    if (is_string($network) && self::cidr_match_bin($ip_bin, $network)) {
                        $is_banned = true;
                        break;
                    }
                }
            }
        }

        if ($custom_ip === null) {
            $this->is_banned_memory_cache = $is_banned;
        }

        if ($is_banned) {
            wp_cache_set($cache_key, 1, 'visiongaia_cerberus', $is_xdr ? 60 : 300);
            wp_cache_set($cache_key . '_type', $is_xdr ? 'XDR' : 'ADMIN', 'visiongaia_cerberus', $is_xdr ? 60 : 300);
        } else {
            wp_cache_set($cache_key, 0, 'visiongaia_cerberus', 60);
            wp_cache_delete($cache_key . '_type', 'visiongaia_cerberus');
        }

        return $is_banned;
    }

    public function handle_failed_login(string $username): void {
        global $wpdb;
        $ip = $this->get_validated_ip();
        
        $expire_time = time() + $this->lockout_time;

        // Atomares Increment: ON DUPLICATE KEY UPDATE verhindert Race-Conditions durch parallele Requests
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$this->table_strikes} (ip, strikes, expires) 
            VALUES (%s, 1, %d) 
            ON DUPLICATE KEY UPDATE 
            strikes = strikes + 1, 
            expires = %d
        ", $ip, $expire_time, $expire_time));

        $strikes = (int) $wpdb->get_var($wpdb->prepare("SELECT strikes FROM {$this->table_strikes} WHERE ip = %s", $ip));

        if ($strikes >= $this->max_retries) {
            $safe_username = preg_replace('/[^a-zA-Z0-9_@\.\-]/', '', substr($username, 0, 50));
            $this->ban_ip($ip, "Brute-Force Limit Reached ($strikes). Target: {$safe_username}");
            $wpdb->query($wpdb->prepare("DELETE FROM {$this->table_strikes} WHERE ip = %s", $ip));
        }
        
        // Asynchroner Garbage-Collector-Stupser (10% Wahrscheinlichkeit)
        if (random_int(1, 10) === 1) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$this->table_strikes} WHERE expires < %d", time()));
        }
    }

    public function hook_rotate_session(string $user_login, \WP_User $user): void {
        $this->rotate_session_signature($user);
    }

    public function rotate_session_signature(\WP_User $user): void {
        $signature = $this->generate_signature();
        update_user_meta($user->ID, '_vis_session_sig', $signature);
    }

    public function validate_session_integrity(): void {
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $stored_sig = get_user_meta($user_id, '_vis_session_sig', true);
        
        if (empty($stored_sig)) {
            $user = wp_get_current_user();
            if ($user instanceof \WP_User && $user->exists()) {
                $this->rotate_session_signature($user);
            }
            return;
        }

        if (!hash_equals((string)$stored_sig, $this->generate_signature())) {
            // [ DIAMANT FIX ]: Graceful Degradation
            // Statt hartem "die()", wird die Session sauber zerstört und der User zum Login geroutet.
            wp_destroy_current_session();
            wp_clear_auth_cookie();
            wp_set_current_user(0);
            
            if (!headers_sent()) {
                wp_safe_redirect(wp_login_url() . '?reauth=cerberus');
                exit;
            } else {
                wp_die('VISIONGAIA CERBERUS: Session Security Violation.', 'Access Denied', 403);
            }
        }
    }

    private function generate_signature(): string {
        $ip = $this->get_validated_ip();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $packed = @inet_pton($ip);
        if ($packed === false) {
             return hash('sha256', 'invalid_ip|' . $ua); 
        }

        // Subnet Isolation: Bindet Session an /24 (IPv4) oder /64 (IPv6)
        $subnet = (strlen($packed) === 16) ? substr($packed, 0, 8) : substr($packed, 0, 3);
        
        return hash('sha256', bin2hex($subnet) . '|' . $ua);
    }

    public function ban_ip(string $ip, string $reason): void {
        if (!self::valid_address_or_network($ip)) {
            error_log('[VIS CERBERUS] Invalid ban target rejected.');
            return;
        }

        global $wpdb;
        $uri = substr(esc_url_raw($_SERVER['REQUEST_URI'] ?? ''), 0, 255);

        if (class_exists('VIS_Event_Bus')) {
            VIS_Event_Bus::emit('CERBERUS', 'BAN', $reason, [
                'target_ip' => $ip,
                'uri' => $uri,
            ], 8);
        }

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_bans} (ip, reason, banned_at, request_uri) VALUES (%s, %s, %s, %s)",
            $ip, $reason, current_time('mysql'), $uri
        ));
        
        $is_xdr = str_starts_with($reason, 'TRINITY_XDR:');
        if ($this->cached_ip === $ip || (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === $ip)) {
            $this->is_banned_memory_cache = true;
        }
        wp_cache_set('vis_ban_status_' . md5($ip), 1, 'visiongaia_cerberus', $is_xdr ? 60 : 300);
        wp_cache_set('vis_ban_status_' . md5($ip) . '_type', $is_xdr ? 'XDR' : 'ADMIN', 'visiongaia_cerberus', $is_xdr ? 60 : 300);

        $this->schedule_os_firewall_sync();
    }

    public function ban_subnet(string $subnet, string $reason = 'PROMETHEUS_BOTANICAL_SWARM_BAN'): void {
        if (!str_contains($subnet, '/')) return;
        $this->ban_ip($subnet, $reason);
        wp_cache_delete('vis_cidr_bans', 'visiongaia_cerberus');
    }

    public function unban_target(string $target): bool {
        $target = trim($target);
        if (!self::valid_address_or_network($target)) {
            throw new ValidationException('Invalid IP or CIDR format.');
        }
        global $wpdb;
        $deleted = $wpdb->delete($this->table_bans, ['ip' => $target], ['%s']);
        if ($deleted === false) throw new StorageException('Cerberus unban persistence failed.');

        wp_cache_delete('vis_ban_status_' . md5($target), 'visiongaia_cerberus');
        wp_cache_delete('vis_ban_status_' . md5($target) . '_type', 'visiongaia_cerberus');
        wp_cache_delete('vis_cidr_bans', 'visiongaia_cerberus');
        $this->schedule_os_firewall_sync();
        return true;
    }

    public function unban_ip(string $ip): bool {
        return $this->unban_target($ip);
    }

    private function schedule_os_firewall_sync(): void {
        if (!wp_next_scheduled('vis_cerberus_sync_firewall')) {
            wp_schedule_single_event(time() + 5, 'vis_cerberus_sync_firewall');
        }
    }

    private static function valid_address_or_network(string $value): bool {
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) return true;
        if (substr_count($value, '/') !== 1) return false;
        [$network, $prefix] = explode('/', $value, 2);
        $packed = @inet_pton($network);
        if ($packed === false || !ctype_digit($prefix)) return false;
        return (int)$prefix >= 0 && (int)$prefix <= strlen($packed) * 8;
    }

    public function sync_os_firewall_rules(): void {
        global $wpdb;
        if (!isset($wpdb) || !($wpdb instanceof \wpdb)) return;

        $vault_dir = defined('VIS_VAULT_DIR') 
            ? (str_ends_with(str_replace('\\', '/', VIS_VAULT_DIR), '/zeus') ? VIS_VAULT_DIR : VIS_VAULT_DIR . '/zeus')
            : (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/vgt-vault/zeus' : ABSPATH . 'wp-content/vgt-vault/zeus');

        if (!is_dir($vault_dir)) {
            @mkdir($vault_dir, 0700, true);
        }

        $bans = $wpdb->get_results("SELECT ip, reason FROM {$this->table_bans} LIMIT 5000", \ARRAY_A);
        $clean_ips = [];
        $hasXdr = class_exists('\VisionGaia\GeDefense\Xdr\ResponseEngine');

        foreach (is_array($bans) ? $bans : [] as $row) {
            $ip = (string)($row['ip'] ?? '');
            $reason = (string)($row['reason'] ?? '');
            if ($ip === '') continue;

            // P1 EDGE TTL INVARIANT: Temporary XDR bans stay in PHP/APCu memory layer only.
            // NEVER export short-lived XDR bans to static edge files where real-time expiration cannot be enforced.
            if (str_starts_with($reason, 'TRINITY_XDR:')) {
                continue;
            }
            $clean_ips[] = $ip;
        }

        $rules = self::compile_os_firewall_rules($clean_ips);
        self::atomic_file_write(wp_normalize_path($vault_dir . '/nginx_deny.conf'), $rules['nginx']);
        self::atomic_file_write(wp_normalize_path($vault_dir . '/nftables_drop.map'), $rules['nftables']);
        self::atomic_file_write(wp_normalize_path($vault_dir . '/htaccess_deny.conf'), $rules['apache']);
    }

    public static function compile_os_firewall_rules(array $candidates): array {
        $clean = [];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $value = trim($candidate);
            $valid = filter_var($value, FILTER_VALIDATE_IP) !== false;
            if (!$valid && substr_count($value, '/') === 1) {
                [$network, $prefix] = explode('/', $value, 2);
                $version = filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 4
                    : (filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 6 : 0);
                $valid = $version !== 0
                    && ctype_digit($prefix)
                    && (int)$prefix >= 0
                    && (int)$prefix <= ($version === 4 ? 32 : 128);
            }
            if (!$valid) {
                continue;
            }
            $clean[$value] = true;
        }

        $addresses = array_keys($clean);
        sort($addresses, SORT_STRING);
        $nginx = "# VISIONGAIA OS FIREWALL DYNAMIC SYNC\n";
        $apache = "# VISIONGAIA OS FIREWALL DYNAMIC SYNC\n";
        foreach ($addresses as $address) {
            $nginx .= "deny {$address};\n";
            $apache .= "Require not ip {$address}\n";
        }

        return [
            'nginx' => $nginx,
            'apache' => $apache,
            'nftables' => implode(', ', $addresses) . "\n",
            'count' => count($addresses),
        ];
    }

    private static function atomic_file_write(string $filepath, string $content): void {
        try {
            $filepath = wp_normalize_path($filepath);
            $tmp_file = $filepath . '.tmp.' . bin2hex(random_bytes(8));
            if (@file_put_contents($tmp_file, $content, LOCK_EX) !== false) {
                @chmod($tmp_file, 0600);
                if (!@rename($tmp_file, $filepath)) {
                    if (is_file($filepath)) @unlink($filepath);
                    if (!@rename($tmp_file, $filepath)) {
                        @copy($tmp_file, $filepath);
                        @unlink($tmp_file);
                    }
                }
            } else {
                @file_put_contents($filepath, $content, LOCK_EX);
            }
            @chmod($filepath, 0600);
        } catch (\Throwable $e) {
            // VGT Fail-Safe
        }
    }

    public function get_validated_ip(): string {
        if ($this->cached_ip !== null) {
            return $this->cached_ip;
        }

        if (class_exists('VIS_Security')) {
            $this->cached_ip = \VIS_Security::client_ip();
            return $this->cached_ip;
        }

        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && $this->is_cloudflare_ip($remote_addr)) {
            $this->cached_ip = filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP) ?: $remote_addr;
        } else {
            $this->cached_ip = $remote_addr;
        }
        
        return $this->cached_ip;
    }

    private function is_cloudflare_ip(string $ip): bool {
        $ip_bin = @inet_pton($ip);
        if ($ip_bin === false) return false;

        $is_v6 = (strlen($ip_bin) === 16);
        $ranges = $is_v6 ? self::CF_RANGES['v6'] : self::CF_RANGES['v4'];

        foreach ($ranges as $cidr) {
            if ($this->cidr_match_bin($ip_bin, $cidr)) {
                return true;
            }
        }
        return false;
    }

    private function cidr_match_bin(string $ip_bin, string $cidr): bool {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) return false;

        $subnet_bin = @inet_pton($parts[0]);
        if ($subnet_bin === false) return false;
        
        $bits = (int)$parts[1];
        $bytes = $bits >> 3; 
        $bits_remainder = $bits & 7; 
        
        if ($bytes > 0) {
            if (substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) return false;
        }
        
        if ($bits_remainder > 0) {
            $mask = 0xff << (8 - $bits_remainder);
            if (!isset($ip_bin[$bytes]) || !isset($subnet_bin[$bytes])) return false;
            if ((ord($ip_bin[$bytes]) & $mask) !== (ord($subnet_bin[$bytes]) & $mask)) return false;
        }
        
        return true;
    }

    private function terminate_request(string $msg): void {
        $ip = htmlspecialchars($this->get_validated_ip());
        $msg = htmlspecialchars($msg);
        
        if (!headers_sent()) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex, nofollow, nosnippet');
        }
        
        die("<h1>403 Forbidden</h1><hr>VISIONGAIA CERBERUS: $msg <br><small>IP: $ip</small>");
    }

    /**
     * Schema Installer für die atomare Tracking-Tabelle.
     */
    public function verify_strike_schema(): void {
        if (get_option('vgt_cerberus_schema_verified')) return;

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql_strikes = "CREATE TABLE IF NOT EXISTS {$this->table_strikes} (
            ip varchar(45) NOT NULL,
            strikes int(11) NOT NULL DEFAULT 1,
            expires int(11) NOT NULL,
            PRIMARY KEY  (ip),
            KEY expires_index (expires)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_strikes);

        update_option('vgt_cerberus_schema_verified', true, false);
    }
}

// EXECUTION TRIGGER
if (did_action('plugins_loaded')) {
    VIS_Cerberus::instance();
} else {
    add_action('plugins_loaded', fn() => VIS_Cerberus::instance(), -9999);
}
