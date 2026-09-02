<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

/**
 * ============================================================================
 * GEDEFENSE ZEUS — REAL GENERATED-WAF REGRESSION HARNESS (SUPREME EDITION)
 * ============================================================================
 * Compiles the exact production zeus-waf.php and executes isolated PHP
 * subprocesses to prove real pre-boot evaluation across all invariant vectors.
 * ============================================================================
 */

$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));
if (!$isCli) {
    echo "This test suite requires CLI execution.\n";
    exit(1);
}

$repoRoot = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $repoRoot . '/');
if (!defined('VGT_ZEUS_PREBOOT')) define('VGT_ZEUS_PREBOOT', true);

// Stub WP functions for compiler
if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path(string $p): string {
        $p = str_replace('\\', '/', $p);
        return (string)preg_replace('|(?<=.)/+|', '/', $p);
    }
}
if (!function_exists('get_option')) {
    function get_option($k, $d = null) { return $d; }
}
if (!function_exists('update_option')) {
    function update_option($k, $v) { return true; }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s) { return trim(strip_tags((string)$s)); }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$s)); }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($s) { return trim(strip_tags((string)$s)); }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
}

// Temporary sandbox for test runs
$testRoot = sys_get_temp_dir() . '/vgt_zeus_live_' . bin2hex(random_bytes(8));
$vaultDir = wp_normalize_path($testRoot . '/vgt-vault/zeus/');
@mkdir($vaultDir . 'cache/', 0700, true);

// Set master key
$masterKey = bin2hex(random_bytes(32));
file_put_contents($vaultDir . 'vgt-master.php', "<?php\ndefine('VGT_MASTER_KEY', '{$masterKey}');\n");

// Require compiler and modules from current repo
require_once $repoRoot . '/includes/modules/zeus/src/class-zeus-contracts.php';
require_once $repoRoot . '/includes/modules/zeus/src/class-zeus-xdr-bridge.php';
require_once $repoRoot . '/includes/modules/zeus/src/class-zeus-policy-manager.php';
require_once $repoRoot . '/includes/modules/zeus/src/class-zeus-config-repository.php';
require_once $repoRoot . '/includes/modules/zeus/src/class-zeus-admission.php';
require_once $repoRoot . '/includes/modules/zeus/src/class-zeus-blackbox.php';
require_once $repoRoot . '/includes/modules/zeus/src/class-zeus-compiler.php';

$baseConfig = [
    'zeus_enabled'          => true,
    'security_profile'      => 'BALANCED',
    'host_lock_mode'        => 'REJECT',
    'canonical_hosts'       => ['example.com', 'www.example.com'],
    'allowed_methods'       => ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'],
    'max_query_length'      => 2048,
    'max_query_params'      => 100,
    'max_header_count'      => 50,
    'max_header_size'       => 16384,
    'max_cookie_size'       => 8192,
    'budget_enabled'        => true,
    'budget_ip_limit'       => 5,
    'budget_subnet_limit'   => 10,
    'budget_action_mode'    => 'THROTTLE',
    'login_admission_mode'  => 'DISABLED',
    'learning_mode_enabled' => false,
    'lockdown_state'        => 'NORMAL',
    'fw_basic'              => true,
    'fw_6g_blacklist'       => true,
    'fw_fake_googlebot'     => true,
    'fw_block_xmlrpc'       => true,
];

function compileTestWaf(array $config, array $xdrRoutes, string $vaultDir, string $filename = 'zeus-waf.php', ?array $customContracts = null, string $swarmIp = '127.0.0.0/24'): string {
    $compiler = new \VisionGaia\GeDefense\Modules\Zeus\VIS_Zeus_Compiler(
        $vaultDir,
        $vaultDir . $filename,
        $config,
        $swarmIp
    );
    
    $payload = $compiler->generate_payload_code();
    if (!empty($xdrRoutes)) {
        $xdrExport = var_export($xdrRoutes, true);
        $payload = preg_replace('/\$xdrContainedRoutes = .*?;/s', '$xdrContainedRoutes = ' . $xdrExport . ';', $payload);
    }
    if ($customContracts !== null) {
        $cExport = var_export($customContracts, true);
        $payload = preg_replace('/\$contracts = .*?;/s', '$contracts = ' . $cExport . ';', $payload);
    }
    
    $wafPath = $vaultDir . $filename;
    file_put_contents($wafPath, $payload);
    return $wafPath;
}

