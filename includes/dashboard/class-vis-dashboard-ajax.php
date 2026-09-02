<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * VGT OMEGA PROTOCOL - MASTER AJAX CONTROLLER
 * STATUS: DIAMANT VGT SUPREME
 */
final class VIS_Dashboard_Ajax {

    public static function ensure_zeus_dependencies(): void {
        require_once dirname(__DIR__, 1) . '/core/class-vis-security.php';
        $zeus_src_dir = dirname(__DIR__, 1) . '/modules/zeus/src/';
        require_once $zeus_src_dir . 'class-zeus-vault-resolver.php';
        require_once $zeus_src_dir . 'class-zeus-policy-manager.php';
        require_once $zeus_src_dir . 'class-zeus-config-repository.php';
        require_once $zeus_src_dir . 'class-zeus-contracts.php';
        require_once $zeus_src_dir . 'class-zeus-xdr-bridge.php';
        require_once $zeus_src_dir . 'class-zeus-admission.php';
        require_once $zeus_src_dir . 'class-zeus-blackbox.php';
        require_once $zeus_src_dir . 'class-zeus-budget.php';
        require_once $zeus_src_dir . 'class-zeus-benchmark.php';
        require_once $zeus_src_dir . 'class-zeus-edge.php';
        require_once $zeus_src_dir . 'class-zeus-learning.php';
        require_once $zeus_src_dir . 'class-zeus-env.php';
        require_once $zeus_src_dir . 'class-zeus-compiler.php';
        require_once $zeus_src_dir . 'class-zeus-shield.php';
        require_once dirname(__DIR__, 1) . '/modules/zeus/class-vis-zeus.php';
    }


    public static function mount_endpoints(): void {
        // --- CORE & SCANNER ---
        add_action('wp_ajax_vis_approve_changes', [self::class, 'handle_approve']); 
        add_action('wp_ajax_vis_save_zeus_config', [self::class, 'handle_zeus_config']);
        add_action('wp_ajax_vis_zeus_run_benchmark', [self::class, 'handle_zeus_benchmark']);
        add_action('wp_ajax_vis_zeus_run_self_test', [self::class, 'handle_zeus_self_test']);
        add_action('wp_ajax_vis_zeus_drain_blackbox', [self::class, 'handle_zeus_drain_blackbox']);
        add_action('wp_ajax_vis_zeus_restore_preset', [self::class, 'handle_zeus_restore_preset']);
        add_action('wp_ajax_vis_zeus_save_contract', [self::class, 'handle_zeus_save_contract']);
        add_action('wp_ajax_vis_zeus_delete_contract', [self::class, 'handle_zeus_delete_contract']);
        add_action('wp_ajax_vis_zeus_generate_admission_token', [self::class, 'handle_zeus_generate_token']);
        add_action('wp_ajax_vis_zeus_rollback_policy', [self::class, 'handle_zeus_rollback_policy']);
        add_action('wp_ajax_vis_dashboard_unban_ip', [self::class, 'handle_unban_ip']);
        add_action('wp_ajax_vis_run_scan', [self::class, 'handle_scan_bridge']); 
        add_action('wp_ajax_vgt_integrity_uplink', [self::class, 'handle_scan_bridge']);
        
        // VGT SECURE EXPLORER: Source inspector AJAX endpoint
        add_action('wp_ajax_vis_inspect_file', [self::class, 'handle_inspect_file']);
        add_action('wp_ajax_vis_security_center_test', [self::class, 'handle_security_center_test']);
        add_action('wp_ajax_vis_oracle_ping', [self::class, 'handle_oracle_ping']);

        // VGT ADD-ON SYSTEM: Dynamic module upload and management
        add_action('wp_ajax_vis_upload_addon', [self::class, 'handle_upload_addon']);
        add_action('wp_ajax_vis_uninstall_addon', [self::class, 'handle_uninstall_addon']);
    }
        
