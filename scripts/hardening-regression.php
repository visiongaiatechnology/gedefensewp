<?php
// STATUS: PLATIN
declare(strict_types=1);

/**
 * GeDefense WP — Hardening Regression Suite
 *
 * Behavioral (not only pattern-based) regression tests for the 8.4.4 hardening
 * pass. Runs with the plain PHP CLI, no Composer, no PHPUnit, no WordPress:
 *
 *   php scripts/hardening-regression.php
 *
 * Exit code 0 = every invariant holds, 1 = at least one regression.
 */

$root = dirname(__DIR__);
define('ABSPATH', str_replace('\\', '/', $root) . '/');

if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'unit-test-auth-key-0123456789abcdef0123456789abcdef');
}

// --- Exception taxonomy (mirrors includes/core/class-vis-security.php) -------
if (!class_exists('AppException', false)) {
    class AppException extends Exception {}
}
if (!class_exists('ValidationException', false)) {
    class ValidationException extends AppException {}
}
if (!class_exists('SecurityException', false)) {
    class SecurityException extends AppException {}
}
if (!class_exists('StorageException', false)) {
    class StorageException extends AppException {}
}

// --- In-memory WordPress shim ------------------------------------------------
$GLOBALS['hr_options'] = [];
$GLOBALS['hr_user_meta'] = [];
$GLOBALS['hr_filters'] = [];
$GLOBALS['hr_nonce_valid'] = false;

function get_option(string $key, mixed $default = false): mixed
{
    return $GLOBALS['hr_options'][$key] ?? $default;
}

function add_option(string $key, mixed $value, string $deprecated = '', bool $autoload = false): bool
{
    if (array_key_exists($key, $GLOBALS['hr_options'])) {
        return false;
    }
    $GLOBALS['hr_options'][$key] = $value;

    return true;
}

function update_option(string $key, mixed $value, bool $autoload = false): bool
{
    $GLOBALS['hr_options'][$key] = $value;

    return true;
}

function get_user_meta(int $user_id, string $key, bool $single = false): mixed
{
    return $GLOBALS['hr_user_meta'][$user_id][$key] ?? '';
}

function update_user_meta(int $user_id, string $key, mixed $value): bool
{
    $GLOBALS['hr_user_meta'][$user_id][$key] = $value;

    return true;
}

function is_user_logged_in(): bool
{
    return true;
}

function get_current_user_id(): int
{
    return 1;
}

function user_can(int $user_id, string $capability): bool
{
    return $user_id === 1 && $capability === 'manage_options';
}

function add_filter(string $hook, $callback, int $priority = 10, int $args = 1): bool
{
    $GLOBALS['hr_filters'][$hook][] = $callback;

    return true;
}

function add_action(string $hook, $callback, int $priority = 10, int $args = 1): bool
{
    return true;
}

function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    if (empty($GLOBALS['hr_filters'][$hook])) {
        return $value;
    }
    foreach ($GLOBALS['hr_filters'][$hook] as $callback) {
        $value = $callback($value, ...$args);
    }

    return $value;
}

function wp_verify_nonce(string $nonce, string $action = '-1'): int|false
{
    return ($GLOBALS['hr_nonce_valid'] && $nonce === 'valid-nonce') ? 1 : false;
}

function wp_create_nonce(string $action = '-1'): string
{
    return 'valid-nonce';
}

function wp_nonce_url(string $action_url, string $action = '-1', string $name = '_wpnonce'): string
{
    $separator = str_contains($action_url, '?') ? '&' : '?';

    return $action_url . $separator . $name . '=' . wp_create_nonce($action);
}

function add_query_arg(mixed $key, mixed $value = null, ?string $url = null): string
{
    if (is_array($key)) {
        $args   = $key;
        $target = (string)$value;
    } else {
        $args   = [(string)$key => $value];
        $target = (string)$url;
    }

    $separator = str_contains($target, '?') ? '&' : '?';

    return $target . $separator . http_build_query($args);
}