function executeLiveWafRequest(
    string $wafPath,
    array $serverOverrides = [],
    array $get = [],
    array $post = [],
    array $cookie = []
): array {
    $serverEnv = array_merge([
        'REMOTE_ADDR'     => '198.51.100.1',
        'HTTP_HOST'       => 'example.com',
        'REQUEST_METHOD'  => 'GET',
        'REQUEST_URI'     => '/',
        'SERVER_PROTOCOL' => 'HTTP/1.1'
    ], $serverOverrides);

    $scriptCode = '<?php '
        . '$_SERVER = ' . var_export($serverEnv, true) . ';'
        . '$_GET = ' . var_export($get, true) . ';'
        . '$_POST = ' . var_export($post, true) . ';'
        . '$_COOKIE = ' . var_export($cookie, true) . ';'
        . 'require ' . var_export($wafPath, true) . ';'
        . 'echo "___VGT_ADMITTED___";';

    $tempScript = sys_get_temp_dir() . '/waf_req_' . bin2hex(random_bytes(8)) . '.php';
    file_put_contents($tempScript, $scriptCode);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $phpBin = PHP_BINARY;
    $proc = proc_open([$phpBin, $tempScript], $descriptors, $pipes);
    if (!is_resource($proc)) {
        @unlink($tempScript);
        throw new RuntimeException("Failed to spawn PHP subprocess.");
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);
    @unlink($tempScript);

    return [
        'exitCode' => $exitCode,
        'output'   => $stdout,
        'error'    => $stderr,
        'admitted' => str_contains($stdout, '___VGT_ADMITTED___')
    ];
}

$totalTests = 0;
$testsPassed = 0;

function assertTest(string $title, bool $condition, string $detail = ''): void {
    global $totalTests, $testsPassed;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "[PASS] {$title}\n";
    } else {
        echo "[FAIL] {$title}\n";
        if ($detail !== '') {
            echo "       Detail: {$detail}\n";
        }
    }
}

echo "===============================================================\n";
echo "ZEUS GENERATED REAL-WAF SUBPROCESS REGRESSION SUITE (SUPREME)\n";
echo "===============================================================\n\n";

// 1. Master switch off
$wafOff = compileTestWaf(array_merge($baseConfig, ['zeus_enabled' => false]), [], $vaultDir, 'zeus-waf-off.php');
$res = executeLiveWafRequest($wafOff, ['HTTP_HOST' => 'evil-attacker.com']);
assertTest('P0-1: zeus_enabled=false returns immediately without blocking', $res['admitted'] === true, $res['output']);

// 2. Host Lock
$wafPath = compileTestWaf($baseConfig, [], $vaultDir, 'zeus-waf.php');
$res = executeLiveWafRequest($wafPath, ['HTTP_HOST' => 'attacker.org']);
assertTest('P0-2: Host Lock mismatch rejected with 421', str_contains($res['output'], 'HOST_LOCK_MISMATCH'), $res['output']);

$res = executeLiveWafRequest($wafPath, ['HTTP_HOST' => 'example.com']);
assertTest('P0-3: Canonical host admitted cleanly', $res['admitted'] === true, $res['output']);

// 3. Canonicalization
$res = executeLiveWafRequest($wafPath, ['REQUEST_URI' => '/index.php?param=%252fetc%252fpasswd']);
assertTest('P0-4: Double percent-encoding rejected', str_contains($res['output'], 'CANON_DOUBLE_ENCODING'), $res['output']);

$res = executeLiveWafRequest($wafPath, ['REQUEST_URI' => '/wp-login.php?user=admin%00']);
assertTest('P0-5: Null byte injection rejected', str_contains($res['output'], 'CANON_NULL_BYTE'), $res['output']);

$res = executeLiveWafRequest($wafPath, ['REQUEST_URI' => '/wp-content%2fuploads/test.txt']);
assertTest('P0-6: Encoded slash rejected', str_contains($res['output'], 'CANON_ENCODED_SLASH'), $res['output']);

$res = executeLiveWafRequest($wafPath, ['REQUEST_URI' => '/wp-includes/../wp-config.php']);
assertTest('P0-7: Dot-segment traversal rejected', str_contains($res['output'], 'CANON_DOT_SEGMENT'), $res['output']);

