<?php
declare(strict_types=1);
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * View: STYX Dashboard (Outbound Executioner & Audit UI)
 * Status: PLATIN VGT STATUS (Hardened UI & i18n)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['styx_enabled']);
$audit_mode = !empty($opt['styx_audit_mode']); // LERN-MODUS: Loggen, aber nicht blocken
$block_wp   = !empty($opt['styx_block_wp_telemetry']); // VGT: WP Core Telemetry Interlock
$whitelist  = $opt['styx_whitelist'] ?? '';

$table_logs = $wpdb->prefix . 'vis_styx_logs';

// --- ZERO-COST REAL DATA AGGREGATION ---
$total_blocked = 0;
$total_allowed = 0;
$unique_origins = 0;
$real_logs = [];

// Overhead-freier Schema-Check
if ( get_option( 'vgt_styx_schema_ready' ) ) {
    $suppress = $wpdb->suppress_errors(true);
    
    // Zähle blockierte Exfiltrationen
    $total_blocked = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_logs} WHERE status = 'BLOCKED'");
    
    // Zähle autorisierte Calls
    $total_allowed = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_logs} WHERE status = 'ALLOWED'");

    // Zähle kompromittierte/aktive Plugins (Origins)
    $unique_origins = (int) $wpdb->get_var("SELECT COUNT(DISTINCT origin) FROM {$table_logs}");

    // Terminal Logs laden (Die letzten 50 Calls)
    $real_logs = $wpdb->get_results("SELECT * FROM {$table_logs} ORDER BY timestamp DESC LIMIT 50");
    
    $wpdb->suppress_errors($suppress);
}

// UI Status Logik (Hardened String Assembly)
$badge_class = 'offline';
if (!$is_enabled) {
    $badge_text = __('SHIELD OFFLINE', 'vgt-sentinel');
} else {
    if ($audit_mode) {
        $badge_text = __('AUDIT MODE (LOGGING ONLY)', 'vgt-sentinel');
        $badge_class = 'pending';
    } else {
        $badge_text = __('EXECUTIONER: STRICT MODE', 'vgt-sentinel');
        $badge_class = 'active';
    }
    if ($block_wp) {
        $badge_text .= ' ' . __('+ WP BLOCKED', 'vgt-sentinel');
    }
}
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $styx_css_path = __DIR__ . '/styx/style.css';
    if (is_readable($styx_css_path)) {
        echo file_get_contents($styx_css_path);
    }
    ?>
</style>