function remove_query_arg(string|array $key, ?string $url = null): string
{
    $url    = $url ?? (string)($_SERVER['REQUEST_URI'] ?? '/');
    $parts  = parse_url($url);
    $query  = [];
    if (isset($parts['query'])) {
        parse_str((string)$parts['query'], $query);
    }
    foreach ((array)$key as $name) {
        unset($query[$name]);
    }

    $path = (string)($parts['path'] ?? '/');
    if (isset($parts['host'])) {
        $scheme = (string)($parts['scheme'] ?? 'https');
        $path   = $scheme . '://' . $parts['host'] . $path;
    }

    return $query === [] ? $path : $path . '?' . http_build_query($query);
}

function esc_attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_url(string $value): string
{
    return $value;
}

function esc_url_raw(string $value): string
{
    return $value;
}

function is_ssl(): bool
{
    return true;
}

function wp_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function wp_parse_url(string $url): array|false
{
    return parse_url($url);
}

// --- Assertion helpers -------------------------------------------------------
$failures   = [];
$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$assertions): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$expectThrow = static function (callable $operation, string $expectedClass, string $message) use ($assert): void {
    try {
        $operation();
        $assert(false, $message . ' (no exception thrown)');
    } catch (Throwable $error) {
        $assert($error instanceof $expectedClass, $message . ' (unexpected ' . get_class($error) . ')');
    }
};

$assertContains = static function (string $haystack, string $needle, string $message) use ($assert): void {
    $assert(str_contains($haystack, $needle), $message);
};

$assertNotContains = static function (string $haystack, string $needle, string $message) use ($assert): void {
    $assert(!str_contains($haystack, $needle), $message);
};

$assertMatches = static function (string $haystack, string $pattern, string $message) use ($assert): void {
    $assert((bool)preg_match($pattern, $haystack), $message);
};

// =============================================================================
// 1. VAULT — crypto fail-closed, no hard-coded legacy secret
// =============================================================================
require_once $root . '/class-vis-vault.php';

$plaintext = 'unit-test-secret-value-42';
$ciphertext = VIS_Vault::encrypt($plaintext);

$assert(str_starts_with($ciphertext, 'vgt1:'), 'Vault ciphertext is not versioned.');
$assert(VIS_Vault::decrypt($ciphertext) === $plaintext, 'Vault encrypt/decrypt roundtrip failed.');
$assert(
    VIS_Vault::encrypt($plaintext) !== VIS_Vault::encrypt($plaintext),
    'Vault reuses nonces: two encryptions produced identical ciphertext.'
);

$expectThrow(
    static fn (): string => VIS_Vault::decrypt(''),
    SecurityException::class,
    'Empty vault payload was accepted.'
);

// Tampered versioned payload must be rejected, never returned.
$tampered = 'vgt1:' . strrev(substr($ciphertext, strlen('vgt1:')));
$expectThrow(
    static fn (): string => VIS_Vault::decrypt($tampered),
    SecurityException::class,
    'Tampered versioned payload was not rejected (fail-open).'
);

// Ciphertext-shaped garbage without prefix must not be reflected back.
$opaque = base64_encode(random_bytes(64));
$expectThrow(
    static fn (): string => VIS_Vault::decrypt($opaque),
    SecurityException::class,
    'Unversioned ciphertext-shaped blob was returned unverified.'
);

// Genuine legacy plaintext (pre-vault configuration) must still pass through.
$assert(
    VIS_Vault::decrypt('gsk_plain.key-123') === 'gsk_plain.key-123',
    'Legacy plaintext configuration value was not passed through.'
);

// The removed hard-coded secret must no longer decrypt anything.
if (function_exists('sodium_crypto_secretbox')) {
    $legacyKey  = hash('sha256', 'VGT_INTERNAL_ENCRYPTION_SECRET', true);
    $legacyNonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $legacyBlob = base64_encode(
        $legacyNonce . sodium_crypto_secretbox('legacy-secret', $legacyNonce, $legacyKey)
    );

    $expectThrow(
        static fn (): string => VIS_Vault::decrypt($legacyBlob),
        SecurityException::class,
        'Hard-coded legacy vault secret still decrypts payloads.'
    );
}

