<?php
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: THREAD (SENTINEL INTELLIGENCE NEXUS)
 * MODULE: GLOBAL TELEMETRY & THREAT MATRIX (XDR & MULTI-SENSOR FUSION)
 * STATUS: DIAMANT VGT SUPREME (Full XDR Correlation, Top Attacker Profiling, Multi-Lingual & Escaped)
 */
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

global $wpdb;

// =========================================================================================
// 1. DATA AGGREGATION KERNEL (XDR & MULTI-SENSOR FUSION)
// =========================================================================================
$table_bans       = defined('VIS_TABLE_BANS') ? $wpdb->prefix . VIS_TABLE_BANS : $wpdb->prefix . 'vis_omega_bans';
$table_logs       = defined('VIS_TABLE_LOGS') ? $wpdb->prefix . VIS_TABLE_LOGS : $wpdb->prefix . 'vis_omega_logs';
$table_xdr_inc    = $wpdb->prefix . 'vis_xdr_incidents';
$table_xdr_ev     = $wpdb->prefix . 'vis_xdr_events';
$table_xdr_resp   = $wpdb->prefix . 'vis_xdr_responses';
$table_prometheus = $wpdb->prefix . 'vis_prometheus_logs';
$table_nemesis    = $wpdb->prefix . 'vis_nemesis_logs';
$table_oracle     = $wpdb->prefix . 'vis_oracle_patterns';

$suppress = $wpdb->suppress_errors(true);

$has_xdr_inc  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_xdr_inc)) === $table_xdr_inc;
$has_xdr_ev   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_xdr_ev)) === $table_xdr_ev;
$has_xdr_resp = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_xdr_resp)) === $table_xdr_resp;
$has_bans     = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_bans)) === $table_bans;
$has_logs     = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_logs)) === $table_logs;
$has_prom     = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_prometheus)) === $table_prometheus;
$has_nem      = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_nemesis)) === $table_nemesis;

// --- KPI AGGREGATION ---
$total_incidents = $has_xdr_inc ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_xdr_inc}") : 0;
$total_xdr_events = $has_xdr_ev ? (int) $wpdb->get_var("SELECT SUM(occurrence_count) FROM {$table_xdr_ev}") : 0;
$total_bans = $has_bans ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_bans}") : 0;
$total_contained_xdr = $has_xdr_resp ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_xdr_resp} WHERE status = 'APPLIED'") : 0;
$total_ghost_kills = $has_bans ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_bans} WHERE reason LIKE 'GHOST_TRAP:%'") : 0;
$active_ghost_traps = get_option('vis_ghost_trap_manifest', []);
if (!is_array($active_ghost_traps)) $active_ghost_traps = [];

// --- TOP THREAT ACTOR PROFILING ---
$top_actors = [];
if ($has_xdr_ev) {
    $xdr_actors = $wpdb->get_results(
        "SELECT actor_ip, 
                MAX(severity) as max_severity, 
                SUM(occurrence_count) as total_hits, 
                COUNT(DISTINCT sensor) as sensor_count,
                GROUP_CONCAT(DISTINCT sensor SEPARATOR ', ') as sensors,
                GROUP_CONCAT(DISTINCT vector SEPARATOR ', ') as vectors,
                MAX(last_seen) as latest_seen
         FROM {$table_xdr_ev} 
         WHERE actor_ip != '' AND actor_ip IS NOT NULL 
         GROUP BY actor_ip 
         ORDER BY total_hits DESC, max_severity DESC 
         LIMIT 6",
        ARRAY_A
    ) ?: [];

    foreach ($xdr_actors as $act) {
        $ip = (string)$act['actor_ip'];
        $is_contained = false;
        $status_label = 'MONITORING';
        if (class_exists('\VisionGaia\GeDefense\Xdr\ResponseEngine') && \VisionGaia\GeDefense\Xdr\ResponseEngine::isIpRestricted($ip)) {
            $is_contained = true;
            $status_label = 'CONTAINED (TTL)';
        } elseif ($has_bans && class_exists('\VIS_Cerberus') && \VIS_Cerberus::instance()->is_ip_banned($ip)) {
            $is_contained = true;
            $status_label = 'BANNED';
        }
        $top_actors[] = [
            'ip' => $ip,
            'severity' => max(1, (int)$act['max_severity']),
            'hits' => (int)$act['total_hits'],
            'sensor_count' => (int)$act['sensor_count'],
            'sensors' => (string)$act['sensors'],
            'vectors' => (string)$act['vectors'],
            'latest' => (string)$act['latest_seen'],
            'contained' => $is_contained,
            'status' => $status_label,
        ];
    }
}

