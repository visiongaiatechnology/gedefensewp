<?php
if (!defined('ABSPATH')) exit;

/**
 * BRIDGE: VISION LEGAL PRO (OMEGA REFINED)
 * Status: ACTIVE (Priority 0)
 * Logic: Aggressives Rewriting für VLP Assets im Output Stream.
 */
class VIS_Bridge_VisionLegalPro {
    private array $config = [];

    public function __construct() {
        $opt = get_option('vis_config', []);
        $this->config = is_array($opt) ? $opt : [];
        
        if (!empty($this->config['hades_enabled'])) {
            // Priority 0 = Start before VLP (which is likely default 10 or 1)
            add_action('template_redirect', [$this, 'start_buffer_wrapper'], 0);
        }
    }

    public function start_buffer_wrapper() {
        if (is_admin() || wp_doing_ajax() || defined('REST_REQUEST')) return;
        
        // Elementor Preview Check - hier keine Maskierung, damit Editor stabil bleibt
        if (isset($_GET['elementor-preview'])) return;

        ob_start([$this, 'rewrite_vlp_output']);
    }

    public function rewrite_vlp_output($buffer) {
        if (empty($buffer)) return $buffer;

        // HARDCODED PATH REPLACEMENT
        // Wir suchen explizit nach dem VLP Ordnerpfad, egal woher er kommt.
        
        // 1. Suche nach: /wp-content/uploads/vgt-shadow-net
        $target_path = '/wp-content/uploads/vgt-shadow-net';
        
        // 2. Ersetze mit: /storage/vgt-shadow-net
        // (Annahme: Hades nutzt 'storage' als Upload Alias)
        $alias = class_exists('VIS_Hades')
            ? VIS_Hades::uploads_alias($this->config)
            : 'storage';
        $new_path = '/' . $alias . '/vgt-shadow-net';

        // Replacement im normalen HTML
        $buffer = str_replace($target_path, $new_path, $buffer);

        // Replacement für Escaped Slashes (JSON/JS)
        $target_esc = str_replace('/', '\/', $target_path);
        $new_esc = str_replace('/', '\/', $new_path);
        $buffer = str_replace($target_esc, $new_esc, $buffer);

        return $buffer;
    }
}
