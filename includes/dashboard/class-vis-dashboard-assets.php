<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Dashboard_Assets {

    public static function enqueue(): void {
        $base_version = defined('VIS_VERSION') ? VIS_VERSION : 'omega';
        $js_file = defined('VIS_PATH') ? VIS_PATH . 'assets/js/vis-scanner-client.js' : '';
        $version = (file_exists($js_file)) ? $base_version . '.' . filemtime($js_file) : $base_version;

        wp_enqueue_style('vis-dashboard-css', VIS_URL . 'assets/css/vis-dashboard.css', [], $version);
        wp_enqueue_style('vis-security-center-css', VIS_URL . 'assets/css/vis-security-center.css', ['vis-dashboard-css'], $version);
        wp_enqueue_script('vis-dashboard-js', VIS_URL . 'assets/js/vis-dashboard.js', ['jquery'], $version, true);
        wp_enqueue_script('vis-security-center-js', VIS_URL . 'assets/js/vis-security-center.js', [], $version, true);
        
        wp_localize_script('vis-dashboard-js', 'visConfig', [
            'nonce'   => wp_create_nonce('vis_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);

        wp_enqueue_script('vis-scanner-client', VIS_URL . 'assets/js/vis-scanner-client.js', ['jquery'], $version, true);
        
        wp_localize_script('vis-scanner-client', 'vis_vars', [
            'nonce'   => wp_create_nonce('vis_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);
    }
}