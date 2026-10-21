<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

use \ValidationException;
use \SecurityException;
use \StorageException;
use \RuntimeException;
use \Throwable;

if ( ! defined( 'ABSPATH' ) ) exit('VGT_ACCESS_DENIED');

class VIS_Zeus_Env {
    
    private string $vault_dir;
    private string $waf_file;
    private array $config;

    private const PHP_HARDENING_MARKER = '; VGT PHP UPLOAD-PROGRESS HARDENING';
    private const PHP_HARDENING_DIRECTIVE = 'session.upload_progress.enabled = Off';

    public function __construct( string $vault_dir, string $waf_file, array $config ) {
        $this->vault_dir = $vault_dir;
        $this->waf_file  = $waf_file;
        $this->config    = $config;
    }

    public function ensure_master_key_isolated(): void {
        if ( ! is_dir( $this->vault_dir ) ) {
            @mkdir( $this->vault_dir, 0700, true );
            @file_put_contents( $this->vault_dir . '.htaccess', "Deny from all", LOCK_EX );
            @chmod( $this->vault_dir, 0700 );
            @chmod( $this->vault_dir . '.htaccess', 0600 );
        }
        
        $key_file = $this->vault_dir . 'vgt-master.php';
        if ( ! file_exists( $key_file ) ) {
            try {
                $key = bin2hex( random_bytes( 32 ) );
            } catch (\Exception $e) {
                throw new RuntimeException('VGT Zeus key generation failed.', 0, $e);
            }
            $content = "<?php\n// VGT OMEGA CRYPTOGRAPHIC VAULT (DO NOT MODIFY)\ndefine('VGT_MASTER_KEY', '{$key}');\n";
            @file_put_contents( $key_file, $content, LOCK_EX );
            @chmod( $key_file, 0600 );
        }
        
        if ( ! defined( 'VGT_MASTER_KEY' ) ) {
            require_once $key_file;
        }
    }

    /** @return array{user_ini:bool,htaccess:bool,wp_config:bool} */
    public function sync_all(): array {
        $results = [];
        foreach ([
            'user_ini' => 'sync_user_ini',
            'htaccess' => 'sync_htaccess',
            'wp_config' => 'sync_wp_config',
        ] as $target => $method) {
            try {
                $results[$target] = $this->{$method}();
            } catch (ValidationException $e) {
                error_log('[ZEUS VALIDATION] ' . $e->getMessage());
                $results[$target] = false;
            } catch (SecurityException $e) {
                error_log('[ZEUS SECURITY] ' . $e->getMessage());
                $results[$target] = false;
            } catch (StorageException $e) {
                error_log('[ZEUS STORAGE] ' . $e->getMessage());
                $results[$target] = false;
            } catch (Throwable $e) {
                error_log('[ZEUS FATAL] ' . $e->getMessage());
                $results[$target] = false;
            }
        }
        return $results;
    }

    private function sync_wp_config(): bool {
        $wp_config_path = ABSPATH . 'wp-config.php';
        if ( ! is_writable( $wp_config_path ) ) return false;

        $content = (string) file_get_contents( $wp_config_path );
        
        $content = preg_replace( '/\/\* VGT ZEUS PRE-BOOT WAF \*\/(.*?)\/\* END VGT ZEUS \*\//s', '', $content );
        
        $injection = "/* VGT ZEUS PRE-BOOT WAF */\nif (file_exists('" . $this->waf_file . "')) {\n    include_once '" . $this->waf_file . "';\n}\n/* END VGT ZEUS */\n";
        
        $content = preg_replace( '/^<\?php\s*/i', "<?php\n" . $injection, trim($content) );
        
        $mode = fileperms($wp_config_path);
        $this->atomic_replace($wp_config_path, $content, $mode === false ? 0600 : ($mode & 0777));
        return true;
    }

