<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

// STATUS: PLATIN
class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

final class VIS_Security {

    private const PRIVATE_HOSTS = ['localhost', 'localhost.localdomain'];

    public static function normalize_relative_path(string $path): string {
        $path = wp_normalize_path(str_replace("\0", '', $path));
        $path = ltrim($path, '/\\');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new InvalidArgumentException('Path escaped jail.');
            }
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $segment)) {
                throw new InvalidArgumentException('Path contains rejected token.');
            }
            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new InvalidArgumentException('Path is empty.');
        }

        return implode('/', $segments);
    }

    public static function jailed_path(string $base_dir, string $relative_path): string {
        if (!is_dir($base_dir) && !wp_mkdir_p($base_dir)) {
            throw new RuntimeException('Storage directory unavailable.');
        }

        $resolvedDir = realpath($base_dir);
        if ($resolvedDir === false || !is_dir($resolvedDir)) {
            throw new RuntimeException('Storage directory unresolved.');
        }

        $relative = self::normalize_relative_path($relative_path);
        $destination = $resolvedDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!str_starts_with($destination, $resolvedDir . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Path escaped jail.');
        }

        return $destination;
    }

    public static function sanitize_hades_segment(string $value, string $fallback): string {
        $value = trim(wp_normalize_path($value), '/');
        if ($value === '') {
            return $fallback;
        }

        $segments = explode('/', $value);
        foreach ($segments as $segment) {
            if ($segment === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/i', $segment)) {
                return $fallback;
            }
        }

        return implode('/', $segments);
    }

    public static function validate_public_http_url(string $url, bool $https_only = true): string {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('URL rejected.');
        }

        $scheme = strtolower((string)$parts['scheme']);
        if ($https_only && $scheme !== 'https') {
            throw new InvalidArgumentException('URL scheme rejected.');
        }
        if (!$https_only && !in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('URL scheme rejected.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URL credentials rejected.');
        }

        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (!in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('URL port rejected.');
        }

        $host = strtolower(rtrim((string)$parts['host'], '.'));
        if (in_array($host, self::PRIVATE_HOSTS, true)) {
            throw new InvalidArgumentException('URL host rejected.');
        }

        $resolved_ips = self::resolve_host_ips($host);
        if ($resolved_ips === []) {
            throw new InvalidArgumentException('URL host unresolved.');
        }

        foreach ($resolved_ips as $ip) {
            if (!self::is_public_ip($ip)) {
                throw new InvalidArgumentException('URL resolved to private network.');
            }
        }

        return esc_url_raw($url, ['http', 'https']);
    }

    /**
     * DNS-rebinding-resistant HTTPS fetch. The validated address is pinned into
     * the TLS connection and the connected peer is verified after the handshake.
     *
     * @return array{headers:array<string,string>,body:string,response:array{code:int,message:string}}|WP_Error
     */
    public static function pinned_https_get(string $url, int $timeout, int $max_bytes, string $user_agent = 'VGT-Sentinel/7.4'): array|WP_Error {
        if (!function_exists('curl_init') || !defined('CURLOPT_RESOLVE')) {
            return new WP_Error('vgt_secure_transport_unavailable', 'Secure remote transport unavailable.');
        }
        if ($timeout < 1 || $timeout > 30 || $max_bytes < 1 || $max_bytes > 16777216) {
            return new WP_Error('vgt_transport_boundary', 'Remote transport boundary rejected.');
        }

        try {
            $safe_url = self::validate_public_http_url($url, true);
        } catch (Throwable $e) {
            return new WP_Error('vgt_rejected_url', 'Remote URL rejected.');
        }

        $parts = wp_parse_url($safe_url);
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        $port = isset($parts['port']) ? (int)$parts['port'] : 443;
        $ips = self::resolve_host_ips($host);
        $public_ips = array_values(array_filter($ips, [self::class, 'is_public_ip']));
        if ($public_ips === [] || count($public_ips) !== count($ips)) {
            return new WP_Error('vgt_rejected_resolution', 'Remote host resolution rejected.');
        }

        $ip = $public_ips[0];
        $resolve_ip = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
        $headers = [];
        $body = '';
        $overflow = false;
        $handle = curl_init();
        if ($handle === false) {
            return new WP_Error('vgt_transport_init', 'Secure remote transport unavailable.');
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => $safe_url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => substr($user_agent, 0, 256),
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, $resolve_ip)],
            CURLOPT_HEADERFUNCTION => static function($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $position = strpos($line, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($line, 0, $position)));
                    $value = trim(substr($line, $position + 1));
                    if ($name !== '' && strlen($value) <= 8192) $headers[$name] = $value;
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function($curl, string $chunk) use (&$body, &$overflow, $max_bytes): int {
                if (strlen($body) + strlen($chunk) > $max_bytes) {
                    $overflow = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        $executed = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $primary_ip = (string)curl_getinfo($handle, CURLINFO_PRIMARY_IP);
        $error = curl_error($handle);
        curl_close($handle);

        if ($overflow) return new WP_Error('vgt_response_too_large', 'Remote response exceeded boundary.');
        if ($executed === false || $error !== '') return new WP_Error('vgt_transport_failed', 'Secure remote request failed.');
        if (!self::is_public_ip($primary_ip) || !in_array($primary_ip, $public_ips, true)) {
            return new WP_Error('vgt_peer_mismatch', 'Remote peer verification failed.');
        }

        return ['headers' => $headers, 'body' => $body, 'response' => ['code' => $status, 'message' => '']];
    }

    public const CF_RANGES = [
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

    public static function is_cloudflare_ip(string $ip): bool {
        $ip_bin = @inet_pton($ip);
        if ($ip_bin === false) return false;

        $is_v6 = (strlen($ip_bin) === 16);
        $ranges = $is_v6 ? self::CF_RANGES['v6'] : self::CF_RANGES['v4'];

        foreach ($ranges as $cidr) {
            if (self::cidr_match_bin($ip_bin, $cidr)) {
                return true;
            }
        }
        return false;
    }

    public static function cidr_match_bin(string $ip_bin, string $cidr): bool {
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

    public static function is_public_ip(string $ip): bool {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    public static function client_ip(): string {
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && self::is_cloudflare_ip($remote_addr)) {
            $cf_ip = filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP);
            if ($cf_ip !== false && self::is_public_ip((string)$cf_ip)) {
                return (string)$cf_ip;
            }
        }
        return filter_var($remote_addr, FILTER_VALIDATE_IP) ? (string)$remote_addr : '0.0.0.0';
    }

    public static function validate_hades_gate(string $admin_secret): bool {
        if (!isset($_COOKIE['vgt_hades_gate']) || !is_string($_COOKIE['vgt_hades_gate'])) {
            return false;
        }
        $expected = hash_hmac('sha256', $admin_secret, wp_salt('auth'));
        return hash_equals($expected, (string)$_COOKIE['vgt_hades_gate']);
    }

    public static function rate_limit(string $scope, int $limit, int $window_seconds): bool {
        if ($limit < 1 || $window_seconds < 1) {
            throw new ValidationException('Rate boundary violation.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vis_rate_limits';
        $scope_hash = hash('sha256', $scope);
        $now = time();
        $window_start = $now - ($now % $window_seconds);
        $expires_at = $window_start + $window_seconds;

        $query = $wpdb->prepare(
            "INSERT INTO {$table} (scope_hash, window_start, hits, expires_at)
             VALUES (%s, %d, 1, %d)
             ON DUPLICATE KEY UPDATE
                hits = IF(window_start = VALUES(window_start), hits + 1, 1),
                expires_at = VALUES(expires_at),
                window_start = VALUES(window_start)",
            $scope_hash,
            $window_start,
            $expires_at
        );

        if ($wpdb->query($query) === false) {
            error_log('[VIS RATE LIMIT] Atomic storage unavailable. Request rejected.');
            return false;
        }

        $hits = $wpdb->get_var($wpdb->prepare(
            "SELECT hits FROM {$table} WHERE scope_hash = %s AND window_start = %d",
            $scope_hash,
            $window_start
        ));

        if (!is_numeric($hits)) {
            error_log('[VIS RATE LIMIT] Counter verification failed. Request rejected.');
            return false;
        }

        if (random_int(1, 1000) === 1) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %d LIMIT 1000", $now));
        }

        return (int)$hits <= $limit;
    }

    private static function resolve_host_ips(string $host): array {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        $a_records = function_exists('dns_get_record') ? @dns_get_record($host, DNS_A + DNS_AAAA) : false;
        if (is_array($a_records)) {
            foreach ($a_records as $record) {
                if (!empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === [] && function_exists('gethostbynamel')) {
            $legacy = @gethostbynamel($host);
            if (is_array($legacy)) {
                $ips = array_merge($ips, $legacy);
            }
        }

        return array_values(array_unique($ips));
    }
}
