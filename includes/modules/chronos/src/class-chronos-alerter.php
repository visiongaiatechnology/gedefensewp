<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

final class VIS_Chronos_Alerter {

    private VIS_Chronos_Config $config;

    public function __construct(VIS_Chronos_Config $config) {
        $this->config = $config;
    }

    public function trigger_alert(array $result): void {
        $to = $this->config->get_email_recipient();
        $subject = $this->config->get_email_subject();
        $raw_body = $this->config->get_email_body();
        
        $count = isset($result['changes']) ? count((array) $result['changes']) : 0;
        $status = isset($result['status']) ? strtoupper($result['status']) : 'UNKNOWN';
        
        $body = str_replace(
            ['{TIMESTAMP}', '{STATUS}', '{CHANGES}'],
            [gmdate('Y-m-d H:i:s'), 'CRITICAL WARNING (' . $status . ')', (string)$count],
            $raw_body
        );

        @wp_mail($to, $subject, $body);
    }

    public function log_internal_event(array $result): void {
        try {
            if (class_exists('VIS_Event_Bus')) {
                $changes = is_array($result['changes'] ?? null) ? $result['changes'] : [];
                foreach ($changes as $change) {
                    $file = (string)($change['file'] ?? 'unknown');
                    $type = (string)($change['type'] ?? 'HASH_MISMATCH');
                    \VIS_Event_Bus::emit(
                        'CHRONOS',
                        $type,
                        sprintf('Chronos asynchronous verification confirmed: %s for %s', $type, $file),
                        [
                            'file' => $file,
                            'entity_type' => 'FILE',
                            'entity_id' => $file,
                            'role' => 'CONFIRMATION',
                            'causal_edge' => 'CONFIRMS',
                            'attribution_confidence' => 95,
                        ],
                        6
                    );
                }
            }
            if (class_exists('VIS_Kernel_Sentinel') && method_exists('VIS_Kernel_Sentinel', 'log_event')) {
                \VIS_Kernel_Sentinel::log_event('CHRONOS_AUTO', 'Status: ' . ($result['status'] ?? 'UNKNOWN'));
            } else {
                global $wpdb;
                $table = $wpdb->prefix . 'vis_omega_logs';
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                    $wpdb->insert($table, [
                        'module'   => 'CHRONOS',
                        'type'     => 'AUTO_SCAN',
                        'message'  => 'Automated Scan Lifecycle complete. State: ' . ($result['status'] ?? 'Unknown'),
                        'severity' => (isset($result['status']) && $result['status'] === 'warning') ? 3 : 1,
                        'ip'       => '127.0.0.1'
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Silent drop
        }
    }
}
