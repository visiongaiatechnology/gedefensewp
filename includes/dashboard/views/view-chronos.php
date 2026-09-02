<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$opt = get_option('vis_config', []);
$is_enabled = !isset($opt['chronos_enabled']) || !empty($opt['chronos_enabled']);
$interval = $opt['chronos_interval'] ?? 'vis_hourly';
$email_to = $opt['chronos_email_to'] ?? get_option('admin_email');
$email_subj = $opt['chronos_email_subject'] ?? '[GEDEFENSE WP] Security Alert: System Integrity Breach Detected';
$email_body = $opt['chronos_email_body'] ?? "GEDEFENSE WP OMEGA REPORT\n=========================\nTimestamp: {TIMESTAMP} UTC\nSystem Status: {STATUS}\n\nIdentified Core/File Modifications: {CHANGES}\nAction Required: Access VGT Dashboard -> Scanner Module immediately.\n";

$next_run = wp_next_scheduled('vis_periodic_scan_event');
$next_run_text = $next_run ? gmdate('Y-m-d H:i:s', $next_run) . ' UTC' : __('Offline / Not Scheduled', 'vgt-sentinel');

$chronToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Chronos Autonomous Scheduler">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('AUTONOMOUS BACKGROUND SCANNER KERNEL & SCHEDULER', 'vgt-sentinel'); ?></p>
            <h2>CHRONOS</h2>
            <p><?php esc_html_e('Orchestriert den OMEGA Scanner-Kernel im Hintergrund. Führt ressourcenschonende, zeitgesteuerte Deep-Scans der Dateisystem-Integrität durch und alarmiert bei Modifikationen.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Chronos Status">
            <span><small><?php esc_html_e('SCHEDULER', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('RUNNING', 'vgt-sentinel') : esc_html__('PAUSED', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('NEXT IGNITION', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$next_run_text); ?></strong></span>
            <span><small><?php esc_html_e('SCAN INTERVAL', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)$interval); ?></strong></span>
            <span><small><?php esc_html_e('ALERT DISPATCHER', 'vgt-sentinel'); ?></small><strong><?php esc_html_e('ACTIVE', 'vgt-sentinel'); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('DAEMON STATE', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('OFFLINE', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('NEXT SCHEDULED RUN', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$next_run_text); ?></strong></article>
        <article><small><?php esc_html_e('SCAN FREQUENCY', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)$interval); ?></strong></article>
        <article><small><?php esc_html_e('DISPATCHER ENGINE', 'vgt-sentinel'); ?></small><strong>WP_MAIL</strong></article>
        <article><small><?php esc_html_e('RESOURCE BUDGET', 'vgt-sentinel'); ?></small><strong>ECONOMICAL</strong></article>
        <article><small><?php esc_html_e('HASH VERIFICATION', 'vgt-sentinel'); ?></small><strong>SHA-256</strong></article>
    </div>

    
        <?php wp_nonce_field('vis_save_config'); ?>
        <input type="hidden" name="vis_save_config" value="1">
        <input type="hidden" name="vis_context" value="chronos">

        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('01 / TEMPORAL SCHEDULING', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Temporal Scheduler Configuration', 'vgt-sentinel'); ?></h3>
                </div>
                <?php $chronToggle('chronos_enabled', $is_enabled, 'Auto-Scan Daemon'); ?>
            </div>
            
            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('Scan Intervall', 'vgt-sentinel'); ?></span>
                    <select name="vis_config[chronos_interval]" class="vgt-titan-select">
                        <option value="vis_15m" <?php selected($interval, 'vis_15m'); ?>><?php esc_html_e('Aggressiv (Alle 15 Minuten)', 'vgt-sentinel'); ?></option>
                        <option value="vis_30m" <?php selected($interval, 'vis_30m'); ?>><?php esc_html_e('Hoch (Alle 30 Minuten)', 'vgt-sentinel'); ?></option>
                        <option value="vis_hourly" <?php selected($interval, 'vis_hourly'); ?>><?php esc_html_e('Standard (Stündlich)', 'vgt-sentinel'); ?></option>
                        <option value="vis_twicedaily" <?php selected($interval, 'vis_twicedaily'); ?>><?php esc_html_e('Ausbalanciert (Alle 12 Stunden)', 'vgt-sentinel'); ?></option>
                        <option value="vis_daily" <?php selected($interval, 'vis_daily'); ?>><?php esc_html_e('Ökonomisch (1x Täglich)', 'vgt-sentinel'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Alarm E-Mail Empfänger', 'vgt-sentinel'); ?></span>
                    <input type="email" name="vis_config[chronos_email_to]" value="<?php echo esc_attr((string)$email_to); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('E-Mail Betreff', 'vgt-sentinel'); ?></span>
                    <input type="text" name="vis_config[chronos_email_subject]" value="<?php echo esc_attr((string)$email_subj); ?>">
                </label>
            </div>

            <label class="vgt-titan-wide-field">
                <span><?php esc_html_e('Alarm E-Mail Inhalt', 'vgt-sentinel'); ?></span>
                <textarea name="vis_config[chronos_email_body]" rows="8"><?php echo esc_textarea((string)$email_body); ?></textarea>
            </label>
            
            <div class="vgt-titan-actions">
                <button type="submit"><?php esc_html_e('CHRONOS EINSTELLUNGEN SPEICHERN', 'vgt-sentinel'); ?></button>
            </div>
        </section>
    
</section>
