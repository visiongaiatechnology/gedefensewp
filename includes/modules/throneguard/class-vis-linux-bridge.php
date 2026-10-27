<?php
/**
 * GEDEFENSE WP → GEDEFENSE LINUX BRIDGE
 *
 * Sends ThroneGuard master-session records to the local GeDefense Linux L7
 * control plane through WordPress/PHP's existing logging path. This class never
 * creates, opens or writes a custom file and never accepts a filesystem path.
 *
 * The Linux-issued shared secret is stored encrypted in the WordPress options
 * table. Its wrapping key is derived from every usable wp-config.php salt, so a
 * database dump alone cannot recover it.
 *
 * A compromised WordPress can still forge records while it holds the secret;
 * the Linux lease is therefore short-lived, IP/host-bound and only relaxes L7
 * rate limits. WAF and kernel enforcement remain untouched.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

final class VIS_Linux_Bridge {
    private const OPTION_ENABLED       = 'vis_gedefense_linux_bridge_enabled';
    private const OPTION_SECRET_ENC    = 'vis_gedefense_linux_bridge_secret_enc';
    private const OPTION_LAST_EVENT_AT = 'vis_gedefense_linux_bridge_last_event_at';
    private const OPTION_LAST_ERROR    = 'vis_gedefense_linux_bridge_last_error';
    private const OPTION_HELLO_AT      = 'vis_gedefense_linux_bridge_hello_at';
    private const OPTION_TRANSPORT     = 'vis_gedefense_linux_bridge_transport';

    private const STORE_AAD      = 'GeDefense-Linux-Bridge-Store-v1';
    private const EVENT_AAD      = 'GeDefense-Linux-WP-Bridge-V1';
    private const STORE_VERSION  = 'v1';
    private const DEFAULT_TTL    = 7200;
    private const MARKER         = 'GeDefense: ';
    private const SALT_CONSTANTS = [
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ];

    /** Only an unlocked ThroneGuard master may configure the bridge. */
    public static function can_manage(): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }
        if (!class_exists('VIS_Throne_Guard')) {
            return false;
        }
        try {
            $status = VIS_Throne_Guard::status();
        } catch (\Throwable $e) {
            return false;
        }
        return !empty($status['session_unlocked']);
    }

    public static function init(): void {
        add_action('admin_post_vis_gedefense_linux_bridge_save', [self::class, 'handle_save']);
        add_action('admin_post_vis_gedefense_linux_bridge_test', [self::class, 'handle_test']);
        add_action('update_option_vis_throneguard_ip_leases', [self::class, 'leases_updated'], 10, 2);
        add_action('clear_auth_cookie', [self::class, 'emit_logout'], 20);
    }

    /** @return array{available:bool,parts:int} */
    public static function salt_status(): array {
        return ['available' => count(self::usable_salts()) >= 6, 'parts' => count(self::usable_salts())];
    }

    /** @return list<string> */
    private static function usable_salts(): array {
        $values = [];
        foreach (self::SALT_CONSTANTS as $name) {
            if (!defined($name) || !is_string(constant($name))) {
                continue;
            }
            $value = trim((string)constant($name));
            if (strlen($value) < 32) {
                continue;
            }
            if (stripos($value, 'put your unique phrase here') !== false || stripos($value, 'change this') !== false) {
                continue;
            }
            if (in_array($value, $values, true)) {
                continue;
            }
            $values[] = $value;
        }
        return $values;
    }

    private static function storage_key(): ?string {
        $parts = self::usable_salts();
        if (count($parts) < 6) {
            return null;
        }
        $key = hash_hkdf(
            'sha256',
            implode("\x00", $parts),
            32,
            'GeDefense-Linux-Bridge-DB-v1|' . home_url('/'),
            'GEDEFENSE-LINUX-BRIDGE-V1'
        );
        return is_string($key) && strlen($key) === 32 ? $key : null;
    }

    private static function encrypt_at_rest(string $plain): ?string {
        $key = self::storage_key();
        if ($key === null) {
            return null;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, self::STORE_AAD, 16);
        if (!is_string($cipher) || strlen($tag) !== 16) {
            return null;
        }
        return self::STORE_VERSION . ':' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
    }

    private static function decrypt_at_rest(string $stored): ?string {
        $key = self::storage_key();
        if ($key === null) {
            return null;
        }
        $parts = explode(':', $stored, 4);
        if (count($parts) !== 4 || $parts[0] !== self::STORE_VERSION) {
            return null;
        }
        $iv = base64_decode($parts[1], true);
        $tag = base64_decode($parts[2], true);
        $cipher = base64_decode($parts[3], true);
        if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || strlen($tag) !== 16 || !is_string($cipher)) {
            return null;
        }
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, self::STORE_AAD);
        return is_string($plain) ? $plain : null;
    }

    public static function store_secret(string $base64Secret): bool {
        $raw = base64_decode(trim($base64Secret), true);
        if (!is_string($raw) || strlen($raw) !== 32) {
            self::set_error('Secret must be the base64-encoded 32-byte key from GeDefense Linux.');
            return false;
        }
        $encrypted = self::encrypt_at_rest(base64_encode($raw));
        if ($encrypted === null) {
            self::set_error('No encryption key can be derived from wp-config.php salts.');
            return false;
        }
        update_option(self::OPTION_SECRET_ENC, $encrypted, false);
        update_option(self::OPTION_LAST_ERROR, '', false);
        return true;
    }

    private static function secret(): ?string {
        $stored = (string)get_option(self::OPTION_SECRET_ENC, '');
        if ($stored === '') {
            return null;
        }
        $plain = self::decrypt_at_rest($stored);
        if ($plain === null) {
            return null;
        }
        $raw = base64_decode($plain, true);
        return is_string($raw) && strlen($raw) === 32 ? $raw : null;
    }

    private static function enabled(): bool {
        return (string)get_option(self::OPTION_ENABLED, '0') === '1';
    }

    public static function leases_updated($oldValue, $newValue): void {
        if (!self::enabled() || !is_array($newValue)) {
            return;
        }
        $old = is_array($oldValue) ? $oldValue : [];
        $now = time();
        foreach ($newValue as $ip => $lease) {
            if (!is_array($lease)) {
                continue;
            }
            $expires = isset($lease['expires_at']) ? (int)$lease['expires_at'] : 0;
            if ($expires <= $now) {
                continue;
            }
            $oldLease = isset($old[$ip]) && is_array($old[$ip]) ? $old[$ip] : [];
            $oldExpires = isset($oldLease['expires_at']) ? (int)$oldLease['expires_at'] : 0;
            if ($oldExpires >= $expires) {
                continue;
            }
            self::emit_event('master_login', $lease);
        }
    }

    public static function send_test_event(): bool {
        return self::emit_event('bridge_hello', []);
    }

    private static function emit_event(string $eventName, array $lease): bool {
        if (!self::enabled()) {
            self::set_error('Bridge is disabled.');
            return false;
        }
        $secret = self::secret();
        if ($secret === null) {
            self::set_error('No usable bridge secret is configured.');
            return false;
        }
        $host = (string)wp_parse_url(home_url('/'), PHP_URL_HOST);
        if ($host === '') {
            self::set_error('Site host could not be resolved.');
            return false;
        }
        $now = time();
        $ip = isset($lease['ip']) && is_string($lease['ip']) ? trim($lease['ip']) : '';
        if ($ip === '') {
            $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '127.0.0.1';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            self::set_error('Client IP is invalid.');
            return false;
        }
        if ($eventName === 'master_login' && (str_starts_with($ip, '127.') || $ip === '::1' || $ip === '0.0.0.0')) {
            self::set_error('Loopback master leases are rejected; configure proxy real-IP handling.');
            return false;
        }
        if ($eventName === 'master_login') {
            $expires = isset($lease['expires_at']) ? (int)$lease['expires_at'] : 0;
            if ($expires <= $now) {
                return false;
            }
            $expires = min($expires, $now + self::DEFAULT_TTL);
            $user = isset($lease['user_login']) && is_string($lease['user_login']) ? $lease['user_login'] : 'master';
        } elseif ($eventName === 'master_logout') {
            $expires = $now + 300;
            $user = 'master';
        } else {
            $expires = $now + 300;
            $user = 'bridge-test';
        }
        $event = [
            'v' => 1,
            'event' => $eventName,
            'site_host' => $host,
            'ip' => $ip,
            'user' => $user,
            'issued_at' => $now,
            'expires_at' => $expires,
            'event_id' => hash('sha256', $eventName . '|' . $host . '|' . $ip . '|' . $now . '|' . random_bytes(16)),
        ];
        $plain = wp_json_encode($event, JSON_UNESCAPED_SLASHES);
        if (!is_string($plain)) {
            self::set_error('Bridge event encoding failed.');
            return false;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $secret, OPENSSL_RAW_DATA, $iv, $tag, self::EVENT_AAD, 16);
        if (!is_string($cipher) || strlen($tag) !== 16) {
            self::set_error('Bridge event encryption failed.');
            return false;
        }
        $line = self::MARKER . wp_json_encode([
            'slug' => 'GeDefense',
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipher),
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($line) || strlen($line) > 8000) {
            self::set_error('Bridge event envelope is invalid.');
            return false;
        }
        $logged = false;
        $transport = '';
        if ((string)ini_get('log_errors') !== '0' && (string)ini_get('log_errors') !== '') {
            $logged = error_log($line);
            $transport = $logged ? 'php_error_log' : '';
        }
        if (!$logged && function_exists('syslog')) {
            openlog('GeDefense-WP', LOG_PID | LOG_NDELAY, LOG_USER);
            $logged = syslog(LOG_INFO, $line);
            closelog();
            $transport = $logged ? 'syslog' : '';
        }
        if (!$logged) {
            self::set_error('Neither PHP error_log() nor syslog() accepted the event.');
            return false;
        }
        update_option(self::OPTION_LAST_EVENT_AT, (string)$now, false);
        update_option(self::OPTION_TRANSPORT, $transport, false);
        if ($eventName === 'bridge_hello') {
            update_option(self::OPTION_HELLO_AT, (string)$now, false);
        }
        update_option(self::OPTION_LAST_ERROR, '', false);
        return true;
    }

    public static function handle_save(): void {
        if (!self::can_manage()) {
            wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        }
        check_admin_referer('vis_gedefense_linux_bridge_save');
        $enabled = isset($_POST['bridge_enabled']);
        $secret = isset($_POST['bridge_secret']) && is_string($_POST['bridge_secret'])
            ? trim(sanitize_text_field(wp_unslash($_POST['bridge_secret'])))
            : '';
        if ($secret !== '' && !self::store_secret($secret)) {
            self::redirect(['bridge_error' => 'secret']);
            return;
        }
        if ($enabled && self::secret() === null) {
            self::set_error('Cannot enable the bridge without a usable secret.');
            self::redirect(['bridge_error' => 'missing']);
            return;
        }
        update_option(self::OPTION_ENABLED, $enabled ? '1' : '0', false);
        self::redirect(['bridge_saved' => '1']);
    }

    public static function handle_test(): void {
        if (!self::can_manage()) {
            wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        }
        check_admin_referer('vis_gedefense_linux_bridge_test');
        $ok = self::send_test_event();
        self::redirect(['bridge_tested' => $ok ? '1' : '0']);
    }

    private static function set_error(string $message): void {
        update_option(self::OPTION_LAST_ERROR, $message, false);
    }

    private static function redirect(array $args): void {
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=vgt-suite&tab=linux_bridge')));
        exit;
    }

    /** @return array<string, mixed> */
    public static function status(): array {
        $salts = self::salt_status();
        $encStored = (string)get_option(self::OPTION_SECRET_ENC, '');
        $logErrors = (string)ini_get('log_errors');
        return [
            'enabled' => self::enabled(),
            'salts_available' => $salts['available'],
            'salt_parts' => $salts['parts'],
            'secret_stored' => $encStored !== '',
            'secret_usable' => $encStored !== '' && self::secret() !== null,
            'log_errors' => $logErrors !== '0' && $logErrors !== '',
            'error_log' => (string)ini_get('error_log'),
            'transport' => (string)get_option(self::OPTION_TRANSPORT, ''),
            'syslog_available' => function_exists('syslog'),
            'last_event_at' => (string)get_option(self::OPTION_LAST_EVENT_AT, ''),
            'hello_at' => (string)get_option(self::OPTION_HELLO_AT, ''),
            'last_error' => (string)get_option(self::OPTION_LAST_ERROR, ''),
        ];
    }
}