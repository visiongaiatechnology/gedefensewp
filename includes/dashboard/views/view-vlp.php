<?php
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * View: VISION LEGAL PRO (Full Asset Matrix)
 * STATUS: DIAMANT VGT SUPREME (Hardened UI & i18n)
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

// =========================================================================================
// 1. CRASH PREVENTION & DEPENDENCY CHECK (STRICT 1:1)
// =========================================================================================
if (!class_exists('VLP_Asset_Library') || !class_exists('VLP_Service_Definitions')) {
    $vis_opt = get_option('vis_config', []);
    $is_deactivated = isset($vis_opt['module_vlp_enabled']) && empty($vis_opt['module_vlp_enabled']);
    echo '<div class="vgt-titan-panel" style="padding:40px; text-align:center; color:var(--vis-copy-muted);">';
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

$status_color_hex = ($asset_missing === 0 && $asset_total > 0) ? '#34d399' : '#f59e0b';
if ($asset_total === 0) $status_color_hex = '#94a3b8';

$vlp_config = get_option('vis_vlp_config', []);
$shadow_net_active = isset($vlp_config['shadow_net_enabled']) && $vlp_config['shadow_net_enabled'];
?>

<section class="vgt-titan" aria-label="Vision Legal Pro GDPR Core">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('DATA SOVEREIGNTY & SHADOW-NET ASSET MIRROR (DSGVO / GDPR)', 'vgt-sentinel'); ?></p>
            <h2>VISION LEGAL PRO</h2>
            <p><?php esc_html_e('Vollständige DSGVO- und Datenschutz-Compliance. Spiegelt externe Schriftarten, Skripte und Assets im lokalen Shadow-Net-Vault und neutralisiert Third-Party Tracker (Google Fonts, YouTube, Maps, Analytics).', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="VLP Status">
            <span><small><?php esc_html_e('SHADOW-NET', 'vgt-sentinel'); ?></small><strong><?php echo $shadow_net_active ? esc_html__('ACTIVE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('LOCAL ASSETS', 'vgt-sentinel'); ?></small><strong style="color: <?php echo esc_attr($status_color_hex); ?>;"><?php echo esc_html((string)$asset_found); ?> / <?php echo esc_html((string)$asset_total); ?></strong></span>
            <span><small><?php esc_html_e('PRIVACY VECTORS', 'vgt-sentinel'); ?></small><strong style="color: var(--vis-cyan);"><?php echo esc_html((string)count($services)); ?> RULES</strong></span>
            <span><small><?php esc_html_e('DATA LEAKAGE', 'vgt-sentinel'); ?></small><strong><?php echo $asset_missing === 0 ? 'ZERO (100% DSGVO)' : esc_html((string)$asset_missing) . ' PENDING'; ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('SHADOW NET VAULT', 'vgt-sentinel'); ?></small><strong style="color: <?php echo esc_attr($status_color_hex); ?>;"><?php echo $asset_missing === 0 ? esc_html__('100% GESPIEGELT', 'vgt-sentinel') : esc_html(sprintf(__('%d AUSSTEHEND', 'vgt-sentinel'), $asset_missing)); ?></strong></article>
        <article><small><?php esc_html_e('LOKALE ASSETS', 'vgt-sentinel'); ?></small><strong style="color: var(--vis-cyan);"><?php echo esc_html((string)$asset_found); ?> STORED</strong></article>
        <article><small><?php esc_html_e('PRIVACY VEKTOREN', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)count($services)); ?> SERVICES</strong></article>
        <article><small><?php esc_html_e('TRACKER NEUTRALISIERUNG', 'vgt-sentinel'); ?></small><strong>HEURISTISCH L7</strong></article>
        <article><small><?php esc_html_e('SPEICHER-ISOLATION', 'vgt-sentinel'); ?></small><strong>LOCAL FS VAULT</strong></article>
        <article><small><?php esc_html_e('COMPLIANCE STANDARD', 'vgt-sentinel'); ?></small><strong>EU-DSGVO COMPLIANT</strong></article>
    </div>

    <!-- SECTION 1: SHADOW NET ASSET MATRIX -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('01 / SHADOW-NET REPOSITORY', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Lokale Asset-Spiegelung & Remote-Quellen', 'vgt-sentinel'); ?></h3>
            </div>
            <div>
                <?php if($asset_missing > 0): ?>
                <button type="button" id="vlp-batch-trigger" style="min-height: 32px; padding: 6px 14px; font-size: 11px; font-weight: 750; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.4); color: var(--vis-cyan); border-radius: 6px; cursor: pointer;">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    <?php printf(esc_html__('AUTO-FIX: ALLE HERUNTERLADEN (%d)', 'vgt-sentinel'), (int)$asset_missing); ?>
                </button>
                <?php else: ?>
                <span class="vgt-titan-badge" style="border-color: rgba(52, 211, 153, 0.35); color: #34d399;">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <?php esc_html_e('ALL SYSTEMS SYNCED', 'vgt-sentinel'); ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--vis-line); text-align: left;">
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 45%;"><?php esc_html_e('REMOTE QUELLE (UPLINK)', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 40%;"><?php esc_html_e('LOKALES ZIEL (VAULT)', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 15%; text-align: right;"><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($assets as $remote => $local): 
                        $exists = file_exists($upload_dir . '/' . $local);
                        $remote_display = strlen((string)$remote) > 65 ? substr((string)$remote, 0, 65) . '…' : (string)$remote;
                    ?>
                    <tr data-url="<?php echo esc_url((string)$remote); ?>" data-file="<?php echo esc_attr((string)$local); ?>" style="border-bottom: 1px solid rgba(148, 184, 224, 0.08);">
                        <td style="padding: 10px 14px; font-family: ui-monospace, monospace; font-size: 11px; color: var(--vis-copy-soft);" title="<?php echo esc_url((string)$remote); ?>">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="var(--vis-copy-muted)" stroke-width="2" style="flex-shrink: 0;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                <span><?php echo esc_html($remote_display); ?></span>
                            </div>
                        </td>
                        <td style="padding: 10px 14px; font-family: ui-monospace, monospace; font-size: 11.5px; color: var(--vis-cyan);">
                            <?php echo esc_html((string)$local); ?>
                        </td>
                        <td style="padding: 10px 14px; text-align: right;">
                            <?php if($exists): ?>
                                <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; font: 750 10px/1 ui-monospace, monospace; background: rgba(52, 211, 153, 0.12); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.32);">
                                    <?php esc_html_e('SECURE', 'vgt-sentinel'); ?>
                                </span>
                            <?php else: ?>
                                <button type="button" class="vlp-download-btn" style="min-height: 26px; padding: 3px 10px; font-size: 10px; font-weight: 750; border-radius: 6px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.32); cursor: pointer;">
                                    <?php esc_html_e('DOWNLOAD', 'vgt-sentinel'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- SECTION 2: PRIVACY SHIELD RULES -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('02 / PRIVACY FILTER MATRIX', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Datenschutz-Filterregeln & Erkennungsmuster', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php esc_html_e('HEURISTIC INTERCEPT', 'vgt-sentinel'); ?></span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--vis-line); text-align: left;">
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 35%;"><?php esc_html_e('SERVICE DEFINITION', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 15%;"><?php esc_html_e('KATEGORIE', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 35%;"><?php esc_html_e('ERKENNUNGSMUSTER', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 15%; text-align: right;"><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($services as $key => $s): 
                        $name = $s['name'] ?? $key;
                        $desc = $s['desc'] ?? '';
                        $cat  = $s['cat'] ?? 'generic';
                        $pattern = $s['pattern'] ?? '';
                        $status = $s['status'] ?? 'unknown';
                        
                        $is_sec = ($status === 'secure');
                        $badge_col = $is_sec ? '#34d399' : '#fb7185';
                    ?>
                    <tr style="border-bottom: 1px solid rgba(148, 184, 224, 0.08);">
                        <td style="padding: 12px 14px;">
                            <strong style="color: #ffffff; display: block; margin-bottom: 3px; font-size: 13px;"><?php echo esc_html((string)$name); ?></strong>
                            <span style="color: var(--vis-copy-muted); font-size: 11px; line-height: 1.4;"><?php echo esc_html((string)$desc); ?></span>
                        </td>
                        <td style="padding: 12px 14px;">
                            <span style="display: inline-flex; padding: 3px 8px; border-radius: 4px; font: 700 9.5px/1 var(--vis-font-data); letter-spacing: 0.08em; background: rgba(148, 184, 224, 0.1); border: 1px solid var(--vis-line); color: var(--vis-copy);">
                                <?php echo esc_html(strtoupper((string)$cat)); ?>
                            </span>
                        </td>
                        <td style="padding: 12px 14px;">
                            <code style="font-family: ui-monospace, monospace; font-size: 11px; background: rgba(4, 11, 24, 0.7); border: 1px solid rgba(192, 132, 252, 0.25); border-radius: 4px; padding: 3px 8px; color: #c084fc;">
                                <?php echo esc_html((string)$pattern); ?>
                            </code>
                        </td>
                        <td style="padding: 12px 14px; text-align: right;">
                            <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; font: 750 10px/1 ui-monospace, monospace; background: <?php echo esc_attr($badge_col); ?>18; color: <?php echo esc_attr($badge_col); ?>; border: 1px solid <?php echo esc_attr($badge_col); ?>40;">
                                <?php echo esc_html(strtoupper((string)$status)); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
