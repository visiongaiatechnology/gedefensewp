<?php
// STATUS: DIAMANT VGT SUPREME
// Negative & Positive Security Regression Suite for VGT Audit Fixes (FIND-001..FIND-017)
declare(strict_types=1);

$root = dirname(__DIR__);
define('ABSPATH', str_replace('\\', '/', $root) . '/');
if (!defined('VIS_PATH')) define('VIS_PATH', ABSPATH);
if (!defined('WP_CONTENT_DIR')) define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
if (!function_exists('sanitize_key')) { function sanitize_key(string $v): string { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $v) ?? ''); } }

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

$GLOBALS['__vgt_test_options'] = [];
$GLOBALS['__vgt_test_transients'] = [];
$GLOBALS['__vgt_logged_in'] = false;
$GLOBALS['__vgt_caps'] = [];

if (!function_exists('get_option')) { function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['__vgt_test_options'][$key] ?? $default; } }
if (!function_exists('update_option')) { function update_option(string $key, mixed $value, bool $autoload = false): bool { $GLOBALS['__vgt_test_options'][$key] = $value; return true; } }
if (!function_exists('delete_option')) { function delete_option(string $key): bool { unset($GLOBALS['__vgt_test_options'][$key]); return true; } }
if (!function_exists('get_transient')) { function get_transient(string $key): mixed { return $GLOBALS['__vgt_test_transients'][$key] ?? false; } }
if (!function_exists('set_transient')) { function set_transient(string $key, mixed $value, int $ttl = 0): bool { $GLOBALS['__vgt_test_transients'][$key] = $value; return true; } }
if (!function_exists('delete_transient')) { function delete_transient(string $key): bool { unset($GLOBALS['__vgt_test_transients'][$key]); return true; } }
if (!function_exists('wp_cache_get')) { function wp_cache_get(string $key, string $group = ''): false { return false; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set(string $key, mixed $value, string $group = '', int $ttl = 0): bool { return true; } }
if (!function_exists('wp_cache_delete')) { function wp_cache_delete(string $key, string $group = ''): bool { return true; } }
if (!function_exists('wp_normalize_path')) { function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); } }
if (!function_exists('wp_json_encode')) { function wp_json_encode(mixed $value, int $flags = 0): string|false { return json_encode($value, $flags); } }
if (!function_exists('wp_parse_url')) { function wp_parse_url(string $url, int $component = -1): mixed { return parse_url($url, $component); } }
if (!function_exists('home_url')) { function home_url(string $path = '/'): string { return 'https://example.test' . (str_starts_with($path, '/') ? $path : '/' . $path); } }
if (!function_exists('site_url')) { function site_url(string $path = '/'): string { return 'https://example.test' . (str_starts_with($path, '/') ? $path : '/' . $path); } }
if (!function_exists('get_site_url')) { function get_site_url(?int $blog_id = null, string $path = '/'): string { return 'https://example.test' . $path; } }
if (!function_exists('wp_salt')) { function wp_salt(string $scheme = 'auth'): string { return 'vgt-regression-deterministic-salt-value-64chars-0000000000000001'; } }
$GLOBALS['__vgt_filters'] = [];
$GLOBALS['__vgt_actions'] = [];
$GLOBALS['__vgt_removed_hooks'] = [];
if (!function_exists('apply_filters')) { function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; } }
if (!function_exists('add_action')) { function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): bool { $GLOBALS['__vgt_actions'][$hook][$priority][] = $callback; return true; } }
if (!function_exists('add_filter')) { function add_filter(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): bool { $GLOBALS['__vgt_filters'][$hook][$priority][] = $callback; return true; } }
if (!function_exists('remove_action')) { function remove_action(string $hook, mixed $callback, int $priority = 10): bool { $GLOBALS['__vgt_removed_hooks'][] = ['action', $hook, $callback, $priority]; return true; } }
if (!function_exists('remove_filter')) { function remove_filter(string $hook, mixed $callback, int $priority = 10): bool { $GLOBALS['__vgt_removed_hooks'][] = ['filter', $hook, $callback, $priority]; return true; } }
if (!function_exists('did_action')) { function did_action(string $hook): int { return 0; } }
if (!function_exists('is_admin')) { function is_admin(): bool { return false; } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in(): bool { return (bool)$GLOBALS['__vgt_logged_in']; } }
if (!function_exists('current_user_can')) { function current_user_can(string $cap): bool { return !empty($GLOBALS['__vgt_caps'][$cap]); } }
if (!function_exists('wp_get_current_user')) { function wp_get_current_user(): object { return (object)['exists' => false, 'ID' => 0]; } }
if (!function_exists('wp_parse_auth_cookie')) { function wp_parse_auth_cookie(string $cookie = '', string $scheme = ''): mixed { return false; } }
if (!function_exists('wp_hash')) { function wp_hash(string $data): string { return hash('sha256', $data); } }
if (!function_exists('is_ssl')) { function is_ssl(): bool { return true; } }
if (!function_exists('register_rest_route')) { function register_rest_route(string $ns, string $route, array $args): bool { return true; } }
if (!function_exists('current_time')) { function current_time(string $type): string { return gmdate('Y-m-d H:i:s'); } }
if (!function_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code = '', public string $message = '', public mixed $data = null) {}
    }
}
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct(public mixed $data = null, public int $status = 200) {}
    }
}