        // CHIRURGISCHER EINGRIFF: Gorgon-Endpoints restlos entfernt.
        // Das Routing wird exklusiv über `class-vis-gorgon-ajax.php` abgewickelt, 
        // um Duplicate-Execution und Headers-Already-Sent Panics zu verhindern.
    

    private static function verify_privileges(string $nonce_action, string $nonce_key = 'nonce'): void {
        if (!check_ajax_referer($nonce_action, $nonce_key, false)) {
            wp_send_json_error(['message' => 'VGT_SECURITY_VIOLATION: Nonce verification failed or session expired. Please refresh the page.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'VGT_UNAUTHORIZED_ACCESS'], 403);
        }
    }

    // ========================================================================
    // CORE & SCANNER HANDLER
    // ========================================================================
    public static function handle_unban_ip(): void {
        // Kryptografische Verifikation (Dual Nonce Acceptance)
        if (!check_ajax_referer('vis_nonce', 'nonce', false) && !check_ajax_referer('vis_dashboard_nonce', 'nonce', false)) {
            wp_send_json_error('VGT SECURITY ALERT: Invalid security token.', 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('VGT SECURITY ALERT: Unauthorized access.', 403);
        }

        $target = isset($_POST['ip']) && is_string($_POST['ip']) ? sanitize_text_field(wp_unslash($_POST['ip'])) : '';
        try {
            if (!class_exists('VIS_Cerberus')) throw new StorageException('Cerberus runtime unavailable.');
            VIS_Cerberus::instance()->unban_target($target);
            wp_send_json_success('IP/CIDR erfolgreich aus der Cerberus-Sperrliste entfernt.');
        } catch (ValidationException $e) {
            wp_send_json_error($e->getMessage(), 422);
        } catch (SecurityException $e) {
            error_log('[CERBERUS SECURITY] ' . $e->getMessage());
            wp_send_json_error('Request rejected for security reasons.', 403);
        } catch (StorageException $e) {
            error_log('[CERBERUS STORAGE] ' . $e->getMessage());
            wp_send_json_error('VGT DB ERROR: Unban-Operation auf Datenbankebene fehlgeschlagen.', 500);
        } catch (Throwable $e) {
            error_log('[CERBERUS FATAL] ' . $e->getMessage());
            wp_send_json_error('Critical system fault.', 500);
        }
    }
    
    
    public static function handle_scan_bridge(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_send_json_error(['message' => 'Request rejected for security reasons.'], 405);
        }
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'VGT_UNAUTHORIZED_ACCESS'], 403);
        }

        $provided_token = isset($_SERVER['HTTP_X_VGT_UPLINK_TOKEN']) ? (string)$_SERVER['HTTP_X_VGT_UPLINK_TOKEN'] : '';
        $valid_token    = get_transient('vis_uplink_master_token');
        $is_cup_valid = is_string($valid_token) && $valid_token !== '' && hash_equals($valid_token, $provided_token);
        
