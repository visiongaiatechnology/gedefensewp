<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Dashboard_Settings {

    public static function process_mutations(): void {
        self::handle_standard_config();
        self::handle_zeus_fallback_config();
    }

    private static function handle_standard_config(): void {
        if ((!isset($_POST['vis_save_config']) && !isset($_POST['_wpnonce'])) || !check_admin_referer('vis_save_config')) {
            return;
        }

        $current = get_option('vis_config', []);
        $raw_new = isset($_POST['vis_config']) && is_array($_POST['vis_config']) ? $_POST['vis_config'] : [];
        $context = isset($_POST['vis_context']) ? sanitize_key($_POST['vis_context']) : 'all';
        
        $new_sanitized = [];
        $textarea_whitelist = ['styx_whitelist', 'prometheus_whitelist_ips', 'aegis_whitelist_ips', 'aegis_whitelist_uas', 'ghost_trap_exts', 'chronos_email_body'];

        foreach ($raw_new as $key => $value) {
            $clean_key = sanitize_key($key);
            if (in_array($clean_key, $textarea_whitelist, true)) {
                $new_sanitized[$clean_key] = sanitize_textarea_field($value);
            } else {
                $new_sanitized[$clean_key] = sanitize_text_field($value);
            }
        }
        
        $scope_map = [
            'aegis'      => ['aegis_enabled', 'aegis_mode'],
            'titan'      => ['titan_enabled', 'titan_block_xmlrpc', 'titan_block_rest', 'titan_disable_feeds', 'titan_camouflage_mode', 'titan_cleanup_emojis', 'titan_cleanup_embeds','titan_includes_guard','titan_xmlrpc_honeypot', 'titan_login_slug', 'titan_login_gatekeeper', 'titan_heartbeat_disable'],
            'hades'      => ['hades_enabled'],
            'prometheus' => ['prometheus_enabled'], 
            'nemesis'    => ['nemesis_enabled', 'nemesis_active_strike'],
            'styx'       => ['styx_enabled', 'styx_audit_mode', 'styx_block_wp_telemetry'],
            'gorgon'     => ['gorgon_enabled'],
            'morpheus'   => ['morpheus_enabled'],
            'zeus'       => ['zeus_enabled'],
            'vlp'        => ['vlp_enabled'],
            'airlock'    => ['airlock_enabled', 'airlock_obfuscate', 'airlock_max_mb', 'airlock_extensions'],
            'chronos'    => ['chronos_enabled', 'chronos_interval', 'chronos_email_to', 'chronos_email_subject'],
            'ghost_trap' => ['ghost_trap_enabled', 'ghost_trap_count', 'ghost_trap_exts', 'ghost_trap_style'],
            'modules'    => ['module_vlp_enabled', 'module_builder_enabled', 'module_seo_enabled'],
            'setup_wizard' => [
                'aegis_enabled', 'aegis_mode',
                'zeus_enabled', 'cerberus_enabled',
                'prometheus_enabled',
                'morpheus_enabled', 'morpheus_enforce',
                'nemesis_enabled', 'nemesis_active_strike',
                'ghost_trap_enabled', 'trap_enabled',
                'titan_enabled', 'titan_block_xmlrpc', 'titan_block_rest', 'titan_disable_feeds', 'titan_camouflage_mode',
                'hades_enabled',
                'chronos_enabled',
                'styx_enabled', 'styx_block_wp_telemetry',
                'gorgon_enabled', 'kernel_enabled', 'filesystem_enabled',
                'airlock_enabled'
            ]
        ];
        
        $checkboxes_to_check = ($context === 'all') ? array_merge(...array_values($scope_map)) : ($scope_map[$context] ?? []);

        foreach ($checkboxes_to_check as $cb) {
            if (!isset($new_sanitized[$cb])) {
                $new_sanitized[$cb] = 0;
            }
        }
        
        $updated_config = array_merge($current, $new_sanitized);
        update_option('vis_config', $updated_config);

        if ($context === 'all' || $context === 'hades' || $context === 'setup_wizard') {
            if (class_exists('VIS_Hades')) {
                VIS_Hades::mark_routes_dirty();
            } else {
                update_option('vis_hades_routes_dirty', '1', false);
                delete_transient('vgt_shadow_compiled_matrix_v12');
            }
        }

        // Setup Wizard: Complete Onboarding Integrations
        if ($context === 'setup_wizard') {
            update_option('vgt_setup_wizard_completed', 1);

            if (!empty($_POST['groq_api_key'])) {
                $key_val = sanitize_text_field($_POST['groq_api_key']);
                if (class_exists('VIS_Key_Vault')) {
                    VIS_Key_Vault::save_key('vis_aegis_ai_key', $key_val);
                }
            }

            if (isset($_POST['vis_zeus_config']) && is_array($_POST['vis_zeus_config'])) {
                $zeus_raw = $_POST['vis_zeus_config'];
                $zeus_clean = [
                    'fw_basic'             => isset($zeus_raw['fw_basic']),
                    'fw_6g_blacklist'      => isset($zeus_raw['fw_6g_blacklist']),
                    'fw_fake_googlebot'    => isset($zeus_raw['fw_fake_googlebot']),
                    'fw_block_xmlrpc'      => isset($zeus_raw['fw_block_xmlrpc']),
                    'brute_rename_login'   => sanitize_text_field($zeus_raw['brute_rename_login'] ?? ''),
                    'brute_magic_cookie'   => sanitize_text_field($zeus_raw['brute_magic_cookie'] ?? ''),
                    'brute_404_lockout'    => 20,
                    'user_login_lockdown'  => 5,
                    'user_force_logout'    => 3600,
                    'fs_disable_edit'      => false,
                    'fs_prevent_hotlink'   => false,
                    'spam_comment_block'   => false
                ];
                update_option('vis_zeus_config', $zeus_clean);
                self::deploy_zeus_safely();
            }

            // Flush rewrite rules automatically to bind Hades stealth routing
            flush_rewrite_rules(true);
        }
        
        // --- VGT POST-SAVE TRIGGERS ---
        // Wenn Ghost Trap gespeichert wird, zwinge die Engine zur sofortigen Neugenerierung der physischen Dateien.
        if ($context === 'all' || $context === 'ghost_trap') {
            if (class_exists('VIS_Ghost_Trap')) {
                VIS_Ghost_Trap::trigger_regeneration();
            }
        }
        // VGT SUPREME FIX: Erweitere die Textareas um chronos_email_body
        if ($context === 'all' || $context === 'chronos') {
             if (class_exists('VIS_Chronos')) {
                 VIS_Chronos::trigger_resync();
             } else {
                 $path = VIS_PATH . 'includes/modules/chronos/class-vis-chronos.php';
                 if (file_exists($path)) {
                     require_once $path;
                     if (method_exists('VIS_Chronos', 'trigger_resync')) {
                         \VIS_Chronos::trigger_resync();
                     }
                 }
             }
        }
        // VGT SUPREME: Trinity Grid Config (Strict Type Enforcement)
        if ($context === 'all' || $context === 'trinity') {
            if (isset($_POST['vis_trinity_config']) && is_array($_POST['vis_trinity_config'])) {
                $trin_raw = $_POST['vis_trinity_config'];
                $trin_clean = [
                    'interlock_enabled'  => isset($trin_raw['interlock_enabled']),
                    'prom_waf_penalty'   => (float) ($trin_raw['prom_waf_penalty'] ?? 50.0),
                    'micro_tarpit_score' => (float) ($trin_raw['micro_tarpit_score'] ?? 75.0),
                    'tarpit_max_loops'   => (int) ($trin_raw['tarpit_max_loops'] ?? 120),
                ];
                update_option('vis_trinity_config', $trin_clean);
            }
        }

        // VGT SUPREME: Prometheus Cognitive Tuning Matrix (Strict Type Enforcement)
        if ($context === 'all' || $context === 'prometheus') {
            if (isset($_POST['vis_prometheus_config']) && is_array($_POST['vis_prometheus_config'])) {
                $prom_raw = $_POST['vis_prometheus_config'];
                $prom_clean = [];
                
                $float_keys = ['event_horizon_score', 'infra_horizon_score', 'score_decay_rate', 'penalty_method', 'penalty_params', 'penalty_regex', 'penalty_404', 'penalty_auth', 'penalty_burst', 'penalty_freq', 'penalty_rotation'];
                $int_keys   = ['infra_cooldown_window', 'score_decay_window'];
                
                foreach ($float_keys as $fk) {
                    if (isset($prom_raw[$fk])) $prom_clean[$fk] = (float) $prom_raw[$fk];
                }
                foreach ($int_keys as $ik) {
                    if (isset($prom_raw[$ik])) $prom_clean[$ik] = (int) $prom_raw[$ik];
                }
                
                update_option('vis_prometheus_config', $prom_clean);
            }
        }

        wp_safe_redirect(add_query_arg('settings-updated', 'true', $_SERVER['REQUEST_URI']));
        exit;
    }

    private static function handle_zeus_fallback_config(): void {
        if (!isset($_POST['action']) || $_POST['action'] !== 'vis_save_zeus_config' || !isset($_POST['vis_zeus_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['vis_zeus_nonce'], 'vis_save_zeus') || !current_user_can('manage_options')) {
            return;
        }
        
        $config = [
            'fw_basic'             => isset($_POST['fw_basic']),
            'fw_6g_blacklist'      => isset($_POST['fw_6g_blacklist']),
            'fw_fake_googlebot'    => isset($_POST['fw_fake_googlebot']),
            'fw_block_xmlrpc'      => isset($_POST['fw_block_xmlrpc']),
            'brute_rename_login'   => sanitize_text_field($_POST['brute_rename_login'] ?? ''),
            'brute_magic_cookie'   => sanitize_text_field($_POST['brute_magic_cookie'] ?? ''),
            'brute_404_lockout'    => (int) ($_POST['brute_404_lockout'] ?? 20),
            'user_login_lockdown'  => (int) ($_POST['user_login_lockdown'] ?? 5),
            'user_force_logout'    => (int) ($_POST['user_force_logout'] ?? 3600),
            'fs_disable_edit'      => isset($_POST['fs_disable_edit']),
            'fs_prevent_hotlink'   => isset($_POST['fs_prevent_hotlink']),
            'spam_comment_block'   => isset($_POST['spam_comment_block'])
        ];

        update_option('vis_zeus_config', $config);

        $deployed = self::deploy_zeus_safely();

        wp_safe_redirect(add_query_arg([
            'page' => 'vgt-suite',
            'tab' => 'zeus',
            'waf-deployed' => $deployed ? 'true' : 'partial',
        ], admin_url('admin.php')));
        exit;
    }

    private static function deploy_zeus_safely(): bool {
        try {
            if (!class_exists('VIS_Zeus')) {
                throw new StorageException('Zeus runtime unavailable.');
            }
            $zeus = new VIS_Zeus();
            $result = $zeus->deploy_perimeter_shield();
            return ($result['waf'] ?? false) === true
                && in_array(true, $result['environment'] ?? [], true);
        } catch (ValidationException $e) {
            error_log('[ZEUS VALIDATION] ' . $e->getMessage());
        } catch (SecurityException $e) {
            error_log('[ZEUS SECURITY] ' . $e->getMessage());
        } catch (StorageException $e) {
            error_log('[ZEUS STORAGE] ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log('[ZEUS FATAL] ' . $e->getMessage());
        }
        return false;
    }
}
