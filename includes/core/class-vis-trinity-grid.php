<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Trinity_Grid {
    private const DEFAULT_WAF_PENALTY = 50.0;

    public static function prime(array $global_config): void {
        $dependencies = [
            'prometheus' => [
                'path'  => 'includes/modules/prometheus/class-vis-prometheus.php',
                'class' => '\\VisionGaia\\Integrity\\Modules\\Prometheus\\VIS_Prometheus',
            ],
            'nemesis' => [
                'path'  => 'includes/modules/nemesis/class-vis-nemesis.php',
                'class' => '\\VisionGaia\\Integrity\\Modules\\Nemesis\\VIS_Nemesis',
            ],
        ];

        foreach ($dependencies as $key => $dependency) {
            if (empty($global_config[$key . '_enabled'])) continue;
            $path = VIS_PATH . $dependency['path'];
            if (!is_readable($path)) continue;
            require_once $path;
            $class = $dependency['class'];
            if (class_exists($class) && method_exists($class, 'get_instance')) {
                $class::get_instance();
            }
        }
    }

    public static function onAegisStrike(string $ip, string $vector): void {
        $config = self::config();
        if (!$config['enabled']) return;

        $prometheus = '\\VisionGaia\\Integrity\\Modules\\Prometheus\\VIS_Prometheus';
        if (class_exists($prometheus) && method_exists($prometheus, 'get_instance')) {
            $prometheus::get_instance()->increase_threat_score(
                $ip,
                $config['waf_penalty'],
                'AEGIS_STRIKE: ' . substr($vector, 0, 96)
            );
        }

        self::engageDeception('AEGIS: Threat Lockout (' . substr($vector, 0, 96) . ')');
    }

    public static function onPrometheusMitigation(string $ip, float $score, ?string $subnet = null): void {
        $target = $subnet ?? $ip;
        if (class_exists('VIS_Cerberus')) {
            $cerberus = VIS_Cerberus::instance();
            $reason = 'PROMETHEUS_PREDICTIVE_STRIKE (Threat Score: ' . (int)$score . ')';
            $subnet === null ? $cerberus->ban_ip($target, $reason) : $cerberus->ban_subnet($target, $reason);
        }

        if (self::config()['enabled']) {
            self::engageDeception('PROMETHEUS: Threat lock (Score: ' . (int)$score . ')');
        }
    }

    /** @return array{enabled:bool,waf_penalty:float} */
    private static function config(): array {
        $raw = get_option('vis_trinity_config', []);
        if (!is_array($raw)) $raw = [];
        return [
            'enabled'     => !isset($raw['interlock_enabled']) || !empty($raw['interlock_enabled']),
            'waf_penalty' => max(0.0, min(100.0, (float)($raw['prom_waf_penalty'] ?? self::DEFAULT_WAF_PENALTY))),
        ];
    }

    private static function engageDeception(string $reason): void {
        $nemesis = '\\VisionGaia\\Integrity\\Modules\\Nemesis\\VIS_Nemesis';
        if (class_exists($nemesis) && method_exists($nemesis, 'get_instance')) {
            $nemesis::get_instance()->trigger_tarpit($reason);
        }
    }
}