$res = executeLiveWafRequest($wafPath, ['REQUEST_URI' => '//wp-admin//admin.php']);
assertTest('P0-8: Duplicate path separators rejected', str_contains($res['output'], 'CANON_DUPLICATE_SLASHES'), $res['output']);

// 4. Method & Ceilings
$res = executeLiveWafRequest($wafPath, ['REQUEST_METHOD' => 'PROPFIND']);
assertTest('P0-9: Disallowed HTTP verb rejected with 405', str_contains($res['output'], 'ENV_METHOD_DISALLOWED'), $res['output']);

$res = executeLiveWafRequest($wafPath, ['REQUEST_URI' => '/search?q=' . str_repeat('A', 2500), 'QUERY_STRING' => 'q=' . str_repeat('A', 2500)]);
assertTest('P0-10: Oversized query rejected with 414', str_contains($res['output'], 'ENV_QUERY_TOO_LONG'), $res['output']);

$largeGet = array_fill(0, 150, 'test');
$res = executeLiveWafRequest($wafPath, ['REQUEST_URI' => '/search'], $largeGet);
assertTest('P0-11: Query parameter count ceiling enforced', str_contains($res['output'], 'ENV_PARAMS_EXCEEDED'), $res['output']);

$headers = [];
for ($i = 0; $i < 60; $i++) $headers["HTTP_X_CUSTOM_HEADER_{$i}"] = 'value';
$res = executeLiveWafRequest($wafPath, array_merge(['REQUEST_URI' => '/'], $headers));
assertTest('P0-12: Header count ceiling enforced with 431', str_contains($res['output'], 'ENV_HEADER_COUNT_EXCEEDED'), $res['output']);

// 5. Route Contracts
$res = executeLiveWafRequest($wafPath, [
    'REQUEST_METHOD' => 'DELETE',
    'REQUEST_URI'    => '/wp-login.php',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
]);
assertTest('P0-13: Route contract method violation rejected with 405', str_contains($res['output'], 'CONTRACT_METHOD_VIOLATION'), $res['output']);

$res = executeLiveWafRequest($wafPath, [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/wp-login.php',
    'CONTENT_LENGTH' => 100000,
    'CONTENT_TYPE'   => 'application/x-www-form-urlencoded',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
]);
assertTest('P0-14: Route contract body limit rejected with 413', str_contains($res['output'], 'CONTRACT_BODY_LIMIT'), $res['output']);

$res = executeLiveWafRequest($wafPath, [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/xmlrpc.php',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
]);
assertTest('P0-15: Disabled route terminated with 403', str_contains($res['output'], 'CONTRACT_ROUTE_DISABLED'), $res['output']);

// 6. Point 2: INCIDENT LOCKDOWN rejects invalid token (vgt_zeus_adm=lol) with 503
$wafLockdown = compileTestWaf(array_merge($baseConfig, ['lockdown_state' => 'INCIDENT_LOCKDOWN']), [], $vaultDir, 'zeus-waf-lockdown.php');
$res = executeLiveWafRequest($wafLockdown, [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
], [], [], ['vgt_zeus_adm' => 'lol']);
assertTest('P0-16: Incident lockdown rejects bogus cookie (lol) with 503', str_contains($res['output'], 'LOCKDOWN_INCIDENT_REJECT'), $res['output']);

// Valid master token admits incident lockdown
$validAllToken = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Admission::generateToken('all', 300, 'auth', $vaultDir);
$res = executeLiveWafRequest($wafLockdown, [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
], [], [], ['vgt_zeus_adm' => $validAllToken]);
assertTest('P0-17: Incident lockdown admits valid cryptographic token', $res['admitted'] === true, $res['output']);

// 7. Point 3: Surface-bound tokens (login token on admin surface is REJECTED)
$adminContract = [
    'admin_root' => [
        'path' => '/wp-admin/',
        'match_type' => 'PREFIX',
        'methods' => ['GET', 'POST'],
        'admission_required' => true,
        'status' => 'ACTIVE'
    ]
];
$wafAdm = compileTestWaf($baseConfig, [], $vaultDir, 'zeus-waf-adm.php', $adminContract);

