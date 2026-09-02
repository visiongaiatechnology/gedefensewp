<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$config = isset($opt) && is_array($opt) ? $opt : [];
$background = sanitize_hex_color((string)($config['loginpager_bg_color'] ?? '')) ?: '#070a13';
$accent = sanitize_hex_color((string)($config['loginpager_accent'] ?? '')) ?: '#00f0ff';
$backgroundImage = class_exists('VIS_LoginPager') ? VIS_LoginPager::safe_url((string)($config['loginpager_bg_image'] ?? '')) : '';
$logo = class_exists('VIS_LoginPager') ? VIS_LoginPager::safe_url((string)($config['loginpager_logo'] ?? '')) : '';
$title = (string)($config['loginpager_title'] ?? get_bloginfo('name'));
$subtitle = (string)($config['loginpager_subtitle'] ?? 'ZERO-TRUST AUTHENTICATION GATEWAY');
$blur = max(4, min(40, (int)($config['loginpager_glass_blur'] ?? 20)));
$is_enabled = !empty($config['loginpager_enabled']);
?>

<div class="lp-cockpit-wrapper" style="--lp-accent:<?php echo esc_attr($accent); ?>;--lp-bg:<?php echo esc_attr($background); ?>;">

    <!-- HERO HEADER -->
    <div class="lp-hero-banner">
        <div class="lp-hero-left">
            <div class="lp-hero-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <div>
                <h2 class="lp-hero-title">LOGIN<span>PAGER</span> // <?php esc_html_e('SOVEREIGN LOGIN SURFACE', 'vgt-sentinel'); ?></h2>
                <p class="lp-hero-desc"><?php esc_html_e('Autarke, hochmoderne Gestaltung der nativen WordPress-Anmeldeseite ohne externe Assets oder CDNs. Mit reaktiver Live-Vorschau und Cyberpunk-Optik.', 'vgt-sentinel'); ?></p>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="<?php echo esc_url(wp_login_url()); ?>" target="_blank" class="vgt-btn vgt-btn-secondary" style="text-decoration:none; padding:10px 16px; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
                <span><?php esc_html_e('Login-Seite testen', 'vgt-sentinel'); ?></span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
            <span style="font-size:11px; font-family:monospace; font-weight:800; letter-spacing:1px; padding:6px 14px; border-radius:99px; display:inline-flex; align-items:center; gap:6px; <?php echo $is_enabled ? 'background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.4); color:#10b981;' : 'background:rgba(148,163,184,0.1); border:1px solid rgba(148,163,184,0.2); color:#94a3b8;'; ?>">
                <span style="width:8px; height:8px; border-radius:50%; background:currentColor;"></span>
                <?php echo $is_enabled ? esc_html__('AKTIV', 'vgt-sentinel') : esc_html__('DEAKTIVIERT', 'vgt-sentinel'); ?>
            </span>
        </div>
    </div>

    <!-- MAIN 2-COLUMN GRID -->
    <div class="lp-grid">
        
        <!-- LEFT: CONTROLS -->
        <div class="lp-panel">
            <div class="lp-panel-header">
                <div class="lp-panel-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <?php esc_html_e('Design & Branding Konfiguration', 'vgt-sentinel'); ?>
                </div>
                <label class="vis-switch">
                    <input type="checkbox" name="vis_config[loginpager_enabled]" value="1" <?php checked($is_enabled); ?>>
                    <span class="vis-slider"></span>
                </label>
            </div>

            <!-- PRESET THEMES -->
            <label class="lp-label"><?php esc_html_e('Farbthemen & Schnell-Presets', 'vgt-sentinel'); ?></label>
            <div class="lp-swatches">
                <button type="button" class="lp-swatch-btn" style="background:#00f0ff; color:#00f0ff;" title="Cyber Cyan" data-login-bg="#070a13" data-login-accent="#00f0ff"></button>
                <button type="button" class="lp-swatch-btn" style="background:#10b981; color:#10b981;" title="Emerald Matrix" data-login-bg="#03150d" data-login-accent="#10b981"></button>
                <button type="button" class="lp-swatch-btn" style="background:#a855f7; color:#a855f7;" title="Purple Haze" data-login-bg="#0c071a" data-login-accent="#a855f7"></button>
                <button type="button" class="lp-swatch-btn" style="background:#d4af37; color:#d4af37;" title="Apex Gold" data-login-bg="#140f04" data-login-accent="#d4af37"></button>
                <button type="button" class="lp-swatch-btn" style="background:#ef4444; color:#ef4444;" title="Crimson Core" data-login-bg="#140404" data-login-accent="#ef4444"></button>
            </div>

            <!-- BACKGROUND COLOR -->
            <div class="lp-form-row">
                <label class="lp-label" for="loginpager-bg"><?php esc_html_e('Hintergrundfarbe', 'vgt-sentinel'); ?></label>
                <div class="lp-color-input-wrap">
                    <input class="lp-color-picker" id="loginpager-bg" type="color" name="vis_config[loginpager_bg_color]" value="<?php echo esc_attr($background); ?>">
                    <input class="lp-input" id="loginpager-bg-hex" type="text" value="<?php echo esc_attr($background); ?>" style="font-family:monospace; max-width:120px;">
                </div>
            </div>

            <!-- ACCENT COLOR -->
            <div class="lp-form-row">
                <label class="lp-label" for="loginpager-accent"><?php esc_html_e('Akzent- & Glühfarbe', 'vgt-sentinel'); ?></label>
                <div class="lp-color-input-wrap">
                    <input class="lp-color-picker" id="loginpager-accent" type="color" name="vis_config[loginpager_accent]" value="<?php echo esc_attr($accent); ?>">
                    <input class="lp-input" id="loginpager-accent-hex" type="text" value="<?php echo esc_attr($accent); ?>" style="font-family:monospace; max-width:120px;">
                </div>
            </div>

            <!-- BACKGROUND IMAGE -->
            <div class="lp-form-row">
                <label class="lp-label" for="loginpager-image"><?php esc_html_e('Hintergrundbild-URL (optional)', 'vgt-sentinel'); ?></label>
                <input id="loginpager-image" class="lp-input" type="url" name="vis_config[loginpager_bg_image]" value="<?php echo esc_url($backgroundImage); ?>" placeholder="https://example.org/background.webp">
            </div>

            <!-- LOGO URL -->
            <div class="lp-form-row">
                <label class="lp-label" for="loginpager-logo"><?php esc_html_e('Logo-URL (optional)', 'vgt-sentinel'); ?></label>
                <input id="loginpager-logo" class="lp-input" type="url" name="vis_config[loginpager_logo]" value="<?php echo esc_url($logo); ?>" placeholder="https://example.org/logo.svg">
            </div>

            <!-- BRANDING TITLE -->
            <div class="lp-form-row">
                <label class="lp-label" for="loginpager-title"><?php esc_html_e('Portal-Titel / Überschrift', 'vgt-sentinel'); ?></label>
                <input id="loginpager-title" class="lp-input" type="text" name="vis_config[loginpager_title]" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </div>

            <!-- SUBTITLE -->
            <div class="lp-form-row">
                <label class="lp-label" for="loginpager-subtitle"><?php esc_html_e('Untertitel / Sicherheits-Badge', 'vgt-sentinel'); ?></label>
                <input id="loginpager-subtitle" class="lp-input" type="text" name="vis_config[loginpager_subtitle]" value="<?php echo esc_attr($subtitle); ?>" placeholder="ZERO-TRUST AUTHENTICATION GATEWAY">
            </div>

            <div style="margin-top:24px;">
                <button type="submit" class="vgt-btn vgt-btn-primary" style="width:100%; padding:14px; font-weight:800; font-size:13px; letter-spacing:1px; text-transform:uppercase;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <?php esc_html_e('LOGINPAGER SPEICHERN', 'vgt-sentinel'); ?>
                </button>
            </div>
        </div>

        <!-- RIGHT: INTERACTIVE LIVE PREVIEW -->
        <div class="lp-browser-frame">
            <div class="lp-browser-bar">
                <div class="lp-browser-dots">
                    <span class="lp-dot lp-dot-red"></span>
                    <span class="lp-dot lp-dot-yellow"></span>
                    <span class="lp-dot lp-dot-green"></span>
                </div>
                <div class="lp-url-bar">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <span><?php echo esc_html(home_url('/wp-login.php')); ?></span>
                </div>
                <span style="font-size:10px; font-family:monospace; color:#10b981; font-weight:700;">● LIVE PREVIEW</span>
            </div>

            <div class="lp-mock-canvas" id="vis-loginpager-preview" style="--login-bg:<?php echo esc_attr($background); ?>;--login-accent:<?php echo esc_attr($accent); ?>;--login-image:<?php echo $backgroundImage !== '' ? "url('" . esc_url($backgroundImage) . "')" : 'none'; ?>;--login-logo:<?php echo $logo !== '' ? "url('" . esc_url($logo) . "')" : 'none'; ?>;">
                
                <div class="lp-mock-card" id="lp-mock-card">
                    <div class="lp-mock-logo-area">
                        <div id="lp-mock-logo-wrap" style="<?php echo $logo === '' ? 'display:none;' : ''; ?>">
                            <img id="lp-mock-logo-img" class="lp-mock-logo-img" src="<?php echo esc_url($logo); ?>" alt="Logo">
                        </div>
                        <h2 class="lp-mock-title" id="lp-mock-title-text" style="<?php echo $logo !== '' ? 'display:none;' : ''; ?>">
                            <span id="lp-mock-title-val"><?php echo esc_html($title); ?></span>
                            <span class="lp-mock-title-dot" id="lp-mock-dot"></span>
                        </h2>
                        <p class="lp-mock-sub" id="lp-mock-sub-text"><?php echo esc_html($subtitle); ?></p>
                    </div>

                    <div class="lp-mock-field">
                        <label class="lp-mock-label"><?php esc_html_e('Benutzername oder E-Mail-Adresse', 'vgt-sentinel'); ?></label>
                        <input class="lp-mock-input" type="text" value="admin" readonly>
                    </div>

                    <div class="lp-mock-field">
                        <label class="lp-mock-label"><?php esc_html_e('Passwort', 'vgt-sentinel'); ?></label>
                        <input class="lp-mock-input" type="password" value="••••••••••••" readonly>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:11px; color:#94a3b8;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" checked disabled> <?php esc_html_e('Angemeldet bleiben', 'vgt-sentinel'); ?>
                        </label>
                        <span><?php esc_html_e('Passwort vergessen?', 'vgt-sentinel'); ?></span>
                    </div>

                    <button type="button" class="lp-mock-btn" id="lp-mock-btn"><?php esc_html_e('ANMELDEN →', 'vgt-sentinel'); ?></button>

                    <div class="lp-mock-footer">
                        GEDEFENSE WP // ZERO-TRUST AUTH MATRIX
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

