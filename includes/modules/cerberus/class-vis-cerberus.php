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

        // Threat Intelligence Inbound Check (Blocks incoming botnets, C2, and malicious IPs)
        if (!$is_banned && class_exists('\VisionGaia\GeDefense\Modules\ThreatIntel\ThreatIntelligence')) {
            $threat_intel = \VisionGaia\GeDefense\Modules\ThreatIntel\ThreatIntelligence::instance();
            if ($threat_intel->is_inbound_enabled() && $threat_intel->is_ip_threat($ip)) {
                $is_banned = true;
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

    public static function ban_ip(string $ip, string $reason): void {
        self::instance()->execute_ban($ip, $reason);
    }

    public function execute_ban(string $ip, string $reason): void {
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
        self::ban_ip($subnet, $reason);
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

    public static function unban_ip(string $ip): bool {
        return self::instance()->unban_target($ip);
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

    public static function terminate(string $msg, array $context = []): void {
        self::instance()->terminate_request($msg, $context);
    }

    public function terminate_request(string $msg, array $context = []): void {
        $ip = !empty($context['ip']) && is_string($context['ip']) ? $context['ip'] : $this->get_validated_ip();
        
        while (ob_get_level()) {
            @ob_end_clean();
        }
        
        if (!headers_sent()) {
            $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
            header("$protocol 403 Forbidden", true, 403);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex, nofollow, nosnippet');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Cache-Control: private, max-age=300');
            $engine_header = !empty($context['defense_engine']) && is_string($context['defense_engine'])
                ? $context['defense_engine']
                : 'VisionGaia-Cerberus';
            header('X-Defense-Engine: ' . $engine_header);
        }
        
        die($this->render_block_page($msg, $ip, $context));
    }

    /**
     * Cyberpunk High-Tech 403 Block Page & Corporate Microsite Engine
     * 100% autark, 0 externe Ressourcen, 0 DB-Abfragen auf Ban-Hits, maximale Performance.
     * Wenn Unternehmens-Branding aktiviert ist, wird eine vollständige Firmen-Landing-Page
     * mit eigenem Hintergrund, Logo, Unternehmens-Profil, USPs, Telefon/E-Mail gerendert,
     * gefolgt von der transparenten Sicherheits- und Entsperr-Box darunter.
     */
    public function render_block_page(string $msg, string $ip, array $context = []): string {
        $safe_ip    = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
        $safe_msg   = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        $ref_prefix = !empty($context['ref_prefix']) && is_string($context['ref_prefix']) ? $context['ref_prefix'] : 'CERB';
        $ref_code   = $ref_prefix . '-' . strtoupper(substr(md5($ip . date('Y-m-d')), 0, 4) . '-' . substr(md5($msg . $ip . 'vgt'), 0, 4));
        $utc_time   = gmdate('Y-m-d H:i:s') . ' UTC';
        
        $engine_label = !empty($context['engine']) && is_string($context['engine']) ? $context['engine'] : 'CERBERUS XDR KERNEL';
        $safe_engine  = htmlspecialchars($engine_label, ENT_QUOTES, 'UTF-8');
        $badge_label  = !empty($context['badge']) && is_string($context['badge']) ? $context['badge'] : 'CERBERUS // ACTIVE MITIGATION';
        $safe_badge   = htmlspecialchars($badge_label, ENT_QUOTES, 'UTF-8');

        $branding = get_option('vis_cerberus_branding', []);
        if (!is_array($branding)) {
            $branding = [];
        }

        $branding_enabled = !empty($branding['enabled']);

        if ($branding_enabled) {
            return $this->render_corporate_landing_block($msg, $ip, $context, $branding, $ref_code, $utc_time, $safe_engine, $safe_badge);
        }

        return $this->render_classic_cyberpunk_block($safe_msg, $safe_ip, $ref_code, $utc_time, $safe_engine, $safe_badge);
    }

    /**
     * VGT KERNEL: Corporate Landing-Page / Microsite Sperranzeige
     * Vermittelt dem Besucher das professionelle Bild der Unternehmens-Website mit
     * eigenem Hintergrundbild, Logo, Wer wir sind / Was wir machen, USPs und Direkt-Kontakten,
     * während darunter die Deeskalations- und Entsperr-Box mit der Vorgangs-ID positioniert ist.
     */
    private function render_corporate_landing_block(
        string $msg,
        string $ip,
        array $context,
        array $branding,
        string $ref_code,
        string $utc_time,
        string $safe_engine,
        string $safe_badge
    ): string {
        $safe_ip  = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
        $safe_msg = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');

        $company_name = !empty($branding['company_name']) && is_string($branding['company_name'])
            ? trim($branding['company_name'])
            : (function_exists('get_bloginfo') ? (string)get_bloginfo('name') : 'VisionGaia Technology');
        $page_title = !empty($branding['page_title']) && is_string($branding['page_title'])
            ? trim($branding['page_title'])
            : $company_name;
        $company_tagline = !empty($branding['company_tagline']) && is_string($branding['company_tagline'])
            ? trim($branding['company_tagline'])
            : '';
        $company_desc = !empty($branding['company_description']) && is_string($branding['company_description'])
            ? trim($branding['company_description'])
            : '';
        $support_phone  = !empty($branding['support_phone']) && is_string($branding['support_phone']) ? trim($branding['support_phone']) : '';
        $support_email  = !empty($branding['support_email']) && is_string($branding['support_email']) ? trim($branding['support_email']) : '';
        $company_addr   = !empty($branding['company_address']) && is_string($branding['company_address']) ? trim($branding['company_address']) : '';
        $business_hours = !empty($branding['business_hours']) && is_string($branding['business_hours']) ? trim($branding['business_hours']) : '';
        $logo_url       = !empty($branding['logo_url']) && is_string($branding['logo_url']) ? trim($branding['logo_url']) : '';
        $bg_image_url   = !empty($branding['bg_image_url']) && is_string($branding['bg_image_url']) ? trim($branding['bg_image_url']) : '';
        $bg_overlay_num = isset($branding['bg_overlay_opacity']) ? max(30, min(98, (int)$branding['bg_overlay_opacity'])) : 85;
        $overlay_val    = round($bg_overlay_num / 100, 2);
        $accent_color   = !empty($branding['accent_color']) && is_string($branding['accent_color']) ? sanitize_hex_color($branding['accent_color']) : '#00e5ff';
        if (!$accent_color) $accent_color = '#00e5ff';
        $custom_sec_note = !empty($branding['custom_security_note']) && is_string($branding['custom_security_note']) ? trim($branding['custom_security_note']) : '';

        // Features parsen
        $features_raw = explode("\n", (string)($branding['company_features'] ?? ''));
        $features = [];
        foreach ($features_raw as $feat_line) {
            $feat_trimmed = trim($feat_line);
            if ($feat_trimmed !== '') {
                $features[] = htmlspecialchars($feat_trimmed, ENT_QUOTES, 'UTF-8');
            }
        }

        $safe_company  = htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8');
        $safe_title    = htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8');
        $safe_tagline  = htmlspecialchars($company_tagline, ENT_QUOTES, 'UTF-8');
        $safe_desc     = nl2br(htmlspecialchars($company_desc, ENT_QUOTES, 'UTF-8'));
        $safe_phone    = htmlspecialchars($support_phone, ENT_QUOTES, 'UTF-8');
        $clean_phone   = preg_replace('/[^0-9+]/', '', $support_phone);
        $safe_email    = htmlspecialchars($support_email, ENT_QUOTES, 'UTF-8');
        $clean_email   = $safe_email !== '' ? 'mailto:' . rawurlencode($support_email) . '?subject=' . rawurlencode('Freischaltung anfordern - Vorgang: ' . $ref_code) : '#';
        $safe_addr     = htmlspecialchars($company_addr, ENT_QUOTES, 'UTF-8');
        $safe_hours    = htmlspecialchars($business_hours, ENT_QUOTES, 'UTF-8');
        $safe_logo     = $logo_url !== '' ? esc_url($logo_url) : '';
        $safe_bg       = $bg_image_url !== '' ? esc_url($bg_image_url) : '';
        $safe_sec_note = $custom_sec_note !== '' ? nl2br(htmlspecialchars($custom_sec_note, ENT_QUOTES, 'UTF-8')) : '';

        // Hintergrund CSS
        $bg_css = $safe_bg !== ''
            ? "background-image: linear-gradient(rgba(6, 7, 10, {$overlay_val}), rgba(6, 7, 10, {$overlay_val})), url('{$safe_bg}'); background-size: cover; background-position: center; background-attachment: fixed;"
            : "background-color: var(--bg); background-image: radial-gradient(circle at 50% 0%, rgba(0, 229, 255, 0.08), transparent 60%), radial-gradient(circle at 80% 80%, rgba(255, 42, 95, 0.06), transparent 50%);";

        // Logo / Emblem HTML
        $logo_html = '';
        if ($safe_logo !== '') {
            $logo_html = '<img src="' . $safe_logo . '" alt="' . $safe_company . '" class="vgt-lp-logo">';
        } else {
            $logo_html = '<div class="vgt-lp-emblem">
                <svg viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($accent_color) . '" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                <span>' . $safe_company . '</span>
            </div>';
        }

        // Features Grid
        $features_html = '';
        if (!empty($features)) {
            $features_html .= '<div class="vgt-lp-features-grid">';
            foreach ($features as $f) {
                $features_html .= '<div class="vgt-lp-feature-card">
                    <span class="vgt-lp-feature-check">✓</span>
                    <span class="vgt-lp-feature-text">' . $f . '</span>
                </div>';
            }
            $features_html .= '</div>';
        }

        // Contact Cards Grid
        $contact_cards_html = '';
        if ($safe_phone !== '' || $safe_email !== '' || $safe_addr !== '' || $safe_hours !== '') {
            $contact_cards_html .= '<div class="vgt-lp-contact-cards">';
            if ($safe_phone !== '') {
                $contact_cards_html .= '<a href="tel:' . $clean_phone . '" class="vgt-lp-contact-card vgt-card-phone" title="Hotline direkt anrufen">
                    <div class="vgt-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <div class="vgt-card-body">
                        <small>DIREKTER TELEFONKONTAKT</small>
                        <strong>' . $safe_phone . '</strong>
                        <span>Jetzt anrufen &amp; sofort freischalten lassen</span>
                    </div>
                </a>';
            }
            if ($safe_email !== '') {
                $contact_cards_html .= '<a href="' . $clean_email . '" class="vgt-lp-contact-card vgt-card-email" title="E-Mail senden">
                    <div class="vgt-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <div class="vgt-card-body">
                        <small>KUNDENSERVICE E-MAIL</small>
                        <strong>' . $safe_email . '</strong>
                        <span>Nachricht mit Vorgangs-ID senden</span>
                    </div>
                </a>';
            }
            if ($safe_addr !== '' || $safe_hours !== '') {
                $contact_cards_html .= '<div class="vgt-lp-contact-card vgt-card-info">
                    <div class="vgt-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="vgt-card-body">
                        <small>STANDORT &amp; SERVICEZEITEN</small>
                        ' . ($safe_addr !== '' ? '<strong>' . $safe_addr . '</strong>' : '') . '
                        ' . ($safe_hours !== '' ? '<span>' . $safe_hours . '</span>' : '') . '
                    </div>
                </div>';
            }
            $contact_cards_html .= '</div>';
        }

        $default_sec_note = 'Unsere automatisierte Web-Firewall hat Ihre Netzwerkverbindung vorübergehend pausiert, um die Website vor verdächtigen Aktivitäten zu schützen. Wenn Sie ein regulärer Besucher sind: Keine Sorge! Rufen Sie uns kurz an oder schreiben Sie uns eine E-Mail mit Ihrer Vorgangs-ID – unser Serviceteam schaltet Ihren Zugang unverzüglich frei.';
        $final_sec_note = $safe_sec_note !== '' ? $safe_sec_note : $default_sec_note;

        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $safe_title . '</title>
    <style>
        :root {
            --bg: #06070a;
            --surface: rgba(13, 16, 23, 0.88);
            --surface-glass: rgba(18, 22, 34, 0.78);
            --accent: ' . esc_attr($accent_color) . ';
            --accent-glow: ' . esc_attr($accent_color) . '40;
            --crimson: #ff2a5f;
            --crimson-glow: rgba(255, 42, 95, 0.35);
            --cyan: #00e5ff;
            --text-main: #f0f3f8;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --border-glass: rgba(255, 255, 255, 0.08);
            --mono-font: "JetBrains Mono", "SF Mono", "Fira Code", Consolas, monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            ' . $bg_css . '
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .vgt-lp-header {
            width: 100%;
            background: rgba(10, 12, 18, 0.85);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-glass);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: sticky; top: 0; z-index: 100;
        }
        .vgt-lp-logo { max-height: 52px; max-width: 220px; object-fit: contain; }
        .vgt-lp-emblem {
            display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 18px; color: #ffffff;
        }
        .vgt-lp-emblem svg { width: 28px; height: 28px; }
        .vgt-lp-header-phone {
            display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px;
            border-radius: 30px; background: rgba(0, 229, 255, 0.1); border: 1px solid var(--accent);
            color: var(--accent); text-decoration: none; font-weight: 700; font-size: 13px;
            transition: all 0.2s ease;
        }
        .vgt-lp-header-phone:hover {
            background: var(--accent); color: #06070a; box-shadow: 0 0 20px var(--accent-glow);
        }
        .vgt-lp-main {
            flex: 1;
            width: 100%;
            max-width: 960px;
            margin: 0 auto;
            padding: 40px 20px 60px 20px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }
        .vgt-lp-hero-card {
            background: var(--surface);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }
        .vgt-lp-hero-card h1 {
            font-size: 32px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff; margin-bottom: 8px; line-height: 1.25;
        }
        .vgt-lp-tagline {
            font-size: 16px; font-weight: 600; color: var(--accent); margin-bottom: 18px;
        }
        .vgt-lp-desc {
            color: #cbd5e1; font-size: 15px; line-height: 1.7; margin-bottom: 24px;
        }
        .vgt-lp-features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 10px;
        }
        .vgt-lp-feature-card {
            background: var(--surface-glass); border: 1px solid var(--border-glass);
            border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px;
        }
        .vgt-lp-feature-check {
            width: 26px; height: 26px; border-radius: 50%; background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981; color: #10b981; display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800; flex-shrink: 0;
        }
        .vgt-lp-feature-text { font-size: 13.5px; font-weight: 600; color: #f1f5f9; }
        .vgt-lp-contact-cards {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px;
        }
        .vgt-lp-contact-card {
            background: var(--surface); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-glass); border-radius: 16px; padding: 22px;
            display: flex; align-items: center; gap: 16px; text-decoration: none; color: #ffffff;
            transition: all 0.25s ease; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        .vgt-lp-contact-card:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(0,0,0,0.6); }
        .vgt-card-phone { border-color: rgba(0, 229, 255, 0.3); }
        .vgt-card-phone:hover { border-color: #00e5ff; box-shadow: 0 0 25px rgba(0, 229, 255, 0.25); }
        .vgt-card-email { border-color: rgba(94, 234, 212, 0.3); }
        .vgt-card-email:hover { border-color: #5eead4; box-shadow: 0 0 25px rgba(94, 234, 212, 0.25); }
        .vgt-card-icon {
            width: 50px; height: 50px; border-radius: 14px; background: rgba(255, 255, 255, 0.05);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .vgt-card-icon svg { width: 24px; height: 24px; stroke: var(--accent); }
        .vgt-card-body small {
            display: block; font-size: 10.5px; font-weight: 700; letter-spacing: 1px; color: var(--text-dim); text-transform: uppercase; margin-bottom: 4px;
        }
        .vgt-card-body strong {
            display: block; font-size: 15px; font-weight: 700; color: #ffffff; margin-bottom: 2px;
        }
        .vgt-card-body span { font-size: 12px; color: var(--text-muted); }
        .vgt-lp-security-card {
            background: rgba(10, 12, 18, 0.92);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 42, 95, 0.3); border-radius: 18px;
            padding: 30px 34px; box-shadow: 0 15px 50px rgba(0, 0, 0, 0.7);
        }
        .vgt-security-badge-bar {
            display: inline-flex; align-items: center; gap: 8px; font-family: var(--mono-font);
            font-size: 11px; font-weight: 700; color: var(--crimson); text-transform: uppercase;
            letter-spacing: 1.5px; margin-bottom: 12px;
        }
        .pulse-dot {
            width: 8px; height: 8px; background: var(--crimson); border-radius: 50%;
            box-shadow: 0 0 10px var(--crimson); animation: pulse 1.8s infinite ease-in-out;
        }
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: 0.4; } }
        .vgt-lp-security-card h3 {
            font-size: 18px; font-weight: 700; color: #ffffff; margin-bottom: 8px;
        }
        .vgt-security-note {
            font-size: 13.5px; line-height: 1.6; color: var(--text-muted); margin-bottom: 20px;
        }
        .vgt-telemetry-grid {
            background: var(--surface-glass); border: 1px solid var(--border-glass);
            border-radius: 12px; padding: 18px; margin-bottom: 16px;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;
        }
        .telemetry-item { display: flex; flex-direction: column; gap: 4px; }
        .telemetry-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: var(--text-dim); font-weight: 600; }
        .telemetry-value { font-family: var(--mono-font); font-size: 12px; font-weight: 600; color: #ffffff; word-break: break-all; }
        .telemetry-value.highlight { color: var(--cyan); }
        .telemetry-value.danger { color: var(--crimson); }
        .telemetry-full { grid-column: 1 / -1; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 10px; margin-top: 2px; }
        .vgt-copy-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05);
            border: 1px dashed rgba(255, 255, 255, 0.16); border-radius: 8px;
            color: #e2e8f0; font-family: var(--mono-font); font-size: 12px; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
        }
        .vgt-copy-btn:hover {
            background: rgba(255, 255, 255, 0.1); border-color: var(--cyan); color: #ffffff;
        }
        .vgt-copy-btn svg { width: 15px; height: 15px; stroke: currentColor; }
        .vgt-lp-footer {
            width: 100%; text-align: center; padding: 24px; color: var(--text-dim);
            font-size: 12px; font-family: var(--mono-font);
        }
        @media (max-width: 768px) {
            .vgt-lp-header { padding: 14px 18px; flex-direction: column; align-items: flex-start; gap: 10px; }
            .vgt-lp-main { padding: 20px 14px; gap: 20px; }
            .vgt-lp-hero-card { padding: 24px 18px; }
            .vgt-lp-hero-card h1 { font-size: 24px; }
            .vgt-lp-security-card { padding: 22px 16px; }
            .vgt-telemetry-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="vgt-lp-header">
    ' . $logo_html . '
    <div class="vgt-lp-header-contact">
        ' . ($safe_phone !== '' ? '<a href="tel:' . $clean_phone . '" class="vgt-lp-header-phone">📞 ' . $safe_phone . '</a>' : '') . '
    </div>
</header>
<main class="vgt-lp-main">
    <section class="vgt-lp-hero-card">
        <h1>' . $safe_title . '</h1>
        ' . ($safe_tagline !== '' ? '<div class="vgt-lp-tagline">' . $safe_tagline . '</div>' : '') . '
        ' . ($safe_desc !== '' ? '<div class="vgt-lp-desc">' . $safe_desc . '</div>' : '') . '
        ' . $features_html . '
    </section>

    ' . $contact_cards_html . '

    <section class="vgt-lp-security-card">
        <div class="vgt-security-badge-bar">
            <span class="pulse-dot"></span>
            ' . $safe_badge . ' // HTTP 403
        </div>
        <h3>Sicherheitshinweis // Zugriff vorübergehend isoliert</h3>
        <p class="vgt-security-note">' . $final_sec_note . '</p>

        <div class="vgt-telemetry-grid">
            <div class="telemetry-item">
                <span class="telemetry-label">Vorgangs-ID / Referenz</span>
                <span class="telemetry-value danger">' . $ref_code . '</span>
            </div>
            <div class="telemetry-item">
                <span class="telemetry-label">Ihre IP-Adresse</span>
                <span class="telemetry-value highlight">' . $safe_ip . '</span>
            </div>
            <div class="telemetry-item">
                <span class="telemetry-label">Zeitpunkt (UTC)</span>
                <span class="telemetry-value">' . $utc_time . '</span>
            </div>
            <div class="telemetry-item">
                <span class="telemetry-label">Schutz-Ebene</span>
                <span class="telemetry-value">' . $safe_engine . '</span>
            </div>
            <div class="telemetry-item telemetry-full">
                <span class="telemetry-label">Sicherheitsbegründung</span>
                <span class="telemetry-value">' . $safe_msg . '</span>
            </div>
        </div>

        <button type="button" class="vgt-copy-btn" onclick="vgtCopyIncident()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <span id="vgt-copy-text">Vorgangsdaten für Kundenservice kopieren</span>
        </button>
    </section>
</main>
<footer class="vgt-lp-footer">
    &copy; ' . date('Y') . ' ' . $safe_company . ' &bull; Geschützt durch VisionGaia Sovereign Security System
</footer>
<script>
function vgtCopyIncident() {
    var text = "Vorgangs-ID: ' . esc_js($ref_code) . '\\nIP-Adresse: ' . esc_js($safe_ip) . '\\nZeitpunkt (UTC): ' . esc_js($utc_time) . '\\nSchutz-Ebene: ' . esc_js($safe_engine) . '\\nBegr\\u00FCndung: ' . esc_js($safe_msg) . '";
    var copyBtn = document.getElementById("vgt-copy-text");
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            if (copyBtn) {
                var prev = copyBtn.textContent;
                copyBtn.textContent = "✓ In die Zwischenablage kopiert!";
                setTimeout(function() { copyBtn.textContent = prev; }, 3000);
            }
        }).catch(function() {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
    function fallbackCopy(val) {
        var ta = document.createElement("textarea");
        ta.value = val;
        ta.style.position = "fixed";
        ta.style.left = "-9999px";
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            document.execCommand("copy");
            if (copyBtn) {
                var prev = copyBtn.textContent;
                copyBtn.textContent = "✓ In die Zwischenablage kopiert!";
                setTimeout(function() { copyBtn.textContent = prev; }, 3000);
            }
        } catch(e) {}
        document.body.removeChild(ta);
    }
}
</script>
</body>
</html>';
    }

    /**
     * VGT KERNEL: Klassische Cyberpunk 403 Shield Card (wenn Branding deaktiviert ist)
     * 100% autark, 0 externe Ressourcen, maximale Härtung.
     */
    private function render_classic_cyberpunk_block(
        string $safe_msg,
        string $safe_ip,
        string $ref_code,
        string $utc_time,
        string $safe_engine,
        string $safe_badge
    ): string {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden — VisionGaia Defense</title>
    <style>
        :root {
            --bg: #06070a;
            --surface: rgba(13, 16, 23, 0.90);
            --surface-card: rgba(18, 22, 34, 0.85);
            --border: rgba(255, 42, 95, 0.28);
            --crimson: #ff2a5f;
            --crimson-glow: rgba(255, 42, 95, 0.35);
            --cyan: #00e5ff;
            --text-main: #f0f3f8;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --mono-font: "JetBrains Mono", "SF Mono", "Fira Code", Consolas, monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(ellipse at 50% 0%, rgba(255, 42, 95, 0.18) 0%, rgba(6, 7, 10, 0) 70%),
                radial-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px);
            background-size: 100% 100%, 28px 28px;
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .container { width: 100%; max-width: 680px; position: relative; }
        .glow-orb {
            position: absolute; top: -60px; left: 50%; transform: translateX(-50%);
            width: 320px; height: 180px;
            background: radial-gradient(circle, var(--crimson-glow) 0%, transparent 70%);
            filter: blur(40px); pointer-events: none; z-index: 0;
        }
        .shield-card {
            position: relative; z-index: 1;
            background: var(--surface);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border); border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7), 0 0 40px rgba(255, 42, 95, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.08);
            text-align: center;
        }
        .badge-bar {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 42, 95, 0.1); border: 1px solid rgba(255, 42, 95, 0.3);
            border-radius: 30px; padding: 6px 14px; font-size: 11px; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase; color: var(--crimson);
            margin-bottom: 24px; box-shadow: 0 0 15px rgba(255, 42, 95, 0.15);
        }
        .pulse-dot {
            width: 7px; height: 7px; background: var(--crimson); border-radius: 50%;
            box-shadow: 0 0 8px var(--crimson); animation: pulse 1.8s infinite ease-in-out;
        }
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: 0.4; } }
        .icon-wrap {
            width: 72px; height: 72px; margin: 0 auto 20px auto;
            background: rgba(255, 42, 95, 0.08); border: 1px solid rgba(255, 42, 95, 0.3);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 30px rgba(255, 42, 95, 0.2);
        }
        .icon-wrap svg {
            width: 36px; height: 36px; fill: none; stroke: var(--crimson);
            stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
        }
        .status-code {
            font-family: var(--mono-font); font-size: 13px; font-weight: 700;
            color: var(--cyan); letter-spacing: 3px; text-transform: uppercase; margin-bottom: 8px;
        }
        h1 { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff; margin-bottom: 12px; }
        .summary-text { color: var(--text-muted); font-size: 14px; line-height: 1.6; max-width: 520px; margin: 0 auto 28px auto; }
        .telemetry-box {
            background: var(--surface-card); border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px; padding: 20px; margin-bottom: 24px; text-align: left;
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
        }
        @media (max-width: 520px) {
            .telemetry-box { grid-template-columns: 1fr; gap: 12px; padding: 16px; }
            .shield-card { padding: 30px 20px; }
            h1 { font-size: 24px; }
        }
        .telemetry-item { display: flex; flex-direction: column; gap: 4px; }
        .telemetry-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: var(--text-dim); font-weight: 600; }
        .telemetry-value { font-family: var(--mono-font); font-size: 12px; font-weight: 600; color: #ffffff; word-break: break-all; }
        .telemetry-value.highlight { color: var(--cyan); }
        .telemetry-value.danger { color: var(--crimson); }
        .telemetry-full { grid-column: 1 / -1; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 12px; margin-top: 4px; }
        .vgt-copy-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05);
            border: 1px dashed rgba(255, 255, 255, 0.16); border-radius: 8px;
            color: #e2e8f0; font-family: var(--mono-font); font-size: 12px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; margin-bottom: 20px;
        }
        .vgt-copy-btn:hover { background: rgba(255, 255, 255, 0.1); border-color: var(--cyan); color: #ffffff; }
        .vgt-copy-btn svg { width: 14px; height: 14px; stroke: currentColor; }
        .advisory { font-size: 12px; line-height: 1.6; color: var(--text-dim); margin-bottom: 20px; }
        .advisory strong { color: var(--text-muted); }
        .footer-brand {
            border-top: 1px solid rgba(255, 255, 255, 0.06); padding-top: 18px;
            display: flex; align-items: center; justify-content: space-between;
            font-family: var(--mono-font); font-size: 10px; color: var(--text-dim);
            letter-spacing: 1px; text-transform: uppercase;
        }
        .footer-brand span.shield-name { color: var(--text-muted); font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <div class="glow-orb"></div>
    <div class="shield-card">
        <div class="badge-bar">
            <span class="pulse-dot"></span>
            ' . $safe_badge . '
        </div>
        <div class="icon-wrap">
            <svg viewBox="0 0 24 24">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <path d="M9 12l2 2 4-4"/>
            </svg>
        </div>
        <div class="status-code">HTTP 403 // FORBIDDEN</div>
        <h1>ACCESS RESTRICTED</h1>
        <p class="summary-text">' . $safe_msg . '</p>
        <div class="telemetry-box">
            <div class="telemetry-item">
                <span class="telemetry-label">Client IP Address</span>
                <span class="telemetry-value highlight">' . $safe_ip . '</span>
            </div>
            <div class="telemetry-item">
                <span class="telemetry-label">Defense Layer</span>
                <span class="telemetry-value">' . $safe_engine . '</span>
            </div>
            <div class="telemetry-item">
                <span class="telemetry-label">Incident Reference</span>
                <span class="telemetry-value danger">' . $ref_code . '</span>
            </div>
            <div class="telemetry-item">
                <span class="telemetry-label">Timestamp</span>
                <span class="telemetry-value">' . $utc_time . '</span>
            </div>
            <div class="telemetry-item telemetry-full">
                <span class="telemetry-label">Security Reason</span>
                <span class="telemetry-value">' . $safe_msg . '</span>
            </div>
        </div>
        <button type="button" class="vgt-copy-btn" onclick="vgtCopyIncident()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <span id="vgt-copy-text">Vorgangsdaten kopieren</span>
        </button>
        <p class="advisory">
            Your connection has been flagged by the automated perimeter security system. 
            If you believe this is a false positive, please contact the site administrator and provide your <strong>Incident Reference</strong> and <strong>IP Address</strong>.
        </p>
        <div class="footer-brand">
            <span class="shield-name">VISIONGAIA TECHNOLOGY</span>
            <span>CERBERUS PROTOCOL V8.1</span>
        </div>
    </div>
</div>
<script>
function vgtCopyIncident() {
    var text = "Vorgangs-ID: ' . esc_js($ref_code) . '\\nIP-Adresse: ' . esc_js($safe_ip) . '\\nZeitpunkt (UTC): ' . esc_js($utc_time) . '\\nSchutz-Ebene: ' . esc_js($safe_engine) . '\\nBegr\\u00FCndung: ' . esc_js($safe_msg) . '";
    var copyBtn = document.getElementById("vgt-copy-text");
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            if (copyBtn) {
                var prev = copyBtn.textContent;
                copyBtn.textContent = "✓ In die Zwischenablage kopiert!";
                setTimeout(function() { copyBtn.textContent = prev; }, 3000);
            }
        }).catch(function() {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
    function fallbackCopy(val) {
        var ta = document.createElement("textarea");
        ta.value = val;
        ta.style.position = "fixed";
        ta.style.left = "-9999px";
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            document.execCommand("copy");
            if (copyBtn) {
                var prev = copyBtn.textContent;
                copyBtn.textContent = "✓ In die Zwischenablage kopiert!";
                setTimeout(function() { copyBtn.textContent = prev; }, 3000);
            }
        } catch(e) {}
        document.body.removeChild(ta);
    }
}
</script>
</body>
</html>';
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
