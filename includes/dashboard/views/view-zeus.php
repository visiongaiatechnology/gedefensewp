<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MODULE: ZEUS DASHBOARD VIEW
 * UI/UX: VGT APEX (Diamond Supreme, Dark Mode, Glassmorphism, Reactive)
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */

// =========================================================================================
// 1. LOGIK CORE (STRICT 1:1)
// =========================================================================================
$zeus_config = get_option( 'vis_zeus_config', [
    'fw_basic'             => true,
    'fw_6g_blacklist'      => true,
    'fw_fake_googlebot'    => true,
    'fw_block_xmlrpc'      => true,
    'brute_rename_login'   => '',      
    'brute_magic_cookie'   => '',      
    'brute_404_lockout'    => 20,      
    'user_login_lockdown'  => 5,        
    'user_force_logout'    => 3600,    
    'fs_disable_edit'      => true,
    'fs_prevent_hotlink'   => false,
    'spam_comment_block'   => true
] );

$vault_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/vgt-vault/zeus/' : dirname( ABSPATH ) . '/wp-content/vgt-vault/zeus/';
$waf_active = file_exists( $vault_dir . 'zeus-waf.php' );

// HADES CROSS-MODULE SYNC (DIAMANT SUPREME)
$hades_opt = get_option( 'vis_config', [] );
$hades_active = !empty($hades_opt['hades_enabled']);
$hades_param  = !empty($hades_opt['hades_admin_param']) ? $hades_opt['hades_admin_param'] : 'vgt_access';
$hades_secret = !empty($hades_opt['hades_admin_secret']) ? $hades_opt['hades_admin_secret'] : 'omega';

// Emergency Bypass Berechnung für die UI
$bypass_url = __('Deaktiviert: Kein statischer Firewall-Bypass vorhanden.', 'vgt-sentinel');

// Pulse classes and colors
$zeus_pulse = $waf_active ? 'vgt-is-active' : 'vgt-is-standby';
$zeus_color = $waf_active ? '#ff003c' : '#64748b'; 
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $zeus_css_path = __DIR__ . '/zeus/style.css';
    if (is_readable($zeus_css_path)) {
        echo file_get_contents($zeus_css_path);
    }
    ?>
</style>

