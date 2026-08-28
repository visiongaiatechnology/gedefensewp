<?php
declare(strict_types=1);
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * View: PROMETHEUS Dashboard (Predictive Intelligence UI)
 * Status: PLATIN VGT STATUS (Hardened UI & i18n)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['prometheus_enabled']);

// VGT ISOLATION: Greife auf die dedizierte Prometheus-Tabelle zu
$table_logs = $wpdb->prefix . 'vis_prometheus_logs';

// --- ZERO-COST REAL DATA AGGREGATION ---
$predictive_strikes = (int) wp_cache_get( 'vgt_prometheus_strikes' );
$total_anomalies = 0;
$global_entropy = 0;
$real_logs = [];

// VGT PLATINUM: Sovereign Whitelist Variable extrahieren
$whitelist_ips = $opt['prometheus_whitelist_ips'] ?? '';

// VGT DYNAMIC CONFIG: Lade Prometheus Settings oder nutze Engine Defaults
$prom_config = get_option('vis_prometheus_config', []);
$cfg_ehs = $prom_config['event_horizon_score']   ?? 100.0;
$cfg_ihs = $prom_config['infra_horizon_score']   ?? 150.0;
$cfg_icw = $prom_config['infra_cooldown_window'] ?? 3600;
$cfg_sdr = $prom_config['score_decay_rate']      ?? 0.2;
$cfg_sdw = $prom_config['score_decay_window']    ?? 300;
$cfg_pm  = $prom_config['penalty_method']        ?? 30.0;
$cfg_pp  = $prom_config['penalty_params']        ?? 15.0;
$cfg_pr  = $prom_config['penalty_regex']         ?? 50.0;
$cfg_p4  = $prom_config['penalty_404']           ?? 25.0;
$cfg_pa  = $prom_config['penalty_auth']          ?? 40.0;
$cfg_pb  = $prom_config['penalty_burst']         ?? 20.0;
$cfg_pf  = $prom_config['penalty_freq']          ?? 10.0;
$cfg_pro = $prom_config['penalty_rotation']      ?? 25.0;


// VGT PLATINUM: Overhead-freier Schema-Check
if ( get_option( 'vgt_prometheus_logs_schema_verified' ) ) {
    $suppress = $wpdb->suppress_errors(true);
    
    // Zhle Anomalien aus den Telemetrie-Logs
    $total_anomalies = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND type = %s", 
        'PROMETHEUS', 'ANOMALY'
    ));
    
    // Harte DB-Verifizierung für Predictive Strikes
    $db_strikes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND type = %s", 
        'PROMETHEUS', 'PREDICTIVE_STRIKE'
    ));
    if ($db_strikes > $predictive_strikes) $predictive_strikes = $db_strikes;

    // Berechne Global Threat Entropy (Stress Level der letzten 24h)
    $recent_strikes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND timestamp >= NOW() - INTERVAL 24 HOUR", 
        'PROMETHEUS'
    ));
    $global_entropy = min(100, ($recent_strikes * 10) + ($total_anomalies > 0 ? 5 : 0));

    // Terminal Logs laden
    $real_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_logs} WHERE module = %s ORDER BY timestamp DESC LIMIT 15", 
        'PROMETHEUS'
    ));
    
    $wpdb->suppress_errors($suppress);
}
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $prometheus_css_path = __DIR__ . '/prometheus/style.css';
    if (is_readable($prometheus_css_path)) {
        echo file_get_contents($prometheus_css_path);
    }
    ?>
</style>

