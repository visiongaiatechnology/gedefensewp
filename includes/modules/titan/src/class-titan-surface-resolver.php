<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Surface_Resolver {
    public const PUBLIC_FRONTEND = 'PUBLIC_FRONTEND';
    public const LOGIN = 'LOGIN';
    public const ADMIN = 'ADMIN';
    public const GEDEFENSE_ADMIN = 'GEDEFENSE_ADMIN';
    public const REST = 'REST';
    public const AJAX = 'AJAX';
    public const CRON = 'CRON';
    public const DOWNLOAD = 'DOWNLOAD';
    public const ACTIVE_CONTENT_PREVIEW = 'ACTIVE_CONTENT_PREVIEW';
    public const API = 'API';
    public const WEBHOOK = 'WEBHOOK';
    public const UNKNOWN = 'UNKNOWN';

    /** @return list<string> */
    public static function all(): array {
        return [self::PUBLIC_FRONTEND, self::LOGIN, self::ADMIN, self::GEDEFENSE_ADMIN, self::REST, self::AJAX, self::CRON, self::DOWNLOAD, self::ACTIVE_CONTENT_PREVIEW, self::API, self::WEBHOOK, self::UNKNOWN];
    }

    public static function resolve(?string $uri = null): string {
        $uri ??= (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? strtolower('/' . ltrim($path, '/')) : '/';

        if ((defined('DOING_CRON') && DOING_CRON) || str_ends_with($path, '/wp-cron.php')) return self::CRON;
        if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || str_ends_with($path, '/admin-ajax.php')) return self::AJAX;
        if (isset($_GET['vis_titan_preview']) || str_contains($path, '/vis-titan-preview/')) return self::ACTIVE_CONTENT_PREVIEW;
        if (isset($_GET['vgt_download']) || isset($_GET['vis_download']) || str_contains($path, '/vgt-download/')) return self::DOWNLOAD;
        if ((defined('REST_REQUEST') && REST_REQUEST) || str_contains($path, '/wp-json/')) return self::REST;
        if (str_ends_with($path, '/wp-login.php')) return self::LOGIN;
        if (self::isWebhook($path)) return self::WEBHOOK;
        if (self::isMachineApi($path)) return self::API;
        if (function_exists('is_admin') && is_admin()) {
            $page = isset($_GET['page']) && is_string($_GET['page']) ? sanitize_key($_GET['page']) : '';
            return str_starts_with($page, 'vgt-') || str_starts_with($page, 'vis-') ? self::GEDEFENSE_ADMIN : self::ADMIN;
        }
        return self::PUBLIC_FRONTEND;
    }

    public static function isSensitive(string $surface): bool {
        return in_array($surface, [self::LOGIN, self::ADMIN, self::GEDEFENSE_ADMIN, self::AJAX, self::ACTIVE_CONTENT_PREVIEW], true);
    }

    public static function isMachineCompatible(string $surface): bool {
        return in_array($surface, [self::REST, self::API, self::WEBHOOK, self::CRON], true);
    }

    private static function isWebhook(string $path): bool {
        foreach (['/wc-api/', '/webhook/', '/hooks/', '/callback/'] as $marker) if (str_contains($path, $marker)) return true;
        return false;
    }

    private static function isMachineApi(string $path): bool {
        return str_contains($path, '/xmlrpc.php') || str_contains($path, '/api/') || str_contains($path, '/admin-post.php');
    }
}
