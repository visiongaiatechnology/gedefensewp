<?php
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: THREAD (SENTINEL INTELLIGENCE NEXUS)
 * MODULE: GLOBAL TELEMETRY & THREAT MATRIX (INCL. PROMETHEUS, NEMESIS & GHOST TRAPS)
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit;

global $wpdb;

// =========================================================================================
// 1. DATA AGGREGATION KERNEL (MULTI-MODULE UPLINK)
// =========================================================================================
$table_bans       = defined('VIS_TABLE_BANS') ? $wpdb->prefix . VIS_TABLE_BANS : $wpdb->prefix . 'vis_bans';
$table_logs       = defined('VIS_TABLE_LOGS') ? $wpdb->prefix . VIS_TABLE_LOGS : $wpdb->prefix . 'vis_logs';
$table_oracle     = $wpdb->prefix . 'vis_oracle_patterns';
$table_prometheus = $wpdb->prefix . 'vis_prometheus_logs';
$table_nemesis    = $wpdb->prefix . 'vis_nemesis_logs';

// Suppress errors dynamically (prevents crashes if modules are not yet initialized)
$suppress = $wpdb->suppress_errors(true);

// --- KPI AGGREGATION ---
$total_bans       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_bans}");
$total_prometheus = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_prometheus}");
$total_nemesis    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_nemesis}");
$total_oracle     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_oracle}");

// --- GHOST TRAP DATA (DECEPTION NODES) ---
$total_ghost_trap_kills = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_bans} WHERE reason LIKE 'GHOST_TRAP:%'");
$active_ghost_traps     = get_option('vis_ghost_trap_manifest', []);

// --- TELEMETRY STREAMS ---
// Aegis Core
$stream_aegis = $wpdb->get_results("SELECT timestamp, module, type, message as details, ip, severity FROM {$table_logs} ORDER BY id DESC LIMIT 15", ARRAY_A) ?: [];

// Prometheus Engine
$stream_prom  = $wpdb->get_results("SELECT timestamp, module, type, details, ip_address as ip, 6 as severity FROM {$table_prometheus} ORDER BY id DESC LIMIT 10", ARRAY_A) ?: [];

// Nemesis Deception
$stream_nem   = $wpdb->get_results("SELECT timestamp, module, type, details, ip_address as ip, 8 as severity FROM {$table_nemesis} ORDER BY id DESC LIMIT 10", ARRAY_A) ?: [];

// Ghost Trap Stream (Extracted from Bans for Global Fusion)
$stream_ghost = $wpdb->get_results("SELECT banned_at as timestamp, 'GHOST_TRAP' as module, 'DECEPTION HIT' as type, reason as details, ip, 9 as severity FROM {$table_bans} WHERE reason LIKE 'GHOST_TRAP:%' ORDER BY id DESC LIMIT 10", ARRAY_A) ?: [];

// Oracle & Bans (Right Column)
$stream_bans   = $wpdb->get_results("SELECT ip, reason, banned_at FROM {$table_bans} ORDER BY id DESC LIMIT 8", ARRAY_A) ?: [];
$stream_oracle = $wpdb->get_results("SELECT ip, type, ai_reason, timestamp FROM {$table_oracle} ORDER BY id DESC LIMIT 5", ARRAY_A) ?: [];

$wpdb->suppress_errors($suppress);

// --- VGT DATA FUSION ---
// Merge all telemetry streams and sort them chronologically (descending)
$global_stream = array_merge($stream_aegis, $stream_prom, $stream_nem, $stream_ghost);
usort($global_stream, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});
$global_stream = array_slice($global_stream, 0, 15); // Keep the UI clean with top 15 events

