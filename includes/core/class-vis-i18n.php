<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * GeDefense WP Multilanguage & Localization Matrix (I18N)
 *
 * Modular architecture supporting German (Default), English, and Russian.
 * Dictionary definitions are separated into standalone language files:
 * - includes/core/i18n/de.php
 * - includes/core/i18n/en.php
 * - includes/core/i18n/ru.php
 */
final class VIS_I18n {

    /** @var array<string, array<string, string>> Cached loaded dictionaries */
    private static array $dictionaries = [];

    /** @var string|null Active language cache */
    private static ?string $current_lang = null;

    /** @var array<string> Supported ISO language codes */
    public const SUPPORTED_LANGUAGES = ['de', 'en', 'ru'];

    public static function init(): void {
        self::maybe_handle_language_switch();
        self::localize_emoji_assets();

        add_filter('gettext_vgt-sentinel', [__CLASS__, 'filter_gettext'], 20, 3);
        add_filter('ngettext_vgt-sentinel', [__CLASS__, 'filter_ngettext'], 20, 5);
    }

    /**
     * Nonce action used by the dashboard language switcher.
     */
    public static function nonce_action(): string {
        return 'vis_lang_switch';
    }

    /**
     * Language switching.
     *
     * A state change (user meta, global option, cookie) may only happen behind a
     * valid nonce. A bare GET keeps a request-scoped preview but never persists
     * — this removes the CSRF/state-change-by-GET path without breaking links.
     */
    private static function maybe_handle_language_switch(): void {
        if (!isset($_REQUEST['vis_lang']) || !is_string($_REQUEST['vis_lang'])) {
            return;
        }

        $requested = strtolower(trim((string)$_REQUEST['vis_lang']));
        if (!in_array($requested, self::SUPPORTED_LANGUAGES, true)) {
            return;
        }

        $nonce = isset($_REQUEST['_wpnonce']) && is_string($_REQUEST['_wpnonce'])
            ? (string)$_REQUEST['_wpnonce']
            : '';

        $verified = $nonce !== ''
            && function_exists('wp_verify_nonce')
            && wp_verify_nonce($nonce, self::nonce_action()) !== false;

        if (!$verified) {
            self::$current_lang = $requested;
            return;
        }

        self::set_language($requested);
    }

    /**
     * Build a nonce-protected language switch URL.
     */
    public static function language_url(string $lang, ?string $base_url = null): string {
        if (!in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
            return (string)($base_url ?? '');
        }

        $base = $base_url ?? remove_query_arg('vis_lang');
        $url = add_query_arg('vis_lang', $lang, remove_query_arg('vis_lang', $base));

        return function_exists('wp_nonce_url') ? wp_nonce_url($url, self::nonce_action()) : $url;
    }

    public static function get_language(): string {
        if (self::$current_lang !== null) {
            return self::$current_lang;
        }

        // 1. Cookie Preference
        if (isset($_COOKIE['vis_lang']) && in_array($_COOKIE['vis_lang'], self::SUPPORTED_LANGUAGES, true)) {
            self::$current_lang = (string)$_COOKIE['vis_lang'];
            return self::$current_lang;
        }

        // 2. User Meta Preference
        if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('get_current_user_id')) {
            $uid = (int) get_current_user_id();
            if ($uid > 0) {
                $user_lang = get_user_meta($uid, 'vis_dashboard_lang', true);
                if (is_string($user_lang) && in_array($user_lang, self::SUPPORTED_LANGUAGES, true)) {
                    self::$current_lang = $user_lang;
                    return self::$current_lang;
                }
            }
        }

        // 3. Global Plugin Option
        $opt = get_option('vis_config', []);
        if (is_array($opt) && !empty($opt['dashboard_lang']) && in_array($opt['dashboard_lang'], self::SUPPORTED_LANGUAGES, true)) {
            self::$current_lang = (string)$opt['dashboard_lang'];
            return self::$current_lang;
        }

