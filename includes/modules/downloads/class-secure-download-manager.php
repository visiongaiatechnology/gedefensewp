<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\SecureDownloads;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class DownloadManager {
    private const MAX_FILE_BYTES = 2147483648;
    private const PUBLIC_ID_PATTERN = '/^[a-f0-9]{32}$/D';
    private const DENIED_EXTENSIONS = ['php','php3','php4','php5','php7','php8','phtml','pht','phar','shtml','cgi','pl','py','rb','sh','bash','exe','dll','com','bat','cmd','msi','htaccess','user.ini'];
    private static bool $booted = false;
    /** @var array<string, object|null> */
    private static array $requestCache = [];

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;
        add_action('template_redirect', [self::class, 'serve'], -100);
        add_action('admin_post_vis_download_register', [self::class, 'registerAction']);
        add_action('admin_post_vis_download_toggle', [self::class, 'toggleAction']);
        add_action('admin_post_vis_download_delete', [self::class, 'deleteAction']);
    }

    public static function isTrustedRequest(): bool {
        $id = self::requestId();
        return $id !== null && self::record($id) !== null;
    }

    public static function serve(): void {
        $id = self::requestId();
        if ($id === null) return;
        $record = self::record($id);
        if ($record === null) {
            status_header(404);
            exit;
        }

        try {
            self::enforceRateLimit($id);
            $path = self::recordPath($record);
            $size = filesize($path);
            if ($size === false || $size < 1 || $size !== (int)$record->file_size) throw new \SecurityException('Secure download size validation failed.');
            $digest = hash_file('sha256', $path);
            if (!is_string($digest) || !hash_equals((string)$record->file_hash, $digest)) throw new \SecurityException('Secure download integrity validation failed.');

            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}vis_secure_downloads SET download_count = LEAST(download_count + 1, 4294967295), last_download_at = %s WHERE public_id = %s",
                gmdate('Y-m-d H:i:s'),
                $id
            ));

            while (ob_get_level() > 0) ob_end_clean();
            nocache_headers();
            header('Content-Type: ' . self::safeMime((string)$record->mime_type));
            header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode((string)$record->display_name));
            header('Content-Length: ' . (string)$size);
            header('Accept-Ranges: none');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: no-referrer');
            header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
            header("Content-Security-Policy: sandbox; default-src 'none'");
            if (is_ssl()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            if (self::method() === 'HEAD') exit;
            $result = readfile($path);
            if ($result === false) error_log('[VGT DOWNLOAD STORAGE] Stream failure.');
            exit;
        } catch (\ValidationException $e) {
            status_header(429);
        } catch (\SecurityException $e) {
            error_log('[VGT DOWNLOAD SECURITY] ' . $e->getMessage());
            self::emitFailure('INTEGRITY_REJECTED');
            status_header(404);
        } catch (\StorageException $e) {
            error_log('[VGT DOWNLOAD STORAGE] ' . $e->getMessage());
            status_header(503);
        } catch (\Throwable $e) {
            error_log('[VGT DOWNLOAD FATAL] ' . $e->getMessage());
            status_header(503);
        }
        exit;
    }

    public static function registerAction(): void {
        self::authorize('vis_download_register');
        try {
            $attachmentId = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
            if ($attachmentId < 1) throw new \ValidationException('Bitte eine Mediendatei auswählen.');
            $sourceInput = get_attached_file($attachmentId, true);
            if (!is_string($sourceInput) || $sourceInput === '') throw new \StorageException('Attachment storage path unavailable.');
            if (is_link($sourceInput)) throw new \SecurityException('Attachment path symlink rejected.');
            $source = realpath($sourceInput);
            $upload = wp_get_upload_dir();
            $uploadRoot = realpath((string)($upload['basedir'] ?? ''));
            if ($source === false || $uploadRoot === false || !is_file($source) || !str_starts_with($source, $uploadRoot . DIRECTORY_SEPARATOR)) {
                throw new \SecurityException('Attachment path escaped upload jail.');
            }
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            if ($extension === '' || in_array($extension, self::DENIED_EXTENSIONS, true)) throw new \SecurityException('Executable download type rejected.');
            $realSize = filesize($source);
            if ($realSize === false || $realSize === 0 || $realSize > self::MAX_FILE_BYTES) throw new \ValidationException('Size boundary violation.');
            $sourceHash = hash_file('sha256', $source);
            if (!is_string($sourceHash)) throw new \StorageException('Source hashing failed.');
            $mime = self::detectMime($source);
            $displayName = sanitize_file_name((string)get_the_title($attachmentId) . '.' . $extension);
            if ($displayName === '.' . $extension) $displayName = 'download.' . $extension;

            $directory = self::ensureDirectory();
            $publicId = bin2hex(random_bytes(16));
            $storageName = bin2hex(random_bytes(32));
            $destination = $directory . DIRECTORY_SEPARATOR . $storageName;
            if (!str_starts_with($destination, $directory . DIRECTORY_SEPARATOR)) throw new \SecurityException('Download destination escaped jail.');
            $temporary = $destination . '.tmp';
            $previousUmask = umask(0077);
            try {
                $copied = copy($source, $temporary);
            } finally {
                umask($previousUmask);
            }
            if (!$copied || !chmod($temporary, 0600)) throw new \StorageException('Secure download staging failed.');
            $copyHash = hash_file('sha256', $temporary);
            if (!is_string($copyHash) || !hash_equals($sourceHash, $copyHash)) {
                @unlink($temporary);
                throw new \SecurityException('Download copy integrity validation failed.');
            }
            if (!rename($temporary, $destination)) {
                @unlink($temporary);
                throw new \StorageException('Secure download atomic commit failed.');
            }

            global $wpdb;
            $ok = $wpdb->insert($wpdb->prefix . 'vis_secure_downloads', [
                'public_id' => $publicId,
                'attachment_id' => $attachmentId,
                'display_name' => substr($displayName, 0, 191),
                'storage_name' => $storageName,
                'mime_type' => $mime,
                'file_size' => $realSize,
                'file_hash' => $sourceHash,
                'enabled' => 1,
                'download_count' => 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($ok !== 1) {
                @unlink($destination);
                throw new \StorageException('Download registry persistence failed.');
            }
            self::redirect('created');
        } catch (\ValidationException $e) {
            self::redirect('validation');
        } catch (\SecurityException $e) {
            error_log('[VGT DOWNLOAD SECURITY] ' . $e->getMessage());
            self::redirect('rejected');
        } catch (\StorageException $e) {
            error_log('[VGT DOWNLOAD STORAGE] ' . $e->getMessage());
            self::redirect('storage');
        } catch (\Throwable $e) {
            error_log('[VGT DOWNLOAD FATAL] ' . $e->getMessage());
            self::redirect('fatal');
        }
    }

    public static function toggleAction(): void {
        self::authorize('vis_download_toggle');
        $id = self::postedId();
        global $wpdb;
        $record = self::record($id, true);
        if ($record !== null) $wpdb->update($wpdb->prefix . 'vis_secure_downloads', ['enabled' => (int)!((bool)$record->enabled)], ['public_id' => $id], ['%d'], ['%s']);
        self::redirect('updated');
    }

    public static function deleteAction(): void {
        self::authorize('vis_download_delete');
        $id = self::postedId();
        $record = self::record($id, true);
        if ($record !== null) {
            try {
                $path = self::recordPath($record);
                global $wpdb;
                if ($wpdb->delete($wpdb->prefix . 'vis_secure_downloads', ['public_id' => $id], ['%s']) !== false && is_file($path)) @unlink($path);
            } catch (\Throwable $e) {
                error_log('[VGT DOWNLOAD DELETE] ' . $e->getMessage());
            }
        }
        self::redirect('deleted');
    }

    /** @return array<int, object> */
    public static function all(int $limit = 100): array {
        global $wpdb;
        $limit = max(1, min(100, $limit));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}vis_secure_downloads ORDER BY id DESC LIMIT %d", $limit));
        return is_array($rows) ? $rows : [];
    }

    public static function url(string $publicId): string {
        return preg_match(self::PUBLIC_ID_PATTERN, $publicId) === 1 ? add_query_arg('vis_secure_download', $publicId, home_url('/')) : '';
    }

    private static function requestId(): ?string {
        if (!in_array(self::method(), ['GET','HEAD'], true) || count($_GET) !== 1 || !isset($_GET['vis_secure_download']) || !is_string($_GET['vis_secure_download'])) return null;
        $id = strtolower(wp_unslash($_GET['vis_secure_download']));
        return preg_match(self::PUBLIC_ID_PATTERN, $id) === 1 ? $id : null;
    }

    private static function method(): string { return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')); }

    private static function record(string $id, bool $includeDisabled = false): ?object {
        $key = $id . ':' . (int)$includeDisabled;
        if (array_key_exists($key, self::$requestCache)) return self::$requestCache[$key];
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}vis_secure_downloads WHERE public_id = %s" . ($includeDisabled ? '' : ' AND enabled = 1') . ' LIMIT 1';
        $record = $wpdb->get_row($wpdb->prepare($sql, $id));
        return self::$requestCache[$key] = is_object($record) ? $record : null;
    }

    private static function recordPath(object $record): string {
        $directory = self::ensureDirectory();
        $name = (string)($record->storage_name ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $name) !== 1) throw new \SecurityException('Stored download path validation failed.');
        $candidate = $directory . DIRECTORY_SEPARATOR . $name;
        $path = realpath($candidate);
        if ($path === false || !is_file($path) || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) throw new \StorageException('Secure download artifact unavailable.');
        return $path;
    }

    private static function ensureDirectory(): string {
        $base = defined('VIS_VAULT_DIR') ? VIS_VAULT_DIR : '';
        if (!is_string($base) || $base === '') throw new \StorageException('Vault path unavailable.');
        if (!is_dir($base) && !wp_mkdir_p($base)) throw new \StorageException('Vault directory unavailable.');
        $resolvedBase = realpath($base);
        if ($resolvedBase === false || !is_dir($resolvedBase)) throw new \StorageException('Vault directory unresolved.');
        $directory = $resolvedBase . DIRECTORY_SEPARATOR . 'secure-downloads';
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new \StorageException('Download vault unavailable.');
        $resolvedDir = realpath($directory);
        if ($resolvedDir === false || !is_dir($resolvedDir) || !str_starts_with($resolvedDir, $resolvedBase . DIRECTORY_SEPARATOR)) throw new \SecurityException('Download vault escaped path jail.');
        @chmod($resolvedDir, 0700);
        return $resolvedDir;
    }

    private static function detectMime(string $path): string {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        return is_string($mime) && preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/iD', $mime) === 1 ? substr($mime, 0, 100) : 'application/octet-stream';
    }

    private static function safeMime(string $mime): string { return preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/iD', $mime) === 1 ? $mime : 'application/octet-stream'; }

    private static function enforceRateLimit(string $id): void {
        $ip = class_exists('\VIS_Security') ? \VIS_Security::client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $key = 'vis_dl_' . hash('sha256', $id . '|' . $ip);
        $hits = (int)get_transient($key);
        if ($hits >= 30) throw new \ValidationException('Download rate boundary exceeded.');
        set_transient($key, $hits + 1, 60);
    }

    private static function authorize(string $action): void {
        if (self::method() !== 'POST' || !current_user_can('manage_options')) wp_die(esc_html__('Request rejected for security reasons.', 'vgt-sentinel'), '', ['response' => 403]);
        check_admin_referer($action);
    }

    private static function postedId(): string {
        $id = isset($_POST['public_id']) && is_string($_POST['public_id']) ? strtolower(wp_unslash($_POST['public_id'])) : '';
        if (preg_match(self::PUBLIC_ID_PATTERN, $id) !== 1) wp_die(esc_html__('Request rejected for security reasons.', 'vgt-sentinel'), '', ['response' => 400]);
        return $id;
    }

    private static function redirect(string $status): never {
        wp_safe_redirect(add_query_arg(['page' => 'vgt-suite', 'tab' => 'downloads', 'download-status' => sanitize_key($status)], admin_url('admin.php')));
        exit;
    }

    private static function emitFailure(string $type): void {
        $fabric = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
        if (class_exists($fabric)) $fabric::ingest(['sensor' => 'SECURE_DOWNLOADS', 'category' => 'INTEGRITY', 'event_type' => $type, 'severity' => 8, 'confidence' => 95, 'vector' => 'FILE_INTEGRITY']);
    }
}