$vaultSource = (string)file_get_contents($root . '/class-vis-vault.php');
$assertNotContains($vaultSource, 'VGT_INTERNAL_ENCRYPTION_SECRET', 'Hard-coded vault secret reintroduced.');
$assertContains($vaultSource, 'VIS_VAULT_LEGACY_KEY', 'Opt-in legacy migration key missing.');
$assertContains($vaultSource, 'looks_like_ciphertext', 'Ciphertext detection helper missing.');

// =============================================================================
// 2. AEGIS — capability policy must not whitelist content authors
// =============================================================================
require_once $root . '/includes/modules/aegis/class-vis-aegis.php';

$userIsPrivileged = new ReflectionMethod(VIS_Aegis::class, 'user_is_privileged');
$userIsPrivileged->setAccessible(true);

$contributor = new class {
    public function has_cap(string $capability): bool
    {
        return $capability === 'edit_posts';
    }
};
$editor = new class {
    public function has_cap(string $capability): bool
    {
        return $capability === 'edit_posts' || $capability === 'publish_posts';
    }
};
$administrator = new class {
    public function has_cap(string $capability): bool
    {
        return $capability === 'manage_options';
    }
};

$assert($userIsPrivileged->invoke(null, $contributor) === false, 'Contributor bypasses the WAF whitelist.');
$assert($userIsPrivileged->invoke(null, $editor) === false, 'Editor bypasses the WAF whitelist.');
$assert($userIsPrivileged->invoke(null, $administrator) === true, 'Administrator lost WAF whitelisting.');

$userByIdIsPrivileged = new ReflectionMethod(VIS_Aegis::class, 'user_id_is_privileged');
$userByIdIsPrivileged->setAccessible(true);
$assert($userByIdIsPrivileged->invoke(null, 1) === true, 'Cookie fallback no longer recognises the administrator.');
$assert($userByIdIsPrivileged->invoke(null, 0) === false, 'Cookie fallback trusts user id 0.');

$aegisSource = (string)file_get_contents($root . '/includes/modules/aegis/class-vis-aegis.php');
$assertNotContains($aegisSource, "has_cap('edit_posts')", 'edit_posts capability bypass reintroduced.');
$assertNotContains($aegisSource, "user_can(\$user_id, 'edit_posts')", 'Cookie-based edit_posts bypass reintroduced.');

// =============================================================================
// 3. I18N — language switching requires a nonce, GET alone never persists
// =============================================================================
require_once $root . '/includes/core/class-vis-i18n.php';

$GLOBALS['hr_options']   = [];
$GLOBALS['hr_user_meta'] = [];
$_GET = ['vis_lang' => 'en'];
$_REQUEST = $_GET;
VIS_I18n::init();
$assert(
    !isset($GLOBALS['hr_options']['vis_config']['dashboard_lang']),
    'Bare GET mutated the global language option (CSRF).'
);
$assert(
    !isset($GLOBALS['hr_user_meta'][1]['vis_dashboard_lang']),
    'Bare GET mutated user language meta (CSRF).'
);

$GLOBALS['hr_options']    = [];
$GLOBALS['hr_user_meta']  = [];
$GLOBALS['hr_nonce_valid'] = true;
$_GET = ['vis_lang' => 'ru', '_wpnonce' => 'valid-nonce'];
$_REQUEST = $_GET;
VIS_I18n::init();
$assert(
    ($GLOBALS['hr_options']['vis_config']['dashboard_lang'] ?? '') === 'ru',
    'Nonce-protected language switch did not persist the global option.'
);
$assert(
    ($GLOBALS['hr_user_meta'][1]['vis_dashboard_lang'] ?? '') === 'ru',
    'Nonce-protected language switch did not persist user meta.'
);

$GLOBALS['hr_options'] = [];
unset($GLOBALS['hr_user_meta'][1]);
$_GET = ['vis_lang' => 'de', '_wpnonce' => 'forged'];
$_REQUEST = $_GET;
VIS_I18n::init();
$assert(
    !isset($GLOBALS['hr_options']['vis_config']['dashboard_lang']),
    'Invalid nonce still persisted a language change.'
);

$GLOBALS['hr_options'] = [];
$_GET = ['vis_lang' => 'xx', '_wpnonce' => 'valid-nonce'];
$_REQUEST = $_GET;
VIS_I18n::init();
$assert(
    !isset($GLOBALS['hr_options']['vis_config']['dashboard_lang']),
    'Unsupported language code was accepted.'
);

