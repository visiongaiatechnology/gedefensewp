<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

final class VIS_Chronos_Engine {

    private const LOCK_NAME          = 'vis_scan_active_lock';
    private const POINTER_NAME       = 'vis_scan_pointer'; 
    private const TIMEOUT_ZOMBIE     = 900;     
    private const TIME_BUDGET        = 20;      
    private const ITERATION_SAFE_MAX = 5000;    

    private VIS_Chronos_Config $config;
    private VIS_Chronos_Alerter $alerter;

    public function __construct(VIS_Chronos_Config $config, VIS_Chronos_Alerter $alerter) {
        $this->config = $config;
        $this->alerter = $alerter;
    }

    public function initiate_scan(): void {
        if (!$this->config->is_active()) return;
        $now = time();

        $current_lock = (int) get_option(self::LOCK_NAME, 0);
        if ($current_lock > 0 && ($now - $current_lock) > self::TIMEOUT_ZOMBIE) {
            delete_option(self::LOCK_NAME);
            delete_option(self::POINTER_NAME);
        }

        if (!add_option(self::LOCK_NAME, $now, '', 'no')) return;

        if (!$this->load_engine_core()) {
            error_log('VISIONGAIA CHRONOS [FATAL]: VIS_Scanner_Engine_Omega missing. Releasing Lock.');
            delete_option(self::LOCK_NAME);
            return;
        }

        try {
            // TARGET LOCKED: Omega Engine Instantiation
            $scanner = new \VIS_Scanner_Engine_Omega();
            $result = $scanner->run_scan_cycle('init', 0, 'scan');

            if (isset($result['status']) && in_array($result['status'], ['next_phase', 'processing'], true)) {
                update_option(self::POINTER_NAME, ['phase' => $result['phase'], 'offset' => $result['offset']], false);
                $this->schedule_next_step();
            } else {
                $this->finalize_process($result);
            }
        } catch (\Throwable $e) {
            error_log('VISIONGAIA CHRONOS [ERROR]: ' . $e->getMessage());
            $this->terminate_sequence();
        }
    }

    public function process_scan_slice(): void {
        if (!$this->config->is_active()) return;
        
        $this->harden_environment();
        $now = time();
        
        $lock_time = (int) get_option(self::LOCK_NAME, 0);
        if ($lock_time === 0 || ($now - $lock_time) > self::TIMEOUT_ZOMBIE) return;
        
        update_option(self::LOCK_NAME, $now, false);

        if (!$this->load_engine_core()) {
            error_log('VISIONGAIA CHRONOS [FATAL]: Worker aborted - Engine lost.');
            $this->terminate_sequence();
            return;
        }

        // TARGET LOCKED: Omega Engine Instantiation
        $scanner = new \VIS_Scanner_Engine_Omega();
        
        $pointer = get_option(self::POINTER_NAME, ['phase' => 'init', 'offset' => 0]);
        $phase   = (string) $pointer['phase'];
        $offset  = (int) $pointer['offset'];

        $start_time = microtime(true);
        $finished   = false;
        $final_result = null;
        $iterations = 0;

        try {
            while (true) {
                $iterations++;
                if ((microtime(true) - $start_time) > self::TIME_BUDGET || $iterations > self::ITERATION_SAFE_MAX) break;

                $result = $scanner->run_scan_cycle($phase, $offset, 'scan');

                if (!isset($result['status'])) throw new \RuntimeException('Invalid response from Scanner Engine Omega.');

                if ($result['status'] === 'processing') {
                    $offset = $result['offset'];
                    $phase  = $result['phase'];
                    usleep(10000); 
                } elseif ($result['status'] === 'next_phase') {
                    $phase  = $result['phase'];
                    $offset = $result['offset'];
                    break;
                } else {
                    $finished = true;
                    $final_result = $result;
                    break;
                }
            }

            if ($finished) {
                $this->finalize_process($final_result);
            } else {
                update_option(self::POINTER_NAME, ['phase' => $phase, 'offset' => $offset], false);
                $this->schedule_next_step();
            }

        } catch (\Throwable $e) {
            error_log('VISIONGAIA CHRONOS [ERROR]: Slice Execution failed - ' . $e->getMessage());
            $this->terminate_sequence();
        }
    }

    /**
     * DEPENDENCY RESOLUTION PROTOCOL (OMEGA TARGETING)
     */
    private function load_engine_core(): bool {
        if (class_exists('VIS_Scanner_Engine_Omega')) return true;

        $candidates = [];
        
        // 1. PRIMARY TARGET: Air-Gapped MU-Plugin Deployment
        if (defined('WPMU_PLUGIN_DIR')) $candidates[] = WPMU_PLUGIN_DIR . '/vgt-scanner-omega.php';
        elseif (defined('WP_CONTENT_DIR')) $candidates[] = WP_CONTENT_DIR . '/mu-plugins/vgt-scanner-omega.php';

        // 2. SECONDARY TARGET: Native Plugin Fallback
        if (defined('VIS_PATH')) $candidates[] = VIS_PATH . 'includes/scanner/class-vis-scanner-engine.php';
        $candidates[] = dirname(__DIR__, 2) . '/scanner/class-vis-scanner-engine.php';
        
        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                require_once $path;
                if (class_exists('VIS_Scanner_Engine_Omega')) return true;
            }
        }
        return false;
    }

    private function schedule_next_step(): void {
        if (!wp_next_scheduled(VIS_Chronos_Scheduler::EVENT_STEP)) {
            wp_schedule_single_event(time() + 1, VIS_Chronos_Scheduler::EVENT_STEP);
        }
    }

    private function finalize_process(?array $result): void {
        $this->terminate_sequence();
        if (!$result) return;

        if (isset($result['status']) && $result['status'] === 'warning') {
            $this->alerter->trigger_alert($result);
        }
        $this->alerter->log_internal_event($result);
    }

    private function terminate_sequence(): void {
        delete_option(self::LOCK_NAME);
        delete_option(self::POINTER_NAME);
        wp_clear_scheduled_hook(VIS_Chronos_Scheduler::EVENT_STEP);
    }

    private function harden_environment(): void {
        @ini_set('display_errors', '0');
        if (function_exists('set_time_limit')) @set_time_limit(120);
        if (function_exists('ignore_user_abort')) @ignore_user_abort(true);
        if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('admin');
    }
}