        // 4. Default: German
        self::$current_lang = 'de';
        return self::$current_lang;
    }

    public static function set_language(string $lang): void {
        if (!in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
            return;
        }
        self::$current_lang = $lang;

        if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('get_current_user_id')) {
            $uid = (int) get_current_user_id();
            if ($uid > 0) {
                update_user_meta($uid, 'vis_dashboard_lang', $lang);
            }
        }

        $opt = get_option('vis_config', []);
        $canPersistGlobal = function_exists('current_user_can')
            ? current_user_can('manage_options')
            : (function_exists('is_user_logged_in') && is_user_logged_in());
        if ($canPersistGlobal && is_array($opt)) {
            $opt['dashboard_lang'] = $lang;
            update_option('vis_config', $opt);
        }

        if (!headers_sent()) {
            $cookie_path = defined('COOKIEPATH') && is_string(COOKIEPATH) ? COOKIEPATH : '/';
            $cookie_domain = defined('COOKIE_DOMAIN') && is_string(COOKIE_DOMAIN) ? COOKIE_DOMAIN : '';
            setcookie('vis_lang', $lang, [
                'expires'  => time() + 31536000,
                'path'     => $cookie_path,
                'domain'   => $cookie_domain,
                'secure'   => function_exists('is_ssl') ? is_ssl() : false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Lazy-load language dictionary from file
     *
     * @param string $lang Language code ('de', 'en', 'ru')
     * @return array<string, string>
     */
    public static function get_dictionary(string $lang): array {
        if (!in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
            $lang = 'de';
        }

        if (!isset(self::$dictionaries[$lang])) {
            $path = __DIR__ . '/i18n/' . $lang . '.php';
            if (file_exists($path)) {
                $loaded = include $path;
                self::$dictionaries[$lang] = is_array($loaded) ? $loaded : [];
            } else {
                self::$dictionaries[$lang] = [];
            }
        }

        return self::$dictionaries[$lang];
    }

    public static function filter_gettext(string $translation, string $text, string $domain): string {
        if ($domain !== 'vgt-sentinel') {
            return $translation;
        }

        $lang = self::get_language();
        $dict = self::get_dictionary($lang);
        $lookup = trim($text);

        // 1. Direct match in active dictionary
        if (isset($dict[$lookup])) {
            return $dict[$lookup];
        }

        if (isset($dict[$text])) {
            return $dict[$text];
        }

        // 2. Case-insensitive fallback lookup
        foreach ($dict as $k => $v) {
            if (strcasecmp($k, $lookup) === 0) {
                return $v;
            }
        }

        return $translation;
    }

    public static function filter_ngettext(string $translation, string $single, string $plural, int $number, string $domain): string {
        if ($domain !== 'vgt-sentinel') {
            return $translation;
        }

        $lang = self::get_language();
        if ($lang === 'en') {
            return $number === 1 ? $single : $plural;
        }

        return $translation;
    }

    /**
     * Canonical local URL base for the bundled Twemoji 17.0.1 SVG mirror.
     */
    public static function local_emoji_base_url(): string {
        $base = defined('VIS_URL') ? (string)VIS_URL : '';
        if ($base === '' && function_exists('plugin_dir_url')) {
            $base = (string)plugin_dir_url(dirname(__DIR__) . '/gedefense-wp.php');
        }

        return ($base !== '' ? rtrim($base, '/') . '/' : '/') . 'assets/twemoji/svg/';
    }

    /**
     * Filter callback pinning WordPress emoji base URLs onto the local mirror.
     */
    public static function filter_emoji_base_url(): string {
        return self::local_emoji_base_url();
    }

    /**
     * Strip external WordPress/Twemoji CDN hosts (s.w.org, cdn.jsdelivr.net) from resource hints.
     *
     * @param array<int|string, mixed> $urls
     * @return array<int|string, mixed>
     */
    public static function filter_emoji_resource_hints(array $urls, string $relation_type): array {
        if (!in_array($relation_type, ['dns-prefetch', 'preconnect'], true)) {
            return $urls;
        }

        return array_values(array_filter($urls, static function (mixed $hint): bool {
            $href = is_array($hint) ? (string)($hint['href'] ?? '') : (string)$hint;
            $host = strtolower((string)(parse_url($href, PHP_URL_HOST) ?? ''));
            if ($host === '' && str_contains($href, '//')) {
                $host = strtolower((string)(parse_url('https:' . ltrim($href, '/'), PHP_URL_HOST) ?? ''));
            }
            return $host !== 's.w.org' && $host !== 'cdn.jsdelivr.net';
        }));
    }

    /**
     * Register priority-99 filters pinning Twemoji URLs to the bundled local mirror.
     */
    public static function register_emoji_url_filters(): void {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('emoji_svg_url', [__CLASS__, 'filter_emoji_base_url'], 99);
        add_filter('emoji_url', [__CLASS__, 'filter_emoji_base_url'], 99);
        add_filter('emoji_svg_ext', static fn(): string => '.svg', 99);
        add_filter('emoji_ext', static fn(): string => '.svg', 99);
        add_filter('wp_resource_hints', [__CLASS__, 'filter_emoji_resource_hints'], 99, 2);
    }

    /**
     * Serve every Twemoji graphic from the locally bundled mirror.
     *
     * WordPress ships its Twemoji runtime with a public jsDelivr CDN default
     * base URL and defaults emoji_svg_url to s.w.org. GeDefense bundles the
     * complete Twemoji 17.0.1 SVG set under assets/twemoji/svg/ and pins
     * emoji_svg_url / emoji_url at priority 99 across frontend, admin, login,
     * and embed contexts so the jsDelivr vendor fallback is never reached.
     * Feed and e-mail emoji conversion stays disabled.
     */
    private static function localize_emoji_assets(): void {
        self::register_emoji_url_filters();

        if (function_exists('add_action')) {
            add_action('init', [__CLASS__, 'register_emoji_url_filters'], 99);
        }

        if (function_exists('remove_filter')) {
            remove_filter('the_content_feed', 'wp_staticize_emoji', 10);
            remove_filter('comment_text_rss', 'wp_staticize_emoji', 10);
            remove_filter('wp_mail', 'wp_staticize_emoji_for_email', 10);
        }
    }

    /**
     * Locally bundled flag artwork (inline SVG, zero external requests).
     */
    private static function flag_svg(string $code): string {
        $safeCode = function_exists('esc_attr')
            ? esc_attr($code)
            : htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $style = 'vertical-align:-1px;border-radius:2px;margin-right:4px;box-shadow:0 0 0 1px rgba(255,255,255,.15);';
        $open  = '<svg class="vis-flag vis-flag-' . $safeCode . '" width="16" height="11" viewBox="0 0 16 11" aria-hidden="true" focusable="false" style="' . $style . '">';
        $close = '</svg>';

        return match ($code) {
            'de' => $open . '<rect width="16" height="3.67" fill="#000000"/><rect y="3.67" width="16" height="3.67" fill="#DD0000"/><rect y="7.34" width="16" height="3.66" fill="#FFCE00"/>' . $close,
            'gb' => $open . '<rect width="16" height="11" fill="#012169"/><path d="M0 0L16 11M16 0L0 11" stroke="#FFFFFF" stroke-width="2.2"/><path d="M0 0L16 11M16 0L0 11" stroke="#C8102E" stroke-width="1.1"/><path d="M8 0V11M0 5.5H16" stroke="#FFFFFF" stroke-width="3.2"/><path d="M8 0V11M0 5.5H16" stroke="#C8102E" stroke-width="1.8"/>' . $close,
            'ru' => $open . '<rect width="16" height="3.67" fill="#FFFFFF"/><rect y="3.67" width="16" height="3.67" fill="#0039A6"/><rect y="7.34" width="16" height="3.66" fill="#D52B1E"/>' . $close,
            default => '',
        };
    }

    public static function render_language_switcher(string $class = ''): string {
        $curr = self::get_language();
        $base_url = remove_query_arg('vis_lang');
        $de_url = self::language_url('de', $base_url);
        $en_url = self::language_url('en', $base_url);
        $ru_url = self::language_url('ru', $base_url);

        $html = '<div class="vis-lang-switcher ' . esc_attr($class) . '" style="display:inline-flex; align-items:center; gap:3px; background:rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.12); border-radius:20px; padding:2px 4px; font-size:11px; font-weight:700;">';
        
        // DE Pill
        $de_active = ($curr === 'de');
        $de_style = $de_active ? 'background:rgba(16,185,129,0.25); color:#10b981; border:1px solid rgba(16,185,129,0.5);' : 'color:#94a3b8; border:1px solid transparent;';
        $html .= '<a href="' . esc_url($de_url) . '" title="Deutsch" style="text-decoration:none; padding:3px 7px; border-radius:14px; transition:all 0.2s; ' . $de_style . '">' . self::flag_svg('de') . ' DE</a>';
        
        // EN Pill
        $en_active = ($curr === 'en');
        $en_style = $en_active ? 'background:rgba(59,130,246,0.25); color:#3b82f6; border:1px solid rgba(59,130,246,0.5);' : 'color:#94a3b8; border:1px solid transparent;';
        $html .= '<a href="' . esc_url($en_url) . '" title="English" style="text-decoration:none; padding:3px 7px; border-radius:14px; transition:all 0.2s; ' . $en_style . '">' . self::flag_svg('gb') . ' EN</a>';

        // RU Pill
        $ru_active = ($curr === 'ru');
        $ru_style = $ru_active ? 'background:rgba(239,68,68,0.25); color:#f87171; border:1px solid rgba(239,68,68,0.5);' : 'color:#94a3b8; border:1px solid transparent;';
        $html .= '<a href="' . esc_url($ru_url) . '" title="Русский" style="text-decoration:none; padding:3px 7px; border-radius:14px; transition:all 0.2s; ' . $ru_style . '">' . self::flag_svg('ru') . ' RU</a>';
        
        $html .= '</div>';
        return $html;
    }
}