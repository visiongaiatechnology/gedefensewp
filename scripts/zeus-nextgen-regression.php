<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . DIRECTORY_SEPARATOR);
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('AUTH_SALT')) define('AUTH_SALT', 'test-salt-secret-0123456789abcdef0123456789abcdef');
if (!defined('VGT_MASTER_KEY')) define('VGT_MASTER_KEY', 'test-master-key-0123456789abcdef0123456789abcdef');

define('WP_CONTENT_DIR', sys_get_temp_dir() . '/vgt_test_zeus_' . md5($root));
@mkdir(WP_CONTENT_DIR . '/vgt-vault/zeus/cache', 0777, true);

if (!function_exists('add_action')) { function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): void {} }
if (!function_exists('add_filter')) { function add_filter(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): void {} }
if (!function_exists('did_action')) { function did_action(string $hook): int { return 1; } }
if (!function_exists('do_action')) { function do_action(string $hook, ...$args): void {} }
if (!function_exists('apply_filters')) { function apply_filters(string $hook, mixed $val, ...$args): mixed { return $val; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false { return json_encode($data, $options, $depth); } }
if (!function_exists('wp_normalize_path')) { function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); } }
if (!function_exists('wp_salt')) { function wp_salt(string $scheme = 'auth'): string { return 'test-salt-secret-0123456789abcdef'; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event(int $ts, string $r, string $h, array $a = []): bool { return true; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled(string $h, array $a = []): int|false { return false; } }
if (!function_exists('home_url')) { function home_url(string $p = ''): string { return 'https://example.test' . $p; } }

$GLOBALS['zeus_sim_options'] = [];
function get_option(string $key, mixed $default = false): mixed {
    return $GLOBALS['zeus_sim_options'][$key] ?? $default;
}
function update_option(string $key, mixed $value, mixed $autoload = null): bool {
    $GLOBALS['zeus_sim_options'][$key] = $value;
    return true;
}
function delete_option(string $key): bool {
    unset($GLOBALS['zeus_sim_options'][$key]);
    return true;
}

// Load real production classes
require_once $root . '/includes/core/class-vis-security.php';
require_once $root . '/includes/core/class-vis-event-bus.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-envelope.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-contracts.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-budget.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-blackbox.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-admission.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-xdr-bridge.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-policy-manager.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-edge.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-learning.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-benchmark.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-compiler.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-env.php';
require_once $root . '/includes/modules/zeus/src/class-zeus-shield.php';
require_once $root . '/includes/modules/zeus/class-vis-zeus.php';

use VisionGaia\GeDefense\Modules\Zeus\Zeus_Envelope;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Contracts;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Budget;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Blackbox;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Admission;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Xdr_Bridge;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Policy_Manager;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Edge;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Learning;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Benchmark;
use VisionGaia\GeDefense\Modules\Zeus\VIS_Zeus_Compiler;

$vaultDir = WP_CONTENT_DIR . '/vgt-vault/zeus/';

echo "===============================================================\n";
echo "ZEUS NEXT GENERATION COMPREHENSIVE REGRESSION SUITE\n";
echo "===============================================================\n";

$allPassed = true;

// 1. HOST LOCK & PROXY VALIDATION
echo "\n--- 1. HOST LOCK & PROXY VALIDATION ---\n";
$cfgHost = ['host_lock_mode' => 'REJECT', 'canonical_hosts' => ['example.test', 'www.example.test']];
$r1_pass = Zeus_Envelope::checkHostLock(['HTTP_HOST' => 'example.test'], $cfgHost);
$r1_fail = Zeus_Envelope::checkHostLock(['HTTP_HOST' => 'evil-attacker.com'], $cfgHost);
$r1_empty = Zeus_Envelope::checkHostLock(['HTTP_HOST' => ''], $cfgHost);

if ($r1_pass === null && ($r1_fail['status_code'] ?? 0) === 421 && ($r1_empty['status_code'] ?? 0) === 400) {
    echo "[PASS] Host Lock correctly admitted canonical host and rejected hostile/empty hosts.\n";
} else {
    echo "[FAIL] Host Lock invariant failed.\n";
    $allPassed = false;
}

// 2. CANONICALIZATION GUARD
echo "\n--- 2. CANONICALIZATION GUARD ---\n";
$c_null = Zeus_Envelope::checkCanonicalization('/file.php%00.jpg');
$c_double = Zeus_Envelope::checkCanonicalization('/%252e%252e/wp-config.php');
$c_slash = Zeus_Envelope::checkCanonicalization('/wp-admin%2fadmin-ajax.php');
$c_back = Zeus_Envelope::checkCanonicalization('/wp-admin\\test.php');
$c_dots = Zeus_Envelope::checkCanonicalization('/../wp-config.php');
$c_dup = Zeus_Envelope::checkCanonicalization('//wp-login.php');
$c_clean = Zeus_Envelope::checkCanonicalization('/wp-login.php?redirect_to=admin');

if ($c_null && $c_double && $c_slash && $c_back && $c_dots && $c_dup && $c_clean === null) {
    echo "[PASS] Canonicalization Guard rejected all 6 hostile path representations while admitting clean URI.\n";
} else {
    echo "[FAIL] Canonicalization Guard failed.\n";
    $allPassed = false;
}

// 3. REQUEST ENVELOPE FIREWALL
echo "\n--- 3. REQUEST ENVELOPE FIREWALL ---\n";
$cfgEnv = ['allowed_methods' => ['GET', 'POST'], 'max_query_length' => 500, 'max_query_params' => 10];
$e_verb = Zeus_Envelope::evaluate(['REQUEST_METHOD' => 'TRACK', 'REQUEST_URI' => '/'], [], [], $cfgEnv);
$e_query = Zeus_Envelope::evaluate(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'QUERY_STRING' => str_repeat('a', 600)], [], [], $cfgEnv);
$e_clean = Zeus_Envelope::evaluate(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'QUERY_STRING' => 's=test'], ['s' => 'test'], [], $cfgEnv);

