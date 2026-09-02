<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
define('ABSPATH', str_replace('\\', '/', $root) . '/');
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');

class AppException extends Exception {}
class ValidationException extends AppException {}
class SecurityException extends AppException {}
class StorageException extends AppException {}

$GLOBALS['titan_options'] = [];
$GLOBALS['titan_transients'] = [];
$GLOBALS['titan_is_admin'] = false;
$GLOBALS['titan_is_ssl'] = true;

function sanitize_key(string $value): string { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $value) ?? ''); }
function wp_json_encode(mixed $value, int $flags = 0): string|false { return json_encode($value, $flags); }
function wp_parse_url(string $url): array|false { return parse_url($url); }
function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
function wp_cache_delete(string $key, string $group = ''): bool { return true; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['titan_options'][$key] ?? $default; }
function update_option(string $key, mixed $value, bool $autoload = false): bool { $GLOBALS['titan_options'][$key] = $value; return true; }
function get_transient(string $key): mixed { return $GLOBALS['titan_transients'][$key] ?? false; }
function set_transient(string $key, mixed $value, int $ttl): bool { $GLOBALS['titan_transients'][$key] = $value; return true; }
function delete_transient(string $key): bool { unset($GLOBALS['titan_transients'][$key]); return true; }
function home_url(string $path = '/'): string { return 'https://example.test' . (str_starts_with($path, '/') ? $path : '/' . $path); }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function rest_url(string $path = ''): string { return 'https://example.test/wp-json/' . ltrim($path, '/'); }
function wp_login_url(): string { return 'https://example.test/wp-login.php'; }
function add_query_arg(string|array $key, mixed $value = null, ?string $url = null): string {
    if (is_array($key)) { $args = $key; $target = (string)$value; } else { $args = [$key => $value]; $target = (string)$url; }
    return $target . (str_contains($target, '?') ? '&' : '?') . http_build_query($args);
}
function is_admin(): bool { return (bool)$GLOBALS['titan_is_admin']; }
function wp_doing_ajax(): bool { return false; }
function is_ssl(): bool { return (bool)$GLOBALS['titan_is_ssl']; }
function get_current_user_id(): int { return 1; }

final class VIS_Key_Vault {
    public static function get_key(string $identifier): string { return (string)get_option('key:' . $identifier, ''); }
    public static function save_key(string $identifier, string $plaintext): void { update_option('key:' . $identifier, $plaintext); }
}

require $root . '/includes/modules/titan/src/class-titan-surface-resolver.php';
require $root . '/includes/modules/titan/src/class-titan-policy-compiler.php';
require $root . '/includes/modules/titan/src/class-titan-server-rules.php';
require $root . '/includes/modules/titan/src/class-titan-assurance.php';
require $root . '/includes/modules/titan/src/class-titan-policy-store.php';
require $root . '/includes/modules/titan/src/class-titan-learning.php';
require $root . '/includes/modules/titan/src/class-titan-violation-collector.php';
require $root . '/includes/modules/titan/src/class-titan-runtime.php';
require $root . '/includes/modules/titan/src/class-titan-login-gate.php';
require $root . '/includes/modules/titan/src/class-titan-sandbox.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };

$balanced = ['titan_profile' => 'balanced', 'titan_csp_mode' => 'report_only', 'titan_fetch_mode' => 'audit'];
$first = VIS_Titan_Policy_Compiler::compile($balanced, VIS_Titan_Surface_Resolver::LOGIN);
$second = VIS_Titan_Policy_Compiler::compile($balanced, VIS_Titan_Surface_Resolver::LOGIN);
$assert($first === $second, 'Policy compiler output is not deterministic.');
$assert(isset($first['headers']['Content-Security-Policy-Report-Only']), 'CSP report-only header missing.');
$assert(substr_count((string)$first['headers']['Content-Security-Policy-Report-Only'], 'default-src') === 1, 'CSP directive duplication detected.');
$assert(($first['headers']['Cross-Origin-Opener-Policy'] ?? '') === 'same-origin', 'LOGIN COOP policy missing.');
$assert(($first['headers']['Cross-Origin-Resource-Policy'] ?? '') === 'same-site', 'LOGIN CORP policy missing.');
$assert(($first['headers']['Origin-Agent-Cluster'] ?? '') === '?1', 'LOGIN OAC policy missing.');
$assert(str_contains((string)($first['headers']['Permissions-Policy'] ?? ''), 'camera=()'), 'Permissions Policy deny baseline missing.');
$assert(!isset($first['headers']['Cross-Origin-Embedder-Policy']), 'COEP enabled without experimental profile.');

