<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: INTEGRITY
 * MODULE: SYSTEM INTEGRITY MONITOR (FILE HASHING ENGINE)
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. REPORT DATA FETCH (STRICT 1:1)
// =========================================================================================
wp_cache_delete('vis_scan_report', 'options');
wp_cache_delete('alloptions', 'options');
$report = get_option('vis_scan_report', false);
$has_report = !empty($report) && is_array($report);
$status = $has_report ? $report['status'] : 'unknown';
$changes = $has_report ? $report['changes'] : [];
$last_scan = $has_report ? $report['timestamp'] : __('Never', 'vgt-sentinel');

// CONSOLIDATE DUPLICATE ANOMALIES PER FILE
if (!empty($changes) && is_array($changes)) {
    $consolidated = [];
    foreach ($changes as $change) {
        if (!is_array($change)) continue;
        $file = (string)($change['file'] ?? '');
        if ($file === '') continue;
        if (!isset($consolidated[$file])) {
            $consolidated[$file] = $change;
        } else {
            $newType = strtoupper((string)($change['type'] ?? ''));
            if ($newType === 'MALWARE' || $newType === 'QUARANTINED') {
                $consolidated[$file]['type'] = $newType;
            }
            $consolidated[$file]['risk'] = max((int)($consolidated[$file]['risk'] ?? 0), (int)($change['risk'] ?? 0));
            $consolidated[$file]['confidence'] = max((int)($consolidated[$file]['confidence'] ?? 0), (int)($change['confidence'] ?? 0));
            $oldDesc = (string)($consolidated[$file]['desc'] ?? '');
            $newDesc = (string)($change['desc'] ?? '');
            if (!str_contains($oldDesc, $newDesc)) {
                $consolidated[$file]['desc'] = $oldDesc . '; ' . $newDesc;
            }
        }
    }
    $changes = array_values($consolidated);
}

// COLORS & SVG PATHS (Adapted for VGT APEX)
$status_color = '#64748b'; 
$status_icon_svg = '<line x1="5" y1="12" x2="19" y2="12"></line>'; // Default: Minus
$status_pulse_class = 'vgt-is-standby';

if ($status === 'clean' || $status === 'init') {
    $status_color = '#10b981'; 
    $status_icon_svg = '<polyline points="20 6 9 17 4 12"></polyline>'; // Check
    $status_pulse_class = 'vgt-is-active';
} elseif ($status === 'warning') {
    $status_color = '#ef4444'; 
    $status_icon_svg = '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>';
    $status_pulse_class = 'vgt-is-alert';
}
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<!-- =========================================================================================
     3. VIEW CONTENT
     ========================================================================================= -->