// =========================================================================================
// 2. VGT HELPER KERNEL
// =========================================================================================
if (!function_exists('vgt_get_module_theme')) {
    function vgt_get_module_theme($module, $severity = 1) {
        $m = strtoupper((string)$module);
        
        if (strpos($m, 'PROMETHEUS') !== false) {
            return ['color' => 'var(--vgt-neon-green)', 'bg' => 'rgba(16, 185, 129, 0.1)', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>'];
        }
        if (strpos($m, 'NEMESIS') !== false) {
            return ['color' => 'var(--vgt-neon-orange)', 'bg' => 'rgba(245, 158, 11, 0.1)', 'icon' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'];
        }
        if (strpos($m, 'ORACLE') !== false) {
            return ['color' => 'var(--vgt-neon-purple)', 'bg' => 'rgba(168, 85, 247, 0.1)', 'icon' => '<path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.73-2.73 2.5 2.5 0 0 1-.44-4.96 2.5 2.5 0 0 1 .44-4.96 2.5 2.5 0 0 1 2.73-2.73A2.5 2.5 0 0 1 9.5 2Z"/>'];
        }
        if (strpos($m, 'GHOST') !== false || strpos($m, 'TRAP') !== false) {
            return ['color' => 'var(--vgt-neon-pink)', 'bg' => 'rgba(236, 72, 153, 0.1)', 'icon' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>'];
        }
        
        // AEGIS Default
        $color = ($severity >= 8) ? 'var(--vgt-neon-red)' : 'var(--vgt-neon-blue)';
        $bg = ($severity >= 8) ? 'rgba(239, 68, 68, 0.1)' : 'rgba(0, 242, 255, 0.1)';
        return ['color' => $color, 'bg' => $bg, 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'];
    }
}

if (!function_exists('vgt_format_time_ago')) {
    function vgt_format_time_ago($datetime) {
        if (empty($datetime)) return __('Unknown', 'vgt-sentinel');
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return sprintf( esc_html__('%ds ago', 'vgt-sentinel'), $diff );
        if ($diff < 3600) return sprintf( esc_html__('%dm ago', 'vgt-sentinel'), floor($diff / 60) );
        if ($diff < 86400) return sprintf( esc_html__('%dh ago', 'vgt-sentinel'), floor($diff / 3600) );
        return wp_date(get_option('date_format'), $time);
    }
}
?>

<!-- =========================================================================================
     3. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $thread_css_path = __DIR__ . '/thread/style.css';
    if (is_readable($thread_css_path)) {
        echo file_get_contents($thread_css_path);
    }
    ?>
</style>

<div class="vgt-apex-ui">

    <!-- THE FIVE PILLARS: KPI STRIP -->
    <div class="vgt-kpi-grid">
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-red); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-red);"><?php echo esc_html(number_format_i18n($total_bans)); ?></div>
                <div class="vgt-kpi-label"><?php esc_html_e('Aegis Terminations', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--vgt-neon-red);">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-green); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-green);"><?php echo esc_html(number_format_i18n($total_prometheus)); ?></div>
                <div class="vgt-kpi-label"><?php esc_html_e('Prometheus Anomalies', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--vgt-neon-green);">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-orange); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-orange);"><?php echo esc_html(number_format_i18n($total_nemesis)); ?></div>
                <div class="vgt-kpi-label"><?php esc_html_e('Nemesis Deceptions', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--vgt-neon-orange);">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-purple); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-purple);"><?php echo esc_html(number_format_i18n($total_oracle)); ?></div>
                <div class="vgt-kpi-label"><?php esc_html_e('Oracle AI Defenses', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(168, 85, 247, 0.1); color: var(--vgt-neon-purple);">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.73-2.73 2.5 2.5 0 0 1-.44-4.96 2.5 2.5 0 0 1 .44-4.96 2.5 2.5 0 0 1 2.73-2.73A2.5 2.5 0 0 1 9.5 2Z"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-pink); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-pink);"><?php echo esc_html(number_format_i18n($total_ghost_trap_kills)); ?></div>
                <div class="vgt-kpi-label"><?php esc_html_e('Ghost Trap Kills', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(236, 72, 153, 0.1); color: var(--vgt-neon-pink);">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
            </div>
        </div>
    </div>

    <!-- MAIN THREAD AREA -->
    <div class="vgt-thread-layout">
        
        <!-- LEFT COLUMN: UNIFIED GLOBAL TELEMETRY STREAM -->
        <div class="vgt-glass-panel" style="display: flex; flex-direction: column;">
            <div class="vgt-module-header">
                <div class="vgt-module-title">
                    <svg class="vgt-radar-spin" viewBox="0 0 24 24" width="18" height="18" stroke="var(--vgt-neon-blue)" stroke-width="2" fill="none" style="border-radius:50%; box-shadow:0 0 10px var(--vgt-neon-blue);"><circle cx="12" cy="12" r="10"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    <?php esc_html_e('GLOBAL TELEMETRY FUSION', 'vgt-sentinel'); ?>
                </div>
                <div class="vgt-badge" style="background: rgba(0, 242, 255, 0.1); color: var(--vgt-neon-blue); border-color: rgba(0, 242, 255, 0.3);">
                    <span style="display:inline-block; width:6px; height:6px; background:currentColor; border-radius:50%; box-shadow: 0 0 8px currentColor;"></span> <?php esc_html_e('MULTI-NODE UPLINK', 'vgt-sentinel'); ?>
                </div>
            </div>
            <div style="overflow-x: auto; flex: 1;">
                <table class="vgt-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('T-Minus', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Sub-System', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Target IP', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Threat Signature & Telemetry Data', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($global_stream)): ?>
                            <tr><td colspan="4" style="text-align:center; color:var(--vgt-text-muted); padding:60px; font-family:monospace;"><?php esc_html_e('>_ ALL_SYSTEMS_IDLE: ZERO_THREATS', 'vgt-sentinel'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($global_stream as $index => $log): 
                                $severity = isset($log['severity']) ? (int)$log['severity'] : 5;
                                $theme = vgt_get_module_theme((string)$log['module'], $severity);
                                $delay = $index * 0.05;
                                $row_class = ($severity >= 8 && strtoupper((string)$log['module']) === 'AEGIS') ? 'vgt-animate-row vgt-critical-row' : 'vgt-animate-row';
                            ?>
                            <tr class="<?php echo esc_attr($row_class); ?>" style="animation-delay: <?php echo esc_attr((string)$delay); ?>s;">
                                <td class="vgt-mono" style="color: var(--vgt-text-muted); white-space: nowrap;"><?php echo esc_html(vgt_format_time_ago((string)$log['timestamp'])); ?></td>
                                <td>
                                    <span class="vgt-badge" style="background: <?php echo esc_attr($theme['bg']); ?>; color: <?php echo esc_attr($theme['color']); ?>; box-shadow: 0 0 10px <?php echo esc_attr($theme['bg']); ?>;">
                                        <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"><?php echo wp_kses_post($theme['icon']); ?></svg>
                                        <?php echo esc_html((string)$log['module']); ?>
                                    </span>
                                </td>
                                <td class="vgt-mono" style="color: #fff; font-weight: 600;"><?php echo esc_html((string)$log['ip']); ?></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?php echo esc_attr($theme['color']); ?>; box-shadow: 0 0 12px <?php echo esc_attr($theme['color']); ?>;"></span>
                                        <span style="font-weight: 800; color: #e2e8f0; letter-spacing: 0.5px;"><?php echo esc_html((string)$log['type']); ?></span>
                                    </div>
                                    <div class="vgt-terminal-box" style="color: <?php echo esc_attr($theme['color']); ?>;">
                                        <span class="vgt-term-prompt" style="color: <?php echo esc_attr($theme['color']); ?>;">system@<?php echo esc_html(strtolower((string)$log['module'])); ?>:~#</span>
                                        <span style="color: var(--vgt-text-dim);"><?php echo esc_html((string)$log['details']); ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT COLUMN: INTEL, GHOST TRAPS & BANS -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- ACTIVE GHOST TRAPS (DECEPTION NODES) -->
            <div class="vgt-glass-panel" style="border-top: 2px solid var(--vgt-neon-pink); box-shadow: inset 0 20px 50px -20px rgba(236, 72, 153, 0.1);">
                <div class="vgt-module-header">
                    <div class="vgt-module-title" style="color: var(--vgt-neon-pink);">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                        <?php esc_html_e('DECEPTION MATRIX', 'vgt-sentinel'); ?>
                    </div>
                </div>
                <div style="padding: 16px 24px;">
                    <?php if (empty($active_ghost_traps)): ?>
                        <div style="color:var(--vgt-text-muted); font-size:12px; font-family:monospace;"><?php esc_html_e('>_ DECEPTION_GRID: OFFLINE', 'vgt-sentinel'); ?></div>
                    <?php else: ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($active_ghost_traps as $trap): ?>
                                <div class="vgt-badge vgt-animate-row" style="background: rgba(236, 72, 153, 0.1); color: var(--vgt-neon-pink); border-color: rgba(236, 72, 153, 0.3); font-size: 11px; text-transform: none; font-family: 'Fira Code', monospace;">
                                    <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                    /<?php echo esc_html((string)$trap); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ORACLE INTEL -->
            <div class="vgt-glass-panel" style="border-top: 2px solid var(--vgt-neon-purple); box-shadow: inset 0 20px 50px -20px rgba(168, 85, 247, 0.1);">
                <div class="vgt-module-header">
                    <div class="vgt-module-title" style="color: var(--vgt-neon-purple);">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <?php esc_html_e('ORACLE INTEL FEED', 'vgt-sentinel'); ?>
                    </div>
                </div>
                <div style="padding: 0; margin: 0;">
                    <?php if (empty($stream_oracle)): ?>
                        <div style="padding:24px; color:var(--vgt-text-muted); font-family:monospace;"><?php esc_html_e('>_ NEURAL_NET: STANDBY', 'vgt-sentinel'); ?></div>
                    <?php else: ?>
                        <?php foreach ($stream_oracle as $index => $intel): ?>
                        <div class="vgt-animate-row" style="padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,0.03); animation-delay: <?php echo esc_attr((string)($index * 0.1)); ?>s;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <h4 style="margin:0; font-size:13px; color:#fff; text-shadow:0 0 8px rgba(255,255,255,0.3);"><?php echo esc_html((string)$intel['type']); ?></h4>
                                <span class="vgt-mono" style="color:var(--vgt-text-muted); font-size:10px;"><?php echo esc_html(vgt_format_time_ago((string)$intel['timestamp'])); ?></span>
                            </div>
                            <div style="padding: 10px 14px; background: rgba(0,0,0,0.8); border-left: 2px solid var(--vgt-neon-purple); border-radius: 0 4px 4px 0; font-family: 'Fira Code', monospace; font-size: 11px; color: var(--vgt-text-dim); word-break: break-all; max-height: 100px; overflow-y: auto;">
                                <span style="color:var(--vgt-neon-purple); font-weight:bold;"><?php esc_html_e('> AI_ANALYSIS:', 'vgt-sentinel'); ?></span><br>
                                <?php echo wp_kses_post(nl2br(esc_html((string)$intel['ai_reason']))); ?>
                            </div>
                            <div class="vgt-mono" style="margin-top:8px; font-size:10px; color:#fff; font-weight:bold;">
                                <?php esc_html_e('TARGET:', 'vgt-sentinel'); ?> <?php echo esc_html((string)$intel['ip']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ACTIVE BANS -->
            <div class="vgt-glass-panel" style="border-top: 2px solid var(--vgt-neon-red); flex: 1; box-shadow: inset 0 20px 50px -20px rgba(239, 68, 68, 0.1);">
                <div class="vgt-module-header">
                    <div class="vgt-module-title" style="color: var(--vgt-neon-red);">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/></svg>
                        <?php esc_html_e('AEGIS TERMINATION LOG', 'vgt-sentinel'); ?>
                    </div>
                </div>
                <div style="padding: 20px 24px;">
                    <?php if (empty($stream_bans)): ?>
                        <p style="color:var(--vgt-text-muted); font-size:12px; font-family:monospace;"><?php esc_html_e('>_ NO_TARGETS_LOCKED', 'vgt-sentinel'); ?></p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($stream_bans as $index => $ban): ?>
                            <div class="vgt-animate-row vgt-ban-card" style="animation-delay: <?php echo esc_attr((string)($index * 0.1)); ?>s;">
                                <div>
                                    <div class="vgt-mono" style="color: #fff; font-size: 14px; font-weight: 900; margin-bottom: 2px;">
                                        <?php echo esc_html((string)$ban['ip']); ?> 
                                        <span style="font-size: 9px; color: var(--vgt-neon-red); border: 1px solid var(--vgt-neon-red); padding: 2px 4px; border-radius: 2px; font-weight: 900; letter-spacing: 1px; margin-left: 8px;"><?php esc_html_e('TERMINATED', 'vgt-sentinel'); ?></span>
                                    </div>
                                    <div style="font-size: 10px; color: var(--vgt-text-dim); text-transform: uppercase; font-family: 'Fira Code', monospace;">
                                        <?php echo esc_html(str_replace('AEGIS: Auto-Ban (', '', rtrim((string)$ban['reason'], ')'))); ?>
                                    </div>
                                </div>
                                <div class="vgt-mono" style="color: var(--vgt-neon-red); font-size: 10px; font-weight:bold; margin-left: auto;">
                                    <?php echo esc_html(vgt_format_time_ago((string)$ban['banned_at'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT)
     ========================================================================================= -->
<script>
    <?php 
    $thread_js_path = __DIR__ . '/thread/script.js';
    if (is_readable($thread_js_path)) {
        echo file_get_contents($thread_js_path);
    }
    ?>
</script>
