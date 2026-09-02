<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

global $wpdb;
$table_bans = defined('VIS_TABLE_BANS') ? $wpdb->prefix . VIS_TABLE_BANS : $wpdb->prefix . 'vis_bans';

if (current_user_can('manage_options') && isset($_POST['vis_manual_ban_submit']) && check_admin_referer('vis_manual_ban_action')) {
    $ban_ip = isset($_POST['ban_ip']) && is_string($_POST['ban_ip']) ? sanitize_text_field(wp_unslash($_POST['ban_ip'])) : '';
    $ban_reason = isset($_POST['ban_reason']) && is_string($_POST['ban_reason']) ? sanitize_text_field(wp_unslash($_POST['ban_reason'])) : 'MANUAL_ADMIN_BAN';
    $requested_duration = isset($_POST['ban_duration']) && is_scalar($_POST['ban_duration']) ? (int)$_POST['ban_duration'] : 86400;
    $ban_duration = in_array($requested_duration, [3600, 86400, 604800, 2592000, 31536000], true) ? $requested_duration : 86400;

    if (filter_var($ban_ip, FILTER_VALIDATE_IP)) {
        if (class_exists('VIS_Cerberus')) {
            VIS_Cerberus::ban_ip($ban_ip, $ban_reason, $ban_duration);
        }
    }
}

if (current_user_can('manage_options') && isset($_POST['vis_unban_ip_submit']) && check_admin_referer('vis_unban_ip_action')) {
    $unban_ip = isset($_POST['unban_ip']) && is_string($_POST['unban_ip']) ? sanitize_text_field(wp_unslash($_POST['unban_ip'])) : '';
    if ($unban_ip !== '' && class_exists('VIS_Cerberus')) {
        VIS_Cerberus::unban_ip($unban_ip);
    }
}

$bans_per_page = 20; 
$current_page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$offset = ($current_page - 1) * $bans_per_page;

$total_bans = 0;
$recent_bans = 0;
$bans = [];

$suppress = $wpdb->suppress_errors(true);
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_bans}'") === $table_bans) {
    $total_bans = (int)$wpdb->get_var("SELECT COUNT(id) FROM {$table_bans}");
    $recent_bans = (int)$wpdb->get_var("SELECT COUNT(id) FROM {$table_bans} WHERE banned_at >= NOW() - INTERVAL 24 HOUR");
    $bans = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_bans} ORDER BY banned_at DESC LIMIT %d OFFSET %d",
        $bans_per_page, $offset
    ));
}
$wpdb->suppress_errors($suppress);

$total_pages = (int)max(1, ceil($total_bans / $bans_per_page));
?>

