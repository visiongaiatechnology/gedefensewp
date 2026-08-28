<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Airlock {

    private VIS_Airlock_Config $config;
    private VIS_Airlock_Scanner $scanner;
    private VIS_Airlock_Sanitizer $sanitizer;

    public function __construct() {
        $this->load_dependencies();
        
        $this->config    = new VIS_Airlock_Config();
        $this->scanner   = new VIS_Airlock_Scanner($this->config);
        $this->sanitizer = new VIS_Airlock_Sanitizer($this->config);

        if (!$this->config->is_active()) return;

        // High Priority Hooks - Defense First
        add_filter('wp_handle_upload_prefilter', [$this->scanner, 'execute_omega_scan'], 10, 1);
        add_filter('sanitize_file_name', [$this->sanitizer, 'generate_entropy_filename'], 20, 1);
    }

    private function load_dependencies(): void {
        $dir = dirname(__FILE__) . '/src/';
        require_once $dir . 'class-airlock-config.php';
        require_once $dir . 'class-airlock-scanner.php';
        require_once $dir . 'class-airlock-sanitizer.php';
    }
}