$languageUrl = VIS_I18n::language_url('en', 'https://example.test/wp-admin/admin.php?page=vgt');
$assert(str_contains($languageUrl, 'vis_lang=en'), 'language_url lost the language argument.');
$assert(str_contains($languageUrl, '_wpnonce='), 'language_url is not nonce protected.');

$i18nSource = (string)file_get_contents($root . '/includes/core/class-vis-i18n.php');
$assertContains($i18nSource, 'vis_lang_switch', 'Language nonce action missing.');
$assertMatches($i18nSource, '/samesite\'\s*=>\s*\'Lax\'/', 'Language cookie is missing SameSite.');
$assertMatches($i18nSource, "/'httponly'\s*=>\s*true/", 'Language cookie is missing HttpOnly.');

// =============================================================================
// 4. BOOTSTRAPPER — fail-closed but opaque, with break-glass
// =============================================================================
$bootstrapSource = (string)file_get_contents($root . '/class-vis-bootstrapper.php');
$assertNotContains($bootstrapSource, 'VGT SYSTEM HALT', 'Legacy fail-close page reintroduced.');
$assertContains($bootstrapSource, 'VIS_DISABLE_FAIL_CLOSE', 'Break-glass constant missing from fail-close path.');
$assertContains($bootstrapSource, 'htmlspecialchars($incident', 'Fail-close page does not render an opaque incident id.');
$assertContains($bootstrapSource, 'X-VGT-Incident', 'Fail-close response lacks a correlation header.');

// =============================================================================
// 5. MAIN PLUGIN FILE — platform floor, secure defaults, cron lifecycle
// =============================================================================
$mainSource = (string)file_get_contents($root . '/gedefense-wp.php');
$assertContains($mainSource, 'Requires PHP: 8.2', 'Plugin header lost the PHP requirement.');
$assertContains($mainSource, 'Requires at least: 6.0', 'Plugin header lost the WordPress requirement.');
$assertContains($mainSource, 'PHP_VERSION_ID < 80200', 'Runtime PHP version guard missing.');
$assertContains($mainSource, '_get_cron_array', 'Deactivation does not sweep plugin cron events.');
$assertMatches($mainSource, '/\$defaults = \[[\s\S]*?\'aegis_enabled\'\s*=>\s*1/', 'Secure activation default for aegis_enabled missing.');
$assertMatches($mainSource, '/\$defaults = \[[\s\S]*?\'aegis_mode\'\s*=>\s*\'learning\'/', 'Safe onboarding mode (learning) missing.');
$assertNotContains($mainSource, 'vis_hourly_scan_event', 'Dead cron hook reference reintroduced.');

// =============================================================================
// 6. REMAINING 8.4.4 INVARIANTS — salts, schema revision, bounds, add-on guard
// =============================================================================
$securitySource = (string)file_get_contents($root . '/includes/core/class-vis-security.php');
$assertContains($securitySource, 'function install_key(', 'Per-installation key derivation missing.');
$assertNotContains($securitySource, 'vgt-oracle-cache', 'Static oracle fallback salt reintroduced.');

$gorgonConfigSource = (string)file_get_contents($root . '/includes/modules/gorgon/src/class-gorgon-config.php');
$assertNotContains($gorgonConfigSource, 'vgt_omega_fallback_salt', 'Static Gorgon fallback salt reintroduced.');

$nemesisSource = (string)file_get_contents($root . '/includes/modules/nemesis/class-vis-nemesis.php');
$assertNotContains($nemesisSource, 'vgt_critical_fallback_salt_99', 'Static Nemesis fallback salt reintroduced.');

$assertContains($mainSource, 'VIS_GORGON_NEXUS_MAGIC', 'Nexus fingerprint constant missing.');
$assertContains($mainSource, 'VIS_Schema::SCHEMA_VERSION', 'Schema gating still uses the marketing version string.');

$schemaSource = (string)file_get_contents($root . '/class-vis-schema.php');
$assertContains($schemaSource, 'public const SCHEMA_VERSION', 'Numeric schema revision missing.');
$assertNotContains($schemaSource, "update_option('vis_db_version', VIS_VERSION)", 'Schema revision still derived from VIS_VERSION.');

