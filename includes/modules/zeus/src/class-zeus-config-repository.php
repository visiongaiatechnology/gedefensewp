<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * CANONICAL ZEUS CONFIGURATION REPOSITORY & SANITIZER
 * Single authoritative source of truth for Zeus configuration mutations, defaults, and staging.
 */
final class Zeus_Config_Repository {

    public const OPTION_KEY = 'vis_zeus_config';

    public static function getDefaults(): array {
        $homeHost = function_exists('home_url') ? parse_url(home_url(), PHP_URL_HOST) : 'localhost';
        $canonicalHost = is_string($homeHost) && $homeHost !== '' ? $homeHost : 'localhost';

        return [
            'zeus_enabled'          => true,
            'security_profile'      => 'BALANCED', // BALANCED, STRICT, PARANOID, CUSTOM
            'host_lock_mode'        => 'DISABLED', // DISABLED, AUDIT, REJECT
            'canonical_hosts'       => [$canonicalHost],
            'allowed_methods'       => ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'],
            'max_query_length'      => 2048,
            'max_query_params'      => 100,
            'max_header_count'      => 50,
            'max_header_size'       => 16384,
            'max_cookie_size'       => 8192,
            'max_body_default'      => 67108864, // 64MB
            'budget_enabled'        => true,
            'budget_ip_limit'       => 180,
            'budget_subnet_limit'   => 450,
            'budget_action_mode'    => 'THROTTLE', // THROTTLE, TEMPORARY_REJECT, XDR_SIGNAL
            'login_admission_mode'  => 'DISABLED', // DISABLED, ADMISSION_TOKEN
            'learning_mode_enabled' => false,
            'lockdown_state'        => 'NORMAL', // NORMAL, HARDENED, FORTRESS, INCIDENT_LOCKDOWN, RECOVERY
            'fw_basic'              => true,
            'fw_6g_blacklist'       => true,
            'fw_fake_googlebot'     => true,
            'fw_block_xmlrpc'       => true,
            'brute_rename_login'    => '',
            'brute_magic_cookie'    => '',
            'brute_404_lockout'     => 20,
            'user_login_lockdown'   => 5,
            'user_force_logout'     => 3600,
            'fs_disable_edit'       => false,
            'fs_prevent_hotlink'    => false,
            'spam_comment_block'    => false,
            'policy_digest'         => ''
        ];
    }

    public static function get(): array {
        $stored = get_option(self::OPTION_KEY, []);
        $defaults = self::getDefaults();
        if (!is_array($stored)) return $defaults;
        return array_merge($defaults, $stored);
    }

