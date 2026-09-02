<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

global $wpdb;
$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['styx_enabled']);
$audit_mode = !empty($opt['styx_audit_mode']);
$block_wp   = !empty($opt['styx_block_wp_telemetry']);
$whitelist  = $opt['styx_whitelist'] ?? '';

$table_logs = $wpdb->prefix . 'vis_styx_logs';

$total_blocked = 0;
$total_allowed = 0;
$unique_origins = 0;
$real_logs = [];

$suppress = $wpdb->suppress_errors(true);
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'") === $table_logs) {
    $total_blocked = (int)$wpdb->get_var("SELECT COUNT(id) FROM {$table_logs} WHERE status = 'BLOCKED'");
    $total_allowed = (int)$wpdb->get_var("SELECT COUNT(id) FROM {$table_logs} WHERE status = 'ALLOWED'");
    $unique_origins = (int)$wpdb->get_var("SELECT COUNT(DISTINCT origin) FROM {$table_logs}");
    $real_logs = $wpdb->get_results("SELECT * FROM {$table_logs} ORDER BY timestamp DESC LIMIT 30");
}
$wpdb->suppress_errors($suppress);

$styxToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Styx Outbound Executioner">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('OUTBOUND EXFILTRATION SHIELD & SHADOW ROUTER', 'vgt-sentinel'); ?></p>
            <h2>STYX</h2>
            <p><?php esc_html_e('Überwacht und blockiert alle ausgehenden HTTP/HTTPS-Verbindungen von WordPress-Plugins, verhindert C&C-Callbacks und Telemetrie-Leaks.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Styx Status">
            <span><small><?php esc_html_e('OUTBOUND SHIELD', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? ($audit_mode ? esc_html__('AUDIT MODE', 'vgt-sentinel') : esc_html__('STRICT', 'vgt-sentinel')) : esc_html__('OFFLINE', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('BLOCKED CALLS', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php echo esc_html((string)$total_blocked); ?></strong></span>
            <span><small><?php esc_html_e('AUTHORIZED', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$total_allowed); ?></strong></span>
            <span><small><?php esc_html_e('WP TELEMETRY', 'vgt-sentinel'); ?></small><strong><?php echo $block_wp ? esc_html__('BLOCKED', 'vgt-sentinel') : esc_html__('ALLOWED', 'vgt-sentinel'); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('BLOCKED EXFILTRATIONS', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php echo esc_html(number_format_i18n($total_blocked)); ?></strong></article>
        <article><small><?php esc_html_e('AUTHORIZED EXTERNAL CALLS', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html(number_format_i18n($total_allowed)); ?></strong></article>
        <article><small><?php esc_html_e('MONITORED ORIGINS', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(number_format_i18n($unique_origins)); ?></strong></article>
        <article><small><?php esc_html_e('WP TELEMETRY INTERLOCK', 'vgt-sentinel'); ?></small><strong><?php echo $block_wp ? esc_html__('INTERLOCKED', 'vgt-sentinel') : esc_html__('PERMITTED', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('INSPECTION ENGINE', 'vgt-sentinel'); ?></small><strong>WP_HTTP HOOK</strong></article>
        <article><small><?php esc_html_e('SHADOW ROUTING', 'vgt-sentinel'); ?></small><strong>ACTIVE</strong></article>
    </div>

    
        <?php wp_nonce_field('vis_save_config'); ?>
        <input type="hidden" name="vis_save_config" value="1">
        <input type="hidden" name="vis_context" value="styx">

        <!-- SECTION 1: OUTBOUND CONTROLS -->
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('01 / POLICY CONTROLS', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Outbound HTTP Security & Telemetry Controls', 'vgt-sentinel'); ?></h3>
                </div>
            </div>
            <div class="vgt-titan-toggle-grid">
                <?php $styxToggle('styx_enabled', !empty($opt['styx_enabled']), 'STYX Executioner Master'); ?>
                <?php $styxToggle('styx_audit_mode', !empty($opt['styx_audit_mode']), 'Audit Mode (Logging Only)'); ?>
                <?php $styxToggle('styx_block_wp_telemetry', !empty($opt['styx_block_wp_telemetry']), 'Block WP Core Telemetry'); ?>
            </div>
            <label class="vgt-titan-wide-field">
                <span><?php esc_html_e('Outbound Domain Whitelist (Eine pro Zeile)', 'vgt-sentinel'); ?></span>
                <textarea name="vis_config[styx_whitelist]" rows="4" placeholder="api.stripe.com&#10;api.paypal.com"><?php echo esc_textarea($whitelist); ?></textarea>
            </label>
            <div class="vgt-titan-actions">
                <button type="submit"><?php esc_html_e('STYX EINSTELLUNGEN SPEICHERN', 'vgt-sentinel'); ?></button>
            </div>
        </section>
    

    <!-- SECTION 2: TRAFFIC LEDGER -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('02 / OUTBOUND LEDGER', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Outbound Traffic Inspection Ledger', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php echo count($real_logs); ?> <?php esc_html_e('CALLS', 'vgt-sentinel'); ?></span>
        </div>

        <?php if (empty($real_logs)): ?>
            <div class="vgt-titan-empty" style="padding: 24px 0; color: #5eead4; text-align: center;">
                <?php esc_html_e('OUTBOUND SHIELD CLEAN — Keine ausgehenden Verbindungen protokolliert.', 'vgt-sentinel'); ?>
            </div>
        <?php else: ?>
            <div class="vgt-titan-table-wrap">
                <table class="vgt-titan-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Origin Plugin', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Target Host / URL', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Status', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($real_logs as $l): ?>
                        <tr>
                            <td><code><?php echo esc_html((string)$l->timestamp); ?></code></td>
                            <td><code><?php echo esc_html((string)$l->origin); ?></code></td>
                            <td><span style="color:#cbd5e1;"><?php echo esc_html((string)$l->url); ?></span></td>
                            <td>
                                <strong style="color: <?php echo $l->status === 'BLOCKED' ? '#fb7185' : '#5eead4'; ?>;">
                                    <?php echo esc_html((string)$l->status); ?>
                                </strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