$geoSource = (string)file_get_contents($root . '/includes/core/class-vis-geo-resolver.php');
$assertContains($geoSource, 'MAX_GEO_TRANSIENTS', 'Geo transient cache is unbounded.');
$assertContains($geoSource, 'remember_geo_transient', 'Bounded geo cache helper missing.');

$dashboardSource = (string)file_get_contents($root . '/includes/dashboard/class-vis-dashboard-ajax.php');
$assertContains($dashboardSource, 'htaccess_rules', 'Add-on directory .htaccess guard missing.');
$assertNotContains($dashboardSource, 'Safe: escaped in frontend jQuery', 'Latent unescaped file content response reintroduced.');

// =============================================================================
// 7. THREAT-INTEL FEED HARDENING (SEC-05) AND ADD-ON ARCHIVE GUARD
// =============================================================================
$threatSource = (string)file_get_contents($root . '/includes/modules/threat-intel/class-vis-threat-intel.php');
$assertContains($threatSource, 'MAX_ENTRIES_PER_FEED', 'Per-feed entry cap missing.');
$assertContains($threatSource, 'GROWTH_QUARANTINE_FACTOR', 'Feed growth quarantine missing.');
$assertContains($threatSource, 'pinned_https_get', 'Pinned transport not used for feed downloads.');
$assertContains($threatSource, 'RENAME TABLE', 'Legacy schema is no longer archived non-destructively.');
$assertNotContains($threatSource, 'DROP TABLE', 'Destructive legacy table drop reintroduced.');
$assertNotContains($threatSource, 'TRUNCATE TABLE', 'Irreversible TRUNCATE reintroduced.');

$assertContains($dashboardSource, '0xA000', 'ZIP symlink entry rejection missing.');

// =============================================================================
// 8. OPAQUE FAILURES, CI GATE AND DOCUMENTATION SYNC
// =============================================================================
$assertContains($dashboardSource, 'function opaque_failure(', 'Opaque AJAX failure helper missing.');
$assertNotContains(
    $dashboardSource,
    "Benchmark execution error: ' . \$e->getMessage()",
    'Internal benchmark error message leaked to the client again.'
);

$ciPath = $root . '/.github/workflows/hardening.yml';
$assert(is_file($ciPath), 'CI hardening workflow missing.');
if (is_file($ciPath)) {
    $ciSource = (string)file_get_contents($ciPath);
    $assertContains($ciSource, 'php -l', 'CI workflow does not lint every PHP file.');
    $assertContains($ciSource, 'scripts/${suite}-regression.php', 'CI workflow does not execute the regression suites.');
    $assertContains($ciSource, 'integrity-regression.php', 'CI workflow does not verify the trust anchor.');
}

// Every outbound HTTP consumer must bound the response size.
foreach ([
    'includes/modules/aegis/class-vis-aegis-oracle.php',
    'includes/modules/morpheus/src/class-morpheus-ai.php',
    'includes/modules/gorgon/src/class-gorgon-uplink.php',
    'includes/modules/gorgon/src/class-vis-gorgon-ajax.php',
    'includes/core/class-vis-ai-gateway.php',
] as $remoteFile) {
    $remoteSource = (string)file_get_contents($root . '/' . $remoteFile);
    $assertContains($remoteSource, 'limit_response_size', 'Remote response is unbounded in ' . $remoteFile . '.');
}

$readmeSource = (string)file_get_contents($root . '/README.md');
$assertContains($readmeSource, 'Complete 29-Suite Test Matrix', 'README test matrix count is out of sync.');
$assertContains($readmeSource, 'hardening-regression.php', 'README does not document the hardening suite.');

// =============================================================================
// RESULT
// =============================================================================
if ($failures !== []) {
    fwrite(STDERR, "VGT HARDENING REGRESSION: FAIL\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '  - ' . $failure . "\n");
    }
    fwrite(STDERR, sprintf("Assertions: %d, Failures: %d\n", $assertions, count($failures)));
    exit(1);
}

echo sprintf("VGT HARDENING REGRESSION: PASS (%d assertions)\n", $assertions);
exit(0);
