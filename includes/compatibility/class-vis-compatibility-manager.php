<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

/**
 * CORE: COMPATIBILITY MANAGER - OMEGA UPGRADE
 * Status: ROBUST
 * Fixes: AIOS Offline Issue durch Class-Based Auto-Discovery.
 */
class VIS_Compatibility_Manager {

    private $bridges = [
        // VisionGaia Ecosystem
        'VisionLegalPro/vision-legal-pro.php' => 'VIS_Bridge_VisionLegalPro',
        
        // Security Integration (AIOS)
        // Hinweis: Wird zusätzlich über Class-Check (Auto-Discovery) geprüft
        'all-in-one-wp-security-and-firewall/wp-security.php' => 'VIS_Bridge_AIOS',

        // Page Builders
        'elementor/elementor.php'             => 'VIS_Bridge_PageBuilders',
        'divi-builder/divi-builder.php'       => 'VIS_Bridge_PageBuilders',
        'oxygen/functions.php'                => 'VIS_Bridge_PageBuilders',
        
        // Caching
        'wp-rocket/wp-rocket.php'             => 'VIS_Bridge_Cache',
    ];

    private $loaded_bridges = [];

    public function __construct() {
        // Priority 15: Wir warten, bis alle anderen Plugins sicher geladen sind
        add_action('plugins_loaded', [$this, 'load_bridges'], 15);
    }

    public function load_bridges() {
        // 1. STANDARD CHECK: Pfad-basiert
        foreach ($this->bridges as $plugin_path => $class_name) {
            if ($this->is_plugin_active($plugin_path)) {
                $this->load_bridge_class($class_name);
            }
        }

        // 2. AUTO-DISCOVERY: Class-basiert (Fallback für AIOS)
        // Falls der Pfad abweicht, aber das Plugin läuft -> Trotzdem laden!
        if (!in_array('VIS_Bridge_AIOS', $this->loaded_bridges)) {
            if (class_exists('AIO_WP_Security') || defined('AIOWPSEC_VERSION')) {
                $this->load_bridge_class('VIS_Bridge_AIOS');
            }
        }

        // 3. BUILDER CHECK
        if ($this->is_builder_active()) {
            $this->load_bridge_class('VIS_Bridge_PageBuilders');
        }
    }

    private function load_bridge_class($class_name) {
        if (in_array($class_name, $this->loaded_bridges)) return;

        // Dateiname aus Klassenname ableiten
        // VIS_Bridge_AIOS -> class-vis-bridge-aios.php
        $slug = strtolower(str_replace('VIS_Bridge_', '', $class_name));
        
        // Mapping für komplexe Namen
        $slug = str_replace('visionlegalpro', 'vision-legal-pro', $slug);
        $slug = str_replace('pagebuilders', 'page-builders', $slug);

        $file_path = VIS_PATH . 'includes/compatibility/bridges/class-vis-bridge-' . $slug . '.php';

        if (file_exists($file_path)) {
            require_once $file_path;
            if (class_exists($class_name)) {
                // Bridge initialisieren (manche Bridges brauchen Instanziierung für Hooks)
                new $class_name();
                $this->loaded_bridges[] = $class_name;
            }
        }
    }

    private function is_plugin_active($plugin_path) {
        // Standard WP Check
        $active_plugins = (array) get_option('active_plugins', []);
        if (in_array($plugin_path, $active_plugins)) return true;

        // Multisite Check (Safe Mode)
        if (is_multisite()) {
            if (!function_exists('is_plugin_active_for_network')) {
                // Helper laden, falls noch nicht verfügbar
                require_once(ABSPATH . '/wp-admin/includes/plugin.php');
            }
            if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($plugin_path)) {
                return true;
            }
        }
        return false;
    }

    private function is_builder_active() {
        return isset($_GET['elementor-preview']) || isset($_GET['et_fb']) || isset($_GET['ct_builder']);
    }
}
