<?php
if (!defined('ABSPATH')) exit;

/**
 * BRIDGE: ALL IN ONE SECURITY (AIOS)
 * Status: INTEGRATED
 * Logic: Liest native AIOS-Tabellen und integriert sie in den Sentinel Threat-Graph.
 */
class VIS_Bridge_AIOS {

    private static $instance = null;
    private $is_active = false;
    private $tables = [
        'lockdown' => 'aiowps_login_lockdown',
        'perm_block' => 'aiowps_permanent_block',
        'events' => 'aiowps_events',
        'audit' => 'aiowps_audit_log'
    ];

    public function __construct() {
        // Prüfung auf AIOS Konstante oder Klasse
        if (defined('AIOWPSEC_VERSION') || class_exists('AIO_WP_Security')) {
            $this->is_active = true;
        }
    }

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function is_installed() {
        return $this->is_active;
    }

    /**
     * Holt aggregierte Bedrohungsdaten aus AIOS Tabellen
     */
    public function get_threat_stats() {
        if (!$this->is_active) return 0;

        global $wpdb;
        $count = 0;

        // 1. Permanente IP Blocks
        $t_perm = $wpdb->prefix . $this->tables['perm_block'];
        if ($this->table_exists($t_perm)) {
            $count += (int)$wpdb->get_var("SELECT COUNT(*) FROM $t_perm");
        }

        // 2. Temporäre Login Lockouts
        $t_lock = $wpdb->prefix . $this->tables['lockdown'];
        if ($this->table_exists($t_lock)) {
            $count += (int)$wpdb->get_var("SELECT COUNT(*) FROM $t_lock");
        }

        // 3. 404 Events & Blocks
        $t_events = $wpdb->prefix . $this->tables['events'];
        if ($this->table_exists($t_events)) {
            $count += (int)$wpdb->get_var("SELECT COUNT(*) FROM $t_events WHERE event_type IN ('404', 'block')");
        }

        return $count;
    }

    /**
     * Prüft den Firewall Status (Basic Check)
     */
    public function get_firewall_status() {
        if (!$this->is_active) return 'OFFLINE';
        
        // Prüfen ob Firewall Config existiert
        $conf = get_option('aio_wp_security_configs');
        if (!empty($conf) && $conf['aiowps_enable_basic_firewall'] == '1') {
            return 'ACTIVE';
        }
        return 'PASSIVE';
    }

    /**
     * Helper: Table Check (Cached)
     */
    private function table_exists($table) {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }
}
