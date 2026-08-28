<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: TITAN
 * MODULE: TITAN KERNEL HARDENING (OMEGA V6)
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. OMEGA LOGIC: REAL-TIME STATUS CHECK (PHYSICAL FILE INSPECTION) - STRICT 1:1
// =========================================================================================
$root_ht_path     = ABSPATH . '.htaccess';
$upload_dir       = wp_upload_dir();
$upload_ht_path   = $upload_dir['basedir'] . '/.htaccess';
$content_ht_path  = WP_CONTENT_DIR . '/.htaccess';
$includes_ht_path = ABSPATH . WPINC . '/.htaccess';
$nginx_conf_path  = wp_normalize_path($upload_dir['basedir'] . '/vgt-titan-shield.conf');

// 1. Check Root Shield
$root_active = false;
if (file_exists($root_ht_path) && strpos(file_get_contents($root_ht_path), 'VisionGaia Titan Firewall') !== false) {
    $root_active = true;
}

// 2. Check Upload Vault
$upload_active = false;
if (file_exists($upload_ht_path) && strpos(file_get_contents($upload_ht_path), 'VisionGaia Titan Upload Guard') !== false) {
    $upload_active = true;
}

// 3. Check Content Sentinel
$content_active = false;
if (file_exists($content_ht_path) && strpos(file_get_contents($content_ht_path), 'VisionGaia Content Sentinel') !== false) {
    $content_active = true;
}

// 4. Check Includes Sentinel
$includes_active = false;
if (file_exists($includes_ht_path) && strpos(file_get_contents($includes_ht_path), 'VisionGaia Includes Sentinel') !== false) {
    $includes_active = true;
}

// 5. Check NGINX Shield
$nginx_active = file_exists($nginx_conf_path);

// Pulse Status für Titan Main Shield
$titan_main_active = !empty($opt['titan_enabled']);
$titan_pulse = $titan_main_active ? 'vgt-is-active' : 'vgt-is-standby';
$titan_color = $titan_main_active ? '#10b981' : '#64748b';
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $titan_css_path = __DIR__ . '/titan/style.css';
    if (is_readable($titan_css_path)) {
        echo file_get_contents($titan_css_path);
    }
    ?>
</style>

