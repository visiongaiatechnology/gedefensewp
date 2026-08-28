<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

/**
 * MODULE: CHRONOS (DIAMANT EDITION V6.2.0)
 * Status: OMEGA SYNCHRONIZED
 * Architecture: Event-Driven State Machine (Decoupled)
 */
final class VIS_Chronos {

    private static ?self $instance = null;

    private VIS_Chronos_Config $config;
    private VIS_Chronos_Scheduler $scheduler;
    private VIS_Chronos_Engine $engine;
    private VIS_Chronos_Alerter $alerter;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();

        $this->config    = new VIS_Chronos_Config();
        $this->alerter   = new VIS_Chronos_Alerter($this->config);
        $this->engine    = new VIS_Chronos_Engine($this->config, $this->alerter);
        $this->scheduler = new VIS_Chronos_Scheduler($this->config, $this->engine);

        $this->scheduler->register_hooks();
    }

    private function load_dependencies(): void {
        $dir = dirname(__FILE__) . '/src/';
        require_once $dir . 'class-chronos-config.php';
        require_once $dir . 'class-chronos-scheduler.php';
        require_once $dir . 'class-chronos-engine.php';
        require_once $dir . 'class-chronos-alerter.php';
    }
    
    public static function trigger_resync(): void {
        wp_clear_scheduled_hook('vis_periodic_scan_event');
        delete_transient('vis_cron_integrity_checked');
    }
}