$failures = [];
$assert = static function(bool $cond, string $msg) use (&$failures): void {
    if (!$cond) $failures[] = $msg;
};

// 1. FIND-001: Unauthenticated AEGIS/Oracle URI & save_builder bypasses blocked
require_once $root . '/includes/modules/aegis/class-vis-aegis-oracle.php';
require_once $root . '/includes/modules/aegis/class-vis-aegis.php';

$_SERVER['REQUEST_URI'] = '/?page=vgt-bypass-test';
$aegis = new VIS_Aegis(['aegis_enabled' => true, 'aegis_mode' => 'strict']);
$v1 = $aegis->assess_payload('<script>alert(1)</script>', 'GET_DATA');
$assert(($v1['verdict'] ?? '') === 'BLOCK', 'FIND-001: Unauthenticated page=vgt- bypassed AEGIS XSS block.');

$v2 = VIS_Aegis_Oracle::distill('{"action":"save_builder","html":"<script>alert(1)</script>"}');
$assert(($v2['verdict'] ?? '') === 'BLOCK', 'FIND-001: save_builder safe rule overrode BLOCK distillate.');

$v3 = VIS_Aegis_Oracle::distill('<script>alert(1)</script>&page=vis-sentinel');
$assert(($v3['verdict'] ?? '') === 'BLOCK', 'FIND-001: page=vis- safe rule overrode BLOCK distillate.');

// 2. FIND-002: Compatibility bridge must not remove Titan headers or inject AEGIS/Hades skips
$bridgeSrc = file_get_contents($root . '/includes/compatibility/bridges/class-vis-bridge-page-builders.php');
$assert(is_string($bridgeSrc)
    && !str_contains($bridgeSrc, 'vis_aegis_skip_injection')
    && !str_contains($bridgeSrc, 'vis_hades_skip_buffer')
    && !str_contains($bridgeSrc, "remove_action('send_headers', ['VIS_Titan', 'manage_headers'])"),
    'FIND-002: Page-Builder bridge still strips global Titan/Hades/AEGIS defenses.');

// 3. FIND-003 & FIND-005: ThroneGuard master_exists check + unconditional superkey check
$tgSrc = file_get_contents($root . '/includes/modules/throneguard/class-vis-throne-guard.php');
$assert(is_string($tgSrc)
    && str_contains($tgSrc, 'MASTER_ESCALATION_BLOCKED')
    && !str_contains($tgSrc, "if (\$existingHash !== '' && (\$currentKey !== '' || \$newKey !== ''))"),
    'FIND-003/005: ThroneGuard escalation or empty-superkey guard missing.');

// 4. FIND-004: Key Vault blocks arbitrary option deletion and placeholder WP salts
define('AUTH_KEY', 'put your unique phrase here');
define('SECURE_AUTH_KEY', 'put your unique phrase here');
require_once $root . '/includes/modules/vault/class-vis-key-vault.php';

foreach (['active_plugins', 'vis_config', 'vis_throneguard_superkey_hash', 'vgt_sentinel_vault_system_salt', 'arbitrary_user_option'] as $opt) {
    $threw = false;
    try {
        VIS_Key_Vault::delete_key($opt);
    } catch (Throwable $e) {
        $threw = true;
    }
    $assert($threw, "FIND-004: Key Vault permitted deletion of protected/unregistered option [{$opt}].");
}

