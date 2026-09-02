<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Recovery {
    public static function register(): void {
        if (!defined('WP_CLI') || WP_CLI !== true || !class_exists('WP_CLI')) return;
        \WP_CLI::add_command('gedefense titan recover', [self::class, 'recover']);
        \WP_CLI::add_command('gedefense titan rollback', [self::class, 'rollback']);
    }

    /** @param list<string> $args @param array<string, mixed> $assocArgs */
    public static function recover(array $args = [], array $assocArgs = []): void {
        unset($args, $assocArgs);
        $config = get_option('vis_config', []);
        $config = is_array($config) ? $config : [];
        $config['titan_profile'] = 'compatible';
        $config['titan_csp_mode'] = 'report_only';
        $config['titan_fetch_mode'] = 'audit';
        $config['titan_trusted_types_mode'] = 'off';
        $config['titan_coep_mode'] = 'off';
        $config['titan_login_gatekeeper'] = 0;
        $config['titan_application_lockdown'] = 0;
        if (!update_option('vis_config', $config, false) && get_option('vis_config', null) !== $config) \WP_CLI::error('TITAN recovery configuration could not be persisted.');
        VIS_Titan_Policy_Store::emergencyRecover($config);
        \WP_CLI::success('TITAN compatible report-only recovery policy activated; login gate and file lockdown disabled.');
    }

    /** @param list<string> $args @param array<string, mixed> $assocArgs */
    public static function rollback(array $args = [], array $assocArgs = []): void {
        unset($args, $assocArgs);
        if (!VIS_Titan_Policy_Store::rollback('LOCAL_WP_CLI_ROLLBACK')) \WP_CLI::error('No last-known-good TITAN policy is available.');
        \WP_CLI::success('TITAN rolled back to the last-known-good policy.');
    }
}
