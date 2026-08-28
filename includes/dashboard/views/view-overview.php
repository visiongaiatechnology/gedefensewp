<?php
declare(strict_types=1);
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * View: DASHBOARD OVERVIEW (COMMAND CENTER)
 * Status: PLATIN VGT STATUS (Hardened UI & i18n)
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// =========================================================================================
// 1. DATA HARVESTING & CONFIGURATION
// =========================================================================================

$table_logs = defined('VIS_TABLE_LOGS') ? $wpdb->prefix . VIS_TABLE_LOGS : $wpdb->prefix . 'vis_omega_logs';
$table_bans = defined('VIS_TABLE_BANS') ? $wpdb->prefix . VIS_TABLE_BANS : $wpdb->prefix . 'vis_apex_bans';

$opt = get_option('vis_config', []);

$vault_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/vgt-vault/zeus/' : dirname( ABSPATH ) . '/wp-content/vgt-vault/zeus/';
$zeus_active = file_exists( $vault_dir . 'zeus-waf.php' );

$aegis_blocks = 0;
$ai_threats = 0;

$suppress = $wpdb->suppress_errors(true);

// Top Stats Harvesting (Sentinel Main Tables)
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'") === $table_logs) {
    $aegis_logs_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_logs} WHERE module IN ('AEGIS', 'CERBERUS', 'AIRLOCK', 'GHOST_TRAP', 'TITAN', 'ZEUS')");
    $aegis_bans_count = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_bans}'") === $table_bans) {
        $aegis_bans_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_bans}");
    }
    $aegis_blocks = $aegis_logs_count + $aegis_bans_count;
    
    $ai_threats = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_logs} WHERE module IN ('PROMETHEUS', 'NEMESIS', 'STYX', 'ORACLE') OR type IN ('ANOMALY', 'PREDICTIVE_STRIKE', 'POISON', 'EXFILTRATION_ATTEMPT', 'CANARY') OR message LIKE '%AI Detected Threat%'");
}

// Prometheus Dedicated Telemetry Harvesting
$prom_tables = [
    $wpdb->prefix . 'vgt_prometheus_logs',
    $wpdb->prefix . 'vis_prometheus_logs',
    $wpdb->prefix . 'prometheus_logs'
];
$prom_count = 0;
foreach ( $prom_tables as $ptable ) {
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $ptable ) ) === $ptable ) {
        $prom_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ptable}" );
        break;
    }
}
$ai_threats += $prom_count;

$wpdb->suppress_errors($suppress);

// Fallbacks
$ai_threats += (int) wp_cache_get('vgt_prometheus_strikes');
$ai_threats += (int) wp_cache_get('vgt_active_tarpits');

// =========================================================================================
// 2. INTEGRITY SCANNER LOGIC (ACTION ROW)
// =========================================================================================
$report = get_option('vis_scan_report', false);
$integrity_status = 'UNKNOWN';
$ui_int_color  = '#888888';
$ui_int_sub    = __('No Scan Data', 'vgt-sentinel');
$ui_int_desc   = __('Bitte starten Sie einen manuellen Integritäts-Scan.', 'vgt-sentinel');
$integrity_clean  = false;
$ui_int_icon_svg  = '<line x1="5" y1="12" x2="19" y2="12"></line>'; // Default (Minus)

if ($report && is_array($report)) {
    $status = $report['status'] ?? 'unknown';
    if ($status === 'warning') {
        $integrity_status = 'ANOMALY';
        $ui_int_color  = '#d63638'; 
        $ui_int_sub    = __('State: Violated', 'vgt-sentinel');
        
        $changes_count = count($report['changes'] ?? []);
        $ui_int_desc   = sprintf(
            /* translators: %d: Number of anomalies */
            _n('%d Anomalie erkannt!', '%d Anomalien erkannt!', $changes_count, 'vgt-sentinel'),
            $changes_count
        );
        $ui_int_icon_svg = '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>';
    } elseif ($status === 'clean' || $status === 'init') {
        $integrity_status = 'SECURE';
        $ui_int_color  = '#00ffaa'; 
        $ui_int_sub    = __('State: Valid', 'vgt-sentinel');
        $ui_int_desc   = __('Systemintegrität verifiziert.', 'vgt-sentinel');
        $integrity_clean  = true;
        $ui_int_icon_svg = '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>';
    }
}