<div class="vgt-apex-ui">

    <div class="vgt-hero-header">
        <div>
            <h1 class="vgt-glitch"><?php esc_html_e('ZEUS WAF COMPILER', 'vgt-sentinel'); ?></h1>
            <p class="vgt-subtitle"><?php echo wp_kses_post(__('Supreme Triad Integration: AEGIS (DPI) × PROMETHEUS (AI) × NEMESIS (Tarpit) <br> WICHTIG APCU muss installiert sein', 'vgt-sentinel')); ?></p>
        </div>
        <div class="vgt-status-wrap <?php echo esc_attr($zeus_pulse); ?>">
            <div class="vgt-pulse"></div>
            <span class="vgt-status-text" style="color: <?php echo esc_attr($zeus_color); ?>;">
                <?php echo $waf_active ? esc_html__('WAF KERNEL ONLINE', 'vgt-sentinel') : esc_html__('WAF OFFLINE / STANDBY', 'vgt-sentinel'); ?>
            </span>
        </div>
    </div>

    <!-- CRITICAL EMERGENCY BYPASS -->
    <div class="vgt-grid-full">
        <div class="vgt-panel vgt-panel-critical">
            <div class="vgt-panel-header">
                <svg class="vgt-icon" style="color: var(--vgt-accent); width:20px; height:20px;" viewBox="0 0 24 24">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <h3 style="color: var(--vgt-accent);"><?php esc_html_e('CRITICAL: Emergency Bypass URL', 'vgt-sentinel'); ?></h3>
            </div>
            <div class="vgt-panel-body">
                <p class="vgt-setting-desc" style="color: #cbd5e1;"><?php esc_html_e('Bewahre diese URL sicher auf. Falls du dich durch fehlerhafte Einstellungen oder das Prometheus Rate-Limiting selbst aus dem System aussperrst, kannst du die WAF mit diesem Link sofort temporär umgehen.', 'vgt-sentinel'); ?></p>
                <div class="vgt-code-block">
                    <span><?php echo esc_html( $bypass_url ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN SETTINGS FORM (METHOD POST ADDED) -->
    <form id="vis-zeus-settings-form" class="vgt-settings-form" method="POST" action="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=zeus')); ?>">
        <?php wp_nonce_field( 'vis_save_zeus', 'vis_zeus_nonce' ); ?>
        <input type="hidden" name="action" value="vis_save_zeus_config">

        <div class="vgt-grid-2">
            
            <!-- COLUMN 1: PERIMETER WAF & AEGIS -->
            <div class="vgt-panel">
                <div class="vgt-panel-header">
                    <svg class="vgt-icon" style="color: var(--vgt-accent); width:18px; height:18px;" viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <h3><?php esc_html_e('Pre-Boot WAF & AEGIS (DPI)', 'vgt-sentinel'); ?></h3>
                </div>
                <div class="vgt-panel-body">
                    
                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <h4 class="vgt-setting-title"><?php esc_html_e('Basic Perimeter Hardening', 'vgt-sentinel'); ?></h4>
                            <p class="vgt-setting-desc"><?php esc_html_e('Sperrt Zugriffe auf wp-config.php, .htaccess und deaktiviert das Directory Listing.', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="fw_basic" value="1" <?php checked( $zeus_config['fw_basic'], true ); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <h4 class="vgt-setting-title"><?php esc_html_e('AEGIS DPI & 6G Matrix', 'vgt-sentinel'); ?></h4>
                            <p class="vgt-setting-desc"><?php esc_html_e('Pre-Boot Erkennung von SQLi, XSS, LFI und Base64 Obfuscation. Blockiert böswillige Query-Strings in O(1) Laufzeit.', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="fw_6g_blacklist" value="1" <?php checked( $zeus_config['fw_6g_blacklist'], true ); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <h4 class="vgt-setting-title"><?php esc_html_e('Terminate XML-RPC', 'vgt-sentinel'); ?></h4>
                            <p class="vgt-setting-desc"><?php esc_html_e('Blockiert alle Zugriffe auf xmlrpc.php präventiv im WAF-Kernel (Verhindert Amplification DDoS).', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="fw_block_xmlrpc" value="1" <?php checked( $zeus_config['fw_block_xmlrpc'], true ); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <h4 class="vgt-setting-title"><?php esc_html_e('Fake Googlebot Extermination', 'vgt-sentinel'); ?></h4>
                            <p class="vgt-setting-desc"><?php esc_html_e('Nutzt asynchrones Reverse-DNS Lookup Caching, um gefälschte Bots zu enttarnen.', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="fw_fake_googlebot" value="1" <?php checked( $zeus_config['fw_fake_googlebot'], true ); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                </div>
            </div>

            <!-- COLUMN 2: FILESYSTEM & SPAM -->
            <div class="vgt-panel">
                <div class="vgt-panel-header">
                    <svg class="vgt-icon" style="color: var(--vgt-accent); width:18px; height:18px;" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <h3><?php esc_html_e('Filesystem & Spam Isolation', 'vgt-sentinel'); ?></h3>
                </div>
                <div class="vgt-panel-body">
                    
                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <h4 class="vgt-setting-title"><?php esc_html_e('Disable File Editor', 'vgt-sentinel'); ?></h4>
                            <p class="vgt-setting-desc"><?php esc_html_e('Erzwingt DISALLOW_FILE_EDIT. Blockiert RCE Vektoren durch kompromittierte Admins.', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="fs_disable_edit" value="1" <?php checked( $zeus_config['fs_disable_edit'], true ); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <h4 class="vgt-setting-title"><?php esc_html_e('Asset Hotlink Protection', 'vgt-sentinel'); ?></h4>
                            <p class="vgt-setting-desc"><?php esc_html_e('Verhindert Traffic-Diebstahl auf .htaccess Ebene (Blockiert externe Bild/Asset Referrer).', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="fs_prevent_hotlink" value="1" <?php checked( $zeus_config['fs_prevent_hotlink'], true ); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <h4 class="vgt-setting-title"><?php esc_html_e('Automated Spam Isolation', 'vgt-sentinel'); ?></h4>
                            <p class="vgt-setting-desc"><?php esc_html_e('Blockiert extreme Link-Payloads und speist den Verstoß direkt in PROMETHEUS (+100 Threat Score).', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="spam_comment_block" value="1" <?php checked( $zeus_config['spam_comment_block'], true ); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                </div>
            </div>

            <!-- COLUMN FULL: AUTH SHIELD & PROMETHEUS CONFIG -->
            <div class="vgt-panel" style="grid-column: 1 / -1;">
                <div class="vgt-panel-header">
                    <svg class="vgt-icon" style="color: var(--vgt-accent); width:18px; height:18px;" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <h3><?php esc_html_e('Auth Shield & PROMETHEUS Threat Thresholds', 'vgt-sentinel'); ?></h3>
                </div>
                <div class="vgt-panel-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">

                    <?php if ($hades_active): ?>
                    <div class="vgt-hades-sync-alert">
                        <svg class="vgt-icon" viewBox="0 0 24 24">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <div>
                            <h4><?php esc_html_e('HADES OMEGA LOCK DETECTED', 'vgt-sentinel'); ?></h4>
                            <p>
                                <?php echo wp_kses_post(__('Das Hades Stealth Protocol verwaltet aktuell das Routing für <code>/wp-admin</code>. Dies ist deine globale Bypass-Route:', 'vgt-sentinel')); ?> 
                                <code>/wp-admin?<?php echo esc_html($hades_param); ?>=<span style="color:var(--vgt-neon-purple);"><?php echo esc_html($hades_secret); ?></span></code>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Rename Login Portal (Slug)', 'vgt-sentinel'); ?></label>
                        <input type="text" name="brute_rename_login" class="vgt-input" value="<?php echo esc_attr( (string)$zeus_config['brute_rename_login'] ); ?>" placeholder="<?php echo esc_attr__('e.g. secure_portal', 'vgt-sentinel'); ?>">
                        <p class="vgt-setting-desc" style="margin-top:4px;"><?php esc_html_e('Zugang nur über /wp-login.php?dein_slug', 'vgt-sentinel'); ?></p>
                    </div>

                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Cryptographic Magic Cookie', 'vgt-sentinel'); ?></label>
                        <input type="text" name="brute_magic_cookie" class="vgt-input" value="<?php echo esc_attr( (string)$zeus_config['brute_magic_cookie'] ); ?>" placeholder="<?php echo esc_attr__('e.g. vgt_entry_token', 'vgt-sentinel'); ?>">
                        <p class="vgt-setting-desc" style="margin-top:4px;"><?php esc_html_e('WAF verlangt HMAC-verifiziertes Cookie.', 'vgt-sentinel'); ?></p>
                    </div>

                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Prometheus 404 Event Horizon', 'vgt-sentinel'); ?></label>
                        <input type="number" name="brute_404_lockout" class="vgt-input" value="<?php echo esc_attr( (string)$zeus_config['brute_404_lockout'] ); ?>" min="0">
                        <p class="vgt-setting-desc" style="margin-top:4px;"><?php esc_html_e('404s/Stunde vor NEMESIS Tarpit (0 = Off).', 'vgt-sentinel'); ?></p>
                    </div>

                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Max Login Failures', 'vgt-sentinel'); ?></label>
                        <input type="number" name="user_login_lockdown" class="vgt-input" value="<?php echo esc_attr( (string)$zeus_config['user_login_lockdown'] ); ?>" min="0">
                        <p class="vgt-setting-desc" style="margin-top:4px;"><?php esc_html_e('Maximalwert vor PROMETHEUS Lockdown.', 'vgt-sentinel'); ?></p>
                    </div>

                    <div class="vgt-input-group">
                        <label><?php esc_html_e('Force Logout Timeout', 'vgt-sentinel'); ?></label>
                        <input type="number" name="user_force_logout" class="vgt-input" value="<?php echo esc_attr( (string)$zeus_config['user_force_logout'] ); ?>" min="0">
                        <p class="vgt-setting-desc" style="margin-top:4px;"><?php esc_html_e('Inaktive Sessions beenden (Sekunden).', 'vgt-sentinel'); ?></p>
                    </div>

                </div>
            </div>

        </div> <!-- /.vgt-grid-2 -->

        <div class="vgt-action-bar">
            <div class="vgt-action-info">
                <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <span><?php esc_html_e('WAF Kompilierung ist O(1) atomar. (PHP .user.ini Caching durch den Server kann bis zu 5 Min. dauern).', 'vgt-sentinel'); ?></span>
            </div>
            <button type="submit" class="vgt-btn-primary">
                <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                <?php esc_html_e('COMPILE & DEPLOY WAF', 'vgt-sentinel'); ?>
            </button>
        </div>

    </form>
</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT VIA INCLUDE)
     ========================================================================================= -->
<script>
    <?php 
    $zeus_js_path = __DIR__ . '/zeus/script.js';
    if (is_readable($zeus_js_path)) {
        include $zeus_js_path;
    }
    ?>
</script>
