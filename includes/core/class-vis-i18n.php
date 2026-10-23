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
        if (is_array($opt)) {
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
        $html .= '<a href="' . esc_url($de_url) . '" title="Deutsch" style="text-decoration:none; padding:3px 7px; border-radius:14px; transition:all 0.2s; ' . $de_style . '">🇩🇪 DE</a>';
        
        // EN Pill
        $en_active = ($curr === 'en');
        $en_style = $en_active ? 'background:rgba(59,130,246,0.25); color:#3b82f6; border:1px solid rgba(59,130,246,0.5);' : 'color:#94a3b8; border:1px solid transparent;';
        $html .= '<a href="' . esc_url($en_url) . '" title="English" style="text-decoration:none; padding:3px 7px; border-radius:14px; transition:all 0.2s; ' . $en_style . '">🇬🇧 EN</a>';

        // RU Pill
        $ru_active = ($curr === 'ru');
        $ru_style = $ru_active ? 'background:rgba(239,68,68,0.25); color:#f87171; border:1px solid rgba(239,68,68,0.5);' : 'color:#94a3b8; border:1px solid transparent;';
        $html .= '<a href="' . esc_url($ru_url) . '" title="Русский" style="text-decoration:none; padding:3px 7px; border-radius:14px; transition:all 0.2s; ' . $ru_style . '">🇷🇺 RU</a>';
        
        $html .= '</div>';
        return $html;
    }
}