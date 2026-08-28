<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: HADES
 * MODULE: HADES GHOST PROTOCOL (STEALTH ENGINE)
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. LOGIK CORE (STRICT 1:1)
// =========================================================================================
$opt = get_option('vis_config', []);
$hades_active = !empty($opt['hades_enabled']);

// Hole Parameter (mit Fallbacks für frische Installationen)
$admin_param  = !empty($opt['hades_admin_param']) ? $opt['hades_admin_param'] : 'vgt_access';
$admin_secret = !empty($opt['hades_admin_secret']) ? $opt['hades_admin_secret'] : 'omega';

// Pulse & Status State
$pulse_class  = $hades_active ? 'vgt-oracle-active' : 'vgt-is-standby';
$status_color = $hades_active ? 'var(--vgt-neon-purple)' : 'var(--vgt-text-muted)';
$status_text  = $hades_active ? __('STEALTH ACTIVE', 'vgt-sentinel') : __('VISIBLE (UNPROTECTED)', 'vgt-sentinel');

// MAPPING DATA (CLOAKING VECTORS) - DYNAMIC CONFIG
$cloaking_rules = [
    'wp-content/themes'  => ['key' => 'hades_map_themes',  'default' => 'content/ui'],
    'wp-content/plugins' => ['key' => 'hades_map_plugins', 'default' => 'content/lib'],
    'wp-content/uploads' => ['key' => 'hades_map_uploads', 'default' => 'storage'],
    'wp-content'         => ['key' => 'hades_map_content', 'default' => 'content'],
    'wp-includes'        => ['key' => 'hades_map_includes', 'default' => 'core'],
    'wp-json (REST API)' => ['key' => 'hades_map_rest',     'default' => 'vgt-api'],
];

// OMEGA FILE MAP: Exakte Endpunkt-Verschleierung - DYNAMIC CONFIG
$endpoint_rules = [
    'wp-admin/admin-ajax.php' => ['key' => 'hades_map_ajax', 'default' => 'vgt-api/nexus'],
    'wp-admin/admin-post.php' => ['key' => 'hades_map_post', 'default' => 'vgt-api/post'],
];

// VGT SUPREME: NGINX DETECTION
$is_nginx = strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx') !== false;
$nginx_rules = '';
if ($is_nginx && class_exists('VIS_Hades')) {
    $nginx_rules = VIS_Hades::get_nginx_rules($opt);
}
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $hades_css_path = __DIR__ . '/hades/style.css';
    if (is_readable($hades_css_path)) {
        echo file_get_contents($hades_css_path);
    }
    ?>
</style>

