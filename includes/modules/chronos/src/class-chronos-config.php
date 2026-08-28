<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

final class VIS_Chronos_Config {

    private array $config;

    public function __construct() {
        $raw = get_option('vis_config', []);
        $this->config = is_array($raw) ? $raw : [];
    }

    public function is_active(): bool {
        return !isset($this->config['chronos_enabled']) || !empty($this->config['chronos_enabled']);
    }

    public function get_interval(): string {
        $valid = ['vis_15m', 'vis_30m', 'vis_hourly', 'vis_twicedaily', 'vis_daily'];
        $interval = $this->config['chronos_interval'] ?? 'vis_hourly';
        return in_array($interval, $valid, true) ? $interval : 'vis_hourly';
    }

    public function get_email_recipient(): string {
        $email = $this->config['chronos_email_to'] ?? '';
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : get_option('admin_email');
    }

    public function get_email_subject(): string {
        $subject = $this->config['chronos_email_subject'] ?? '';
        return !empty($subject) ? $subject : '[VGT SENTINEL] Security Alert: System Integrity Breach Detected';
    }

    public function get_email_body(): string {
        $body = $this->config['chronos_email_body'] ?? '';
        if (empty($body)) {
            $body = "VISIONGAIA SENTINEL OMEGA REPORT\n";
            $body .= "===================================\n";
            $body .= "Timestamp: {TIMESTAMP} UTC\n";
            $body .= "System Status: {STATUS}\n\n";
            $body .= "Identified Core/File Modifications: {CHANGES}\n";
            $body .= "Action Required: Access VGT Dashboard -> Scanner Module immediately.\n";
        }
        return $body;
    }
}
