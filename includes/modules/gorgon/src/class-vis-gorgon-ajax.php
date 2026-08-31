<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Gorgon;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * VGT OMEGA PROTOCOL - GORGON NEURAL AJAX CONTROLLER
 * STATUS: DIAMANT VGT SUPREME
 */
final class Gorgon_Ajax {

    public static function mount_endpoints(): void {
        $actions = ['toggle', 'update_config', 'ping_nexus', 'sync', 'add_node', 'remove_node'];
        foreach ($actions as $action) {
            add_action("wp_ajax_vgt_gorgon_{$action}", [self::class, "handle_{$action}"]);
        }
    }

    private static function verify_privileges(): void {
        check_ajax_referer('vgt_gorgon_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'VGT_UNAUTHORIZED_ACCESS']);
        }
    }

    public static function handle_toggle(): void {
        self::verify_privileges();
        $enabled = isset($_POST['enabled']) && filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN);
        $config = get_option('vis_config', []);
        $config['gorgon_enabled'] = $enabled ? 1 : 0;
        update_option('vis_config', $config);
        wp_send_json_success(['status' => $enabled ? 'active' : 'inactive']);
    }

    public static function handle_update_config(): void {
        self::verify_privileges();

        try {
            $url = \VIS_Security::validate_public_http_url((string)($_POST['url'] ?? ''), true);
        } catch (\Throwable $e) {
            error_log('[VGT GORGON SECURITY] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Nexus endpoint rejected. HTTPS public endpoint required.']);
            return;
        }
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';

        if (empty($url)) {
            wp_send_json_error(['message' => 'URL fehlt.']);
        }

        $config = get_option('vis_config', []);
        if (!is_array($config)) $config = [];

        if (empty($key) && empty($config['gorgon_api_key'])) {
            wp_send_json_error(['message' => 'Key fehlt.']);
        }

        $config['gorgon_nexus_url'] = $url;
        $config['gorgon_nexus_preemptive_url'] = str_replace('/sync', '/query', $url);

        if (!empty($key)) {
            $config['gorgon_api_key'] = class_exists('\\VIS_Vault') ? \VIS_Vault::encrypt($key) : $key;
        }

        update_option('vis_config', $config);

        wp_send_json_success(['message' => 'Nexus Bridge konfiguriert.']);
    }

    public static function handle_ping_nexus(): void {
        self::verify_privileges();

        $config = get_option('vis_config', []);
        if (!is_array($config)) $config = [];

        try {
            $url = \VIS_Security::validate_public_http_url((string)($config['gorgon_nexus_url'] ?? ''), true);
        } catch (\Throwable $e) {
            error_log('[VGT GORGON SECURITY] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Gespeicherter Nexus-Endpunkt wurde verworfen.', 'debug_status' => 'URL_REJECTED']);
            return;
        }
        $api_key = self::resolve_api_key($config);

        if (empty($url)) {
            wp_send_json_error(['message' => 'Kein Nexus-Endpunkt gespeichert.', 'debug_status' => 'LOCAL_DB_EMPTY']);
            return;
        }

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'Kein Nexus-Key gespeichert.', 'debug_status' => 'LOCAL_KEY_EMPTY']);
            return;
        }

        $payload = wp_json_encode([
            'site'    => self::get_node_id(),
            'vectors' => [],
            'pull'    => true,
        ]);

        if (false === $payload) {
            wp_send_json_error(['message' => 'Telemetry payload rejected.', 'debug_status' => 'JSON_ENCODE_FAILED']);
            return;
        }

        error_log('[VGT GORGON PING] Hitting telemetry sync: ' . $url);

        $response = wp_remote_post($url, [
            'body'      => $payload,
            'timeout'   => 8,
            'sslverify' => true,
            'redirection' => 0,
            'headers'   => [
                'X-VGT-Nexus-Auth'       => $api_key,
                'X-VGT-Sentinel-Magic'   => 'vgt-magic-noc-9938-omega-fusion',
                'X-VGT-Sentinel-Version' => defined('VIS_VERSION') ? VIS_VERSION : '7.4.0',
                'Content-Type'           => 'application/json',
                'Connection'             => 'close',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message'      => 'Unreachable: ' . $response->get_error_message(),
                'debug_status' => 'WP_REMOTE_ERROR',
                'debug_body'   => $response->get_error_message(),
            ]);
            return;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        error_log('[VGT GORGON PING] HTTP=' . $status . ' | Target=' . $url . ' | Body: ' . substr($body, 0, 100));

        if ($status >= 200 && $status < 300) {
            $decoded = json_decode($body, true);
            wp_send_json_success([
                'message'            => 'NEXUS TELEMETRY ONLINE',
                'genesis_completed'  => is_array($decoded) ? ($decoded['genesis_completed']  ?? false) : false,
                'is_groq_configured' => is_array($decoded) ? ($decoded['is_groq_configured'] ?? false) : false,
                'active_nodes'       => is_array($decoded) ? ($decoded['active_nodes_count'] ?? 0) : 0,
            ]);
        } else {
            wp_send_json_error([
                'message'      => 'Unrecognized',
                'debug_status' => $status,
                'debug_url'    => $url,
                'debug_body'   => substr(strip_tags($body), 0, 300),
            ]);
        }
    }

    private static function resolve_api_key(array $config): string {
        $api_key = trim((string)($config['gorgon_api_key'] ?? ''));
        if ($api_key !== '' && class_exists('\\VIS_Vault')) {
            return trim(\VIS_Vault::decrypt($api_key));
        }
        return $api_key;
    }

    private static function get_node_id(): string {
        $salt = defined('AUTH_SALT') ? AUTH_SALT : (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'vgt_omega_fallback_salt');
        return hash_hmac('sha384', get_site_url(), $salt);
    }

    public static function handle_sync(): void {
        self::verify_privileges();
        if (class_exists('\\VisionGaia\\GeDefense\\Modules\\Gorgon\\Gorgon')) {
            $gorgon = \VisionGaia\GeDefense\Modules\Gorgon\Gorgon::get_instance();
            $gorgon->execute_sync_cycle(true);
            wp_send_json_success(['message' => 'Manual Sync Triggered']);
        }
        wp_send_json_error(['message' => 'Gorgon Core Offline']);
    }

    public static function handle_add_node(): void {
        self::verify_privileges();
        $id = sanitize_key($_POST['id'] ?? '');
        if (empty($id)) wp_send_json_error(['message' => 'Invalid ID']);

        $config = get_option('vis_config', []);
        if (!isset($config['gorgon_nodes'])) $config['gorgon_nodes'] = [];

        $config['gorgon_nodes'][$id] = [
            'table' => sanitize_text_field($_POST['table'] ?? ''),
            'ip_col' => sanitize_text_field($_POST['ip_col'] ?? 'ip'),
            'type_col' => sanitize_text_field($_POST['type_col'] ?? 'type'),
            'time_col' => sanitize_text_field($_POST['time_col'] ?? 'time'),
        ];

        update_option('vis_config', $config);
        wp_send_json_success();
    }

    public static function handle_remove_node(): void {
        self::verify_privileges();
        $id = sanitize_key($_POST['node_id'] ?? '');
        $config = get_option('vis_config', []);
        if (isset($config['gorgon_nodes'][$id])) {
            unset($config['gorgon_nodes'][$id]);
            update_option('vis_config', $config);
        }
        wp_send_json_success();
    }
}
