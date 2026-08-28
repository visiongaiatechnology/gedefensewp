<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Ghost_Trap {

    private VIS_Ghost_Trap_Config $config;
    private VIS_Ghost_Trap_Engine $engine;

    public function __construct() {
        $this->load_dependencies();
        
        $this->config = new VIS_Ghost_Trap_Config();
        $this->engine = new VIS_Ghost_Trap_Engine($this->config);

        // System Hooks (Sicherheitshalber Cleanup bei Deaktivierung)
        register_deactivation_hook(VIS_PATH . 'vision-integrity-sentinel.php', [$this->engine, 'destroy_all_traps']);
    }

    private function load_dependencies(): void {
        $dir = dirname(__FILE__) . '/src/';
        require_once $dir . 'class-ghost-trap-config.php';
        require_once $dir . 'class-ghost-trap-engine.php';
    }

    /**
     * VGT KERNEL BRIDGE: Wird vom Dashboard Settings-Mutator nach dem Speichern aufgerufen.
     */
    public static function trigger_regeneration(): void {
        if (!class_exists('VIS_Ghost_Trap_Config') || !class_exists('VIS_Ghost_Trap_Engine')) {
            $dir = dirname(__FILE__) . '/src/';
            require_once $dir . 'class-ghost-trap-config.php';
            require_once $dir . 'class-ghost-trap-engine.php';
        }
        
        $config = new VIS_Ghost_Trap_Config();
        $engine = new VIS_Ghost_Trap_Engine($config);
        $engine->redeploy_matrix();
    }
}