// 5. FIND-006: Styx rejects HTTP_HOST spoofing & fails closed on IPv6 [::]
require_once $root . '/includes/core/class-vis-security.php';
require_once $root . '/includes/modules/styx/class-vis-styx.php';
$_SERVER['HTTP_HOST'] = '169.254.169.254';
$styxRef = new ReflectionClass(\VisionGaia\GeDefense\Modules\Styx\Styx::class);
$styxObj = $styxRef->newInstanceWithoutConstructor();
$propEnabled = $styxRef->getProperty('is_enabled');
$propEnabled->setValue($styxObj, true);
$propInit = $styxRef->getProperty('is_initialized');
$propInit->setValue($styxObj, true);
$propExact = $styxRef->getProperty('exact_hosts');
$propExact->setValue($styxObj, []);
$propOwn = $styxRef->getMethod('own_hosts');
$ownHosts = $propOwn->invoke($styxObj);
$assert(!in_array('169.254.169.254', $ownHosts, true), 'FIND-006: Styx own_hosts() trusted spoofed HTTP_HOST.');

$outRes = $styxObj->intercept_outbound(false, [], 'http://[::]/latest/meta-data/');
$assert($outRes instanceof WP_Error || is_array($outRes), 'FIND-006: Styx failed open on IPv6 [::] URL.');

// 6. FIND-007: Threat Intel rejects /0 and private CIDRs while admitting public CIDRs
require_once $root . '/includes/modules/threat-intel/class-vis-threat-intel.php';
$tiRef = new ReflectionClass(\VisionGaia\GeDefense\Modules\ThreatIntel\ThreatIntelligence::class);
$tiObj = $tiRef->newInstanceWithoutConstructor();
foreach (['0.0.0.0/0', '10.0.0.0/8', '127.0.0.1', '192.168.1.0/24', '::/0', 'fe80::/10'] as $badCidr) {
    $assert($tiObj->validate_and_sanitize_ip_or_cidr($badCidr) === null, "FIND-007: Threat Intel accepted dangerous CIDR/IP [{$badCidr}].");
}
$goodCidr = $tiObj->validate_and_sanitize_ip_or_cidr('198.51.100.0/24');
$assert(is_array($goodCidr) && $goodCidr['type'] === 'cidr_v4', 'FIND-007: Threat Intel rejected valid public /24 CIDR.');

// 7. FIND-008: Titan CSP report collector blocks forged unauthenticated report and accepts signed token
require_once $root . '/includes/modules/titan/src/class-titan-surface-resolver.php';
require_once $root . '/includes/modules/titan/src/class-titan-violation-collector.php';
$fakeReq = new class {
    public function get_header(string $h): string { return 'application/csp-report'; }
    public function get_body(): string { return json_encode(['csp-report' => ['effective-directive' => 'script-src', 'document-uri' => 'https://example.test/wp-login.php']]); }
    public function get_param(string $k): mixed { return $k === 'surface' ? 'LOGIN' : ''; }
};
$forgedResp = VIS_Titan_Violation_Collector::collect($fakeReq);
$assert($forgedResp instanceof WP_Error, 'FIND-008: Unauthenticated CSP report was accepted without signed token.');

$GLOBALS['wpdb'] = new class {
    public string $prefix = 'wp_';
    public function prepare(string $q, mixed ...$args): string { return $q; }
    public function query(string $q): int { return 1; }
    public function get_var(string $q): mixed { return 1; }
    public function get_row(string $q, mixed $out = null): mixed { return ['hits' => 1]; }
    public function suppress_errors(bool $s = true): bool { return true; }
};

$_SERVER['REMOTE_ADDR'] = '198.51.100.77';
$validToken = VIS_Titan_Violation_Collector::issueReportToken('LOGIN');
$signedReq = new class($validToken) {
    public function __construct(private string $tok) {}
    public function get_header(string $h): string { return 'application/csp-report'; }
    public function get_body(): string { return json_encode(['csp-report' => ['effective-directive' => 'script-src', 'document-uri' => 'https://example.test/wp-login.php']]); }
    public function get_param(string $k): mixed { return match($k) { 'surface' => 'LOGIN', 'token' => $this->tok, default => '' }; }
};
$validResp = VIS_Titan_Violation_Collector::collect($signedReq);
$assert($validResp instanceof WP_REST_Response && $validResp->status === 204, 'FIND-008: Valid signed CSP report was rejected.');

