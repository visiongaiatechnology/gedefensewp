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

<style>
    .lp-cockpit-wrapper {
        --lp-accent: <?php echo esc_attr($accent); ?>;
        --lp-bg: <?php echo esc_attr($background); ?>;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .lp-hero-banner {
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.12) 0%, rgba(168, 85, 247, 0.08) 50%, rgba(15, 23, 42, 0.6) 100%);
        border: 1px solid rgba(6, 182, 212, 0.35);
        border-radius: 14px;
        padding: 24px 28px;
        margin-bottom: 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        box-shadow: 0 0 30px rgba(6, 182, 212, 0.12);
        backdrop-filter: blur(12px);
    }

    .lp-hero-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .lp-hero-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        background: rgba(6, 182, 212, 0.15);
        border: 1px solid #00f0ff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #00f0ff;
        box-shadow: 0 0 20px rgba(0, 240, 255, 0.3);
        flex-shrink: 0;
    }

    .lp-hero-title {
        margin: 0 0 6px 0;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 1.5px;
        color: #fff;
        text-transform: uppercase;
    }
    .lp-hero-title span { color: #00f0ff; }

    .lp-hero-desc {
        margin: 0;
        font-size: 13px;
        color: #94a3b8;
        line-height: 1.5;
        max-width: 780px;
    }

    /* 2-COLUMN LAYOUT */
    .lp-grid {
        display: grid;
        grid-template-columns: 460px 1fr;
        gap: 28px;
        align-items: start;
    }
    @media (max-width: 1200px) {
        .lp-grid { grid-template-columns: 1fr; }
    }

    .lp-panel {
        background: rgba(15, 23, 42, 0.75);
        border: 1px solid rgba(148, 163, 184, 0.15);
        border-radius: 14px;
        padding: 26px;
        backdrop-filter: blur(10px);
        position: relative;
    }

    .lp-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.15);
    }
    .lp-panel-title {
        font-size: 15px;
        font-weight: 800;
        letter-spacing: 1px;
        color: #fff;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* PRESETS */
    .lp-swatches {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 18px;
    }
    .lp-swatch-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
    }
    .lp-swatch-btn:hover, .lp-swatch-btn.active {
        transform: scale(1.15);
        border-color: #fff;
        box-shadow: 0 0 15px currentColor;
    }

    /* FORM STYLES */
    .lp-form-row {
        margin-bottom: 18px;
    }
    .lp-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #cbd5e1;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    .lp-color-input-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .lp-color-picker {
        -webkit-appearance: none;
        border: none;
        width: 44px;
        height: 40px;
        border-radius: 8px;
        cursor: pointer;
        background: transparent;
        padding: 0;
    }
    .lp-color-picker::-webkit-color-swatch {
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .lp-input {
        width: 100%;
        background: rgba(2, 6, 23, 0.7);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 8px;
        padding: 11px 14px;
        color: #fff;
        font-size: 13px;
        outline: none;
        box-sizing: border-box;
        transition: all 0.2s;
    }
    .lp-input:focus {
        border-color: #00f0ff;
        box-shadow: 0 0 15px rgba(0, 240, 255, 0.2);
    }

    /* PREVIEW CONTAINER */
    .lp-browser-frame {
        background: #020617;
        border: 1px solid rgba(0, 240, 255, 0.3);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.8), 0 0 20px rgba(0, 240, 255, 0.15);
    }
    .lp-browser-bar {
        background: #0b1329;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding: 12px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }
    .lp-browser-dots {
        display: flex;
        gap: 6px;
    }
    .lp-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .lp-dot-red { background: #ef4444; }
    .lp-dot-yellow { background: #f59e0b; }
    .lp-dot-green { background: #10b981; }
    .lp-url-bar {
        flex-grow: 1;
        background: rgba(2, 6, 23, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 6px;
        padding: 6px 14px;
        font-family: monospace;
        font-size: 11px;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* MOCK SCREEN */
    .lp-mock-canvas {
        min-height: 520px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px;
        position: relative;
        background-color: var(--lp-bg);
        background-size: cover;
        background-position: center;
        transition: background 0.3s ease;
    }
    .lp-mock-card {
        width: 100%;
        max-width: 380px;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 16px;
        padding: 32px 28px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
        position: relative;
        z-index: 1;
        transition: all 0.3s ease;
    }
    .lp-mock-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--lp-accent), transparent);
        border-radius: 16px 16px 0 0;
    }
    .lp-mock-logo-area {
        text-align: center;
        margin-bottom: 22px;
    }
    .lp-mock-logo-img {
        max-height: 60px;
        max-width: 180px;
        margin: 0 auto;
        display: block;
        object-fit: contain;
    }
    .lp-mock-title {
        font-size: 20px;
        font-weight: 900;
        letter-spacing: 2px;
        color: #fff;
        text-transform: uppercase;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .lp-mock-title-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--lp-accent);
        box-shadow: 0 0 10px var(--lp-accent);
    }
    .lp-mock-sub {
        font-size: 10px;
        font-family: monospace;
        letter-spacing: 1px;
        color: #94a3b8;
        text-transform: uppercase;
        margin: 0;
    }
    .lp-mock-field {
        margin-bottom: 14px;
    }
    .lp-mock-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #cbd5e1;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    .lp-mock-input {
        width: 100%;
        background: rgba(2, 6, 23, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 8px;
        padding: 10px 12px;
        color: #fff;
        font-size: 13px;
        box-sizing: border-box;
        outline: none;
    }
    .lp-mock-btn {
        width: 100%;
        padding: 12px;
        background: var(--lp-accent);
        border: none;
        border-radius: 8px;
        color: #020617;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        cursor: default;
        margin-top: 14px;
        box-shadow: 0 0 20px rgba(0, 240, 255, 0.3);
    }
    .lp-mock-footer {
        margin-top: 18px;
        text-align: center;
        font-size: 11px;
        color: #64748b;
    }
</style>

<div class="lp-cockpit-wrapper">

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
                <button type="button" class="lp-swatch-btn" style="background:#00f0ff; color:#00f0ff;" title="Cyber Cyan" onclick="applyPreset('#070a13', '#00f0ff')"></button>
                <button type="button" class="lp-swatch-btn" style="background:#10b981; color:#10b981;" title="Emerald Matrix" onclick="applyPreset('#03150d', '#10b981')"></button>
                <button type="button" class="lp-swatch-btn" style="background:#a855f7; color:#a855f7;" title="Purple Haze" onclick="applyPreset('#0c071a', '#a855f7')"></button>
                <button type="button" class="lp-swatch-btn" style="background:#d4af37; color:#d4af37;" title="Apex Gold" onclick="applyPreset('#140f04', '#d4af37')"></button>
                <button type="button" class="lp-swatch-btn" style="background:#ef4444; color:#ef4444;" title="Crimson Core" onclick="applyPreset('#140404', '#ef4444')"></button>
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

<script>
function applyPreset(bg, accent) {
    document.getElementById('loginpager-bg').value = bg;
    document.getElementById('loginpager-bg-hex').value = bg;
    document.getElementById('loginpager-accent').value = accent;
    document.getElementById('loginpager-accent-hex').value = accent;
    
    var preview = document.getElementById('vis-loginpager-preview');
    if (preview) {
        preview.style.setProperty('--login-bg', bg);
        preview.style.setProperty('--login-accent', accent);
        preview.style.backgroundColor = bg;
    }
    var mockBtn = document.getElementById('lp-mock-btn');
    if (mockBtn) mockBtn.style.backgroundColor = accent;
    var mockDot = document.getElementById('lp-mock-dot');
    if (mockDot) {
        mockDot.style.backgroundColor = accent;
        mockDot.style.boxShadow = '0 0 10px ' + accent;
    }
}
</script>