if (($e_verb['status_code'] ?? 0) === 405 && ($e_query['status_code'] ?? 0) === 414 && $e_clean === null) {
    echo "[PASS] Request Envelope Firewall strictly enforced verbs and structural limits.\n";
} else {
    echo "[FAIL] Request Envelope Firewall failed.\n";
    $allPassed = false;
}

// 4. ROUTE CONTRACTS ENGINE
echo "\n--- 4. ROUTE CONTRACTS ENGINE ---\n";
$contracts = Zeus_Contracts::getDefaultContracts();
$r_login_oversized = Zeus_Contracts::evaluate('/wp-login.php', 'POST', 100000, 'application/x-www-form-urlencoded', 0, 0, $contracts);
$r_login_clean = Zeus_Contracts::evaluate('/wp-login.php', 'POST', 32000, 'application/x-www-form-urlencoded', 0, 0, $contracts);
$r_xmlrpc = Zeus_Contracts::evaluate('/xmlrpc.php', 'POST', 500, 'text/xml', 0, 0, $contracts);
$r_admin = Zeus_Contracts::evaluate('/wp-admin/upload.php', 'POST', 1000000, 'multipart/form-data', 0, 0, $contracts);

if (($r_login_oversized['status_code'] ?? 0) === 413 && $r_login_clean === null && ($r_xmlrpc['status_code'] ?? 0) === 403 && $r_admin === null) {
    echo "[PASS] Route Contracts enforced exact/prefix matching, body limits, and disabled route terminations.\n";
} else {
    echo "[FAIL] Route Contracts failed.\n";
    $allPassed = false;
}

// 5. REQUEST BUDGET ENGINE
echo "\n--- 5. REQUEST BUDGET ENGINE ---\n";
$cfgBudget = ['budget_enabled' => true, 'budget_ip_limit' => 5, 'budget_action_mode' => 'THROTTLE'];
$testIp = '203.0.113.88';
$b_res = null;
for ($i = 0; $i < 6; $i++) {
    $b_res = Zeus_Budget::evaluate($testIp, '/', 'GET', $cfgBudget, $vaultDir);
}
if ($b_res !== null && ($b_res['status_code'] ?? 0) === 429) {
    echo "[PASS] Request Budget Engine accurately throttled request on iteration 6 without sleep.\n";
} else {
    echo "[FAIL] Request Budget Engine failed.\n";
    $allPassed = false;
}

// 6. PRE-BOOT ADMISSION TOKENS & CLEAN URL EXCHANGE
echo "\n--- 6. PRE-BOOT ADMISSION TOKENS & REPLAY PROTECTION ---\n";
$token = Zeus_Admission::generateToken('login', 300, 'admin_auth');
$v1 = Zeus_Admission::validateToken($token, 'login', $vaultDir);
$v2_replay = Zeus_Admission::validateToken($token, 'login', $vaultDir);
$v3_wrong_surface = Zeus_Admission::validateToken($token, 'admin', $vaultDir);

if ($v1['valid'] === true && $v2_replay['valid'] === false && $v2_replay['reason'] === 'TOKEN_REPLAY_DETECTED' && $v3_wrong_surface['valid'] === false) {
    echo "[PASS] Admission token verified with HMAC, replay protection blocked 2nd attempt, and surface check validated.\n";
} else {
    echo "[FAIL] Admission tokens failed.\n";
    $allPassed = false;
}

