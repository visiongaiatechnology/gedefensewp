<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Prometheus {
    final class Prometheus {
        private static ?self $instance = null;
        public float $amount = 0.0;
        public string $ip = '';
        public static function get_instance(): self { return self::$instance ??= new self(); }
        public function increase_threat_score(string $ip, float $amount, string $reason): void {
            $this->ip = $ip;
            $this->amount = $amount;
        }
    }
}

namespace VisionGaia\GeDefense\Modules\Nemesis {
    final class Nemesis {
        private static ?self $instance = null;
        public int $signals = 0;
        public static function get_instance(): self { return self::$instance ??= new self(); }
        public function trigger_tarpit(string $reason): void { $this->signals++; }
    }
}

namespace {
    define('ABSPATH', __DIR__ . DIRECTORY_SEPARATOR);
    define('VIS_TRUST_PROXY', true);
    define('VIS_TRUSTED_PROXY_IPS', ['8.8.8.8']);
    $GLOBALS['vgt_trinity_test_config'] = ['interlock_enabled' => true, 'prom_waf_penalty' => 999.0];

    function get_option(string $key, mixed $default = false): mixed {
        return $key === 'vis_trinity_config' ? $GLOBALS['vgt_trinity_test_config'] : $default;
    }

    final class VIS_Cerberus {
        private static ?self $instance = null;
        public string $ip = '';
        public string $subnet = '';
        public static function instance(): self { return self::$instance ??= new self(); }
        public function ban_ip(string $ip, string $reason): void { $this->ip = $ip; }
        public function ban_subnet(string $subnet, string $reason): void { $this->subnet = $subnet; }
    }

    require dirname(__DIR__) . '/includes/core/class-vis-trinity-grid.php';
    require dirname(__DIR__) . '/includes/core/class-vis-security.php';

    $failures = [];
    if (!VIS_Security::ip_in_cidr('203.0.113.42', '203.0.113.0/24')) $failures[] = 'CIDR enforcement primitive failed.';
    if (VIS_Security::network_cidr('2001:db8::1234') !== '2001:db8::/64') $failures[] = 'IPv6 network normalization failed.';
    $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';
    if (VIS_Security::client_ip() !== '1.1.1.1') $failures[] = 'Canonical trusted-proxy identity failed.';
    VIS_Trinity_Grid::onAegisStrike('198.51.100.9', 'sqli_union');
    $prometheus = \VisionGaia\GeDefense\Modules\Prometheus\Prometheus::get_instance();
    $nemesis = \VisionGaia\GeDefense\Modules\Nemesis\Nemesis::get_instance();
    if ($prometheus->ip !== '198.51.100.9' || $prometheus->amount !== 100.0) {
        $failures[] = 'AEGIS strike was not routed through bounded Prometheus scoring.';
    }
    if ($nemesis->signals !== 1) $failures[] = 'AEGIS deception signal missing.';

    VIS_Trinity_Grid::onPrometheusMitigation('198.51.100.9', 160.0, '198.51.100.0/24');
    if (VIS_Cerberus::instance()->subnet !== '198.51.100.0/24') {
        $failures[] = 'Prometheus subnet mitigation did not reach Cerberus.';
    }
    if ($nemesis->signals !== 2) $failures[] = 'Prometheus deception signal missing.';

    $nemesis_source = file_get_contents(dirname(__DIR__) . '/includes/modules/nemesis/class-vis-nemesis.php');
    foreach (['ignore_user_abort(', 'set_time_limit( 0', 'TARPIT_CHUNK_DELAY_MICROSEC', 'Content-Length: 10000000000'] as $forbidden) {
        if (is_string($nemesis_source) && str_contains($nemesis_source, $forbidden)) {
            $failures[] = 'Unbounded Nemesis primitive remains: ' . $forbidden;
        }
    }

    if ($failures !== []) {
        fwrite(STDERR, "VGT TRINITY REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "VGT TRINITY REGRESSION: PASS\n");
}
