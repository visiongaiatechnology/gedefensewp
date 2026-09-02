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

        $pluginFile = is_file(VIS_PATH . 'gedefense-wp.php')
            ? VIS_PATH . 'gedefense-wp.php'
            : VIS_PATH . 'vision-integrity-sentinel.php';
        register_deactivation_hook($pluginFile, [$this->engine, 'destroy_all_traps']);
        $this->migrate_legacy_traps();
    }

    private function load_dependencies(): void {
        $dir = dirname(__FILE__) . '/src/';
        require_once $dir . 'class-ghost-trap-config.php';
        require_once $dir . 'class-ghost-trap-authenticator.php';
        require_once $dir . 'class-ghost-trap-engine.php';
    }

    private function migrate_legacy_traps(): void {
        if (!$this->config->is_active()) return;
        if (!$this->engine->requiresMigration()) return;
        if (get_transient('vis_ghost_trap_v2_migration_lock') !== false) return;
        set_transient('vis_ghost_trap_v2_migration_lock', '1', 300);
        try {
            $this->engine->redeploy_matrix();
            delete_transient('vis_ghost_trap_v2_migration_lock');
        } catch (ValidationException $e) {
            error_log('[GHOST TRAP VALIDATION] ' . $e->getMessage());
        } catch (SecurityException $e) {
            error_log('[GHOST TRAP SECURITY] ' . $e->getMessage());
        } catch (StorageException $e) {
            error_log('[GHOST TRAP STORAGE] ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log('[GHOST TRAP FATAL] ' . $e->getMessage());
        }
    }

    /**
     * VGT KERNEL BRIDGE: Wird vom Dashboard Settings-Mutator nach dem Speichern aufgerufen.
     */
    public static function trigger_regeneration(): void {
        if (!class_exists('VIS_Ghost_Trap_Config') || !class_exists('VIS_Ghost_Trap_Engine')) {
            $dir = dirname(__FILE__) . '/src/';
            require_once $dir . 'class-ghost-trap-config.php';
            require_once $dir . 'class-ghost-trap-authenticator.php';
            require_once $dir . 'class-ghost-trap-engine.php';
        }
        
        $config = new VIS_Ghost_Trap_Config();
        $engine = new VIS_Ghost_Trap_Engine($config);
        $engine->redeploy_matrix();
    }
}
