<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Benchmark {

    public const OPTION_HISTORY = 'vis_zeus_benchmark_history';
    public const MAX_HISTORY = 20;

    /**
     * Runs Engine Microbenchmark (Mode A): measures in-memory Zeus request evaluation throughput.
     * Strictly local in-memory simulation without external network calls.
     *
     * @param string $profileName
     * @param int $iterations
     * @return array<string, mixed>
     */
    public static function runMicrobenchmark(string $profileName = 'MIXED_BOT_SWARM', int $iterations = 5000): array {
        $config = get_option('vis_zeus_config', []);
        if (!is_array($config)) $config = [];

        $contracts = Zeus_Contracts::getDefaultContracts();
        $cases = self::generateSyntheticCases($profileName);
        $caseCount = count($cases);

        $memStart = memory_get_usage();
        $t0 = microtime(true);

        $allowedCount = 0;
        $rejectedCount = 0;
        $latencies = [];

        for ($i = 0; $i < $iterations; $i++) {
            $case = $cases[$i % $caseCount];
            $tCase0 = microtime(true);

            // 1. Envelope Evaluation
            $violation = Zeus_Envelope::evaluate($case['server'], $case['get'], $case['cookie'], $config);

            // 2. Contract Evaluation if envelope passed
            if ($violation === null) {
                $path = (string)($case['server']['REQUEST_URI'] ?? '/');
                $method = (string)($case['server']['REQUEST_METHOD'] ?? 'GET');
                $cLen = (int)($case['server']['CONTENT_LENGTH'] ?? 0);
                $cType = (string)($case['server']['CONTENT_TYPE'] ?? '');
                $qLen = strlen((string)($case['server']['QUERY_STRING'] ?? ''));
                $pCount = count($case['get']);

                $violation = Zeus_Contracts::evaluate($path, $method, $cLen, $cType, $qLen, $pCount, $contracts);
            }

            $tCase1 = microtime(true);
            if ($i < 1000) {
                $latencies[] = ($tCase1 - $tCase0) * 1000.0; // ms
            }

            if ($violation === null) {
                $allowedCount++;
            } else {
                $rejectedCount++;
            }
        }

        $t1 = microtime(true);
        $memEnd = memory_get_usage();

        $totalDurationSec = max(0.0001, $t1 - $t0);
        $evalsPerSec = (int)round($iterations / $totalDurationSec);

        sort($latencies);
        $p50 = !empty($latencies) ? $latencies[(int)(count($latencies) * 0.50)] : 0.0;
        $p95 = !empty($latencies) ? $latencies[(int)(count($latencies) * 0.95)] : 0.0;
        $p99 = !empty($latencies) ? $latencies[(int)(count($latencies) * 0.99)] : 0.0;

        $result = [
            'type' => 'ENGINE_MICROBENCHMARK',
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'profile' => $profileName,
            'iterations' => $iterations,
            'duration_ms' => round($totalDurationSec * 1000, 2),
            'evals_per_sec' => $evalsPerSec,
            'p50_ms' => round($p50, 4),
            'p95_ms' => round($p95, 4),
            'p99_ms' => round($p99, 4),
            'memory_delta_kb' => round(max(0, $memEnd - $memStart) / 1024, 2),
            'allowed_count' => $allowedCount,
            'rejected_count' => $rejectedCount,
            'drop_rate_pct' => round(($rejectedCount / max(1, $iterations)) * 100, 1),
            'fingerprint' => self::getHardwareFingerprint(),
        ];

        self::saveRunHistory($result);
        return $result;
    }

    /**
     * Executes Deterministic Security Self-Test verifying 9 core invariant capabilities.
     *
     * @param string $vaultDir
     * @return array<string, mixed>
     */
    public static function runSecuritySelfTest(string $vaultDir): array {
        $config = [
            'allowed_methods' => ['GET', 'POST'],
            'host_lock_mode' => 'REJECT',
            'canonical_hosts' => ['example.test'],
            'max_query_length' => 1024,
            'max_query_params' => 20
        ];
        $contracts = Zeus_Contracts::getDefaultContracts();

        $tests = [];

        // 1. Invalid HTTP Method
        $t1 = Zeus_Envelope::evaluate(['REQUEST_METHOD' => 'TRACK', 'REQUEST_URI' => '/', 'HTTP_HOST' => 'example.test'], [], [], $config);
        $tests['invalid_method'] = [
            'title' => 'Invalid HTTP Verb Rejection (TRACK)',
            'pass' => ($t1 !== null && ($t1['status_code'] ?? 0) === 405),
            'rule_id' => $t1['rule_id'] ?? 'NONE'
        ];

        // 2. Host Lock Mismatch
        $t2 = Zeus_Envelope::evaluate(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_HOST' => 'evil-attacker.com'], [], [], $config);
        $tests['host_lock'] = [
            'title' => 'Host Lock Mismatch Rejection',
            'pass' => ($t2 !== null && ($t2['status_code'] ?? 0) === 421),
            'rule_id' => $t2['rule_id'] ?? 'NONE'
        ];

        // 3. Double Percent-Encoding
        $t3 = Zeus_Envelope::evaluate(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/%252e%252e/wp-config.php', 'HTTP_HOST' => 'example.test'], [], [], $config);
        $tests['double_encoding'] = [
            'title' => 'Double Percent-Encoding (%252e)',
            'pass' => ($t3 !== null && ($t3['event_type'] ?? '') === 'ZEUS.CANONICALIZATION_REJECT'),
            'rule_id' => $t3['rule_id'] ?? 'NONE'
        ];

        // 4. Ambiguous Encoded Slash
        $t4 = Zeus_Envelope::evaluate(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/wp-admin%2fadmin-ajax.php', 'HTTP_HOST' => 'example.test'], [], [], $config);
        $tests['encoded_slash'] = [
            'title' => 'Ambiguous Encoded Slash (%2F)',
            'pass' => ($t4 !== null && ($t4['event_type'] ?? '') === 'ZEUS.CANONICALIZATION_REJECT'),
            'rule_id' => $t4['rule_id'] ?? 'NONE'
        ];

        // 5. Null Byte Traversal
        $t5 = Zeus_Envelope::evaluate(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test.php%00.jpg', 'HTTP_HOST' => 'example.test'], [], [], $config);
        $tests['null_byte'] = [
            'title' => 'Null Byte (%00) Traversal Block',
            'pass' => ($t5 !== null && ($t5['event_type'] ?? '') === 'ZEUS.CANONICALIZATION_REJECT'),
            'rule_id' => $t5['rule_id'] ?? 'NONE'
        ];

        // 6. Route Contract Body Ceiling
        $t6 = Zeus_Contracts::evaluate('/wp-login.php', 'POST', 131072, 'application/x-www-form-urlencoded', 0, 0, $contracts);
        $tests['contract_body_ceiling'] = [
            'title' => 'Route Contract Max Body Ceiling (128KB > 64KB)',
            'pass' => ($t6 !== null && ($t6['status_code'] ?? 0) === 413),
            'rule_id' => $t6['rule_id'] ?? 'NONE'
        ];

        // 7. XML-RPC Disabled Contract
        $t7 = Zeus_Contracts::evaluate('/xmlrpc.php', 'POST', 500, 'text/xml', 0, 0, $contracts);
        $tests['xmlrpc_disabled'] = [
            'title' => 'XML-RPC Disabled Contract Block',
            'pass' => ($t7 !== null && ($t7['status_code'] ?? 0) === 403),
            'rule_id' => $t7['rule_id'] ?? 'NONE'
        ];

        // 8. Admission Token Expiry & Replay
        $token = Zeus_Admission::generateToken('login', 300);
        $validRes = Zeus_Admission::validateToken($token, 'login', $vaultDir);
        $replayRes = Zeus_Admission::validateToken($token, 'login', $vaultDir);
        $tests['token_replay'] = [
            'title' => 'Admission Token Replay Prevention',
            'pass' => ($validRes['valid'] === true && $replayRes['valid'] === false && $replayRes['reason'] === 'TOKEN_REPLAY_DETECTED'),
            'rule_id' => 'ADMISSION_REPLAY'
        ];

        // 9. Virtual Emergency Route Containment
        Zeus_Xdr_Bridge::containRoute('/wp-json/vulnerable-plugin/v1/', 'test_incident_999', 1800);
        $isContained = Zeus_Xdr_Bridge::isRouteContained('/wp-json/vulnerable-plugin/v1/import');
        $isOtherOpen = !Zeus_Xdr_Bridge::isRouteContained('/wp-json/wp/v2/posts');
        Zeus_Xdr_Bridge::removeRouteContainment('test_incident_999');

        $tests['virtual_route_containment'] = [
            'title' => 'XDR Virtual Emergency Route Containment',
            'pass' => ($isContained && $isOtherOpen),
            'rule_id' => 'XDR_ROUTE_ISOLATION'
        ];

        $passedCount = 0;
        foreach ($tests as $t) {
            if ($t['pass']) $passedCount++;
        }

        return [
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'total_tests' => count($tests),
            'passed_count' => $passedCount,
            'all_pass' => ($passedCount === count($tests)),
            'tests' => $tests
        ];
    }

    public static function getHardwareFingerprint(): array {
        return [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'opcache_enabled' => function_exists('opcache_get_status') && !empty(opcache_get_status(false)['opcache_enabled']),
            'apcu_enabled' => function_exists('apcu_fetch') && ini_get('apc.enabled'),
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI / Local'
        ];
    }

    public static function saveRunHistory(array $runData): void {
        $history = get_option(self::OPTION_HISTORY, []);
        if (!is_array($history)) $history = [];
        array_unshift($history, $runData);
        $history = array_slice($history, 0, self::MAX_HISTORY);
        update_option(self::OPTION_HISTORY, $history, false);
    }

    public static function getRunHistory(): array {
        $history = get_option(self::OPTION_HISTORY, []);
        return is_array($history) ? $history : [];
    }

    private static function generateSyntheticCases(string $profile): array {
        $cases = [];

        if ($profile === 'MALFORMED_BOT' || $profile === 'MIXED_BOT_SWARM') {
            $cases[] = [
                'server' => ['REQUEST_METHOD' => 'TRACK', 'REQUEST_URI' => '/wp-config.php', 'HTTP_HOST' => 'example.test'],
                'get' => [],
                'cookie' => []
            ];
            $cases[] = [
                'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/%252e%252e/etc/passwd', 'HTTP_HOST' => 'example.test'],
                'get' => [],
                'cookie' => []
            ];
            $cases[] = [
                'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/wp-admin%2fadmin-ajax.php', 'HTTP_HOST' => 'example.test'],
                'get' => [],
                'cookie' => []
            ];
        }

        if ($profile === 'LOGIN_SCANNER' || $profile === 'MIXED_BOT_SWARM') {
            $cases[] = [
                'server' => ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/wp-login.php', 'HTTP_HOST' => 'example.test', 'CONTENT_LENGTH' => '100000', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
                'get' => [],
                'cookie' => []
            ];
            $cases[] = [
                'server' => ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/xmlrpc.php', 'HTTP_HOST' => 'example.test', 'CONTENT_LENGTH' => '500', 'CONTENT_TYPE' => 'text/xml'],
                'get' => [],
                'cookie' => []
            ];
        }

        // Normal visitor cases
        $cases[] = [
            'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_HOST' => 'example.test', 'QUERY_STRING' => 's=security'],
            'get' => ['s' => 'security'],
            'cookie' => []
        ];
        $cases[] = [
            'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/about-us/', 'HTTP_HOST' => 'example.test'],
            'get' => [],
            'cookie' => []
        ];
        $cases[] = [
            'server' => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/wp-json/wp/v2/posts', 'HTTP_HOST' => 'example.test'],
            'get' => [],
            'cookie' => []
        ];

        return $cases;
    }
}
