<?php
declare(strict_types=1);

/**
 * MODULE: TITAN (The Strength) - OMEGA V6 (VGT_OS CAMOUFLAGE & HONEYPOTS)
 * Architecture: VisionGaiaTechnology
 * Status: ACTIVE / STABLE
 * Security Level: PLATINUM DIAMOND / ZERO TOLERANCE
 */

if (!defined('ABSPATH')) {
    exit('ACCESS DENIED: VGT PROTOCOL');
}

foreach ([
    'class-titan-surface-resolver.php',
    'class-titan-policy-compiler.php',
    'class-titan-server-rules.php',
    'class-titan-assurance.php',
    'class-titan-policy-store.php',
    'class-titan-learning.php',
    'class-titan-violation-collector.php',
    'class-titan-runtime.php',
    'class-titan-login-gate.php',
    'class-titan-sandbox.php',
    'class-titan-recovery.php',
] as $titanDependency) {
    require_once __DIR__ . '/src/' . $titanDependency;
}

final class VIS_Titan {

    private array $options;
    private ?VIS_Titan_Runtime $runtime = null;
    
    // SYSTEM MARKERS (TRINITY + 1)
    private const HTACCESS_MARKER  = 'VisionGaia Titan Firewall';
    private const UPLOADS_MARKER   = 'VisionGaia Titan Upload Guard';
    private const CONTENT_MARKER   = 'VisionGaia Content Sentinel';
    private const INCLUDES_MARKER  = 'VisionGaia Includes Sentinel';

    private const BLOCKED_FILENAMES = [
        'phpinfo.php', 'info.php', 'composer.lock', 'composer.json',
        'wp-config-sample.php', 'debug.log', 'error_log', '.env'
    ];

    // VGT SUPREME: Type-Safe Accessors
    private function opt_enabled(string $key): bool {
        return !empty($this->options[$key]);
    }

    private function get_opt_string(string $key, string $default = ''): string {
        return isset($this->options[$key]) && is_string($this->options[$key]) ? $this->options[$key] : $default;
    }

    public function __construct(array $options) {
        $this->options = $options;

        add_action('admin_post_vis_titan_policy_action', [self::class, 'handle_policy_action']);
        add_action('admin_post_vis_titan_download_nginx', [VIS_Titan_Server_Rules::class, 'handleDownload']);
        add_action('admin_post_vis_titan_generate_gate_link', [VIS_Titan_Login_Gate::class, 'handleGenerateLink']);
        VIS_Titan_Recovery::register();

        if (!$this->opt_enabled('titan_enabled')) {
            return;
        }

        // 0. WAF Pre-Init Execution (VGT SUPREME: CPU-Zero-Overhead)
        // Läuft sofort, ohne auf WordPress Hooks zu warten.
        $this->block_sensitive_files();

        // 1. Surface-aware compiled HTTP/browser policy hot path.
        $this->runtime = new VIS_Titan_Runtime($this->options);
        $this->runtime->boot();

        // 2. Disable File Editor
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
        if ($this->opt_enabled('titan_application_lockdown') && !defined('DISALLOW_FILE_MODS')) {
            define('DISALLOW_FILE_MODS', true);
        }
        
        // 3. Init Hooks & Active Defense
        add_action('init', [$this, 'enforce_protocols'], 20);
        add_action('init', [$this, 'source_cleanup'], 20);

        // 4. Feed Control
        if ($this->opt_enabled('titan_disable_feeds')) {
            $this->disable_feeds();
        }

        // 5. Advanced Camouflage & Heartbeat
        add_action('wp_head', [$this, 'inject_cms_meta'], 1);
        
        if ($this->opt_enabled('titan_remove_asset_versions')) {
            add_filter('style_loader_src', [$this, 'remove_ver_string'], 9999);
            add_filter('script_loader_src', [$this, 'remove_ver_string'], 9999);
        }
        
        if ($this->opt_enabled('titan_heartbeat_disable')) {
            add_action('init', [$this, 'disable_heartbeat'], 1);
        }

        // 6. Login Gatekeeper
        if ($this->opt_enabled('titan_login_gatekeeper')) {
            add_action('login_init', [new VIS_Titan_Login_Gate(), 'enforce'], 0);
        }

        // 7. Active Defense: XML-RPC Honeypot
        if ($this->opt_enabled('titan_xmlrpc_honeypot')) {
            $this->arm_xmlrpc_honeypot();
        }

        add_filter('wp_handle_upload', [VIS_Titan_Sandbox::class, 'registerUpload'], 100);
        add_action('template_redirect', [VIS_Titan_Sandbox::class, 'servePreview'], 0);
        add_action('admin_post_vis_titan_preview_link', [VIS_Titan_Sandbox::class, 'handlePreviewLink']);

        // 8. IO Operations (Admin Context Only)
        if (is_admin()) {
            add_action('admin_init', [$this, 'check_htaccess_update']);
        }
    }

