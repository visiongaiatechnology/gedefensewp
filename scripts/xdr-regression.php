<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
use VisionGaia\GeDefense\Xdr\EvidenceStore;
use VisionGaia\GeDefense\Xdr\PolicyEngine;
use VisionGaia\GeDefense\Xdr\Redactor;
use VisionGaia\GeDefense\Xdr\RequestContext;
use VisionGaia\GeDefense\Xdr\XdrEvent;

define('ABSPATH', $root . DIRECTORY_SEPARATOR);
define('ARRAY_A', 'ARRAY_A');
if (!function_exists('get_current_user_id')) { function get_current_user_id(): int { return 0; } }
if (!function_exists('home_url')) { function home_url(string $path = ''): string { return 'https://example.test' . $path; } }
if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false {
        return json_encode($data, $options, $depth);
    }
}
if (!function_exists('wp_salt')) { function wp_salt(string $scheme = 'auth'): string { return 'test-salt-secret-0123456789abcdef0123456789abcdef'; } }

require $root . '/includes/xdr/class-xdr-request-context.php';
require $root . '/includes/xdr/class-xdr-redactor.php';
require $root . '/includes/xdr/class-xdr-event.php';
require $root . '/includes/xdr/class-xdr-evidence-store.php';
require $root . '/includes/xdr/class-xdr-policy-engine.php';

$failures = [];

// 1. Request Context & Correlation Stability
$requestId = RequestContext::id();
if (preg_match('/^[a-f0-9]{32}$/D', $requestId) !== 1 || !hash_equals($requestId, RequestContext::id())) {
    $failures[] = 'Request correlation ID is not stable and cryptographic.';
}

// 2. Metadata Redaction Boundary
$redacted = Redactor::sanitize(['api_key' => 'secret', 'nested' => ['password' => 'secret'], 'safe' => "ok\0"]);
if (($redacted['api_key'] ?? '') !== '[REDACTED]' || ($redacted['nested']['password'] ?? '') !== '[REDACTED]' || ($redacted['safe'] ?? '') !== 'ok') {
    $failures[] = 'Metadata redaction boundary failed.';
}

// 3. Canonical Event Contract & Role Verification
$event = XdrEvent::fromArray([
    'sensor' => 'AEGIS', 'category' => 'INGRESS', 'event_type' => 'WAF_DETECTION',
    'role' => 'DETECTION', 'severity' => 8, 'confidence' => 91, 'actor_ip' => '198.51.100.14',
    'metadata' => ['authorization' => 'Bearer secret', 'route' => '/wp-login.php'],
]);
if (preg_match('/^[a-f0-9]{32}$/D', $event->eventId) !== 1 || $event->requestId !== $requestId || $event->category !== 'INGRESS') {
    $failures[] = 'Canonical event contract failed.';
}
if (!$event->isDetection() || $event->isResponse() || $event->isContext()) {
    $failures[] = 'Event role predicates failed.';
}
if (($event->metadata['authorization'] ?? '') !== '[REDACTED]' || strlen($event->hash()) !== 64) {
    $failures[] = 'Event evidence digest or redaction failed.';
}

// 4. Policy Engine Multi-Sensor Gate Verification
$single = PolicyEngine::decide(99, 1);
$multi = PolicyEngine::decide(80, 2);
$lowConf = PolicyEngine::decide(70, 3);
if ($single['containment_allowed'] || !$multi['containment_allowed'] || $lowConf['containment_allowed']) {
    $failures[] = 'Multi-sensor containment gate failed.';
}

// 5. Schema Definitions & Table Assertions
$schema = file_get_contents($root . '/class-vis-schema.php');
$fabric = file_get_contents($root . '/includes/xdr/class-xdr-event-fabric.php');
$eventSource = file_get_contents($root . '/includes/xdr/class-xdr-event.php');
$response = file_get_contents($root . '/includes/xdr/class-xdr-response-engine.php');
$incidentSource = file_get_contents($root . '/includes/xdr/class-xdr-incident-engine.php');
$repoSource = file_get_contents($root . '/includes/xdr/class-xdr-event-repository.php');

foreach (['vis_xdr_events','vis_xdr_incidents','vis_xdr_incident_events','vis_xdr_responses','vis_xdr_evidence'] as $table) {
    if (!is_string($schema) || !str_contains($schema, $table)) $failures[] = 'Missing XDR schema table: ' . $table;
}
if (!is_string($schema) || !str_contains($schema, "role varchar(16) NOT NULL DEFAULT 'DETECTION'")) {
    $failures[] = 'Missing role column in events schema.';
}
if (!is_string($schema) || !str_contains($schema, 'attack_story longtext NOT NULL')) {
    $failures[] = 'Missing attack_story column in incidents schema.';
}
if (!is_string($schema) || !str_contains($schema, "owner varchar(32) NOT NULL DEFAULT 'TRINITY_XDR'")) {
    $failures[] = 'Missing owner column in responses schema.';
}
if (!is_string($schema) || !str_contains($schema, 'current_root char(64) NOT NULL')) {
    $failures[] = 'Missing current_root column in evidence schema.';
}

