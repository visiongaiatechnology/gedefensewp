<?php
/**
 * Plugin Name: GeDefense WP
 * Description: OMEGA-CLASS Security Suite. High-Performance Integrity Monitoring & Active Defense Matrix.
 * Version: 7.5.2 OMEGA ASSURANCE UI
 * Author: VisionGaiaTechnology
 * Author URI: https://visiongaiatechnology.de
 * License: GNU General Public License v3.0 (or AGPLv3)
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * --- PROPRIETARY LICENSE INFORMATION ---
 * (c) 2024-2026 VisionGaiaTechnology. All Rights Reserved.
 * This software is a proprietary product of VisionGaiaTechnology.
 * Any redistribution, modification, or commercial resale of this code
 * without explicit written permission is strictly prohibited.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

// --- 1. DOUBLE-LOAD PROTECTION & CONSTANTS ---
if (defined('VIS_VERSION')) {
    return;
}

define('VIS_VERSION', '7.5.2');
define('VIS_MANIFEST_DIGEST', '20702ddd216c1dcd0dc40f1847b558b93187b4c8d61c14955dfc9262c3fed94e');
define('VIS_PRODUCT_NAME', 'GeDefense WP');
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
if (class_exists('VIS_Event_Bus')) {
    VIS_Event_Bus::init();
}

// --- 3. ZERO-OVERHEAD HOOK MATRIX (STANDARD PLUGIN CONTEXT) ---
register_activation_hook(__FILE__, function(): void {
    require_once VIS_PATH . 'class-vis-schema.php';
    VIS_Schema::enforce();
});

register_deactivation_hook(__FILE__, function(): void {
    wp_clear_scheduled_hook('vis_hourly_scan_event');
    flush_rewrite_rules();
});

if (is_admin()) {
    add_action('admin_init', function(): void {
        if (get_option('vis_db_version') !== VIS_VERSION) {
            require_once VIS_PATH . 'class-vis-schema.php';
            VIS_Schema::enforce();
        }
        require_once VIS_PATH . 'class-vis-vault.php';
        VIS_Vault::auto_migrate_config();
    });
}

if (did_action('plugins_loaded')) {
    $vis_global_config = get_option('vis_config', []);
    if (!is_array($vis_global_config)) {
        $vis_global_config = [];
    }
    VIS_Bootstrapper::engage_phase_2($vis_global_config);
} else {
    add_action('plugins_loaded', function(): void {
        $vis_global_config = get_option('vis_config', []);
        if (!is_array($vis_global_config)) {
            $vis_global_config = [];
        }
        VIS_Bootstrapper::engage_phase_2($vis_global_config);
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
VIS_Bootstrapper::engage_phase_1($vis_global_config);
