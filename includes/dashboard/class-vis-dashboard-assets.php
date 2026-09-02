<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Dashboard_Assets {

    public static function enqueue(): void {
        $base_version = defined('VIS_VERSION') ? VIS_VERSION : 'omega';
        $js_file = defined('VIS_PATH') ? VIS_PATH . 'assets/js/vis-scanner-client.js' : '';
        $version = (file_exists($js_file)) ? $base_version . '.' . filemtime($js_file) : $base_version;

        $activePage = isset($_GET['page']) && is_string($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $activeTab = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        if ($activeTab === '') {
            $activeTab = $activePage === 'vgt-throneguard' ? 'throneguard' : ($activePage === 'vgt-loginpager' ? 'loginpager' : 'overview');
        }

        wp_enqueue_style('vis-dashboard-css', VIS_URL . 'assets/css/vis-dashboard.css', [], $version);
        wp_enqueue_style('vis-scanner-modal-css', VIS_URL . 'assets/css/vis-scanner-modal.css', ['vis-dashboard-css'], $version);
        wp_enqueue_style('vis-dashboard-sidebar', VIS_URL . 'includes/dashboard/views/sidebar/style.css', ['vis-dashboard-css'], $version);

        $viewStylePath = VIS_PATH . 'includes/dashboard/views/' . $activeTab . '/style.css';
        if (!in_array($activeTab, ['titan', 'loginpager'], true) && preg_match('/^[a-z0-9_]{1,32}$/D', $activeTab) === 1 && is_file($viewStylePath)) {
            wp_enqueue_style('vis-dashboard-view-' . $activeTab, VIS_URL . 'includes/dashboard/views/' . $activeTab . '/style.css', ['vis-dashboard-css'], $version);
        }
        if ($activeTab === 'security_center') {
            wp_enqueue_style('vis-security-center-css', VIS_URL . 'assets/css/vis-security-center.css', ['vis-dashboard-css'], $version);
            wp_enqueue_style('vis-dashboard-view-systatus', VIS_URL . 'includes/dashboard/views/systatus/style.css', ['vis-dashboard-css'], $version);
            wp_enqueue_style('vis-dashboard-view-logs', VIS_URL . 'includes/dashboard/views/logs/style.css', ['vis-dashboard-css'], $version);
        }
        if ($activeTab === 'trinity') wp_enqueue_style('vis-xdr-css', VIS_URL . 'assets/css/vis-xdr.css', ['vis-dashboard-css'], $version);
        if ($activeTab === 'titan') wp_enqueue_style('vis-titan-css', VIS_URL . 'assets/css/vis-titan.css', ['vis-dashboard-css'], $version);
        wp_enqueue_style('vis-dashboard-modern', VIS_URL . 'assets/css/vis-dashboard-modern.css', ['vis-dashboard-css'], $version);

        wp_enqueue_script('vis-dashboard-js', VIS_URL . 'assets/js/vis-dashboard.js', ['jquery'], $version, true);
        if ($activeTab === 'security_center') wp_enqueue_script('vis-security-center-js', VIS_URL . 'assets/js/vis-security-center.js', [], $version, true);
        
        wp_localize_script('vis-dashboard-js', 'visConfig', [
            'nonce'          => wp_create_nonce('vis_nonce'),
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'isolationToken' => wp_create_nonce('vgt_morpheus_isolation'),
        ]);

        wp_enqueue_script('vis-scanner-client', VIS_URL . 'assets/js/vis-scanner-client.js', ['jquery'], $version, true);
        if ($activeTab === 'loginpager' || $activePage === 'vgt-loginpager') {
            wp_enqueue_style('vis-loginpager-admin', VIS_URL . 'includes/dashboard/views/loginpager/style.css', ['vis-dashboard-css'], $version);
            wp_enqueue_script('vis-loginpager-admin', VIS_URL . 'assets/js/vis-loginpager-admin.js', [], $version, true);
        }
        if ($activeTab === 'zeus') {
            wp_enqueue_script('vis-zeus-js', VIS_URL . 'includes/dashboard/views/zeus/script.js', [], $version, true);
        }
        $viewScripts = ['cerberus', 'gorgon', 'integrity', 'kernel', 'modules', 'morpheus', 'nemesis', 'overview', 'prometheus', 'setup_wizard', 'styx', 'thread', 'throneguard', 'vlp'];
        if (in_array($activeTab, $viewScripts, true)) {
            $viewScriptPath = VIS_PATH . 'includes/dashboard/views/' . $activeTab . '/script.js';
            if (is_file($viewScriptPath)) wp_enqueue_script('vis-dashboard-view-' . $activeTab, VIS_URL . 'includes/dashboard/views/' . $activeTab . '/script.js', ['vis-dashboard-js'], $version, true);
        }
        if ($activeTab === 'aegis') {
            wp_enqueue_script('vis-oracle-diagnostics', VIS_URL . 'assets/js/vis-oracle-diagnostics.js', [], $version, true);
            wp_localize_script('vis-oracle-diagnostics', 'visOracleDiagnostics', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('vis_oracle_ping'),
            ]);
        }
        if ($activeTab === 'titan') {
            wp_enqueue_script('vis-titan-command-center', VIS_URL . 'assets/js/vis-titan-command-center.js', [], $version, true);
            wp_localize_script('vis-titan-command-center', 'visTitanCommandCenter', [
                'endpoint'  => admin_url('admin-post.php'),
                'nonce'     => wp_create_nonce('vis_titan_policy_action'),
                'gateNonce' => wp_create_nonce('vis_titan_generate_gate_link'),
            ]);
        }
        
        wp_localize_script('vis-scanner-client', 'vis_vars', [
            'nonce'   => wp_create_nonce('vis_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);
    }
}
