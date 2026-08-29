<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

$opt = get_option('vis_config', []);
$zeus_opt = get_option('vis_zeus_config', []);

if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
    $hades_enabled = !empty($opt['hades_enabled']);
    $hades_param = $opt['hades_admin_param'] ?? 'vgt_access';
    $hades_secret = $opt['hades_admin_secret'] ?? 'omega';
    
    $zeus_rename = $zeus_opt['brute_rename_login'] ?? '';
    $site_url = site_url();
    ?>
    <div class="vgt-apex-ui" style="max-width: 680px; margin: 40px auto; text-align: center;">
        <div class="vgt-glass-panel" style="padding: 40px;">
            <div style="background:rgba(16, 185, 129, 0.1); width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; border: 1px solid rgba(16, 185, 129, 0.3);">
                <svg class="vgt-icon" style="color:var(--vgt-neon-green); width:36px; height:36px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            
            <h2 style="color:#fff; margin-top:0; font-size: 24px; font-weight: 700;"><?php esc_html_e('GeDefense WP erfolgreich scharfgeschaltet!', 'vgt-sentinel'); ?></h2>
            <p style="color:var(--vgt-text-dim); font-size:14px; line-height:1.6; margin-bottom:30px;">
                <?php esc_html_e('Alle konfigurierten Sicherheitsmodule (WAF, RASP, Malware-Scanner, Honeypots & Härtungs-Schilde) wurden in den WordPress-Kernel kompiliert und sind ab sofort aktiv.', 'vgt-sentinel'); ?>
            </p>

            <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); padding: 20px; border-radius: 8px; margin-bottom: 30px; text-align: left;">
                <h4 style="color:#fff; margin-top:0; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <svg class="vgt-icon" style="color:var(--vgt-neon-green); width:18px; height:18px;" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <?php esc_html_e('Deine Administrator-Zugangsdaten (Wichtig!):', 'vgt-sentinel'); ?>
                </h4>
                
                <?php if ($hades_enabled): ?>
                    <div style="margin-top:12px;">
                        <span style="font-size:11px; text-transform:uppercase; color:var(--vgt-text-dim); display:block;"><?php esc_html_e('Geheime Hades Admin-URL:', 'vgt-sentinel'); ?></span>
                        <code style="display:block; background:rgba(0,0,0,0.4); padding:10px; border-radius:6px; font-family:monospace; color:#10b981; word-break:break-all; margin-top:4px; border: 1px solid rgba(16, 185, 129, 0.2);">
                            <?php echo esc_html($site_url); ?>/wp-admin?<?php echo esc_html($hades_param); ?>=<?php echo esc_html($hades_secret); ?>
                        </code>
                    </div>
                <?php endif; ?>

                <?php if (!empty($zeus_rename)): ?>
                    <div style="margin-top:12px;">
                        <span style="font-size:11px; text-transform:uppercase; color:var(--vgt-text-dim); display:block;"><?php esc_html_e('Zeus Login-Pfad:', 'vgt-sentinel'); ?></span>
                        <code style="display:block; background:rgba(0,0,0,0.4); padding:10px; border-radius:6px; font-family:monospace; color:#3b82f6; word-break:break-all; margin-top:4px; border: 1px solid rgba(59, 130, 246, 0.2);">
                            <?php echo esc_html($site_url); ?>/<?php echo esc_html(ltrim($zeus_rename, '/')); ?>
                        </code>
                    </div>
                <?php endif; ?>

                <?php if (!$hades_enabled && empty($zeus_rename)): ?>
                    <p style="font-size:12px; color:var(--vgt-text-dim); margin:10px 0 0 0;"><?php esc_html_e('Standard-Zugangswege verbleiben unverändert (keine Login-Verschleierung aktiv).', 'vgt-sentinel'); ?></p>
                <?php endif; ?>
            </div>

            <a href="?page=vgt-suite&tab=overview" class="vgt-btn vgt-btn-primary" style="display:inline-block; width:100%; padding:14px; text-decoration:none; font-size:14px;">
                <?php esc_html_e('ZUM COMMAND CENTER &rarr;', 'vgt-sentinel'); ?>
            </a>
        </div>
    </div>
    <?php
    return;
}

// IP Detection
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $user_ip = trim($ips[0]);
}

$aegis_whitelist = !empty($opt['aegis_whitelist_ips']) ? $opt['aegis_whitelist_ips'] : '';
$prom_whitelist = !empty($opt['prometheus_whitelist_ips']) ? $opt['prometheus_whitelist_ips'] : '';

// Auto-add current IP to fields if empty
if (empty($aegis_whitelist) && !empty($user_ip)) {
    $aegis_whitelist = $user_ip;
}
if (empty($prom_whitelist) && !empty($user_ip)) {
    $prom_whitelist = $user_ip;
}