<div class="vgt-apex-ui">

    <!-- MAIN SHIELD & OMEGA MATRIX -->
    <div class="vgt-glass-panel" style="border-top: 3px solid <?php echo esc_attr($titan_color); ?>;">
        <div class="vgt-module-header">
            <div class="vgt-module-title">
                <div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); display: flex;">
                    <svg class="vgt-icon" style="color:<?php echo esc_attr($titan_color); ?>; width:24px; height:24px;" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div>
                    <h2><?php esc_html_e('TITAN KERNEL HARDENING (OMEGA V6)', 'vgt-sentinel'); ?></h2>
                    <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace; display:flex; align-items:center; gap:8px;">
                        <?php esc_html_e('System Core Shield:', 'vgt-sentinel'); ?>
                        <span class="<?php echo esc_attr($titan_pulse); ?>" style="display:inline-flex; align-items:center; gap:6px;">
                            <span class="vgt-status-pulse"></span>
                            <strong style="color:<?php echo esc_attr($titan_color); ?>; letter-spacing:0.5px;">
                                <?php echo $titan_main_active ? esc_html__('LOCKED & SECURED', 'vgt-sentinel') : esc_html__('UNPROTECTED', 'vgt-sentinel'); ?>
                            </strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="vgt-alert-banner">
            <svg class="vgt-icon" style="color:var(--vgt-neon-orange); width:20px; height:20px;" viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <div>
                <p><strong><?php esc_html_e('SYSTEM HINWEIS:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('OMEGA V6 aktiviert radikale Tarnkappen-Protokolle (VGT_OS). Konfigurationsänderungen erfordern einen Klick auf "CONFIG SAVE". Nginx-User müssen die generierte .conf im Server-Block includen.', 'vgt-sentinel'); ?></p>
            </div>
        </div>

        <div class="vgt-setting-row" style="border-bottom:none; padding-bottom:16px;">
            <div class="vgt-label-group">
                <strong style="font-size:14px; color:var(--vgt-neon-green);"><?php esc_html_e('TITAN SHIELD (SECURITY HEADERS & FIREWALL)', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Aktiviert die physischen Firewalls auf File-System Ebene für Root, Uploads, Content und generiert den Nginx-Shield.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch">
                <input type="checkbox" name="vis_config[titan_enabled]" <?php checked(!empty($opt['titan_enabled'])); ?>>
                <span class="vgt-slider"></span>
            </label>
        </div>

        <div class="vgt-matrix-grid">
            <!-- ROOT FW -->
            <div class="vgt-matrix-card" data-status="<?php echo $root_active ? 'active' : 'pending'; ?>">
                <svg class="vgt-icon vgt-matrix-icon" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                <div class="vgt-matrix-label"><?php esc_html_e('ROOT FW', 'vgt-sentinel'); ?></div>
                <div class="vgt-matrix-state <?php echo $root_active ? 'state-active' : 'state-pending'; ?>">
                    <?php echo $root_active ? esc_html__('● SECURE', 'vgt-sentinel') : esc_html__('○ PENDING', 'vgt-sentinel'); ?>
                </div>
            </div>
            <!-- UPLOAD VAULT -->
            <div class="vgt-matrix-card" data-status="<?php echo $upload_active ? 'active' : 'pending'; ?>">
                <svg class="vgt-icon vgt-matrix-icon" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>
                <div class="vgt-matrix-label"><?php esc_html_e('UPLOAD VAULT', 'vgt-sentinel'); ?></div>
                <div class="vgt-matrix-state <?php echo $upload_active ? 'state-active' : 'state-pending'; ?>">
                    <?php echo $upload_active ? esc_html__('● LOCKED', 'vgt-sentinel') : esc_html__('○ PENDING', 'vgt-sentinel'); ?>
                </div>
            </div>
            <!-- CONTENT SENTINEL -->
            <div class="vgt-matrix-card" data-status="<?php echo $content_active ? 'active' : 'pending'; ?>">
                <svg class="vgt-icon vgt-matrix-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                <div class="vgt-matrix-label"><?php esc_html_e('CONTENT SENTINEL', 'vgt-sentinel'); ?></div>
                <div class="vgt-matrix-state <?php echo $content_active ? 'state-active' : 'state-pending'; ?>">
                    <?php echo $content_active ? esc_html__('● SHIELDED', 'vgt-sentinel') : esc_html__('○ PENDING', 'vgt-sentinel'); ?>
                </div>
            </div>
            <!-- INCLUDES GUARD -->
            <div class="vgt-matrix-card" data-status="<?php echo $includes_active ? 'active' : 'pending'; ?>">
                <svg class="vgt-icon vgt-matrix-icon" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                <div class="vgt-matrix-label"><?php esc_html_e('INCLUDES GUARD', 'vgt-sentinel'); ?></div>
                <div class="vgt-matrix-state <?php echo $includes_active ? 'state-active' : 'state-pending'; ?>">
                    <?php echo $includes_active ? esc_html__('● LOCKED', 'vgt-sentinel') : esc_html__('○ PENDING', 'vgt-sentinel'); ?>
                </div>
            </div>
            <!-- NGINX VAULT -->
            <div class="vgt-matrix-card" data-status="<?php echo $nginx_active ? 'active' : 'pending'; ?>">
                <svg class="vgt-icon vgt-matrix-icon" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="1" y1="9" x2="4" y2="9"></line></svg>
                <div class="vgt-matrix-label"><?php esc_html_e('NGINX VAULT', 'vgt-sentinel'); ?></div>
                <div class="vgt-matrix-state <?php echo $nginx_active ? 'state-active' : 'state-pending'; ?>">
                    <?php echo $nginx_active ? esc_html__('● GENERATED', 'vgt-sentinel') : esc_html__('○ PENDING', 'vgt-sentinel'); ?>
                </div>
            </div>
        </div>

        <?php if ($titan_main_active): ?>
        <div class="vgt-glass-panel" style="border: none; border-top: 1px solid rgba(255,255,255,0.03); border-radius: 0; margin-bottom: 0; background: rgba(59, 130, 246, 0.03); padding: 24px;">
            <h3 style="margin-top:0; color:#fff; font-size: 14px; display: flex; align-items: center; gap: 10px;">
                <svg class="vgt-icon" style="color: var(--vgt-neon-blue); width:18px; height:18px;" viewBox="0 0 24 24">
                    <rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line>
                </svg>
                <?php esc_html_e('NGINX SERVER INTEGRATION', 'vgt-sentinel'); ?>
            </h3>
            <p style="font-size:13px; color:var(--vgt-text-dim); margin-bottom:15px;"><?php esc_html_e('Fügen Sie den folgenden include-Befehl in den server {} Block Ihrer Nginx-Konfiguration (z.B. in AApanel) ein und starten Sie Nginx neu:', 'vgt-sentinel'); ?></p>
            <div style="background: rgba(0,0,0,0.5); border: 1px solid rgba(59, 130, 246, 0.3); padding: 12px 16px; border-radius: 6px; font-family: monospace; color: var(--vgt-neon-blue); font-size: 12px; overflow-x: auto;">
                include <?php echo esc_html($nginx_conf_path); ?>;
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- CAMOUFLAGE & ANTI-RECONNAISSANCE -->
    <div class="vgt-glass-panel">
        <div class="vgt-section-title">
            <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"></path>
            </svg>
            <?php esc_html_e('IDENTITY CAMOUFLAGE & ANTI-RECONNAISSANCE', 'vgt-sentinel'); ?>
        </div>
        
        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong style="color:var(--vgt-neon-cyan);"><?php esc_html_e('VGT_OS SERVER SPOOFING', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Überschreibt den HTTP Server Header mit "VGT_OS/1.0.0". Macht OS- und Server-Fingerprinting unmöglich.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_server_spoof]" <?php checked(!empty($opt['titan_server_spoof'])); ?>><span class="vgt-slider"></span></label>
        </div>

        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('USER ENUMERATION KILL', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Blockiert /?author=1 und schließt den /wp/v2/users REST-Endpunkt. Verhindert Brute-Force Vorbereitungen.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_anti_enum]" <?php checked(!empty($opt['titan_anti_enum'])); ?>><span class="vgt-slider"></span></label>
        </div>

        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('VERSION STRING STRIPPING', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Entfernt alle ?ver=x.x.x Anhängsel aus CSS/JS. Verhindert CVE-Matching durch Scanner.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_hide_version]" <?php checked(!empty($opt['titan_hide_version'])); ?>><span class="vgt-slider"></span></label>
        </div>
        
        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('FRAMEWORK SPOOFING', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Injiziert Fake-Meta-Tags (Laravel, Drupal), um Wappalyzer & Bots in die Irre zu führen.', 'vgt-sentinel'); ?></p>
            </div>
            <div>
                <select name="vis_config[titan_camouflage_mode]" class="vgt-select">
                    <option value="none" <?php selected($opt['titan_camouflage_mode'] ?? '', 'none'); ?>><?php esc_html_e('Deaktiviert (Standard)', 'vgt-sentinel'); ?></option>
                    <option value="laravel" <?php selected($opt['titan_camouflage_mode'] ?? '', 'laravel'); ?>><?php esc_html_e('Laravel Framework (Header Only)', 'vgt-sentinel'); ?></option>
                    <option value="drupal" <?php selected($opt['titan_camouflage_mode'] ?? '', 'drupal'); ?>><?php esc_html_e('Drupal 9 (Header & Meta Tags)', 'vgt-sentinel'); ?></option>
                </select>
            </div>
        </div>
    </div>

    <!-- LOGIN DOOR & HONEYPOT (ACTIVE DEFENSE) -->
    <div class="vgt-glass-panel" style="border-left: 4px solid var(--vgt-neon-purple);">
        <div class="vgt-section-title">
            <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M12 8v4"></path><path d="M12 16h.01"></path>
            </svg>
            <?php esc_html_e('TACTICAL ACTIVE DEFENSE', 'vgt-sentinel'); ?>
        </div>
        
        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong style="color:var(--vgt-neon-purple);"><?php esc_html_e('THE LOGIN GATEKEEPER', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Versteckt den Login. wp-login.php wirft einen 403-Fehler, außer mit Geheim-Parameter:', 'vgt-sentinel'); ?> <code style="color:#00f2ff;">?vgt_door=...</code></p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="text" name="vis_config[titan_login_slug]" class="vgt-input" value="<?php echo esc_attr($opt['titan_login_slug'] ?? 'matrix'); ?>" style="width: 150px; min-width:unset; text-align:center;">
                <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_login_gatekeeper]" <?php checked(!empty($opt['titan_login_gatekeeper'])); ?>><span class="vgt-slider"></span></label>
            </div>
        </div>

        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong style="color:var(--vgt-neon-red);"><?php esc_html_e('XML-RPC HONEYPOT (LETHAL TRAP)', 'vgt-sentinel'); ?></strong>
                <p><?php echo wp_kses_post(__('Bleibt als Falle offen. Jeder Scanner, der die Datei berührt, wird <strong>sofort und lebenslang gebannt</strong>.', 'vgt-sentinel')); ?></p>
            </div>
            <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_xmlrpc_honeypot]" <?php checked(!empty($opt['titan_xmlrpc_honeypot'])); ?>><span class="vgt-slider"></span></label>
        </div>
        
        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('WP-INCLUDES SENTINEL', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Legt eine extrem strikte Firewall um /wp-includes/, die direkte PHP-Aufrufe serverseitig eliminiert.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_includes_guard]" <?php checked(!empty($opt['titan_includes_guard'])); ?>><span class="vgt-slider"></span></label>
        </div>
    </div>

    <!-- HEARTBEAT & API -->
    <div class="vgt-glass-panel">
        <div class="vgt-section-title">
            <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
            </svg>
            <?php esc_html_e('PERFORMANCE & API RESTRICTION', 'vgt-sentinel'); ?>
        </div>
        
        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('HEARTBEAT FLATLINE', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Deaktiviert die ressourcenintensive Heartbeat-API, die oft für DDoS-Amplification missbraucht wird.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_heartbeat_disable]" <?php checked(!empty($opt['titan_heartbeat_disable'])); ?>><span class="vgt-slider"></span></label>
        </div>

        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('XML-RPC BLOCKIEREN (PASSIV)', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Schließt die Schnittstelle komplett. (Wird vom Honeypot oben überschrieben).', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_block_xmlrpc]" <?php checked(!empty($opt['titan_block_xmlrpc'])); ?>><span class="vgt-slider"></span></label>
        </div>

        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('REST API EINSCHRÄNKEN', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Erlaubt Zugriff auf die REST API nur für eingeloggte Benutzer.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch"><input type="checkbox" name="vis_config[titan_block_rest]" <?php checked(!empty($opt['titan_block_rest'])); ?>><span class="vgt-slider"></span></label>
        </div>
        
        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('RSS & ATOM FEEDS DEAKTIVIEREN', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Verhindert Content-Scraping. Gibt "403 Forbidden" bei Feed-Zugriff zurück.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch">
                <input type="checkbox" name="vis_config[titan_disable_feeds]" <?php checked(!empty($opt['titan_disable_feeds'])); ?>>
                <span class="vgt-slider"></span>
            </label>
        </div>
    </div>

</div>
