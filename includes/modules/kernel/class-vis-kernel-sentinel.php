<?php
/**
 * VISIONGAIATECHNOLOGY SYSTEM
 * MODULE: KERNEL SENTINEL (OMEGA HARDENED)
 * ARCHITECT: OMEGA PROTOCOL
 * * UPGRADES: 
 * - DoS Protection (Max Read Limit)
 * - TOCTOU Mitigation
 * - Input Sanitization
 * - Strict chmod enforcement
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

class VIS_Kernel_Sentinel {

    private const LOCK_TRANSIENT = 'vis_kernel_check_lock';
    private const ALERT_OPTION   = 'vis_kernel_panic_mode';
    private const VAULT_DIR_NAME = 'vis-vault';
    private const SIGNAL_FILE    = 'aegis-signal.json';
    private const MAX_READ_BYTES = 524288; // 512 KiB hard ceiling.

    public function __construct() {
        // Init Watchdog bei WordPress Init
        add_action('init', [$this, 'watchdog_routine']);
    }

    /**
     * Führt die Überwachungslogik aus.
     */
    public function watchdog_routine(): void {
        // 0. Context Check
        if (defined('DOING_CRON') && DOING_CRON) return;

        // 1. Performance Gate (60s Cooldown)
        if (get_transient(self::LOCK_TRANSIENT)) return;
        set_transient(self::LOCK_TRANSIENT, 1, 60);

        // 2. Pfad-Konstruktion & Security Enforcement
        $vault_path = $this->get_vault_path();
        $file_path  = $vault_path . '/' . self::SIGNAL_FILE;

        $this->enforce_vault_security($vault_path);

        // 3. Atomic Read (TOCTOU-Safe: Kein file_exists Check vorab)
        // Wir öffnen direkt. Wenn sie nicht da ist, schlägt fopen fehl - das ist atomar sicher.
        $data = $this->safe_json_read($file_path);

        if ($data === null || !isset($data['status'])) {
            // Optional: Log nur bei wiederholtem Fehler, um Log-Flooding zu vermeiden
            return;
        }

        // 4. Entscheidungs-Logik
        // Sanitize input bevor logic check (Defensive Programming)
        $status = strtoupper(trim((string)$data['status']));

        if ($status === 'CRITICAL') {
            $this->trigger_alarm_protocol($data);
        } elseif ($status === 'SECURE') {
            $this->disarm_protocol();
        }
    }

    /**
     * Liest eine JSON Datei mit Shared Lock (LOCK_SH)
     * Hardened gegen DoS (Dateigröße) und Race Conditions.
     */
    private function safe_json_read(string $path): ?array {
        // Suppress warnings (@), wir handhaben Fehler explizit
        $handle = @fopen($path, 'rb');
        if (!$handle) return null;

        $content = null;

        // Versuche Lock zu erhalten
        // LOCK_SH blockiert, bis der Writer fertig ist.
        if (flock($handle, LOCK_SH)) {
            $filesize = @filesize($path);
            
            // OMEGA PROTOCOL: DoS Protection
            // Lese niemals mehr als MAX_READ_BYTES, egal wie groß die Datei ist.
            if ($filesize > 0) {
                $read_length = min($filesize, self::MAX_READ_BYTES);
                $content = fread($handle, $read_length);
            }
            
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        if (!$content) return null;

        // JSON_THROW_ON_ERROR ist in modernen WP Umgebungen verfügbar (PHP 7.3+)
        // Fallback catch für ältere Umgebungen
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (JsonException $e) {
            error_log('[VIS SENTINEL] Corrupt JSON in Vault: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Löst Alarm aus.
     * Sanitized inputs für E-Mail-Sicherheit.
     */
    private function trigger_alarm_protocol(array $data): void {
        if (get_option(self::ALERT_OPTION)) return;

        $to      = get_option('admin_email');
        $subject = 'ALARM KERNEL - BREACH DETECTED [URGENT]';
        
        // Host Header Injection Prevention: Nutze WP Helper oder Default
        $server_name = isset($_SERVER['SERVER_NAME']) ? sanitize_text_field($_SERVER['SERVER_NAME']) : 'UNKNOWN_HOST';
        
        // Data Sanitization (Trust No One)
        $timestamp   = sanitize_text_field($data['timestamp'] ?? date('Y-m-d H:i:s'));
        $alert_type  = sanitize_text_field($data['alert_type'] ?? 'UNKNOWN');
        $raw_data    = sanitize_text_field($data['raw'] ?? 'N/A');
        
        // Details können Text enthalten, wir strippen gefährliche Zeichen aber lassen Zeilenumbrüche
        $details     = strip_tags($data['details'] ?? 'Keine Details.');
        
        $message  = "VISIONGAIATECHNOLOGY SENTINEL\n";
        $message .= "=============================\n";
        $message .= "STATUS:      CRITICAL (KERNEL BREACH)\n";
        $message .= "HOST:        {$server_name}\n";
        $message .= "TIMESTAMP:   {$timestamp}\n";
        $message .= "TYPE:        {$alert_type}\n";
        $message .= "RAW METRICS: {$raw_data}\n";
        $message .= "=============================\n\n";
        $message .= "DETAILS:\n{$details}\n\n";
        $message .= "ACTION REQUIRED:\n";
        $message .= "1. Login: " . wp_login_url() . "\n";
        $message .= "2. Analysis: " . admin_url('admin.php?page=vgt-suite&tab=kernel') . "\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'X-Priority: 1 (Highest)',
        ];

        wp_mail($to, $subject, $message, $headers);

        update_option(self::ALERT_OPTION, true);
    }

    private function disarm_protocol(): void {
        if (get_option(self::ALERT_OPTION)) {
            delete_option(self::ALERT_OPTION);
            // Logging nur bei Statuswechsel
            error_log('[VIS SENTINEL] System Disarmed. Status: SECURE.');
        }
    }

    private function get_vault_path(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/' . self::VAULT_DIR_NAME;
    }

    private function enforce_vault_security(string $path): void {
        if (!is_dir($path)) {
            wp_mkdir_p($path);
            // HARDENING: Explizite Permissions setzen (nur Owner darf lesen/schreiben)
            @chmod($path, 0700);
        }

        $htaccess_file = $path . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $rules = "Order Deny,Allow\nDeny from all\n<Files *.json>\nOrder Deny,Allow\nDeny from all\n</Files>";
            @file_put_contents($htaccess_file, $rules);
            @chmod($htaccess_file, 0600);
        }

        $index_file = $path . '/index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, "<?php // Silence.");
            @chmod($index_file, 0600);
        }

        $web_config_file = $path . '/web.config';
        if (!file_exists($web_config_file)) {
            $web_config = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<configuration><system.webServer><authorization><deny users="*" />'
                . '</authorization><directoryBrowse enabled="false" /></system.webServer></configuration>';
            @file_put_contents($web_config_file, $web_config, LOCK_EX);
            @chmod($web_config_file, 0600);
        }
    }
}

// System Init
new VIS_Kernel_Sentinel();