<div class="vgt-module-container styx-core">
    
    <!-- HEADER SECTION -->
    <div class="vgt-header">
        <div class="vgt-title-group">
            <h1 class="vgt-glitch-text styx-glitch" data-text="<?php echo esc_attr__('STYX EXECUTIONER', 'vgt-sentinel'); ?>">
                <?php esc_html_e('STYX EXECUTIONER', 'vgt-sentinel'); ?>
            </h1>
            <p class="vgt-subtitle"><?php esc_html_e('Outbound Exfiltration Shield & Shadow-Router', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-status-badge <?php echo esc_attr($badge_class); ?>" id="styx-main-badge" style="<?php echo ($is_enabled && $block_wp) ? 'box-shadow: 0 0 20px rgba(188,19,254,0.3); border-color: rgba(188,19,254,0.5);' : ''; ?>">
            <span class="pulse-dot" style="<?php echo ($is_enabled && $block_wp) ? 'background-color: #bc13fe;' : ''; ?>"></span> 
            <span id="badge-text-styx"><?php echo esc_html($badge_text); ?></span>
        </div>
    </div>

    <!-- ABSOLUTE BULLETPROOF CONFIG TOGGLES -->
    <div class="vgt-master-switch-panel">
        <div class="panel-info">
            <h3><?php esc_html_e('Outbound Telemetry Control', 'vgt-sentinel'); ?></h3>
            <p><?php esc_html_e('Blockiert unautorisierte ausgehende HTTP-Requests. Verhindert, dass gehackte Plugins Daten an externe C&C-Server exfiltrieren. Nutze den Audit Mode, um das System zunächst im Lernmodus laufen zu lassen.', 'vgt-sentinel'); ?></p>
            <p style="margin-top: 10px; color: #bc13fe; font-size: 0.85rem;"><strong><?php esc_html_e('WP CORE TELEMETRY:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Schalte diesen Switch ein, um native Verbindungen zur wp.org API zu kappen (Blockt Supply-Chain Leaks & Core-Updates).', 'vgt-sentinel'); ?></p>
        </div>
        <div class="panel-actions-group">
            <!-- STYX MASTER SWITCH -->
            <label class="vgt-pure-switch styx-switch" id="toggle-container-styx">
                <input type="checkbox" name="vis_config[styx_enabled]" id="styx_enabled" value="1" <?php checked($is_enabled, true); ?>>
                <span class="vgt-pure-slider"></span>
                <div class="switch-label" id="toggle-label-styx">
                    <?php echo $is_enabled ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?>
                </div>
            </label>
            
            <!-- AUDIT MODE SWITCH -->
            <label class="vgt-pure-switch styx-audit-switch" id="toggle-container-audit">
                <input type="checkbox" name="vis_config[styx_audit_mode]" id="styx_audit_mode" value="1" <?php checked($audit_mode, true); ?>>
                <span class="vgt-pure-slider-audit"></span>
                <div class="switch-label" id="toggle-label-audit" style="color: <?php echo $audit_mode ? '#ffbd2e' : '#666'; ?>;">
                    <?php esc_html_e('AUDIT MODE', 'vgt-sentinel'); ?>
                </div>
            </label>

            <!-- WP TELEMETRY BLOCK SWITCH -->
            <label class="vgt-pure-switch styx-wp-switch" id="toggle-container-wp">
                <input type="checkbox" name="vis_config[styx_block_wp_telemetry]" id="styx_block_wp_telemetry" value="1" <?php checked($block_wp, true); ?>>
                <span class="vgt-pure-slider-wp"></span>
                <div class="switch-label" id="toggle-label-wp" style="color: <?php echo $block_wp ? '#bc13fe' : '#666'; ?>;">
                    <?php esc_html_e('BLOCK WP CORE', 'vgt-sentinel'); ?>
                </div>
            </label>
        </div>
    </div>

    <div id="styx-dynamic-content" class="<?php echo $is_enabled ? '' : 'vgt-disabled'; ?>">
        
        <!-- HIGH LEVEL KPI METRICS -->
        <div class="vgt-kpi-matrix">
            <div class="vgt-kpi-box styx-kpi-red">
                <div class="kpi-icon">
                    <svg class="vgt-icon" style="width:28px; height:28px;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-blocked"><?php echo esc_html(number_format_i18n($total_blocked)); ?></span>
                    <span class="kpi-label"><?php esc_html_e('Blocked Exfiltrations', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-fast' : ''; ?>"></div>
            </div>
            <div class="vgt-kpi-box styx-kpi-green">
                <div class="kpi-icon">
                    <svg class="vgt-icon" style="width:28px; height:28px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-allowed"><?php echo esc_html(number_format_i18n($total_allowed)); ?></span>
                    <span class="kpi-label"><?php esc_html_e('Authorized Calls', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-medium' : ''; ?>"></div>
            </div>
            <div class="vgt-kpi-box styx-kpi-purple">
                <div class="kpi-icon">
                    <svg class="vgt-icon" style="width:28px; height:28px;" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-origins"><?php echo esc_html(number_format_i18n($unique_origins)); ?></span>
                    <span class="kpi-label"><?php esc_html_e('Active Internal Origins', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-slow' : ''; ?>" style="width: 100%; opacity: 0.8; transform: none;"></div>
            </div>
        </div>

        <!-- STYX WHITELIST PANEL -->
        <div class="vgt-whitelist-panel styx-whitelist" style="margin-bottom: 30px;">
            <div class="panel-header">
                <h3><svg class="vgt-icon" style="color: #ff0055; width:20px; height:20px;" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg> <?php esc_html_e('ZERO-TRUST WHITELIST (Allowed Destinations)', 'vgt-sentinel'); ?></h3>
                <p><?php esc_html_e('Trage hier die Domains ein, die kontaktiert werden dürfen (z.B. Lizenzen). Alles andere wird terminiert. Wildcards erlaubt (*.google.com).', 'vgt-sentinel'); ?></p>
            </div>
            <div class="panel-body">
                <textarea name="vis_config[styx_whitelist]" id="styx_whitelist" class="vgt-textarea" placeholder="<?php echo esc_attr("api.rankmath.com\n*.wordpress.org"); ?>" rows="4" spellcheck="false"><?php echo esc_textarea($whitelist); ?></textarea>
                <div class="vgt-form-hint"><?php esc_html_e('Hinweis: Core WordPress APIs (api.wordpress.org) werden nativ zugelassen, es sei denn der \'BLOCK WP CORE\' Switch ist aktiviert.', 'vgt-sentinel'); ?></div>
            </div>
        </div>

        <!-- TACTICAL EVENT STREAM (DATA GRID) -->
        <div class="vgt-terminal">
            <div class="vgt-term-header">
                <div class="vgt-term-buttons">
                    <span class="btn-red"></span><span class="btn-yellow"></span><span class="btn-green"></span>
                </div>
                <div class="vgt-term-title"><?php esc_html_e('styx@vgt-ai:~/outbound-traffic$ tail -f /var/log/styx_exfiltration.log', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-term-body" id="styx-terminal" style="overflow-x: auto;">
                <?php if ($is_enabled && !empty($real_logs)): ?>
                    <table class="styx-data-grid">
                        <thead>
                            <tr>
                                <th width="15%"><?php esc_html_e('TIMESTAMP', 'vgt-sentinel'); ?></th>
                                <th width="20%"><?php esc_html_e('ORIGIN (PLUGIN/THEME)', 'vgt-sentinel'); ?></th>
                                <th width="40%"><?php esc_html_e('TARGET HOST (DESTINATION)', 'vgt-sentinel'); ?></th>
                                <th width="15%"><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($real_logs as $log): 
                            $time = wp_date('H:i:s', strtotime($log->timestamp));
                            $is_blocked = ($log->status === 'BLOCKED');
                            $status_color = $is_blocked ? '#ff0055' : '#00ffaa';
                            $origin_color = str_contains((string)$log->origin, 'UNKNOWN') ? '#888' : '#bc13fe';
                        ?>
                            <tr>
                                <td class="term-time">[<?php echo esc_html($time); ?>]</td>
                                <td style="color: <?php echo esc_attr($origin_color); ?>; font-weight: bold; font-size: 11px;"><?php echo esc_html((string)$log->origin); ?></td>
                                <td style="color: #ccc;"><?php echo esc_html((string)$log->host); ?> <br><span style="font-size: 9px; opacity: 0.5;"><?php echo esc_html((string)$log->url); ?></span></td>
                                <td>
                                    <span class="vgt-status-pill" style="border-color: <?php echo esc_attr($status_color); ?>; color: <?php echo esc_attr($status_color); ?>; background: rgba(<?php echo $is_blocked ? '255,0,85' : '0,255,170'; ?>, 0.1);">
                                        <?php echo esc_html((string)$log->status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif ($is_enabled): ?>
                    <code class="sys-boot">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[SYSTEM] Styx Executioner initialized. Outbound matrix is clean.', 'vgt-sentinel'); ?></code>
                    <code class="log-info">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[SYSTEM] Monitoring internal processes...', 'vgt-sentinel'); ?><span class="cursor-blink">_</span></code>
                <?php else: ?>
                    <code class="log-critical">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[ERROR] Styx Executioner shutdown. Outbound traffic is completely unmonitored.', 'vgt-sentinel'); ?></code>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT)
     ========================================================================================= -->
<script>
    <?php 
    $styx_js_path = __DIR__ . '/styx/script.js';
    if (is_readable($styx_js_path)) {
        include $styx_js_path;
    }
    ?>
</script>