    private function sync_user_ini(): bool {
        $ini_file = ABSPATH . '.user.ini';
        if ((is_file($ini_file) && !is_writable($ini_file))
            || (!is_file($ini_file) && !is_writable(ABSPATH))) {
            throw new StorageException('Zeus user INI is managed by the hosting platform.');
        }
        $directive = 'auto_prepend_file = "' . $this->waf_file . '"';
        
        $content = '';
        if ( file_exists( $ini_file ) && is_readable( $ini_file ) ) {
            $content = (string) file_get_contents( $ini_file );
        }

        // Strip any existing VGT markers and prepends to prevent repeated accumulation
        $content = preg_replace( '/;\s*VGT ZEUS WAF[\r\n]*/s', '', $content );
        $content = preg_replace( '/^auto_prepend_file\s*=\s*".*zeus-waf\.php".*$/m', '', $content );
        $content = preg_replace( '/^auto_prepend_file\s*=\s*".*wp-security-firewall\.php".*$/m', '', $content );
        $content = trim( $content );
        // The hardening line is owned by this plugin, so an old value is replaced, never accumulated.
        $content = preg_replace( '/;\s*VGT PHP UPLOAD-PROGRESS HARDENING[\r\n]*/s', '', $content );
        $content = preg_replace( '/^\s*session\.upload_progress\.enabled\s*=.*$/m', '', $content );
        $content = trim( $content );

        if (!empty($this->config['zeus_enabled'])) {
            $content = ($content !== '' ? $content . "\n\n" : '') . "; VGT ZEUS WAF\n" . $directive . "\n";
        }

        // Upload-progress hardening is independent of the WAF switch: it closes the PHP handler
        // that runs before application code, so it stays in place even when ZEUS itself is off.
        $hardening = self::PHP_HARDENING_MARKER . "\n" . self::PHP_HARDENING_DIRECTIVE;
        $content = ($content !== '' ? $content . "\n\n" : '') . $hardening . "\n";

        if ($content === '' && is_file($ini_file)) {
            @unlink($ini_file);
            return true;
        }

        $temp_file = $ini_file . '.tmp.' . bin2hex(random_bytes(16));
        $mode = is_file($ini_file) ? fileperms($ini_file) : false;
        $this->atomic_replace($ini_file, $content, $mode === false ? 0600 : ($mode & 0777), $temp_file);
        return true;
    }

    private function sync_htaccess(): bool {
        $htaccess_file = ABSPATH . '.htaccess';
        if ( ! is_writable( $htaccess_file ) && ! is_writable( ABSPATH ) ) return false;

        $rules = [];
        $rules[] = '# VGT ZEUS PRE-BOOT WAF';
        $rules[] = '<IfModule mod_php.c>';
        $rules[] = 'php_value auto_prepend_file "' . $this->waf_file . '"';
        $rules[] = '</IfModule>';
        $rules[] = '<IfModule lsapi_module>';
        $rules[] = 'php_value auto_prepend_file "' . $this->waf_file . '"';
        $rules[] = '</IfModule>';

        if ( $this->config['fw_basic'] ) {
            $rules[] = '# VGT BASIC HARDENING';
            $rules[] = '<FilesMatch "^(wp-config\.php|php\.ini|\.user\.ini|\.htaccess)$">';
            $rules[] = 'Require all denied';
            $rules[] = '</FilesMatch>';
            $rules[] = 'Options -Indexes';
            $rules[] = 'ServerSignature Off';
        }

        if ( $this->config['fw_block_xmlrpc'] ) {
            $rules[] = '# VGT XML-RPC BLOCK';
            $rules[] = '<Files xmlrpc.php>';
            $rules[] = 'Require all denied';
            $rules[] = '</Files>';
        }

        if ( $this->config['fw_6g_blacklist'] ) {
            $rules[] = '# VGT 6G FIREWALL/BLACKLIST';
            $rules[] = '<IfModule mod_rewrite.c>';
            $rules[] = 'RewriteEngine On';
            $rules[] = 'RewriteCond %{QUERY_STRING} (eval\() [NC,OR]';
            $rules[] = 'RewriteCond %{QUERY_STRING} (127\.0\.0\.1) [NC,OR]';
            $rules[] = 'RewriteCond %{QUERY_STRING} ([a-zA-Z0-9_]=http:\/\/) [NC,OR]';
            $rules[] = 'RewriteCond %{QUERY_STRING} (base64_encode)(.*)(\() [NC,OR]';
            $rules[] = 'RewriteCond %{QUERY_STRING} (GLOBALS|REQUEST)(=|\[|%) [NC]';
            $rules[] = 'RewriteRule .* - [F]';
            $rules[] = '</IfModule>';
        }

        if ( $this->config['fs_prevent_hotlink'] ) {
            $domain = parse_url( get_site_url(), PHP_URL_HOST );
            $rules[] = '# VGT HOTLINK PROTECTION';
            $rules[] = '<IfModule mod_rewrite.c>';
            $rules[] = 'RewriteEngine on';
            $rules[] = 'RewriteCond %{HTTP_REFERER} !^$';
            $rules[] = 'RewriteCond %{HTTP_REFERER} !^http(s)?://(www\.)?' . preg_quote((string)$domain) . ' [NC]';
            $rules[] = 'RewriteRule \.(jpg|jpeg|png|gif|svg|webp)$ - [F]';
            $rules[] = '</IfModule>';
        }

        $lock_file = ABSPATH . '.htaccess.vgt.lock';
        $lock_fh = @fopen( $lock_file, 'w' );
        if ( $lock_fh ) {
            if ( flock( $lock_fh, LOCK_EX ) ) {
                $updated = function_exists('insert_with_markers') ? insert_with_markers($htaccess_file, 'VGT_ZEUS', $rules) : true;
                flock( $lock_fh, LOCK_UN );
            } else {
                $updated = false;
            }
            fclose( $lock_fh );
            @unlink( $lock_file );
            return (bool)$updated;
        }
        return false;
    }