<div class="vgt-apex-ui" id="vgt-integrity-view" data-inspect-nonce="<?php echo esc_attr(wp_create_nonce('vis_nonce')); ?>">

    <!-- MODULE HEADER -->
    <div class="vgt-glass-panel vgt-module-header" style="border-left: 4px solid <?php echo esc_attr($status_color); ?>;">
        <div class="vgt-module-title">
            <div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); display: flex;">
                <svg class="vgt-icon" style="color:<?php echo esc_attr($status_color); ?>; width:24px; height:24px;" viewBox="0 0 24 24">
                    <?php echo wp_kses_post($status_icon_svg); // Dynamic Icon but internal logic 1:1 ?>
                </svg>
            </div>
            <div>
                <h2>
                    <?php esc_html_e('SYSTEM INTEGRITY MONITOR', 'vgt-sentinel'); ?>
                    <?php if($status === 'warning'): ?>
                        <span class="vgt-badge vgt-badge-alert" style="border-radius:4px;"><?php esc_html_e('BREACH DETECTED', 'vgt-sentinel'); ?></span>
                    <?php else: ?>
                        <span class="vgt-badge vgt-badge-neutral" style="border-radius:4px;"><?php esc_html_e('FILE HASHING ENGINE', 'vgt-sentinel'); ?></span>
                    <?php endif; ?>
                </h2>
                <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace;">
                    <?php esc_html_e('Last Deep Scan:', 'vgt-sentinel'); ?> 
                    <span style="color:#fff;"><?php echo esc_html($last_scan); ?></span>
                    <span style="margin: 0 8px; color:var(--vgt-text-muted);">|</span>
                    <span class="<?php echo esc_attr($status_pulse_class); ?>" style="display:inline-flex; align-items:center; gap:6px;">
                        <span class="vgt-status-pulse"></span>
                        <span style="color:<?php echo esc_attr($status_color); ?>;"><?php echo esc_html(strtoupper($status)); ?></span>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="vgt-integrity-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(VIS_Sentinel_Export::action()); ?>">
                <?php wp_nonce_field(VIS_Sentinel_Export::nonce_action()); ?>
                <button type="submit" class="vgt-btn vgt-btn-ghost">
                    <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M12 3v12"></path><polyline points="7 10 12 15 17 10"></polyline><path d="M5 21h14"></path></svg>
                    <?php esc_html_e('EXPORT ANALYSE-DATEN', 'vgt-sentinel'); ?>
                </button>
            </form>
            <button type="button" id="vis-btn-scan" class="vgt-btn vgt-btn-neon vis-btn-scan" data-mode="scan">
                <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <?php esc_html_e('RUN DEEP SCAN', 'vgt-sentinel'); ?>
            </button>
        </div>
    </div>

    <!-- STATE PANELS -->
    <?php if(!$has_report): ?>
        <!-- EMPTY STATE -->
        <div class="vgt-glass-panel vgt-state-clean" style="border-color:var(--vgt-border);">
            <svg class="vgt-icon" style="width:64px; height:64px; color:var(--vgt-text-muted); margin-bottom:20px; opacity:0.5;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <h3><?php esc_html_e('AWAITING INITIALIZATION', 'vgt-sentinel'); ?></h3>
            <p><?php esc_html_e('Kein Integritäts-Bericht im System verzeichnet. Bitte starten Sie einen manuellen Baseline-Scan, um das Hashing-Netzwerk zu aktivieren.', 'vgt-sentinel'); ?></p>
        </div>

    <?php elseif($status === 'clean' || $status === 'init'): ?>
        <!-- SECURE STATE -->
        <div class="vgt-glass-panel vgt-state-clean" style="border-top:3px solid var(--vgt-neon-green);">
            <svg class="vgt-icon vgt-state-clean-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <h3><?php esc_html_e('SYSTEM SECURE', 'vgt-sentinel'); ?></h3>
            <p><?php esc_html_e('Alle überwachten Dateien stimmen exakt mit dem kryptographischen Manifest überein. Es wurden keine nicht-autorisierten Modifikationen (Zero-Day/Malware) im Dateisystem festgestellt.', 'vgt-sentinel'); ?></p>
        </div>

    <?php else: ?>
        <!-- WARNING / ANOMALY STATE -->
        <div class="vgt-glass-panel vgt-table-container" style="border: 1px solid rgba(239, 68, 68, 0.4); box-shadow: 0 0 30px rgba(239, 68, 68, 0.1);">
            
                        <div class="vgt-state-alert-header">
                <div style="display:flex; align-items:center; gap:12px; color:var(--vgt-neon-red);">
                    <svg class="vgt-icon" style="width:24px; height:24px; animation: vgt-pulse-alert 1.5s infinite;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <div>
                        <strong style="font-size:16px; letter-spacing:1px; display:block;"><?php esc_html_e('CRITICAL ANOMALIES DETECTED', 'vgt-sentinel'); ?></strong>
                        <span style="font-size:12px; font-family:monospace; color:var(--vgt-text-dim);">
                            <?php 
                            printf(
                                esc_html(
                                    _n('%d Datei verstößt gegen die System-Baseline.', '%d Dateien verstoßen gegen die System-Baseline.', count($changes), 'vgt-sentinel')
                                ),
                                (int)count($changes)
                            ); 
                            ?>
                        </span>
                    </div>
                </div>
                
                <button type="button" id="vis-btn-approve" class="vgt-btn vgt-btn-danger" data-mode="reindex">
                    <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <?php esc_html_e('BASELINE UPDATEN (APPROVE)', 'vgt-sentinel'); ?>
                </button>
            </div>

            <?php
            $count_all = count($changes);
            $count_new = 0;
            $count_modified = 0;
            $count_deleted = 0;
            $count_malware = 0;

            foreach ($changes as $c) {
                $t = strtoupper((string)($c['type'] ?? ''));
                if ($t === 'NEW') {
                    $count_new++;
                } elseif ($t === 'MODIFIED') {
                    $count_modified++;
                } elseif ($t === 'DELETED' || $t === 'UNAVAILABLE') {
                    $count_deleted++;
                } elseif ($t === 'MALWARE' || $t === 'QUARANTINED' || !empty($c['risk'])) {
                    $count_malware++;
                } else {
                    $count_modified++;
                }
            }
            ?>

            <!-- DIRECT ANOMALY CATEGORY TABS -->
            <div class="vgt-anomaly-tabs-bar">
                <button type="button" class="vgt-tab-btn vgt-tab-active" data-filter="all">
                    <svg class="vgt-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span><?php esc_html_e('ALLE ANOMALIEN', 'vgt-sentinel'); ?></span>
                    <span class="vgt-tab-count"><?php echo (int)$count_all; ?></span>
                </button>
                <button type="button" class="vgt-tab-btn" data-filter="new">
                    <svg class="vgt-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    <span><?php esc_html_e('NEU', 'vgt-sentinel'); ?></span>
                    <span class="vgt-tab-count <?php echo $count_new > 0 ? 'vgt-count-new' : ''; ?>"><?php echo (int)$count_new; ?></span>
                </button>
                <button type="button" class="vgt-tab-btn" data-filter="modified">
                    <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    <span><?php esc_html_e('MODIFIZIERT', 'vgt-sentinel'); ?></span>
                    <span class="vgt-tab-count <?php echo $count_modified > 0 ? 'vgt-count-mod' : ''; ?>"><?php echo (int)$count_modified; ?></span>
                </button>
                <button type="button" class="vgt-tab-btn" data-filter="deleted">
                    <svg class="vgt-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    <span><?php esc_html_e('GELÖSCHT', 'vgt-sentinel'); ?></span>
                    <span class="vgt-tab-count <?php echo $count_deleted > 0 ? 'vgt-count-del' : ''; ?>"><?php echo (int)$count_deleted; ?></span>
                </button>
                <button type="button" class="vgt-tab-btn" data-filter="malware">
                    <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <span><?php esc_html_e('MALWARE', 'vgt-sentinel'); ?></span>
                    <span class="vgt-tab-count <?php echo $count_malware > 0 ? 'vgt-count-alert' : ''; ?>"><?php echo (int)$count_malware; ?></span>
                </button>
            </div>

            <table class="vgt-data-table" id="vgt-integrity-table">
                <thead>
                    <tr>
                        <th width="12%"><?php esc_html_e('TYPE', 'vgt-sentinel'); ?></th>
                        <th width="43%"><?php esc_html_e('DATEIPFAD (TARGET)', 'vgt-sentinel'); ?></th>
                        <th width="30%"><?php esc_html_e('DETAILS', 'vgt-sentinel'); ?></th>
                        <th width="15%" style="text-align:right;"><?php esc_html_e('ACTION', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($changes as $change): 
                    $type = strtoupper((string)($change['type'] ?? 'MODIFIED'));
                    $badge_class = 'vgt-badge-alert'; 
                    $category = 'modified';
                    
                    if ($type === 'NEW') {
                        $badge_class = 'vgt-badge-active';
                        $category = 'new';
                    } elseif ($type === 'MODIFIED') {
                        $badge_class = 'vgt-badge-warning';
                        $category = 'modified';
                    } elseif ($type === 'DELETED' || $type === 'UNAVAILABLE') {
                        $badge_class = 'vgt-badge-alert';
                        $category = 'deleted';
                    } elseif ($type === 'MALWARE' || $type === 'QUARANTINED' || !empty($change['risk'])) {
                        $badge_class = 'vgt-badge-alert';
                        $category = 'malware';
                    }
                    
                    $file_rel_path = ltrim((string)($change['file'] ?? ''), '/');
                ?>
                    <tr class="vgt-anomaly-row" data-category="<?php echo esc_attr($category); ?>">
                        <td><span class="vgt-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($type); ?></span></td>
                        <td class="vgt-text-mono" style="color:#fff; word-break:break-all;">
                            <?php echo esc_html((string)($change['file'] ?? '')); ?>
                        </td>
                        <td style="color:var(--vgt-text-dim); font-size:12px;">
                            <?php echo esc_html((string)($change['desc'] ?? '')); ?>
                            <?php if (!empty($change['risk'])): ?>
                                <span style="display:block; font-size:10px; color:var(--vgt-neon-red); margin-top:2px;">
                                    <?php printf(esc_html__('Risiko: %d%% | Konfidenz: %d%%', 'vgt-sentinel'), (int)$change['risk'], (int)($change['confidence'] ?? 0)); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:inline-flex; gap:8px;">
                                <button type="button" class="vgt-btn vgt-btn-ghost vis-inspect-file" data-file="<?php echo esc_attr((string)($change['file'] ?? '')); ?>" style="padding:6px 10px; color:var(--vgt-text-main); border-color:var(--vgt-border);">
                                    <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    <?php esc_html_e('VIEW', 'vgt-sentinel'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="vgt-tab-empty-notice" class="vgt-tab-empty-row" style="display: none;">
                        <td colspan="4">
                            <svg class="vgt-icon" style="width:28px; height:28px; margin-bottom:8px; opacity:0.4;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <div><?php esc_html_e('Keine Anomalien in dieser Kategorie verzeichnet.', 'vgt-sentinel'); ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- VGT SECURE CODE VIEWER MODAL -->
    <div id="vis-source-modal" class="vis-modal-backdrop" style="display: none;">
        <div class="vis-modal-content" style="max-width: 900px; width: 90%;">
            <div class="vis-modal-header" style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(5,5,5,0.4);">
                <div class="vis-modal-title" id="vis-source-title" style="font-family: 'Orbitron', monospace; font-size: 14px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-editor-code" style="color:var(--vgt-neon-green, #10b981);"></span>
                    <?php esc_html_e('SOURCE VIEWER', 'vgt-sentinel'); ?>
                </div>
                <button type="button" class="vis-modal-close" id="vis-source-close" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 20px;"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="vis-modal-body" style="padding: 20px; background: rgba(2, 4, 10, 0.95);">
                <pre id="vis-source-code" style="margin: 0; padding: 15px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; overflow: auto; max-height: 55vh; font-family: 'Fira Code', 'JetBrains Mono', monospace; font-size: 12px; color: #e2e8f0; line-height: 1.6; text-align: left; user-select: text; white-space: pre; word-wrap: normal;"></pre>
            </div>
            <div class="vis-modal-footer" style="padding: 15px 20px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: flex-end; background: rgba(5,5,5,0.4);">
                <button type="button" class="vgt-btn vgt-btn-ghost" id="vis-source-ok" style="padding: 8px 20px; border-color: rgba(255,255,255,0.1); color: #fff; font-family: 'Orbitron', sans-serif; font-size: 11px; letter-spacing: 1px;"><?php esc_html_e('SCHLIESSEN', 'vgt-sentinel'); ?></button>
            </div>
        </div>
    </div>

    </div>
