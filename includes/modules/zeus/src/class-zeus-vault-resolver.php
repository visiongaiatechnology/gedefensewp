<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

/**
 * CANONICAL ZEUS STORAGE & PATH RESOLVER
 * Single authoritative resolver for all ZEUS vault, WAF, Blackbox, and cache paths.
 */
final class Zeus_Vault_Resolver {

    /**
     * Resolves the primary canonical Zeus vault directory.
     * Prioritizes VIS_VAULT_DIR if available, with full server hardening.
     */
    public static function getSecondaryVaultDir(): ?string {
        if (!defined('VIS_VAULT_DIR') || !is_string(VIS_VAULT_DIR) || VIS_VAULT_DIR === '') {
            return null;
        }
        $primary = self::getVaultDir();
        $fallback = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/vgt-vault/zeus/' : null;
        if ($fallback === null) return null;
        $fallbackNorm = function_exists('wp_normalize_path') ? wp_normalize_path($fallback) : str_replace('\\', '/', $fallback);
        if (!str_ends_with($fallbackNorm, '/')) $fallbackNorm .= '/';
        return ($fallbackNorm !== $primary) ? $fallbackNorm : null;
    }

    public static function getVaultDir(): string {
        if (defined('VIS_VAULT_DIR') && is_string(VIS_VAULT_DIR) && VIS_VAULT_DIR !== '') {
            $base = str_replace('\\', '/', VIS_VAULT_DIR);
            $dir = str_ends_with($base, '/zeus') || str_ends_with($base, '/zeus/')
                ? rtrim($base, '/') . '/'
                : rtrim($base, '/') . '/zeus/';
        } else {
            $dir = defined('WP_CONTENT_DIR')
                ? WP_CONTENT_DIR . '/vgt-vault/zeus/'
                : (defined('ABSPATH') ? ABSPATH . 'wp-content/vgt-vault/zeus/' : sys_get_temp_dir() . '/vgt-vault/zeus/');
        }
        
        $normalized = function_exists('wp_normalize_path') ? wp_normalize_path($dir) : str_replace('\\', '/', $dir);
        if (!str_ends_with($normalized, '/')) {
            $normalized .= '/';
        }

        self::ensureVaultHardening($normalized);
        return $normalized;
    }

    /**
     * Hardens the vault directory across Apache, Nginx, IIS, and Lighttpd.
     */
    public static function ensureDirectories(): void {
        self::ensureVaultHardening(self::getVaultDir());
        self::getCacheDir();
    }

    public static function ensureVaultHardening(string $vaultDir): void {
        if (!is_dir($vaultDir)) {
            @mkdir($vaultDir, 0700, true);
        }
        @chmod($vaultDir, 0700);

        // 1. .htaccess (Apache / LiteSpeed)
        $htaccess = $vaultDir . '.htaccess';
        if (!file_exists($htaccess)) {
            $htContent = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
            @file_put_contents($htaccess, $htContent, LOCK_EX);
            @chmod($htaccess, 0600);
        }

        // 2. web.config (IIS)
        $webConfig = $vaultDir . 'web.config';
        if (!file_exists($webConfig)) {
            $wcContent = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <authorization>\n      <deny users=\"*\" />\n    </authorization>\n  </system.webServer>\n</configuration>\n";
            @file_put_contents($webConfig, $wcContent, LOCK_EX);
            @chmod($webConfig, 0600);
        }

        // 3. index.php (Direct PHP execution prevention / Nginx fallback)
        $indexPhp = $vaultDir . 'index.php';
        if (!file_exists($indexPhp)) {
            $idxContent = "<?php\nhttp_response_code(403);\nexit('VGT_ACCESS_DENIED');\n";
            @file_put_contents($indexPhp, $idxContent, LOCK_EX);
            @chmod($indexPhp, 0600);
        }
    }

    /**
     * Resolves the active production WAF file path.
     */
    public static function getWafFile(): string {
        return self::getVaultDir() . 'zeus-waf.php';
    }

    /**
     * Resolves the Last Known Good (LKG) WAF backup file path.
     */
    public static function getLkgFile(): string {
        return self::getVaultDir() . 'zeus-waf.lkg.php';
    }

    /**
     * Resolves the Blackbox flight recorder spool file path.
     */
    public static function getSpoolFile(): string {
        return self::getVaultDir() . 'blackbox.spool';
    }

    /**
     * Resolves the pre-boot budget & token cache directory.
     */
    public static function getCacheDir(): string {
        $cache = self::getVaultDir() . 'cache/';
        if (!is_dir($cache)) {
            @mkdir($cache, 0700, true);
        }
        @chmod($cache, 0700);
        return $cache;
    }
}
