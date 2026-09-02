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
                'class' => '\\VisionGaia\\GeDefense\\Modules\\Prometheus\\Prometheus',
            ],
            'nemesis' => [
                'path'  => 'includes/modules/nemesis/class-vis-nemesis.php',
                'class' => '\\VisionGaia\\GeDefense\\Modules\\Nemesis\\Nemesis',
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
        self::emitXdr([
            'sensor' => 'AEGIS',
            'category' => 'INGRESS',
            'event_type' => 'WAF_DETECTION',
            'severity' => 8,
            'confidence' => 70,
            'score' => 70.0,
            'actor_ip' => $ip,
            'vector' => $vector,
            'action_type' => 'BLOCK',
            'outcome' => 'INTERCEPTED',
        ]);
        $config = self::config();
        if (!$config['enabled']) return;

        $prometheus = '\\VisionGaia\\GeDefense\\Modules\\Prometheus\\Prometheus';
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
        self::emitXdr([
            'sensor' => 'PROMETHEUS',
            'category' => 'BEHAVIOR',
            'event_type' => 'EVENT_HORIZON_REACHED',
            'severity' => max(7, min(10, (int)ceil($score / 25))),
            'confidence' => max(80, min(98, (int)round(80 + ($score / 50)))),
            'score' => $score,
            'actor_ip' => $ip,
            'entity_type' => $subnet === null ? 'IP' : 'SUBNET',
            'entity_id' => $subnet ?? $ip,
            'vector' => 'BEHAVIORAL_THRESHOLD',
            'action_type' => $subnet === null ? 'BAN_IP' : 'BAN_SUBNET',
            'outcome' => 'MITIGATED',
            'metadata' => ['subnet' => $subnet, 'threat_score' => $score],
        ]);
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

    /** @param array<string, mixed> $verdict */
    public static function onMalwareFinding(string $source, string $subject, array $verdict, ?string $ip): void {
        $source = strtoupper(preg_replace('/[^A-Z0-9_]/i', '_', $source) ?? 'SCANNER');
        $risk = max(0, min(100, (int)($verdict['risk'] ?? 0)));
        $confidence = max(0, min(100, (int)($verdict['confidence'] ?? 0)));
        $safeSubject = substr(preg_replace('/[^A-Za-z0-9._\/-]/', '_', $subject) ?? 'unknown', 0, 160);
        $findings = is_array($verdict['findings'] ?? null) ? $verdict['findings'] : [];
        foreach ($findings as $finding) {
            if (is_array($finding) && hash_equals('KNOWN_GHOST_TRAP', (string)($finding['code'] ?? ''))) return;
        }
        if ($source === 'INTEGRITY' && ($risk < 80 || $confidence < 75)) return;

        self::emitXdr([
            'sensor' => $source,
            'category' => 'MALWARE',
            'event_type' => 'MALWARE_FINDING',
            'role' => $source === 'INTEGRITY' ? 'CONTEXT' : 'DETECTION',
            'severity' => max(1, min(10, (int)ceil($risk / 10))),
            'confidence' => $confidence,
            'score' => $risk,
            'actor_ip' => is_string($ip) ? $ip : '',
            'user_id' => $source === 'INTEGRITY' ? 0 : get_current_user_id(),
            'session_id' => $source === 'INTEGRITY' ? 'integrity-scanner-system' : '',
            'entity_type' => 'FILE',
            'entity_id' => 'file:' . hash('sha256', $safeSubject),
            'vector' => (string)($verdict['classification'] ?? 'MALWARE_SUSPECT'),
            'action_type' => $source === 'INTEGRITY' ? 'OBSERVE' : 'QUARANTINE_REVIEW',
            'outcome' => 'CORRELATED',
            'metadata' => ['source' => $source, 'subject_hash' => hash('sha256', $safeSubject), 'risk' => $risk],
        ]);

        if ($source !== 'INTEGRITY' && class_exists('VIS_Event_Bus')) {
            VIS_Event_Bus::emit('TRINITY', 'MALWARE_CORRELATION', 'Malware finding correlated.', [
                'source' => $source,
                'subject' => $safeSubject,
                'risk' => $risk,
                'confidence' => $confidence,
            ], max(1, min(10, (int)ceil($risk / 10))));
        }

        if ($source === 'AIRLOCK' && is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) && self::config()['enabled']) {
            $prometheus = '\\VisionGaia\\GeDefense\\Modules\\Prometheus\\Prometheus';
            if (class_exists($prometheus) && method_exists($prometheus, 'get_instance')) {
                $penalty = max(0.0, min(80.0, ($risk * $confidence) / 125.0));
                $prometheus::get_instance()->increase_threat_score($ip, $penalty, 'AIRLOCK_MALWARE_FINDING');
            }
        }

        if ($source !== 'INTEGRITY' && $risk >= 90 && $confidence >= 90 && self::config()['enabled']) {
            self::engageDeception($source . ': High-confidence malware finding');
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
        $nemesis = '\\VisionGaia\\GeDefense\\Modules\\Nemesis\\Nemesis';
        if (class_exists($nemesis) && method_exists($nemesis, 'get_instance')) {
            $nemesis::get_instance()->trigger_tarpit($reason);
        }
    }

    /** @param array<string, mixed> $signal */
    private static function emitXdr(array $signal): void {
        $fabric = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
        if (class_exists($fabric)) {
            $fabric::ingest($signal);
        }
    }
}