    /**
     * VGT INFRASTRUCTURE: Proxy-Aware IP Resolution (Strict Boundary)
     */
    private function get_client_ip(): string {
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // VGT SUPREME: Strict Proxy Trust Boundary
        // Vertraut Forwarded-Headern nur, wenn die Anfrage von einem lokalen/privaten Proxy kommt
        // oder wenn der Administrator das explizit erzwungen hat.
        $is_private_proxy = !filter_var($remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        
        if ($is_private_proxy || defined('VGT_TRUST_FORWARDED_HEADERS')) {
            $headers = ['HTTP_TRUE_CLIENT_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];
            foreach ($headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ips = explode(',', $_SERVER[$header]);
                    // VGT SUPREME: Reverse Array. Vertraue immer der letzten IP (proxynah), um Spoofing zu verhindern.
                    $ips = array_reverse($ips);
                    foreach ($ips as $ip) {
                        $ip = trim($ip);
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            return $ip;
                        }
                    }
                }
            }
        }
        return $remote_addr;
    }

    public static function handle_policy_action(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die('Request rejected for security reasons.', '', ['response' => 403]);
        }
        check_admin_referer('vis_titan_policy_action');
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        try {
            if ($operation === 'activate_report_only') VIS_Titan_Policy_Store::activate('report_only');
            elseif ($operation === 'activate_enforce') VIS_Titan_Policy_Store::activate('enforce');
            elseif ($operation === 'rollback') VIS_Titan_Policy_Store::rollback('ADMIN_ROLLBACK');
            elseif ($operation === 'generate_candidate') {
                $config = get_option('vis_config', []);
                VIS_Titan_Learning::stageCandidate(is_array($config) ? $config : []);
            }
            elseif ($operation === 'validate') {
                $config = get_option('vis_config', []);
                VIS_Titan_Policy_Store::stage(is_array($config) ? $config : []);
            } else throw new ValidationException('Invalid TITAN policy operation.');
            wp_safe_redirect(add_query_arg(['page' => 'vgt-suite', 'tab' => 'titan', 'titan-action' => 'complete'], admin_url('admin.php')));
            exit;
        } catch (ValidationException $e) {
            wp_die(esc_html($e->getMessage()), '', ['response' => 422]);
        } catch (SecurityException $e) {
            error_log('[TITAN SECURITY] ' . $e->getMessage());
            wp_die('Request rejected for security reasons.', '', ['response' => 403]);
        } catch (StorageException $e) {
            error_log('[TITAN STORAGE] ' . $e->getMessage());
            wp_die('A server error occurred.', '', ['response' => 500]);
        } catch (Throwable $e) {
            error_log('[TITAN FATAL] ' . $e->getMessage());
            wp_die('Critical system fault.', '', ['response' => 500]);
        }
    }

    /**
     * VGT_OS SERVER SPOOFING & HEADER SECURITY
     */
    public function manage_headers(): void {
        if (headers_sent()) return;

        $security_headers = [
            'X-Frame-Options'        => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'geolocation=(), camera=(), microphone=(), payment=(), usb=(), fullscreen=(self)'
        ];

        // VGT_OS OVERRIDE
        if ($this->opt_enabled('titan_server_spoof')) {
            header('Server: VGT_OS/1.0.0');
        }

        foreach ($security_headers as $key => $val) {
            header(sprintf('%s: %s', $key, $val));
        }

        if (is_ssl() && $this->opt_enabled('titan_hsts_enabled')) {
            $hsts = 'max-age=' . max(300, min(63072000, (int)($this->options['titan_hsts_max_age'] ?? 31536000)));
            if ($this->opt_enabled('titan_hsts_include_subdomains')) $hsts .= '; includeSubDomains';
            if ($this->opt_enabled('titan_hsts_preload') && $this->opt_enabled('titan_hsts_include_subdomains')) $hsts .= '; preload';
            header('Strict-Transport-Security: ' . $hsts, true);
        }
        
        if (function_exists('header_remove')) {
            $remove = ['X-Powered-By', 'X-Pingback'];
            // Wenn Spoofing aktiv ist, blockieren wir auch den nativen Server-Header-Leak von PHP
            if ($this->opt_enabled('titan_server_spoof')) {
                $remove[] = 'Server';
                header('Server: VGT_OS/1.0.0'); // Force Override
            }
            foreach ($remove as $header) {
                header_remove($header);
            }
        }
    }

    public function filter_wp_headers(array $headers): array {
        unset($headers['X-Pingback'], $headers['X-Powered-By']);
        if ($this->opt_enabled('titan_server_spoof')) {
            $headers['Server'] = 'VGT_OS/1.0.0';
        } else {
            unset($headers['Server']);
        }
        return $headers;
    }

    /**
     * CMS EMULATION (META INJECTION)
     */
    public function inject_cms_meta(): void {
        $fake_tech = $this->get_opt_string('titan_camouflage_mode', 'none');
        
        $meta = match ($fake_tech) {
            'laravel' => '<meta name="generator" content="Laravel" />',
            'drupal' => '<meta name="generator" content="Drupal 9 (https://www.drupal.org)" />' . "\n" .
                        '<meta name="MobileOptimized" content="width" />' . "\n" .
                        '<meta name="HandheldFriendly" content="true" />',
            'joomla' => '<meta name="generator" content="Joomla! - Open Source Content Management" />',
            default  => '',
        };

        if ($meta) {
            echo "\n<!-- VGT_OS Camouflage -->\n" . $meta . "\n";
        }
    }

    /**
     * ANTI-RECONNAISSANCE: VERSION STRIPPING & CLEANUP
     */
    public function source_cleanup(): void {
        if ($this->opt_enabled('titan_hide_version')) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }
        if ($this->opt_enabled('titan_remove_discovery_links')) {
            foreach (['wlwmanifest_link', 'rsd_link', 'wp_shortlink_wp_head', 'rest_output_link_wp_head'] as $action) remove_action('wp_head', $action);
            remove_action('template_redirect', 'rest_output_link_header', 11);
        }

        if ($this->opt_enabled('titan_cleanup_emojis')) {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('wp_print_styles', 'print_emoji_styles');
            remove_filter('the_content_feed', 'wp_staticize_emoji');
            remove_filter('comment_text_rss', 'wp_staticize_emoji');
            add_filter('emoji_svg_url', '__return_false');
        }

        if ($this->opt_enabled('titan_cleanup_embeds')) {
            remove_action('rest_api_init', 'wp_oembed_register_route');
            remove_filter('oembed_dataparse', 'wp_filter_oembed_result');
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            add_filter('embed_oembed_discover', '__return_false');
        }
    }

    public function remove_ver_string($src) {
        // VGT SUPREME: Type-Safe Guard gegen false/null Werte aus Dritt-Plugins
        if (!is_string($src) || empty($src)) {
            return $src;
        }
        
        if (strpos($src, 'ver=') !== false) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }

    public function disable_heartbeat() {
        wp_deregister_script('heartbeat');
    }

    /**
     * PROTOCOL ENFORCEMENT & ENUMERATION BLOCK
     */
    public function enforce_protocols(): void {
        // User Enumeration Kill
        if ($this->opt_enabled('titan_anti_enum')) {
            if (!is_admin() && isset($_REQUEST['author']) && is_numeric($_REQUEST['author'])) {
                $this->terminate_request('User Enumeration Blocked');
            }
            add_filter('rest_endpoints', function($endpoints) {
                if (isset($endpoints['/wp/v2/users'])) unset($endpoints['/wp/v2/users']);
                if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
                return $endpoints;
            });
        }

        $xmlrpcMode = $this->xmlrpc_mode();
        if ($xmlrpcMode === 'disabled') {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('xmlrpc_methods', '__return_empty_array');
        } elseif ($xmlrpcMode === 'pingback_disabled') {
            add_filter('xmlrpc_methods', static function(array $methods): array {
                unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
                return $methods;
            });
        }

        $applicationPasswords = $this->get_opt_string('titan_application_passwords_mode', 'allow');
        if ($applicationPasswords === 'disable') add_filter('wp_is_application_passwords_available', '__return_false');
        elseif ($applicationPasswords === 'audit') add_action('application_password_did_authenticate', [$this, 'audit_application_password'], 10, 2);

        // REST API Restriction (VGT SUPREME: Traversal Defense & O(1) Scalability)
        if ($this->opt_enabled('titan_block_rest')) {
            add_filter('rest_authentication_errors', function($result) {
                if (!empty($result)) return $result;
                if (is_user_logged_in()) return $result;
                
                $route = (string)($GLOBALS['wp']->query_vars['rest_route'] ?? ($_SERVER['REQUEST_URI'] ?? ''));
                
                // VGT TRAVERSAL DEFENSE
                if (str_contains($route, '..')) {
                    return new WP_Error('rest_forbidden', 'VGT_OS: Path Traversal Detected.', ['status' => 403]);
                }

                $normalized_route = ltrim(wp_normalize_path($route), '/');
                
                // VGT SUPREME: Dynamic Namespace Registry via Filter
                $allowed_namespaces = apply_filters('vis_titan_allowed_rest_namespaces', [
                    'contact-form-7/v1', 'wc/v3', 'wc/store', 'wp/v2/types', 
                    'akismet/v1', 'yoast/v1', 'rankmath/v1', 'acf/v3', 'oembed/1.0'
                ]);
                
                foreach ($allowed_namespaces as $ns) {
                    if (preg_match('#^' . preg_quote($ns, '#') . '(/|$)#', $normalized_route)) {
                        return $result;
                    }
                }
                return new WP_Error('rest_forbidden', 'VGT_OS: Protocol Restricted.', ['status' => 401]);
            });
        }
    }

    public function audit_application_password(mixed $user, mixed $item): void {
        $fabric = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
        if (!class_exists($fabric)) return;
        $userId = is_object($user) && isset($user->ID) ? max(0, (int)$user->ID) : 0;
        $uuid = is_array($item) && isset($item['uuid']) && is_string($item['uuid']) ? $item['uuid'] : '';
        $fabric::ingest([
            'sensor' => 'TITAN', 'category' => 'AUTHENTICATION', 'event_type' => 'TITAN_APPLICATION_PASSWORD_AUTH',
            'role' => 'CONTEXT', 'severity' => 2, 'confidence' => 100, 'score' => 0,
            'actor_ip' => $this->get_client_ip(), 'user_id' => $userId, 'entity_type' => 'USER',
            'entity_id' => 'user:' . $userId, 'vector' => 'APPLICATION_PASSWORD', 'action_type' => 'AUDIT',
            'outcome' => 'AUTHENTICATED', 'metadata' => ['credential_id_hash' => $uuid !== '' ? hash('sha256', $uuid) : 'UNAVAILABLE'],
        ]);
    }

    /**
     * VGT THE LOGIN GATEKEEPER
     */
    /**
     * ACTIVE DEFENSE: XML-RPC HONEYPOT
     * STATUS: DIAMANT VGT SUPREME
     */
    private function arm_xmlrpc_honeypot(): void {
        if ($this->xmlrpc_mode() !== 'honeypot') return;
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '';
        
        if (strpos($uri, 'xmlrpc.php') !== false) {
            $fabric = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
            if (class_exists($fabric)) {
                $fabric::ingest([
                    'sensor' => 'TITAN', 'category' => 'DECEPTION',
                    'event_type' => 'TITAN_XMLRPC_HONEYPOT_TOUCH', 'role' => 'CONFIRMATION',
                    'severity' => 9, 'confidence' => 97, 'score' => 95,
                    'actor_ip' => $this->get_client_ip(), 'entity_type' => 'ROUTE',
                    'entity_id' => 'route:' . hash('sha256', '/xmlrpc.php'),
                    'vector' => 'XMLRPC_HONEYPOT', 'action_type' => 'BLOCK',
                    'outcome' => 'INTERCEPTED', 'metadata' => ['surface' => 'API'],
                ]);
            }
            
            // Sofortige Terminierung der TCP/HTTP Verbindung (Ressourcen-Erhalt)
            if (!headers_sent()) {
                header('HTTP/1.1 403 Forbidden');
                header('Content-Type: text/plain; charset=utf-8');
            }
            
            exit('Request rejected for security reasons.');
        }
    }

    private function xmlrpc_mode(): string {
        $mode = strtolower($this->get_opt_string('titan_xmlrpc_mode', ''));
        if (in_array($mode, ['disabled', 'pingback_disabled', 'auth_only', 'honeypot', 'custom'], true)) return $mode;
        if ($this->opt_enabled('titan_xmlrpc_honeypot')) return 'honeypot';
        return $this->opt_enabled('titan_block_xmlrpc') ? 'disabled' : 'auth_only';
    }
    /**
     * PHP LAYER PROTECTION (WAF)
     * VGT SUPREME: Execution Pre-Init (Zero CPU Overhead for WP)
     */
    public function block_sensitive_files(): void {
        if (!isset($_SERVER['REQUEST_URI'])) return;

        $decoded_uri = (string) $_SERVER['REQUEST_URI'];
        
        // VGT SUPREME: Deep Recursive Decode against Double-Encoding Bypasses
        $iterations = 0;
        while (preg_match('/%[0-9a-f]{2}/i', $decoded_uri) && $iterations < 3) {
            $decoded_uri = rawurldecode($decoded_uri);
            $iterations++;
        }
        
        // VGT SUPREME: Sanitize Invalid UTF-8 Overlong Encodings
        if (function_exists('mb_convert_encoding')) {
            $decoded_uri = mb_convert_encoding($decoded_uri, 'UTF-8', 'UTF-8');
        }
        
        $uri_path = parse_url(strtolower($decoded_uri), PHP_URL_PATH) ?: strtolower($decoded_uri);
        $basename = (string)(pathinfo($uri_path, PATHINFO_BASENAME) ?: '');
        if (in_array($basename, self::BLOCKED_FILENAMES, true)) {
             $this->terminate_request('File Blocked');
        }
        if (preg_match('~(?:^|/)(?:\.git|\.svn|vis-vault-omega)(?:/|$)|/actuator(?:/|$)~', $uri_path) === 1
            || preg_match('~/(?:wp-config\.php|database|db)[^/]*\.(?:bak|old|save|sql)(?:$|[/?])~', $uri_path) === 1) {
             $this->terminate_request('Protected Path');
        }
    }
    
    private function terminate_request(string $reason): void {
        if (!headers_sent()) http_response_code(403);
        exit('VGT_OS SHIELD: ' . $reason);
    }

    private function disable_feeds(): void {
        $kill_feed = function() {
            if (!is_admin()) {
                if (!headers_sent()) {
                    http_response_code(403);
                    header('Content-Type: text/plain; charset=utf-8');
                }
                echo 'VGT_OS: Feeds Disabled.';
                exit;
            }
        };
        $feeds = ['do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments'];
        foreach ($feeds as $feed) add_action($feed, $kill_feed, 1);
    }

    /**
     * HTACCESS & NGINX HANDLER (QUADRITY PATH)
     */
    public function check_htaccess_update(): void {
        // VGT SUPREME: Eliminiert redundante I/O-Schreibvorgänge.
        // Triggert NUR, wenn der spezifische VGT-Namespace gespeichert wird.
        $updated = filter_input(INPUT_GET, 'settings-updated', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if ($updated === 'true' && (strpos((string)$page, 'vis-') === 0 || strpos((string)$page, 'vgt-') === 0)) {
            $this->update_htaccess();            // Level 1: Root
            $this->update_upload_protection();   // Level 2: Uploads
            $this->update_content_protection();  // Level 3: Content
            $this->update_includes_protection(); // Level 4: WP-Includes Sentinel
            $this->update_nginx_protection();    // Level 5: NGINX Server Config
        }
    }

    public function update_htaccess(): void {
        if (!function_exists('get_home_path')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $htaccess_path = get_home_path() . '.htaccess';
        insert_with_markers($htaccess_path, self::HTACCESS_MARKER, explode("\n", $this->generate_root_htaccess_rules()));
    }

    public function update_upload_protection(): void {
        $upload_dir = wp_upload_dir();
        $htaccess_path = $upload_dir['basedir'] . '/.htaccess';
        $rules = [
            "# --- VISIONGAIATECHNOLOGY OMEGA SHIELD: UPLOAD VAULT [VER. 6.0] ---",
            "Options -Indexes",
            "<FilesMatch \"\.(?i:php[0-9]?|phtml|pl|py|jsp|asp|sh|cgi|exe|bat|msi)$\">",
            "    <IfModule mod_authz_core.c>",
            "        Require all denied",
            "    </IfModule>",
            "    <IfModule !mod_authz_core.c>",
            "        Order allow,deny",
            "        Deny from all",
            "    </IfModule>",
            "</FilesMatch>",
            "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>"
        ];
        insert_with_markers($htaccess_path, self::UPLOADS_MARKER, $rules);

        // CREATE index.php IF MISSING (Prevent directory listing on managed servers)
        $index_file = $upload_dir['basedir'] . '/index.php';
        if (is_dir($upload_dir['basedir']) && is_writable($upload_dir['basedir'])) {
            if (!file_exists($index_file)) {
                @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
            }
        }
    }

    public function update_content_protection(): void {
        $path = WP_CONTENT_DIR . '/.htaccess';
        $rules = [
            "# --- VISIONGAIATECHNOLOGY OMEGA SHIELD: CONTENT SENTINEL ---",
            "Options -Indexes",
            "<FilesMatch \"^(debug\.log|error_log|wp-config\.php|php\.ini|composer\.(json|lock)|\.env)$\">",
            "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>",
            "</FilesMatch>",
            "<FilesMatch \"^(?:wp-config\\.php|debug\\.log|error_log|composer\\.(?:json|lock)|\\.env)(?:\\.(?:bak|old|save))?$\">",
            "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>",
            "</FilesMatch>"
        ];
        insert_with_markers($path, self::CONTENT_MARKER, $rules);

        // CREATE index.php IF MISSING (Prevent directory listing on managed servers)
        $index_file = WP_CONTENT_DIR . '/index.php';
        if (is_dir(WP_CONTENT_DIR) && is_writable(WP_CONTENT_DIR)) {
            if (!file_exists($index_file)) {
                @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
            }
        }
    }

    // LEVEL 4: WP-INCLUDES SENTINEL
    public function update_includes_protection(): void {
        $path = ABSPATH . WPINC . '/.htaccess';
        if (!$this->opt_enabled('titan_includes_guard')) {
            if (file_exists($path)) insert_with_markers($path, self::INCLUDES_MARKER, []);
            return;
        }

        $rules = [
            "# --- VISIONGAIATECHNOLOGY OMEGA SHIELD: INCLUDES GUARD ---",
            "<IfModule mod_rewrite.c>",
            "RewriteEngine On",
            "RewriteBase /",
            "RewriteRule ^[^/]+\.php$ - [F,L]",
            "RewriteRule ^js/tinymce/langs/.+\.php - [F,L]",
            "RewriteRule ^theme-compat/ - [F,L]",
            "</IfModule>"
        ];
        insert_with_markers($path, self::INCLUDES_MARKER, $rules);
    }

    // LEVEL 5: NGINX SERVER CONFIGURATION (VGT SUPREME: Atomares I/O)
    public function update_nginx_protection(): void {
        VIS_Titan_Server_Rules::deploy($this->options);
    }

    private function generate_root_htaccess_rules(): string {
        $file_regex = str_replace('.', '\.', implode('|', self::BLOCKED_FILENAMES));

        $rules = [
            "# --- VGT TITAN: ROOT SHIELD ---",
            "<IfModule mod_headers.c>",
            "Header set X-Frame-Options \"DENY\"",
            "Header set X-Content-Type-Options \"nosniff\"",
            "Header always set Referrer-Policy \"strict-origin-when-cross-origin\"",
            "Header always set Permissions-Policy \"geolocation=(), camera=(), microphone=(), payment=(), usb=(), fullscreen=(self)\"",
            "</IfModule>",
            "Options -Indexes",
            "RedirectMatch 403 /\.(?!well-known).*$", 
            "<FilesMatch \"^(?:{$file_regex})(?:\\.(?:bak|old|save))?$\">",
            "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>",
            "</FilesMatch>"
        ];

        if ($this->opt_enabled('titan_hsts_enabled')) {
            $hsts = 'max-age=' . max(300, min(63072000, (int)($this->options['titan_hsts_max_age'] ?? 31536000)));
            if ($this->opt_enabled('titan_hsts_include_subdomains')) $hsts .= '; includeSubDomains';
            if ($this->opt_enabled('titan_hsts_preload') && $this->opt_enabled('titan_hsts_include_subdomains')) $hsts .= '; preload';
            array_splice($rules, 6, 0, ['Header always set Strict-Transport-Security "' . $hsts . '" "expr=%{HTTPS} == \'on\'"']);
        }

        if ($this->xmlrpc_mode() === 'disabled') {
            $rules[] = "<Files xmlrpc.php>\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n</Files>";
        }
        
        return implode("\n", $rules);
    }
}
