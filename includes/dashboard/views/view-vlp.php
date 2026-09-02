<?php
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * View: VISION LEGAL PRO (Full Asset Matrix)
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

// =========================================================================================
// 1. CRASH PREVENTION & DEPENDENCY CHECK (STRICT 1:1)
// =========================================================================================
if (!class_exists('VLP_Asset_Library') || !class_exists('VLP_Service_Definitions')) {
    $vis_opt = get_option('vis_config', []);
    $is_deactivated = isset($vis_opt['module_vlp_enabled']) && empty($vis_opt['module_vlp_enabled']);
    echo '<div class="vgt-glass-panel" style="padding:40px; text-align:center; color:var(--vgt-text-dim);">';
    if ($is_deactivated) {
        echo '<h3 style="color:#fff; margin-top:0;">' . esc_html__('Vision Legal Pro Modul Deaktiviert', 'vgt-sentinel') . '</h3>';
        echo '<p>' . esc_html__('Dieses Modul wurde in den Systemeinstellungen deaktiviert. Sie können es in der Modul-Verwaltung reaktivieren.', 'vgt-sentinel') . '</p>';
        echo '<a href="?page=vgt-suite&tab=modules" class="vgt-btn vgt-btn-primary" style="margin-top:15px; display:inline-block;">' . esc_html__('Zur Modul-Verwaltung', 'vgt-sentinel') . '</a>';
    } else {
        echo '<h3 style="color:#ef4444; margin-top:0;">' . esc_html__('CRITICAL: VLP CORE NOT LOADED', 'vgt-sentinel') . '</h3>';
        echo '<p>' . esc_html__('System-Integrität kompromittiert. Bitte prüfen Sie die Integration von vision-legal-pro.php.', 'vgt-sentinel') . '</p>';
    }
    echo '</div>';
    return;
}

// =========================================================================================
// 2. DATA FETCH (SAFELY - STRICT 1:1)
// =========================================================================================
$services = method_exists('VLP_Service_Definitions', 'get_services') ? VLP_Service_Definitions::get_services() : [];
$assets   = method_exists('VLP_Asset_Library', 'get_matrix') ? VLP_Asset_Library::get_matrix() : [];

$asset_total = count($assets);
$asset_found = 0;

$upload_dir = defined('VLP_UPLOAD_DIR') ? VLP_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/vgt-shadow-net/assets';

foreach($assets as $r => $l) { 
    if(file_exists($upload_dir . '/' . $l)) $asset_found++; 
}
$asset_missing = $asset_total - $asset_found;

$status_color_hex = ($asset_missing === 0 && $asset_total > 0) ? '#10b981' : '#f59e0b';
if ($asset_total === 0) $status_color_hex = '#64748b';

$vlp_config = get_option('vis_vlp_config', []);
$shadow_net_active = isset($vlp_config['shadow_net_enabled']) && $vlp_config['shadow_net_enabled'];
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<!-- =========================================================================================
     VIEW CONTENT
     ========================================================================================= -->
