<?php 
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$guard = new VIS_Filesystem_Guard();
$files = $guard->scan_permissions();

// OMEGA LOGIC: Issue Counter for Header Pulse
$issues_count = 0;
foreach($files as $f) {
    if ($f['status'] !== 'secure') {
        $issues_count++;
    }
}

$is_all_secure = ($issues_count === 0);
$pulse_color = $is_all_secure ? 'var(--vis-green)' : 'var(--vis-red)';
$pulse_text  = $is_all_secure 
    ? __('ALL PERMISSIONS SECURE', 'vgt-sentinel') 
    : sprintf(_n('%d ANOMALIE ERKANNT', '%d ANOMALIEN ERKANNT', $issues_count, 'vgt-sentinel'), $issues_count);
?>

<section class="vgt-titan" aria-label="Dateisystem Sicherheit & Permission Audit">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('STORAGE SECURITY & POSIX FILE PERMISSIONS AUDIT', 'vgt-sentinel'); ?></p>
            <h2>FILESYSTEM GUARD</h2>
            <p><?php esc_html_e('Prüft kritische WordPress-Dateien und -Verzeichnisse auf korrekte POSIX-Berechtigungen (CHMOD). Erkennt Überprivilegierungen (z.B. 0777) und schützt sensible Konfigurationsdateien (wp-config.php) vor unautorisiertem Lese- und Schreibzugriff.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Filesystem Status">
            <span><small><?php esc_html_e('AUDIT ENGINE', 'vgt-sentinel'); ?></small><strong style="color: <?php echo esc_attr($pulse_color); ?>;"><?php echo esc_html($pulse_text); ?></strong></span>
            <span><small><?php esc_html_e('SCOPED TARGETS', 'vgt-sentinel'); ?></small><strong style="color: var(--vis-cyan);"><?php echo esc_html((string)count($files)); ?> NODES</strong></span>
            <span><small><?php esc_html_e('POSIX POLICY', 'vgt-sentinel'); ?></small><strong>0755 / 0644 / 0600</strong></span>
            <span><small><?php esc_html_e('AUDIT FREQUENCY', 'vgt-sentinel'); ?></small><strong>REAL-TIME</strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('PERMISSION STATUS', 'vgt-sentinel'); ?></small><strong style="color: <?php echo esc_attr($pulse_color); ?>;"><?php echo $is_all_secure ? esc_html__('SECURE (Nominal)', 'vgt-sentinel') : esc_html(sprintf(__('%d ANOMALIEN', 'vgt-sentinel'), $issues_count)); ?></strong></article>
        <article><small><?php esc_html_e('AUDITED NODES', 'vgt-sentinel'); ?></small><strong style="color: var(--vis-cyan);"><?php echo esc_html((string)count($files)); ?> FILES</strong></article>
        <article><small><?php esc_html_e('WP-CONFIG CHMOD', 'vgt-sentinel'); ?></small><strong>0600 RECOMMENDED</strong></article>
        <article><small><?php esc_html_e('FOLDER POLICY', 'vgt-sentinel'); ?></small><strong>0755 STRICT</strong></article>
        <article><small><?php esc_html_e('FILE POLICY', 'vgt-sentinel'); ?></small><strong>0644 READ-ONLY</strong></article>
        <article><small><?php esc_html_e('EXPOSURE RISK', 'vgt-sentinel'); ?></small><strong><?php echo $is_all_secure ? 'ZERO (LOCKED)' : 'ELEVATED PRIVILEGES'; ?></strong></article>
    </div>

    <!-- CHMOD EMPFEHLUNGS-BANNER -->
    <div style="margin-bottom: 20px; padding: 16px 20px; border-radius: 12px; background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.28); display: flex; align-items: flex-start; gap: 14px;">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="var(--vis-cyan)" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        <div style="font-size: 13px; line-height: 1.6; color: var(--vis-copy);">
            <strong style="color: var(--vis-cyan);"><?php esc_html_e('POSIX CHMOD KONTROLLE:', 'vgt-sentinel'); ?></strong> 
            <?php esc_html_e('Dieses Modul prüft kritische WordPress-Dateien auf korrekte Datei- und Ordnerrechte (Linux/Unix Standard). Fehlerhafte Berechtigungen (z.B. 0777) stellen ein gravierendes Angriffsrisiko dar.', 'vgt-sentinel'); ?>
            <div style="margin-top: 6px; font-size: 12px; color: var(--vis-copy-soft);">
                <strong><?php esc_html_e('Sicherheitsstandard:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Verzeichnisse auf 0755, reguläre PHP/Core-Dateien auf 0644, und die wp-config.php zwingend auf 0600 oder 0640 setzen.', 'vgt-sentinel'); ?>
            </div>
        </div>
    </div>

    <!-- AUDIT DATA TABLE PANEL -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('01 / BERECHTIGUNGSMATRIX', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Systemdateien & POSIX-Rechte-Audit', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge" style="border-color: rgba(94, 234, 212, 0.35); color: var(--vis-cyan);">
                <?php echo esc_html(sprintf(__('%d Pfade analysiert', 'vgt-sentinel'), count($files))); ?>
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--vis-line); text-align: left;">
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 25%;">
                            <?php esc_html_e('DATEI / ORDNER', 'vgt-sentinel'); ?>
                        </th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 35%;">
                            <?php esc_html_e('PFAD (ABSOLUT)', 'vgt-sentinel'); ?>
                        </th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 12%;">
                            <?php esc_html_e('IST-CHMOD', 'vgt-sentinel'); ?>
                        </th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 12%;">
                            <?php esc_html_e('SOLL-CHMOD', 'vgt-sentinel'); ?>
                        </th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 16%; text-align: right;">
                            <?php esc_html_e('BEWERTUNG', 'vgt-sentinel'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($files as $f): 
                    $is_sec = ($f['status'] === 'secure');
                    $is_warn = ($f['status'] === 'warning');
                    $badge_col = $is_sec ? '#34d399' : ($is_warn ? '#fb7185' : '#f59e0b');
                    $badge_label = $is_sec ? __('SECURE', 'vgt-sentinel') : $f['msg'];
                ?>
                    <tr style="border-bottom: 1px solid rgba(148, 184, 224, 0.08);">
                        <td style="padding: 12px 14px; font-weight: 750; color: #ffffff;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="<?php echo esc_attr($badge_col); ?>" stroke-width="2">
                                    <?php if ($is_sec): ?>
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    <?php else: ?>
                                        <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    <?php endif; ?>
                                </svg>
                                <?php echo esc_html($f['name']); ?>
                            </div>
                        </td>
                        <td style="padding: 12px 14px; font-family: ui-monospace, monospace; font-size: 11px; color: var(--vis-copy-soft);">
                            <?php echo esc_html($f['path']); ?>
                        </td>
                        <td style="padding: 12px 14px; font-family: ui-monospace, monospace; font-size: 13px; font-weight: 750; color: <?php echo esc_attr($badge_col); ?>;">
                            <?php echo esc_html($f['current']); ?>
                        </td>
                        <td style="padding: 12px 14px; font-family: ui-monospace, monospace; font-size: 13px; font-weight: 700; color: var(--vis-copy-muted);">
                            <?php echo esc_html($f['recommended']); ?>
                        </td>
                        <td style="padding: 12px 14px; text-align: right;">
                            <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; font: 750 10px/1 ui-monospace, monospace; text-transform: uppercase; letter-spacing: 0.06em; background: <?php echo esc_attr($badge_col); ?>18; color: <?php echo esc_attr($badge_col); ?>; border: 1px solid <?php echo esc_attr($badge_col); ?>40;">
                                <?php echo esc_html($badge_label); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