$experimental = VIS_Titan_Policy_Compiler::compile(['titan_profile' => 'experimental_browser_zero_trust', 'titan_coep_mode' => 'credentialless'], VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN);
$assert(($experimental['headers']['Cross-Origin-Embedder-Policy'] ?? '') === 'credentialless', 'Experimental COEP gating failed.');

$_SERVER['REQUEST_URI'] = '/wp-login.php';
$nonceOne = new VIS_Titan_Runtime(['titan_profile' => 'strict', 'titan_csp_mode' => 'report_only', 'titan_nonce_enabled' => 1]);
$nonceTwo = new VIS_Titan_Runtime(['titan_profile' => 'strict', 'titan_csp_mode' => 'report_only', 'titan_nonce_enabled' => 1]);
$nonceProperty = new ReflectionProperty(VIS_Titan_Runtime::class, 'nonce');
$n1 = (string)$nonceProperty->getValue($nonceOne);
$n2 = (string)$nonceProperty->getValue($nonceTwo);
$assert($n1 !== '' && $n2 !== '' && !hash_equals($n1, $n2), 'Per-response nonce uniqueness failed.');

$noPreload = VIS_Titan_Policy_Compiler::compile(['titan_hsts_enabled' => 1], VIS_Titan_Surface_Resolver::LOGIN);
$assert(!str_contains((string)($noPreload['headers']['Strict-Transport-Security'] ?? ''), 'preload'), 'HSTS preload was enabled without opt-in.');
$withPreload = VIS_Titan_Policy_Compiler::compile(['titan_hsts_enabled' => 1, 'titan_hsts_include_subdomains' => 1, 'titan_hsts_preload' => 1, 'titan_hsts_max_age' => 31536000], VIS_Titan_Surface_Resolver::LOGIN);
$assert(str_contains((string)$withPreload['headers']['Strict-Transport-Security'], 'preload'), 'HSTS preload opt-in failed.');
$GLOBALS['titan_is_ssl'] = false;
$runtime = new VIS_Titan_Runtime(['titan_hsts_enabled' => 1]);
$effective = new ReflectionMethod(VIS_Titan_Runtime::class, 'effectiveHeaders');
$assert(!isset($effective->invoke($runtime)['Strict-Transport-Security']), 'HSTS emitted on HTTP.');
$GLOBALS['titan_is_ssl'] = true;

$assert(VIS_Titan_Runtime::evaluateFetchMetadata('strict', VIS_Titan_Surface_Resolver::LOGIN, 'same-origin', 'navigate', 'document', 'POST') === 'ALLOW', 'Same-origin Fetch Metadata request blocked.');
$assert(VIS_Titan_Runtime::evaluateFetchMetadata('strict', VIS_Titan_Surface_Resolver::LOGIN, 'cross-site', 'navigate', 'document', 'POST') === 'DENY', 'Cross-site sensitive mutation not blocked.');
$assert(VIS_Titan_Runtime::evaluateFetchMetadata('strict', VIS_Titan_Surface_Resolver::REST, '', '', '', 'POST') === 'ALLOW', 'REST machine client without Fetch Metadata blocked.');
$assert(VIS_Titan_Runtime::evaluateFetchMetadata('strict', VIS_Titan_Surface_Resolver::WEBHOOK, 'cross-site', 'cors', 'empty', 'POST') === 'ALLOW', 'Webhook compatibility failed.');

