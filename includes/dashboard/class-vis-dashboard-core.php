<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Dashboard_Core {
    private string $page_hook = '';

    public function __construct() {
        $this->load_dependencies();

        add_action('admin_menu', [$this, 'register_menu_matrix'], 9);
        add_action('admin_init', ['VIS_Dashboard_Settings', 'process_mutations']);
        add_action('admin_enqueue_scripts', [$this, 'inject_assets']);
        add_action('admin_notices', [$this, 'display_setup_wizard_notice']);

        // Auto-complete wizard if options already exist (system is already configured)
        if (!get_option('vgt_setup_wizard_completed')) {
            $vis_config = get_option('vis_config', []);
            if (!empty($vis_config['aegis_enabled']) || !empty($vis_config['titan_enabled']) || get_option('vis_zeus_config')) {
                update_option('vgt_setup_wizard_completed', 1);
            }
        }
        
        VIS_Dashboard_Ajax::mount_endpoints();
        VIS_Sentinel_Export::mount();

        // VGT KERNEL: Initialisierung & Boot des autonomen Scanner-Kerns
        $scanner_path = defined('VIS_PATH') ? VIS_PATH . 'includes/scanner/class-vis-scanner-engine.php' : '';
        if ($scanner_path && file_exists($scanner_path)) {
            if (!class_exists('VIS_Scanner_Engine_Omega')) {
                require_once $scanner_path;
            }
            if (class_exists('VIS_Scanner_Engine_Omega')) {
                new VIS_Scanner_Engine_Omega();
            }
        }
    }

    public function display_setup_wizard_notice(): void {
        if (!current_user_can('manage_options')) return;
        if (get_option('vgt_setup_wizard_completed')) return;
        
        if (isset($_GET['page']) && $_GET['page'] === 'vgt-suite' && isset($_GET['tab']) && $_GET['tab'] === 'setup_wizard') {
            return;
        }

        echo '<div class="notice notice-warning is-dismissible" style="border-left-color: #3b82f6; padding: 12px 20px;">';
        echo '<p style="font-size: 14px; margin: 0 0 8px 0; color: #1e293b;"><strong>' . esc_html__('Willkommen bei GeDefense WP!', 'vgt-sentinel') . '</strong> — ' . esc_html__('Bitte führen Sie den Einrichtungs-Assistenten aus, um die grundlegenden Sicherheitsvorkehrungen und das IP-Whitelisting zu konfigurieren.', 'vgt-sentinel') . '</p>';
        echo '<p style="margin: 0;"><a href="' . esc_url(admin_url('admin.php?page=vgt-suite&tab=setup_wizard')) . '" class="button button-primary" style="background: #3b82f6; border-color: #3b82f6; color: #fff; text-decoration: none;">' . esc_html__('Einrichtungs-Assistenten starten &rarr;', 'vgt-sentinel') . '</a></p>';
        echo '</div>';
    }

    private function load_dependencies(): void {
        $dir = plugin_dir_path(__FILE__);
        $dependencies = [
            'VIS_Dashboard_Assets'   => 'class-vis-dashboard-assets.php',
            'VIS_Dashboard_Settings' => 'class-vis-dashboard-settings.php',
            'VIS_Dashboard_Ajax'     => 'class-vis-dashboard-ajax.php',
            'VIS_Sentinel_Export'    => 'class-vis-sentinel-export.php'
        ];

        foreach ($dependencies as $class => $file) {
            if (!class_exists($class) && is_readable($dir . $file)) {
                require_once $dir . $file;
            }
        }
    }

    public function register_menu_matrix(): void {
        $this->page_hook = add_menu_page(
            'VGT Suite', 
            'VGT Suite', 
            'manage_options', 
            'vgt-suite', 
            '__return_empty_string', 
            VIS_SENTINEL_ICON, 
            99
        );

        add_submenu_page(
            'vgt-suite',
            'GeDefense WP',
            'GeDefense WP',
            'manage_options',
            'vgt-suite',
            [new VIS_Dashboard_View(), 'render']
        );
    }

    public function inject_assets(string $current_hook): void {
        if ($current_hook !== $this->page_hook && (empty($_GET['page']) || $_GET['page'] !== 'vgt-suite')) return;
        VIS_Dashboard_Assets::enqueue();
    }
}
