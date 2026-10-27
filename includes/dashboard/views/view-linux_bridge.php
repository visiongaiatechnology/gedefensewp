<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$bridge = class_exists('VIS_Linux_Bridge') ? VIS_Linux_Bridge::status() : [];
$can_view = class_exists('VIS_Linux_Bridge') ? VIS_Linux_Bridge::can_view() : false;
$can_manage = class_exists('VIS_Linux_Bridge') ? VIS_Linux_Bridge::can_manage() : false;
$enabled = !empty($bridge['enabled']);
$secretOk = !empty($bridge['secret_usable']);
$saltsOk = !empty($bridge['salts_available']);
$logged = !empty($bridge['log_errors']);
$transport = (string)($bridge['transport'] ?? '');
$lastEvent = (string)($bridge['last_event_at'] ?? '');
$lastError = (string)($bridge['last_error'] ?? '');
$saved = isset($_GET['bridge_saved']);
$tested = isset($_GET['bridge_tested']) ? sanitize_key(wp_unslash($_GET['bridge_tested'])) : '';
$errorCode = isset($_GET['bridge_error']) ? sanitize_key(wp_unslash($_GET['bridge_error'])) : '';
?>
<div class="vgt-lb-page">
    <?php if (!$can_view): ?><div class="vgt-lb-alert is-error"><strong>Kein Zugriff.</strong> <?php esc_html_e('Diese Ansicht ist WordPress-Administratoren vorbehalten.', 'vgt-sentinel'); ?></div>
    <?php elseif (!$can_manage): ?><div class="vgt-lb-alert is-warn"><strong>Schreibgeschützt.</strong> <?php esc_html_e('Die Linux-Bridge ist sichtbar; Speichern und Testen sind erst nach dem Entsperren der ThroneGuard-Master-Session möglich.', 'vgt-sentinel'); ?></div><?php endif; ?>
    <section class="vgt-lb-hero">
        <div class="vgt-lb-hero-copy">
            <span class="vgt-lb-eyebrow"><i aria-hidden="true"></i> GEDEFENSE WP → GEDEFENSE LINUX</span>
            <h1><?php esc_html_e('Linux Bridge', 'vgt-sentinel'); ?></h1>
            <p><?php esc_html_e('Übergibt verifizierte ThroneGuard-Master-Sessions verschlüsselt an die lokale GeDefense-Linux-L7-Steuerung. Kein eigener Dateipfad, kein Zugriff auf den Kernel – WordPress nutzt ausschließlich sein bestehendes PHP-Logging.', 'vgt-sentinel'); ?></p>
            <div class="vgt-lb-hero-actions">
                <span class="vgt-lb-state <?php echo $enabled ? 'is-on' : 'is-off'; ?>"><span></span><?php echo $enabled ? esc_html__('BRIDGE AKTIV', 'vgt-sentinel') : esc_html__('BRIDGE INAKTIV', 'vgt-sentinel'); ?></span>
                <span class="vgt-lb-meta"><?php echo esc_html(sprintf(__('Letztes Ereignis: %s', 'vgt-sentinel'), $lastEvent !== '' ? gmdate('Y-m-d H:i:s', (int)$lastEvent) . ' UTC' : '—')); ?></span>
            </div>
        </div>
        <div class="vgt-lb-hero-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M8 12l2 2 4-4"/></svg>
        </div>
    </section>

    <?php if ($saved): ?><div class="vgt-lb-alert is-ok"><strong><?php esc_html_e('Gespeichert.', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Die Bridge-Konfiguration wurde sicher aktualisiert.', 'vgt-sentinel'); ?></div><?php endif; ?>
    <?php if ($tested === '1'): ?><div class="vgt-lb-alert is-ok"><strong><?php esc_html_e('Testereignis übergeben.', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Das PHP-/Syslog-Ereignis wurde erzeugt. Prüfe Transport und Log-Pfad in GeDefense Linux (das Testereignis erzeugt bewusst keinen Master-Lease).', 'vgt-sentinel'); ?></div><?php endif; ?>
    <?php if ($tested === '0'): ?><div class="vgt-lb-alert is-error"><strong><?php esc_html_e('Test fehlgeschlagen.', 'vgt-sentinel'); ?></strong> <?php esc_html_e('PHP-Logging/Syslog hat das Ereignis nicht angenommen.', 'vgt-sentinel'); ?></div><?php endif; ?>
    <?php if ($errorCode !== ''): ?><div class="vgt-lb-alert is-error"><strong><?php esc_html_e('Secret abgelehnt.', 'vgt-sentinel'); ?></strong> <?php echo $errorCode === 'secret' ? esc_html__('Der Wert ist kein gültiger 32-Byte-Base64-Key, oder die wp-config-Salts reichen nicht aus.', 'vgt-sentinel') : esc_html__('Die Bridge kann nicht ohne gültiges Secret aktiviert werden.', 'vgt-sentinel'); ?></div><?php endif; ?>
    <?php if ($lastError !== ''): ?><div class="vgt-lb-alert is-warn"><strong><?php esc_html_e('Letzter Bridge-Hinweis:', 'vgt-sentinel'); ?></strong> <?php echo esc_html($lastError); ?></div><?php endif; ?>

    <div class="vgt-lb-grid">
        <section class="vgt-lb-card">
            <header class="vgt-lb-card-head">
                <div><span class="vgt-lb-kicker">CONNECTION</span><h2><?php esc_html_e('Linux Secret eintragen', 'vgt-sentinel'); ?></h2></div>
                <span class="vgt-lb-chip <?php echo $secretOk ? 'is-ok' : 'is-warn'; ?>"><?php echo $secretOk ? esc_html__('SECRET OK', 'vgt-sentinel') : esc_html__('SECRET FEHLT', 'vgt-sentinel'); ?></span>
            </header>
            <p class="vgt-lb-copy"><?php esc_html_e('Erzeuge in GeDefense Linux unter CMS Defense → ThroneGuard-Bridge einen Schlüssel. Kopiere den einmalig angezeigten Base64-Wert hier hinein. Er wird AES-256-GCM-verschlüsselt in der Datenbank gespeichert; der Schlüssel dafür wird aus den wp-config.php-Salts abgeleitet.', 'vgt-sentinel'); ?></p>
            <?php if ($can_manage): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="vgt-lb-form">
                <input type="hidden" name="action" value="vis_gedefense_linux_bridge_save">
                <?php wp_nonce_field('vis_gedefense_linux_bridge_save'); ?>
                <label class="vgt-lb-field">
                    <span><?php esc_html_e('Linux Secret (Base64, 32 Byte)', 'vgt-sentinel'); ?></span>
                    <input type="password" name="bridge_secret" autocomplete="new-password" spellcheck="false" maxlength="512" placeholder="<?php echo $secretOk ? esc_attr__('Gespeichert – leer lassen zum Beibehalten', 'vgt-sentinel') : esc_attr__('Base64-Secret aus GeDefense Linux', 'vgt-sentinel'); ?>">
                </label>
                <label class="vgt-lb-toggle">
                    <input type="checkbox" name="bridge_enabled" value="1" <?php checked($enabled); ?>>
                    <span><strong><?php esc_html_e('Bridge aktivieren', 'vgt-sentinel'); ?></strong><small><?php esc_html_e('Nur verifizierte ThroneGuard-Master-Leases werden übermittelt.', 'vgt-sentinel'); ?></small></span>
                </label>
                <button class="vgt-lb-button is-primary" type="submit"><?php esc_html_e('Sicher speichern', 'vgt-sentinel'); ?></button>
            </form>
            <?php endif; ?>
        </section>

        <aside class="vgt-lb-card">
            <header class="vgt-lb-card-head"><div><span class="vgt-lb-kicker">POSTURE</span><h2><?php esc_html_e('Sicherheitslage', 'vgt-sentinel'); ?></h2></div></header>
            <dl class="vgt-lb-facts">
                <div><dt><?php esc_html_e('DB-Verschlüsselung', 'vgt-sentinel'); ?></dt><dd class="<?php echo $saltsOk ? 'ok' : 'warn'; ?>"><?php echo $saltsOk ? esc_html__('HKDF aus wp-config-Salts', 'vgt-sentinel') : esc_html__('Salts unvollständig', 'vgt-sentinel'); ?></dd></div>
                <div><dt><?php esc_html_e('Secret', 'vgt-sentinel'); ?></dt><dd class="<?php echo $secretOk ? 'ok' : 'warn'; ?>"><?php echo $secretOk ? esc_html__('verschlüsselt gespeichert', 'vgt-sentinel') : esc_html__('nicht entschlüsselbar', 'vgt-sentinel'); ?></dd></div>
                <div><dt><?php esc_html_e('PHP-Logging', 'vgt-sentinel'); ?></dt><dd class="<?php echo $logged || !empty($bridge['syslog_available']) ? 'ok' : 'warn'; ?>"><?php echo $logged ? 'log_errors = On' : (!empty($bridge['syslog_available']) ? esc_html__('Syslog-Fallback', 'vgt-sentinel') : esc_html__('kein Transport', 'vgt-sentinel')); ?></dd></div>
                <div><dt><?php esc_html_e('Transport', 'vgt-sentinel'); ?></dt><dd class="mono"><?php echo esc_html($transport !== '' ? $transport : '—'); ?></dd></div>
                <div><dt><?php esc_html_e('PHP error_log', 'vgt-sentinel'); ?></dt><dd class="mono"><?php echo esc_html(($bridge['error_log'] ?? '') !== '' ? (string)$bridge['error_log'] : 'PHP-FPM / Server-Log'); ?></dd></div>
                <div><dt><?php esc_html_e('Testereignis', 'vgt-sentinel'); ?></dt><dd><?php echo !empty($bridge['hello_at']) ? esc_html(gmdate('Y-m-d H:i:s', (int)$bridge['hello_at']) . ' UTC') : '—'; ?></dd></div>
            </dl>
            <p class="vgt-lb-note"><?php esc_html_e('In GeDefense Linux muss die bestehende PHP-/WordPress-Logdatei als Bridge-Log eingetragen und für den Linux-Dienst lesbar sein. Die Datei wird ausschließlich lesend geöffnet; WordPress erhält keinen zusätzlichen Schreibpfad außerhalb seines Webroots.', 'vgt-sentinel'); ?></p>
            <?php if ($can_manage): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="vis_gedefense_linux_bridge_test">
                <?php wp_nonce_field('vis_gedefense_linux_bridge_test'); ?>
                <button class="vgt-lb-button" type="submit"><?php esc_html_e('Testereignis senden', 'vgt-sentinel'); ?></button>
            </form>
            <?php endif; ?>
        </aside>
    </div>

    <div class="vgt-lb-boundary"><strong><?php esc_html_e('Sicherheitsgrenze:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Ein kompromittiertes WordPress kann mit dem gespeicherten Secret weiterhin gültige Ereignisse erzeugen. Die Linux-Ausnahme ist deshalb auf zwei Stunden, IP und Host begrenzt und lockert ausschließlich L7-Rate-Limits – keine WAF-Regel und keine Kernel-Durchsetzung.', 'vgt-sentinel'); ?></div>
</div>