$_SERVER['REQUEST_URI'] = '/wp-login.php';
$assert(VIS_Titan_Surface_Resolver::resolve() === VIS_Titan_Surface_Resolver::LOGIN, 'LOGIN surface classification failed.');
$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
$assert(VIS_Titan_Surface_Resolver::resolve() === VIS_Titan_Surface_Resolver::REST, 'REST surface classification failed.');
$_SERVER['REQUEST_URI'] = '/?vis_titan_preview=abc';
$_GET['vis_titan_preview'] = 'abc';
$assert(VIS_Titan_Surface_Resolver::resolve() === VIS_Titan_Surface_Resolver::ACTIVE_CONTENT_PREVIEW, 'Sandbox surface classification failed.');
unset($_GET['vis_titan_preview']);

$rules = VIS_Titan_Server_Rules::nginxRules(['titan_xmlrpc_mode' => 'disabled', 'titan_includes_guard' => 1]);
$assert(!str_contains($rules, 'wp-content/uploads/vgt-titan-shield.conf'), 'Public Nginx path disclosure remains.');
$assert(!preg_match('~[A-Za-z]:\\\\|/(?:home|var|srv|users)/~i', $rules), 'Absolute Nginx path disclosure remains.');
$assert(!str_contains($rules, '|zip'), 'Nginx rules globally deny ZIP files.');
$assert(str_contains($rules, '^/wp-content/uploads/'), 'Uploads PHP execution denial missing.');
$attachmentRules = VIS_Titan_Server_Rules::nginxRules(['titan_active_content_direct_access' => 'attachment']);
$assert(str_contains($attachmentRules, 'Content-Disposition "attachment"'), 'Direct active content attachment policy missing.');

$sandboxHeaders = VIS_Titan_Sandbox::previewHeaders();
$assert(str_starts_with($sandboxHeaders['Content-Security-Policy'], 'sandbox;'), 'Sandbox CSP header missing.');
$assert(!str_contains($sandboxHeaders['Content-Security-Policy'], 'allow-scripts'), 'Sandbox unexpectedly permits scripts.');
$assert(($sandboxHeaders['X-Content-Type-Options'] ?? '') === 'nosniff', 'Sandbox nosniff missing.');
$GLOBALS['titan_options']['vis_config'] = ['titan_sandbox_origin_verified' => 1, 'titan_sandbox_origin' => 'https://sandbox.example.test'];
$originResolver = new ReflectionMethod(VIS_Titan_Sandbox::class, 'configuredOrigin');
$assert($originResolver->invoke(null) === 'https://sandbox.example.test', 'Dedicated sandbox origin validation failed.');

$GLOBALS['titan_options']['vis_titan_learned_origins'] = [];
$_SERVER['REQUEST_URI'] = '/';
$learningRuntime = new VIS_Titan_Runtime(['titan_learning_enabled' => 1]);
$learningRuntime->learnScriptSource('https://cdn.example.test/app.js');
$learnedRecords = array_values((array)get_option('vis_titan_learned_origins', []));
$assert(($learnedRecords[0]['type'] ?? '') === 'script', 'Learning observation is not resource-type scoped.');

