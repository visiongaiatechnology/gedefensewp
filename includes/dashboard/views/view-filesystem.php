<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: FILESYSTEM
 * MODULE: SECURE FILE SYSTEM PERMISSION AUDIT
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. LOGIK CORE (STRICT 1:1)
// =========================================================================================
$guard = new VIS_Filesystem_Guard();
$files = $guard->scan_permissions();

// OMEGA LOGIC: Issue Counter for Header Pulse
$issues_count = 0;
foreach($files as $f) {
    if ($f['status'] !== 'secure') {
        $issues_count++;
    }
}

// Header Pulse State
$pulse_class = ($issues_count === 0) ? 'vgt-is-active' : 'vgt-is-alert';
$pulse_color = ($issues_count === 0) ? '#10b981' : '#ef4444'; // Neon Green or Red

if ($issues_count === 0) {
    $pulse_text = __('ALL PERMISSIONS SECURE', 'vgt-sentinel');
} else {
    $pulse_text = sprintf(
        /* translators: %d: number of anomalies */
        _n('%d ANOMALY DETECTED', '%d ANOMALIES DETECTED', $issues_count, 'vgt-sentinel'),
        $issues_count
    );
}
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<div class="vgt-apex-ui">

    <div class="vgt-glass-panel" style="border-top: 3px solid var(--vgt-neon-blue);">
        
        <!-- MODULE HEADER -->
        <div class="vgt-module-header">
            <div class="vgt-module-title">
                <div style="background:rgba(59, 130, 246, 0.1); padding:10px; border-radius:8px; border:1px solid rgba(59, 130, 246, 0.3); display: flex;">
                    <svg class="vgt-icon" style="color:var(--vgt-neon-blue); width:24px; height:24px;" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                </div>
                <div>
                    <h2><?php esc_html_e('DATEISYSTEM SICHERHEIT', 'vgt-sentinel'); ?></h2>
                    <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace; display:flex; align-items:center; gap:8px;">
                        <?php esc_html_e('Permission Audit:', 'vgt-sentinel'); ?>
                        <span class="<?php echo esc_attr($pulse_class); ?>" style="display:inline-flex; align-items:center; gap:6px;">
                            <span class="vgt-status-pulse"></span>
                            <strong style="color:<?php echo esc_attr($pulse_color); ?>; letter-spacing:0.5px;">
                                <?php echo esc_html($pulse_text); ?>
                            </strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- INFO BANNERS -->
        <div class="vgt-alert-banner">
            <svg class="vgt-icon" style="color:var(--vgt-neon-blue); width:22px; height:22px; flex-shrink:0;" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <div>
                <p><strong><?php esc_html_e('CHMOD KONTROLLE:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Dieses Modul prüft kritische WordPress-Dateien auf korrekte Datei- und Ordnerrechte (Linux/Unix Standard). Fehlerhafte Berechtigungen (z.B. 0777) sind ein massives Sicherheitsrisiko.', 'vgt-sentinel'); ?></p>
                <p style="margin-top:4px; font-size:12px;"><strong><?php esc_html_e('Empfehlung:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Ordner auf 0755, reguläre Dateien auf 0644, und die wp-config.php zwingend auf 0600 setzen.', 'vgt-sentinel'); ?></p>
            </div>
        </div>

        <!-- FILESYSTEM MATRIX -->
        <div class="vgt-table-container">
            <table class="vgt-data-table">
                <thead>
                    <tr>
                        <th width="25%"><?php esc_html_e('DATEI / ORDNER', 'vgt-sentinel'); ?></th>
                        <th width="35%"><?php esc_html_e('PFAD (ABSOLUT)', 'vgt-sentinel'); ?></th>
                        <th width="10%"><?php esc_html_e('AKTUELL', 'vgt-sentinel'); ?></th>
                        <th width="10%"><?php esc_html_e('SOLL', 'vgt-sentinel'); ?></th>
                        <th width="20%" style="text-align:right;"><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($files as $f): 
                    $text_color = 'var(--vgt-text-muted)';
                    $val_color  = 'var(--vgt-neon-green)';
                    $badge      = 'vgt-badge-active';
                    $status_msg = __('SECURE', 'vgt-sentinel');
                    
                    if ($f['status'] === 'warning') {
                        $text_color = 'var(--vgt-text-dim)';
                        $val_color  = 'var(--vgt-neon-red)';
                        $badge      = 'vgt-badge-alert';
                        $status_msg = $f['msg'];
                    } elseif ($f['status'] === 'missing') {
                        $text_color = 'var(--vgt-text-dim)';
                        $val_color  = 'var(--vgt-neon-orange)';
                        $badge      = 'vgt-badge-warning';
                        $status_msg = $f['msg'];
                    }
                ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <svg class="vgt-icon" style="width:16px; height:16px; color:<?php echo esc_attr($val_color); ?>;" viewBox="0 0 24 24">
                                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline>
                                </svg>
                                <strong style="color:#fff; font-size:13px; letter-spacing:0.5px;"><?php echo esc_html($f['label']); ?></strong>
                            </div>
                        </td>
                        <td class="vgt-text-mono" style="color:var(--vgt-text-dim); word-break:break-all;">
                            <?php echo esc_html($f['path']); ?>
                        </td>
                        <td class="vgt-text-mono" style="color:<?php echo esc_attr($val_color); ?>; font-weight:bold; font-size:13px;">
                            <?php echo esc_html($f['perms']); ?>
                        </td>
                        <td class="vgt-text-mono" style="color:var(--vgt-text-muted);">
                            <?php echo esc_html($f['rec']); ?>
                        </td>
                        <td style="text-align:right;">
                            <span class="vgt-badge <?php echo esc_attr($badge); ?>">
                                <?php echo esc_html($status_msg); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- FOOTER ALERT -->
        <?php if($issues_count > 0): ?>
        <div class="vgt-alert-banner vgt-alert-warning" style="margin-top:0;">
            <svg class="vgt-icon" style="color:var(--vgt-neon-orange); width:20px; height:20px; flex-shrink:0;" viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <p><strong><?php esc_html_e('EINGRIFF ERFORDERLICH:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Wenn Rechte als "Warning" markiert sind, ändern Sie diese bitte manuell über Ihren FTP-Client (z.B. FileZilla) oder Ihr Hosting-Panel. Die VGT Engine greift aus Stabilitätsgründen nicht direkt in die Dateirechte ein.', 'vgt-sentinel'); ?></p>
        </div>
        <?php endif; ?>

    </div>
</div>