<div class="vgt-apex-ui">

    <div class="vgt-glass-panel" style="border-top: 3px solid var(--vgt-neon-purple);">
        
        <!-- MODULE HEADER -->
        <div class="vgt-module-header">
            <div class="vgt-module-title">
                <div style="background:rgba(168, 85, 247, 0.1); padding:10px; border-radius:8px; border:1px solid rgba(168, 85, 247, 0.3); display: flex;">
                    <svg class="vgt-icon" style="color:var(--vgt-neon-purple); width:24px; height:24px;" viewBox="0 0 24 24">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"></path>
                    </svg>
                </div>
                <div>
                    <h2><?php esc_html_e('HADES GHOST PROTOCOL', 'vgt-sentinel'); ?></h2>
                    <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace; display:flex; align-items:center; gap:8px;">
                        <?php esc_html_e('Cloaking Engine:', 'vgt-sentinel'); ?>
                        <span class="<?php echo esc_attr($pulse_class); ?>" style="display:inline-flex; align-items:center; gap:6px;">
                            <span class="vgt-status-pulse"></span>
                            <strong style="color:<?php echo esc_attr($status_color); ?>; letter-spacing:0.5px;">
                                <?php echo esc_html($status_text); ?>
                            </strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PERMALINK WARNING BANNER -->
        <div class="vgt-alert-banner">
            <svg class="vgt-icon" style="color:var(--vgt-neon-red); width:22px; height:22px; flex-shrink:0;" viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <div>
                <p><strong><?php esc_html_e('CRITICAL ACTION REQUIRED:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Nach der Aktivierung oder Deaktivierung des Stealth Modes MÜSSEN Sie zwingend die Permalinks neu generieren (Einstellungen > Permalinks > Speichern klicken), andernfalls kommt es zu 404 Fehlern, da die .htaccess Rewrite-Regeln nicht kompiliert wurden.', 'vgt-sentinel'); ?></p>
            </div>
        </div>

        <?php if ($is_nginx && $hades_active): ?>
        <!-- NGINX DETECTION BANNER -->
        <div class="vgt-alert-banner" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3);">
            <svg class="vgt-icon" style="color:var(--vgt-neon-orange); width:22px; height:22px; flex-shrink:0;" viewBox="0 0 24 24">
                <path d="M12 2L2 22h20L12 2z"></path><line x1="12" y1="16" x2="12" y2="16"></line><line x1="12" y1="8" x2="12" y2="12"></line>
            </svg>
            <div style="width:100%;">
                <p><strong style="color:var(--vgt-neon-orange);"><?php esc_html_e('NGINX SERVER DETECTED:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Wir haben festgestellt, dass dieses System auf NGINX läuft. Die automatische .htaccess Generierung greift hier nicht. Sie müssen folgenden Code-Block manuell in Ihren NGINX server {} Block kopieren und den Server neu laden (nginx -s reload).', 'vgt-sentinel'); ?></p>
                <textarea readonly class="vgt-input" style="width:100%; height:180px; margin-top:12px; font-family:monospace; font-size:11px; white-space:pre;"><?php echo esc_textarea($nginx_rules); ?></textarea>
            </div>
        </div>
        <?php endif; ?>

        <!-- MAIN TOGGLE ROW -->
        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong style="color:var(--vgt-neon-purple);"><?php esc_html_e('ACTIVATE STEALTH MODE', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Überschreibt die Standard-URLs von WordPress und verbirgt eindeutige Pfade wie /wp-content/, /plugins/ oder /themes/ vor externen Scannern und Wappalyzer.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch">
                <input type="checkbox" name="vis_config[hades_enabled]" <?php checked(!empty($opt['hades_enabled'])); ?>>
                <span class="vgt-slider"></span>
            </label>
        </div>

        <!-- ADMIN & LOGIN CLOAKING (404 MIMICRY) -->
        <div class="vgt-setting-row" style="flex-direction: column; align-items: flex-start;">
            <div class="vgt-label-group" style="width: 100%;">
                <strong style="color:#fff;"><?php esc_html_e('ADMIN ACCESS MATRIX (404 MIMICRY)', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Isoliert /wp-admin und /wp-login.php. Ohne dieses kryptographische Parameter-Paar in der URL wird ein hartes 404 Not Found simuliert. Scans und Brute-Force-Angriffe laufen ins Leere.', 'vgt-sentinel'); ?></p>
            </div>

            <div class="vgt-input-matrix">
                <span class="vgt-input-addon"><?php esc_html_e('/wp-admin?', 'vgt-sentinel'); ?></span>
                <input type="text" class="vgt-input" name="vis_config[hades_admin_param]" value="<?php echo esc_attr($admin_param); ?>" placeholder="<?php echo esc_attr__('vgt_access', 'vgt-sentinel'); ?>" style="width: 180px;">
                <span class="vgt-input-addon">=</span>
                <input type="text" class="vgt-input" name="vis_config[hades_admin_secret]" value="<?php echo esc_attr($admin_secret); ?>" placeholder="<?php echo esc_attr__('omega', 'vgt-sentinel'); ?>" style="flex-grow: 1;">
            </div>

            <div class="vgt-route-preview">
                <svg class="vgt-icon" style="width:16px; height:16px; color: var(--vgt-neon-green);" viewBox="0 0 24 24">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span style="font-family: monospace;">
                    <strong><?php esc_html_e('Access Route:', 'vgt-sentinel'); ?></strong> 
                    <span style="color:#fff;"><?php echo esc_html(site_url('/wp-admin')); ?>?<?php echo esc_html($admin_param); ?>=<span style="color:var(--vgt-neon-purple);"><?php echo esc_html($admin_secret); ?></span></span>
                </span>
            </div>
        </div>

        <!-- CLOAKING VECTORS (URL REWRITES) -->
        <div class="vgt-section-title" style="border-top: none;">
            <svg class="vgt-icon" style="width:14px; height:14px; margin-right:8px;" viewBox="0 0 24 24">
                <polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line>
                <polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line>
                <line x1="4" y1="4" x2="9" y2="9"></line>
            </svg>
            <?php esc_html_e('ACTIVE CLOAKING VECTORS (DIRECTORY MAPPING)', 'vgt-sentinel'); ?>
        </div>
        
        <div class="vgt-table-container">
            <table class="vgt-data-table">
                <thead>
                    <tr>
                        <th width="45%"><?php esc_html_e('EXPOSED PATH (FRONTEND)', 'vgt-sentinel'); ?></th>
                        <th width="10%"></th>
                        <th width="45%"><?php esc_html_e('PHYSICAL TARGET (BACKEND)', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($cloaking_rules as $physical => $data): 
                        $val = !empty($opt[$data['key']]) ? $opt[$data['key']] : $data['default'];
                    ?>
                    <tr>
                        <td>
                            <div class="vgt-input-matrix" style="margin-top:0; padding:8px;">
                                <span class="vgt-input-addon">/</span>
                                <input type="text" class="vgt-input" name="vis_config[<?php echo esc_attr($data['key']); ?>]" value="<?php echo esc_attr($val); ?>" style="flex-grow:1;">
                                <span class="vgt-input-addon">/</span>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <svg class="vgt-icon vgt-route-arrow" style="width:16px; height:16px;" viewBox="0 0 24 24">
                                <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </td>
                        <td class="vgt-text-mono" style="color:var(--vgt-text-dim);">
                            /<?php echo esc_html($physical); ?>/
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ENDPOINT STEALTH VECTORS (FILE MAPPING) -->
        <div class="vgt-section-title" style="border-top: none;">
            <svg class="vgt-icon" style="width:14px; height:14px; margin-right:8px;" viewBox="0 0 24 24">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="M12 8v4"></path><path d="M12 16h.01"></path>
            </svg>
            <?php esc_html_e('ENDPOINT STEALTH (FILE-LEVEL CLOAKING)', 'vgt-sentinel'); ?>
        </div>
        
        <div class="vgt-table-container">
            <table class="vgt-data-table">
                <thead>
                    <tr>
                        <th width="45%"><?php esc_html_e('EXPOSED FILE (FRONTEND)', 'vgt-sentinel'); ?></th>
                        <th width="10%"></th>
                        <th width="45%"><?php esc_html_e('PHYSICAL TARGET (BACKEND)', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($endpoint_rules as $physical => $data): 
                        $val = !empty($opt[$data['key']]) ? $opt[$data['key']] : $data['default'];
                    ?>
                    <tr>
                        <td>
                            <div class="vgt-input-matrix" style="margin-top:0; padding:8px;">
                                <span class="vgt-input-addon">/</span>
                                <input type="text" class="vgt-input" name="vis_config[<?php echo esc_attr($data['key']); ?>]" value="<?php echo esc_attr($val); ?>" style="flex-grow:1;">
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <svg class="vgt-icon vgt-route-arrow" style="width:16px; height:16px;" viewBox="0 0 24 24">
                                <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </td>
                        <td class="vgt-text-mono" style="color:var(--vgt-text-dim);">
                            /<?php echo esc_html($physical); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>