$gate = new VIS_Titan_Login_Gate();
$token = $gate->issueToken();
$payload = $gate->consumeToken($token);
$assert(($payload['purpose'] ?? '') === 'wordpress-login-gate', 'Login token purpose validation failed.');
$replayRejected = false;
try { $gate->consumeToken($token); } catch (SecurityException $e) { $replayRejected = true; }
$assert($replayRejected, 'Login token replay was accepted.');
$parts = explode('.', $token, 2);
$oldPayload = ['v' => 1, 'iat' => time() - 700, 'exp' => time() - 400, 'nonce' => bin2hex(random_bytes(16)), 'purpose' => 'wordpress-login-gate'];
$encodedOld = rtrim(strtr(base64_encode(json_encode($oldPayload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
$secretMethod = new ReflectionMethod(VIS_Titan_Login_Gate::class, 'secret');
$secret = $secretMethod->invoke($gate);
$oldMac = hash_hmac('sha256', "GEDEFENSE:TITAN:GATE:v1\0" . $encodedOld, $secret, true);
$oldToken = $encodedOld . '.' . rtrim(strtr(base64_encode($oldMac), '+/', '-_'), '=');
$expiredRejected = false;
try { $gate->consumeToken($oldToken); } catch (SecurityException $e) { $expiredRejected = true; }
$assert($expiredRejected, 'Expired login token was accepted.');

$GLOBALS['titan_options']['vis_titan_policy_state'] = [
    'active' => ['policy_id' => 'new', 'version' => 2, 'compiled' => [], 'validation' => []],
    'last_known_good' => ['policy_id' => 'old', 'version' => 1, 'compiled' => [], 'validation' => []],
];
$assert(VIS_Titan_Policy_Store::rollback('REGRESSION') === true, 'Known-good policy rollback failed.');
$assert(($GLOBALS['titan_options']['vis_titan_policy_state']['active']['policy_id'] ?? '') === 'old', 'Rollback did not restore last-known-good policy.');

$settingsSource = file_get_contents($root . '/includes/dashboard/class-vis-dashboard-settings.php');
$assert(is_string($settingsSource) && str_contains($settingsSource, "'titan_server_spoof'"), 'Server spoof setting is missing from TITAN scope.');
$assert(is_string($settingsSource) && str_contains($settingsSource, "'titan_anti_enum'"), 'Anti-enumeration setting is missing from TITAN scope.');
$assert(is_string($settingsSource) && str_contains($settingsSource, "'titan_hide_version'"), 'Version hiding setting is missing from TITAN scope.');
$assert(is_string($settingsSource) && str_contains($settingsSource, '$checkboxes_to_check = array_values(array_intersect'), 'Settings disable roundtrip is not checkbox-bounded.');

$reportBodyLimit = new ReflectionClassConstant(VIS_Titan_Violation_Collector::class, 'MAX_BODY_BYTES');
$assert($reportBodyLimit->getValue() === 16384, 'CSP report body limit changed unexpectedly.');
$originMethod = new ReflectionMethod(VIS_Titan_Violation_Collector::class, 'origin');
$assert($originMethod->invoke(null, 'https://example.test/private/path?token=secret') === 'https://example.test', 'CSP report URL redaction failed.');

$loginSource = file_get_contents($root . '/includes/modules/titan/src/class-titan-login-gate.php');
$assert(is_string($loginSource) && str_contains($loginSource, "'role' => 'DETECTION'"), 'TITAN login XDR role is not DETECTION.');
$collectorSource = file_get_contents($root . '/includes/modules/titan/src/class-titan-violation-collector.php');
$assert(is_string($collectorSource) && str_contains($collectorSource, "'role' => 'CONTEXT'"), 'CSP XDR role is not CONTEXT.');
$airlockSource = file_get_contents($root . '/includes/modules/airlock/src/class-airlock-scanner.php');
$sandboxSource = file_get_contents($root . '/includes/modules/titan/src/class-titan-sandbox.php');
$assert(is_string($airlockSource) && str_contains($airlockSource, "'classification' => 'AIRLOCK_INSPECTED_UPLOAD'"), 'Airlock inspection receipt is missing.');
$assert(is_string($sandboxSource) && str_contains($sandboxSource, 'lacks an authenticated Airlock inspection receipt'), 'Sandbox accepts uninspected active content.');
$learningSource = file_get_contents($root . '/includes/modules/titan/src/class-titan-learning.php');
$recoverySource = file_get_contents($root . '/includes/modules/titan/src/class-titan-recovery.php');
$assert(is_string($learningSource) && str_contains($learningSource, "\$config['titan_csp_mode'] = 'report_only'"), 'Learning candidate is not forced through report-only validation.');
$assert(is_string($recoverySource) && str_contains($recoverySource, "WP_CLI !== true"), 'TITAN emergency recovery is not constrained to local WP-CLI.');

if ($failures !== []) {
    fwrite(STDERR, "VGT TITAN REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT TITAN REGRESSION: PASS (compiler, surfaces, browser policy, fetch, learning, sandbox, gate, rollback, recovery, server rules, XDR roles)\n");
