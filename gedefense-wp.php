<?php
/**
 * Plugin Name: GeDefense WP - Open Core
 * Plugin URI: https://github.com/visiongaiatechnology/gedefensewp
 * Description: OMEGA-CLASS Security Suite. High-Performance Integrity Monitoring, Active Defense & RASP Matrix.
 * Version: 8.5.1
 * Author: VisionGaiaTechnology
 * Author URI: https://visiongaiatechnology.de
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * 
 * GeDefense WP - Open Core is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

// Hard platform floor: the code base relies on PHP 8.2 language features
// (readonly classes, standalone false type, first-class callable syntax).
// Without this guard WordPress would activate the plugin on an older runtime
// and every request would die with a fatal error.
if (PHP_VERSION_ID < 80200) {
    if (function_exists('add_action')) {
        add_action('admin_notices', static function (): void {
            if (function_exists('current_user_can') && !current_user_can('activate_plugins')) {
                return;
            }
            echo '<div class="notice notice-error"><p>GeDefense WP requires PHP 8.2 or newer and stays inactive until the platform is upgraded.</p></div>';
        });
    }

    return;
}

// --- 1. DOUBLE-LOAD PROTECTION & CONSTANTS ---
if (defined('VIS_VERSION')) {
    return;
}

define('VIS_VERSION', '8.5.1 OPEN CORE');
define('VIS_MANIFEST_DIGEST', '8264983cbbb79d58df0382ac1f8f857ba807d40c28d365dacd980a6d2b0352dc');
define('VIS_PRODUCT_NAME', 'GeDefense WP - Open Core');
define('VIS_PATH', plugin_dir_path(__FILE__));
define('VIS_URL', plugin_dir_url(__FILE__));
define('VIS_SENTINEL_ICON', VIS_URL . 'Sentinel.png');
define('VIS_TABLE_BANS', 'vis_apex_bans');
define('VIS_TABLE_LOGS', 'vis_omega_logs');

// Nexus wire-format fingerprint. This is NOT an authentication factor: Gorgon
// requests authenticate with the per-site API key. The value ships in the
// open-source build, so it must never be treated as a secret. Override it in
// wp-config.php when the Nexus protocol version changes.
if (!defined('VIS_GORGON_NEXUS_MAGIC')) {
    define('VIS_GORGON_NEXUS_MAGIC', 'vgt-magic-noc-9938-omega-fusion');
}

if (!defined('VIS_VAULT_DIR')) {
    if (class_exists('\\VGT\\OS\\System\\VaultManager')) {
        define('VIS_VAULT_DIR', \VGT\OS\System\VaultManager::getVaultPath() . '/sentinel-omega');
    } else {
        $vis_upload_dir = wp_upload_dir(null, false);
        define('VIS_VAULT_DIR', wp_normalize_path($vis_upload_dir['basedir'] . '/vis-vault-omega'));
    }
}

if (!defined('VIS_MANIFEST_FILE')) {
    define('VIS_MANIFEST_FILE', VIS_VAULT_DIR . '/integrity_matrix.json');
}

// --- 2. CORE SYSTEM IGNITION (ABSOLUTE MINIMUM) ---
require_once VIS_PATH . 'class-vis-bootstrapper.php';
require_once VIS_PATH . 'includes/core/class-vis-security.php';
VIS_Bootstrapper::register_autoloader();
if (class_exists('\VisionGaia\GeDefense\Core\EventBus')) {
    \VisionGaia\GeDefense\Core\EventBus::init();
}

// --- 3. ZERO-OVERHEAD HOOK MATRIX (STANDARD PLUGIN CONTEXT) ---
register_activation_hook(__FILE__, function(): void {
    require_once VIS_PATH . 'class-vis-schema.php';
    \VisionGaia\GeDefense\Core\Schema::enforce();

    // Secure-by-default activation. The previous build activated with an empty
    // configuration, so the firewall engine stayed disabled while the operator
    // assumed protection was active. Existing keys are never overwritten.
    $config = get_option('vis_config', []);
    if (!is_array($config)) {
        $config = [];
    }

    $defaults = [
        'aegis_enabled'    => 1,
        'aegis_mode'       => 'learning',
        'zeus_enabled'     => 1,
        'morpheus_enabled' => 1,
    ];

    $changed = false;
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $config)) {
            $config[$key] = $value;
            $changed = true;
        }
    }

    if ($changed) {
        update_option('vis_config', $config);
    }
});

register_deactivation_hook(__FILE__, function(): void {
    // Clear every scheduled event owned by the plugin. The previous build only
    // cleared three hooks: one had no handler at all while six other security
    // and retention jobs kept running after deactivation.
    $owned_prefixes = ['vis_', 'vgt_'];

    if (function_exists('_get_cron_array')) {
        $cron_array = _get_cron_array();
        if (is_array($cron_array)) {
            foreach (array_keys($cron_array) as $timestamp) {
                $events = isset($cron_array[$timestamp]) ? (array)$cron_array[$timestamp] : [];
                foreach (array_keys($events) as $hook) {
                    $hook = (string)$hook;
                    foreach ($owned_prefixes as $prefix) {
                        if (str_starts_with($hook, $prefix)) {
                            wp_clear_scheduled_hook($hook);
                            break;
                        }
                    }
                }
            }
        }
    }

    foreach ([
        'vis_log_retention_cleanup',
        'vis_periodic_scan_event',
        'vis_cerberus_sync_firewall',
        'vis_xdr_retention_cleanup',
        'vis_xdr_response_cleanup',
        'vgt_gorgon_neural_sync',
        'vgt_morpheus_async_ai_build',
    ] as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    flush_rewrite_rules();
});

if (is_admin()) {
    add_action('admin_init', function(): void {
        require_once VIS_PATH . 'class-vis-schema.php';
        if (get_option('vis_db_version') !== VIS_Schema::SCHEMA_VERSION
            || get_option('vis_xdr_schema_version') !== VIS_Schema::XDR_SCHEMA_VERSION
            || get_option('vis_download_schema_version') !== VIS_Schema::DOWNLOAD_SCHEMA_VERSION) {
            VIS_Schema::enforce();
        }
        require_once VIS_PATH . 'class-vis-vault.php';
        \VisionGaia\GeDefense\Core\Vault::auto_migrate_config();
    });
}

if (did_action('plugins_loaded')) {
    $vis_global_config = get_option('vis_config', []);
    if (!is_array($vis_global_config)) {
        $vis_global_config = [];
    }
    \VisionGaia\GeDefense\Core\Bootstrapper::engage_phase_2($vis_global_config);
} else {
    add_action('plugins_loaded', function(): void {
        $vis_global_config = get_option('vis_config', []);
        if (!is_array($vis_global_config)) {
            $vis_global_config = [];
        }
        \VisionGaia\GeDefense\Core\Bootstrapper::engage_phase_2($vis_global_config);
    }, 10);
}

// AJAX/API Guard: Vault is required for secured AJAX secrets. Gorgon routes are mounted by the module only.
$is_vgt_action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) && strpos($_REQUEST['action'], 'vgt_') === 0;
if (wp_doing_ajax() || $is_vgt_action) {
    require_once VIS_PATH . 'class-vis-vault.php';
}

// --- 4. IMMEDIATE PHASE 1 ENGAGEMENT (PERIMETER LOCKDOWN) ---
$vis_global_config = get_option('vis_config', []);
if (!is_array($vis_global_config)) {
    $vis_global_config = [];
}
\VisionGaia\GeDefense\Core\Bootstrapper::engage_phase_1($vis_global_config);