$loginToken = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Admission::generateToken('login', 300, 'auth', $vaultDir);
$res = executeLiveWafRequest($wafAdm, [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/wp-admin/index.php',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
], [], [], ['vgt_zeus_adm' => $loginToken]);
assertTest('P0-18: Surface mismatch (login token on admin contract) rejected with 403', str_contains($res['output'], 'ADMISSION_TOKEN_REJECTED'), $res['output']);

$adminToken = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Admission::generateToken('admin', 300, 'auth', $vaultDir);
$res = executeLiveWafRequest($wafAdm, [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/wp-admin/index.php',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
], [], [], ['vgt_zeus_adm' => $adminToken]);
assertTest('P0-19: Surface match (admin token on admin contract) admitted cleanly', $res['admitted'] === true, $res['output']);

// Nonce single-use replay protection
$resReplay = executeLiveWafRequest($wafAdm, [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/wp-admin/index.php',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
], [], [], ['vgt_zeus_adm' => $adminToken]);
assertTest('P0-20: Replayed admission token with same nonce is rejected', str_contains($resReplay['output'], 'ADMISSION_TOKEN_REJECTED'), $resReplay['output']);

// 8. XDR Virtual Route Containment
$xdrActive = [
    '/wp-json/vulnerable-plugin/' => [
        'expires_at'  => gmdate('Y-m-d H:i:s', time() + 600),
        'incident_id' => 'inc_test_123',
        'owner'       => 'TRINITY_XDR'
    ]
];
$wafXdr = compileTestWaf($baseConfig, $xdrActive, $vaultDir, 'zeus-waf-xdr.php');
$res = executeLiveWafRequest($wafXdr, [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/wp-json/vulnerable-plugin/v1/data',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
]);
assertTest('P1-1: Active XDR route containment blocks target route with 503', str_contains($res['output'], 'XDR_ROUTE_ISOLATED'), $res['output']);

$res = executeLiveWafRequest($wafXdr, [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/wp-json/safe-plugin/v1/data',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
]);
assertTest('P1-2: Non-contained routes remain online and admitted', $res['admitted'] === true, $res['output']);

$xdrExpired = [
    '/wp-json/vulnerable-plugin/' => [
        'expires_at'  => gmdate('Y-m-d H:i:s', time() - 10),
        'incident_id' => 'inc_test_123',
        'owner'       => 'TRINITY_XDR'
    ]
];
$wafXdrExpired = compileTestWaf($baseConfig, $xdrExpired, $vaultDir, 'zeus-waf-expired.php');
$res = executeLiveWafRequest($wafXdrExpired, [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI'    => '/wp-json/vulnerable-plugin/v1/data',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
]);
assertTest('P1-3: Expired XDR route containment admits cleanly without cron (Hard TTL)', $res['admitted'] === true, $res['output']);

// 9. Point 5: Route Contract specific Query bounds & Cross Site Check
$strictContract = [
    'api_checkout' => [
        'path' => '/api/checkout',
        'match_type' => 'EXACT',
        'methods' => ['POST'],
        'max_query_length' => 10,
        'max_query_params' => 1,
        'cross_site_policy' => 'SAME_ORIGIN',
        'status' => 'ACTIVE'
    ]
];
$wafStrict = compileTestWaf($baseConfig, [], $vaultDir, 'zeus-waf-strict.php', $strictContract);

$res = executeLiveWafRequest($wafStrict, [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/api/checkout?longparam=123456789012345',
    'QUERY_STRING'   => 'longparam=123456789012345',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.5'
]);
assertTest('P0-21: Route contract specific max_query_length enforced', str_contains($res['output'], 'CONTRACT_QUERY_TOO_LONG'), $res['output']);

$res = executeLiveWafRequest($wafStrict, [
    'REQUEST_METHOD'      => 'POST',
    'REQUEST_URI'         => '/api/checkout',
    'HTTP_HOST'           => 'example.com',
    'HTTP_SEC_FETCH_SITE' => 'cross-site',
    'REMOTE_ADDR'         => '198.51.100.5'
]);
assertTest('P0-22: Route contract cross_site_policy blocks cross-site state mutation', str_contains($res['output'], 'CONTRACT_CROSS_SITE_VIOLATION'), $res['output']);

// 10. Point 5: Subnet Rate Budget & Action Mode (TEMPORARY_REJECT -> 503)
$wafSubnet = compileTestWaf(array_merge($baseConfig, [
    'budget_ip_limit' => 50,
    'budget_subnet_limit' => 3,
    'budget_action_mode' => 'TEMPORARY_REJECT'
]), [], $vaultDir, 'zeus-waf-subnet.php');

