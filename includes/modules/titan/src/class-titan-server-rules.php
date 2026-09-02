<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Server_Rules {
    private const LEGACY_MARKER = 'VISIONGAIATECHNOLOGY OMEGA SHIELD: NGINX VAULT';

    /** @param array<string, mixed> $config */
    public static function deploy(array $config): array {
        $rules = self::nginxRules($config);
        $validation = self::validateNginx($rules);
        self::removeLegacyPublicConfig();
        if ($validation['state'] === 'FAILED') throw new SecurityException('TITAN server configuration validation failed.');
        $directory = self::privateDirectory();
        if ($directory === null) {
            $status = ['state' => 'GENERATED_EXPORT_ONLY', 'storage' => 'MEMORY_ONLY', 'validation' => $validation, 'updated_at' => gmdate('c')];
            update_option('vis_titan_server_rule_status', $status, false);
            return $status;
        }
        $destination = $directory . DIRECTORY_SEPARATOR . 'vgt-titan-shield.conf';
        if (!str_starts_with($destination, $directory . DIRECTORY_SEPARATOR)) throw new SecurityException('TITAN server configuration path escaped jail.');
        self::atomicWrite($destination, $rules);
        $status = ['state' => 'GENERATED', 'storage' => 'PRIVATE_VAULT', 'file_hash' => hash('sha256', $rules), 'validation' => $validation, 'updated_at' => gmdate('c')];
        update_option('vis_titan_server_rule_status', $status, false);
        return $status;
    }

    /** @param array<string, mixed> $config */
    public static function nginxRules(array $config): string {
        $rules = [
            '# VGT TITAN NGINX POLICY v2 - generated configuration',
            'autoindex off;',
            'location ~ /\\.(?!well-known/) { deny all; access_log off; log_not_found off; }',
            'location ~* /(?:wp-config\\.php(?:\\.(?:bak|old|save))?|\\.env|composer\\.(?:json|lock)|debug\\.log|error_log)$ { deny all; access_log off; log_not_found off; }',
            'location ~* ^/wp-content/uploads/.*\\.(?:php[0-9]?|phtml|phar|pl|py|jsp|asp|sh|cgi)$ { deny all; access_log off; log_not_found off; }',
        ];
        $xmlrpc = (string)($config['titan_xmlrpc_mode'] ?? (!empty($config['titan_block_xmlrpc']) ? 'disabled' : 'auth_only'));
        if ($xmlrpc === 'disabled') $rules[] = 'location = /xmlrpc.php { deny all; access_log off; log_not_found off; }';
        $activeContent = (string)($config['titan_active_content_direct_access'] ?? 'attachment');
        if ($activeContent === 'block') $rules[] = 'location ~* ^/wp-content/uploads/.*\.(?:html?|svg|xml)$ { deny all; access_log off; log_not_found off; }';
        elseif ($activeContent === 'attachment') $rules[] = 'location ~* ^/wp-content/uploads/.*\.(?:html?|svg|xml)$ { add_header Content-Disposition "attachment" always; add_header X-Content-Type-Options "nosniff" always; }';
        if (!empty($config['titan_includes_guard'])) $rules[] = 'location ~* ^/wp-includes/.*\\.(?:php|phps|php[0-9]?|phtml)$ { deny all; access_log off; log_not_found off; }';
        return implode("\n", $rules) . "\n";
    }

    /** @return array{state:string,checks:list<string>,failures:list<string>} */
    public static function validationSummary(): array {
        $config = get_option('vis_config', []);
        return self::validateNginx(self::nginxRules(is_array($config) ? $config : []));
    }

    public static function handleDownload(): void {
        if (!is_user_logged_in() || !current_user_can('manage_options')) wp_die('Request rejected for security reasons.', '', ['response' => 403]);
        check_admin_referer('vis_titan_download_nginx');
        $config = get_option('vis_config', []);
        $rules = self::nginxRules(is_array($config) ? $config : []);
        $validation = self::validateNginx($rules);
        if ($validation['state'] !== 'PASS') wp_die('Server rule validation failed.', '', ['response' => 500]);
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="vgt-titan-shield.conf"');
            header('Content-Length: ' . strlen($rules));
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
        }
        echo $rules;
        exit;
    }

    /** @return array{state:string,checks:list<string>,failures:list<string>} */
    private static function validateNginx(string $rules): array {
        $checks = [];
        $failures = [];
        if (strlen($rules) > 65536 || preg_match('/[\0\r]/', $rules) === 1) $failures[] = 'SIZE_OR_CONTROL_CHARACTER'; else $checks[] = 'BOUNDARY';
        if (str_contains(strtolower($rules), '/wp-content/plugins/') && str_contains($rules, 'deny all')) $failures[] = 'PLUGIN_EXECUTION_DENIAL'; else $checks[] = 'PLUGIN_EXECUTION_PRESERVED';
        if (preg_match('~[A-Za-z]:\\\\|/(?:home|var|srv|users)/[^\s;]+~i', $rules) === 1) $failures[] = 'ABSOLUTE_PATH_DISCLOSURE'; else $checks[] = 'NO_ABSOLUTE_PATH';
        if (substr_count($rules, 'location = /xmlrpc.php') > 1) $failures[] = 'DUPLICATE_XMLRPC_BLOCK'; else $checks[] = 'NO_DUPLICATE_BLOCKS';
        if (substr_count($rules, '{') !== substr_count($rules, '}')) $failures[] = 'UNBALANCED_BRACES'; else $checks[] = 'SYNTAX_BALANCE';
        return ['state' => $failures === [] ? 'PASS' : 'FAILED', 'checks' => $checks, 'failures' => $failures];
    }

    private static function privateDirectory(): ?string {
        if (!defined('VIS_VAULT_DIR')) return null;
        $candidate = rtrim((string)VIS_VAULT_DIR, '/\\') . DIRECTORY_SEPARATOR . 'titan';
        if (!is_dir($candidate) && !mkdir($candidate, 0700, true) && !is_dir($candidate)) return null;
        $resolved = realpath($candidate);
        $root = realpath(ABSPATH);
        $content = defined('WP_CONTENT_DIR') ? realpath(WP_CONTENT_DIR) : false;
        if ($resolved === false || $root === false) return null;
        $normalized = wp_normalize_path($resolved);
        $rootNormalized = rtrim(wp_normalize_path($root), '/') . '/';
        $contentNormalized = $content !== false ? rtrim(wp_normalize_path($content), '/') . '/' : '';
        if (str_starts_with($normalized . '/', $rootNormalized) || ($contentNormalized !== '' && str_starts_with($normalized . '/', $contentNormalized))) return null;
        chmod($resolved, 0700);
        return $resolved;
    }

    private static function atomicWrite(string $destination, string $content): void {
        $temporary = $destination . '.' . bin2hex(random_bytes(16)) . '.tmp';
        if (file_put_contents($temporary, $content, LOCK_EX) === false) throw new StorageException('TITAN server configuration write failed.');
        chmod($temporary, 0600);
        if (!rename($temporary, $destination)) {
            unlink($temporary);
            throw new StorageException('TITAN server configuration commit failed.');
        }
        chmod($destination, 0600);
    }

    private static function removeLegacyPublicConfig(): void {
        $uploads = wp_upload_dir();
        $base = isset($uploads['basedir']) && is_string($uploads['basedir']) ? $uploads['basedir'] : '';
        if ($base === '') return;
        $candidate = $base . DIRECTORY_SEPARATOR . 'vgt-titan-shield.conf';
        if (!is_file($candidate)) return;
        $size = filesize($candidate);
        if ($size === false || $size > 131072) return;
        $content = file_get_contents($candidate);
        if (is_string($content) && str_contains($content, self::LEGACY_MARKER)) unlink($candidate);
    }
}
