<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: ORACLE
 * MODULE: ORACLE SYSTEM AUDIT & PROPHECY ENGINE
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. LOGIK CORE (STRICT 1:1)
// =========================================================================================
$oracle = new VIS_Oracle();
$res = $oracle->run_prophecy();

// OMEGA LOGIC: Pre-Calculate Matrix Stats for APEX UI
$total_checks = count($res);
$passed_checks = 0;
$failed_checks = 0;

foreach($res as $r) {
    if ($r['status'] === 'PASS') {
        $passed_checks++;
    } else {
        $failed_checks++;
    }
}

// Calculate Immunity Percentage
$immunity_pct = ($total_checks > 0) ? (int) round(($passed_checks / $total_checks) * 100) : 100;

// Oracle Pulse State (i18n Hardened)
$oracle_state = ($failed_checks === 0) ? __('SYSTEM OPTIMAL', 'vgt-sentinel') : __('ANOMALIES DETECTED', 'vgt-sentinel');
$pulse_class  = ($failed_checks === 0) ? 'vgt-is-active' : 'vgt-is-alert';
$pulse_color  = ($failed_checks === 0) ? 'var(--vgt-neon-green)' : 'var(--vgt-neon-red)';
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<div class="vgt-apex-ui">

    <!-- MASTER ORACLE PROPHECY HERO (VGT GLASS 2100) -->
    <div class="vsc-hero" id="vis-oracle-hero" style="border-left: 4px solid <?php echo esc_attr($pulse_color); ?>;">
        <div class="vsc-hero-copy">
            <span class="vsc-eyebrow"><span class="vsc-live-dot" aria-hidden="true" style="background:<?php echo esc_attr($pulse_color); ?>; box-shadow: 0 0 14px <?php echo esc_attr($pulse_color); ?>;"></span><?php esc_html_e('COGNITIVE AUDIT // PROPHECY ENGINE', 'vgt-sentinel'); ?></span>
            <h2><?php esc_html_e('Orakel Scanner & Heuristik', 'vgt-sentinel'); ?></h2>
            <p><?php esc_html_e('Proaktive KI-Sicherheitsanalyse gegen Zero-Day-Vektoren, Deserialisierungs-Schwachstellen, SQLi/RCE-Muster und unsichere Plugin-Berechtigungen.', 'vgt-sentinel'); ?></p>
            <div class="vsc-actions">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'vgt-suite', 'tab' => 'oracle'], admin_url('admin.php'))); ?>" class="vsc-button vsc-button-primary" style="text-decoration:none;">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    <span><?php esc_html_e('RUN PROPHECY SCAN', 'vgt-sentinel'); ?></span>
                </a>
                <span class="vsc-last-run">
                    <?php echo esc_html(sprintf(__('%d Vektoren evaluiert · Heuristik online', 'vgt-sentinel'), $total_checks)); ?>
                </span>
            </div>
        </div>
        <div class="vsc-score-panel">
            <div class="vsc-score-ring" id="vis-oracle-score-ring" role="img" aria-label="<?php echo esc_attr__('Immunitäts-Score', 'vgt-sentinel'); ?>" style="--vsc-score: <?php echo esc_attr((string)round($immunity_pct * 3.6)); ?>deg; background: conic-gradient(<?php echo esc_attr($pulse_color); ?> var(--vsc-score), rgba(148, 184, 224, 0.12) 0); filter: drop-shadow(0 0 24px <?php echo esc_attr($pulse_color); ?>40);">
                <div><strong><?php echo esc_html((string)$immunity_pct); ?></strong><span>%</span></div>
            </div>
            <div class="vsc-score-meta">
                <span class="vsc-posture" style="color: <?php echo esc_attr($pulse_color); ?>;"><?php echo esc_html($oracle_state); ?></span>
                <small><?php echo esc_html(sprintf(__('%d von %d bestanden', 'vgt-sentinel'), $passed_checks, $total_checks)); ?></small>
            </div>
        </div>
    </div>

    <!-- KPI COMMAND STRIP (4 TELEMETRY TILES) -->
    <div class="vgt-kpi-grid" style="margin-bottom: 24px;">
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vis-cyan); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color:var(--vis-cyan);"><?php echo esc_html((string)$total_checks); ?></div>
                <div class="vgt-kpi-label"><?php esc_html_e('TOTAL CHECKS', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(94, 234, 212, 0.1); color: var(--vis-cyan);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vis-green); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color:var(--vis-green);"><?php echo esc_html((string)$passed_checks); ?></div>
                <div class="vgt-kpi-label"><?php esc_html_e('PASSED VECTORS', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(52, 211, 153, 0.1); color: var(--vis-green);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid <?php echo $failed_checks > 0 ? 'var(--vis-red)' : 'var(--vis-copy-muted)'; ?>; border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color:<?php echo $failed_checks > 0 ? 'var(--vis-red)' : 'var(--vis-copy-muted)'; ?>;"><?php echo esc_html((string)$failed_checks); ?></div>
                <div class="vgt-kpi-label"><?php esc_html_e('ANOMALIES FOUND', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(251, 113, 133, 0.1); color: var(--vis-red);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid <?php echo $immunity_pct === 100 ? 'var(--vis-green)' : 'var(--vis-amber)'; ?>; border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color:<?php echo $immunity_pct === 100 ? 'var(--vis-green)' : 'var(--vis-amber)'; ?>;"><?php echo esc_html((string)$immunity_pct); ?>%</div>
                <div class="vgt-kpi-label"><?php esc_html_e('IMMUNITY RATING', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(251, 191, 36, 0.1); color: var(--vis-amber);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
        </div>
    </div>

    <div class="vgt-glass-panel">

        <!-- =========================================================================================
             5. AUDIT REPORT DATA TABLE
             ========================================================================================= -->
        <div class="vgt-table-container">
            <table class="vgt-data-table">
                <thead>
                    <tr>
                        <th width="35%"><?php esc_html_e('SECURITY CHECK DEFINITION', 'vgt-sentinel'); ?></th>
                        <th width="15%"><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th>
                        <th width="50%"><?php esc_html_e('ANALYSIS RESULT (PROPHECY)', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($res as $r): 
                    $is_pass = ($r['status'] === 'PASS');
                    $badge   = $is_pass ? 'vgt-badge-active' : 'vgt-badge-alert';
                    $msg_col = $is_pass ? 'var(--vgt-text-dim)' : 'var(--vgt-neon-red)';
                    $icon_svg = $is_pass 
                        ? '<polyline points="20 6 9 17 4 12"></polyline>' 
                        : '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>';
                ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <svg class="vgt-icon" style="width:16px; height:16px; color:<?php echo $is_pass ? 'var(--vgt-neon-green)' : 'var(--vgt-neon-red)'; ?>;" viewBox="0 0 24 24">
                                    <?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </svg>
                                <strong style="color:#fff; font-size:13px; letter-spacing:0.5px;"><?php echo esc_html(__($r['check'], 'vgt-sentinel')); ?></strong>
                            </div>
                        </td>
                        <td>
                            <span class="vgt-badge <?php echo esc_attr($badge); ?>">
                                <?php echo esc_html(__($r['status'], 'vgt-sentinel')); ?>
                            </span>
                        </td>
                        <td style="color:<?php echo esc_attr($msg_col); ?>; line-height: 1.5;">
                            <?php echo esc_html(__($r['msg'], 'vgt-sentinel')); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>
