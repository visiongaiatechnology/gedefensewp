<?php
/**
 * MODULE: HADES (The Unseen) - OMEGA V4.4 (STEALTH PROTOCOL)
 * Status: ACTIVE / ULTRA-DIAMANT VGT SUPREME
 * Logic: Global Output Buffer, Dynamic Path Mapping, Admin 404 Mimicry & REST API Cloaking.
 * Updates: Native REST-API (/wp-json/) Verschleierung integriert.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT Protocol: Direct access denied.');
}

final class VIS_Hades {

    private bool $enabled;
    private string $marker = 'VisionGaia Hades';
    
    // Admin Stealth Config
    private string $admin_param;
    private string $admin_secret;
    
    // Maps
    private array $map = [];
    private array $file_map = [];
    private string $rest_map;

    public function __construct(array $options) {
        $this->enabled = !empty($options['hades_enabled']);
        
        $this->admin_param  = !empty($options['hades_admin_param']) ? sanitize_key((string) $options['hades_admin_param']) : 'vgt_access';
        $this->admin_secret = !empty($options['hades_admin_secret']) ? (string) $options['hades_admin_secret'] : 'omega';
        
        // VGT SUPREME: Dynamic Mapping Generierung
        $this->map = [
            $this->sanitize_map_segment((string)($options['hades_map_themes'] ?? ''), 'content/ui')   => 'wp-content/themes',
            $this->sanitize_map_segment((string)($options['hades_map_plugins'] ?? ''), 'content/lib') => 'wp-content/plugins',
            $this->sanitize_map_segment((string)($options['hades_map_uploads'] ?? ''), 'storage')     => 'wp-content/uploads',
            $this->sanitize_map_segment((string)($options['hades_map_content'] ?? ''), 'content')     => 'wp-content',
            $this->sanitize_map_segment((string)($options['hades_map_includes'] ?? ''), 'core')       => 'wp-includes',
        ];

        $this->file_map = [
            $this->sanitize_map_segment((string)($options['hades_map_ajax'] ?? ''), 'vgt-api/nexus') => 'wp-admin/admin-ajax.php',
            $this->sanitize_map_segment((string)($options['hades_map_post'] ?? ''), 'vgt-api/post')  => 'wp-admin/admin-post.php',
        ];

        // VGT REST API CLOAKING
        $this->rest_map = $this->sanitize_map_segment((string)($options['hades_map_rest'] ?? ''), 'vgt-api');

        // AUTO-SYNC: .htaccess Rules & WP Rewrite Flush
        $routes_dirty = get_option('vis_hades_routes_dirty', '') === '1';
        if (is_admin() && ($routes_dirty || (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'))) {
            $this->update_server_rules();
            // Flush für die neue REST-API Struktur zwingend erforderlich
            flush_rewrite_rules();
            delete_option('vis_hades_routes_dirty');
            delete_transient('vgt_shadow_compiled_matrix_v12');
        }

        if ($this->enabled) {
            add_action('init', [$this, 'enforce_admin_stealth'], 1);

            if (!is_admin() && !defined('DOING_CRON')) {
                add_action('template_redirect', [$this, 'engage_stealth_protocol'], 0);
            }

            $this->init_url_filters();
        }
    }

    public function enforce_admin_stealth(): void {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (strpos($request_uri, 'admin-ajax.php') !== false || strpos($request_uri, 'admin-post.php') !== false) {
            return;
        }

        $is_login_attempt = strpos($request_uri, 'wp-login.php') !== false || strpos($request_uri, 'wp-admin') !== false;

        if ($is_login_attempt) {
            $zeus_config = get_option('vis_zeus_config', []);
            $zeus_slug   = $zeus_config['brute_rename_login'] ?? '';
            $has_zeus_slug = !empty($zeus_slug) && isset($_GET[$zeus_slug]);

            $has_param = (isset($_GET[$this->admin_param]) && is_string($_GET[$this->admin_param]) && hash_equals($this->admin_secret, (string)$_GET[$this->admin_param])) || $has_zeus_slug;
            $has_cookie = class_exists('VIS_Security')
                ? VIS_Security::validate_hades_gate($this->admin_secret)
                : (isset($_COOKIE['vgt_hades_gate']) && is_string($_COOKIE['vgt_hades_gate']) && hash_equals(hash_hmac('sha256', $this->admin_secret, wp_salt('auth')), (string)$_COOKIE['vgt_hades_gate']));

            if ($has_param) {
                $hash = hash_hmac('sha256', $this->admin_secret, wp_salt('auth'));
                setcookie('vgt_hades_gate', $hash, [
                    'expires'  => time() + 86400,
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                return; 
            }

            if (!$has_cookie) {
                if (class_exists('VIS_Event_Bus')) {
                    \VIS_Event_Bus::emit(
                        'HADES',
                        'ADMIN_SURFACE_PROBE',
                        'Stealth admin surface probe intercepted: Cloaked 404 response served.',
                        [
                            'category' => 'AUTHENTICATION',
                            'role' => 'DETECTION',
                            'route' => (string)($_SERVER['REQUEST_URI'] ?? '/wp-login.php'),
                            'attribution_confidence' => 100,
                            'severity' => 7,
                        ],
                        7
                    );
                }
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                nocache_headers();
                
                $template = get_query_template('404');
                if ($template && is_string($template)) {
                    include($template);
                } else {
                    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><center><h1>404 Not Found</h1></center><hr><center>nginx</center></body></html>';
                }
                exit; 
            }
        }
    }

    public function engage_stealth_protocol(): void {
        if (is_feed() || (defined('REST_REQUEST') && REST_REQUEST) || apply_filters('vis_hades_skip_buffer', false)) {
            return;
        }

        ob_start([$this, 'rewrite_final_buffer']);
    }

    public function rewrite_final_buffer(string|false $buffer): string|false {
        if (empty($buffer)) {
            return $buffer;
        }

        // 1. OMEGA DIRECTORY MAPPING
        foreach ($this->map as $fake => $real) {
            $buffer = str_replace('/' . $real, '/' . $fake, $buffer);
            
            $real_esc = str_replace('/', '\/', $real);
            $fake_esc = str_replace('/', '\/', $fake);
            $buffer = str_replace($real_esc, $fake_esc, $buffer);
        }
        
        // 2. OMEGA FILE ENDPOINT MAPPING
        foreach ($this->file_map as $fake_file => $real_file) {
            $buffer = str_replace('/' . $real_file, '/' . $fake_file, $buffer);
            
            $real_esc = str_replace('/', '\/', $real_file);
            $fake_esc = str_replace('/', '\/', $fake_file);
            $buffer = str_replace($real_esc, $fake_esc, $buffer);
        }

        // 3. OMEGA REST API HARDCODE CLEANUP
        // Vernichtet /wp-json/ Strings, die von sturen Themes hardcodiert wurden
        $buffer = str_replace('/wp-json/', '/' . $this->rest_map . '/', $buffer);
        $buffer = str_replace('\/wp-json\/', '\/' . $this->rest_map . '\/', $buffer);

        // VGT SUPREME FIX: Admin Bar Protection
        if (!is_user_logged_in()) {
            $deep_clean = [
                'wp-block-'    => 'vgt-block-',
                'wp-image-'    => 'vgt-image-',
                'wp-custom-'   => 'vgt-custom-',
                'wp-singular'  => 'vgt-singular',
                'wp-theme-'    => 'vgt-theme-',
                '--wp--'       => '--vgt--',
                '--wp-'        => '--vgt-',
                'id=\'wp-'     => 'id=\'vgt-',
                'id="wp-'      => 'id="vgt-',
                'class="wp-'   => 'class="vgt-',
                'class=\'wp-'  => 'class=\'vgt-',
                '/wp-*.php'    => '/sys-*.php', 
                '/wp-admin/*'  => '/vgt-admin/*'
            ];
            
            $buffer = str_replace(array_keys($deep_clean), array_values($deep_clean), $buffer);
        }
        
        return $buffer;
    }

    private function init_url_filters(): void {
        // Nativer Filter: Überschreibt /wp-json/ tief im WordPress Core
        add_filter('rest_url_prefix', function() {
            return $this->rest_map;
        });

        $real_to_fake = array_flip($this->map);
        
        add_filter('plugins_url', function(string $url) use ($real_to_fake): string { 
            $fake = $real_to_fake['wp-content/plugins'] ?? 'content/lib';
            return str_replace('wp-content/plugins', $fake, $url); 
        }, 10, 1);
        
        add_filter('theme_file_uri', function(string $url) use ($real_to_fake): string { 
            $fake = $real_to_fake['wp-content/themes'] ?? 'content/ui';
            return str_replace('wp-content/themes', $fake, $url); 
        }, 10, 1);
        
        add_filter('upload_dir', function(array $uploads) use ($real_to_fake): array {
            $fake = $real_to_fake['wp-content/uploads'] ?? 'storage';
            if (isset($uploads['baseurl']) && is_string($uploads['baseurl'])) {
                $uploads['baseurl'] = str_replace('wp-content/uploads', $fake, $uploads['baseurl']);
            }
            foreach(['url', 'subdir'] as $k) {
                if(isset($uploads[$k]) && is_string($uploads[$k])) {
                    $uploads[$k] = str_replace('wp-content/uploads', $fake, $uploads[$k]);
                }
            }
            return $uploads;
        });
        
        $replacer = [$this, 'replace_base_urls'];
        add_filter('style_loader_src', $replacer, 999);
        add_filter('script_loader_src', $replacer, 999);
        
        add_filter('includes_url', function(string $url) use ($real_to_fake): string {
            $fake = $real_to_fake['wp-includes'] ?? 'core';
            return str_replace('wp-includes', $fake, $url);
        }, 999);
        
        add_filter('content_url', function(string $url) use ($real_to_fake): string {
            $fake = $real_to_fake['wp-content'] ?? 'content';
            return str_replace('wp-content', $fake, $url);
        }, 999);

        // ENDPOINT STEALTH
        $real_to_fake_files = array_flip($this->file_map);
        
        add_filter('admin_url', function(mixed $url, mixed $path) use ($real_to_fake_files): mixed {
            if (!is_string($url) || !is_string($path)) {
                return $url;
            }

            $fake_ajax = $real_to_fake_files['wp-admin/admin-ajax.php'] ?? 'vgt-api/nexus';
            if (!is_admin() && strpos($path, 'admin-ajax.php') !== false) {
                return str_replace('wp-admin/admin-ajax.php', $fake_ajax, $url);
            }
            
            $fake_post = $real_to_fake_files['wp-admin/admin-post.php'] ?? 'vgt-api/post';
            if (!is_admin() && strpos($path, 'admin-post.php') !== false) {
                return str_replace('wp-admin/admin-post.php', $fake_post, $url);
            }
            
            return $url;
        }, 999, 2);
    }

    public function replace_base_urls(mixed $src): mixed {
        if (!is_string($src) || $src === '') {
            return $src;
        }

        foreach ($this->map as $fake => $real) {
            if (strpos($src, $real) !== false) {
                return str_replace($real, $fake, $src);
            }
        }
        return $src;
    }

    public function update_server_rules(): void {
        if (!$this->is_apache()) return; 

        $htaccess_path = ABSPATH . '.htaccess';
        if (!file_exists($htaccess_path) || !is_writable($htaccess_path)) return;

        $rules = $this->generate_apache_rules();
        $content = file_get_contents($htaccess_path);
        
        if ($content === false) return;
        
        $start = "# BEGIN " . $this->marker;
        $end   = "# END " . $this->marker;
        
        $pattern = "/".preg_quote($start, '/').".*?".preg_quote($end, '/')."/s";
        $clean_content = preg_replace($pattern, '', $content);
        
        if ($this->enabled) {
            $new_content = $start . "\n" . $rules . "\n" . $end . "\n" . trim((string) $clean_content);
        } else {
            $new_content = trim((string) $clean_content);
        }

        file_put_contents($htaccess_path, $new_content);
    }

    private function generate_apache_rules(): string {
        $rules = "<IfModule mod_rewrite.c>\n";
        $rules .= "RewriteEngine On\n";
        
        // 1. DIRECTORY RULES
        foreach ($this->map as $fake => $real) {
            $rules .= "RewriteRule ^{$fake}/(.*) {$real}/$1 [L,QSA]\n";
        }

        // 2. FILE ENDPOINT RULES
        foreach ($this->file_map as $fake_file => $real_file) {
            $rules .= "RewriteRule ^{$fake_file}$ {$real_file} [L,QSA]\n";
        }
        
        // 3. HARDENED SECURITY BLOCKS
        $upload_fake = array_search('wp-content/uploads', $this->map) ?: 'storage';
        $rules .= "RewriteRule ^{$upload_fake}/.*\.php$ - [F,L]\n";
        $rules .= "RewriteRule ^wp-content/uploads/.*\.php$ - [F,L]\n";
        $rules .= "</IfModule>\n";
        
        return $rules;
    }

    private function sanitize_map_segment(string $value, string $fallback): string {
        if (class_exists('VIS_Security')) {
            return VIS_Security::sanitize_hades_segment($value, $fallback);
        }

        $value = trim(wp_normalize_path($value), '/');
        return preg_match('/^[a-z0-9][a-z0-9_\/-]{0,127}$/i', $value) ? $value : $fallback;
    }

    private function is_apache(): bool {
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
        return (strpos($server_software, 'Apache') !== false || strpos($server_software, 'LiteSpeed') !== false);
    }

    public static function uploads_alias(?array $options = null): string {
        $options ??= get_option('vis_config', []);
        if (!is_array($options)) {
            $options = [];
        }

        $value = (string)($options['hades_map_uploads'] ?? '');
        if (class_exists('VIS_Security')) {
            return VIS_Security::sanitize_hades_segment($value, 'storage');
        }

        $value = trim(wp_normalize_path($value), '/');
        return preg_match('/^[a-z0-9][a-z0-9_\/-]{0,127}$/i', $value) ? $value : 'storage';
    }

    public static function rewrite_uploads_url(string $url, ?array $options = null): string {
        if ($url === '') {
            return $url;
        }

        $options ??= get_option('vis_config', []);
        if (!is_array($options) || empty($options['hades_enabled'])) {
            return $url;
        }

        $alias = self::uploads_alias($options);
        $url = str_replace('/wp-content/uploads', '/' . $alias, $url);
        return str_replace('\/wp-content\/uploads', '\/' . str_replace('/', '\/', $alias), $url);
    }

    public static function mark_routes_dirty(): void {
        update_option('vis_hades_routes_dirty', '1', false);
        delete_transient('vgt_shadow_compiled_matrix_v12');
    }

    public static function get_nginx_rules(array $options): string {
        $sanitize = static function(string $value, string $fallback): string {
            return class_exists('VIS_Security')
                ? VIS_Security::sanitize_hades_segment($value, $fallback)
                : (preg_match('/^[a-z0-9][a-z0-9_\/-]{0,127}$/i', trim($value, '/')) ? trim($value, '/') : $fallback);
        };

        $map = [
            $sanitize((string)($options['hades_map_themes'] ?? ''), 'content/ui')   => 'wp-content/themes',
            $sanitize((string)($options['hades_map_plugins'] ?? ''), 'content/lib') => 'wp-content/plugins',
            $sanitize((string)($options['hades_map_uploads'] ?? ''), 'storage')     => 'wp-content/uploads',
            $sanitize((string)($options['hades_map_content'] ?? ''), 'content')     => 'wp-content',
            $sanitize((string)($options['hades_map_includes'] ?? ''), 'core')       => 'wp-includes',
        ];
        
        $file_map = [
            $sanitize((string)($options['hades_map_ajax'] ?? ''), 'vgt-api/nexus') => 'wp-admin/admin-ajax.php',
            $sanitize((string)($options['hades_map_post'] ?? ''), 'vgt-api/post')  => 'wp-admin/admin-post.php',
        ];

        $rules = "# --- VISIONGAIATECHNOLOGY OMEGA SHIELD: NGINX HADES REWRITE ---\n";
        $rules .= "# Fügen Sie diesen Block in Ihren NGINX server {} Block ein.\n\n";

        foreach ($map as $fake => $real) {
            $rules .= "rewrite ^{$fake}/(.*)$ /{$real}/$1 last;\n";
        }

        foreach ($file_map as $fake_file => $real_file) {
            $rules .= "rewrite ^{$fake_file}$ /{$real_file} last;\n";
        }

        $rules .= "\n# HARDENED SECURITY BLOCKS\n";
        $upload_fake = array_search('wp-content/uploads', $map) ?: 'storage';
        $rules .= "location ~* ^/{$upload_fake}/.*\.php$ { deny all; access_log off; log_not_found off; }\n";
        $rules .= "location ~* ^/wp-content/uploads/.*\.php$ { deny all; access_log off; log_not_found off; }\n";

        return $rules;
    }
}