// =========================================================================================
// 3. SUBSYSTEM LOGIC (4 PILLARS)
// =========================================================================================

// PROMETHEUS & NEMESIS
$prom_enabled = !empty($opt['prometheus_enabled']);
$nemesis_enabled = !empty($opt['nemesis_enabled']);

// TITAN HARDENING
$titan_active_count = 0;
$found_titan_keys = 0;
$titan_status_html = '';
$known_titan_labels = [
    'titan_xmlrpc'     => __('XML-RPC Locked', 'vgt-sentinel'), 
    'titan_rest_api'   => __('REST API Guard', 'vgt-sentinel'), 
    'titan_wp_version' => __('Version Cloak', 'vgt-sentinel'), 
    'titan_file_edit'  => __('Editor Disabled', 'vgt-sentinel')
];

foreach ($opt as $key => $val) {
    if (strpos($key, 'titan_') === 0 && !empty($val) && $val !== '0' && $key !== 'titan_enabled') {
        $titan_active_count++;
        $found_titan_keys++;
        if ($found_titan_keys <= 4) {
            $label = isset($known_titan_labels[$key]) ? $known_titan_labels[$key] : ucwords(str_replace(['titan_', '_'], ['', ' '], $key));
            $titan_status_html .= "<div class='vgt-checklist-item'><svg class='vgt-icon' style='color:#00ffaa; width:12px; height:12px;' viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 10 0v4'></path></svg> " . esc_html($label) . "</div>";
        }
    }
}
if ($titan_active_count === 0 && !empty($opt['titan_enabled'])) {
    $titan_active_count = 4;
    $titan_status_html = "<div class='vgt-checklist-item'><svg class='vgt-icon' style='color:#00ffaa; width:12px; height:12px;' viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 10 0v4'></path></svg> " . esc_html__('Kernel Hardening', 'vgt-sentinel') . "</div><div class='vgt-checklist-item'><svg class='vgt-icon' style='color:#00ffaa; width:12px; height:12px;' viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 10 0v4'></path></svg> " . esc_html__('API Security Guard', 'vgt-sentinel') . "</div>";
} elseif ($titan_active_count === 0) {
    $titan_status_html = "<div class='vgt-checklist-item'><svg class='vgt-icon' style='color:#ff4d4d; width:12px; height:12px;' viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 9.9-1'></path></svg> " . esc_html__('System Vulnerable', 'vgt-sentinel') . "</div>";
}
$titan_score = min(100, max(0, ($titan_active_count >= 4) ? 100 : ($titan_active_count * 25)));

// VLP (VISION LEGAL PRO)
$vlp_is_running = class_exists('VisionLegalPro_Core') || class_exists('VIS_Bridge_Vision_Legal_Pro') || !empty($opt['vlp_enabled']);
$vlp_settings = get_option('vlp_settings', []);
$vlp_shadow = ($vlp_is_running || class_exists('VLP_Shadow_Interceptor') || !empty($vlp_settings['shadow_net'])) ? __('ENGAGED', 'vgt-sentinel') : __('OFFLINE', 'vgt-sentinel');
$vlp_privacy = ($vlp_is_running || class_exists('VLP_Gatekeeper') || !empty($vlp_settings['privacy_shield'])) ? __('ENFORCED', 'vgt-sentinel') : __('BYPASSED', 'vgt-sentinel');

// =========================================================================================
// 4. GLOBAL SECURITY EVENTS (SENTINEL + PROMETHEUS MERGE)
// =========================================================================================