<div class="vgt-apex-ui">

    <div class="vgt-glass-panel" style="margin-bottom: 24px;">
        <div class="vgt-module-header">
            <div class="vgt-module-title">
                <div style="background:rgba(59, 130, 246, 0.1); padding:10px; border-radius:8px; border:1px solid rgba(59, 130, 246, 0.3); display: flex;">
                    <svg class="vgt-icon" style="color:var(--vgt-neon-blue); width:24px; height:24px;" viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <path d="M12 8v4"></path>
                        <path d="M12 16h.01"></path>
                    </svg>
                </div>
                <div>
                    <h2>
                        <?php esc_html_e('VISION LEGAL PRO', 'vgt-sentinel'); ?> 
                        <span class="vgt-badge vgt-badge-active" style="border-radius:4px;"><?php esc_html_e('GDPR / DSGVO CORE', 'vgt-sentinel'); ?></span>
                    </h2>
                    <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace;">
                        <?php esc_html_e('System Status:', 'vgt-sentinel'); ?> 
                        <span style="color:<?php echo esc_attr($shadow_net_active ? 'var(--vgt-neon-green)' : 'var(--vgt-text-muted)'); ?>;">
                            <?php echo $shadow_net_active ? esc_html__('SHADOWNET ACTIVE', 'vgt-sentinel') : esc_html__('SHADOWNET STANDBY', 'vgt-sentinel'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="<?php echo esc_attr($shadow_net_active ? 'vgt-is-active' : 'vgt-is-standby'); ?>" style="display:flex; align-items:center; gap:8px;">
                <span class="vgt-status-pulse"></span>
                <span style="font-size:10px; font-weight:700; letter-spacing:1px; color:var(--vgt-text-muted); text-transform:uppercase;"><?php esc_html_e('Network Sync', 'vgt-sentinel'); ?></span>
            </div>
        </div>
    </div>

    <!-- BENTO GRID (KPI) -->
    <div class="vgt-bento-grid">
        
        <!-- SHADOW NET STATUS -->
        <div class="vgt-glass-panel vgt-kpi-card" style="border-top: 3px solid <?php echo esc_attr($status_color_hex); ?>; margin-bottom: 0;">
            <div>
                <div class="vgt-kpi-header">
                    <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path></svg> <?php esc_html_e('SHADOW NET ASSETS', 'vgt-sentinel'); ?>
                </div>
                <div class="vgt-kpi-value" style="color:<?php echo esc_attr($status_color_hex); ?>;">
                    <?php echo esc_html(number_format_i18n($asset_found)); ?> 
                    <span class="vgt-kpi-sub">/ <?php echo esc_html(number_format_i18n($asset_total)); ?></span>
                </div>
                <div class="vgt-kpi-desc">
                    <?php 
                    if ($asset_missing === 0) {
                        esc_html_e('Alle externen Ressourcen sind lokal gespiegelt und gehärtet. Zero-Leakage verifiziert.', 'vgt-sentinel');
                    } else {
                        printf(
                            wp_kses_post(
                                _n('<span style="color:var(--vgt-neon-orange); font-weight:700;">%d Asset fehlt</span> im lokalen Storage. Synchronisation empfohlen.', '<span style="color:var(--vgt-neon-orange); font-weight:700;">%d Assets fehlen</span> im lokalen Storage. Synchronisation empfohlen.', $asset_missing, 'vgt-sentinel')
                            ),
                            (int)$asset_missing
                        );
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- PRIVACY SHIELD STATUS -->
        <div class="vgt-glass-panel vgt-kpi-card" style="border-top: 3px solid var(--vgt-neon-blue); margin-bottom: 0;">
            <div>
                <div class="vgt-kpi-header">
                    <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> <?php esc_html_e('PRIVACY VECTORS', 'vgt-sentinel'); ?>
                </div>
                <div class="vgt-kpi-value" style="color:var(--vgt-neon-blue);">
                    <?php echo esc_html(number_format_i18n(count($services))); ?>
                </div>
                <div class="vgt-kpi-desc">
                    <?php esc_html_e('Aktive Heuristik-Überwachung etabliert.', 'vgt-sentinel'); ?><br>
                    <?php esc_html_e('Intercept-Rules für Google Fonts, Maps, YouTube und Analytics injiziert.', 'vgt-sentinel'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SHADOW NET CONTROLS (ASSET MATRIX) -->
    <div class="vgt-glass-panel vgt-table-container">
        <div class="vgt-table-header">
            <h3><svg class="vgt-icon" style="width:18px; height:18px;" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> <?php esc_html_e('SHADOW NET ASSET MATRIX', 'vgt-sentinel'); ?></h3>
            
            <?php if($asset_missing > 0): ?>
            <button type="button" id="vlp-batch-trigger" class="vgt-btn vgt-btn-neon">
                <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                <?php printf(esc_html__('AUTO-FIX: DOWNLOAD ALL (%d)', 'vgt-sentinel'), (int)$asset_missing); ?>
            </button>
            <?php else: ?>
            <span class="vgt-badge vgt-badge-active"><svg class="vgt-icon" style="width:12px; height:12px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> <?php esc_html_e('ALL SYSTEMS SYNCED', 'vgt-sentinel'); ?></span>
            <?php endif; ?>
        </div>

        <table class="vgt-data-table">
            <thead>
                <tr>
                    <th width="45%"><?php esc_html_e('REMOTE SOURCE (UPLINK)', 'vgt-sentinel'); ?></th>
                    <th width="40%"><?php esc_html_e('LOCAL TARGET (VAULT)', 'vgt-sentinel'); ?></th>
                    <th width="15%" style="text-align:right;"><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($assets as $remote => $local): 
                    $exists = file_exists($upload_dir . '/' . $local);
                    $remote_display = strlen((string)$remote) > 65 ? substr((string)$remote, 0, 65) . '...' : (string)$remote;
                ?>
                <tr data-url="<?php echo esc_url((string)$remote); ?>" data-file="<?php echo esc_attr((string)$local); ?>">
                    <td class="vgt-text-mono" style="color:var(--vgt-text-dim);" title="<?php echo esc_url((string)$remote); ?>">
                        <svg class="vgt-icon" style="width:14px; height:14px; color:var(--vgt-text-muted); margin-right:6px;" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                        <?php echo esc_html($remote_display); ?>
                    </td>
                    <td class="vgt-text-mono" style="color:var(--vgt-neon-blue);">
                        <?php echo esc_html((string)$local); ?>
                    </td>
                    <td style="text-align:right;">
                        <?php if($exists): ?>
                            <span class="vgt-badge vgt-badge-active"><?php esc_html_e('SECURE', 'vgt-sentinel'); ?></span>
                        <?php else: ?>
                            <button type="button" class="vgt-btn vgt-btn-ghost vlp-download-btn"><?php esc_html_e('DOWNLOAD', 'vgt-sentinel'); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PRIVACY SHIELD MATRIX -->
    <div class="vgt-glass-panel vgt-table-container">
        <div class="vgt-table-header">
            <h3><svg class="vgt-icon" style="width:18px; height:18px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> <?php esc_html_e('PRIVACY SHIELD RULES', 'vgt-sentinel'); ?></h3>
        </div>
        <table class="vgt-data-table">
            <thead>
                <tr>
                    <th width="35%"><?php esc_html_e('SERVICE DEFINITION', 'vgt-sentinel'); ?></th>
                    <th width="15%"><?php esc_html_e('CATEGORY', 'vgt-sentinel'); ?></th>
                    <th width="35%"><?php esc_html_e('DETECTION PATTERN', 'vgt-sentinel'); ?></th>
                    <th width="15%" style="text-align:right;"><?php esc_html_e('DEFAULT ACTION', 'vgt-sentinel'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($services as $key => $s): 
                    $name = $s['name'] ?? $key;
                    $desc = $s['desc'] ?? '';
                    $cat  = $s['cat'] ?? 'generic';
                    $pattern = $s['pattern'] ?? '';
                    $status = $s['status'] ?? 'unknown';
                    
                    $badge_cls = ($status === 'secure') ? 'vgt-badge-active' : 'vgt-badge-alert';
                ?>
                <tr>
                    <td>
                        <strong style="color:#fff; display:block; margin-bottom:4px; font-size:13px;"><?php echo esc_html((string)$name); ?></strong>
                        <span style="color:var(--vgt-text-dim); font-size:11px;"><?php echo esc_html((string)$desc); ?></span>
                    </td>
                    <td>
                        <span class="vgt-badge vgt-badge-neutral"><?php echo esc_html(strtoupper((string)$cat)); ?></span>
                    </td>
                    <td>
                        <code class="vgt-text-mono" style="background:rgba(0,0,0,0.3); padding:4px 8px; border-radius:4px; color:var(--vgt-neon-purple); border:1px solid rgba(168,85,247,0.2);">
                            <?php echo esc_html((string)$pattern); ?>
                        </code>
                    </td>
                    <td style="text-align:right;">
                        <span class="vgt-badge <?php echo esc_attr($badge_cls); ?>"><?php echo esc_html(strtoupper((string)$status)); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

