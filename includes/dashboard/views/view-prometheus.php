<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

global $wpdb;
$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['prometheus_enabled']);

$table_logs = $wpdb->prefix . 'vis_prometheus_logs';

$predictive_strikes = (int)wp_cache_get('vgt_prometheus_strikes');
$total_anomalies = 0;
$global_entropy = 0;
$real_logs = [];

$whitelist_ips = $opt['prometheus_whitelist_ips'] ?? '';

$prom_config = get_option('vis_prometheus_config', []);
$cfg_ehs = max(200.0, (float)($prom_config['event_horizon_score'] ?? 200.0));
$cfg_ihs = (float)($prom_config['infra_horizon_score'] ?? 150.0);
$cfg_icw = (int)($prom_config['infra_cooldown_window'] ?? 3600);
$cfg_sdr = (float)($prom_config['score_decay_rate'] ?? 0.2);
$cfg_sdw = (int)($prom_config['score_decay_window'] ?? 300);
$cfg_pm  = (float)($prom_config['penalty_method'] ?? 30.0);
$cfg_pp  = (float)($prom_config['penalty_params'] ?? 15.0);
$cfg_pr  = (float)($prom_config['penalty_regex'] ?? 50.0);
$cfg_p4  = (float)($prom_config['penalty_404'] ?? 25.0);
$cfg_pa  = (float)($prom_config['penalty_auth'] ?? 40.0);
$cfg_pb  = (float)($prom_config['penalty_burst'] ?? 20.0);
$cfg_pf  = (float)($prom_config['penalty_freq'] ?? 10.0);
$cfg_pro = (float)($prom_config['penalty_rotation'] ?? 25.0);

$suppress = $wpdb->suppress_errors(true);
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'") === $table_logs) {
    $total_anomalies = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND type = %s", 
        'PROMETHEUS', 'ANOMALY'
    ));
    
    $db_strikes = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND type = %s", 
        'PROMETHEUS', 'PREDICTIVE_STRIKE'
    ));
    if ($db_strikes > $predictive_strikes) $predictive_strikes = $db_strikes;

    $recent_strikes = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND timestamp >= NOW() - INTERVAL 24 HOUR", 
        'PROMETHEUS'
    ));
    $global_entropy = min(100, ($recent_strikes * 10) + ($total_anomalies > 0 ? 5 : 0));

    $real_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_logs} WHERE module = %s ORDER BY timestamp DESC LIMIT 30", 
        'PROMETHEUS'
    ));
}
$wpdb->suppress_errors($suppress);

$promToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Prometheus Behavioral Intelligence">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('BEHAVIORAL PROFILING & PREDICTIVE THREAT AI', 'vgt-sentinel'); ?></p>
            <h2>PROMETHEUS</h2>
            <p><?php esc_html_e('Echtzeit-Verhaltensanalyse, neuronale Threat-Entropy-Berechnung und prädiktive Host-Isolation – vollständig lokal, zero-latency, ohne externe Cloud-Abhängigkeiten.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Prometheus Status">
            <span><small><?php esc_html_e('AI COGNITION', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('OFFLINE', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('STRIKE ENGINE', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ARMED', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('THREAT ENTROPY', 'vgt-sentinel'); ?></small><strong style="color: <?php echo $global_entropy > 50 ? '#fb7185' : '#5eead4'; ?>;"><?php echo esc_html((string)$global_entropy); ?>%</strong></span>
            <span><small><?php esc_html_e('TELEMETRY', 'vgt-sentinel'); ?></small><strong><?php echo esc_html__('NOMINAL', 'vgt-sentinel'); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('ANOMALIEN GESAMT', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(number_format_i18n($total_anomalies)); ?></strong></article>
        <article><small><?php esc_html_e('PREDICTIVE STRIKES', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php echo esc_html(number_format_i18n($predictive_strikes)); ?></strong></article>
        <article><small><?php esc_html_e('GLOBAL ENTROPY', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)$global_entropy); ?>%</strong></article>
        <article><small><?php esc_html_e('EVENT HORIZON', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)$cfg_ehs); ?> SCORE</strong></article>
        <article><small><?php esc_html_e('DECAY RATE', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)$cfg_sdr); ?> / SEC</strong></article>
        <article><small><?php esc_html_e('INFRA COOLDOWN', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)$cfg_icw); ?>s</strong></article>
    </div>

    <nav class="vgt-titan-nav" aria-label="Prometheus Bereiche">
        <a href="#prom-overview"><?php esc_html_e('Overview & Runtime', 'vgt-sentinel'); ?></a>
        <a href="#prom-horizons"><?php esc_html_e('Thresholds & Horizons', 'vgt-sentinel'); ?></a>
        <a href="#prom-penalties"><?php esc_html_e('Penalty Matrix', 'vgt-sentinel'); ?></a>
        <a href="#prom-whitelist"><?php esc_html_e('Sovereign Whitelist', 'vgt-sentinel'); ?></a>
        <a href="#prom-ledger"><?php esc_html_e('Live Incident Ledger', 'vgt-sentinel'); ?> (<?php echo count($real_logs); ?>)</a>
    </nav>

    
        <?php wp_nonce_field('vis_save_config'); ?>
        <input type="hidden" name="vis_save_config" value="1">
        <input type="hidden" name="vis_context" value="prometheus">

        <!-- SECTION 1: OVERVIEW & RUNTIME -->
        <section id="prom-overview" class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('01 / CONTROL PLANE', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Cognitive Runtime Controls', 'vgt-sentinel'); ?></h3>
                </div>
                <?php $promToggle('prometheus_enabled', !empty($opt['prometheus_enabled']), 'Prometheus Cognition'); ?>
            </div>
            <p><?php esc_html_e('Aktiviert die kontinuierliche verhaltensbasierte KI-Erkennung. Das System berechnet in Echtzeit dynamische Bedrohungsscores für jede IP-Adresse und isoliert bösartige Scanner präventiv.', 'vgt-sentinel'); ?></p>
        </section>

        <!-- SECTION 2: THRESHOLDS & HORIZONS -->
        <section id="prom-horizons" class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('02 / EVENT HORIZONS', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Event Horizons & Cooldown Parameters', 'vgt-sentinel'); ?></h3>
                </div>
            </div>
            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('Single-IP Event Horizon (Score Limit)', 'vgt-sentinel'); ?></span>
                    <input type="number" step="10" min="200" max="1000" name="vis_prometheus_config[event_horizon_score]" value="<?php echo esc_attr((string)$cfg_ehs); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Subnet Cluster Horizon Score', 'vgt-sentinel'); ?></span>
                    <input type="number" step="10" min="100" max="1000" name="vis_prometheus_config[infra_horizon_score]" value="<?php echo esc_attr((string)$cfg_ihs); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Subnet Cooldown Window (Seconds)', 'vgt-sentinel'); ?></span>
                    <input type="number" step="60" min="300" max="86400" name="vis_prometheus_config[infra_cooldown_window]" value="<?php echo esc_attr((string)$cfg_icw); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Score Decay Rate (per second)', 'vgt-sentinel'); ?></span>
                    <input type="number" step="0.05" min="0.05" max="1.0" name="vis_prometheus_config[score_decay_rate]" value="<?php echo esc_attr((string)$cfg_sdr); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Score Decay Window (Seconds)', 'vgt-sentinel'); ?></span>
                    <input type="number" step="30" min="60" max="3600" name="vis_prometheus_config[score_decay_window]" value="<?php echo esc_attr((string)$cfg_sdw); ?>">
                </label>
            </div>
        </section>

        <!-- SECTION 3: PENALTY MATRIX -->
        <section id="prom-penalties" class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('03 / HEURISTIC WEIGHTS', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Heuristic Penalty Matrix', 'vgt-sentinel'); ?></h3>
                </div>
            </div>
            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('SQLi / Regex Penalty', 'vgt-sentinel'); ?></span>
                    <input type="number" name="vis_prometheus_config[penalty_regex]" value="<?php echo esc_attr((string)$cfg_pr); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Auth Brute-Force Penalty', 'vgt-sentinel'); ?></span>
                    <input type="number" name="vis_prometheus_config[penalty_auth]" value="<?php echo esc_attr((string)$cfg_pa); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('404 Recon Sweep Penalty', 'vgt-sentinel'); ?></span>
                    <input type="number" name="vis_prometheus_config[penalty_404]" value="<?php echo esc_attr((string)$cfg_p4); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Burst Frequency Penalty', 'vgt-sentinel'); ?></span>
                    <input type="number" name="vis_prometheus_config[penalty_burst]" value="<?php echo esc_attr((string)$cfg_pb); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Disallowed HTTP Method Penalty', 'vgt-sentinel'); ?></span>
                    <input type="number" name="vis_prometheus_config[penalty_method]" value="<?php echo esc_attr((string)$cfg_pm); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Parameter Tampering Penalty', 'vgt-sentinel'); ?></span>
                    <input type="number" name="vis_prometheus_config[penalty_params]" value="<?php echo esc_attr((string)$cfg_pp); ?>">
                </label>
            </div>
        </section>

        <!-- SECTION 4: SOVEREIGN WHITELIST -->
        <section id="prom-whitelist" class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('04 / EXCLUSIONS', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Sovereign IP & Subnet Whitelist', 'vgt-sentinel'); ?></h3>
                </div>
            </div>
            <label class="vgt-titan-wide-field">
                <span><?php esc_html_e('Whitelisted IP Addresses / CIDRs (Eine pro Zeile)', 'vgt-sentinel'); ?></span>
                <textarea name="vis_config[prometheus_whitelist_ips]" rows="4" placeholder="192.168.1.0/24&#10;203.0.113.5"><?php echo esc_textarea($whitelist_ips); ?></textarea>
            </label>
            <div class="vgt-titan-actions">
                <button type="submit"><?php esc_html_e('PROMETHEUS EINSTELLUNGEN SPEICHERN', 'vgt-sentinel'); ?></button>
            </div>
        </section>
    

    <!-- SECTION 5: INCIDENT LEDGER -->
    <section id="prom-ledger" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('05 / AUDIT TRAIL', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Live Incident Telemetry Ledger', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php echo count($real_logs); ?> <?php esc_html_e('EVENTS', 'vgt-sentinel'); ?></span>
        </div>

        <?php if (empty($real_logs)): ?>
            <div class="vgt-titan-empty" style="padding: 24px 0; color: #7dd3fc; text-align: center;">
                <?php esc_html_e('HEURISTIC RADAR CLEAN — Keine anomalen Verhaltensmuster in den Telemetrie-Puffern registriert.', 'vgt-sentinel'); ?>
            </div>
        <?php else: ?>
            <div class="vgt-titan-table-wrap">
                <table class="vgt-titan-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Type', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Target Actor IP', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Incident Details', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($real_logs as $l): 
                            $is_strike = ($l->type === 'PREDICTIVE_STRIKE');
                        ?>
                        <tr>
                            <td><code><?php echo esc_html((string)$l->timestamp); ?></code></td>
                            <td><strong style="color: <?php echo $is_strike ? '#fb7185' : '#f59e0b'; ?>;"><?php echo esc_html((string)$l->type); ?></strong></td>
                            <td><code><?php echo esc_html((string)$l->ip); ?></code></td>
                            <td><?php echo esc_html((string)$l->message); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
