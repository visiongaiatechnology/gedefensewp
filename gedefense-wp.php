<?php
/**
 * Plugin Name: GeDefense WP - Open Core
 * Plugin URI: https://github.com/visiongaiatechnology/gedefensewp
 * Description: OMEGA-CLASS Security Suite. High-Performance Integrity Monitoring, Active Defense & RASP Matrix.
 * Version: 8.1.0
 * Author: VisionGaiaTechnology
 * Author URI: https://visiongaiatechnology.de
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
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

// --- 1. DOUBLE-LOAD PROTECTION & CONSTANTS ---
if (defined('VIS_VERSION')) {
    return;
}

define('VIS_VERSION', '8.1.0 OPEN CORE');
define('VIS_MANIFEST_DIGEST', '64d3be758103abdd185d5453bb7f8ff8712af1f1296ec6b03c3f6a6fc6f3b0a5');
define('VIS_PRODUCT_NAME', 'GeDefense WP - Open Core');
define('VIS_PATH', plugin_dir_path(__FILE__));
define('VIS_URL', plugin_dir_url(__FILE__));
define('VIS_SENTINEL_ICON', VIS_URL . 'Sentinel.png');
define('VIS_TABLE_BANS', 'vis_apex_bans');
define('VIS_TABLE_LOGS', 'vis_omega_logs');

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
});

register_deactivation_hook(__FILE__, function(): void {
    wp_clear_scheduled_hook('vis_hourly_scan_event');
    wp_clear_scheduled_hook('vis_xdr_retention_cleanup');
    wp_clear_scheduled_hook('vis_xdr_response_cleanup');
    flush_rewrite_rules();
});

if (is_admin()) {
    add_action('admin_init', function(): void {
        require_once VIS_PATH . 'class-vis-schema.php';
        if (get_option('vis_db_version') !== VIS_VERSION
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
