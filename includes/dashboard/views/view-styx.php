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

// THREAT INTELLIGENCE HANDLERS
$threat_intel_class = '\VisionGaia\GeDefense\Modules\ThreatIntel\ThreatIntelligence';
$threat_intel = class_exists($threat_intel_class) ? $threat_intel_class::instance() : null;

$intel_saved = false;
$sync_result = null;

if (current_user_can('manage_options') && isset($_POST['vis_threat_intel_save']) && check_admin_referer('vis_threat_intel_save_action')) {
    if ($threat_intel) {
        $raw = isset($_POST['vis_threat_intel']) && is_array($_POST['vis_threat_intel']) ? wp_unslash($_POST['vis_threat_intel']) : [];
        $active_feeds = [];
        foreach (array_keys($threat_intel_class::FEEDS) as $fkey) {
            $active_feeds[$fkey] = !empty($raw['active_feeds'][$fkey]);
        }
        $new_cfg = [
            'enabled'        => !empty($raw['enabled']),
            'block_outbound' => !empty($raw['block_outbound']),
            'block_inbound'  => !empty($raw['block_inbound']),
            'active_feeds'   => $active_feeds,
        ];
        $current = get_option($threat_intel_class::OPTION_CONFIG, []);
        if (is_array($current)) {
            $new_cfg = array_merge($current, $new_cfg);
        }
        update_option($threat_intel_class::OPTION_CONFIG, $new_cfg);
        $threat_intel->load_config();
        $intel_saved = true;
    }
}

if (current_user_can('manage_options') && isset($_POST['vis_threat_intel_sync']) && check_admin_referer('vis_threat_intel_sync_action')) {
    if ($threat_intel) {
        $sync_result = $threat_intel->sync_all_feeds();
    }
}

$intel_stats = $threat_intel ? $threat_intel->get_statistics() : [
    'total' => 0, 'ipv4' => 0, 'ipv6' => 0, 'cidrs' => 0, 'last_sync' => null, 'next_sync' => null,
    'is_enabled' => false, 'block_outbound' => false, 'block_inbound' => false, 'feed_stats' => []
];
$intel_cfg = $threat_intel ? $threat_intel->get_config() : [];

// ROUTING & SUB-TAB CONTROLLER
$styx_section = isset($_REQUEST['styx_section']) && is_string($_REQUEST['styx_section'])
    ? sanitize_key($_REQUEST['styx_section'])
    : ((isset($_POST['vis_threat_intel_save']) || isset($_POST['vis_threat_intel_sync'])) ? 'threat_intel' : 'outbound');

if (!in_array($styx_section, ['outbound', 'threat_intel'], true)) {
    $styx_section = 'outbound';
}

$styxToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Styx Outbound Executioner">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('OUTBOUND EXFILTRATION SHIELD & THREAT INTELLIGENCE MATRIX', 'vgt-sentinel'); ?></p>
            <h2>STYX</h2>
            <p><?php esc_html_e('Überwacht und blockiert alle ausgehenden HTTP/HTTPS-Verbindungen von WordPress-Plugins, verhindert C&C-Callbacks und synchronisiert weltweite Threat-Intelligence-Feeds.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Styx Status">
            <span><small><?php esc_html_e('OUTBOUND SHIELD', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? ($audit_mode ? esc_html__('AUDIT MODE', 'vgt-sentinel') : esc_html__('STRICT', 'vgt-sentinel')) : esc_html__('OFFLINE', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('BLOCKED CALLS', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php echo esc_html((string)$total_blocked); ?></strong></span>
            <span><small><?php esc_html_e('THREAT INTEL', 'vgt-sentinel'); ?></small><strong style="color: <?php echo !empty($intel_stats['is_enabled']) ? '#5eead4' : '#94a3b8'; ?>;"><?php echo !empty($intel_stats['is_enabled']) ? esc_html__('OPT-IN AKTIV', 'vgt-sentinel') : esc_html__('OPT-IN OFF', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('WP TELEMETRY', 'vgt-sentinel'); ?></small><strong><?php echo $block_wp ? esc_html__('BLOCKED', 'vgt-sentinel') : esc_html__('ALLOWED', 'vgt-sentinel'); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('BLOCKED EXFILTRATIONS', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php echo esc_html(number_format_i18n($total_blocked)); ?></strong></article>
        <article><small><?php esc_html_e('AUTHORIZED EXTERNAL CALLS', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html(number_format_i18n($total_allowed)); ?></strong></article>
        <article><small><?php esc_html_e('THREAT INTEL REPUTATION NODES', 'vgt-sentinel'); ?></small><strong style="color: #00e5ff;"><?php echo esc_html(number_format_i18n((int)$intel_stats['total'])); ?></strong></article>
        <article><small><?php esc_html_e('AUTO-SYNC INTERVALL', 'vgt-sentinel'); ?></small><strong><?php esc_html_e('ALLE 12 STUNDEN', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('INSPECTION ENGINE', 'vgt-sentinel'); ?></small><strong>WP_HTTP HOOK</strong></article>
        <article><small><?php esc_html_e('SHADOW ROUTING', 'vgt-sentinel'); ?></small><strong>ACTIVE</strong></article>
    </div>

    <!-- SUB-NAVIGATION: STYX CONTROLLER & THREAT INTEL TABS -->
    <nav class="vgt-titan-nav" aria-label="<?php echo esc_attr__('Styx Bereiche', 'vgt-sentinel'); ?>" id="styx-nav-tabs">
        <a class="<?php echo $styx_section === 'outbound' ? 'is-active active' : ''; ?>"
           href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=styx&styx_section=outbound')); ?>"
           data-tab="styx-outbound">
            🛡️ <?php esc_html_e('Outbound Shield & Traffic-Ledger', 'vgt-sentinel'); ?>
        </a>
        <a class="<?php echo $styx_section === 'threat_intel' ? 'is-active active' : ''; ?>"
           href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=styx&styx_section=threat_intel')); ?>"
           data-tab="styx-threat-intel">
            🌐 <?php esc_html_e('Threat Intelligence Feeds & C2-Abwehrnetzwerk (Opt-In)', 'vgt-sentinel'); ?>
            <?php if (!empty($intel_stats['is_enabled'])): ?>
                <span class="vgt-titan-badge" style="background: rgba(0, 229, 255, 0.2); color: #00e5ff; margin-left: 6px; font-size: 10px; padding: 2px 6px;">
                    <?php echo esc_html(number_format_i18n((int)$intel_stats['total'])); ?> NODES
                </span>
            <?php else: ?>
                <span class="vgt-titan-badge" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; margin-left: 6px; font-size: 10px; padding: 2px 6px;">
                    <?php esc_html_e('OPT-IN', 'vgt-sentinel'); ?>
                </span>
            <?php endif; ?>
        </a>
    </nav>

    <!-- TAB PANE 1: OUTBOUND CONTROLS & TRAFFIC LEDGER -->
    <div id="pane-styx-outbound" class="vgt-tab-pane <?php echo $styx_section === 'outbound' ? 'is-active' : ''; ?>" style="<?php echo $styx_section === 'outbound' ? 'display: block;' : 'display: none;'; ?>">
        <!-- SECTION 1: OUTBOUND CONTROLS -->
        <section class="vgt-titan-panel">
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=styx&styx_section=outbound')); ?>">
                <?php wp_nonce_field('vis_save_config'); ?>
                <input type="hidden" name="vis_save_config" value="1">
                <input type="hidden" name="vis_context" value="styx">
                <input type="hidden" name="styx_section" value="outbound">

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
                <label class="vgt-titan-wide-field" style="margin-top: 16px;">
                    <span><?php esc_html_e('Outbound Domain Whitelist (Eine pro Zeile)', 'vgt-sentinel'); ?></span>
                    <textarea name="vis_config[styx_whitelist]" rows="4" placeholder="api.stripe.com&#10;api.paypal.com"><?php echo esc_textarea($whitelist); ?></textarea>
                </label>
                <div class="vgt-titan-actions">
                    <button type="submit"><?php esc_html_e('STYX EINSTELLUNGEN SPEICHERN', 'vgt-sentinel'); ?></button>
                </div>
            </form>
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
    </div>

    <!-- TAB PANE 2: THREAT INTELLIGENCE MATRIX (OPT-IN) -->
    <div id="pane-styx-threat-intel" class="vgt-tab-pane <?php echo $styx_section === 'threat_intel' ? 'is-active' : ''; ?>" style="<?php echo $styx_section === 'threat_intel' ? 'display: block;' : 'display: none;'; ?>">
        
        <?php if ($intel_saved): ?>
            <div class="vis-dashboard-notice is-success" style="margin: 0 0 16px 0;" role="status">
                <?php esc_html_e('Threat-Intelligence-Einstellungen erfolgreich gespeichert und synchronisiert.', 'vgt-sentinel'); ?>
            </div>
        <?php endif; ?>

        <?php if ($sync_result): ?>
            <div class="vis-dashboard-notice <?php echo ($sync_result['status'] ?? '') === 'success' ? 'is-success' : 'is-warning'; ?>" style="margin: 0 0 16px 0;" role="status">
                <?php if (($sync_result['status'] ?? '') === 'success'): ?>
                    <strong><?php esc_html_e('Threat Intelligence Synchronisation erfolgreich abgeschlossen:', 'vgt-sentinel'); ?></strong>
                    <?php printf(esc_html__('%s aktive Angreifer-Knoten in der lokalen Sicherheits-Matrix aktualisiert.', 'vgt-sentinel'), esc_html(number_format_i18n((int)$sync_result['total_threats']))); ?>
                <?php else: ?>
                    <strong><?php esc_html_e('Hinweis zur Synchronisation:', 'vgt-sentinel'); ?></strong> <?php echo esc_html((string)($sync_result['message'] ?? 'Unbekannter Status')); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="vgt-titan-panel" id="styx-threat-intel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('03 / AUTONOMOUS THREAT INTELLIGENCE', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Threat Intelligence Feeds & C2-Abwehrnetzwerk (Opt-In)', 'vgt-sentinel'); ?></h3>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=styx&styx_section=threat_intel')); ?>" style="display: inline;">
                        <?php wp_nonce_field('vis_threat_intel_sync_action'); ?>
                        <input type="hidden" name="styx_section" value="threat_intel">
                        <button type="submit" name="vis_threat_intel_sync" value="1" style="background: rgba(0, 229, 255, 0.1); border: 1px solid #00e5ff; color: #00e5ff; font-weight: 700; font-size: 11px; padding: 6px 14px; border-radius: 6px; cursor: pointer;">
                            ⚡ <?php esc_html_e('JETZT SYNCHRONISIEREN (12H CRON)', 'vgt-sentinel'); ?>
                        </button>
                    </form>
                    <span class="vgt-titan-badge" style="background: <?php echo !empty($intel_stats['is_enabled']) ? 'rgba(94, 234, 212, 0.15)' : 'rgba(148, 163, 184, 0.15)'; ?>; color: <?php echo !empty($intel_stats['is_enabled']) ? '#5eead4' : '#94a3b8'; ?>;">
                        <?php echo !empty($intel_stats['is_enabled']) ? esc_html__('AKTIV (12H AUTO-SYNC)', 'vgt-sentinel') : esc_html__('OPT-IN (DEAKTIVIERT)', 'vgt-sentinel'); ?>
                    </span>
                </div>
            </div>

            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6; margin-bottom: 20px;">
                <?php esc_html_e('Das Threat-Intelligence-System ruft alle 12 Stunden vollautomatisch verifizierte Reputationslisten von 9 weltweit führenden Sicherheitsdiensten ab. Jede IP wird vor dem Import durch eine strikte Whitelist-Regex geschleust, um SQL-Injections und Payload-Angriffe mathematisch auszuschließen. Verhindert C2-Callbacks nach außen (Styx) und blockiert Angreifer-Netze am Perimeter (Cerberus).', 'vgt-sentinel'); ?>
            </p>

            <!-- STATUS OVERVIEW -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px;">
                <div>
                    <small style="display:block; color:#64748b; font-size:10px; text-transform:uppercase; letter-spacing:1px;"><?php esc_html_e('Gesamtzahl Bösartiger IPs', 'vgt-sentinel'); ?></small>
                    <strong style="color:#00e5ff; font-size:18px;"><?php echo esc_html(number_format_i18n((int)$intel_stats['total'])); ?></strong>
                </div>
                <div>
                    <small style="display:block; color:#64748b; font-size:10px; text-transform:uppercase; letter-spacing:1px;"><?php esc_html_e('IPv4 / IPv6 Einzel-Nodes', 'vgt-sentinel'); ?></small>
                    <strong style="color:#f0f3f8; font-size:16px;"><?php echo esc_html(number_format_i18n((int)$intel_stats['ipv4'])); ?> / <?php echo esc_html(number_format_i18n((int)$intel_stats['ipv6'])); ?></strong>
                </div>
                <div>
                    <small style="display:block; color:#64748b; font-size:10px; text-transform:uppercase; letter-spacing:1px;"><?php esc_html_e('Gekaperte CIDR-Subnetze', 'vgt-sentinel'); ?></small>
                    <strong style="color:#fb7185; font-size:16px;"><?php echo esc_html(number_format_i18n((int)$intel_stats['cidrs'])); ?></strong>
                </div>
                <div>
                    <small style="display:block; color:#64748b; font-size:10px; text-transform:uppercase; letter-spacing:1px;"><?php esc_html_e('Letzte Aktualisierung', 'vgt-sentinel'); ?></small>
                    <code style="color:#cbd5e1; font-size:11px;"><?php echo !empty($intel_stats['last_sync']) ? esc_html((string)$intel_stats['last_sync']) : esc_html__('Noch nie synchronisiert', 'vgt-sentinel'); ?></code>
                </div>
                <div>
                    <small style="display:block; color:#64748b; font-size:10px; text-transform:uppercase; letter-spacing:1px;"><?php esc_html_e('Nächster Cron-Sync', 'vgt-sentinel'); ?></small>
                    <code style="color:#5eead4; font-size:11px;"><?php echo !empty($intel_stats['next_sync']) ? esc_html((string)$intel_stats['next_sync']) : esc_html__('Nicht eingeplant (Inaktiv)', 'vgt-sentinel'); ?></code>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=styx&styx_section=threat_intel')); ?>">
                <?php wp_nonce_field('vis_threat_intel_save_action'); ?>
                <input type="hidden" name="styx_section" value="threat_intel">

                <!-- MASTER OPT-IN TOGGLE -->
                <div style="margin-bottom: 20px; padding: 18px; background: rgba(0, 229, 255, 0.04); border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 8px;">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="vis_threat_intel[enabled]" value="1" <?php checked(!empty($intel_cfg['enabled'])); ?>>
                        <strong style="color: #f0f3f8; font-size: 14px;"><?php esc_html_e('Automatisierte Threat-Intelligence-Feeds aktivieren (Opt-In)', 'vgt-sentinel'); ?></strong>
                    </label>
                    <small style="display:block; color:#94a3b8; margin-top:4px; margin-left: 28px;">
                        <?php esc_html_e('Datensouveränität: Erst durch Ihre explizite Aktivierung verbindet sich Ihr System alle 12 Stunden im Hintergrund mit den ausgewählten Sicherheitslisten.', 'vgt-sentinel'); ?>
                    </small>
                </div>

                <!-- ENFORCEMENT TARGETS -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin-bottom: 24px;">
                    <div style="padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="vis_threat_intel[block_outbound]" value="1" <?php checked(!empty($intel_cfg['block_outbound'])); ?>>
                            <strong style="color: #38bdf8; font-size: 13px;"><?php esc_html_e('Styx Egress Shield: Outbound C2-Block', 'vgt-sentinel'); ?></strong>
                        </label>
                        <small style="display:block; color:#94a3b8; margin-top:6px; margin-left: 24px;">
                            <?php esc_html_e('Verhindert, dass kompromittierte Plugins oder Themes ausgehende HTTP-Verbindungen zu bekannten Botnet-C2-Servern aufbauen.', 'vgt-sentinel'); ?>
                        </small>
                    </div>

                    <div style="padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="vis_threat_intel[block_inbound]" value="1" <?php checked(!empty($intel_cfg['block_inbound'])); ?>>
                            <strong style="color: #fb7185; font-size: 13px;"><?php esc_html_e('Cerberus Perimeter: Inbound Threat Block', 'vgt-sentinel'); ?></strong>
                        </label>
                        <small style="display:block; color:#94a3b8; margin-top:6px; margin-left: 24px;">
                            <?php esc_html_e('Schmettert eingehende Anfragen von dokumentierten Angreifern, Scannern und Tor-Exit-Nodes bereits an den Toren ab.', 'vgt-sentinel'); ?>
                        </small>
                    </div>
                </div>

                <!-- FEED SELECTION TABLE -->
                <h4 style="margin: 0 0 12px 0; color: #5eead4; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">
                    <?php esc_html_e('Abonnierte Sicherheits-Feeds (12-Stunden Intervall)', 'vgt-sentinel'); ?>
                </h4>

                <div class="vgt-titan-table-wrap" style="margin-bottom: 24px;">
                    <table class="vgt-titan-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;"><?php esc_html_e('Aktiv', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('Sicherheits-Feed', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('Typ / Format', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('Sync-Status & Einträge', 'vgt-sentinel'); ?></th>
                                <th style="text-align: right;"><?php esc_html_e('Offizielle Quelle', 'vgt-sentinel'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $feed_stats = $intel_stats['feed_stats'] ?? [];
                            if ($threat_intel_class && class_exists($threat_intel_class)):
                                foreach ($threat_intel_class::FEEDS as $fkey => $fmeta): 
                                    $is_feed_active = isset($intel_cfg['active_feeds'][$fkey]) ? !empty($intel_cfg['active_feeds'][$fkey]) : true;
                                    $fstat = $feed_stats[$fkey] ?? null;
                                    $count = isset($fstat['count']) ? (int)$fstat['count'] : null;
                            ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="vis_threat_intel[active_feeds][<?php echo esc_attr($fkey); ?>]" value="1" <?php checked($is_feed_active); ?>>
                                </td>
                                <td>
                                    <strong style="color:#ffffff;"><?php echo esc_html((string)$fmeta['name']); ?></strong>
                                    <small style="display:block; color:#64748b; font-size:11px;"><?php echo esc_html((string)$fmeta['desc']); ?></small>
                                </td>
                                <td>
                                    <code><?php echo esc_html((string)$fmeta['format']); ?></code>
                                </td>
                                <td>
                                    <?php if ($count !== null): ?>
                                        <span style="color:#5eead4; font-weight:700; font-family:monospace;"><?php echo esc_html(number_format_i18n($count)); ?> <?php esc_html_e('Einträge', 'vgt-sentinel'); ?></span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:11px;"><?php esc_html_e('Bereit zur Synchronisation', 'vgt-sentinel'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?php echo esc_url((string)$fmeta['url']); ?>" target="_blank" rel="noopener noreferrer" style="color:#00e5ff; font-size:11px; text-decoration:none;">
                                        ↗ <?php esc_html_e('Feed-Link', 'vgt-sentinel'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endforeach; 
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="vgt-titan-actions">
                    <button type="submit" name="vis_threat_intel_save" value="1" style="background: linear-gradient(135deg, #00e5ff 0%, #0077b6 100%); color: #06070a; font-weight: 800; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; letter-spacing: 1px; font-size: 13px;">
                        <?php esc_html_e('THREAT-INTEL EINSTELLUNGEN SPEICHERN', 'vgt-sentinel'); ?>
                    </button>
                </div>
            </form>
        </section>
    </div>
</section>

<style>
.vgt-titan-nav a.is-active,
.vgt-titan-nav a.active {
    color: #00e5ff !important;
    border-color: rgba(0, 229, 255, 0.5) !important;
    background: rgba(0, 229, 255, 0.14) !important;
    box-shadow: 0 0 16px rgba(0, 229, 255, 0.2) !important;
}
.vgt-tab-pane {
    display: none;
}
.vgt-tab-pane.is-active {
    display: block;
    animation: vgt-tab-fade-in 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes vgt-tab-fade-in {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
(function() {
    function initStyxTabs() {
        var navLinks = document.querySelectorAll('#styx-nav-tabs a[data-tab]');
        var panes = {
            'styx-outbound': document.getElementById('pane-styx-outbound'),
            'styx-threat-intel': document.getElementById('pane-styx-threat-intel')
        };
        if (!navLinks.length) return;

        function activateTab(tabId, updateUrl) {
            navLinks.forEach(function(l) {
                var match = l.getAttribute('data-tab') === tabId;
                l.classList.toggle('is-active', match);
                l.classList.toggle('active', match);
            });
            Object.keys(panes).forEach(function(key) {
                var pane = panes[key];
                if (pane) {
                    if (key === tabId) {
                        pane.classList.add('is-active');
                        pane.style.display = 'block';
                    } else {
                        pane.classList.remove('is-active');
                        pane.style.display = 'none';
                    }
                }
            });
            if (updateUrl && window.history && window.history.replaceState) {
                var sec = tabId === 'styx-threat-intel' ? 'threat_intel' : 'outbound';
                var u = new URL(window.location.href);
                u.searchParams.set('styx_section', sec);
                window.history.replaceState({}, '', u.toString());
            }
        }

        navLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                activateTab(this.getAttribute('data-tab'), true);
            });
        });

        if (window.location.hash === '#styx-threat-intel') {
            activateTab('styx-threat-intel', false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStyxTabs);
    } else {
        initStyxTabs();
    }
})();
</script>
