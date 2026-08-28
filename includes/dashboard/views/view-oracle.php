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

// Oracle Pulse State (i18n Hardened)
$oracle_state = ($failed_checks === 0) ? __('SYSTEM OPTIMAL', 'vgt-sentinel') : __('ANOMALIES DETECTED', 'vgt-sentinel');
$pulse_class  = ($failed_checks === 0) ? 'vgt-is-active' : 'vgt-is-alert';
$pulse_color  = ($failed_checks === 0) ? 'var(--vgt-neon-green)' : 'var(--vgt-neon-red)';
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $oracle_css_path = __DIR__ . '/oracle/style.css';
    if (is_readable($oracle_css_path)) {
        echo file_get_contents($oracle_css_path);
    }
    ?>
</style>

<div class="vgt-apex-ui">

    <div class="vgt-glass-panel" style="border-top: 3px solid <?php echo esc_attr($pulse_color); ?>;">
        
        <!-- =========================================================================================
             3. MODULE HEADER
             ========================================================================================= -->
        <div class="vgt-module-header">
            <div class="vgt-module-title">
                <div style="background:rgba(255, 255, 255, 0.05); padding:10px; border-radius:8px; border:1px solid rgba(255, 255, 255, 0.1); display: flex;">
                    <svg class="vgt-icon" style="color:<?php echo esc_attr($pulse_color); ?>; width:24px; height:24px;" viewBox="0 0 24 24">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </div>
                <div>
                    <h2><?php esc_html_e('ORACLE SYSTEM AUDIT', 'vgt-sentinel'); ?></h2>
                    <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace; display:flex; align-items:center; gap:8px;">
                        <?php esc_html_e('Prophecy Engine:', 'vgt-sentinel'); ?>
                        <span class="<?php echo esc_attr($pulse_class); ?>" style="display:inline-flex; align-items:center; gap:6px;">
                            <span class="vgt-status-pulse"></span>
                            <strong style="color:<?php echo esc_attr($pulse_color); ?>; letter-spacing:0.5px;">
                                <?php echo esc_html($oracle_state); ?>
                            </strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================================================================================
             4. KPI MATRIX (PRE-CALCULATED)
             ========================================================================================= -->
        <div class="vgt-bento-grid">
            <div class="vgt-kpi-card">
                <div class="vgt-kpi-val" style="color:var(--vgt-neon-blue);"><?php echo esc_html((string)$total_checks); ?></div>
                <div class="vgt-kpi-lbl"><?php esc_html_e('TOTAL CHECKS', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-card">
                <div class="vgt-kpi-val" style="color:var(--vgt-neon-green);"><?php echo esc_html((string)$passed_checks); ?></div>
                <div class="vgt-kpi-lbl"><?php esc_html_e('PASSED VECTORS', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-card">
                <div class="vgt-kpi-val" style="color:<?php echo $failed_checks > 0 ? 'var(--vgt-neon-red)' : 'var(--vgt-text-muted)'; ?>;"><?php echo esc_html((string)$failed_checks); ?></div>
                <div class="vgt-kpi-lbl"><?php esc_html_e('ANOMALIES FOUND', 'vgt-sentinel'); ?></div>
            </div>
        </div>

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
                                    <?php echo $icon_svg; // Secure internal logic bypass ?>
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