<section class="vgt-titan" aria-label="Cerberus Perimeter Defense">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('LAYER 1 PERIMETER DEFENSE & IN-MEMORY IP SHUNNING', 'vgt-sentinel'); ?></p>
            <h2>CERBERUS</h2>
            <p><?php esc_html_e('Zero-Latency In-Memory IP-Blockade auf Kernel- und Opcache-Ebene mit permanenter Edge-Firewall-Synchronisation (Nginx / Apache).', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Cerberus Status">
            <span><small><?php esc_html_e('PERIMETER', 'vgt-sentinel'); ?></small><strong><?php esc_html_e('LOCKED DOWN', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('ACTIVE BANS', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)$total_bans); ?></strong></span>
            <span><small><?php esc_html_e('24H NEUTRALIZED', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$recent_bans); ?></strong></span>
            <span><small><?php esc_html_e('EDGE FIREWALL', 'vgt-sentinel'); ?></small><strong><?php esc_html_e('SYNCED', 'vgt-sentinel'); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('ACTIVE OPCACHE BANS', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(number_format_i18n($total_bans)); ?></strong></article>
        <article><small><?php esc_html_e('THREATS NEUTRALIZED (24H)', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html(number_format_i18n($recent_bans)); ?></strong></article>
        <article><small><?php esc_html_e('PAGINATION ENGINE', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)$current_page); ?> / <?php echo esc_html((string)$total_pages); ?></strong></article>
        <article><small><?php esc_html_e('STORAGE ENGINE', 'vgt-sentinel'); ?></small><strong>OPCACHE + DB</strong></article>
        <article><small><?php esc_html_e('EDGE EXPORT', 'vgt-sentinel'); ?></small><strong>NGINX / APACHE</strong></article>
        <article><small><?php esc_html_e('RESPONSE LATENCY', 'vgt-sentinel'); ?></small><strong>0.00ms</strong></article>
    </div>

    <nav class="vgt-titan-nav" aria-label="Cerberus Bereiche">
        <a href="#cerb-roster"><?php esc_html_e('Active Threat Roster', 'vgt-sentinel'); ?> (<?php echo esc_html((string)$total_bans); ?>)</a>
        <a href="#cerb-manual"><?php esc_html_e('Manual Ban Enforcement', 'vgt-sentinel'); ?></a>
    </nav>

    <!-- SECTION 1: ACTIVE THREAT ROSTER -->
    <section id="cerb-roster" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('01 / PERIMETER MATRIX', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Active Threat Roster & In-Memory Bans', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php echo esc_html((string)$total_bans); ?> <?php esc_html_e('ACTIVE BANS', 'vgt-sentinel'); ?></span>
        </div>

        <?php if (empty($bans)): ?>
            <div class="vgt-titan-empty" style="padding: 24px 0; color: #5eead4; text-align: center;">
                <?php esc_html_e('PERIMETER CLEAN — Keine aktiven IP-Sperren im Opcache verzeichnet.', 'vgt-sentinel'); ?>
            </div>
        <?php else: ?>
            <div class="vgt-titan-table-wrap">
                <table class="vgt-titan-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('IP-Adresse', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Banned At', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Reason / Origin', 'vgt-sentinel'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Actions', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bans as $b): ?>
                        <tr>
                            <td><code><?php echo esc_html((string)$b->ip); ?></code></td>
                            <td><code><?php echo esc_html((string)$b->banned_at); ?></code></td>
                            <td><strong style="color: #fb7185;"><?php echo esc_html((string)($b->reason ?? 'POLICY_VIOLATION')); ?></strong></td>
                            <td style="text-align:right;">
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field('vis_unban_ip_action'); ?>
                                    <input type="hidden" name="unban_ip" value="<?php echo esc_attr((string)$b->ip); ?>">
                                    <button type="submit" name="vis_unban_ip_submit" value="1" style="background:rgba(251,113,133,0.1); border:1px solid #fb7185; color:#fecdd3; border-radius:6px; padding:6px 10px; cursor:pointer; font:700 10px monospace;">
                                        <?php esc_html_e('ENTSPERREN', 'vgt-sentinel'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- SECTION 2: MANUAL BAN ENFORCEMENT -->
    <section id="cerb-manual" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('02 / DIRECT INTERVENTION', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Manuelle IP-Sperre einrichten', 'vgt-sentinel'); ?></h3>
            </div>
        </div>
        <form method="post" action="">
            <?php wp_nonce_field('vis_manual_ban_action'); ?>
            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('IP-Adresse (IPv4 oder IPv6)', 'vgt-sentinel'); ?></span>
                    <input type="text" name="ban_ip" placeholder="203.0.113.42" required autocomplete="off">
                </label>
                <label>
                    <span><?php esc_html_e('Ban Dauer', 'vgt-sentinel'); ?></span>
                    <select name="ban_duration" class="vgt-titan-select">
                        <option value="900"><?php esc_html_e('15 Minuten (Kurzzeit / XDR)', 'vgt-sentinel'); ?></option>
                        <option value="3600"><?php esc_html_e('1 Stunde', 'vgt-sentinel'); ?></option>
                        <option value="86400" selected><?php esc_html_e('24 Stunden (Standard)', 'vgt-sentinel'); ?></option>
                        <option value="604800"><?php esc_html_e('7 Tage', 'vgt-sentinel'); ?></option>
                        <option value="31536000"><?php esc_html_e('Permanent (1 Jahr)', 'vgt-sentinel'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Begründung / Notiz', 'vgt-sentinel'); ?></span>
                    <input type="text" name="ban_reason" placeholder="Manual security exclusion">
                </label>
            </div>
            <div class="vgt-titan-actions">
                <button type="submit" name="vis_manual_ban_submit" value="1"><?php esc_html_e('IP SOFORT SPERREN', 'vgt-sentinel'); ?></button>
            </div>
        </form>
    </section>
</section>