// 1. SENTINEL MAIN LOGS
$sentinel_logs = [];
$suppress = $wpdb->suppress_errors(true);
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'") === $table_logs) {
    $raw_sentinel = $wpdb->get_results("SELECT * FROM {$table_logs} ORDER BY id DESC LIMIT 50");
    if($raw_sentinel) {
        foreach($raw_sentinel as $row) {
            $sentinel_logs[] = (object) [
                'timestamp' => $row->timestamp,
                'module'    => $row->module,
                'ip'        => !empty($row->ip_address) ? $row->ip_address : (!empty($row->ip) ? $row->ip : 'N/A'),
                'message'   => !empty($row->details) ? $row->details : (!empty($row->message) ? $row->message : $row->type),
                'source'    => 'sentinel'
            ];
        }
    }
}

// 2. PROMETHEUS TELEMETRIE
$prom_logs = [];
foreach ( $prom_tables as $ptable ) {
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $ptable ) ) === $ptable ) {
        $raw_prom = $wpdb->get_results("SELECT * FROM {$ptable} ORDER BY id DESC LIMIT 25");
        if($raw_prom) {
            foreach($raw_prom as $row) {
                $prom_logs[] = (object) [
                    'timestamp' => $row->timestamp,
                    'module'    => 'PROMETHEUS',
                    'ip'        => !empty($row->ip_address) ? $row->ip_address : 'UNKNOWN',
                    'message'   => !empty($row->details) ? $row->details : $row->type,
                    'source'    => 'prometheus'
                ];
            }
        }
        break;
    }
}

// 3. MERGE & SORT
$merged_logs = array_merge($sentinel_logs, $prom_logs);
usort($merged_logs, function($a, $b) {
    return strtotime($b->timestamp) - strtotime($a->timestamp);
});
$final_logs = array_slice($merged_logs, 0, 50);

// 4. ORACLE THREAT INTELLIGENCE (LIMIT optimiert auf 10 für Omnipresent Logging Übersicht)
$oracle_logs = [];
$oracle_table = $wpdb->prefix . 'vis_oracle_patterns';
if ($wpdb->get_var("SHOW TABLES LIKE '{$oracle_table}'") === $oracle_table) {
    // VGT LOGIC GATE: Begrenzung auf exakt 10 für perfekte UI-Ausbalancierung.
    $raw_oracle = $wpdb->get_results("SELECT * FROM {$oracle_table} ORDER BY id DESC LIMIT 10");
    if($raw_oracle) {
        $oracle_logs = $raw_oracle;
    }
}
$wpdb->suppress_errors($suppress);

?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $overview_css_path = __DIR__ . '/overview/style.css';
    if (is_readable($overview_css_path)) {
        echo file_get_contents($overview_css_path);
    }
    ?>
</style>

<!-- =========================================================================================
     VIEW CONTENT - VGT SENTINEL CYBER DEFENSE COCKPIT
     ========================================================================================= -->