    /**
     * Ensure the per-directory hardening directive exists without touching the rest of the file.
     * This runs from the request bootstrap; it reads one small file and writes only when the
     * directive is missing, so an already hardened site pays no write.
     */
    public function ensure_upload_progress_hardening(): bool {
        $ini_file = ABSPATH . '.user.ini';
        $content  = is_readable($ini_file) ? (string) file_get_contents($ini_file) : '';
        if (self::user_ini_has_upload_progress_hardening($content)) {
            return true;
        }
        if (!self::user_ini_writable($ini_file)) {
            // Hosting platforms may pin the file read-only and immutable. That is a reported
            // state, not a per-request error: the security health check shows it as blocked.
            return false;
        }
        $content = $content !== '' ? rtrim($content) . "\n\n" : '';
        $content .= self::PHP_HARDENING_MARKER . "\n" . self::PHP_HARDENING_DIRECTIVE . "\n";
        $temporary = $ini_file . '.tmp.' . bin2hex(random_bytes(16));
        $mode = is_file($ini_file) ? fileperms($ini_file) : false;
        $this->atomic_replace($ini_file, $content, $mode === false ? 0600 : ($mode & 0777), $temporary);
        return true;
    }

    /** @return array{state:string,configured:bool,effective:?bool,user_ini:bool,writable:bool,path:string,detail:string} */
    public function upload_progress_status(): array {
        return self::upload_progress_hardening_status(ABSPATH);
    }

    /**
     * Inspect the effective web-SAPI value of session.upload_progress.enabled together with the
     * per-directory directive. The effective value is the authority: a global Off protects the site
     * even when the host refuses .user.ini writes, and a written directive that the running SAPI has
     * not picked up yet is reported as pending, not as protected.
     *
     * @return array{state:string,configured:bool,effective:?bool,user_ini:bool,writable:bool,path:string,detail:string}
     */
    public static function upload_progress_hardening_status( string $root ): array {
        $ini_file = rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . '.user.ini';
        $content  = is_readable( $ini_file ) ? (string) file_get_contents( $ini_file ) : '';
        $configured = self::user_ini_has_upload_progress_hardening( $content );

        $user_ini_name = ini_get( 'user_ini.filename' );
        $user_ini      = $user_ini_name !== false && trim( (string) $user_ini_name ) !== '';

        $raw       = ini_get( 'session.upload_progress.enabled' );
        $effective = $raw === false ? null : (bool) filter_var( $raw, FILTER_VALIDATE_BOOLEAN );
        $writable  = self::user_ini_writable( $ini_file );

        $state = self::classify_upload_progress_state( $configured, $user_ini, $writable, $effective );
        $detail = match ( $state ) {
            'protected'   => 'Session upload progress is disabled in the effective PHP SAPI.',
            'pending'     => 'The hardening directive is written; the running PHP SAPI still reports the upload-progress handler until the next request or cache expiry.',
            'blocked'     => 'The .user.ini is managed by the hosting platform; GeDefense cannot write the hardening directive there.',
            'unsupported' => 'This PHP SAPI ignores .user.ini overrides; enforce session.upload_progress.enabled = Off in the pool configuration.',
            default       => 'The effective upload-progress value could not be read, so the hardening cannot be verified.',
        };

        return [
            'state'      => $state,
            'configured' => $configured,
            'effective'  => $effective,
            'user_ini'   => $user_ini,
            'writable'   => $writable,
            'path'       => $ini_file,
            'detail'     => $detail,
        ];
    }

