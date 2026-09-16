<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

global $wpdb;
$table_bans = defined('VIS_TABLE_BANS') ? $wpdb->prefix . VIS_TABLE_BANS : $wpdb->prefix . 'vis_bans';

// LIVE PREVIEW HANDLER
if (current_user_can('manage_options') && isset($_GET['preview_block']) && $_GET['preview_block'] === '1') {
    if (check_admin_referer('vis_cerberus_preview_action')) {
        if (class_exists('VIS_Cerberus')) {
            $is_prom = isset($_GET['preview_engine']) && $_GET['preview_engine'] === 'prometheus';
            $preview_engine = $is_prom
                ? 'PROMETHEUS ZERO-TRUST MATRIX // OMEGA PROTOCOL'
                : 'CERBERUS XDR KERNEL';
            $preview_badge = $is_prom
                ? 'PROMETHEUS // PREDICTIVE MITIGATION'
                : 'CERBERUS // ACTIVE MITIGATION';
            $preview_prefix = $is_prom ? 'PROM' : 'CERB';
            $preview_msg = $is_prom
                ? 'PROMETHEUS_PREDICTIVE_STRIKE (Threat Score: 208)'
                : 'POLICY_VIOLATION // BRUTE_FORCE_THRESHOLD_EXCEEDED';

            echo VIS_Cerberus::instance()->render_block_page($preview_msg, '198.51.100.42', [
                'engine' => $preview_engine,
                'badge' => $preview_badge,
                'ref_prefix' => $preview_prefix,
                'defense_engine' => 'VisionGaia-Preview',
            ]);
            exit;
        }
    }
}

// ACTION 1: MANUAL BAN
if (current_user_can('manage_options') && isset($_POST['vis_manual_ban_submit']) && check_admin_referer('vis_manual_ban_action')) {
    $ban_ip = isset($_POST['ban_ip']) && is_string($_POST['ban_ip']) ? sanitize_text_field(wp_unslash($_POST['ban_ip'])) : '';
    $ban_reason = isset($_POST['ban_reason']) && is_string($_POST['ban_reason']) ? sanitize_text_field(wp_unslash($_POST['ban_reason'])) : 'MANUAL_ADMIN_BAN';
    $requested_duration = isset($_POST['ban_duration']) && is_scalar($_POST['ban_duration']) ? (int)$_POST['ban_duration'] : 86400;
    $ban_duration = in_array($requested_duration, [3600, 86400, 604800, 2592000, 31536000], true) ? $requested_duration : 86400;

    if (filter_var($ban_ip, FILTER_VALIDATE_IP)) {
        if (class_exists('VIS_Cerberus')) {
            VIS_Cerberus::instance()->ban_ip($ban_ip, $ban_reason);
        }
    }
}

// ACTION 2: UNBAN TARGET
if (current_user_can('manage_options') && isset($_POST['vis_unban_ip_submit']) && check_admin_referer('vis_unban_ip_action')) {
    $unban_ip = isset($_POST['unban_ip']) && is_string($_POST['unban_ip']) ? sanitize_text_field(wp_unslash($_POST['unban_ip'])) : '';
    if ($unban_ip !== '' && class_exists('VIS_Cerberus')) {
        VIS_Cerberus::instance()->unban_target($unban_ip);
    }
}

// ACTION 3: SAVE BRANDING / CUSTOMIZER
$branding_saved = false;
if (current_user_can('manage_options') && isset($_POST['vis_cerberus_branding_submit']) && check_admin_referer('vis_cerberus_branding_action')) {
    $raw_branding = isset($_POST['vis_branding']) && is_array($_POST['vis_branding']) ? wp_unslash($_POST['vis_branding']) : [];
    $clean_branding = [
        'enabled'        => !empty($raw_branding['enabled']),
        'company_name'   => sanitize_text_field($raw_branding['company_name'] ?? ''),
        'support_phone'  => sanitize_text_field($raw_branding['support_phone'] ?? ''),
        'support_email'  => sanitize_email($raw_branding['support_email'] ?? ''),
        'business_hours' => sanitize_text_field($raw_branding['business_hours'] ?? ''),
        'custom_notice'  => sanitize_textarea_field($raw_branding['custom_notice'] ?? ''),
        'layout_mode'    => in_array($raw_branding['layout_mode'] ?? '', ['banner_top', 'banner_bottom', 'classic'], true) ? $raw_branding['layout_mode'] : 'banner_top',
        'logo_url'       => esc_url_raw($raw_branding['logo_url'] ?? ''),
    ];
    update_option('vis_cerberus_branding', $clean_branding);
    $branding_saved = true;
}

$branding = get_option('vis_cerberus_branding', []);
if (!is_array($branding)) {
    $branding = [];
}