$subnetThrottled = false;
for ($i = 1; $i <= 5; $i++) {
    $res = executeLiveWafRequest($wafSubnet, [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI'    => '/test-page',
        'HTTP_HOST'      => 'example.com',
        'REMOTE_ADDR'    => "198.51.100.{$i}" // all in 198.51.100.0/24 subnet
    ]);
    if (str_contains($res['output'], 'BUDGET_SUBNET_REJECTED')) {
        $subnetThrottled = true;
        break;
    }
}
assertTest('P0-23: Subnet rate budget triggers TEMPORARY_REJECT with 503', $subnetThrottled === true, 'Subnet iteration completed');

// 11. Point 5: Learning Mode (Audit decision without killing)
$wafLearn = compileTestWaf(array_merge($baseConfig, [
    'learning_mode_enabled' => true
]), [], $vaultDir, 'zeus-waf-learn.php');

$res = executeLiveWafRequest($wafLearn, [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI'    => '/wp-login.php',
    'CONTENT_LENGTH' => 100000,
    'CONTENT_TYPE'   => 'application/x-www-form-urlencoded',
    'HTTP_HOST'      => 'example.com',
    'REMOTE_ADDR'    => '198.51.100.99'
]);
assertTest('P0-24: Learning mode audits policy infractions without terminating request', $res['admitted'] === true, $res['output']);

// 12. Point 6: Form Checkbox Uncheck Persistence Test
$sanitizedForm = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Config_Repository::sanitize([
    'vgt_zeus_form_submit' => '1',
    'security_profile'     => 'BALANCED',
    'canonical_hosts'      => ['example.com']
    // Note: zeus_enabled and budget_enabled checkboxes are intentionally absent
], ['zeus_enabled' => true, 'budget_enabled' => true]);
assertTest('P0-25: Form submission with absent checkboxes sets them to false', $sanitizedForm['zeus_enabled'] === false && $sanitizedForm['budget_enabled'] === false, 'Values: zeus=' . var_export($sanitizedForm['zeus_enabled'], true));

// 13. Point 7: Blackbox True Coalescing
$spoolFile = $vaultDir . 'blackbox.spool';
@unlink($spoolFile);
for ($i = 0; $i < 5; $i++) {
    \VisionGaia\GeDefense\Modules\Zeus\Zeus_Blackbox::record(
        'CANONICALIZATION_REJECT',
        'CANON_NULL_BYTE',
        'Null byte detected in request target.',
        9,
        '198.51.100.77',
        '/test-route',
        'GET',
        'BLOCK',
        $vaultDir
    );
}
$spoolLines = array_values(array_filter(explode("\n", (string)file_get_contents($spoolFile))));
$coalesced = false;
if (count($spoolLines) === 1) {
    $rec = json_decode($spoolLines[0], true);
    if (($rec['count'] ?? 0) === 5) {
        $coalesced = true;
    }
}
assertTest('P0-26: Blackbox true coalescing updates count in place within 300s window', $coalesced === true, 'Lines count: ' . count($spoolLines) . ', content: ' . ($spoolLines[0] ?? ''));

// 14. Release Artifact Check: No .user.ini in repo
$repoUserIni = $repoRoot . '/.user.ini';
assertTest('P0-27: Release artifact check: No .user.ini committed in repo tree', !file_exists($repoUserIni), 'File exists: ' . $repoUserIni);




// 15. CANONICALIZATION FALSE POSITIVE IMMUNITY TEST
$res_redirect = executeLiveWafRequest($wafPath, [
    'REQUEST_URI' => '/wp-login.php?redirect_to=https%3A%2F%2Fexample.com%2Fwp-admin%2F',
    'QUERY_STRING' => 'redirect_to=https%3A%2F%2Fexample.com%2Fwp-admin%2F',
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'example.com'
]);
assertTest('P0-28: Redirect query with encoded slash (%2F) admitted without false positive', $res_redirect['admitted'] === true, $res_redirect['output']);

$res_dot_query = executeLiveWafRequest($wafPath, [
    'REQUEST_URI' => '/?s=1..2',
    'QUERY_STRING' => 's=1..2',
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'example.com'
]);
assertTest('P0-29: Search query with dot-dot (1..2) admitted without false positive', $res_dot_query['admitted'] === true, $res_dot_query['output']);