// 8. FIND-009: Zeus_Xdr_Bridge blocks core login/admin containment
require_once $root . '/includes/modules/zeus/src/class-zeus-xdr-bridge.php';
foreach (['/', '/wp-login.php', '/wp-admin/', '/wp-json'] as $protectedRoute) {
    $threw = false;
    try {
        \VisionGaia\GeDefense\Modules\Zeus\Zeus_Xdr_Bridge::containRoute($protectedRoute, 'inc-test');
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    $assert($threw, "FIND-009: Protected route [{$protectedRoute}] allowed into XDR auto-containment.");
}

// 9. FIND-010: Ghost Trap restricts extensions to php only
require_once $root . '/includes/modules/trap/src/class-ghost-trap-config.php';
$GLOBALS['__vgt_test_options']['vis_config'] = ['ghost_trap_exts' => 'php, sql, bak, old'];
$trapCfg = new VIS_Ghost_Trap_Config();
$assert($trapCfg->get_extensions() === ['php'], 'FIND-010: Ghost Trap permitted non-PHP webroot trap extensions.');

// 10. FIND-012: Hades stealth rejects ?admin-ajax.php query-string bypass
require_once $root . '/includes/modules/hades/class-vis-hades.php';
$hadesSrc = file_get_contents($root . '/includes/modules/hades/class-vis-hades.php');
$assert(is_string($hadesSrc) && str_contains($hadesSrc, 'parse_url($request_uri, PHP_URL_PATH)'), 'FIND-012: Hades missing URL path isolation.');

// 11. FIND-013: Morpheus nonce alignment & DB Shield does not exempt wp_insert_user
$assetsSrc = file_get_contents($root . '/includes/dashboard/class-vis-dashboard-assets.php');
$dbShieldSrc = file_get_contents($root . '/includes/modules/morpheus/src/shields/class-morpheus-shield-db.php');
$assert(is_string($assetsSrc) && str_contains($assetsSrc, "'vgt_morpheus_action'") && str_contains($assetsSrc, 'generate_isolation_token()'), 'FIND-013: Morpheus localized nonce/token mismatch.');
$assert(is_string($dbShieldSrc) && !str_contains($dbShieldSrc, "'wp_insert_user'") && !str_contains($dbShieldSrc, "'update_metadata'"), 'FIND-013: DB shield still exempts user/meta APIs.');

// 12. FIND-014: Airlock default removes SVG, active SVG entities blocked, ZIP PHP risk>=80, mid-file & scanner path scanned
require_once $root . '/includes/modules/airlock/src/class-airlock-config.php';
require_once $root . '/includes/modules/airlock/src/class-airlock-scanner.php';
$GLOBALS['__vgt_test_options']['vis_config'] = [];
$airCfg = new VIS_Airlock_Config();
$assert(!array_key_exists('svg', $airCfg->get_allowed_map()), 'FIND-014: SVG still enabled in default Airlock allowlist.');

$tmpSvg = tempnam(sys_get_temp_dir(), 'svg');
if ($tmpSvg !== false) {
    file_put_contents($tmpSvg, '<svg xmlns="http://www.w3.org/2000/svg"><svg:script>&#x61;lert(1)</svg:script></svg>');
    $airScan = new VIS_Airlock_Scanner($airCfg);
    $scanMethod = new ReflectionMethod(VIS_Airlock_Scanner::class, 'scan_svg');
    $assert($scanMethod->invoke($airScan, $tmpSvg) === false, 'FIND-014: Malicious namespaced/entity SVG passed Airlock scan_svg().');
    unlink($tmpSvg);
}

require_once $root . '/includes/scanner/class-vis-malware-engine.php';
$verdict = new VIS_Scan_Verdict('abc', 85, 95, [new VIS_Scan_Finding('ARCHIVE_EXECUTABLE_PAYLOAD', 85, 95, 'test', true)], false);
$assert($verdict->shouldBlock(), 'FIND-014: Archive verdict failed block threshold check.');

$lex = new VIS_Php_Lexical_Detector();
$uploadEvadeCtx = new VIS_Scan_Context(VIS_Scan_Context::PROFILE_DEEP_FILESYSTEM, 'TEST', 'wp-content/uploads/includes/scanner/evil.php', 'evil.php', 'php', 'text/x-php', null, 'NEW');
$evadeRes = $lex->analyzeContent('<?php ev' . 'al($' . '_POST["x"]);', $uploadEvadeCtx);
$assert($evadeRes !== [], 'FIND-014: Lexical detector skipped uploaded file under uploads/includes/scanner/.');

$bigFile = tempnam(sys_get_temp_dir(), 'bigphp');
if ($bigFile !== false) {
    $pad = str_repeat(' ', 600000);
    file_put_contents($bigFile, "<?php\n" . $pad . "\neval(base64_decode(\$_POST['x']));\n" . $pad);
    $bigCtx = new VIS_Scan_Context(VIS_Scan_Context::PROFILE_DEEP_FILESYSTEM, 'TEST', 'wp-content/plugins/test/big.php', 'big.php', 'php', 'text/x-php', null, 'NEW');
    $bigBudget = new VIS_Scan_Budget(1048576, 500, 20, 500, 33554432, 100.0);
    $bigRes = $lex->detect($bigFile, $bigCtx, $bigBudget);
    unlink($bigFile);
    $assert($bigRes !== [], 'FIND-014: Mid-file webshell inside >1MB PHP file escaped multi-window sampling.');
}

// 13. FIND-016: Gorgon views hide key and harvester rejects wp_users
$gorgonView = file_get_contents($root . '/includes/dashboard/views/view-gorgon.php');
$assert(is_string($gorgonView) && !str_contains($gorgonView, 'value="<?php echo esc_attr($api_key); ?>"'), 'FIND-016: Plaintext Gorgon API key rendered in HTML input value.');

require_once $root . '/includes/modules/gorgon/class-vis-gorgon.php';
require_once $root . '/includes/modules/gorgon/src/class-gorgon-config.php';
require_once $root . '/includes/modules/gorgon/src/class-gorgon-harvester.php';
$GLOBALS['wpdb'] = (object)['prefix' => 'wp_'];
$GLOBALS['__vgt_test_options']['vis_config'] = ['gorgon_nodes' => ['exfil' => ['table' => 'wp_users']]];
$gCore = (new ReflectionClass(\VisionGaia\GeDefense\Modules\Gorgon\Gorgon::class))->newInstanceWithoutConstructor();
$harvester = new \VisionGaia\GeDefense\Modules\Gorgon\Gorgon_Harvester(new \VisionGaia\GeDefense\Modules\Gorgon\Gorgon_Config($gCore));
$refActive = new ReflectionMethod(\VisionGaia\GeDefense\Modules\Gorgon\Gorgon_Harvester::class, 'get_active_nodes');
$nodes = $refActive->invoke($harvester);
$assert(!isset($nodes['exfil']), 'FIND-016: Gorgon Harvester admitted wp_users table.');

// 14. FIND-017: view-trinity attribute escaped and VIS_i18n requires manage_options
$trinityView = file_get_contents($root . '/includes/dashboard/views/view-trinity.php');
$i18nSrc = file_get_contents($root . '/includes/core/class-vis-i18n.php');
$assert(is_string($trinityView) && str_contains($trinityView, 'sanitize_html_class'), 'FIND-017: view-trinity attribute missing sanitize_html_class/esc_attr.');
$assert(is_string($i18nSrc) && str_contains($i18nSrc, "\$canPersistGlobal"), 'FIND-017: VIS_i18n global language write missing capability check.');

// 15. HOTFIX-8.6.1-TWEMOJI: Local 4,009-SVG Twemoji mirror, priority-99 URL pinning, inline flag SVGs, Titan fix
if (!defined('VIS_URL')) define('VIS_URL', 'https://example.test/wp-content/plugins/gedefense-wp/');
if (!function_exists('esc_attr')) { function esc_attr(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('remove_query_arg')) { function remove_query_arg(string $key, ?string $url = null): string { return $url ?? '/wp-admin/admin.php?page=vgt-suite'; } }
if (!function_exists('add_query_arg')) { function add_query_arg(string $k, string $v, string $u): string { return $u . (str_contains($u, '?') ? '&' : '?') . urlencode($k) . '=' . urlencode($v); } }

$twSvgDir = $root . '/assets/twemoji/svg';
$assert(is_dir($twSvgDir), 'HOTFIX-8.6.1: assets/twemoji/svg directory missing.');
$assert(file_exists($twSvgDir . '/1f6e1.svg'), 'HOTFIX-8.6.1: Twemoji shield SVG 1f6e1.svg missing.');
$assert(file_exists($twSvgDir . '/1f1e9-1f1ea.svg'), 'HOTFIX-8.6.1: Twemoji DE flag SVG 1f1e9-1f1ea.svg missing.');
$assert(file_exists($root . '/assets/twemoji/LICENSE.txt'), 'HOTFIX-8.6.1: Twemoji LICENSE.txt missing.');
$assert(file_exists($root . '/assets/twemoji/NOTICE.txt'), 'HOTFIX-8.6.1: Twemoji NOTICE.txt missing.');
$svgFiles = glob($twSvgDir . '/*.svg') ?: [];
$assert(count($svgFiles) >= 4009, 'HOTFIX-8.6.1: Expected complete Twemoji 17.0.1 SVG set (>=4009).');

require_once $root . '/includes/core/class-vis-i18n.php';
$GLOBALS['__vgt_filters'] = [];
$GLOBALS['__vgt_removed_hooks'] = [];
// Simulate a conflicting theme/plugin returning false or s.w.org at lower priority
add_filter('emoji_svg_url', static fn(): bool => false, 10);
add_filter('emoji_url', static fn(): string => 'https://s.w.org/images/core/emoji/17.0.2/svg/', 10);
VIS_I18n::init();

$evalFilterChain = static function (string $hook, mixed $initial, mixed ...$extraArgs): mixed {
    $val = $initial;
    $buckets = $GLOBALS['__vgt_filters'][$hook] ?? [];
    ksort($buckets, SORT_NUMERIC);
    foreach ($buckets as $callbacks) {
        foreach ($callbacks as $cb) {
            $val = $cb($val, ...$extraArgs);
        }
    }
    return $val;
};

$resolvedSvgUrl = $evalFilterChain('emoji_svg_url', 'https://s.w.org/images/core/emoji/17.0.2/svg/');
$resolvedPngUrl = $evalFilterChain('emoji_url', 'https://s.w.org/images/core/emoji/17.0.2/72x72/');
$resolvedSvgExt = $evalFilterChain('emoji_svg_ext', '.svg');
$resolvedExt    = $evalFilterChain('emoji_ext', '.png');
$assert(is_string($resolvedSvgUrl) && str_starts_with($resolvedSvgUrl, 'https://example.test/') && str_ends_with($resolvedSvgUrl, '/assets/twemoji/svg/'), 'HOTFIX-8.6.1: emoji_svg_url not pinned to local mirror.');
$assert(!str_contains((string)$resolvedSvgUrl, 'jsdelivr') && !str_contains((string)$resolvedSvgUrl, 's.w.org'), 'HOTFIX-8.6.1: emoji_svg_url still points externally.');
$assert(is_string($resolvedPngUrl) && str_ends_with($resolvedPngUrl, '/assets/twemoji/svg/'), 'HOTFIX-8.6.1: emoji_url not pinned to local mirror.');
$assert($resolvedSvgExt === '.svg' && $resolvedExt === '.svg', 'HOTFIX-8.6.1: emoji extensions not pinned to .svg.');

$hints = $evalFilterChain('wp_resource_hints', ['https://s.w.org', '//cdn.jsdelivr.net', 'https://example.test'], 'dns-prefetch');
$assert($hints === ['https://example.test'], 'HOTFIX-8.6.1: External emoji CDN hosts were not stripped from resource hints.');

$switcherHtml = VIS_I18n::render_language_switcher();
$assert(str_contains($switcherHtml, '<svg class="vis-flag vis-flag-de"') && str_contains($switcherHtml, 'vis-flag-gb') && str_contains($switcherHtml, 'vis-flag-ru'), 'HOTFIX-8.6.1: Inline flag SVGs missing from language switcher.');
$assert(!str_contains($switcherHtml, '<img') && !str_contains($switcherHtml, 'jsdelivr'), 'HOTFIX-8.6.1: Language switcher emitted external img tag.');

$titanSrc = (string)file_get_contents($root . '/includes/modules/titan/class-vis-titan.php');
$assert(!str_contains($titanSrc, "add_filter('emoji_svg_url', '__return_false')"), 'HOTFIX-8.6.1: Titan must not poison emoji_svg_url with __return_false.');
$assert(str_contains($titanSrc, "admin_print_scripts"), 'HOTFIX-8.6.1: Titan unhook_core_emojis must also remove admin emoji scripts.');
require_once $root . '/includes/modules/titan/class-vis-titan.php';
$titanObj = new VIS_Titan(['titan_enabled' => 1, 'titan_cleanup_emojis' => 1]);
$GLOBALS['__vgt_removed_hooks'] = [];
$titanObj->source_cleanup();
$removedPairs = array_map(static fn(array $r): string => $r[0] . ':' . $r[1], $GLOBALS['__vgt_removed_hooks']);
$assert(in_array('action:admin_print_scripts', $removedPairs, true), 'HOTFIX-8.6.1: Titan source_cleanup failed to remove admin_print_scripts.');

if ($failures !== []) {
    fwrite(STDERR, "VGT AUDIT FIXES REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT AUDIT FIXES REGRESSION: PASS (FIND-001..FIND-017 + HOTFIX-8.6.1-TWEMOJI verified)\n");
exit(0);
