<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Sandbox {
    private const OPTION = 'vis_titan_active_content';
    private const MAX_RECORDS = 100;
    private const MAX_PREVIEW_BYTES = 5242880;
    private const ACTIVE_EXTENSIONS = ['html','htm','svg','xml'];

    /** @param array<string, mixed> $upload @return array<string, mixed> */
    public static function registerUpload(array $upload): array {
        $config = get_option('vis_config', []);
        if (!is_array($config) || empty($config['airlock_enabled'])) return $upload;
        $path = isset($upload['file']) && is_string($upload['file']) ? $upload['file'] : '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($path === '' || !in_array($extension, self::ACTIVE_EXTENSIONS, true) || !is_file($path)) return $upload;
        try {
            $digest = hash_file('sha256', $path);
            if (!is_string($digest)) throw new StorageException('Active content receipt hashing failed.');
            $receiptKey = 'vis_airlock_receipt_' . $digest;
            $receipt = get_transient($receiptKey);
            if (!is_array($receipt)
                || !hash_equals($digest, (string)($receipt['sha256'] ?? ''))
                || !hash_equals('AIRLOCK_INSPECTED_UPLOAD', (string)($receipt['classification'] ?? ''))) {
                throw new SecurityException('Active content lacks an authenticated Airlock inspection receipt.');
            }
            delete_transient($receiptKey);
            $record = self::recordForPath($path, (string)($receipt['mime'] ?? $upload['type'] ?? 'application/octet-stream'));
            $records = get_option(self::OPTION, []);
            $records = is_array($records) ? $records : [];
            $records[$record['id']] = $record;
            uasort($records, static fn(array $a, array $b): int => strcmp((string)$b['registered_at'], (string)$a['registered_at']));
            update_option(self::OPTION, array_slice($records, 0, self::MAX_RECORDS, true), false);
        } catch (ValidationException $e) {
            error_log('[TITAN SANDBOX VALIDATION] ' . $e->getMessage());
        } catch (SecurityException $e) {
            error_log('[TITAN SANDBOX SECURITY] ' . $e->getMessage());
        } catch (StorageException $e) {
            error_log('[TITAN SANDBOX STORAGE] ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log('[TITAN SANDBOX FATAL] ' . $e->getMessage());
        }
        return $upload;
    }

    public static function servePreview(): void {
        if (!isset($_GET['vis_titan_preview'], $_GET['token']) || !is_string($_GET['vis_titan_preview']) || !is_string($_GET['token'])) return;
        try {
            $id = sanitize_key(wp_unslash($_GET['vis_titan_preview']));
            $token = wp_unslash($_GET['token']);
            if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1 || !self::verifyToken($id, $token)) throw new SecurityException('Sandbox preview token validation failed.');
            $sandboxOrigin = self::configuredOrigin();
            if ($sandboxOrigin !== null) {
                $expectedHost = strtolower((string)wp_parse_url($sandboxOrigin, PHP_URL_HOST));
                $requestHost = strtolower(preg_replace('/:\\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
                if ($requestHost === '' || !hash_equals($expectedHost, $requestHost)) throw new SecurityException('Dedicated sandbox origin validation failed.');
            }
            $records = get_option(self::OPTION, []);
            $record = is_array($records) && is_array($records[$id] ?? null) ? $records[$id] : [];
            if ($record === []) throw new ValidationException('Sandbox preview record unavailable.');
            $path = self::resolveRecordPath($record);
            $size = filesize($path);
            if ($size === false || $size === 0 || $size > self::MAX_PREVIEW_BYTES || $size !== (int)($record['size'] ?? 0)) throw new SecurityException('Sandbox preview size validation failed.');
            $hash = hash_file('sha256', $path);
            if (!is_string($hash) || !hash_equals((string)($record['hash'] ?? ''), $hash)) throw new SecurityException('Sandbox preview integrity validation failed.');
            if (!headers_sent()) {
                http_response_code(200);
                header('Content-Type: ' . self::safeMime((string)($record['mime'] ?? 'text/plain')));
                foreach (self::previewHeaders() as $name => $value) header($name . ': ' . $value, true);
                header('Content-Length: ' . $size);
            }
            readfile($path);
            exit;
        } catch (ValidationException $e) {
            self::fail(404);
        } catch (SecurityException $e) {
            error_log('[TITAN SANDBOX SECURITY] ' . $e->getMessage());
            self::fail(403);
        } catch (StorageException $e) {
            error_log('[TITAN SANDBOX STORAGE] ' . $e->getMessage());
            self::fail(500);
        } catch (Throwable $e) {
            error_log('[TITAN SANDBOX FATAL] ' . $e->getMessage());
            self::fail(500);
        }
    }

    public static function handlePreviewLink(): void {
        if (!is_user_logged_in() || !current_user_can('manage_options')) wp_die('Request rejected for security reasons.', '', ['response' => 403]);
        check_admin_referer('vis_titan_preview_link');
        $id = isset($_POST['id']) && is_string($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
        $records = get_option(self::OPTION, []);
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1 || !is_array($records) || !is_array($records[$id] ?? null)) wp_die('Preview unavailable.', '', ['response' => 404]);
        $expiration = time() + 300;
        $payload = $id . '|' . $expiration;
        $token = $expiration . '.' . hash_hmac('sha256', "GEDEFENSE:TITAN:PREVIEW:v1\0" . $payload, self::secret());
        $url = add_query_arg(['vis_titan_preview' => $id, 'token' => $token], self::configuredOrigin() ?? home_url('/'));
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
        }
        echo $url;
        exit;
    }

    /** @return list<array<string, mixed>> */
    public static function records(): array {
        $records = get_option(self::OPTION, []);
        return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
    }

    /** @return array<string, string> */
    public static function previewHeaders(): array {
        return [
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), fullscreen=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Origin-Agent-Cluster' => '?1',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ];
    }

    /** @return array<string, mixed> */
    private static function recordForPath(string $path, string $mime): array {
        $uploads = wp_get_upload_dir();
        $root = realpath((string)($uploads['basedir'] ?? ''));
        $resolved = realpath($path);
        if ($root === false || $resolved === false || !is_file($resolved) || is_link($path)) throw new SecurityException('Active content path validation failed.');
        $rootPrefix = rtrim(wp_normalize_path($root), '/') . '/';
        $normalized = wp_normalize_path($resolved);
        if (!str_starts_with($normalized, $rootPrefix)) throw new SecurityException('Active content path escaped upload jail.');
        $relative = substr($normalized, strlen($rootPrefix));
        $size = filesize($resolved);
        $hash = hash_file('sha256', $resolved);
        if ($relative === '' || $size === false || $size === 0 || $size > self::MAX_PREVIEW_BYTES || !is_string($hash)) throw new ValidationException('Active content boundary violation.');
        return ['id' => bin2hex(random_bytes(16)), 'relative_path' => $relative, 'hash' => $hash, 'size' => $size, 'mime' => self::safeMime($mime), 'registered_at' => gmdate('c'), 'isolation' => self::configuredOrigin() !== null ? 'DEDICATED_ORIGIN_CSP_SANDBOX' : 'CSP_SANDBOX_FALLBACK'];
    }

    private static function resolveRecordPath(array $record): string {
        $relative = (string)($record['relative_path'] ?? '');
        if ($relative === '' || str_contains($relative, "\0") || str_starts_with($relative, '/') || preg_match('~(?:^|/)\.\.(?:/|$)~', $relative) === 1) throw new SecurityException('Sandbox path traversal rejected.');
        $uploads = wp_get_upload_dir();
        $root = realpath((string)($uploads['basedir'] ?? ''));
        if ($root === false) throw new StorageException('Sandbox upload root unavailable.');
        $resolvedDir = realpath($root);
        if ($resolvedDir === false || !is_dir($resolvedDir)) throw new StorageException('Sandbox upload root unavailable.');
        $destination = $resolvedDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!str_starts_with($destination, $resolvedDir . DIRECTORY_SEPARATOR)) throw new SecurityException('Sandbox path escaped jail.');
        $resolved = realpath($destination);
        if ($resolved === false || !is_file($resolved) || !str_starts_with($resolved, $resolvedDir . DIRECTORY_SEPARATOR)) throw new SecurityException('Sandbox file resolution rejected.');
        return $resolved;
    }

    private static function verifyToken(string $id, string $token): bool {
        if (substr_count($token, '.') !== 1) return false;
        [$expiration, $mac] = explode('.', $token, 2);
        if (preg_match('/^[0-9]{10}$/D', $expiration) !== 1 || (int)$expiration < time() || (int)$expiration > time() + 300 || preg_match('/^[a-f0-9]{64}$/D', $mac) !== 1) return false;
        $expected = hash_hmac('sha256', "GEDEFENSE:TITAN:PREVIEW:v1\0" . $id . '|' . $expiration, self::secret());
        return hash_equals($expected, $mac);
    }

    private static function secret(): string {
        if (!class_exists('VIS_Key_Vault')) throw new StorageException('TITAN key vault unavailable.');
        $stored = VIS_Key_Vault::get_key('vis_titan_preview_signing_key');
        if (preg_match('/^[a-f0-9]{64}$/D', $stored) !== 1) {
            $stored = bin2hex(random_bytes(32));
            VIS_Key_Vault::save_key('vis_titan_preview_signing_key', $stored);
        }
        return hash('sha256', "GEDEFENSE:TITAN:PREVIEW:KEY:v1\0" . $stored, true);
    }

    private static function safeMime(string $mime): string {
        return in_array($mime, ['text/html','image/svg+xml','application/xml','text/xml'], true) ? $mime : 'text/plain; charset=utf-8';
    }

    private static function configuredOrigin(): ?string {
        $config = get_option('vis_config', []);
        if (!is_array($config) || empty($config['titan_sandbox_origin_verified'])) return null;
        $origin = isset($config['titan_sandbox_origin']) && is_string($config['titan_sandbox_origin']) ? trim($config['titan_sandbox_origin']) : '';
        $parts = wp_parse_url($origin);
        $home = wp_parse_url(home_url('/'));
        if (!is_array($parts) || !is_array($home)
            || !hash_equals('https', strtolower((string)($parts['scheme'] ?? '')))
            || empty($parts['host'])
            || !empty($parts['path'])
            || !empty($parts['query'])
            || !empty($parts['user'])
            || hash_equals(strtolower((string)$parts['host']), strtolower((string)($home['host'] ?? '')))) return null;
        $cookieDomain = defined('COOKIE_DOMAIN') ? strtolower(trim((string)COOKIE_DOMAIN, '.')) : '';
        if ($cookieDomain !== '') {
            $sandboxHost = strtolower((string)$parts['host']);
            if (hash_equals($sandboxHost, $cookieDomain) || str_ends_with($sandboxHost, '.' . $cookieDomain)) return null;
        }
        return 'https://' . strtolower((string)$parts['host']) . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    }

    private static function fail(int $status): never {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        exit($status === 404 ? 'Not Found' : 'Request rejected for security reasons.');
    }
}