$site_url = site_url();
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $wizard_css_path = __DIR__ . '/setup_wizard/style.css';
    if (is_readable($wizard_css_path)) {
        echo file_get_contents($wizard_css_path);
    }
    ?>
    .vgt-wizard-card {
        background: rgba(15, 23, 42, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        transition: all 0.2s ease;
    }
    .vgt-wizard-card:hover {
        border-color: rgba(59, 130, 246, 0.3);
        background: rgba(15, 23, 42, 0.6);
    }
    .vgt-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .vgt-badge-tag {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.5px;
        padding: 3px 8px;
        border-radius: 4px;
        text-transform: uppercase;
    }
    .vgt-badge-blue { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
    .vgt-badge-green { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
    .vgt-badge-purple { background: rgba(168, 85, 247, 0.15); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
    .vgt-badge-orange { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
    .vgt-badge-red { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
    .vgt-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
        margin-top: 15px;
    }
    .vgt-summary-item {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.05);
        padding: 10px 14px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 12px;
    }
</style>

<div class="vgt-apex-ui">
    <input type="hidden" name="vis_save_config" value="1">
    <input type="hidden" name="vis_context" value="setup_wizard">

    <!-- STEP INDICATORS (7 STEPS) -->
    <div class="vgt-glass-panel">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom:1px solid var(--vgt-border); padding-bottom:15px;">
            <div>
                <h2 style="margin:0; font-size:18px; color:#fff; display:flex; align-items:center; gap:8px;">
                    <span><?php esc_html_e('GeDefense WP Setup Wizard', 'vgt-sentinel'); ?></span>
                </h2>
                <span style="font-size:11px; color:var(--vgt-text-dim);"><?php esc_html_e('Initialisierung & Konfiguration des Abwehr-Kernels', 'vgt-sentinel'); ?></span>
            </div>
            <div>
                <?php if (class_exists('VIS_I18n')) echo VIS_I18n::render_language_switcher(); ?>
            </div>
        </div>

        <div class="vgt-step-indicator">
            <div class="vgt-step-dot active" id="dot-1"><span class="vgt-step-num">1</span> <?php esc_html_e('IP-Schutz', 'vgt-sentinel'); ?></div>
            <div class="vgt-step-dot" id="dot-2"><span class="vgt-step-num">2</span> <?php esc_html_e('Firewall & WAF', 'vgt-sentinel'); ?></div>
            <div class="vgt-step-dot" id="dot-3"><span class="vgt-step-num">3</span> <?php esc_html_e('Malware & RASP', 'vgt-sentinel'); ?></div>
            <div class="vgt-step-dot" id="dot-4"><span class="vgt-step-num">4</span> <?php esc_html_e('Täuschung & Fallen', 'vgt-sentinel'); ?></div>
            <div class="vgt-step-dot" id="dot-5"><span class="vgt-step-num">5</span> <?php esc_html_e('Härtung & Stealth', 'vgt-sentinel'); ?></div>
            <div class="vgt-step-dot" id="dot-6"><span class="vgt-step-num">6</span> <?php esc_html_e('Autopilot & AI', 'vgt-sentinel'); ?></div>
            <div class="vgt-step-dot" id="dot-7"><span class="vgt-step-num">7</span> <?php esc_html_e('Scharfschaltung', 'vgt-sentinel'); ?></div>
        </div>

        <!-- ====================================================================
             STEP 1: IP Whitelisting & Auto-Detection
             ==================================================================== -->
        <div class="vgt-wizard-step active" id="step-1">
            <h3 style="margin-top:0; color:#fff; display:flex; align-items:center; gap:10px;">
                <svg class="vgt-icon" style="color:var(--vgt-neon-green); width:22px; height:22px;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <?php esc_html_e('Schritt 1: System-Check & IP-Whitelisting', 'vgt-sentinel'); ?>
            </h3>
            <p style="font-size:13px; color:var(--vgt-text-dim); line-height:1.6;">
                <?php esc_html_e('Um zu verhindern, dass du während Sicherheitsprüfungen versehentlich ausgesperrt wirst, hinterlegt GeDefense deine IP-Adresse auf den internen Whitelists von AEGIS, Cerberus und Prometheus.', 'vgt-sentinel'); ?>
            </p>

            <?php if (!empty($user_ip)): ?>
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); padding: 14px; border-radius: 8px; margin: 18px 0; font-size:13px; display:flex; align-items:center; gap:10px;">
                    <span class="vgt-pulse-dot" style="background:var(--vgt-neon-green);"></span>
                    <span style="color:#fff;"><?php printf(esc_html__('Erkannte Administrator-IP: %s', 'vgt-sentinel'), '<strong style="color:#10b981; font-family:monospace; font-size:14px;">' . esc_html($user_ip) . '</strong>'); ?></span>
                </div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
                <div class="vgt-form-group">
                    <label><?php esc_html_e('AEGIS & CERBERUS IP-Whitelist (eine pro Zeile)', 'vgt-sentinel'); ?></label>
                    <textarea name="vis_config[aegis_whitelist_ips]" class="vgt-input" style="height:110px; font-family:monospace;"><?php echo esc_textarea($aegis_whitelist); ?></textarea>
                    <small style="color:var(--vgt-text-muted); font-size:11px; display:block; margin-top:4px;"><?php esc_html_e('Diese IPs werden niemals von der Firewall oder dem Ban-System blockiert.', 'vgt-sentinel'); ?></small>
                </div>
                <div class="vgt-form-group">
                    <label><?php esc_html_e('PROMETHEUS Scanner Whitelist (eine pro Zeile)', 'vgt-sentinel'); ?></label>
                    <textarea name="vis_config[prometheus_whitelist_ips]" class="vgt-input" style="height:110px; font-family:monospace;"><?php echo esc_textarea($prom_whitelist); ?></textarea>
                    <small style="color:var(--vgt-text-muted); font-size:11px; display:block; margin-top:4px;"><?php esc_html_e('IPs, deren Dateioperationen nicht heuristisch überwacht werden.', 'vgt-sentinel'); ?></small>
                </div>
            </div>
        </div>

        <!-- ====================================================================
             STEP 2: Active Firewall & WAF Core (AEGIS, ZEUS, CERBERUS)
             ==================================================================== -->
        <div class="vgt-wizard-step" id="step-2">
            <h3 style="margin-top:0; color:#fff; display:flex; align-items:center; gap:10px;">
                <svg class="vgt-icon" style="color:var(--vgt-neon-blue); width:22px; height:22px;" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="20 6 9 17 4 12"/></svg>
                <?php esc_html_e('Schritt 2: Firewall- & WAF-Schichten (AEGIS, Zeus & Cerberus)', 'vgt-sentinel'); ?>
            </h3>
            <p style="font-size:13px; color:var(--vgt-text-dim); line-height:1.6; margin-bottom:20px;">
                <?php esc_html_e('Konfiguriere den mehrstufigen WAF-Schutz gegen SQL-Injections, Cross-Site Scripting (XSS), RCE und bösartige Botnetze.', 'vgt-sentinel'); ?>
            </p>

            <!-- AEGIS CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-blue">AEGIS WAF</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('AEGIS Deep Packet Inspection (DPI)', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[aegis_enabled]" id="cfg-aegis" value="1" <?php checked(!isset($opt['aegis_enabled']) || !empty($opt['aegis_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0 0 12px 0;">
                    <?php esc_html_e('Untersucht eingehende GET-, POST- und Header-Payloads in Echtzeit auf Angriffsvektoren (SQLi, XSS, LFI, RCE, Object-Injection).', 'vgt-sentinel'); ?>
                </p>
                <div class="vgt-form-group" style="margin-bottom:0;">
                    <label style="font-size:11px;"><?php esc_html_e('AEGIS Betriebsmodus:', 'vgt-sentinel'); ?></label>
                    <select name="vis_config[aegis_mode]" class="vgt-select">
                        <option value="learning" <?php selected(($opt['aegis_mode'] ?? 'learning') === 'learning'); ?>><?php esc_html_e('Learning Mode (Empfohlen für den Start – Protokollierung ohne permanente IP-Bans)', 'vgt-sentinel'); ?></option>
                        <option value="strict" <?php selected(($opt['aegis_mode'] ?? 'learning') === 'strict'); ?>><?php esc_html_e('Strict Mode (Zero-Trust – Sofortiger Request-Abbruch und automatischer IP-Ban)', 'vgt-sentinel'); ?></option>
                    </select>
                </div>
            </div>

            <!-- ZEUS CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-purple">ZEUS WAF</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Zeus Pre-Boot & 6G Blacklist WAF', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[zeus_enabled]" id="cfg-zeus" value="1" <?php checked(!isset($opt['zeus_enabled']) || !empty($opt['zeus_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0 0 12px 0;">
                    <?php esc_html_e('Extrem schlanker WAF-Wächter, der bösartige Bots und 6G-Query-Angriffe blockiert, bevor WordPress komplexe Datenbankabfragen lädt.', 'vgt-sentinel'); ?>
                </p>
                <div style="display:flex; gap:20px; font-size:12px;">
                    <label class="vgt-checkbox-label" style="display:flex; align-items:center; gap:6px; color:#fff; cursor:pointer;">
                        <input type="checkbox" name="vis_zeus_config[fw_basic]" value="1" <?php checked(!isset($zeus_opt['fw_basic']) || !empty($zeus_opt['fw_basic'])); ?>>
                        <span><?php esc_html_e('Basis Firewall-Regeln', 'vgt-sentinel'); ?></span>
                    </label>
                    <label class="vgt-checkbox-label" style="display:flex; align-items:center; gap:6px; color:#fff; cursor:pointer;">
                        <input type="checkbox" name="vis_zeus_config[fw_6g_blacklist]" value="1" <?php checked(!isset($zeus_opt['fw_6g_blacklist']) || !empty($zeus_opt['fw_6g_blacklist'])); ?>>
                        <span><?php esc_html_e('6G Blacklist Matrix', 'vgt-sentinel'); ?></span>
                    </label>
                </div>
            </div>

            <!-- CERBERUS CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-red">CERBERUS</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Cerberus Instant Drop & Rate Limiter', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[cerberus_enabled]" id="cfg-cerberus" value="1" <?php checked(!isset($opt['cerberus_enabled']) || !empty($opt['cerberus_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0;">
                    <?php esc_html_e('Verwaltet die zentrale IP-Sperrliste. Blockierte Angreifer werden sofort mit HTTP 403 abgewiesen, ohne CPU- oder DB-Ressourcen zu verbrauchen.', 'vgt-sentinel'); ?>
                </p>
            </div>
        </div>

        <!-- ====================================================================
             STEP 3: Malware Engine & Sandbox (PROMETHEUS & MORPHEUS)
             ==================================================================== -->
        <div class="vgt-wizard-step" id="step-3">
            <h3 style="margin-top:0; color:#fff; display:flex; align-items:center; gap:10px;">
                <svg class="vgt-icon" style="color:var(--vgt-neon-orange); width:22px; height:22px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <?php esc_html_e('Schritt 3: Malware-Schutz & RASP-Isolation (Prometheus & Morpheus)', 'vgt-sentinel'); ?>
            </h3>
            <p style="font-size:13px; color:var(--vgt-text-dim); line-height:1.6; margin-bottom:20px;">
                <?php esc_html_e('Schütze dein Dateisystem und isoliere ausgeführten PHP-Code durch moderne Runtime Application Self-Protection (RASP).', 'vgt-sentinel'); ?>
            </p>

            <!-- PROMETHEUS CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-orange">PROMETHEUS</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Prometheus Malware & Signature Engine', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[prometheus_enabled]" id="cfg-prometheus" value="1" <?php checked(!isset($opt['prometheus_enabled']) || !empty($opt['prometheus_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0;">
                    <?php esc_html_e('Überwacht PHP-Dateien kontinuierlich auf verdächtigen Code (Webshells, c99, r57, eval(base64), unautorisierte File-Dropper) und verhindert deren Ausführung.', 'vgt-sentinel'); ?>
                </p>
            </div>

            <!-- MORPHEUS CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-blue">MORPHEUS RASP</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Morpheus Sandbox & Call-Stack Isolation', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[morpheus_enabled]" id="cfg-morpheus" value="1" <?php checked(!isset($opt['morpheus_enabled']) || !empty($opt['morpheus_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0 0 12px 0;">
                    <?php esc_html_e('Verfolgt Plugin-Aufrufe zur Laufzeit. Verhindert SSRF-Netzwerkangriffe, blockiert direkte SQL-Manipulationen an Tabellen wie wp_users und wehrt Option-Hijacking ab.', 'vgt-sentinel'); ?>
                </p>
                <div class="vgt-form-group" style="margin-bottom:0;">
                    <label class="vgt-checkbox-label" style="display:flex; align-items:center; gap:8px; color:#fff; cursor:pointer;">
                        <input type="checkbox" name="vis_config[morpheus_enforce]" value="1" <?php checked(!empty($opt['morpheus_enforce'])); ?>>
                        <span><?php esc_html_e('Strikte Durchsetzung (Enforcement Mode) – Standard: Audit/Lernmodus', 'vgt-sentinel'); ?></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- ====================================================================
             STEP 4: Cyber Deception & Honeypots (NEMESIS & GHOST TRAP)
             ==================================================================== -->
        <div class="vgt-wizard-step" id="step-4">
            <h3 style="margin-top:0; color:#fff; display:flex; align-items:center; gap:10px;">
                <svg class="vgt-icon" style="color:var(--vgt-neon-purple); width:22px; height:22px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/></svg>
                <?php esc_html_e('Schritt 4: Cyber-Täuschung & Honigtöpfe (Nemesis & Ghost Trap)', 'vgt-sentinel'); ?>
            </h3>
            <p style="font-size:13px; color:var(--vgt-text-dim); line-height:1.6; margin-bottom:20px;">
                <?php esc_html_e('Locke automatisierte Hacker-Bots gezielt in virtuelle Fallen und enttarne Scanner frühzeitig.', 'vgt-sentinel'); ?>
            </p>

            <!-- NEMESIS CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-purple">NEMESIS</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Nemesis Deception Grid', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[nemesis_enabled]" id="cfg-nemesis" value="1" <?php checked(!isset($opt['nemesis_enabled']) || !empty($opt['nemesis_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0 0 10px 0;">
                    <?php esc_html_e('Injiziert unsichtbare Fake-Login-Felder und fingierte Fehlermeldungen. Bots, die diese Felder ausfüllen, enttarnen sich sofort als Angreifer.', 'vgt-sentinel'); ?>
                </p>
                <p style="font-size:12px; color:var(--vgt-neon-green); margin:0;">
                    <?php esc_html_e('Bounded Response: Keine blockierenden PHP-Worker oder offensiven Payloads.', 'vgt-sentinel'); ?>
                </p>
            </div>

            <!-- GHOST TRAP CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-green">GHOST TRAP</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Ghost Trap Honeypot', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[ghost_trap_enabled]" id="cfg-trap" value="1" <?php checked(!isset($opt['ghost_trap_enabled']) || !empty($opt['ghost_trap_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0;">
                    <?php esc_html_e('Erzeugt dynamische Köder-Dateien (.bak, .sql, config-dumps), die nur bösartige Scanner ansteuern. Bei Zugriff wird die IP sofort isoliert.', 'vgt-sentinel'); ?>
                </p>
            </div>
        </div>

        <!-- ====================================================================
             STEP 5: Hardening & Stealth (TITAN & HADES)
             ==================================================================== -->
        <div class="vgt-wizard-step" id="step-5">
            <h3 style="margin-top:0; color:#fff; display:flex; align-items:center; gap:10px;">
                <svg class="vgt-icon" style="color:var(--vgt-neon-yellow); width:22px; height:22px;" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <?php esc_html_e('Schritt 5: Systemhärtung & Stealth (Titan & Hades)', 'vgt-sentinel'); ?>
            </h3>
            <p style="font-size:13px; color:var(--vgt-text-dim); line-height:1.6; margin-bottom:20px;">
                <?php esc_html_e('Schließe Standard-Sicherheitslücken in WordPress und verstecke den administrativen Login-Pfad.', 'vgt-sentinel'); ?>
            </p>

            <!-- TITAN CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-orange">TITAN HARDENING</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Titan Kernel Hardening Shield', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[titan_enabled]" id="cfg-titan" value="1" <?php checked(!isset($opt['titan_enabled']) || !empty($opt['titan_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:12px; font-size:12px;">
                    <label class="vgt-checkbox-label" style="display:flex; align-items:center; gap:6px; color:#fff; cursor:pointer;">
                        <input type="checkbox" name="vis_config[titan_block_xmlrpc]" value="1" <?php checked(!isset($opt['titan_block_xmlrpc']) || !empty($opt['titan_block_xmlrpc'])); ?>>
                        <span><?php esc_html_e('XML-RPC sperren (DDoS-Schutz)', 'vgt-sentinel'); ?></span>
                    </label>
                    <label class="vgt-checkbox-label" style="display:flex; align-items:center; gap:6px; color:#fff; cursor:pointer;">
                        <input type="checkbox" name="vis_config[titan_block_rest]" value="1" <?php checked(!empty($opt['titan_block_rest'])); ?>>
                        <span><?php esc_html_e('REST-API User Enumeration blockieren', 'vgt-sentinel'); ?></span>
                    </label>
                    <label class="vgt-checkbox-label" style="display:flex; align-items:center; gap:6px; color:#fff; cursor:pointer;">
                        <input type="checkbox" name="vis_config[titan_camouflage_mode]" value="1" <?php checked(!isset($opt['titan_camouflage_mode']) || !empty($opt['titan_camouflage_mode'])); ?>>
                        <span><?php esc_html_e('WP-Versions-Header entfernen', 'vgt-sentinel'); ?></span>
                    </label>
                    <label class="vgt-checkbox-label" style="display:flex; align-items:center; gap:6px; color:#fff; cursor:pointer;">
                        <input type="checkbox" name="vis_config[titan_disable_feeds]" value="1" <?php checked(!empty($opt['titan_disable_feeds'])); ?>>
                        <span><?php esc_html_e('RSS/Atom-Feeds deaktivieren', 'vgt-sentinel'); ?></span>
                    </label>
                </div>
            </div>

            <!-- HADES & ZEUS LOGIN STEALTH -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-purple">STEALTH</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Hades Login-Verschleierung (Stealth URL)', 'vgt-sentinel'); ?></h4>
                    </div>
                    <label class="vgt-switch">
                        <input type="checkbox" name="vis_config[hades_enabled]" id="hades-toggle" value="1" <?php checked(!empty($opt['hades_enabled'])); ?>>
                        <span class="vgt-slider"></span>
                    </label>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0 0 15px 0;">
                    <?php esc_html_e('Sperrt den regulären /wp-admin und /wp-login.php Zugriff. Der Login wird nur freigegeben, wenn ein geheimer URL-Parameter mitgegeben wird.', 'vgt-sentinel'); ?>
                </p>
                <div id="hades-inputs" style="display:<?php echo !empty($opt['hades_enabled']) ? 'grid' : 'none'; ?>; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="vgt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e('Hades URL Schlüssel (Param)', 'vgt-sentinel'); ?></label>
                        <input type="text" class="vgt-input" name="vis_config[hades_admin_param]" id="hades-param-input" value="<?php echo esc_attr($opt['hades_admin_param'] ?? 'vgt_access'); ?>" placeholder="vgt_access">
                    </div>
                    <div class="vgt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e('Hades URL Passwort (Secret)', 'vgt-sentinel'); ?></label>
                        <input type="text" class="vgt-input" name="vis_config[hades_admin_secret]" id="hades-secret-input" value="<?php echo esc_attr($opt['hades_admin_secret'] ?? 'omega'); ?>" placeholder="omega">
                    </div>
                </div>
            </div>
        </div>

        <!-- ====================================================================
             STEP 6: Automation & AI (CHRONOS, STYX & ORACLE)
             ==================================================================== -->
        <div class="vgt-wizard-step" id="step-6">
            <h3 style="margin-top:0; color:#fff; display:flex; align-items:center; gap:10px;">
                <svg class="vgt-icon" style="color:var(--vgt-neon-blue); width:22px; height:22px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M14.31 8l5.74 9.94M9.69 8h11.48"/></svg>
                <?php esc_html_e('Schritt 6: Automatisierung, Vault & AI-Oracle (Chronos & Styx)', 'vgt-sentinel'); ?>
            </h3>
            <p style="font-size:13px; color:var(--vgt-text-dim); line-height:1.6; margin-bottom:20px;">
                <?php esc_html_e('Aktiviere autonome Hintergrundprüfungen und optionale KI-Angriffsklassifikation.', 'vgt-sentinel'); ?>
            </p>

            <!-- CHRONOS & STYX CARD -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div class="vgt-wizard-card" style="margin-bottom:0;">
                    <div class="vgt-card-header">
                        <span class="vgt-badge-tag vgt-badge-blue">CHRONOS</span>
                        <label class="vgt-switch">
                            <input type="checkbox" name="vis_config[chronos_enabled]" id="cfg-chronos" value="1" <?php checked(!isset($opt['chronos_enabled']) || !empty($opt['chronos_enabled'])); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>
                    <h4 style="margin:0 0 8px 0; font-size:14px; color:#fff;"><?php esc_html_e('Chronos Autonomer Scanner', 'vgt-sentinel'); ?></h4>
                    <p style="font-size:12px; color:var(--vgt-text-dim); margin:0;">
                        <?php esc_html_e('Führt zeitgesteuerte Hintergrund-Audits durch und benachrichtigt bei Integritätsabweichungen.', 'vgt-sentinel'); ?>
                    </p>
                </div>

                <div class="vgt-wizard-card" style="margin-bottom:0;">
                    <div class="vgt-card-header">
                        <span class="vgt-badge-tag vgt-badge-purple">STYX</span>
                        <label class="vgt-switch">
                            <input type="checkbox" name="vis_config[styx_enabled]" id="cfg-styx" value="1" <?php checked(!isset($opt['styx_enabled']) || !empty($opt['styx_enabled'])); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>
                    <h4 style="margin:0 0 8px 0; font-size:14px; color:#fff;"><?php esc_html_e('Styx Telemetrie-Sperre', 'vgt-sentinel'); ?></h4>
                    <p style="font-size:12px; color:var(--vgt-text-dim); margin:0;">
                        <?php esc_html_e('Blockiert ungefragte externe Telemetriedaten und schützt System-Invariants.', 'vgt-sentinel'); ?>
                    </p>
                </div>
            </div>

            <!-- ORACLE AI CARD -->
            <div class="vgt-wizard-card">
                <div class="vgt-card-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="vgt-badge-tag vgt-badge-green">AEGIS ORACLE AI</span>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php esc_html_e('Groq AI Integration (Optional)', 'vgt-sentinel'); ?></h4>
                    </div>
                </div>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:0 0 15px 0;">
                    <?php esc_html_e('Verbinde das Aegis Oracle mit der Groq API, um unbekannte Zero-Day-Muster durch LLMs in Echtzeit klassifizieren zu lassen.', 'vgt-sentinel'); ?>
                </p>
                <div class="vgt-form-group" style="margin-bottom:0;">
                    <label><?php esc_html_e('Groq API Key (Wird hardware-verschlüsselt im Vault gespeichert)', 'vgt-sentinel'); ?></label>
                    <input type="password" class="vgt-input" name="groq_api_key" placeholder="gsk_xxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="new-password">
                </div>
            </div>
        </div>

        <!-- ====================================================================
             STEP 7: Completion & System Ignition
             ==================================================================== -->
        <div class="vgt-wizard-step" id="step-7">
            <h3 style="margin-top:0; color:#fff; display:flex; align-items:center; gap:10px;">
                <svg class="vgt-icon" style="color:var(--vgt-neon-green); width:24px; height:24px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <?php esc_html_e('Schritt 7: Scharfschaltung & Zusammenfassung', 'vgt-sentinel'); ?>
            </h3>
            <p style="font-size:13px; color:var(--vgt-text-dim); line-height:1.6;">
                <?php esc_html_e('Überprüfe die Übersicht aller konfigurierten Schutzmodule. Mit Klick auf den Button unten werden alle Firewall-Filter, RASP-Schilde und Sicherheitsregeln sofort aktiviert.', 'vgt-sentinel'); ?>
            </p>

            <!-- MODULE SUMMARY MATRIX -->
            <div class="vgt-wizard-card" style="margin-top:20px;">
                <h4 style="color:#fff; margin-top:0; font-size:14px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:8px;">
                    <?php esc_html_e('Aktivierungs-Status der Schutzschichten:', 'vgt-sentinel'); ?>
                </h4>
                <div class="vgt-summary-grid">
                    <div class="vgt-summary-item"><span>AEGIS DPI Firewall</span><strong id="sum-aegis" style="color:#10b981;">AKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Zeus Pre-Boot WAF</span><strong id="sum-zeus" style="color:#10b981;">AKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Cerberus IP Ban</span><strong id="sum-cerberus" style="color:#10b981;">AKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Prometheus Scanner</span><strong id="sum-prometheus" style="color:#10b981;">AKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Morpheus RASP</span><strong id="sum-morpheus" style="color:#10b981;">AKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Nemesis Deception</span><strong id="sum-nemesis" style="color:#10b981;">AKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Ghost Trap Honeypot</span><strong id="sum-trap" style="color:#10b981;">AKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Titan Hardening</span><strong id="sum-titan" style="color:#10b981;">AKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Hades Stealth</span><strong id="sum-hades" style="color:#94a3b8;">INAKTIV</strong></div>
                    <div class="vgt-summary-item"><span>Chronos Autopilot</span><strong id="sum-chronos" style="color:#10b981;">AKTIV</strong></div>
                </div>
            </div>

            <!-- CREDENTIALS / URL REVIEW -->
            <div style="background: rgba(139, 92, 246, 0.08); border: 1px solid rgba(139, 92, 246, 0.25); padding: 20px; border-radius: 8px; margin-top:20px; text-align: left;">
                <h4 style="color:#fff; margin-top:0; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <svg class="vgt-icon" style="color:var(--vgt-neon-purple); width:18px; height:18px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line></svg>
                    <?php esc_html_e('Zukünftiger Administrator-Zugang:', 'vgt-sentinel'); ?>
                </h4>

                <div id="hades-review-block" style="margin-top: 12px; display: none;">
                    <span style="font-size:11px; text-transform:uppercase; color:var(--vgt-text-dim); display:block;"><?php esc_html_e('Geheime Hades Login-URL (Unbedingt speichern!):', 'vgt-sentinel'); ?></span>
                    <code id="hades-url-preview" style="display:block; background:rgba(0,0,0,0.4); padding:10px; border-radius:6px; font-family:monospace; color:#a855f7; word-break:break-all; margin-top:4px; border:1px solid rgba(168,85,247,0.3);">
                        <?php echo esc_html($site_url); ?>/wp-admin?key=val
                    </code>
                </div>

                <p id="standard-review-block" style="font-size:12px; color:var(--vgt-text-dim); margin: 10px 0 0 0;">
                    <?php esc_html_e('Standard-Anmeldewege bleiben aktiv (keine Login-Verschleierung gewählt).', 'vgt-sentinel'); ?>
                </p>
            </div>
            
            <p style="color:var(--vgt-neon-green); font-size:12px; margin-top:20px; line-height:1.5;">
                <?php esc_html_e('Klicke jetzt auf "Sicherheitsmodule aktivieren", um die Konfiguration dauerhaft zu speichern.', 'vgt-sentinel'); ?>
            </p>
        </div>

        <!-- WIZARD ACTIONS -->
        <div class="vgt-wizard-actions">
            <button type="button" class="vgt-btn" id="btn-prev" style="visibility:hidden;"><?php esc_html_e('&larr; Zurück', 'vgt-sentinel'); ?></button>
            <button type="button" class="vgt-btn vgt-btn-primary" id="btn-next"><?php esc_html_e('Weiter &rarr;', 'vgt-sentinel'); ?></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentStep = 1;
    const maxSteps = 7;
    const siteUrl = '<?php echo esc_js($site_url); ?>';

    const DOM = {
        steps: document.querySelectorAll('.vgt-wizard-step'),
        dots: document.querySelectorAll('.vgt-step-dot'),
        btnNext: document.getElementById('btn-next'),
        btnPrev: document.getElementById('btn-prev'),
        hadesToggle: document.getElementById('hades-toggle'),
        hadesInputs: document.getElementById('hades-inputs'),
        hadesParam: document.getElementById('hades-param-input'),
        hadesSecret: document.getElementById('hades-secret-input'),
        hadesReview: document.getElementById('hades-review-block'),
        hadesPreview: document.getElementById('hades-url-preview'),
        standardReview: document.getElementById('standard-review-block'),
        // Module check elements
        cfgAegis: document.getElementById('cfg-aegis'),
        cfgZeus: document.getElementById('cfg-zeus'),
        cfgCerberus: document.getElementById('cfg-cerberus'),
        cfgPrometheus: document.getElementById('cfg-prometheus'),
        cfgMorpheus: document.getElementById('cfg-morpheus'),
        cfgNemesis: document.getElementById('cfg-nemesis'),
        cfgTrap: document.getElementById('cfg-trap'),
        cfgTitan: document.getElementById('cfg-titan'),
        cfgChronos: document.getElementById('cfg-chronos'),
        // Summary elements
        sumAegis: document.getElementById('sum-aegis'),
        sumZeus: document.getElementById('sum-zeus'),
        sumCerberus: document.getElementById('sum-cerberus'),
        sumPrometheus: document.getElementById('sum-prometheus'),
        sumMorpheus: document.getElementById('sum-morpheus'),
        sumNemesis: document.getElementById('sum-nemesis'),
        sumTrap: document.getElementById('sum-trap'),
        sumTitan: document.getElementById('sum-titan'),
        sumHades: document.getElementById('sum-hades'),
        sumChronos: document.getElementById('sum-chronos')
    };

    const updateWizard = () => {
        // Build URL Previews & Summary for step 7
        if (currentStep === 7) {
            const hasHades = DOM.hadesToggle && DOM.hadesToggle.checked;
            const hadesK = (DOM.hadesParam && DOM.hadesParam.value.trim()) || 'vgt_access';
            const hadesV = (DOM.hadesSecret && DOM.hadesSecret.value.trim()) || 'omega';

            if (hasHades) {
                DOM.hadesPreview.textContent = `${siteUrl}/wp-admin?${hadesK}=${hadesV}`;
                DOM.hadesReview.style.display = 'block';
                DOM.standardReview.style.display = 'none';
            } else {
                DOM.hadesReview.style.display = 'none';
                DOM.standardReview.style.display = 'block';
            }

            // Update summary badges
            const setSum = (el, chk) => {
                if (!el) return;
                const active = chk && chk.checked;
                el.textContent = active ? 'AKTIV' : 'INAKTIV';
                el.style.color = active ? '#10b981' : '#94a3b8';
            };

            setSum(DOM.sumAegis, DOM.cfgAegis);
            setSum(DOM.sumZeus, DOM.cfgZeus);
            setSum(DOM.sumCerberus, DOM.cfgCerberus);
            setSum(DOM.sumPrometheus, DOM.cfgPrometheus);
            setSum(DOM.sumMorpheus, DOM.cfgMorpheus);
            setSum(DOM.sumNemesis, DOM.cfgNemesis);
            setSum(DOM.sumTrap, DOM.cfgTrap);
            setSum(DOM.sumTitan, DOM.cfgTitan);
            setSum(DOM.sumHades, DOM.hadesToggle);
            setSum(DOM.sumChronos, DOM.cfgChronos);
        }

        // Toggle step visibility
        DOM.steps.forEach((step, idx) => {
            step.classList.toggle('active', (idx + 1) === currentStep);
        });

        // Toggle step indicators
        DOM.dots.forEach((dot, idx) => {
            const stepNum = idx + 1;
            dot.classList.toggle('active', stepNum === currentStep);
            dot.classList.toggle('completed', stepNum < currentStep);
        });

        // Toggle action buttons
        DOM.btnPrev.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        
        if (currentStep === maxSteps) {
            DOM.btnNext.innerHTML = '<?php echo esc_js(__('SICHERHEITSMODULE AKTIVIEREN &rarr;', 'vgt-sentinel')); ?>';
            DOM.btnNext.type = 'submit';
            DOM.btnNext.name = 'vis_save_config';
            DOM.btnNext.value = '1';
        } else {
            DOM.btnNext.innerHTML = '<?php echo esc_js(__('Weiter &rarr;', 'vgt-sentinel')); ?>';
            DOM.btnNext.type = 'button';
            DOM.btnNext.removeAttribute('name');
            DOM.btnNext.removeAttribute('value');
        }
    };

    DOM.btnNext.addEventListener('click', (e) => {
        if (currentStep < maxSteps) {
            e.preventDefault();
            currentStep++;
            updateWizard();
        }
    });

    DOM.btnPrev.addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep--;
            updateWizard();
        }
    });

    if (DOM.hadesToggle && DOM.hadesInputs) {
        DOM.hadesToggle.addEventListener('change', () => {
            DOM.hadesInputs.style.display = DOM.hadesToggle.checked ? 'grid' : 'none';
        });
    }
});
</script>