<div class="vgt-module-container prometheus-core">
    
    <!-- HEADER SECTION -->
    <div class="vgt-header">
        <div class="vgt-title-group">
            <h1 class="vgt-glitch-text prom-glitch" data-text="<?php echo esc_attr__('PROMETHEUS ENGINE', 'vgt-sentinel'); ?>"><?php esc_html_e('PROMETHEUS ENGINE', 'vgt-sentinel'); ?></h1>
            <p class="vgt-subtitle"><?php esc_html_e('Behavioral Profiling & Predictive Threat AI', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-status-badge <?php echo $is_enabled ? 'active' : 'offline'; ?>" id="prom-main-badge">
            <span class="pulse-dot"></span> 
            <span id="badge-text-prom"><?php echo $is_enabled ? esc_html__('AI COGNITION: ONLINE', 'vgt-sentinel') : esc_html__('AI SENSORS: BLIND', 'vgt-sentinel'); ?></span>
        </div>
    </div>

    <!-- ABSOLUTE BULLETPROOF CONFIG TOGGLE -->
    <div class="vgt-master-switch-panel">
        <div class="panel-info">
            <h3><?php esc_html_e('Cognitive Threat Assessment', 'vgt-sentinel'); ?></h3>
            <p><?php echo wp_kses_post(__('Aktiviert die verhaltensbasierte Analyse in Echtzeit. Das System berechnet einen dynamischen Threat-Score für jede IP und jedes Subnetz. Übersteigt der Score den Horizont, wird ein präemptiver Strike ausgeführt.', 'vgt-sentinel')); ?></p>
        </div>
        <div class="panel-action">
            <label class="vgt-pure-switch prom-switch" id="toggle-container-prom">
                <input type="checkbox" name="vis_config[prometheus_enabled]" id="prometheus_enabled" value="1" <?php checked($is_enabled, true); ?>>
                <span class="vgt-pure-slider"></span>
                <div class="switch-label" id="toggle-label-prom">
                    <?php echo $is_enabled ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?>
                </div>
            </label>
        </div>
    </div>

    <div id="prom-dynamic-content" class="<?php echo $is_enabled ? '' : 'vgt-disabled'; ?>">
        
        <!-- HIGH LEVEL KPI METRICS -->
        <div class="vgt-kpi-matrix">
            <div class="vgt-kpi-box">
                <div class="kpi-icon"><svg class="vgt-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-strikes"><?php echo esc_html(number_format_i18n((int)$predictive_strikes)); ?></span>
                    <span class="kpi-label"><?php esc_html_e('Predictive Strikes', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-fast' : ''; ?>"></div>
            </div>
            <div class="vgt-kpi-box">
                <div class="kpi-icon"><svg class="vgt-icon" viewBox="0 0 24 24"><path d="M18 20a6 6 0 0 0-12 0"></path><circle cx="12" cy="10" r="4"></circle><circle cx="12" cy="12" r="10"></circle></svg></div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-anomalies"><?php echo esc_html(number_format_i18n((int)$total_anomalies)); ?></span>
                    <span class="kpi-label"><?php esc_html_e('Behavioral Anomalies', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-medium' : ''; ?>"></div>
            </div>
            <div class="vgt-kpi-box">
                <div class="kpi-icon"><svg class="vgt-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg></div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-entropy"><?php echo esc_html((string)$global_entropy); ?>%</span>
                    <span class="kpi-label"><?php esc_html_e('Global Threat Entropy (24h)', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-slow' : ''; ?>" style="width: <?php echo esc_attr((string)$global_entropy); ?>%; background: linear-gradient(90deg, transparent, var(--vgt-prom)); opacity: 0.8; transform: none;"></div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- COGNITIVE TUNING MATRIX (DYNAMIC SETTINGS) -->
        <!-- ========================================== -->
        <div class="vgt-section-header">
            <h2><svg class="vgt-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg> <?php esc_html_e('Cognitive Tuning Matrix', 'vgt-sentinel'); ?></h2>
            <p><?php esc_html_e('Feinjustierung der neuronalen Bewertungsparameter. Manipulation dieser Werte verändert die Aggressivität des Systems.', 'vgt-sentinel'); ?></p>
        </div>

        <div class="vgt-grid vgt-tuning-grid">
            
            <!-- PANEL 1: EVENT HORIZONS -->
            <div class="vgt-card vgt-glass-card tuning-card">
                <div class="card-header">
                    <div class="icon-wrapper"><svg class="vgt-icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg></div>
                    <h3><?php esc_html_e('Engine Thresholds', 'vgt-sentinel'); ?></h3>
                </div>
                <div class="tuning-body">
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('IP Event Horizon Score', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="10" name="vis_prometheus_config[event_horizon_score]" value="<?php echo esc_attr((string)$cfg_ehs); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 100.0 | Limit für einzelne IPs.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Subnet Event Horizon Score', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="10" name="vis_prometheus_config[infra_horizon_score]" value="<?php echo esc_attr((string)$cfg_ihs); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 150.0 | Cluster-Limit (Botnets).', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Subnet Cooldown (Sekunden)', 'vgt-sentinel'); ?></label>
                        <input type="number" step="1" min="60" name="vis_prometheus_config[infra_cooldown_window]" value="<?php echo esc_attr((string)$cfg_icw); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 3600 | Deep-Freeze Dauer.', 'vgt-sentinel'); ?></span>
                    </div>
                </div>
            </div>

            <!-- PANEL 2: DECAY ALGORITHM -->
            <div class="vgt-card vgt-glass-card tuning-card">
                <div class="card-header">
                    <div class="icon-wrapper"><svg class="vgt-icon" viewBox="0 0 24 24"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.59-9.21l-5.42 5.42"></path></svg></div>
                    <h3><?php esc_html_e('Decay Algorithm', 'vgt-sentinel'); ?></h3>
                </div>
                <div class="tuning-body">
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Score Decay Rate (pro Sekunde)', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.01" min="0" name="vis_prometheus_config[score_decay_rate]" value="<?php echo esc_attr((string)$cfg_sdr); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 0.2 | Wie schnell der Score abfällt.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Memory Cooldown Window (Sekunden)', 'vgt-sentinel'); ?></label>
                        <input type="number" step="1" min="60" name="vis_prometheus_config[score_decay_window]" value="<?php echo esc_attr((string)$cfg_sdw); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 300 | Persistenz der Anomalien im RAM.', 'vgt-sentinel'); ?></span>
                    </div>
                </div>
            </div>

            <!-- PANEL 3: TACTICAL PENALTIES -->
            <div class="vgt-card vgt-glass-card tuning-card span-full">
                <div class="card-header">
                    <div class="icon-wrapper" style="color: var(--vgt-danger);"><svg class="vgt-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg></div>
                    <h3><?php esc_html_e('Tactical Penalty Weights', 'vgt-sentinel'); ?></h3>
                </div>
                <div class="tuning-body grid-2col">
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Suspicious HTTP Method Penalty', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="0" name="vis_prometheus_config[penalty_method]" value="<?php echo esc_attr((string)$cfg_pm); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 30.0 | Für PUT, TRACE, TRACK, etc.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('High Parameter Count Penalty', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="0" name="vis_prometheus_config[penalty_params]" value="<?php echo esc_attr((string)$cfg_pp); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 15.0 | >8 URL Parameter detektiert.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('LFI/RCE/Regex Match Penalty', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="0" name="vis_prometheus_config[penalty_regex]" value="<?php echo esc_attr((string)$cfg_pr); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 50.0 | Direkter Payload Match.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('404 Not Found Penalty', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="0" name="vis_prometheus_config[penalty_404]" value="<?php echo esc_attr((string)$cfg_p4); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 25.0 | Crawler/Scanner Recon.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Auth Failure Penalty', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="0" name="vis_prometheus_config[penalty_auth]" value="<?php echo esc_attr((string)$cfg_pa); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 40.0 | Falscher Login-Versuch.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Micro-Burst Penalty (< 0.2s)', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="0" name="vis_prometheus_config[penalty_burst]" value="<?php echo esc_attr((string)$cfg_pb); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 20.0 | DoS/Brute-Force Velocity.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('High Freq Penalty (< 1s)', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="0" name="vis_prometheus_config[penalty_freq]" value="<?php echo esc_attr((string)$cfg_pf); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 10.0 | Schnelle Request-Folge.', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Subnet IP-Rotation Penalty', 'vgt-sentinel'); ?></label>
                        <input type="number" step="0.1" min="0" name="vis_prometheus_config[penalty_rotation]" value="<?php echo esc_attr((string)$cfg_pro); ?>" class="vgt-input">
                        <span class="vgt-hint"><?php esc_html_e('Default: 25.0 | Verteilte Angriffs-Netze.', 'vgt-sentinel'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <!-- ========================================== -->

        <!-- SOVEREIGN WHITELIST PANEL -->
        <div class="vgt-whitelist-panel" style="margin-bottom: 30px;">
            <div class="panel-header">
                <h3><svg class="vgt-icon" style="color: #00ffaa; width:20px; height:20px;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> <?php esc_html_e('SOVEREIGN WHITELIST (Creator\'s Immunity)', 'vgt-sentinel'); ?></h3>
                <p><?php esc_html_e('Trage hier deine IP-Adressen ein. Das Prometheus-System wird blind für diese Adressen. Keine Frequenz-Analyse, keine Strafen, absoluter Systemzugriff.', 'vgt-sentinel'); ?></p>
            </div>
            <div class="panel-body">
                <textarea name="vis_config[prometheus_whitelist_ips]" id="prometheus_whitelist_ips" class="vgt-textarea" placeholder="<?php echo esc_attr("192.168.1.100\n203.0.113.5"); ?>" rows="4" spellcheck="false"><?php echo esc_textarea($whitelist_ips); ?></textarea>
                <div class="vgt-form-hint"><?php esc_html_e('Hinweis: Server-Loopbacks (127.0.0.1, ::1) und interne Cron-Requests sind nativ immunisiert.', 'vgt-sentinel'); ?></div>
            </div>
        </div>

        <!-- TACTICAL EVENT STREAM (REAL TERMINAL) -->
        <div class="vgt-terminal">
            <div class="vgt-term-header">
                <div class="vgt-term-buttons">
                    <span class="btn-red"></span><span class="btn-yellow"></span><span class="btn-green"></span>
                </div>
                <div class="vgt-term-title"><?php esc_html_e('prometheus@vgt-ai:~/cognition$ tail -f /var/log/predictive.log', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-term-body" id="prom-terminal">
                <?php if ($is_enabled && !empty($real_logs)): ?>
                    <code class="sys-boot">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[SYSTEM] Neural Link established. Streaming telemetry...', 'vgt-sentinel'); ?></code>
                    <?php foreach ($real_logs as $log): 
                        $time = wp_date('H:i:s', strtotime($log->timestamp));
                        $type = str_pad("[" . $log->type . "]", 19, " ", STR_PAD_RIGHT); 
                        $class = ($log->type === 'PREDICTIVE_STRIKE' || $log->type === 'INFRA_STRIKE') ? 'log-critical' : 'log-warn';
                    ?>
                        <code class="<?php echo esc_attr($class); ?>">
                            <span class="term-time">[<?php echo esc_html($time); ?>]</span> 
                            <span class="term-type"><?php echo esc_html($type); ?></span> 
                            <span class="term-msg"><?php echo esc_html((string)$log->details); ?></span> 
                            <span class="term-ip">(<?php esc_html_e('IP:', 'vgt-sentinel'); ?> <?php echo esc_html((string)$log->ip_address); ?>)</span>
                        </code>
                    <?php endforeach; ?>
                    <code class="log-info"><span class="term-time">[<?php echo wp_date('H:i:s'); ?>]</span> <?php esc_html_e('[SYSTEM] AI Sensors scanning inbound vectors...', 'vgt-sentinel'); ?><span class="cursor-blink">_</span></code>
                <?php elseif ($is_enabled): ?>
                    <code class="sys-boot">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[SYSTEM] Prometheus AI initialized. No behavioral anomalies detected.', 'vgt-sentinel'); ?></code>
                    <code class="log-info">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[SYSTEM] Awaiting target data...', 'vgt-sentinel'); ?><span class="cursor-blink">_</span></code>
                <?php else: ?>
                    <code class="log-critical">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[ERROR] Cognitive AI is offline. System operates blindly.', 'vgt-sentinel'); ?></code>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT VIA INCLUDE)
     ========================================================================================= -->
<script>
    <?php 
    $prometheus_js_path = __DIR__ . '/prometheus/script.js';
    if (is_readable($prometheus_js_path)) {
        include $prometheus_js_path;
    }
    ?>
</script>