// 7. PRE-BOOT BLACKBOX FLIGHT RECORDER & ROLLING HASH CHAIN
echo "\n--- 7. BLACKBOX FLIGHT RECORDER & HASH CHAIN ---\n";
@unlink($vaultDir . 'blackbox.spool');
Zeus_Blackbox::record('ZEUS.REQUEST_MALFORMED', 'ENV_TEST_1', 'Test reason 1', 7, '198.51.100.1', '/wp-login.php', 'POST', 'BLOCK', $vaultDir);
Zeus_Blackbox::record('ZEUS.CANONICALIZATION_REJECT', 'CANON_TEST_2', 'Test reason 2', 8, '198.51.100.2', '/%252e/test', 'GET', 'BLOCK', $vaultDir);

$metrics = Zeus_Blackbox::getMetrics($vaultDir);
$spoolLines = file($vaultDir . 'blackbox.spool', FILE_IGNORE_NEW_LINES);
$rec1 = json_decode($spoolLines[0], true);
$rec2 = json_decode($spoolLines[1], true);

// Verify rolling hash chain via canonical validator
$chainValid = Zeus_Blackbox::verifyChain($vaultDir);
$drained = Zeus_Blackbox::drainToEventBus($vaultDir);

if ($metrics['total_events'] === 2 && $chainValid && $drained === 2) {
    echo "[PASS] Blackbox recorded events with valid tamper-evident hash chain and drained to Event Bus.\n";
} else {
    echo "[FAIL] Blackbox verification failed.\n";
    $allPassed = false;
}

// 8. TWO-WAY XDR & VIRTUAL EMERGENCY ROUTE CONTAINMENT
echo "\n--- 8. TWO-WAY XDR VIRTUAL ROUTE CONTAINMENT ---\n";
Zeus_Xdr_Bridge::containRoute('/wp-json/vulnerable-plugin/v1/', 'inc_sec_999', 1800, 'resp_uuid_999');
$c_match = Zeus_Xdr_Bridge::isRouteContained('/wp-json/vulnerable-plugin/v1/import');
$c_other = Zeus_Xdr_Bridge::isRouteContained('/wp-json/other-plugin/v1/data');
Zeus_Xdr_Bridge::removeRouteContainment('inc_sec_999', 'resp_uuid_999');
$c_cleared = Zeus_Xdr_Bridge::isRouteContained('/wp-json/vulnerable-plugin/v1/import');

if ($c_match === true && $c_other === false && $c_cleared === false) {
    echo "[PASS] XDR Virtual Emergency Route Containment isolated target route prefix while keeping other routes active.\n";
} else {
    echo "[FAIL] XDR Virtual Route Containment failed.\n";
    $allPassed = false;
}

// 9. A/B POLICY DEPLOYMENT & DIGEST
echo "\n--- 9. A/B POLICY DEPLOYMENT & DIGEST ---\n";
$cfgCandidate = VIS_Zeus::getDefaultConfig();
$cfgCandidate['max_query_length'] = 1024;
$stageRes = Zeus_Policy_Manager::stageAndActivate($cfgCandidate, function(array $cfg) {
    // Simulated compiler success
    return true;
});
$activeDigest = get_option('vis_zeus_config')['policy_digest'] ?? '';

if ($stageRes['success'] && !empty($activeDigest) && $activeDigest === Zeus_Policy_Manager::computeDigest($cfgCandidate)) {
    echo "[PASS] A/B Policy Manager validated candidate, generated deterministic SHA-256 digest, and activated atomically.\n";
} else {
    echo "[FAIL] A/B Policy Manager failed.\n";
    $allPassed = false;
}

// 10. HARDENING LAB BENCHMARK & DETERMINISTIC SELF-TEST
echo "\n--- 10. HARDENING LAB BENCHMARK & SELF-TEST ---\n";
$benchRes = Zeus_Benchmark::runMicrobenchmark('MIXED_BOT_SWARM', 1000);
$selfTestRes = Zeus_Benchmark::runSecuritySelfTest($vaultDir);

if ($benchRes['evals_per_sec'] > 500 && $selfTestRes['all_pass'] === true && $selfTestRes['passed_count'] === 9) {
    echo "[PASS] Hardening Lab Microbenchmark achieved {$benchRes['evals_per_sec']} evals/sec and Self-Test scored 9/9 PASS.\n";
} else {
    echo "[FAIL] Hardening Lab failed.\n";
    $allPassed = false;
}

@unlink($repoRoot . '/.user.ini');
@unlink(ABSPATH . '.user.ini');

echo "\n===============================================================\n";
if ($allPassed) {
    echo "ZEUS NEXT GENERATION KERNEL: ALL 10 TEST SUITES VERIFIED PASS (100%)\n";
    exit(0);
} else {
    echo "ONE OR MORE ZEUS NEXT GEN TESTS FAILED\n";
    exit(1);
}