    public static function sanitize(array $raw, ?array $current = null): array {
        if ($current === null) {
            $current = self::get();
        }

        // Detect full dashboard form submission vs partial programmatic update
        $isFormSubmit = isset($raw['vis_zeus_nonce']) || isset($raw['security_profile']) || isset($raw['vgt_zeus_form_submit']);

        $str_clean = static fn($s) => function_exists('sanitize_text_field') ? sanitize_text_field((string)$s) : trim(strip_tags((string)$s));
        $key_clean = static fn($s) => function_exists('sanitize_key') ? sanitize_key((string)$s) : preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$s));
        $txt_clean = static fn($s) => function_exists('sanitize_textarea_field') ? sanitize_textarea_field((string)$s) : trim(strip_tags((string)$s));

        // Canonical Hosts
        $canonicalHosts = $current['canonical_hosts'] ?? [];
        if (isset($raw['canonical_hosts'])) {
            if (is_array($raw['canonical_hosts'])) {
                $canonicalHosts = array_map($str_clean, $raw['canonical_hosts']);
            } elseif (is_string($raw['canonical_hosts'])) {
                $canonicalHosts = array_filter(array_map('trim', explode("\n", $txt_clean($raw['canonical_hosts']))));
            }
        }
        $canonicalHosts = array_values(array_unique(array_filter($canonicalHosts)));
        if (empty($canonicalHosts) && function_exists('home_url')) {
            $homeHost = parse_url(home_url(), PHP_URL_HOST);
            if ($homeHost) $canonicalHosts = [$homeHost];
        }

        // Allowed Methods
        $allowedMethods = $current['allowed_methods'] ?? ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'];
        if (isset($raw['allowed_methods'])) {
            $allowedMethods = is_array($raw['allowed_methods'])
                ? array_map('strtoupper', array_map($str_clean, $raw['allowed_methods']))
                : ['GET', 'POST'];
        }

        $validProfiles = ['BALANCED', 'STRICT', 'PARANOID', 'CUSTOM'];
        $profile = strtoupper($key_clean($raw['security_profile'] ?? $current['security_profile'] ?? 'BALANCED'));
        if (!in_array($profile, $validProfiles, true)) $profile = 'BALANCED';

        $validHostLock = ['DISABLED', 'AUDIT', 'REJECT'];
        $hostLock = strtoupper($key_clean($raw['host_lock_mode'] ?? $current['host_lock_mode'] ?? 'DISABLED'));
        if (!in_array($hostLock, $validHostLock, true)) $hostLock = 'DISABLED';

        $validLockdown = ['NORMAL', 'HARDENED', 'FORTRESS', 'INCIDENT_LOCKDOWN', 'RECOVERY'];
        $lockdown = strtoupper($key_clean($raw['lockdown_state'] ?? $current['lockdown_state'] ?? 'NORMAL'));
        if (!in_array($lockdown, $validLockdown, true)) $lockdown = 'NORMAL';

        $validAdmission = ['DISABLED', 'ADMISSION_TOKEN'];
        $admission = strtoupper($key_clean($raw['login_admission_mode'] ?? $current['login_admission_mode'] ?? 'DISABLED'));
        if (!in_array($admission, $validAdmission, true)) $admission = 'DISABLED';

        $validBudgetAction = ['THROTTLE', 'TEMPORARY_REJECT', 'XDR_SIGNAL'];
        $budgetAction = strtoupper($key_clean($raw['budget_action_mode'] ?? $current['budget_action_mode'] ?? 'THROTTLE'));
        if (!in_array($budgetAction, $validBudgetAction, true)) $budgetAction = 'THROTTLE';

        // Checkbox resolution: In a form submission, an absent checkbox key means FALSE!
        $getBool = static function(string $key) use ($raw, $current, $isFormSubmit): bool {
            if ($isFormSubmit) {
                return !empty($raw[$key]);
            }
            return isset($raw[$key]) ? (bool)$raw[$key] : (!empty($current[$key]));
        };

        $clean = [
            'zeus_enabled'          => $getBool('zeus_enabled'),
            'security_profile'      => $profile,
            'host_lock_mode'        => $hostLock,
            'canonical_hosts'       => $canonicalHosts,
            'allowed_methods'       => array_values(array_intersect($allowedMethods, ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE', 'TRACE', 'CONNECT'])),
            'max_query_length'      => max(64, min(16384, (int)($raw['max_query_length'] ?? $current['max_query_length'] ?? 2048))),
            'max_query_params'      => max(1, min(500, (int)($raw['max_query_params'] ?? $current['max_query_params'] ?? 100))),
            'max_header_count'      => max(5, min(200, (int)($raw['max_header_count'] ?? $current['max_header_count'] ?? 50))),
            'max_header_size'       => max(1024, min(65536, (int)($raw['max_header_size'] ?? $current['max_header_size'] ?? 16384))),
            'max_cookie_size'       => max(512, min(32768, (int)($raw['max_cookie_size'] ?? $current['max_cookie_size'] ?? 8192))),
            'max_body_default'      => max(1024, (int)($raw['max_body_default'] ?? $current['max_body_default'] ?? 67108864)),
            'budget_enabled'        => $getBool('budget_enabled'),
            'budget_ip_limit'       => max(1, min(5000, (int)($raw['budget_ip_limit'] ?? $current['budget_ip_limit'] ?? 180))),
            'budget_subnet_limit'   => max(2, min(10000, (int)($raw['budget_subnet_limit'] ?? $current['budget_subnet_limit'] ?? 450))),
            'budget_action_mode'    => $budgetAction,
            'login_admission_mode'  => $admission,
            'learning_mode_enabled' => $getBool('learning_mode_enabled'),
            'lockdown_state'        => $lockdown,
            'fw_basic'              => $getBool('fw_basic'),
            'fw_6g_blacklist'       => $getBool('fw_6g_blacklist'),
            'fw_fake_googlebot'     => $getBool('fw_fake_googlebot'),
            'fw_block_xmlrpc'       => $getBool('fw_block_xmlrpc'),
            'brute_rename_login'    => $str_clean($raw['brute_rename_login'] ?? $current['brute_rename_login'] ?? ''),
            'brute_magic_cookie'    => $str_clean($raw['brute_magic_cookie'] ?? $current['brute_magic_cookie'] ?? ''),
            'brute_404_lockout'     => max(5, min(100, (int)($raw['brute_404_lockout'] ?? $current['brute_404_lockout'] ?? 20))),
            'user_login_lockdown'   => max(1, min(20, (int)($raw['user_login_lockdown'] ?? $current['user_login_lockdown'] ?? 5))),
            'user_force_logout'     => max(60, min(86400, (int)($raw['user_force_logout'] ?? $current['user_force_logout'] ?? 3600))),
            'fs_disable_edit'       => $getBool('fs_disable_edit'),
            'fs_prevent_hotlink'    => $getBool('fs_prevent_hotlink'),
            'spam_comment_block'    => $getBool('spam_comment_block'),
        ];

        $clean['policy_digest'] = Zeus_Policy_Manager::computeDigest($clean);
        return $clean;
    }

    /**
     * Authoritative save pipeline: sanitize, stage via Policy Manager, deploy, return result.
     */
    public static function save(array $raw): array {
        $current = self::get();
        $sanitized = self::sanitize($raw, $current);

        return Zeus_Policy_Manager::stageAndActivate(
            $sanitized,
            static function(array $cfg) {
                if (class_exists('\VIS_Zeus')) {
                    $zeus = new \VIS_Zeus();
                    return $zeus->deploy_perimeter_shield();
                }
                return ['waf' => true, 'environment' => ['user_ini' => true, 'htaccess' => true, 'wp_config' => true]];
            }
        );
    }
}
