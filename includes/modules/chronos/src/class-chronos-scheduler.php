<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

final class VIS_Chronos_Scheduler {

    public const EVENT_MAIN = 'vis_periodic_scan_event';
    public const EVENT_STEP = 'vis_scan_step_event';

    private VIS_Chronos_Config $config;
    private VIS_Chronos_Engine $engine;

    public function __construct(VIS_Chronos_Config $config, VIS_Chronos_Engine $engine) {
        $this->config = $config;
        $this->engine = $engine;
    }

    public function register_hooks(): void {
        add_filter('cron_schedules', [$this, 'add_custom_intervals']);
        
        add_action(self::EVENT_MAIN, [$this->engine, 'initiate_scan']);
        add_action(self::EVENT_STEP, [$this->engine, 'process_scan_slice']);

        if (is_admin() || (defined('DOING_CRON') && DOING_CRON)) {
            add_action('init', [$this, 'ensure_schedule_integrity']);
        }
    }

    public function add_custom_intervals(array $schedules): array {
        $schedules['vis_15m'] = ['interval' => 900, 'display' => 'Alle 15 Minuten (VGT Chronos)'];
        $schedules['vis_30m'] = ['interval' => 1800, 'display' => 'Alle 30 Minuten (VGT Chronos)'];
        $schedules['vis_hourly'] = ['interval' => 3600, 'display' => 'Stündlich (VGT Chronos)'];
        $schedules['vis_twicedaily'] = ['interval' => 43200, 'display' => '2x Täglich (VGT Chronos)'];
        $schedules['vis_daily'] = ['interval' => 86400, 'display' => 'Täglich (VGT Chronos)'];
        return $schedules;
    }

    public function ensure_schedule_integrity(): void {
        if (get_transient('vis_cron_integrity_checked')) return;

        $target_interval = $this->config->get_interval();
        $is_scheduled = wp_next_scheduled(self::EVENT_MAIN);

        if (!$this->config->is_active()) {
            if ($is_scheduled) wp_clear_scheduled_hook(self::EVENT_MAIN);
            return;
        }

        // Reschedule if interval changed or not existing
        if (!$is_scheduled || wp_get_schedule(self::EVENT_MAIN) !== $target_interval) {
            wp_clear_scheduled_hook(self::EVENT_MAIN);
            wp_schedule_event(time(), $target_interval, self::EVENT_MAIN);
        }
        
        set_transient('vis_cron_integrity_checked', 1, 3600);
    }
}
