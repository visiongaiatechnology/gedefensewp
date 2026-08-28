<?php
if (!defined('ABSPATH')) exit;

/**
 * BRIDGE: PAGE BUILDERS & CUSTOMIZER (OMEGA REFINED)
 * Deaktiviert aggressive Sicherheitsfeatures während des Editierens/Customizers,
 * um Konflikte im iFrame-Rendering zu vermeiden.
 */
class VIS_Bridge_PageBuilders {

    public function __construct() {
        // Wir prüfen sehr früh, ob wir uns in einem Bearbeitungs-Modus befinden
        add_action('init', [$this, 'check_and_disable'], 5);
    }

    public function check_and_disable() {
        if ($this->is_editing_mode()) {
            $this->disable_interventions();
        }
    }

    private function is_editing_mode() {
        global $wp_customize;

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            return false;
        }

        // 1. WP Customizer (Wrapper & Preview)
        if (isset($wp_customize) || is_customize_preview() || strpos($_SERVER['REQUEST_URI'], 'customize.php') !== false) {
            return true;
        }
        
        // 2. Elementor Editor
        if (isset($_GET['elementor-preview']) || (isset($_POST['action']) && $_POST['action'] === 'elementor_ajax')) {
            return true;
        }
        
        // 3. Divi Builder
        if (isset($_GET['et_fb'])) return true;
        
        // 4. Oxygen Builder
        if (defined('SHOW_CT_BUILDER') && SHOW_CT_BUILDER) return true;
        
        return false;
    }

    private function disable_interventions() {
        // --- HADES BYPASS ---
        // Verhindert, dass Pfade im Customizer umgeschrieben werden (verursacht oft JS-Errors)
        add_filter('vis_hades_skip_buffer', '__return_true');
        
        // Verhindert URL-Filtering für Scripts/Styles im Edit-Modus
        remove_all_filters('style_loader_src', 999);
        remove_all_filters('script_loader_src', 999);

        // --- AEGIS BYPASS ---
        // Verhindert, dass AJAX-Speichervorgänge als XSS/SQLi geblockt werden
        add_filter('vis_aegis_skip_injection', '__return_true');

        // --- TITAN HEADER RELAXATION ---
        // Customizer braucht oft iFrame-Freigabe
        remove_action('send_headers', ['VIS_Titan', 'manage_headers']);
        
        // Header manuell setzen, die für den iFrame sicher sind
        add_action('send_headers', function() {
            if (!headers_sent()) {
                header_remove('X-Frame-Options');
                header("Content-Security-Policy: frame-ancestors 'self'");
            }
        }, 1);
    }
}