    /**
     * Pure state classifier, exercised directly by the regression script.
     */
    public static function classify_upload_progress_state( bool $configured, bool $user_ini, bool $writable, ?bool $effective ): string {
        if ( $effective === false ) {
            return 'protected';
        }
        if ( ! $user_ini ) {
            return 'unsupported';
        }
        if ( ! $writable ) {
            return 'blocked';
        }
        if ( ! $configured ) {
            return 'pending';
        }
        return $effective === true ? 'pending' : 'unverified';
    }

    private static function user_ini_has_upload_progress_hardening( string $content ): bool {
        return $content !== ''
            && preg_match( '/^\s*session\.upload_progress\.enabled\s*=\s*Off\s*$/mi', $content ) === 1;
    }

    private static function user_ini_writable( string $ini_file ): bool {
        return ( is_file( $ini_file ) && is_writable( $ini_file ) )
            || ( ! is_file( $ini_file ) && is_writable( ABSPATH ) );
    }

    private function atomic_replace(string $destination, string $content, int $mode, ?string $temporary = null): void {
        $normalize = static function (string $p): string {
            $norm = function_exists('wp_normalize_path') ? wp_normalize_path($p) : str_replace('\\', '/', $p);
            if (preg_match('/^[a-z]:\//i', $norm)) {
                $norm = strtoupper($norm[0]) . substr($norm, 1);
            }
            return $norm;
        };

        $raw_root = realpath(ABSPATH);
        $raw_dir  = realpath(dirname($destination));
        if ($raw_root === false || $raw_dir === false || !is_dir($raw_dir)) {
            throw new SecurityException('Zeus configuration path escaped jail.');
        }

        $resolved_root = rtrim($normalize($raw_root), '/') . '/';
        $resolved_dir  = rtrim($normalize($raw_dir), '/') . '/';

        if ($resolved_dir !== $resolved_root && !str_starts_with($resolved_dir, $resolved_root)) {
            throw new SecurityException('Zeus configuration path escaped jail.');
        }

        $temporary ??= $destination . '.tmp.' . bin2hex(random_bytes(16));
        $norm_temporary = $normalize($temporary);
        if (!str_starts_with($norm_temporary, $resolved_dir)) {
            throw new SecurityException('Zeus staging path escaped jail.');
        }
        if (file_put_contents($temporary, $content, LOCK_EX) === false
            || !hash_equals(hash('sha256', $content), (string)hash_file('sha256', $temporary))) {
            if (is_file($temporary)) @unlink($temporary);
            throw new StorageException('Zeus configuration staging failed.');
        }
        @chmod($temporary, $mode);

        if (!@rename($temporary, $destination)) {
            if (is_file($destination)) @unlink($destination);
            if (!@rename($temporary, $destination)) {
                if (!$this->locked_replace_existing($destination, $content, $mode)) {
                    if (is_file($temporary)) @unlink($temporary);
                    throw new StorageException('Zeus atomic configuration write failed.');
                }
                @unlink($temporary);
            }
        }

        clearstatcache(true, $destination);
        if (!hash_equals(hash('sha256', $content), (string)hash_file('sha256', $destination))) {
            throw new StorageException('Zeus configuration verification failed.');
        }
    }

    private function locked_replace_existing(string $destination, string $content, int $mode): bool {
        if (!is_file($destination) || !is_writable($destination)) {
            return false;
        }

        $real_size = filesize($destination);
        if ($real_size === false || $real_size > 4194304) {
            return false;
        }

        $handle = @fopen($destination, 'c+b');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            return false;
        }

        $original = stream_get_contents($handle);
        if ($original === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }

        $success = $this->rewrite_locked_stream($handle, $content);
        if (!$success) {
            $this->rewrite_locked_stream($handle, $original);
        }
        if ($success) {
            @chmod($destination, $mode);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        clearstatcache(true, $destination);

        return $success
            && hash_equals(hash('sha256', $content), (string)hash_file('sha256', $destination));
    }

    /** @param resource $handle */
    private function rewrite_locked_stream($handle, string $content): bool {
        if (fseek($handle, 0) !== 0 || !ftruncate($handle, 0)) {
            return false;
        }

        $length = strlen($content);
        $written = 0;
        while ($written < $length) {
            $result = fwrite($handle, substr($content, $written));
            if ($result === false || $result === 0) {
                return false;
            }
            $written += $result;
        }

        if (!fflush($handle)) {
            return false;
        }
        return !function_exists('fsync') || @fsync($handle);
    }
}

if (!class_exists('\VIS_Zeus_Env')) {
    class_alias(VIS_Zeus_Env::class, 'VIS_Zeus_Env');
}
