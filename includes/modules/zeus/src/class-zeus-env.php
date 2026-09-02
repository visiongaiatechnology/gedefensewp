<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if ( ! defined( 'ABSPATH' ) ) exit('VGT_ACCESS_DENIED');

class VIS_Zeus_Env {
    
    private string $vault_dir;
    private string $waf_file;
    private array $config;

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

        if (!empty($this->config['zeus_enabled'])) {
            $content = ($content !== '' ? $content . "\n\n" : '') . "; VGT ZEUS WAF\n" . $directive . "\n";
        }

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

    private function atomic_replace(string $destination, string $content, int $mode, ?string $temporary = null): void {
        $resolved_root = realpath(ABSPATH);
        $resolved_dir = realpath(dirname($destination));
        if ($resolved_root === false
            || $resolved_dir === false
            || !is_dir($resolved_dir)
            || ($resolved_dir !== $resolved_root
                && !str_starts_with($resolved_dir, $resolved_root . DIRECTORY_SEPARATOR))) {
            throw new SecurityException('Zeus configuration path escaped jail.');
        }

        $temporary ??= $destination . '.tmp.' . bin2hex(random_bytes(16));
        if (!str_starts_with($temporary, $resolved_dir . DIRECTORY_SEPARATOR)) {
            throw new SecurityException('Zeus staging path escaped jail.');
        }
        if (file_put_contents($temporary, $content, LOCK_EX) === false
            || !chmod($temporary, $mode)
            || !hash_equals(hash('sha256', $content), (string)hash_file('sha256', $temporary))) {
            if (is_file($temporary)) @unlink($temporary);
            throw new StorageException('Zeus configuration staging failed.');
        }

        if (!@rename($temporary, $destination)) {
            if (!$this->locked_replace_existing($destination, $content, $mode)) {
                if (is_file($temporary)) @unlink($temporary);
                throw new StorageException('Zeus atomic configuration write failed.');
            }
            @unlink($temporary);
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
        return !function_exists('fsync') || fsync($handle);
    }
}

if (!class_exists('\VIS_Zeus_Env')) {
    class_alias(VIS_Zeus_Env::class, 'VIS_Zeus_Env');
}