// 6. Rolling Evidence Chain Verification (Scenario 5)
if (!class_exists('wpdb')) { class wpdb { public string $prefix = 'wp_'; } }
class MockEvidenceWpdb extends wpdb {
    public string $prefix = 'wp_';
    public array $evidence = [];
    public function prepare(string $query, mixed ...$args): string {
        return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
    }
    public function insert(string $table, array $data, array $format): int {
        $this->evidence[] = $data;
        return 1;
    }
    public function get_row(string $query, string $output = 'OBJECT'): ?array {
        if (empty($this->evidence)) return null;
        $last = end($this->evidence);
        return $last;
    }
    public function get_results(string $query, string $output = 'OBJECT'): array {
        return $this->evidence;
    }
    public function get_var(string $query): ?string {
        if (str_contains($query, 'vis_xdr_events')) return null;
        if (empty($this->evidence)) return null;
        $last = end($this->evidence);
        return $last['current_root'] ?? null;
    }
}

global $wpdb;
$wpdb = new MockEvidenceWpdb();

$incidentId = bin2hex(random_bytes(16));
$e1 = XdrEvent::fromArray(['sensor' => 'AEGIS', 'category' => 'INGRESS', 'event_type' => 'WAF_1', 'severity' => 8]);
$e2 = XdrEvent::fromArray(['sensor' => 'MORPHEUS', 'category' => 'RUNTIME', 'event_type' => 'CAP_1', 'severity' => 8]);
$e3 = XdrEvent::fromArray(['sensor' => 'STYX', 'category' => 'EGRESS', 'event_type' => 'BLOCK_1', 'severity' => 7]);

$root1 = EvidenceStore::attach($incidentId, $e1);
$root2 = EvidenceStore::attach($incidentId, $e2);
$root3 = EvidenceStore::attach($incidentId, $e3);

$verification = EvidenceStore::verify($incidentId);
if (($verification['status'] ?? '') !== 'VALID' || ($verification['count'] ?? 0) !== 3 || !hash_equals($root3, $verification['root'])) {
    $failures[] = 'Rolling cryptographic evidence chain valid verification failed.';
}

// Tamper simulation
$wpdb->evidence[1]['digest'] = str_repeat('0', 64);
$tamperVerification = EvidenceStore::verify($incidentId);
if (($tamperVerification['status'] ?? '') === 'VALID') {
    $failures[] = 'Rolling cryptographic evidence chain failed to catch tamper mutation.';
}

// 7. Sensor Independence & Response Non-Increment (Scenario 3)
$respEvent = XdrEvent::fromArray([
    'sensor' => 'CERBERUS', 'category' => 'IDENTITY', 'event_type' => 'BAN_IP',
    'role' => 'RESPONSE', 'severity' => 8, 'confidence' => 100
]);
if (!$respEvent->isResponse() || $respEvent->isDetection()) {
    $failures[] = 'Response event role isolation failed.';
}

// 8. Morpheus Effective Matrix & XDR Overlay Tests
require $root . '/includes/modules/morpheus/src/class-morpheus-path-jail.php';
$GLOBALS['test_options'] = [];
function update_option(string $key, mixed $value, mixed $autoload = null): bool {
    $GLOBALS['test_options'][$key] = $value;
    return true;
}
function get_option(string $key, mixed $default = false): mixed {
    return $GLOBALS['test_options'][$key] ?? $default;
}

$morpheusFile = file_get_contents($root . '/includes/modules/morpheus/class-vis-morpheus.php');
if (!str_contains($morpheusFile, 'get_effective_matrix') || !str_contains($morpheusFile, 'add_xdr_overlay')) {
    $failures[] = 'Morpheus temporary XDR overlay methods missing.';
}

$styxFile = file_get_contents($root . '/includes/modules/styx/class-vis-styx.php');
if (!str_contains($styxFile, 'vis_styx_xdr_overlays') || !str_contains($styxFile, 'add_xdr_overlay')) {
    $failures[] = 'Styx temporary XDR egress overlay methods missing.';
}

if ($failures !== []) {
    fwrite(STDERR, "VGT XDR REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "VGT XDR REGRESSION: PASS\n");