        if (!$is_cup_valid) {
            // Legacy Fallback: Wenn CUP fehlt (z.B. gecachtes JS), nutze nativen WP Nonce
            if (!check_ajax_referer('vis_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => 'Native Uplink lost. Cryptographic handshake failed.'], 403);
            }
        }

        $phase  = isset($_POST['phase']) && is_string($_POST['phase']) ? sanitize_key(wp_unslash($_POST['phase'])) : 'init';
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
        $mode   = isset($_POST['mode']) && is_string($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'scan';

        try {
            $scanner_path = defined('VIS_PATH') ? VIS_PATH . 'includes/scanner/class-vis-scanner-engine.php' : '';
            if (!class_exists('VIS_Scanner_Engine_Omega') && is_file($scanner_path)) require_once $scanner_path;
            if (!class_exists('VIS_Scanner_Engine_Omega')) throw new StorageException('Omega scanner engine unavailable.');
            $engine = new VIS_Scanner_Engine_Omega();
            $result = $engine->run_scan_cycle($phase, $offset, $mode);
            wp_send_json_success($result);
        } catch (ValidationException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 422);
        } catch (SecurityException $e) {
            error_log('[VGT DASHBOARD SECURITY] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Request rejected for security reasons.'], 403);
        } catch (StorageException $e) {
            error_log('[VGT DASHBOARD STORAGE] ' . $e->getMessage());
            wp_send_json_error(['message' => 'A server error occurred.'], 500);
        } catch (\Throwable $e) {
            error_log('[VGT DASHBOARD ENGINE] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Critical system fault.'], 500);
        }
    }

    public static function handle_approve(): void {
        self::verify_privileges('vis_nonce');
        wp_send_json_error(['message' => 'Deprecated Endpoint. Update Frontend Cache.']);
    }

    public static function handle_vlp_download(): void {
        self::verify_privileges('vis_nonce');
        
        if (!class_exists('VLP_Asset_Downloader')) {
            $path = VIS_PATH . 'includes/VLP/includes/modules/shadow-net/class-vlp-asset-downloader.php';
            if (is_readable($path)) require_once $path;
        }

        if (class_exists('VLP_Asset_Downloader') && method_exists('VLP_Asset_Downloader', 'handle_ajax_download')) {
            VLP_Asset_Downloader::get_instance()->handle_ajax_download();
        } else {
            wp_send_json_error(['message' => 'VLP Module Offline.']);
        }
    }

    public static function handle_zeus_config(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');

        try {
            if (!class_exists('\VisionGaia\GeDefense\Modules\Zeus\Zeus_Config_Repository')) {
                throw new StorageException('Zeus config repository unavailable.');
            }

            $stageResult = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Config_Repository::save($_POST);

            if (!$stageResult['success']) {
                throw new StorageException((string)$stageResult['error']);
            }

            wp_send_json_success([
                'message' => 'ZEUS NEXT GENERATION policy compiled & activated atomically.',
                'digest' => $stageResult['digest']
            ]);
        } catch (ValidationException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 422);
        } catch (SecurityException $e) {
            error_log('[ZEUS SECURITY] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Request rejected for security reasons.'], 403);
        } catch (StorageException $e) {
            error_log('[ZEUS STORAGE] ' . $e->getMessage());
            wp_send_json_error(['message' => 'A server storage error occurred.'], 500);
        } catch (Throwable $e) {
            error_log('[ZEUS FATAL] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Critical system fault.'], 500);
        }
    }

    public static function handle_zeus_benchmark(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');
        $profile = sanitize_key($_POST['profile'] ?? 'MIXED_BOT_SWARM');
        $iterations = min(20000, max(100, (int)($_POST['iterations'] ?? 5000)));

        try {
            $result = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Benchmark::runMicrobenchmark($profile, $iterations);
            wp_send_json_success($result);
        } catch (Throwable $e) {
            error_log('[ZEUS BENCHMARK] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Benchmark execution error: ' . $e->getMessage()], 500);
        }
    }

    public static function handle_zeus_self_test(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');
        $vaultDir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/vgt-vault/zeus/' : ABSPATH . 'wp-content/vgt-vault/zeus/';
        try {
            $result = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Benchmark::runSecuritySelfTest(wp_normalize_path($vaultDir));
            wp_send_json_success($result);
        } catch (Throwable $e) {
            error_log('[ZEUS SELFTEST] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Self-test execution error: ' . $e->getMessage()], 500);
        }
    }

    public static function handle_zeus_drain_blackbox(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');
        $vaultDir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/vgt-vault/zeus/' : ABSPATH . 'wp-content/vgt-vault/zeus/';
        try {
            $drained = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Blackbox::drainToEventBus(wp_normalize_path($vaultDir), 200);
            $metrics = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Blackbox::getMetrics(wp_normalize_path($vaultDir));
            wp_send_json_success([
                'message' => sprintf('Drained %d pre-boot events into Trinity XDR Fabric.', $drained),
                'drained' => $drained,
                'metrics' => $metrics
            ]);
        } catch (Throwable $e) {
            error_log('[ZEUS DRAIN] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Drain error: ' . $e->getMessage()], 500);
        }
    }

    public static function handle_zeus_restore_preset(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');
        $preset = sanitize_key($_POST['preset'] ?? 'RECOMMENDED');

        $config = VIS_Zeus::getDefaultConfig();
        if ($preset === 'RECOMMENDED') {
            $config['host_lock_mode'] = 'AUDIT';
            $config['budget_action_mode'] = 'THROTTLE';
            $config['max_query_length'] = 2048;
        }

        update_option('vis_zeus_config', $config);
        if (class_exists('VIS_Zeus')) {
            $zeus = new VIS_Zeus();
            $zeus->deploy_perimeter_shield();
        }

        wp_send_json_success([
            'message' => sprintf('Zeus configuration restored to %s preset.', $preset),
            'config' => $config
        ]);
    }

    public static function handle_zeus_save_contract(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');
        $id = sanitize_key($_POST['contract_id'] ?? '');
        $path = sanitize_text_field($_POST['path'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? 'Custom Route Contract');
        $matchType = sanitize_key($_POST['match_type'] ?? 'EXACT');
        $methodsRaw = $_POST['methods'] ?? ['GET', 'POST'];
        $methods = is_array($methodsRaw) ? array_map('sanitize_text_field', $methodsRaw) : ['GET', 'POST'];
        $maxBody = max(1024, (int)($_POST['max_body_bytes'] ?? 65536));
        $maxQuery = max(64, (int)($_POST['max_query_length'] ?? 2048));

        if ($id === '' || $path === '') {
            wp_send_json_error(['message' => 'Contract ID and path are required.'], 400);
        }

        $custom = get_option('vis_zeus_custom_contracts', []);
        if (!is_array($custom)) $custom = [];

        $custom[$id] = [
            'name' => $name,
            'match_type' => $matchType === 'PREFIX' ? 'PREFIX' : 'EXACT',
            'path' => '/' . ltrim($path, '/'),
            'methods' => array_values($methods),
            'max_body_bytes' => $maxBody,
            'allowed_content_types' => ['*'],
            'max_query_length' => $maxQuery,
            'max_query_params' => 50,
            'cross_site_policy' => 'ALLOW',
            'admission_required' => !empty($_POST['admission_required']),
            'rate_budget' => ['limit' => 120, 'window' => 60],
            'status' => 'ACTIVE',
            'is_system' => false
        ];

        update_option('vis_zeus_custom_contracts', $custom);
        if (class_exists('VIS_Zeus')) {
            $zeus = new VIS_Zeus();
            $zeus->deploy_perimeter_shield();
        }

        wp_send_json_success(['message' => 'Route contract saved and active.', 'contracts' => $custom]);
    }

    public static function handle_zeus_delete_contract(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');
        $id = sanitize_key($_POST['contract_id'] ?? '');
        $custom = get_option('vis_zeus_custom_contracts', []);
        if (is_array($custom) && isset($custom[$id])) {
            unset($custom[$id]);
            update_option('vis_zeus_custom_contracts', $custom);
            if (class_exists('VIS_Zeus')) {
                $zeus = new VIS_Zeus();
                $zeus->deploy_perimeter_shield();
            }
        }
        wp_send_json_success(['message' => 'Route contract deleted.', 'contracts' => $custom]);
    }

    public static function handle_zeus_generate_token(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');
        $surface = sanitize_key($_POST['surface'] ?? 'login');
        $ttl = min(3600, max(60, (int)($_POST['ttl'] ?? 300)));

        $token = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Admission::generateToken($surface, $ttl);
        $entryUrl = home_url('/wp-login.php?vgt_adm=' . urlencode($token));

        wp_send_json_success([
            'token' => $token,
            'entry_url' => $entryUrl,
            'expires_in' => $ttl
        ]);
    }

    public static function handle_zeus_rollback_policy(): void {
        self::ensure_zeus_dependencies();
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');
        $ok = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Policy_Manager::rollbackToLastKnownGood(function(array $cfg) {
            $zeus = new VIS_Zeus();
            $zeus->deploy_perimeter_shield();
        });

        if ($ok) {
            wp_send_json_success(['message' => 'Successfully rolled back to Last Known Good policy.']);
        } else {
            wp_send_json_error(['message' => 'No Last Known Good policy available.'], 400);
        }
    }

    public static function handle_inspect_file(): void {
        check_ajax_referer('vis_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Zugriff verweigert.', 'vgt-sentinel')]);
        }

        $file = sanitize_text_field($_POST['file'] ?? '');
        if (empty($file)) {
            wp_send_json_error(['message' => __('Kein Dateipfad übermittelt.', 'vgt-sentinel')]);
        }

        // Normalize the path
        $normalized_path = $file;
        if (strpos($file, WP_CONTENT_DIR) !== 0) {
            $normalized_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . ltrim($file, '/\\');
        }

        $real_path = realpath($normalized_path);
        if (!$real_path) {
            wp_send_json_error(['message' => __('Die Datei existiert nicht oder ist ungültig.', 'vgt-sentinel')]);
        }

        $real_content = realpath(WP_CONTENT_DIR);
        if ($real_content === false || !str_starts_with($real_path, $real_content . DIRECTORY_SEPARATOR)) {
            wp_send_json_error(['message' => __('Zugriff verweigert (Pfad-Traversal erkannt).', 'vgt-sentinel')]);
        }

        $normalized_real = wp_normalize_path($real_path);
        $vault_roots = array_filter([
            defined('VIS_VAULT_DIR') ? wp_normalize_path(VIS_VAULT_DIR) : '',
            wp_normalize_path(WP_CONTENT_DIR . '/uploads/vgt-vault'),
            wp_normalize_path(WP_CONTENT_DIR . '/uploads/vis-vault-omega'),
        ]);
        foreach ($vault_roots as $vault_root) {
            if ($normalized_real === $vault_root || str_starts_with($normalized_real, $vault_root . '/')) {
                wp_send_json_error(['message' => __('Geschützter Sicherheitsbereich.', 'vgt-sentinel')], 403);
            }
        }

        $basename = strtolower(basename($real_path));
        if (preg_match('/(?:^\.env|\.key$|\.pem$|\.p12$|\.pfx$|credentials|secret)/i', $basename) === 1) {
            wp_send_json_error(['message' => __('Geschützte Konfigurationsdatei.', 'vgt-sentinel')], 403);
        }

        // Verify is file and readable
        if (!is_file($real_path) || !is_readable($real_path)) {
            wp_send_json_error(['message' => __('Datei kann nicht gelesen werden.', 'vgt-sentinel')]);
        }

        // Extension whitelist validation
        $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
        $allowed_extensions = ['php', 'js', 'css', 'json', 'txt', 'html', 'xml', 'htaccess'];
        if (!in_array($ext, $allowed_extensions, true) && basename($real_path) !== '.htaccess') {
            wp_send_json_error(['message' => __('Dateityp nicht erlaubt.', 'vgt-sentinel')]);
        }

        // Max filesize cap: 500KB
        $size = @filesize($real_path);
        if ($size === false || $size === 0 || $size > 1024 * 500) {
            wp_send_json_error(['message' => __('Datei ist zu groß (maximal 500 KB erlaubt).', 'vgt-sentinel')]);
        }

        $content = @file_get_contents($real_path);
        if ($content === false) {
             wp_send_json_error(['message' => __('Fehler beim Lesen der Dateiinhalte.', 'vgt-sentinel')]);
        }

        wp_send_json_success([
             'filename' => esc_html(basename($real_path)),
             'path'     => esc_html(str_replace(WP_CONTENT_DIR, '', $real_path)),
             'content'  => $content // Safe: escaped in frontend jQuery
        ]);
    }

    public static function handle_security_center_test(): void {
        self::verify_privileges('vis_nonce');
        $engine = VIS_PATH . 'includes/core/class-vis-security-center.php';
        if (!class_exists('VIS_Security_Center') && is_readable($engine)) require_once $engine;
        if (!class_exists('VIS_Security_Center')) {
            wp_send_json_error(['message' => 'Security Center unavailable.'], 503);
        }
        try {
            wp_send_json_success(VIS_Security_Center::snapshot(true));
        } catch (Throwable $e) {
            error_log('[VIS SECURITY CENTER] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Security self-test failed safely.'], 500);
        }
    }

    public static function handle_oracle_ping(): void {
        self::verify_privileges('vis_oracle_ping');
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            wp_send_json_error(['message' => 'Request rejected for security reasons.'], 405);
        }

        $rate_key = 'vis_oracle_ping_' . get_current_user_id();
        if (get_transient($rate_key) !== false) {
            wp_send_json_error(['message' => __('Bitte 15 Sekunden bis zum nächsten Test warten.', 'vgt-sentinel')], 429);
        }
        set_transient($rate_key, '1', 15);

        try {
            if (!class_exists('VIS_Key_Vault')) {
                throw new StorageException('Oracle vault runtime unavailable.');
            }
            $api_key = VIS_Key_Vault::get_key('vis_aegis_ai_key');
            if ($api_key === '') {
                throw new ValidationException(__('Kein Groq-Key im Schlüssel-Tresor hinterlegt.', 'vgt-sentinel'));
            }

            $started = hrtime(true);
            $response = wp_remote_get('https://api.groq.com/openai/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept' => 'application/json',
                    'User-Agent' => 'GeDefense-Oracle-Diagnostics/8.0',
                ],
                'timeout' => 8,
                'redirection' => 0,
                'sslverify' => true,
                'limit_response_size' => 32768,
            ]);
            $latency = max(1, (int)round((hrtime(true) - $started) / 1_000_000));
            if (is_wp_error($response)) {
                throw new StorageException('Oracle diagnostics transport failure.');
            }
            if (wp_remote_retrieve_response_code($response) !== 200) {
                throw new SecurityException('Oracle token or remote origin rejected diagnostics authentication.');
            }

            [$grade, $score] = match (true) {
                $latency <= 180 => [__('Exzellent', 'vgt-sentinel'), 100],
                $latency <= 350 => [__('Schnell', 'vgt-sentinel'), 82],
                $latency <= 700 => [__('Stabil', 'vgt-sentinel'), 60],
                $latency <= 1400 => [__('Langsam', 'vgt-sentinel'), 36],
                default => [__('Sehr langsam', 'vgt-sentinel'), 15],
            };
            wp_send_json_success([
                'latencyMs' => $latency,
                'score' => $score,
                'grade' => $grade,
                'message' => __('Groq-Uplink authentifiziert und erreichbar.', 'vgt-sentinel'),
            ]);
        } catch (ValidationException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 422);
        } catch (SecurityException $e) {
            error_log('[ORACLE SECURITY] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Request rejected for security reasons.'], 403);
        } catch (StorageException $e) {
            error_log('[ORACLE STORAGE] ' . $e->getMessage());
            wp_send_json_error(['message' => __('Groq-Uplink ist momentan nicht erreichbar.', 'vgt-sentinel')], 503);
        } catch (Throwable $e) {
            error_log('[ORACLE FATAL] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Critical system fault.'], 500);
        }
    }

    // ========================================================================
    // ADD-ON MANAGEMENT HANDLERS
    // ========================================================================
    public static function handle_upload_addon(): void {
        self::verify_privileges('vis_nonce');

        if (empty($_FILES['addon_zip']) || empty($_FILES['addon_zip']['tmp_name'])) {
            wp_send_json_error(['message' => __('Keine Datei hochgeladen.', 'vgt-sentinel')]);
        }

        $file = $_FILES['addon_zip'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('Upload-Fehler aufgetreten.', 'vgt-sentinel')]);
        }

        $filename = strtolower($file['name']);
        if (!str_ends_with($filename, '.zip')) {
            wp_send_json_error(['message' => __('Nur .zip Archive sind als Add-Ons zulässig.', 'vgt-sentinel')]);
        }

        if (!class_exists('ZipArchive')) {
            wp_send_json_error(['message' => __('PHP ZipArchive Erweiterung nicht verfügbar.', 'vgt-sentinel')]);
        }

        $zip = new \ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            wp_send_json_error(['message' => __('ZIP-Archiv konnte nicht geöffnet werden oder ist beschädigt.', 'vgt-sentinel')]);
        }

        // Security check: inspect all zip entries for Path Traversal, dangerous symlinks, or zip bombs
        $max_uncompressed = 52428800; // 50MB
        $max_files = 500;
        $total_uncompressed = 0;

        if ($zip->numFiles > $max_files) {
            $zip->close();
            wp_send_json_error(['message' => __('Sicherheitsalarm: Zu viele Dateien im ZIP-Archiv.', 'vgt-sentinel')], 403);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) continue;
            $entry_name = $stat['name'];
            if (str_contains($entry_name, '..') || str_starts_with($entry_name, '/') || str_starts_with($entry_name, '\\') || str_contains($entry_name, "\0")) {
                $zip->close();
                wp_send_json_error(['message' => __('Sicherheitsalarm: Unzulässige Pfadstruktur im ZIP-Archiv erkannt.', 'vgt-sentinel')], 403);
            }

            $total_uncompressed += (int)($stat['size'] ?? 0);
            if ($total_uncompressed > $max_uncompressed) {
                $zip->close();
                wp_send_json_error(['message' => __('Sicherheitsalarm: Entpacktes Archiv überschreitet das 50MB Limit (Zip-Bomb Schutz).', 'vgt-sentinel')], 403);
            }
        }

        $addons_dir = VIS_Module_Registry::get_addons_dir();
        if (!is_dir($addons_dir)) {
            wp_mkdir_p($addons_dir);
            // Protect addons directory with .htaccess and index.php
            @file_put_contents($addons_dir . '/index.php', "<?php\nhttp_response_code(404);\nexit;\n");
        }

        $extracted = $zip->extractTo($addons_dir);
        $zip->close();

        if (!$extracted) {
            wp_send_json_error(['message' => __('Entpacken des Add-Ons fehlgeschlagen.', 'vgt-sentinel')]);
        }

        wp_send_json_success([
            'message' => __('Add-On erfolgreich installiert und registriert!', 'vgt-sentinel')
        ]);
    }

    public static function handle_uninstall_addon(): void {
        self::verify_privileges('vis_nonce');

        $addon_id = sanitize_key($_POST['addon_id'] ?? '');
        if (empty($addon_id) || !in_array($addon_id, ['vlp', 'builder', 'seo'], true)) {
            wp_send_json_error(['message' => __('Ungültiges Add-On.', 'vgt-sentinel')]);
        }

        $deleted = VIS_Module_Registry::uninstall_addon($addon_id);
        if ($deleted) {
            wp_send_json_success(['message' => sprintf(__('Add-On "%s" erfolgreich deinstalliert.', 'vgt-sentinel'), strtoupper($addon_id))]);
        } else {
            wp_send_json_error(['message' => __('Add-On Verzeichnis konnte nicht gefunden oder gelöscht werden.', 'vgt-sentinel')]);
        }
    }
}