<div class="vis-overview-container vgt-command-center">

    <?php
    // Calculate Security Score & Status Matrix (Parity with view-systatus)
    $score = 0;
    $score_details = [];

    // Aegis (20%)
    if (!empty($opt['aegis_enabled'])) {
        $score += 20;
        $score_details[] = ['name' => 'Aegis WAF', 'active' => true, 'weight' => 20, 'tab' => 'aegis'];
    } else {
        $score_details[] = ['name' => 'Aegis WAF', 'active' => false, 'weight' => 20, 'tab' => 'aegis'];
    }

    // Zeus (25%)
    $zeus_config = get_option('vis_zeus_config', []);
    $zeus_active_check = !empty($zeus_config);
    if ($zeus_active_check) {
        $score += 25;
        $score_details[] = ['name' => 'Zeus Defender', 'active' => true, 'weight' => 25, 'tab' => 'zeus'];
    } else {
        $score_details[] = ['name' => 'Zeus Defender', 'active' => false, 'weight' => 25, 'tab' => 'zeus'];
    }

    // Prometheus (15%)
    if (!empty($opt['prometheus_enabled'])) {
        $score += 15;
        $score_details[] = ['name' => 'Prometheus AI', 'active' => true, 'weight' => 15, 'tab' => 'prometheus'];
    } else {
        $score_details[] = ['name' => 'Prometheus AI', 'active' => false, 'weight' => 15, 'tab' => 'prometheus'];
    }

    // Titan (15%)
    if (!empty($opt['titan_enabled'])) {
        $score += 15;
        $score_details[] = ['name' => 'Titan Hardening', 'active' => true, 'weight' => 15, 'tab' => 'titan'];
    } else {
        $score_details[] = ['name' => 'Titan Hardening', 'active' => false, 'weight' => 15, 'tab' => 'titan'];
    }

    // Hades (10%)
    if (!empty($opt['hades_enabled'])) {
        $score += 10;
        $score_details[] = ['name' => 'Hades Stealth', 'active' => true, 'weight' => 10, 'tab' => 'hades'];
    } else {
        $score_details[] = ['name' => 'Hades Stealth', 'active' => false, 'weight' => 10, 'tab' => 'hades'];
    }

    // Cerberus (10%)
    if (class_exists('VIS_Cerberus')) {
        $score += 10;
        $score_details[] = ['name' => 'Cerberus Perimeter', 'active' => true, 'weight' => 10, 'tab' => 'cerberus'];
    } else {
        $score_details[] = ['name' => 'Cerberus Perimeter', 'active' => false, 'weight' => 10, 'tab' => 'cerberus'];
    }

    // Airlock (5%)
    if (!isset($opt['airlock_enabled']) || !empty($opt['airlock_enabled'])) {
        $score += 5;
        $score_details[] = ['name' => 'Airlock Ingress', 'active' => true, 'weight' => 5, 'tab' => 'airlock'];
    } else {
        $score_details[] = ['name' => 'Airlock Ingress', 'active' => false, 'weight' => 5, 'tab' => 'airlock'];
    }

    // Scoring Meta
    if ($score >= 90) {
        $score_status = __('MAXIMALER SCHUTZ', 'vgt-sentinel');
        $score_desc = __('Herausragende Abwehrbereitschaft. Alle kritischen Module und Perimeter-Schilde sind aktiv. Die Seite läuft unter maximalem Schutz.', 'vgt-sentinel');
        $score_color = '#10b981'; // Green
    } elseif ($score >= 70) {
        $score_status = __('OPTIMALER SCHUTZ', 'vgt-sentinel');
        $score_desc = __('Hohe Sicherheitsabdeckung. Die primären Firewalls und Härtungskomponenten sind aktiv. Einige Deception-Schilde oder periphere WAFs könnten noch hinzugeschaltet werden.', 'vgt-sentinel');
        $score_color = '#3b82f6'; // Blue
    } elseif ($score >= 40) {
        $score_status = __('MINIMALER SCHUTZ', 'vgt-sentinel');
        $score_desc = __('Basisüberwachung ist aktiv. Es wird dringend empfohlen, Aegis WAF, Titan Hardening und Zeus Defender im Setup-Wizard oder Command Center zu konfigurieren.', 'vgt-sentinel');
        $score_color = '#f59e0b'; // Orange
    } else {
        $score_status = __('GEFÄHRDET (LOW)', 'vgt-sentinel');
        $score_desc = __('Kritischer Zustand! Fast alle Sicherheitsmodule sind inaktiv. Das System bietet derzeit keinen ausreichenden Schutz vor Targeted Exploits oder Brute-Force-Angriffen.', 'vgt-sentinel');
        $score_color = '#ef4444'; // Red
    }

    $dashoffset = 439 - (439 * $score / 100);
    ?>

    <!-- FUTURISTIC HUD VITAL BAR -->
    <div class="vis-hud-bar" style="border-left-color: <?php echo esc_attr($score_color); ?>;">
        <div class="vis-hud-title">
            <svg class="vgt-icon" style="width:20px; height:20px; color:<?php echo esc_attr($score_color); ?>;" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
            <?php esc_html_e('GEDEFENSE COMMAND CENTER', 'vgt-sentinel'); ?>
        </div>
        <div class="vis-hud-grid">
            <div class="vis-hud-item">
                <span class="vis-hud-label"><?php esc_html_e('ACTIVE SHIELD LEVEL:', 'vgt-sentinel'); ?></span>
                <span class="vis-hud-value" style="color: <?php echo esc_attr($score_color); ?>;"><?php echo esc_html((string)$score); ?>%</span>
            </div>
            <div class="vis-hud-item">
                <span class="vis-hud-label"><?php esc_html_e('NOC CORE STATUS:', 'vgt-sentinel'); ?></span>
                <span class="vis-hud-dot pulse" style="background: <?php echo esc_attr($score_color); ?>;"></span>
                <span class="vis-hud-value"><?php esc_html_e('ONLINE', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vis-hud-item">
                <span class="vis-hud-label"><?php esc_html_e('SYSTEM EDITION:', 'vgt-sentinel'); ?></span>
                <span class="vis-hud-value" style="color: #10b981;"><?php esc_html_e('OPEN CORE', 'vgt-sentinel'); ?></span>
            </div>
        </div>
    </div>

    <!-- MAIN TWO COLUMN Cockpit DISPLAY -->
    <div class="vis-overview-layout">
        
        <!-- LEFT MAIN PANEL -->
        <div class="vis-main-panel">
            
            <!-- SECURITY SCORING MATRIX -->
            <div class="vis-cyber-card" style="border-top: 3px solid <?php echo esc_attr($score_color); ?>;">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg class="vgt-icon" style="width:18px; height:18px; color:<?php echo esc_attr($score_color); ?>;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                        <?php esc_html_e('COCKPIT PROTECTION MATRIX', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge" style="background: <?php echo esc_attr($score_color); ?>20; color: <?php echo esc_attr($score_color); ?>; border: 1px solid <?php echo esc_attr($score_color); ?>40;"><?php echo esc_html($score_status); ?></span>
                </div>
                
                <div class="vgt-score-panel">
                    <div class="vgt-score-gauge-box">
                        <div class="vgt-gauge-circle-container">
                            <svg viewBox="0 0 160 160" style="width: 160px; height: 160px; display: block;">
                                <circle class="vgt-gauge-circle-bg" cx="80" cy="80" r="70"></circle>
                                <circle class="vgt-gauge-circle-val" cx="80" cy="80" r="70" 
                                        style="stroke: <?php echo esc_attr($score_color); ?>; stroke-dasharray: 439; stroke-dashoffset: <?php echo esc_attr((string)$dashoffset); ?>;"></circle>
                            </svg>
                            <div class="vgt-gauge-number" style="text-shadow: 0 0 15px <?php echo esc_attr($score_color); ?>50;"><?php echo esc_html((string)$score); ?>%</div>
                        </div>
                    </div>
                    <div class="vgt-score-detail-box">
                        <div class="vgt-score-header">
                            <div class="vgt-score-status-text" style="color: <?php echo esc_attr($score_color); ?>;"><?php echo esc_html($score_status); ?></div>
                            <div class="vgt-score-sub-desc"><?php echo esc_html($score_desc); ?></div>
                        </div>
                        <div class="vgt-matrix-grid">
                            <?php foreach ($score_details as $detail): ?>
                                <div class="vgt-matrix-item <?php echo $detail['active'] ? 'active' : 'inactive'; ?>">
                                    <div class="vgt-matrix-info">
                                        <div class="vgt-matrix-name"><?php echo esc_html($detail['name']); ?></div>
                                        <div class="vgt-matrix-status" style="color: <?php echo $detail['active'] ? '#10b981' : '#ef4444'; ?>;">
                                            <?php echo $detail['active'] ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('OFFLINE', 'vgt-sentinel'); ?>
                                            <span style="opacity: 0.5; color: #888; font-family: monospace; font-size: 9px; margin-left: 5px;">(+<?php echo esc_html((string)$detail['weight']); ?>%)</span>
                                        </div>
                                    </div>
                                    <a href="?page=vgt-suite&tab=<?php echo esc_attr($detail['tab']); ?>" class="vgt-matrix-action">
                                        <?php esc_html_e('CONFIG', 'vgt-sentinel'); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COMBINED TRAFFIC PROTOCOLS -->
            <div class="vis-cyber-card">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg class="vgt-icon" style="width:18px; height:18px; color:#00f2ff;" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        <?php esc_html_e('GLOBAL COGNITIVE INCIDENT PROTOCOLS', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge vis-badge-info"><?php esc_html_e('COMBINED LOGS', 'vgt-sentinel'); ?></span>
                </div>
                
                <?php if(empty($final_logs)): ?>
                    <div style="padding:40px; text-align:center; color:#888;">
                        <svg class="vgt-icon" style="width:50px; height:50px; margin-bottom:15px; display:block; color:#10b981; margin-left:auto; margin-right:auto;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <h4 style="color:#fff; margin:0 0 5px 0;"><?php esc_html_e('SHIELD CLEAN', 'vgt-sentinel'); ?></h4>
                        <p style="margin:0; font-size:12px;"><?php esc_html_e('Keine Sicherheitsvorfälle im Protokoll verzeichnet.', 'vgt-sentinel'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="vis-table-wrapper">
                        <table class="vis-table">
                            <thead>
                                <tr>
                                    <th width="150"><?php esc_html_e('TIMESTAMP', 'vgt-sentinel'); ?></th>
                                    <th width="140"><?php esc_html_e('SOURCE', 'vgt-sentinel'); ?></th>
                                    <th width="130"><?php esc_html_e('IP ADDRESS', 'vgt-sentinel'); ?></th>
                                    <th><?php esc_html_e('EVENT DETAILS', 'vgt-sentinel'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($final_logs as $log): 
                                $badge_style = 'background:rgba(255,255,255,0.1); color:#fff; border: 1px solid rgba(255,255,255,0.2);';
                                if ($log->module === 'NEMESIS') $badge_style = 'background:rgba(188,19,254,0.15); color:#bc13fe; border:1px solid rgba(188,19,254,0.4);';
                                elseif ($log->module === 'PROMETHEUS') $badge_style = 'background:rgba(0,242,255,0.15); color:#00f2ff; border:1px solid rgba(0,242,255,0.4);';
                                elseif ($log->module === 'AEGIS') $badge_style = 'background:rgba(0,255,170,0.15); color:#00ffaa; border:1px solid rgba(0,255,170,0.4);';
                                elseif ($log->module === 'ZEUS') $badge_style = 'background:rgba(255,0,60,0.15); color:#ff003c; border:1px solid rgba(255,0,60,0.4);';
                                elseif ($log->module === 'STYX') $badge_style = 'background:rgba(255,0,60,0.15); color:#ff003c; border:1px solid rgba(255,0,60,0.4);';
                            ?>
                                <tr>
                                    <td class="text-mono" style="color:#777; font-size:11px;">
                                        <?php echo esc_html((string)$log->timestamp); ?>
                                    </td>
                                    <td>
                                        <span class="vis-badge" style="<?php echo esc_attr($badge_style); ?> font-weight:700; font-size:9px; padding: 2px 6px;">
                                            <?php echo esc_html((string)$log->module); ?>
                                        </span>
                                    </td>
                                    <td class="text-mono" style="color:#00f2ff; font-size:11px;">
                                        <?php echo esc_html((string)$log->ip); ?>
                                    </td>
                                    <td style="color:#aaa; font-size:12px; line-height: 1.4;">
                                        <?php echo esc_html((string)$log->message); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ORACLE DYNAMIC INTELLIGENCE FEED -->
            <div class="vis-cyber-card" style="border-top: 2px solid #00f2ff;">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg class="vgt-icon" style="width:18px; height:18px; color:#00f2ff;" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                        <?php esc_html_e('ORACLE INTELLIGENCE AUDIT FEED', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge vis-badge-info"><?php esc_html_e('REAL-TIME', 'vgt-sentinel'); ?></span>
                </div>

                <?php if(empty($oracle_logs)): ?>
                    <div style="padding:40px; text-align:center; color:#888;">
                        <svg class="vgt-icon" style="width:50px; height:50px; margin-bottom:15px; display:block; color:#00f2ff; opacity: 0.3; margin-left:auto; margin-right:auto;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <h4 style="color:#fff; margin:0 0 5px 0;"><?php esc_html_e('ORACLE STANDBY', 'vgt-sentinel'); ?></h4>
                        <p style="margin:0; font-size:12px;"><?php esc_html_e('Das AI-Modell ist bereit. Es wartet auf eingehende Request-Analysen.', 'vgt-sentinel'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="vgt-oracle-list" style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach($oracle_logs as $olog): 
                            $is_block = ($olog->type === 'ZERO_DAY_BLOCK');
                            $event_class = $is_block ? 'is-block' : 'is-safe';
                            $verdict_label = $is_block ? __('BLOCK', 'vgt-sentinel') : __('SAFE', 'vgt-sentinel');
                            $verdict_color = $is_block ? '#ff003c' : '#00ffaa';
                            $title_icon_path = $is_block ? '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>' : '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>';
                        ?>
                            <div class="vgt-oracle-event <?php echo esc_attr($event_class); ?>">
                                <div class="vgt-oracle-header vgt-accordion-trigger">
                                    <span class="vgt-oracle-time"><?php echo esc_html((string)$olog->timestamp); ?></span>
                                    <span class="vgt-oracle-ip"><?php esc_html_e('TARGET IP:', 'vgt-sentinel'); ?> <?php echo esc_html((string)$olog->ip); ?></span>
                                    <span class="vgt-oracle-status"><?php esc_html_e('VERDICT:', 'vgt-sentinel'); ?> <span style="color:<?php echo esc_attr($verdict_color); ?>;"><?php echo esc_html($verdict_label); ?></span></span>
                                    <span class="vgt-accordion-icon"><svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg></span>
                                </div>
                                
                                <div class="vgt-oracle-body-wrapper">
                                    <div class="vgt-oracle-body">
                                        <div class="vgt-oracle-reasoning">
                                            <div class="vgt-oracle-title">
                                                <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><?php echo wp_kses_post($title_icon_path); ?></svg> <?php esc_html_e('HARMONY REASONING (AI AUDIT)', 'vgt-sentinel'); ?>
                                            </div>
                                            <div class="vgt-oracle-text">
                                                <?php echo wp_kses_post(nl2br(esc_html((string)$olog->ai_reason))); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="vgt-oracle-payload">
                                            <div class="vgt-oracle-title" style="color: #888;">
                                                <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg> <?php esc_html_e('RAW PAYLOAD (INTERCEPTED)', 'vgt-sentinel'); ?>
                                            </div>
                                            <div class="vgt-payload-container">
                                                <pre><code><?php echo esc_html((string)$olog->message); ?></code></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT SIDEBAR PANEL -->
        <div class="vis-sidebar-panel">
            
            <!-- CORE VITALITY GRAPH / SHIELD -->
            <div class="vis-cyber-card vis-card-aegis">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg class="vgt-icon" style="width:18px; height:18px; color:#00ffaa;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <?php esc_html_e('CORE SHIELD VITALITY', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge vis-badge-success"><?php esc_html_e('SHIELDED', 'vgt-sentinel'); ?></span>
                </div>
                
                <div class="vis-hero-graph">
                    <div class="vis-pulse-container">
                        <div class="vis-pulse-core"></div>
                        <div class="vis-pulse-ring ring-1"></div>
                        <div class="vis-pulse-ring ring-2"></div>
                    </div>
                </div>
                
                <div class="vis-hero-stats">
                    <div class="stat-group">
                        <span class="stat-value"><?php echo esc_html(number_format_i18n($aegis_blocks)); ?></span>
                        <span class="stat-label"><?php esc_html_e('WAF Blocked', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="stat-group">
                        <span class="stat-value" style="color: #bc13fe;"><?php echo esc_html(number_format_i18n($ai_threats)); ?></span>
                        <span class="stat-label"><?php esc_html_e('AI Strikes', 'vgt-sentinel'); ?></span>
                    </div>
                </div>
            </div>

            <!-- INTEGRITY baseline status -->
            <div class="vis-cyber-card vis-card-integrity-baseline">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg class="vgt-icon" style="width:18px; height:18px; color:#00f2ff;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?php esc_html_e('INTEGRITY baseline', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge" style="background: <?php echo esc_attr($ui_int_color); ?>20; color: <?php echo esc_attr($ui_int_color); ?>; border: 1px solid <?php echo esc_attr($ui_int_color); ?>40;"><?php echo esc_html($integrity_status); ?></span>
                </div>
                
                <div class="vis-integrity-sidebar-detail">
                    <div class="vis-integrity-circle-box" style="color: <?php echo esc_attr($ui_int_color); ?>;">
                        <svg class="vgt-icon" style="width:24px; height:24px;" viewBox="0 0 24 24">
                            <?php echo wp_kses_post($ui_int_icon_svg); ?>
                        </svg>
                    </div>
                    <p style="margin: 0; font-size:12px; color:#aaa; line-height: 1.5;">
                        <?php echo esc_html($ui_int_desc); ?><br>
                        <small style="opacity: 0.6; font-family: monospace;">[<?php echo esc_html($ui_int_sub); ?>]</small>
                    </p>
                    <a href="?page=vgt-suite&tab=integrity" class="vis-btn-sidebar-action <?php echo ($integrity_status === 'ANOMALY') ? 'vis-btn-sidebar-danger' : ''; ?>">
                        <?php echo ($integrity_status === 'ANOMALY') ? esc_html__('ANOMALIEN BEHEBEN', 'vgt-sentinel') : esc_html__('SCAN MANAGER', 'vgt-sentinel'); ?>
                        <svg class="vgt-icon" style="width:12px; height:12px;" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>
            </div>

            <!-- OPEN CORE ACTIVE DEFENSE SHOWCASE -->
            <div class="vis-cyber-card vis-card-license">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg class="vgt-icon" style="width:18px; height:18px; color:#3b82f6;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <?php esc_html_e('OPEN CORE EDITION', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight:700;"><?php esc_html_e('SOVEREIGN (AGPLv3)', 'vgt-sentinel'); ?></span>
                </div>
                
                <p style="font-size: 12px; color: #888; margin-top:0; margin-bottom: 18px; line-height:1.5;">
                    <?php esc_html_e('Unbegrenzte, server-eigene Cyber-Abwehr ohne externe Cloud-Abhängigkeiten oder Telemetrie-Leaks. 100% DSGVO-souverän und latenzfrei.', 'vgt-sentinel'); ?>
                </p>
                
                <div class="vis-value-box">
                    <div class="value-amount" style="color:#10b981;"><?php esc_html_e('369€', 'vgt-sentinel'); ?><span style="font-size:12px; color:#888; font-weight:normal;"> / mo</span></div>
                    <div class="value-text">
                        <?php esc_html_e('SaaS Equivalent Value', 'vgt-sentinel'); ?>
                        <small style="color:#3b82f6; font-weight:600;"><?php esc_html_e('Included Free in Open Core', 'vgt-sentinel'); ?></small>
                    </div>
                </div>
                
                <ul class="vis-feature-list" style="margin-top:16px;">
                    <li>
                        <svg class="vgt-icon" style="color:#10b981; width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('Zero-Latency Pre-Boot Kernel WAF', 'vgt-sentinel'); ?>
                    </li>
                    <li>
                        <svg class="vgt-icon" style="color:#10b981; width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('Prometheus Heuristic AI & Malware Guard', 'vgt-sentinel'); ?>
                    </li>
                    <li>
                        <svg class="vgt-icon" style="color:#10b981; width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('Nemesis Deception Grid & Tarpit Traps', 'vgt-sentinel'); ?>
                    </li>
                    <li>
                        <svg class="vgt-icon" style="color:#10b981; width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('Morpheus RASP Runtime Sandboxing', 'vgt-sentinel'); ?>
                    </li>
                    <li>
                        <svg class="vgt-icon" style="color:#3b82f6; width:14px; height:14px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                        <?php esc_html_e('Extensible Add-On Hub (VLP, Builder, SEO)', 'vgt-sentinel'); ?>
                    </li>
                </ul>
            </div>
            
        </div>
        
    </div>

</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT VIA INCLUDE FÜR EVENT DELEGATION)
     ========================================================================================= -->
<script>
    <?php 
    $overview_js_path = __DIR__ . '/overview/script.js';
    if (is_readable($overview_js_path)) {
        include $overview_js_path;
    }
    ?>
</script>
