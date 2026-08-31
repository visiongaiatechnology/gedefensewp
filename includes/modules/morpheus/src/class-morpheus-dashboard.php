<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Morpheus_Dashboard {

    private Morpheus $core;

    public function __construct( Morpheus $core ) {
        $this->core = $core;
        
        add_action( 'wp_ajax_vgt_morpheus_trigger_ai', [ $this, 'ajax_trigger_ai' ] );
        add_action( 'wp_ajax_vgt_morpheus_reject_ai', [ $this, 'ajax_reject_ai' ] );
        add_action( 'wp_ajax_vgt_morpheus_approve_ai', [ $this, 'ajax_approve_ai' ] );
        add_action( 'wp_ajax_vgt_morpheus_delete_matrix', [ $this, 'ajax_delete_matrix' ] );
        add_action( 'wp_ajax_vgt_morpheus_toggle_strict', [ $this, 'ajax_toggle_strict' ] );
    }

    public function generate_isolation_token(): string {
        if (!defined('AUTH_SALT') || strlen((string)AUTH_SALT) < 32) {
            throw new \SecurityException('Isolation token key unavailable.');
        }
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN_UA';
        return hash_hmac('sha256', $user_id . '|' . $ip . '|' . $ua, (string)AUTH_SALT);
    }

    private function verify_isolation_token(): void {
        check_ajax_referer('vgt_morpheus_action', 'nonce');
        if ( ! current_user_can('manage_options') ) wp_die('VGT Protocol: Unauthorized');
        
        $client_token = $_POST['isolation_token'] ?? '';
        if (!is_string($client_token) || strlen($client_token) !== 64) {
            throw new \SecurityException('Isolation token validation failed.');
        }
        $expected_token = $this->generate_isolation_token();
        
        if ( ! hash_equals($expected_token, $client_token) ) {
            wp_die('VGT Protocol: Network/Session Isolation Breach Detected. Request Dropped.', 'Forbidden', ['response' => 403]);
        }
    }

    public function ajax_toggle_strict(): void {
        $this->guarded(function(): void {
            $this->verify_isolation_token();
            $is_strict = filter_var($_POST['strict_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $config = get_option('vis_config', []);
            if (!is_array($config)) {
                $config = [];
            }
            $config['morpheus_strict_mode'] = $is_strict;
            if (!update_option('vis_config', $config)) {
                $stored = get_option('vis_config', []);
                if (!is_array($stored) || ($stored['morpheus_strict_mode'] ?? null) !== $is_strict) {
                    throw new \StorageException('Morpheus configuration write failed.');
                }
            }

            $this->core->enforcement_mode = $is_strict;
            Morpheus::ai_debug('System enforcement mode changed.');
            wp_send_json_success(['strict' => $is_strict]);
        });
    }

    public function ajax_trigger_ai(): void {
        $this->guarded(function(): void {
            $this->verify_isolation_token();
            $slug = Morpheus_Path_Jail::validate_slug($_POST['plugin_slug'] ?? null);
            Morpheus_Path_Jail::existing_file('audit', $slug, '.log');

            Morpheus::ai_debug("==== MANUAL AI TRIGGER INITIATED FOR [$slug] ====");
            $result = $this->core->ai->trigger_ai($slug, true);
            if ($result['success']) {
                wp_send_json_success(['message' => 'AI processing finished.']);
            }

            $incident_id = 'mor_' . bin2hex(random_bytes(8));
            error_log('[MORPHEUS AI] ' . $incident_id . ' ' . (string)$result['message']);
            wp_send_json_error([
                'message' => 'AI processing failed.',
                'incident_id' => $incident_id,
            ], 502);
        });
    }

    public function ajax_reject_ai(): void {
        $this->guarded(function(): void {
            $this->verify_isolation_token();
            $slug = Morpheus_Path_Jail::validate_slug($_POST['plugin_slug'] ?? null);
            $targets = [
                Morpheus_Path_Jail::file('proposed', $slug, '.json'),
                Morpheus_Path_Jail::file('audit', $slug, '.log.submitted'),
                Morpheus_Path_Jail::file('audit', $slug, '.log.processing'),
            ];

            foreach ($targets as $target) {
                if (is_file($target) && !unlink($target)) {
                    throw new \StorageException('Morpheus artifact deletion failed.');
                }
            }

            Morpheus::ai_debug("Vorschlag für [$slug] durch Admin abgelehnt. Audit beginnt von vorn.");
            wp_send_json_success();
        });
    }

    public function ajax_approve_ai(): void {
        $this->guarded(function(): void {
            $this->verify_isolation_token();
            $slug = Morpheus_Path_Jail::validate_slug($_POST['plugin_slug'] ?? null);
            $proposed_file = Morpheus_Path_Jail::existing_file('proposed', $slug, '.json');
            $json_content = file_get_contents($proposed_file);
            if ($json_content === false || strlen($json_content) > 262144) {
                throw new \StorageException('Proposed matrix read boundary failed.');
            }

            try {
                $new_data = json_decode($json_content, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \SecurityException('Proposed matrix validation failed.', 0, $e);
            }
            if (!is_array($new_data)) {
                throw new \SecurityException('Proposed matrix validation failed.');
            }

            $this->core->update_plugin_matrix($slug, $new_data);
            if (!unlink($proposed_file)) {
                throw new \StorageException('Proposed matrix cleanup failed.');
            }

            Morpheus::ai_debug("Erfolg! Matrix für [$slug] kompiliert.");
            wp_send_json_success();
        });
    }

    public function ajax_delete_matrix(): void {
        $this->guarded(function(): void {
            $this->verify_isolation_token();
            $slug = Morpheus_Path_Jail::validate_slug($_POST['plugin_slug'] ?? null);
            $this->core->delete_plugin_matrix($slug);
            Morpheus::ai_debug("Admin-Aktion: Erlaubnis für [$slug] aus der Matrix entfernt.");
            wp_send_json_success();
        });
    }

    private function guarded(callable $operation): void {
        try {
            $operation();
        } catch (\ValidationException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        } catch (\SecurityException $e) {
            error_log('[SEC] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Request rejected for security reasons.'], 403);
        } catch (\StorageException $e) {
            error_log('[STORAGE] ' . $e->getMessage());
            wp_send_json_error(['message' => 'A server error occurred.'], 500);
        } catch (\Throwable $e) {
            error_log('[FATAL] ' . $e->getMessage());
            wp_send_json_error(['message' => 'Critical system fault.'], 500);
        }
    }
}
