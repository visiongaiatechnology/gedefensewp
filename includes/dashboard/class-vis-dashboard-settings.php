<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Dashboard_Settings {

    public static function process_mutations(): void {
        self::handle_standard_config();
    }

    private static function handle_standard_config(): void {
        if (!current_user_can('manage_options')) return;
        if (!isset($_POST['vis_context']) || !isset($_POST['_wpnonce'])) {
            return;
        }
        $nonce = is_string($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'vis_save_config')) {
            return;
        }

        $current = get_option('vis_config', []);
        $current = is_array($current) ? $current : [];
        $raw_new = isset($_POST['vis_config']) && is_array($_POST['vis_config']) ? wp_unslash($_POST['vis_config']) : [];
        $context = isset($_POST['vis_context']) && is_string($_POST['vis_context']) ? sanitize_key(wp_unslash($_POST['vis_context'])) : 'all';
        
        $new_sanitized = [];
        $textarea_whitelist = ['styx_whitelist', 'prometheus_whitelist_ips', 'aegis_whitelist_ips', 'aegis_whitelist_uas', 'ghost_trap_exts', 'chronos_email_body', 'titan_script_origins', 'titan_style_origins', 'titan_img_origins', 'titan_connect_origins', 'titan_frame_origins'];

        foreach ($raw_new as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) continue;
            $clean_key = sanitize_key($key);
            if (in_array($clean_key, ['loginpager_bg_color', 'loginpager_accent'], true)) {
                $new_sanitized[$clean_key] = sanitize_hex_color((string)$value) ?: ($clean_key === 'loginpager_accent' ? '#00f0ff' : '#070a13');
            } elseif (in_array($clean_key, ['loginpager_bg_image', 'loginpager_logo'], true)) {
                $new_sanitized[$clean_key] = class_exists('VIS_LoginPager') ? VIS_LoginPager::safe_url((string)$value) : esc_url_raw((string)$value, ['https', 'http']);
            } elseif (in_array($clean_key, $textarea_whitelist, true)) {
                $new_sanitized[$clean_key] = sanitize_textarea_field($value);
            } else {
                $new_sanitized[$clean_key] = sanitize_text_field($value);
            }
        }
        
        $scope_map = [
            'aegis'      => ['aegis_enabled', 'aegis_mode', 'aegis_whitelist_ips', 'aegis_whitelist_uas'],
            'titan'      => [
                'titan_enabled', 'titan_profile', 'titan_csp_mode', 'titan_fetch_mode',
                'titan_trusted_types_mode', 'titan_coep_mode', 'titan_nonce_enabled',
                'titan_learning_enabled', 'titan_server_spoof', 'titan_anti_enum',
                'titan_hide_version', 'titan_remove_asset_versions', 'titan_remove_discovery_links',
                'titan_application_lockdown', 'titan_block_xmlrpc', 'titan_xmlrpc_mode',
                'titan_block_rest', 'titan_disable_feeds', 'titan_camouflage_mode',
                'titan_cleanup_emojis', 'titan_cleanup_embeds', 'titan_includes_guard',
                'titan_xmlrpc_honeypot', 'titan_login_gatekeeper', 'titan_heartbeat_disable',
                'titan_hsts_enabled', 'titan_hsts_include_subdomains', 'titan_hsts_preload',
                'titan_hsts_max_age', 'titan_application_passwords_mode',
                'titan_header_conflict_strategy',
                'titan_script_origins', 'titan_style_origins', 'titan_img_origins',
                'titan_connect_origins', 'titan_frame_origins', 'titan_permissions_self',
                'titan_sandbox_origin', 'titan_sandbox_origin_verified', 'titan_active_content_direct_access',
            ],
            'hades'      => [
                'hades_enabled', 'hades_admin_param', 'hades_admin_secret',
                'hades_map_themes', 'hades_map_plugins', 'hades_map_uploads', 'hades_map_content',
                'hades_map_includes', 'hades_map_rest', 'hades_map_ajax', 'hades_map_post',
            ],
            'prometheus' => ['prometheus_enabled', 'prometheus_whitelist_ips'], 
            'nemesis'    => ['nemesis_enabled'],
            'styx'       => ['styx_enabled', 'styx_audit_mode', 'styx_block_wp_telemetry', 'styx_whitelist'],
            'gorgon'     => [],
            'morpheus'   => ['morpheus_enabled'],
            'zeus'       => ['zeus_enabled'],
            'vlp'        => ['vlp_enabled'],
            'airlock'    => ['airlock_enabled', 'airlock_obfuscate', 'airlock_max_mb', 'airlock_extensions'],
            'loginpager' => ['loginpager_enabled', 'loginpager_bg_color', 'loginpager_accent', 'loginpager_bg_image', 'loginpager_logo', 'loginpager_title', 'loginpager_subtitle', 'loginpager_glass_blur'],
            'chronos'    => ['chronos_enabled', 'chronos_interval', 'chronos_email_to', 'chronos_email_subject', 'chronos_email_body'],
            'ghost_trap' => ['ghost_trap_enabled', 'ghost_trap_count', 'ghost_trap_exts', 'ghost_trap_style'],
            'modules'    => ['module_vlp_enabled', 'module_builder_enabled', 'module_seo_enabled'],
            'setup_wizard' => [
                'aegis_enabled', 'aegis_mode', 'aegis_whitelist_ips', 'prometheus_whitelist_ips',
                'zeus_enabled', 'cerberus_enabled',
                'prometheus_enabled',
                'morpheus_enabled', 'morpheus_enforce',
                'nemesis_enabled',
                'ghost_trap_enabled', 'trap_enabled',
                'titan_enabled', 'titan_block_xmlrpc', 'titan_block_rest', 'titan_disable_feeds', 'titan_hide_version',
                'hades_enabled', 'hades_admin_param', 'hades_admin_secret',
                'chronos_enabled',
                'styx_enabled', 'styx_block_wp_telemetry',
                'gorgon_enabled', 'kernel_enabled', 'filesystem_enabled',
                'airlock_enabled',
                'throneguard_enabled', 'loginpager_enabled',
                'loginpager_bg_color', 'loginpager_accent', 'loginpager_bg_image', 'loginpager_logo'
            ]
        ];
        
        $checkbox_keys = [
            'aegis_enabled', 'hades_enabled', 'prometheus_enabled', 'nemesis_enabled',
            'styx_enabled', 'styx_audit_mode', 'styx_block_wp_telemetry', 'gorgon_enabled',
            'morpheus_enabled', 'zeus_enabled', 'vlp_enabled', 'airlock_enabled',
            'airlock_obfuscate', 'loginpager_enabled', 'chronos_enabled', 'ghost_trap_enabled',
            'cerberus_enabled', 'morpheus_enforce', 'throneguard_enabled', 'kernel_enabled',
            'filesystem_enabled', 'trap_enabled',
            'module_vlp_enabled', 'module_builder_enabled', 'module_seo_enabled',
            'titan_enabled', 'titan_nonce_enabled', 'titan_learning_enabled',
            'titan_server_spoof', 'titan_anti_enum', 'titan_hide_version',
            'titan_remove_asset_versions', 'titan_remove_discovery_links',
            'titan_application_lockdown', 'titan_block_xmlrpc', 'titan_block_rest',
            'titan_disable_feeds', 'titan_cleanup_emojis', 'titan_cleanup_embeds',
            'titan_includes_guard', 'titan_xmlrpc_honeypot', 'titan_login_gatekeeper',
            'titan_heartbeat_disable', 'titan_hsts_enabled', 'titan_hsts_include_subdomains',
            'titan_hsts_preload',
            'titan_sandbox_origin_verified',
        ];
        $context_keys = $context === 'all' ? array_merge(...array_values($scope_map)) : ($scope_map[$context] ?? []);
        $new_sanitized = array_intersect_key($new_sanitized, array_fill_keys($context_keys, true));
        $checkboxes_to_check = array_values(array_intersect($context_keys, $checkbox_keys));

        if (isset($new_sanitized['aegis_mode'])) {
            $new_sanitized['aegis_mode'] = in_array($new_sanitized['aegis_mode'], ['strict', 'learning'], true) ? $new_sanitized['aegis_mode'] : 'strict';
        }
        if (isset($new_sanitized['chronos_interval'])) {
            $new_sanitized['chronos_interval'] = in_array($new_sanitized['chronos_interval'], ['vis_15m', 'vis_30m', 'vis_hourly', 'vis_twicedaily', 'vis_daily'], true) ? $new_sanitized['chronos_interval'] : 'vis_hourly';
        }
        if (isset($new_sanitized['chronos_email_to'])) {
            $new_sanitized['chronos_email_to'] = sanitize_email($new_sanitized['chronos_email_to']);
        }
        if (isset($new_sanitized['airlock_max_mb'])) {
            $new_sanitized['airlock_max_mb'] = self::bounded_int($new_sanitized['airlock_max_mb'], 5, 1, 100);
        }

        foreach ($checkboxes_to_check as $cb) {
            if (!isset($new_sanitized[$cb])) {
                $new_sanitized[$cb] = 0;
            }
        }

        if ($context === 'all' || $context === 'titan') {
            $new_sanitized = self::sanitize_titan_config($new_sanitized);
        }
        
        $updated_config = array_merge($current, $new_sanitized);
        $persisted = update_option('vis_config', $updated_config);
        if (!$persisted && get_option('vis_config', null) !== $updated_config) {
            wp_safe_redirect(add_query_arg(['page' => 'vgt-suite', 'tab' => $context, 'settings-error' => 'storage'], admin_url('admin.php')));
            exit;
        }

        if ($context === 'all' || $context === 'titan') {
            self::stage_titan_policy($updated_config);
        }

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

            if (!empty($updated_config['throneguard_enabled']) && class_exists('VIS_Throne_Guard')) {
                VIS_Throne_Guard::provision_current_master();
            }
            if (class_exists('VIS_Throne_Guard')) {
                VIS_Throne_Guard::apply_administrator_policy(!empty($updated_config['throneguard_enabled']) && !empty($updated_config['throneguard_harden_admin']));
            }

            if (!empty($_POST['groq_api_key'])) {
                $key_val = is_string($_POST['groq_api_key']) ? sanitize_text_field(wp_unslash($_POST['groq_api_key'])) : '';
                if (class_exists('VIS_Key_Vault')) {
                    VIS_Key_Vault::save_key('vis_aegis_ai_key', $key_val);
                }
            }

            if (isset($_POST['vis_zeus_config']) && is_array($_POST['vis_zeus_config'])) {
                $zeus_raw = wp_unslash($_POST['vis_zeus_config']);
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
                $trin_raw = wp_unslash($_POST['vis_trinity_config']);
                $trin_clean = [
                    'interlock_enabled'  => !empty($trin_raw['interlock_enabled']),
                    'prom_waf_penalty'   => self::bounded_float($trin_raw['prom_waf_penalty'] ?? null, 50.0, 0.0, 100.0),
                    'micro_tarpit_score' => self::bounded_float($trin_raw['micro_tarpit_score'] ?? null, 75.0, 10.0, 200.0),
                ];
                update_option('vis_trinity_config', $trin_clean);
            }

            if (isset($_POST['vis_xdr_config']) && is_array($_POST['vis_xdr_config'])) {
                $xdr_raw = wp_unslash($_POST['vis_xdr_config']);
                $existing_xdr = get_option('vis_xdr_config', []);
                if (!is_array($existing_xdr)) $existing_xdr = [];

                $clean_xdr = array_merge($existing_xdr, [
                    'auto_response_enabled'   => !empty($xdr_raw['auto_response_enabled']) ? 1 : 0,
                    'escalation_enabled'      => !empty($xdr_raw['escalation_enabled']) ? 1 : 0,
                    'actuator_cerberus'       => isset($xdr_raw['actuator_cerberus']) ? (!empty($xdr_raw['actuator_cerberus']) ? 1 : 0) : (!empty($xdr_raw['auto_response_enabled']) ? 1 : 0),
                    'actuator_zeus_route'     => isset($xdr_raw['actuator_zeus_route']) ? (!empty($xdr_raw['actuator_zeus_route']) ? 1 : 0) : (!empty($xdr_raw['auto_response_enabled']) ? 1 : 0),
                    'actuator_zeus_admission' => isset($xdr_raw['actuator_zeus_admission']) ? (!empty($xdr_raw['actuator_zeus_admission']) ? 1 : 0) : (!empty($xdr_raw['auto_response_enabled']) ? 1 : 0),
                    'actuator_morpheus'       => isset($xdr_raw['actuator_morpheus']) ? (!empty($xdr_raw['actuator_morpheus']) ? 1 : 0) : (!empty($xdr_raw['auto_response_enabled']) ? 1 : 0),
                    'actuator_styx'           => isset($xdr_raw['actuator_styx']) ? (!empty($xdr_raw['actuator_styx']) ? 1 : 0) : (!empty($xdr_raw['auto_response_enabled']) ? 1 : 0),
                    'actuator_plugin'         => isset($xdr_raw['actuator_plugin']) ? (!empty($xdr_raw['actuator_plugin']) ? 1 : 0) : (!empty($xdr_raw['auto_response_enabled']) ? 1 : 0),
                    'retention_days'          => self::bounded_int($xdr_raw['retention_days'] ?? ($existing_xdr['retention_days'] ?? 30), 30, 7, 180),
                    'containment_policy'      => sanitize_key($xdr_raw['containment_policy'] ?? ($existing_xdr['containment_policy'] ?? 'TEMPORARY_BY_DEFAULT')),
                    'ttl_preset'              => strtoupper(sanitize_key($xdr_raw['ttl_preset'] ?? ($existing_xdr['ttl_preset'] ?? 'BALANCED'))),
                    'ttl_actor_ban'           => self::bounded_int($xdr_raw['ttl_actor_ban'] ?? ($existing_xdr['ttl_actor_ban'] ?? 900), 900, 60, 86400),
                    'ttl_subnet'              => self::bounded_int($xdr_raw['ttl_subnet'] ?? ($existing_xdr['ttl_subnet'] ?? 1800), 1800, 120, 86400),
                    'ttl_zeus_route'          => self::bounded_int($xdr_raw['ttl_zeus_route'] ?? ($existing_xdr['ttl_zeus_route'] ?? 900), 900, 60, 86400),
                    'ttl_zeus_admission'      => self::bounded_int($xdr_raw['ttl_zeus_admission'] ?? ($existing_xdr['ttl_zeus_admission'] ?? 900), 900, 60, 86400),
                    'ttl_morpheus_overlay'    => self::bounded_int($xdr_raw['ttl_morpheus_overlay'] ?? ($existing_xdr['ttl_morpheus_overlay'] ?? 900), 900, 60, 86400),
                    'ttl_styx_overlay'        => self::bounded_int($xdr_raw['ttl_styx_overlay'] ?? ($existing_xdr['ttl_styx_overlay'] ?? 900), 900, 60, 86400),
                    'whitelist'               => sanitize_textarea_field($xdr_raw['whitelist'] ?? ($existing_xdr['whitelist'] ?? ''))
                ]);
                update_option('vis_xdr_config', $clean_xdr);
            }
        }

        // VGT SUPREME: Prometheus Cognitive Tuning Matrix (Strict Type Enforcement)
        if ($context === 'all' || $context === 'prometheus') {
            if (isset($_POST['vis_prometheus_config']) && is_array($_POST['vis_prometheus_config'])) {
                $prom_raw = wp_unslash($_POST['vis_prometheus_config']);
                $prom_clean = [];
                
                $float_bounds = [
                    'event_horizon_score' => [200.0, 200.0, 1000.0],
                    'infra_horizon_score' => [150.0, 50.0, 2000.0],
                    'score_decay_rate'    => [0.2, 0.01, 10.0],
                    'penalty_method' => [30.0, 0.0, 500.0], 'penalty_params' => [15.0, 0.0, 500.0],
                    'penalty_regex' => [50.0, 0.0, 500.0], 'penalty_404' => [25.0, 0.0, 500.0],
                    'penalty_auth' => [40.0, 0.0, 500.0], 'penalty_burst' => [20.0, 0.0, 500.0],
                    'penalty_freq' => [10.0, 0.0, 500.0], 'penalty_rotation' => [25.0, 0.0, 500.0],
                ];
                $int_bounds = [
                    'infra_cooldown_window' => [3600, 60, 86400],
                    'score_decay_window' => [300, 60, 86400],
                ];

                foreach ($float_bounds as $key => [$default, $min, $max]) {
                    if (array_key_exists($key, $prom_raw)) $prom_clean[$key] = self::bounded_float($prom_raw[$key], $default, $min, $max);
                }
                foreach ($int_bounds as $key => [$default, $min, $max]) {
                    if (array_key_exists($key, $prom_raw)) $prom_clean[$key] = self::bounded_int($prom_raw[$key], $default, $min, $max);
                }
                
                update_option('vis_prometheus_config', $prom_clean);
            }
        }

        // VGT SUPREME: Zeus Next Gen Config & Pre-Boot Deployment (Canonical Save Pipeline)
        if ($context === 'all' || $context === 'zeus' || isset($_POST['vgt_zeus_form_submit'])) {
            VIS_Dashboard_Ajax::ensure_zeus_dependencies();
            if (class_exists('\VisionGaia\GeDefense\Modules\Zeus\Zeus_Config_Repository')) {
                \VisionGaia\GeDefense\Modules\Zeus\Zeus_Config_Repository::save(wp_unslash($_POST));
            }
        }

        $redirect_args = ['page' => 'vgt-suite', 'tab' => $context, 'settings-updated' => 'true'];
        if ($context === 'trinity') {
            $xdr_sec = isset($_POST['xdr_section']) && is_string($_POST['xdr_section'])
                ? sanitize_key(wp_unslash($_POST['xdr_section']))
                : (isset($_GET['xdr_section']) && is_string($_GET['xdr_section']) ? sanitize_key(wp_unslash($_GET['xdr_section'])) : 'policy');
            if ($xdr_sec !== '') {
                $redirect_args['xdr_section'] = $xdr_sec;
            }
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    private static function bounded_float(mixed $value, float $default, float $min, float $max): float {
        $number = is_numeric($value) ? (float)$value : $default;
        return max($min, min($max, $number));
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    private static function sanitize_titan_config(array $config): array {
        $enums = [
            'titan_profile' => [['compatible', 'balanced', 'strict', 'paranoid', 'custom', 'experimental_browser_zero_trust'], 'balanced'],
            'titan_csp_mode' => [['off', 'learning', 'report_only', 'enforce'], 'report_only'],
            'titan_fetch_mode' => [['off', 'audit', 'enforce_sensitive', 'strict'], 'audit'],
            'titan_trusted_types_mode' => [['off', 'report_only', 'gedefense_admin_only', 'strict_selected_surfaces'], 'off'],
            'titan_coep_mode' => [['off', 'require-corp', 'credentialless'], 'off'],
            'titan_xmlrpc_mode' => [['disabled', 'pingback_disabled', 'auth_only', 'honeypot', 'custom'], 'auth_only'],
            'titan_camouflage_mode' => [['none', 'laravel', 'drupal', 'joomla'], 'none'],
            'titan_application_passwords_mode' => [['allow', 'audit', 'disable'], 'allow'],
            'titan_header_conflict_strategy' => [['observe', 'override_titan_owned'], 'observe'],
            'titan_active_content_direct_access' => [['attachment', 'block', 'allow'], 'attachment'],
        ];
        foreach ($enums as $key => [$allowed, $default]) {
            if (!array_key_exists($key, $config)) continue;
            $value = strtolower((string)$config[$key]);
            $config[$key] = in_array($value, $allowed, true) ? $value : $default;
        }
        if (array_key_exists('titan_hsts_max_age', $config)) {
            $config['titan_hsts_max_age'] = self::bounded_int($config['titan_hsts_max_age'], 31536000, 300, 63072000);
        }
        if (isset($config['titan_sandbox_origin'])) {
            $origin = esc_url_raw((string)$config['titan_sandbox_origin'], ['https']);
            $parts = wp_parse_url($origin);
            $config['titan_sandbox_origin'] = is_array($parts)
                && ($parts['scheme'] ?? '') === 'https'
                && !empty($parts['host'])
                && empty($parts['path'])
                && empty($parts['query'])
                && empty($parts['user'])
                ? 'https://' . strtolower((string)$parts['host']) . (isset($parts['port']) ? ':' . (int)$parts['port'] : '')
                : '';
        }
        foreach (['titan_script_origins', 'titan_style_origins', 'titan_img_origins', 'titan_connect_origins', 'titan_frame_origins'] as $key) {
            if (!isset($config[$key])) continue;
            $tokens = preg_split('/[\s,]+/', trim((string)$config[$key])) ?: [];
            $origins = [];
            foreach ($tokens as $origin) {
                if ($origin === '' || preg_match('~^https://(?:\*\.)?[A-Za-z0-9.-]+(?::[0-9]{1,5})?$~D', $origin) !== 1) continue;
                $origins[$origin] = true;
            }
            $config[$key] = implode("\n", array_keys($origins));
        }
        return $config;
    }

    /** @param array<string, mixed> $config */
    private static function stage_titan_policy(array $config): void {
        try {
            if (!class_exists('VIS_Titan_Policy_Store')) {
                $entry = defined('VIS_PATH') ? VIS_PATH . 'includes/modules/titan/class-vis-titan.php' : '';
                if ($entry !== '' && is_file($entry)) require_once $entry;
            }
            if (!class_exists('VIS_Titan_Policy_Store') || !class_exists('VIS_Titan_Server_Rules')) throw new StorageException('TITAN policy runtime unavailable.');
            VIS_Titan_Policy_Store::stage($config);
            VIS_Titan_Server_Rules::deploy($config);
        } catch (ValidationException $e) {
            error_log('[TITAN VALIDATION] ' . $e->getMessage());
        } catch (SecurityException $e) {
            error_log('[TITAN SECURITY] ' . $e->getMessage());
        } catch (StorageException $e) {
            error_log('[TITAN STORAGE] ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log('[TITAN FATAL] ' . $e->getMessage());
        }
    }

    private static function bounded_int(mixed $value, int $default, int $min, int $max): int {
        $number = is_numeric($value) ? (int)$value : $default;
        return max($min, min($max, $number));
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
