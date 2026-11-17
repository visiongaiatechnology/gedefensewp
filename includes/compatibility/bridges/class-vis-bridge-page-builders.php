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
        if (isset($wp_customize) || (function_exists('is_customize_preview') && is_customize_preview())) {
            return true;
        }

        // 2. Elementor Editor (nur wenn Elementor wirklich geladen & aktiv)
        if (did_action('elementor/loaded') > 0
            && (isset($_GET['elementor-preview']) || (isset($_POST['action']) && $_POST['action'] === 'elementor_ajax'))) {
            return true;
        }

        // 3. Divi Builder (nur wenn Divi aktiv)
        if (defined('ET_BUILDER_VERSION') && isset($_GET['et_fb'])) {
            return true;
        }

        // 4. Oxygen Builder
        if (defined('SHOW_CT_BUILDER') && SHOW_CT_BUILDER) {
            return true;
        }

        return false;
    }

    private function disable_interventions() {
        // --- HADES URL COMPATIBILITY ---
        // Verhindert nur Script/Style-Asset-Rewrites im verifizierten Editor-iFrame;
        // Titan Security-Header (CSP/nosniff/Referrer) und AEGIS WAF bleiben aktiv (FIND-002).
        remove_all_filters('style_loader_src', 999);
        remove_all_filters('script_loader_src', 999);

        global $wp_customize;
        $isCustomizer = isset($wp_customize) || (function_exists('is_customize_preview') && is_customize_preview());
        if ($isCustomizer) {
            add_action('send_headers', function() {
                if (!headers_sent()) {
                    header_remove('X-Frame-Options');
                    header("Content-Security-Policy: frame-ancestors 'self'");
                }
            }, 99);
        }
    }
}