// Fallback if XDR events table is fresh: aggregate from bans table
if (empty($top_actors) && $has_bans) {
    $ban_actors = $wpdb->get_results("SELECT ip, reason, banned_at FROM {$table_bans} ORDER BY id DESC LIMIT 6", ARRAY_A) ?: [];
    foreach ($ban_actors as $ban) {
        $ip = (string)$ban['ip'];
        $top_actors[] = [
            'ip' => $ip,
            'severity' => 8,
            'hits' => 1,
            'sensor_count' => 1,
            'sensors' => 'CERBERUS',
            'vectors' => (string)$ban['reason'],
            'latest' => (string)$ban['banned_at'],
            'contained' => true,
            'status' => 'BANNED',
        ];
    }
}

// --- UNIFIED TELEMETRY STREAM ---
$stream_events = [];
if ($has_xdr_ev) {
    $stream_events = $wpdb->get_results(
        "SELECT last_seen as timestamp, sensor as module, vector as type, route as details, actor_ip as ip, entity_type, entity_id, role, severity, occurrence_count 
         FROM {$table_xdr_ev} 
         ORDER BY id DESC LIMIT 40",
        ARRAY_A
    ) ?: [];
}

if (empty($stream_events) && $has_logs) {
    $legacy_stream = $wpdb->get_results(
        "SELECT timestamp, module, type, message as details, ip, 'IP' as entity_type, ip as entity_id, 'DETECTION' as role, severity, 1 as occurrence_count 
         FROM {$table_logs} 
         ORDER BY id DESC LIMIT 30",
        ARRAY_A
    ) ?: [];
    $stream_events = $legacy_stream;
}

$wpdb->suppress_errors($suppress);

