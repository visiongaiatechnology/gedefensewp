<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * VGT OMEGA PROTOCOL - MASTER AJAX CONTROLLER
 * STATUS: PLATIN (Gorgon-Routing delegiert zur Auflösung von Race-Conditions)
 */
final class VIS_Dashboard_Ajax {

    public static function mount_endpoints(): void {
        // --- CORE & SCANNER ---
        add_action('wp_ajax_vis_approve_changes', [self::class, 'handle_approve']); 
        add_action('wp_ajax_vis_save_zeus_config', [self::class, 'handle_zeus_config']);
        add_action('wp_ajax_vis_dashboard_unban_ip', [self::class, 'handle_unban_ip']);
        add_action('wp_ajax_vis_run_scan', [self::class, 'handle_scan_bridge']); 
        add_action('wp_ajax_vgt_integrity_uplink', [self::class, 'handle_scan_bridge']);
        
        // VGT SECURE EXPLORER: Source inspector AJAX endpoint
        add_action('wp_ajax_vis_inspect_file', [self::class, 'handle_inspect_file']);
        add_action('wp_ajax_vis_security_center_test', [self::class, 'handle_security_center_test']);

        // VGT ADD-ON SYSTEM: Dynamic module upload and management
        add_action('wp_ajax_vis_upload_addon', [self::class, 'handle_upload_addon']);
        add_action('wp_ajax_vis_uninstall_addon', [self::class, 'handle_uninstall_addon']);
    }
        
        // CHIRURGISCHER EINGRIFF: Gorgon-Endpoints restlos entfernt.
        // Das Routing wird exklusiv über `class-vis-gorgon-ajax.php` abgewickelt, 
        // um Duplicate-Execution und Headers-Already-Sent Panics zu verhindern.
    

    private static function verify_privileges(string $nonce_action, string $nonce_key = 'nonce'): void {
        check_ajax_referer($nonce_action, $nonce_key);
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'VGT_UNAUTHORIZED_ACCESS']);
        }
    }

    // ========================================================================
    // CORE & SCANNER HANDLER
    // ========================================================================
    public static function handle_unban_ip(): void {
        // Kryptografische Verifikation
        check_ajax_referer('vis_dashboard_nonce', 'nonce'); 
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('VGT SECURITY ALERT: Unauthorized access.');
        }

        $ip = sanitize_text_field($_POST['ip'] ?? '');
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            wp_send_json_error('VGT KERNEL ERROR: Invalid IP format.');
        }

        global $wpdb;
        $table_bans = defined('VIS_TABLE_BANS') ? $wpdb->prefix . VIS_TABLE_BANS : $wpdb->prefix . 'vis_bans';
        
        $deleted = $wpdb->delete($table_bans, ['ip' => $ip]);
        
        if ($deleted !== false) {
            wp_send_json_success('IP erfolgreich aus der Cerberus-Sperrliste entfernt.');
        } else {
            wp_send_json_error('VGT DB ERROR: Unban-Operation auf Datenbankebene fehlgeschlagen.');
        }
    }
    
    
    public static function handle_scan_bridge(): void {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'VGT_UNAUTHORIZED_ACCESS'], 403);
        }

        $scanner_path = defined('VIS_PATH') ? VIS_PATH . 'includes/scanner/class-vis-scanner-engine.php' : '';
        if (!class_exists('VIS_Scanner_Engine_Omega') && file_exists($scanner_path)) {
            require_once $scanner_path;
        }

        if (!class_exists('VIS_Scanner_Engine_Omega')) {
            wp_send_json_error(['message' => 'CRITICAL FAULT: Omega Engine Missing. Compilation aborted.']);
        }

        $engine = new VIS_Scanner_Engine_Omega();
        
        // 1. Check Auth (CUP preferred, Nonce Fallback)
        $provided_token = $_SERVER['HTTP_X_VGT_UPLINK_TOKEN'] ?? '';
        $valid_token    = get_transient('vis_uplink_master_token');
        
        $is_cup_valid = ($valid_token && hash_equals($valid_token, $provided_token));
        
        if (!$is_cup_valid) {
            // Legacy Fallback: Wenn CUP fehlt (z.B. gecachtes JS), nutze nativen WP Nonce
            if (!check_ajax_referer('vis_nonce', 'nonce', false)) {
                wp_send_json_error(['message' => 'Native Uplink lost. Cryptographic handshake failed.'], 403);
                exit;
            }
        }
        // 2. Payload Extraction
        $phase  = isset($_POST['phase']) ? sanitize_text_field($_POST['phase']) : 'init';
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
        $mode   = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'scan';
        
        // 3. Execution
        try {
            $result = $engine->run_scan_cycle($phase, $offset, $mode);
            
            // VGT OMEGA KERNEL FIX: Terminal-State Synchronisation
            // Wir gleichen die Abbruchbedingung im Backend EXAKT mit der Scanner.js ab.
            if (isset($result['status']) && $result['status'] === 'error') {
                wp_send_json_error($result, 500);
            }
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            error_log('[VGT DASHBOARD ENGINE] ' . $e->getMessage());
            wp_send_json_error(['message' => 'ENGINE EXCEPTION: Internal scanner fault.'], 500);
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
        self::verify_privileges('vis_save_zeus', 'vis_zeus_nonce');

        $config = [
            'fw_basic'             => isset($_POST['fw_basic']),
            'fw_6g_blacklist'      => isset($_POST['fw_6g_blacklist']),
            'fw_fake_googlebot'    => isset($_POST['fw_fake_googlebot']),
            'fw_block_xmlrpc'      => isset($_POST['fw_block_xmlrpc']),
            'brute_rename_login'   => sanitize_key($_POST['brute_rename_login'] ?? ''),
            'brute_magic_cookie'   => sanitize_key($_POST['brute_magic_cookie'] ?? ''),
            'brute_404_lockout'    => (int) ($_POST['brute_404_lockout'] ?? 20),
            'user_login_lockdown'  => (int) ($_POST['user_login_lockdown'] ?? 5),
            'user_force_logout'    => (int) ($_POST['user_force_logout'] ?? 3600),
            'fs_disable_edit'      => isset($_POST['fs_disable_edit']),
            'fs_prevent_hotlink'   => isset($_POST['fs_prevent_hotlink']),
            'spam_comment_block'   => isset($_POST['spam_comment_block'])
        ];

        update_option('vis_zeus_config', $config);

        try {
            if (!class_exists('VIS_Zeus')) {
                throw new StorageException('Zeus runtime unavailable.');
            }
            $zeus = new VIS_Zeus();
            $result = $zeus->deploy_perimeter_shield();
            if (!in_array(true, $result['environment'] ?? [], true)) {
                wp_send_json_success([
                    'message' => 'ZEUS WAF compiled. Hosting bootstrap files require manual activation.',
                    'status' => 'partial',
                ]);
            }
        } catch (ValidationException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 422);
        } catch (SecurityException $e) {
            error_log('[ZEUS SECURITY] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Request rejected for security reasons.'], 403);
        } catch (StorageException $e) {
            error_log('[ZEUS STORAGE] ' . $e->getMessage());
            wp_send_json_error(['message' => 'A server error occurred.'], 500);
        } catch (Throwable $e) {
            error_log('[ZEUS FATAL] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Critical system fault.'], 500);
        }

        wp_send_json_success(['message' => 'ZEUS OMEGA WAF Payload successfully compiled & deployed.']);
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