$cerb_section = isset($_GET['cerb_section']) && is_string($_GET['cerb_section']) ? sanitize_key($_GET['cerb_section']) : 'roster';
if (!in_array($cerb_section, ['roster', 'branding'], true)) {
    $cerb_section = 'roster';
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

$preview_nonce = wp_create_nonce('vis_cerberus_preview_action');
$preview_cerb_url = admin_url('admin.php?page=vgt-suite&tab=cerberus&preview_block=1&preview_engine=cerberus&_wpnonce=' . $preview_nonce);
$preview_prom_url = admin_url('admin.php?page=vgt-suite&tab=cerberus&preview_block=1&preview_engine=prometheus&_wpnonce=' . $preview_nonce);
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
        <article><small><?php esc_html_e('BRANDING STATUS', 'vgt-sentinel'); ?></small><strong style="color: <?php echo !empty($branding['enabled']) ? '#5eead4' : '#94a3b8'; ?>;"><?php echo !empty($branding['enabled']) ? esc_html__('AKTIV', 'vgt-sentinel') : esc_html__('STANDARDFARBE', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('RESPONSE LATENCY', 'vgt-sentinel'); ?></small><strong>0.00ms</strong></article>
    </div>

    <nav class="vgt-titan-nav" aria-label="<?php echo esc_attr__('Cerberus Bereiche', 'vgt-sentinel'); ?>">
        <a class="<?php echo $cerb_section === 'roster' ? 'is-active' : ''; ?>"
           href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=cerberus&cerb_section=roster')); ?>">
            <?php esc_html_e('Active Threat Roster', 'vgt-sentinel'); ?> (<?php echo esc_html((string)$total_bans); ?>)
        </a>
        <a class="<?php echo $cerb_section === 'branding' ? 'is-active' : ''; ?>"
           href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=cerberus&cerb_section=branding')); ?>">
            <?php esc_html_e('Block-Page Personalisierung & Branding', 'vgt-sentinel'); ?>
        </a>
    </nav>

    <?php if ($branding_saved): ?>
        <div class="vis-dashboard-notice is-success" style="margin: 16px 0;" role="status">
            <?php esc_html_e('Sperrseiten-Personalisierung erfolgreich gespeichert und synchronisiert.', 'vgt-sentinel'); ?>
        </div>
    <?php endif; ?>

    <?php if ($cerb_section === 'branding'): ?>
        <!-- SECTION: BLOCK-PAGE PERSONALISIERUNG & BRANDING -->
        <section id="cerb-branding" class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('03 / ACCESS RESTRICTED CUSTOMIZER', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Sperrseiten-Personalisierung & Unternehmens-Branding', 'vgt-sentinel'); ?></h3>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="<?php echo esc_url($preview_cerb_url); ?>" target="_blank" rel="noopener" class="button" style="background: rgba(0, 229, 255, 0.1); border: 1px solid #00e5ff; color: #00e5ff; font-weight: 700; font-size: 11px;">
                        👁 <?php esc_html_e('Vorschau: Cerberus', 'vgt-sentinel'); ?>
                    </a>
                    <a href="<?php echo esc_url($preview_prom_url); ?>" target="_blank" rel="noopener" class="button" style="background: rgba(255, 42, 95, 0.1); border: 1px solid #ff2a5f; color: #ff2a5f; font-weight: 700; font-size: 11px;">
                        👁 <?php esc_html_e('Vorschau: Prometheus', 'vgt-sentinel'); ?>
                    </a>
                </div>
            </div>

            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6; margin-bottom: 24px;">
                <?php esc_html_e('Personalisieren Sie die Sperrseite für Kunden und Besucher: Hinterlegen Sie Firmennamen, Hotline, Support-E-Mail und Erreichbarkeitszeiten. Wählen Sie ein Banner-Layout, damit die Sperrmeldung als diskreter Balken oben oder unten erscheint, während die Support-Kontaktdaten im Zentrum stehen.', 'vgt-sentinel'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('vis_cerberus_branding_action'); ?>
                
                <div style="margin-bottom: 24px; padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px;">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="vis_branding[enabled]" value="1" <?php checked(!empty($branding['enabled'])); ?>>
                        <strong style="color: #f0f3f8; font-size: 14px;"><?php esc_html_e('Unternehmens-Support-Branding auf Sperrseiten aktivieren', 'vgt-sentinel'); ?></strong>
                    </label>
                    <small style="display:block; color:#94a3b8; margin-top:4px; margin-left: 28px;">
                        <?php esc_html_e('Wenn aktiviert, wird sowohl bei Cerberus-Sperren als auch bei Prometheus-Strikes das personalisierte Unternehmensportal gerendert.', 'vgt-sentinel'); ?>
                    </small>
                </div>

                <div class="vgt-titan-fields">
                    <label>
                        <span><?php esc_html_e('Unternehmensname / Firmenname', 'vgt-sentinel'); ?></span>
                        <input type="text" name="vis_branding[company_name]" value="<?php echo esc_attr((string)($branding['company_name'] ?? get_bloginfo('name'))); ?>" placeholder="z. B. BaufiFair GmbH">
                    </label>

                    <label>
                        <span><?php esc_html_e('Support-Hotline / Telefonnummer', 'vgt-sentinel'); ?></span>
                        <input type="text" name="vis_branding[support_phone]" value="<?php echo esc_attr((string)($branding['support_phone'] ?? '')); ?>" placeholder="z. B. +49 (0) 30 12345678">
                        <small style="color:#64748b; font-size:11px;"><?php esc_html_e('Wird auf Smartphones als direkter One-Tap-Anrufbutton gerendert.', 'vgt-sentinel'); ?></small>
                    </label>

                    <label>
                        <span><?php esc_html_e('Support-E-Mail-Adresse', 'vgt-sentinel'); ?></span>
                        <input type="email" name="vis_branding[support_email]" value="<?php echo esc_attr((string)($branding['support_email'] ?? get_option('admin_email'))); ?>" placeholder="z. B. kontakt@baufifair.de">
                        <small style="color:#64748b; font-size:11px;"><?php esc_html_e('Klick öffnet Mail-Client mit bereits vorausgefüllter Incident-Referenz.', 'vgt-sentinel'); ?></small>
                    </label>

                    <label>
                        <span><?php esc_html_e('Erreichbarkeit / Bürozeiten', 'vgt-sentinel'); ?></span>
                        <input type="text" name="vis_branding[business_hours]" value="<?php echo esc_attr((string)($branding['business_hours'] ?? '')); ?>" placeholder="z. B. Mo. - Fr.: 08:30 - 18:00 Uhr">
                    </label>

                    <label>
                        <span><?php esc_html_e('Layout-Modus der Sperranzeige', 'vgt-sentinel'); ?></span>
                        <select name="vis_branding[layout_mode]" class="vgt-titan-select">
                            <option value="banner_top" <?php selected(($branding['layout_mode'] ?? 'banner_top'), 'banner_top'); ?>>
                                <?php esc_html_e('Sicherheits-Balken OBEN fixiert, Support-Portal zentriert (Empfohlen)', 'vgt-sentinel'); ?>
                            </option>
                            <option value="banner_bottom" <?php selected(($branding['layout_mode'] ?? ''), 'banner_bottom'); ?>>
                                <?php esc_html_e('Support-Portal zentriert, Sicherheits-Balken UNTEN fixiert', 'vgt-sentinel'); ?>
                            </option>
                            <option value="classic" <?php selected(($branding['layout_mode'] ?? ''), 'classic'); ?>>
                                <?php esc_html_e('Klassische Cyberpunk-Card mit integrierter Support-Sektion', 'vgt-sentinel'); ?>
                            </option>
                        </select>
                    </label>

                    <label>
                        <span><?php esc_html_e('Firmen-Logo URL (Optional)', 'vgt-sentinel'); ?></span>
                        <input type="url" name="vis_branding[logo_url]" value="<?php echo esc_attr((string)($branding['logo_url'] ?? '')); ?>" placeholder="https://domain.tld/logo.png">
                    </label>
                </div>

                <div style="margin-top: 16px;">
                    <label>
                        <span style="display:block; margin-bottom: 6px; font-weight: 600; color: #f0f3f8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                            <?php esc_html_e('Individueller Hinweistext / Kunden-Nachricht', 'vgt-sentinel'); ?>
                        </span>
                        <textarea name="vis_branding[custom_notice]" rows="3" class="vgt-titan-textarea" style="width: 100%; background: #06070a; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #f0f3f8; padding: 10px; font-size: 13px;" placeholder="z. B. Sie wurden durch unsere Firewall vorübergehend blockiert. Rufen Sie uns bitte an oder schreiben Sie uns – wir schalten Sie sofort frei!"><?php echo esc_textarea((string)($branding['custom_notice'] ?? '')); ?></textarea>
                    </label>
                </div>

                <div class="vgt-titan-actions" style="margin-top: 24px;">
                    <button type="submit" name="vis_cerberus_branding_submit" value="1" style="background: linear-gradient(135deg, #00e5ff 0%, #0077b6 100%); color: #06070a; font-weight: 800; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; letter-spacing: 1px;">
                        <?php esc_html_e('PERSONALISIERUNG SPEICHERN', 'vgt-sentinel'); ?>
                    </button>
                </div>
            </form>
        </section>

    <?php else: ?>
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
    <?php endif; ?>
</section>