// =========================================================================================
// 2. HELPER FUNCTIONS
// =========================================================================================
if (!function_exists('vgt_get_sensor_theme')) {
    function vgt_get_sensor_theme(string $module, int $severity = 1): array {
        $m = strtoupper(trim($module));
        return match ($m) {
            'PROMETHEUS'  => ['color' => 'var(--vgt-neon-green)',  'bg' => 'rgba(16, 185, 129, 0.12)', 'icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
            'MORPHEUS'    => ['color' => 'var(--vgt-neon-purple)', 'bg' => 'rgba(168, 85, 247, 0.12)', 'icon' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/>'],
            'STYX'        => ['color' => 'var(--vgt-neon-cyan)',   'bg' => 'rgba(6, 182, 212, 0.12)',  'icon' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>'],
            'CERBERUS'    => ['color' => 'var(--vgt-neon-red)',    'bg' => 'rgba(239, 68, 68, 0.12)',  'icon' => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>'],
            'CHRONOS'     => ['color' => 'var(--vgt-neon-orange)', 'bg' => 'rgba(245, 158, 11, 0.12)', 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
            'GHOST_TRAP', 'TRAP' => ['color' => 'var(--vgt-neon-pink)', 'bg' => 'rgba(236, 72, 153, 0.12)', 'icon' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>'],
            'THRONEGUARD' => ['color' => 'var(--vgt-neon-indigo)', 'bg' => 'rgba(99, 102, 241, 0.12)', 'icon' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'],
            'HADES'       => ['color' => 'var(--vgt-neon-purple)', 'bg' => 'rgba(147, 51, 234, 0.12)', 'icon' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'],
            'NEMESIS'     => ['color' => 'var(--vgt-neon-orange)', 'bg' => 'rgba(245, 158, 11, 0.12)', 'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
            default       => ($severity >= 8) 
                ? ['color' => 'var(--vgt-neon-red)',  'bg' => 'rgba(239, 68, 68, 0.12)',  'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>']
                : ['color' => 'var(--vgt-neon-blue)', 'bg' => 'rgba(0, 242, 255, 0.12)', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
        };
    }
}

if (!function_exists('vgt_format_time_ago')) {
    function vgt_format_time_ago(string $datetime): string {
        if ($datetime === '') return esc_html__('Unknown', 'vgt-sentinel');
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return sprintf(esc_html__('%ds ago', 'vgt-sentinel'), max(1, $diff));
        if ($diff < 3600) return sprintf(esc_html__('%dm ago', 'vgt-sentinel'), floor($diff / 60));
        if ($diff < 86400) return sprintf(esc_html__('%dh ago', 'vgt-sentinel'), floor($diff / 3600));
        return wp_date((string)get_option('date_format', 'Y-m-d'), $time);
    }
}
?>

<!-- DECENTRALIZED ASSET INJECTION (CSS) -->
<div class="vgt-apex-ui">

    <!-- KPI COMMAND STRIP -->
    <div class="vgt-kpi-grid">
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-blue); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-blue);"><?php echo esc_html(number_format_i18n($total_incidents)); ?></div>
                <div class="vgt-kpi-label"><?php echo esc_html__('Correlated Incidents', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(0, 242, 255, 0.1); color: var(--vgt-neon-blue);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-red); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-red);"><?php echo esc_html(number_format_i18n($total_contained_xdr + $total_bans)); ?></div>
                <div class="vgt-kpi-label"><?php echo esc_html__('Contained Threat Actors', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--vgt-neon-red);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-green); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-green);"><?php echo esc_html(number_format_i18n($total_xdr_events)); ?></div>
                <div class="vgt-kpi-label"><?php echo esc_html__('Telemetry Signals', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--vgt-neon-green);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-pink); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-pink);"><?php echo esc_html(number_format_i18n($total_ghost_kills)); ?></div>
                <div class="vgt-kpi-label"><?php echo esc_html__('Deception Interceptions', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(236, 72, 153, 0.1); color: var(--vgt-neon-pink);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
            </div>
        </div>
        <div class="vgt-glass-panel vgt-kpi-card" style="border-left: 3px solid var(--vgt-neon-purple); border-top: none;">
            <div>
                <div class="vgt-kpi-val" style="color: var(--vgt-neon-purple);"><?php echo esc_html((string)count($active_ghost_traps)); ?></div>
                <div class="vgt-kpi-label"><?php echo esc_html__('Active Honeytokens', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-kpi-icon" style="background: rgba(168, 85, 247, 0.1); color: var(--vgt-neon-purple);">
                <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
        </div>
    </div>

    <!-- TOP ATTACKERS & THREAT PROFILING MATRIX -->
    <div class="vgt-glass-panel">
        <div class="vgt-module-header">
            <div class="vgt-module-title" style="color: #fff;">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="var(--vgt-neon-red)" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/></svg>
                <?php echo esc_html__('Top Threat Actors & Profiling', 'vgt-sentinel'); ?>
            </div>
            <div class="vgt-badge" style="background: rgba(239, 68, 68, 0.1); color: var(--vgt-neon-red); border-color: rgba(239, 68, 68, 0.3);">
                <span style="display:inline-block; width:6px; height:6px; background:currentColor; border-radius:50%;"></span>
                <?php echo esc_html__('ADVERSARY INTELLIGENCE', 'vgt-sentinel'); ?>
            </div>
        </div>
        <div class="vgt-profiling-grid">
            <?php if (empty($top_actors)): ?>
                <div style="color:var(--vgt-text-muted); font-size:12px; font-family:monospace; padding:20px; grid-column:1/-1; text-align:center;">
                    <?php echo esc_html__('>_ NO_THREAT_ACTORS_DETECTED: ALL_CLEAR', 'vgt-sentinel'); ?>
                </div>
            <?php else: ?>
                <?php foreach ($top_actors as $actor): 
                    $severity_color = $actor['severity'] >= 8 ? 'var(--vgt-neon-red)' : ($actor['severity'] >= 5 ? 'var(--vgt-neon-orange)' : 'var(--vgt-neon-blue)');
                ?>
                <div class="vgt-actor-card">
                    <div class="vgt-actor-header">
                        <div class="vgt-actor-ip">
                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?php echo esc_attr($severity_color); ?>; box-shadow:0 0 8px <?php echo esc_attr($severity_color); ?>;"></span>
                            <?php echo esc_html($actor['ip']); ?>
                        </div>
                        <span class="vgt-badge" style="background: <?php echo $actor['contained'] ? 'rgba(239, 68, 68, 0.15)' : 'rgba(0, 242, 255, 0.15)'; ?>; color: <?php echo $actor['contained'] ? 'var(--vgt-neon-red)' : 'var(--vgt-neon-blue)'; ?>; border-color: currentColor;">
                            <?php echo esc_html($actor['status']); ?>
                        </span>
                    </div>
                    <div class="vgt-actor-meta">
                        <div class="vgt-actor-row">
                            <span><?php echo esc_html__('Strike Pressure / Hits', 'vgt-sentinel'); ?>:</span>
                            <span class="vgt-mono" style="font-weight:700; color:#fff;"><?php echo esc_html(number_format_i18n($actor['hits'])); ?></span>
                        </div>
                        <div class="vgt-actor-row">
                            <span><?php echo esc_html__('Sensors Tripped', 'vgt-sentinel'); ?>:</span>
                            <span class="vgt-mono" style="color:var(--vgt-neon-purple);"><?php echo esc_html($actor['sensors']); ?></span>
                        </div>
                        <div class="vgt-actor-row">
                            <span><?php echo esc_html__('Attack Vectors', 'vgt-sentinel'); ?>:</span>
                            <span class="vgt-mono" style="color:var(--vgt-text-dim); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html($actor['vectors']); ?></span>
                        </div>
                        <div class="vgt-actor-row">
                            <span><?php echo esc_html__('Last Seen', 'vgt-sentinel'); ?>:</span>
                            <span class="vgt-mono"><?php echo esc_html(vgt_format_time_ago($actor['latest'])); ?></span>
                        </div>
                    </div>
                    <div style="margin-top:4px; display:flex; justify-content:flex-end;">
                        <button type="button" class="vgt-btn-filter-ip" data-filter-ip="<?php echo esc_attr((string)$actor['ip']); ?>">
                            <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <?php echo esc_html__('Filter Events', 'vgt-sentinel'); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN TELEMETRY FUSION & DECEPTION MATRIX LAYOUT -->
    <div class="vgt-thread-layout">
        
        <!-- LEFT COLUMN: UNIFIED GLOBAL TELEMETRY STREAM -->
        <div class="vgt-glass-panel" style="display: flex; flex-direction: column;">
            <div class="vgt-module-header">
                <div class="vgt-module-title">
                    <svg class="vgt-radar-spin" viewBox="0 0 24 24" width="16" height="16" stroke="var(--vgt-neon-blue)" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    <?php echo esc_html__('Global Telemetry & XDR Fusion Stream', 'vgt-sentinel'); ?>
                </div>
                <div class="vgt-badge" style="background: rgba(0, 242, 255, 0.1); color: var(--vgt-neon-blue); border-color: rgba(0, 242, 255, 0.3);">
                    <span style="display:inline-block; width:6px; height:6px; background:currentColor; border-radius:50%; box-shadow: 0 0 8px currentColor;"></span>
                    <?php echo esc_html__('MULTI-NODE UPLINK', 'vgt-sentinel'); ?>
                </div>
            </div>

            <!-- REACTIVE SEARCH & FILTER BAR -->
            <div class="vgt-filter-bar">
                <input type="text" id="vgt-telemetry-ip-search" class="vgt-search-input" placeholder="<?php echo esc_attr__('Search IP, vector or route...', 'vgt-sentinel'); ?>">
                
                <select id="vgt-telemetry-sensor-filter" class="vgt-select-filter">
                    <option value=""><?php echo esc_html__('All Sensors', 'vgt-sentinel'); ?></option>
                    <option value="AEGIS">AEGIS (WAF)</option>
                    <option value="PROMETHEUS">PROMETHEUS (Behavior)</option>
                    <option value="MORPHEUS">MORPHEUS (Sandbox)</option>
                    <option value="STYX">STYX (Egress)</option>
                    <option value="CERBERUS">CERBERUS (Containment)</option>
                    <option value="CHRONOS">CHRONOS (Integrity)</option>
                    <option value="GHOST_TRAP">GHOST TRAP (Deception)</option>
                    <option value="THRONEGUARD">THRONEGUARD (Identity)</option>
                    <option value="HADES">HADES (Stealth)</option>
                </select>

                <select id="vgt-telemetry-severity-filter" class="vgt-select-filter">
                    <option value="0"><?php echo esc_html__('All Severities', 'vgt-sentinel'); ?></option>
                    <option value="5"><?php echo esc_html__('Medium (5+)', 'vgt-sentinel'); ?></option>
                    <option value="7"><?php echo esc_html__('High (7+)', 'vgt-sentinel'); ?></option>
                    <option value="9"><?php echo esc_html__('Critical (9+)', 'vgt-sentinel'); ?></option>
                </select>

                <button type="button" class="vgt-btn-filter-ip" style="margin-left:auto;" data-reset-filters>
                    <?php echo esc_html__('Reset Filters', 'vgt-sentinel'); ?>
                </button>
            </div>

            <div style="overflow-x: auto; flex: 1;">
                <table class="vgt-table" id="vgt-telemetry-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('T-Minus', 'vgt-sentinel'); ?></th>
                            <th><?php echo esc_html__('Sensor', 'vgt-sentinel'); ?></th>
                            <th><?php echo esc_html__('Actor IP', 'vgt-sentinel'); ?></th>
                            <th><?php echo esc_html__('Role', 'vgt-sentinel'); ?></th>
                            <th><?php echo esc_html__('Signature & Target Entity', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stream_events)): ?>
                            <tr><td colspan="5" style="text-align:center; color:var(--vgt-text-muted); padding:60px; font-family:monospace;"><?php echo esc_html__('>_ ALL_SYSTEMS_IDLE: ZERO_THREATS', 'vgt-sentinel'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($stream_events as $index => $log): 
                                $severity = max(1, min(10, (int)($log['severity'] ?? 5)));
                                $sensorName = (string)($log['module'] ?? 'SYSTEM');
                                $theme = vgt_get_sensor_theme($sensorName, $severity);
                                $role = strtoupper(trim((string)($log['role'] ?? 'DETECTION')));
                                $actorIp = (string)($log['ip'] ?? '');
                                $entityType = (string)($log['entity_type'] ?? '');
                                $entityId = (string)($log['entity_id'] ?? '');
                                $hits = (int)($log['occurrence_count'] ?? 1);
                            ?>
                            <tr class="vgt-telemetry-row" 
                                data-ip="<?php echo esc_attr(strtolower($actorIp)); ?>" 
                                data-sensor="<?php echo esc_attr(strtoupper($sensorName)); ?>" 
                                data-severity="<?php echo esc_attr((string)$severity); ?>">
                                <td class="vgt-mono" style="color: var(--vgt-text-muted); white-space: nowrap;">
                                    <?php echo esc_html(vgt_format_time_ago((string)$log['timestamp'])); ?>
                                </td>
                                <td>
                                    <span class="vgt-badge" style="background: <?php echo esc_attr($theme['bg']); ?>; color: <?php echo esc_attr($theme['color']); ?>; box-shadow: 0 0 8px <?php echo esc_attr($theme['bg']); ?>;">
                                        <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"><?php echo wp_kses_post($theme['icon']); ?></svg>
                                        <?php echo esc_html($sensorName); ?>
                                    </span>
                                </td>
                                <td class="vgt-mono" style="color: #fff; font-weight: 600;">
                                    <?php if ($actorIp !== ''): ?>
                                        <button type="button" class="vgt-inline-filter" data-filter-ip="<?php echo esc_attr($actorIp); ?>">
                                            <?php echo esc_html($actorIp); ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="color:var(--vgt-text-muted);"><?php echo esc_html__('SYSTEM', 'vgt-sentinel'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="vgt-badge" style="background:rgba(255,255,255,0.05); color:<?php echo $role === 'CONFIRMATION' ? 'var(--vgt-neon-green)' : ($role === 'RESPONSE' ? 'var(--vgt-neon-purple)' : 'var(--vgt-text-dim)'); ?>;">
                                        <?php echo esc_html($role); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?php echo esc_attr($theme['color']); ?>; box-shadow: 0 0 10px <?php echo esc_attr($theme['color']); ?>;"></span>
                                        <span style="font-weight: 800; color: #e2e8f0; letter-spacing: 0.5px;"><?php echo esc_html((string)$log['type']); ?></span>
                                        <?php if ($hits > 1): ?>
                                            <span class="vgt-badge" style="background:rgba(245,158,11,0.15); color:var(--vgt-neon-orange); font-size:9px;">
                                                &times;<?php echo esc_html(number_format_i18n($hits)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vgt-terminal-box" style="color: <?php echo esc_attr($theme['color']); ?>;">
                                        <span class="vgt-term-prompt">target:</span>
                                        <span style="color: var(--vgt-text-dim);">
                                            <?php if ($entityType !== '' && $entityId !== ''): ?>
                                                [<?php echo esc_html($entityType); ?>: <?php echo esc_html($entityId); ?>]
                                            <?php endif; ?>
                                            <?php echo esc_html((string)$log['details']); ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT COLUMN: INTEL & DECEPTION NODES -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- ACTIVE GHOST TRAPS (DECEPTION MATRIX) -->
            <div class="vgt-glass-panel" style="border-top: 2px solid var(--vgt-neon-pink); box-shadow: inset 0 20px 50px -20px rgba(236, 72, 153, 0.1);">
                <div class="vgt-module-header">
                    <div class="vgt-module-title" style="color: var(--vgt-neon-pink);">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                        <?php echo esc_html__('Deception Matrix & Canaries', 'vgt-sentinel'); ?>
                    </div>
                </div>
                <div style="padding: 16px 20px;">
                    <?php if (empty($active_ghost_traps)): ?>
                        <div style="color:var(--vgt-text-muted); font-size:12px; font-family:monospace;"><?php echo esc_html__('>_ DECEPTION_GRID: OFFLINE', 'vgt-sentinel'); ?></div>
                    <?php else: ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($active_ghost_traps as $trapKey => $trapVal): 
                                $trapName = is_string($trapKey) ? $trapKey : (string)$trapVal;
                            ?>
                                <div class="vgt-badge" style="background: rgba(236, 72, 153, 0.1); color: var(--vgt-neon-pink); border-color: rgba(236, 72, 153, 0.3); font-size: 11px; text-transform: none; font-family: 'Fira Code', monospace;">
                                    <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                    /<?php echo esc_html($trapName); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ACTIVE CERBERUS CONTAINMENT SENTINELS -->
            <div class="vgt-glass-panel" style="border-top: 2px solid var(--vgt-neon-red);">
                <div class="vgt-module-header">
                    <div class="vgt-module-title" style="color: var(--vgt-neon-red);">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php echo esc_html__('Containment Sentinel State', 'vgt-sentinel'); ?>
                    </div>
                </div>
                <div style="padding: 16px 20px; display:flex; flex-direction:column; gap:10px;">
                    <div class="vgt-actor-row" style="font-size:12px;">
                        <span style="color:var(--vgt-text-dim);"><?php echo esc_html__('Active Hard-TTL Bans', 'vgt-sentinel'); ?>:</span>
                        <span class="vgt-mono" style="font-weight:700; color:var(--vgt-neon-blue);"><?php echo esc_html(number_format_i18n($total_contained_xdr)); ?></span>
                    </div>
                    <div class="vgt-actor-row" style="font-size:12px;">
                        <span style="color:var(--vgt-text-dim);"><?php echo esc_html__('Permanent / Admin Bans', 'vgt-sentinel'); ?>:</span>
                        <span class="vgt-mono" style="font-weight:700; color:var(--vgt-neon-red);"><?php echo esc_html(number_format_i18n($total_bans)); ?></span>
                    </div>
                    <div class="vgt-actor-row" style="font-size:12px;">
                        <span style="color:var(--vgt-text-dim);"><?php echo esc_html__('Multi-Vector Shielding', 'vgt-sentinel'); ?>:</span>
                        <span class="vgt-badge" style="background:rgba(16,185,129,0.1); color:var(--vgt-neon-green); font-size:9px;">
                            <?php echo esc_html__('OPERATIONAL', 'vgt-sentinel'); ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

