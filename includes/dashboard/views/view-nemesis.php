<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

global $wpdb;
$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['nemesis_enabled']);
$table_logs = $wpdb->prefix . 'vis_nemesis_logs';

$tarpit_total   = 0;
$canaries_count = 0;
$poison_count   = 0;
$real_logs      = [];

$suppress = $wpdb->suppress_errors(true);
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'") === $table_logs) {
    $tarpit_total = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND (type = 'TARPIT' OR (type = 'CANARY' AND details LIKE %s))", 
        'NEMESIS', '%Honeypot%'
    ));
    $canaries_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND type = 'CANARY' AND details NOT LIKE %s", 
        'NEMESIS', '%Honeypot%'
    ));
    $poison_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND type IN ('POISON', 'SABOTAGE', 'STRIKE')", 
        'NEMESIS'
    ));
    $real_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_logs} WHERE module = %s ORDER BY timestamp DESC LIMIT 30", 
        'NEMESIS'
    ));
}
$wpdb->suppress_errors($suppress);

$nemToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Nemesis Deception Grid">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('ADVANCED DECEPTION, TARPITTING & COUNTERINTELLIGENCE PROTOCOL', 'vgt-sentinel'); ?></p>
            <h2>NEMESIS</h2>
            <p><?php esc_html_e('Aktive Täuschungsmatrix, künstliche Latenzfallen (Tarpits), Canary-Token und Honigtöpfe zur vollständigen Erschöpfung von Angreifer-Ressourcen.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Nemesis Status">
            <span><small><?php esc_html_e('DECEPTION MATRIX', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ENGAGED', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('TARPIT TRAPS', 'vgt-sentinel'); ?></small><strong style="color: #a855f7;"><?php echo esc_html((string)$tarpit_total); ?></strong></span>
            <span><small><?php esc_html_e('CANARY TOKENS', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$canaries_count); ?></strong></span>
            <span><small><?php esc_html_e('POISON EVENTS', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php echo esc_html((string)$poison_count); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('ACTIVE TARPIT TRAPS', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(number_format_i18n($tarpit_total)); ?></strong></article>
        <article><small><?php esc_html_e('CANARY TRIGGERS', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html(number_format_i18n($canaries_count)); ?></strong></article>
        <article><small><?php esc_html_e('POISON & SABOTAGE', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php echo esc_html(number_format_i18n($poison_count)); ?></strong></article>
        <article><small><?php esc_html_e('TARPIT LATENCY', 'vgt-sentinel'); ?></small><strong>10s - 60s</strong></article>
        <article><small><?php esc_html_e('HONEYPOT ROUTES', 'vgt-sentinel'); ?></small><strong>ACTIVE</strong></article>
        <article><small><?php esc_html_e('DECEPTION ACCURACY', 'vgt-sentinel'); ?></small><strong>100%</strong></article>
    </div>

    <nav class="vgt-titan-nav" aria-label="Nemesis Bereiche">
        <a href="#nem-overview"><?php esc_html_e('Deception Controls', 'vgt-sentinel'); ?></a>
        <a href="#nem-ledger"><?php esc_html_e('Interception Ledger', 'vgt-sentinel'); ?> (<?php echo count($real_logs); ?>)</a>
    </nav>

    
        <?php wp_nonce_field('vis_save_config'); ?>
        <input type="hidden" name="vis_save_config" value="1">
        <input type="hidden" name="vis_context" value="nemesis">

        <!-- SECTION 1: DECEPTION CONTROLS -->
        <section id="nem-overview" class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('01 / DECEPTION PROTOCOL', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Nemesis Deception Grid Configuration', 'vgt-sentinel'); ?></h3>
                </div>
                <?php $nemToggle('nemesis_enabled', !empty($opt['nemesis_enabled']), 'Nemesis Deception Matrix'); ?>
            </div>
            <p><?php esc_html_e('Aktiviert Honeypot-Routen, Tarpits und künstliche Latenzen für bösartige Scanner. Angreifer werden in Endlosschleifen gefangen und verbrauchen ihre eigenen Serverressourcen.', 'vgt-sentinel'); ?></p>
            <div class="vgt-titan-actions">
                <button type="submit"><?php esc_html_e('NEMESIS EINSTELLUNGEN SPEICHERN', 'vgt-sentinel'); ?></button>
            </div>
        </section>
    

    <!-- SECTION 2: INTERCEPTION LEDGER -->
    <section id="nem-ledger" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('02 / TRAP INTERCEPTIONS', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Deception Interception Ledger', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php echo count($real_logs); ?> <?php esc_html_e('TRAPPED', 'vgt-sentinel'); ?></span>
        </div>

        <?php if (empty($real_logs)): ?>
            <div class="vgt-titan-empty" style="padding: 24px 0; color: #5eead4; text-align: center;">
                <?php esc_html_e('DECEPTION MATRIX QUIET — Keine Angreifer in den Tarpits oder Honeypots gefangen.', 'vgt-sentinel'); ?>
            </div>
        <?php else: ?>
            <div class="vgt-titan-table-wrap">
                <table class="vgt-titan-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Trap Type', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Target Actor IP', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Trap Execution Details', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($real_logs as $l): ?>
                        <tr>
                            <td><code><?php echo esc_html((string)$l->timestamp); ?></code></td>
                            <td><strong style="color: #a855f7;"><?php echo esc_html((string)$l->type); ?></strong></td>
                            <td><code><?php echo esc_html((string)$l->ip); ?></code></td>
                            <td><?php echo esc_html((string)$l->details); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
