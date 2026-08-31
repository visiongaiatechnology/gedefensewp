<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_LoginPager {
    private static ?self $instance = null;

    public static function get_instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_style'], 999);
        add_filter('login_headerurl', static fn(string $url): string => home_url('/'));
        add_filter('login_headertext', [$this, 'filter_login_headertext']);
    }

    public function filter_login_headertext(string $text): string {
        $config = get_option('vis_config', []);
        if (is_array($config) && !empty($config['loginpager_title'])) {
            return esc_html((string)$config['loginpager_title']);
        }
        return get_bloginfo('name');
    }

    public function enqueue_login_style(): void {
        $config = get_option('vis_config', []);
        $config = is_array($config) ? $config : [];
        if (empty($config['loginpager_enabled'])) return;

        $background = sanitize_hex_color((string)($config['loginpager_bg_color'] ?? '')) ?: '#070a13';
        $accent = sanitize_hex_color((string)($config['loginpager_accent'] ?? '')) ?: '#00f0ff';
        $backgroundImage = self::safe_url((string)($config['loginpager_bg_image'] ?? ''));
        $logo = self::safe_url((string)($config['loginpager_logo'] ?? ''));
        $blur = max(4, min(40, (int)($config['loginpager_glass_blur'] ?? 20)));
        [$red, $green, $blue] = self::rgb($accent);

        $backgroundRule = $backgroundImage === ''
            ? "background: radial-gradient(circle at 50% 15%, rgba({$red},{$green},{$blue},0.15) 0%, transparent 65%), radial-gradient(circle at 80% 85%, rgba({$red},{$green},{$blue},0.08) 0%, transparent 50%), {$background} !important;"
            : "background-color: {$background} !important; background-image: url('{$backgroundImage}') !important; background-position: center !important; background-size: cover !important;";

        $logoRule = $logo === ''
            ? ""
            : "background-image: url('{$logo}') !important; background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important; width: 100% !important; height: 80px !important;";

        $css = "
:root {
    --vis-login-accent: {$accent};
    --vis-login-rgb: {$red}, {$green}, {$blue};
    --vis-login-blur: {$blur}px;
}
body.login {
    {$backgroundRule}
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #f8fafc;
    position: relative;
    overflow-x: hidden;
}
body.login::before {
    content: '';
    position: fixed;
    inset: 0;
    background: 
        linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
    background-size: 32px 32px;
    pointer-events: none;
    z-index: 0;
}
.login #login {
    width: min(420px, calc(100vw - 32px));
    padding: 0;
    position: relative;
    z-index: 1;
    animation: tgFadeIn 0.4s ease-out;
}
@keyframes tgFadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
.login h1 {
    margin-bottom: 24px;
    text-align: center;
}
.login h1 a {
    {$logoRule}
    outline: none;
}
" . ($logo === '' ? "
.login h1 a {
    background-image: none !important;
    text-indent: 0 !important;
    font-size: 24px !important;
    font-weight: 900 !important;
    letter-spacing: 2px !important;
    color: #fff !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 10px !important;
    width: auto !important;
    height: auto !important;
    text-decoration: none !important;
    text-transform: uppercase !important;
}
.login h1 a::after {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--vis-login-accent);
    box-shadow: 0 0 12px var(--vis-login-accent);
}
" : "") . "
.login form {
    background: rgba(15, 23, 42, 0.75) !important;
    backdrop-filter: blur(var(--vis-login-blur)) !important;
    -webkit-backdrop-filter: blur(var(--vis-login-blur)) !important;
    border: 1px solid rgba(var(--vis-login-rgb), 0.25) !important;
    border-radius: 16px !important;
    box-shadow: 0 0 40px rgba(var(--vis-login-rgb), 0.15), 0 25px 60px rgba(0, 0, 0, 0.7) !important;
    padding: 36px 30px !important;
    margin-top: 0 !important;
}
.login form::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--vis-login-accent), transparent);
    border-radius: 16px 16px 0 0;
}
.login label {
    color: #cbd5e1 !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: 0.8px !important;
    text-transform: uppercase !important;
    margin-bottom: 8px !important;
}
.login input[type=text],
.login input[type=password],
.login input[type=email] {
    width: 100% !important;
    background: rgba(2, 6, 23, 0.8) !important;
    border: 1px solid rgba(148, 163, 184, 0.2) !important;
    color: #fff !important;
    border-radius: 8px !important;
    padding: 12px 14px !important;
    font-size: 15px !important;
    box-sizing: border-box !important;
    outline: none !important;
    transition: all 0.2s ease !important;
}
.login input[type=text]:focus,
.login input[type=password]:focus,
.login input[type=email]:focus {
    border-color: var(--vis-login-accent) !important;
    box-shadow: 0 0 15px rgba(var(--vis-login-rgb), 0.35) !important;
}
.login .forgetmenot {
    display: flex !important;
    align-items: center !important;
    margin-top: 14px !important;
}
.login .forgetmenot label {
    font-size: 11px !important;
    text-transform: none !important;
    color: #94a3b8 !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    cursor: pointer !important;
}
.login input[type=checkbox] {
    accent-color: var(--vis-login-accent) !important;
}
.login .submit {
    margin-top: 20px !important;
}
.login .button-primary {
    width: 100% !important;
    padding: 13px !important;
    background: linear-gradient(135deg, rgba(var(--vis-login-rgb), 0.9) 0%, rgba(var(--vis-login-rgb), 0.6) 100%) !important;
    background-color: var(--vis-login-accent) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 8px !important;
    color: #020617 !important;
    font-size: 13px !important;
    font-weight: 800 !important;
    letter-spacing: 1.5px !important;
    text-transform: uppercase !important;
    cursor: pointer !important;
    box-shadow: 0 0 20px rgba(var(--vis-login-rgb), 0.35) !important;
    transition: all 0.2s ease !important;
    height: auto !important;
}
.login .button-primary:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 0 30px rgba(var(--vis-login-rgb), 0.55) !important;
    color: #000 !important;
}
.login #nav, .login #backtoblog {
    text-align: center !important;
    margin-top: 18px !important;
    padding: 0 !important;
}
.login #nav a, .login #backtoblog a {
    color: #94a3b8 !important;
    font-size: 12px !important;
    text-decoration: none !important;
    transition: color 0.2s !important;
}
.login #nav a:hover, .login #backtoblog a:hover {
    color: var(--vis-login-accent) !important;
}
.login #login_error, .login .message, .login .success {
    background: rgba(15, 23, 42, 0.8) !important;
    border-radius: 8px !important;
    color: #f8fafc !important;
    backdrop-filter: blur(12px) !important;
    padding: 12px 16px !important;
    border-left: 4px solid var(--vis-login-accent) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
}
.login #login_error {
    border-left-color: #ef4444 !important;
    color: #fca5a5 !important;
}
.login .language-switcher {
    display: none !important;
}
";

        wp_register_style('vis-loginpager', false, [], defined('VIS_VERSION') ? VIS_VERSION : '8');
        wp_enqueue_style('vis-loginpager');
        wp_add_inline_style('vis-loginpager', $css);
    }

    public static function safe_url(string $value): string {
        $url = esc_url_raw(trim($value), ['https', 'http']);
        return is_string($url) ? str_replace(["'", '"', '(', ')', '\\\\'], '', $url) : '';
    }

    private static function rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}