<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Edge {

    /**
     * Compiles generated Nginx edge configuration.
     *
     * @param array<string, mixed> $config
     * @param array<string> $canonicalHosts
     * @return string
     */
    public static function compileNginxRules(array $config, array $canonicalHosts): string {
        $lines = [];
        $lines[] = '# ======================================================================';
        $lines[] = '# GEDEFENSE ZEUS — GENERATED NGINX EDGE CONFIGURATION';
        $lines[] = '# DEPLOYED: ' . gmdate('Y-m-d H:i:s') . ' UTC';
        $lines[] = '# ======================================================================';
        $lines[] = '';

        // 1. Canonical Host Enforcement
        if (!empty($canonicalHosts) && ($config['host_lock_mode'] ?? 'DISABLED') === 'REJECT') {
            $hostList = implode(' ', array_map('escapeshellarg', $canonicalHosts));
            $lines[] = '# Canonical Host Enforcement';
            $lines[] = 'if ($host !~* ^(' . implode('|', array_map('preg_quote', $canonicalHosts)) . ')$) {';
            $lines[] = '    return 421;';
            $lines[] = '}';
            $lines[] = '';
        }

        // 2. Sensitive Files Protection
        $lines[] = '# Sensitive Files Protection';
        $lines[] = 'location ~* /\.(user\.ini|htaccess|htpasswd|git|svn|env) {';
        $lines[] = '    deny all;';
        $lines[] = '    return 404;';
        $lines[] = '}';
        $lines[] = 'location ~* ^/wp-config\.php {';
        $lines[] = '    deny all;';
        $lines[] = '    return 404;';
        $lines[] = '}';
        $lines[] = '';

        // 3. Block PHP in Uploads
        $lines[] = '# Block Direct PHP Execution in Uploads';
        $lines[] = 'location ~* ^/wp-content/uploads/.*\.(php|phtml|php5|php7|php8|phar)$ {';
        $lines[] = '    deny all;';
        $lines[] = '    return 403;';
        $lines[] = '}';
        $lines[] = '';

        // 4. XML-RPC Block
        if (!empty($config['fw_block_xmlrpc'])) {
            $lines[] = '# XML-RPC Denial';
            $lines[] = 'location = /xmlrpc.php {';
            $lines[] = '    deny all;';
            $lines[] = '    return 403;';
            $lines[] = '}';
            $lines[] = '';
        }

        // 5. Method Whitelist
        $lines[] = '# Allowed HTTP Methods';
        $lines[] = 'if ($request_method !~ ^(GET|POST|HEAD|OPTIONS|PUT|PATCH|DELETE)$) {';
        $lines[] = '    return 405;';
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Compiles generated Apache edge configuration.
     *
     * @param array<string, mixed> $config
     * @param array<string> $canonicalHosts
     * @return string
     */
    public static function compileApacheRules(array $config, array $canonicalHosts): string {
        $lines = [];
        $lines[] = '# ======================================================================';
        $lines[] = '# GEDEFENSE ZEUS — GENERATED APACHE .HTACCESS EDGE POLICY';
        $lines[] = '# DEPLOYED: ' . gmdate('Y-m-d H:i:s') . ' UTC';
        $lines[] = '# ======================================================================';
        $lines[] = '';
        $lines[] = '<IfModule mod_rewrite.c>';
        $lines[] = 'RewriteEngine On';
        $lines[] = '';

        // Sensitive Files Match
        $lines[] = '<FilesMatch "^(wp-config\.php|\.user\.ini|\.htaccess|\.env)$">';
        $lines[] = 'Require all denied';
        $lines[] = '</FilesMatch>';
        $lines[] = '';

        // Block PHP in uploads
        $lines[] = 'RewriteRule ^wp-content/uploads/.*\.(php|phtml|php5|php7|php8|phar)$ - [F,L,NC]';
        $lines[] = '';

        if (!empty($config['fw_block_xmlrpc'])) {
            $lines[] = 'RewriteRule ^xmlrpc\.php$ - [F,L,NC]';
            $lines[] = '';
        }

        $lines[] = '</IfModule>';
        return implode("\n", $lines) . "\n";
    }

    /**
     * Analyzes server-side mutual TLS (mTLS) environment state.
     *
     * @return array{status: string, client_s_dn: string, client_verify: string, details: string}
     */
    public static function getMtlsStatus(): array {
        $verify = (string)($_SERVER['SSL_CLIENT_VERIFY'] ?? $_SERVER['REDIRECT_SSL_CLIENT_VERIFY'] ?? '');
        $dn = (string)($_SERVER['SSL_CLIENT_S_DN'] ?? $_SERVER['REDIRECT_SSL_CLIENT_S_DN'] ?? '');

        if ($verify === 'SUCCESS') {
            return [
                'status' => 'VERIFIED',
                'client_s_dn' => $dn,
                'client_verify' => 'SUCCESS',
                'details' => 'Mutual TLS certificate authenticated by edge webserver.'
            ];
        } elseif ($verify !== '') {
            return [
                'status' => 'NOT_VERIFIED',
                'client_s_dn' => $dn,
                'client_verify' => $verify,
                'details' => sprintf('mTLS verification failed with code: %s', $verify)
            ];
        }

        return [
            'status' => 'CONFIGURED_OPTIONAL',
            'client_s_dn' => '',
            'client_verify' => 'NONE',
            'details' => 'No edge mTLS client certificate provided.'
        ];
    }
}