// 16. ADMISSION TOKEN LIFECYCLE: ENTRY TOKEN VS MULTI-USE SESSION CAPABILITY
$sessionToken = \VisionGaia\GeDefense\Modules\Zeus\Zeus_Admission::generateToken('all', 300, 'login', $vaultDir, 'session');
$res_sess1 = executeLiveWafRequest($wafLockdown, [
    'REQUEST_URI' => '/wp-admin/',
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'example.com',
    'REMOTE_ADDR' => '203.0.113.10'
], [], [], ['vgt_zeus_adm' => $sessionToken]);
$res_sess2 = executeLiveWafRequest($wafLockdown, [
    'REQUEST_URI' => '/wp-admin/',
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'example.com',
    'REMOTE_ADDR' => '203.0.113.10'
], [], [], ['vgt_zeus_adm' => $sessionToken]);
assertTest('P0-30: Session capability token allows multiple requests until TTL without nonce replay rejection', $res_sess1['admitted'] === true && $res_sess2['admitted'] === true, $res_sess2['output']);

// 17. SUBNET CIDR WHITELIST TEST
$wafCidr = compileTestWaf(array_merge($baseConfig, ['budget_ip_limit' => 1, 'budget_action_mode' => 'THROTTLE']), [], $vaultDir, 'zeus-waf-cidr.php', null, '127.0.0.0/24');
$res_cidr_wl = executeLiveWafRequest($wafCidr, [
    'REQUEST_URI' => '/api/test',
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'example.com',
    'REMOTE_ADDR' => '127.0.0.42'
]);
$res_cidr_wl2 = executeLiveWafRequest($wafCidr, [
    'REQUEST_URI' => '/api/test',
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'example.com',
    'REMOTE_ADDR' => '127.0.0.42'
]);
assertTest('P0-31: Binary CIDR matching whitelists 127.0.0.42 in /24 subnet from rate budget throttling', $res_cidr_wl['admitted'] === true && $res_cidr_wl2['admitted'] === true, $res_cidr_wl2['output']);

// 18. STATIC FAST PATH SECURITY: /wp-json/something.js MUST NOT BYPASS ENVELOPE/CONTRACTS
$disabledRoute = [
    'xmlrpc' => [
        'path' => '/xmlrpc.php',
        'match_type' => 'EXACT',
        'methods' => ['GET', 'POST'],
        'status' => 'DISABLED'
    ]
];
$wafDisabled = compileTestWaf($baseConfig, [], $vaultDir, 'zeus-waf-dis.php', $disabledRoute);
$res_fake_static = executeLiveWafRequest($wafDisabled, [
    'REQUEST_URI' => '/xmlrpc.php',
    'REQUEST_METHOD' => 'POST',
    'HTTP_HOST' => 'example.com',
    'REMOTE_ADDR' => '198.51.100.99'
]);
assertTest('P0-32: Disabled route is terminated with 403 regardless of static fast path', str_contains($res_fake_static['output'], 'CONTRACT_ROUTE_DISABLED'), $res_fake_static['output']);

// 19. VAULT SERVER HARDENING SECURITY FILES CHECK
\VisionGaia\GeDefense\Modules\Zeus\Zeus_Vault_Resolver::ensureVaultHardening($vaultDir);
$htFile = $vaultDir . '.htaccess';
$wcFile = $vaultDir . 'web.config';
$idxFile = $vaultDir . 'index.php';
assertTest('P0-33: Vault directory hardened with .htaccess, web.config, and index.php', file_exists($htFile) && file_exists($wcFile) && file_exists($idxFile));


// Cleanup temporary sandbox
array_map('unlink', glob($vaultDir . 'cache/*') ?: []);
@rmdir($vaultDir . 'cache/');
array_map('unlink', glob($vaultDir . '*') ?: []);
@rmdir($vaultDir);
@rmdir($testRoot . '/vgt-vault/');
@rmdir($testRoot);

echo "\n===============================================================\n";
echo "ZEUS GENERATED REAL-WAF RESULTS: {$testsPassed} / {$totalTests} PASSED (" . round(($testsPassed / $totalTests) * 100) . "%)\n";
echo "===============================================================\n";

if ($testsPassed !== $totalTests) {
    exit(1);
}
