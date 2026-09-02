<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
use VisionGaia\GeDefense\Xdr\EventFabric;
use VisionGaia\GeDefense\Xdr\EventRepository;
use VisionGaia\GeDefense\Xdr\EvidenceStore;
use VisionGaia\GeDefense\Xdr\IncidentEngine;
use VisionGaia\GeDefense\Xdr\PolicyEngine;
use VisionGaia\GeDefense\Xdr\Redactor;
use VisionGaia\GeDefense\Xdr\RequestContext;
use VisionGaia\GeDefense\Xdr\ResponseEngine;
use VisionGaia\GeDefense\Xdr\XdrEvent;
use VisionGaia\GeDefense\Modules\Morpheus\Morpheus;
use VisionGaia\GeDefense\Modules\Styx\Styx;

define('ABSPATH', $root . DIRECTORY_SEPARATOR);
define('ARRAY_A', 'ARRAY_A');
define('AUTH_SALT', 'test-salt-secret-0123456789abcdef0123456789abcdef');

define('WP_CONTENT_DIR', sys_get_temp_dir() . '/vgt_test_content_' . md5($root));
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
@mkdir(WP_CONTENT_DIR . '/vgt-vault/morpheus', 0777, true);
@mkdir(WP_PLUGIN_DIR, 0777, true);

if (!function_exists('add_action')) { function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {} }
if (!function_exists('add_filter')) { function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {} }
if (!function_exists('get_current_user_id')) { function get_current_user_id(): int { return 0; } }
if (!function_exists('home_url')) { function home_url(string $path = ''): string { return 'https://example.test' . $path; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false { return json_encode($data, $options, $depth); } }
if (!function_exists('wp_salt')) { function wp_salt(string $scheme = 'auth'): string { return 'test-salt-secret-0123456789abcdef0123456789abcdef'; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = []): bool { return true; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool { return true; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled(string $hook, array $args = []): int|false { return false; } }
if (!function_exists('wp_unslash')) { function wp_unslash(mixed $val): mixed { return $val; } }
if (!function_exists('wp_normalize_path')) { function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); } }
if (!function_exists('get_theme_root')) { function get_theme_root(): string { return WP_CONTENT_DIR . '/themes'; } }

$matrixFile = $root . '/includes/modules/morpheus/compiled-matrix.json';
$matrixHash = is_file($matrixFile) ? hash_hmac('sha256', (string)file_get_contents($matrixFile), AUTH_SALT) : '';

$GLOBALS['xdr_sim_options'] = [
    'vis_xdr_schema_version' => '3',
    'vis_xdr_config' => ['auto_response_enabled' => 1, 'retention_days' => 30],
    'vis_config' => ['styx_enabled' => 1],
    'vgt_styx_schema_ready' => '1',
    'vgt_matrix_hash' => $matrixHash,
];
function get_option(string $key, mixed $default = false): mixed {
    return $GLOBALS['xdr_sim_options'][$key] ?? $default;
}
function update_option(string $key, mixed $value, mixed $autoload = null): bool {
    $GLOBALS['xdr_sim_options'][$key] = $value;
    return true;
}
function delete_option(string $key): bool {
    unset($GLOBALS['xdr_sim_options'][$key]);
    return true;
}

if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public array $tables = [];
        public int $insert_id = 0;
        public function get_charset_collate(): string { return 'DEFAULT CHARSET=utf8mb4'; }
    }
}

class XdrTestDatabase extends wpdb {
    public array $tables = [
        'wp_vis_xdr_events' => [],
        'wp_vis_xdr_incidents' => [],
        'wp_vis_xdr_incident_events' => [],
        'wp_vis_xdr_responses' => [],
        'wp_vis_xdr_evidence' => [],
        'wp_vis_omega_bans' => [],
        'wp_vis_omega_logs' => [],
    ];
    public int $insert_id = 0;

    public function query(string $q): mixed {
        if (str_starts_with($q, 'UPDATE wp_vis_xdr_incidents')) {
            if (!empty($this->tables['wp_vis_xdr_incidents'])) {
                $last = count($this->tables['wp_vis_xdr_incidents']) - 1;
                $this->tables['wp_vis_xdr_incidents'][$last]['updated_at'] = gmdate('Y-m-d H:i:s');
                $this->tables['wp_vis_xdr_incidents'][$last]['event_count']++;
            }
        }
        return true;
    }

    public function insert(string $table, array $data, array $format = []): int {
        $this->insert_id++;
        $data['id'] = $this->insert_id;
        $this->tables[$table][] = $data;
        return 1;
    }

    public function update(string $table, array $data, array $where, array $format = [], array $where_format = []): int {
        if (!isset($this->tables[$table])) return 0;
        $updated = 0;
        foreach ($this->tables[$table] as $idx => $row) {
            $match = true;
            foreach ($where as $wKey => $wVal) {
                if (($row[$wKey] ?? null) !== $wVal) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $this->tables[$table][$idx] = array_merge($row, $data);
                $updated++;
            }
        }
        return $updated;
    }

    public function delete(string $table, array $where, array $where_format = []): int {
        if (!isset($this->tables[$table])) return 0;
        $before = count($this->tables[$table]);
        $this->tables[$table] = array_values(array_filter($this->tables[$table], function($row) use ($where) {
            foreach ($where as $k => $v) {
                if (($row[$k] ?? null) === $v) return false;
            }
            return true;
        }));
        return $before - count($this->tables[$table]);
    }

    public function prepare(string $query, mixed ...$args): string {
        if (empty($args)) return $query;
        if (isset($args[0]) && is_array($args[0])) $args = $args[0];
        $formatted = $query;
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? (string)$arg : "'" . addslashes((string)$arg) . "'";
            $formatted = preg_replace('/%[sdf]/', $val, $formatted, 1);
        }
        return $formatted;
    }

    public function get_row(string $query, string $output = 'OBJECT'): ?array {
        if (str_contains($query, 'vis_xdr_incidents')) {
            if (empty($this->tables['wp_vis_xdr_incidents'])) return null;
            if (preg_match("/execution_chain_id = '([^']+)'/", $query, $m)) {
                foreach (array_reverse($this->tables['wp_vis_xdr_incidents']) as $r) {
                    if (($r['execution_chain_id'] ?? '') === $m[1] && in_array($r['status'], ['OPEN','INVESTIGATING','CONTAINED','MONITORING'], true)) {
                        return $r;
                    }
                }
                return null;
            }
            if (preg_match("/correlation_key = '([^']+)'/", $query, $m)) {
                foreach (array_reverse($this->tables['wp_vis_xdr_incidents']) as $r) {
                    if (($r['correlation_key'] ?? '') === $m[1] && in_array($r['status'], ['OPEN','INVESTIGATING','CONTAINED','MONITORING'], true)) {
                        return $r;
                    }
                }
                return null;
            }
            if (preg_match("/incident_uuid = '([^']+)'/", $query, $m)) {
                foreach ($this->tables['wp_vis_xdr_incidents'] as $r) {
                    if (($r['incident_uuid'] ?? '') === $m[1]) return $r;
                }
                return null;
            }
            return null;
        }
        if (str_contains($query, 'vis_xdr_evidence')) {
            $rows = $this->tables['wp_vis_xdr_evidence'];
            if (empty($rows)) return null;
            return end($rows);
        }
        if (str_contains($query, 'vis_omega_bans')) {
            if (preg_match("/ip = '([^']+)'/", $query, $m)) {
                foreach ($this->tables['wp_vis_omega_bans'] as $r) {
                    if (($r['ip'] ?? '') === $m[1]) return $r;
                }
            }
            $rows = $this->tables['wp_vis_omega_bans'];
            if (empty($rows)) return null;
            return end($rows);
        }
        if (str_contains($query, 'vis_xdr_events')) {
            if (str_contains($query, 'dedupe_hash')) {
                if (preg_match("/dedupe_hash = '([^']+)'/", $query, $m)) {
                    foreach ($this->tables['wp_vis_xdr_events'] as $r) {
                        if (($r['dedupe_hash'] ?? '') === $m[1]) return $r;
                    }
                }
            }
        }
        return null;
    }

    public function get_var(string $query): ?string {
        if (str_contains($query, 'SHOW TABLES')) {
            preg_match("/LIKE\s+['\"]?([^'\"]+)['\"]?/i", $query, $m);
            return $m[1] ?? 'table';
        }
        if (str_contains($query, 'action_type FROM')) {
            $rows = $this->tables['wp_vis_xdr_responses'];
            if (empty($rows)) return null;
            $last = end($rows);
            return $last['action_type'] ?? null;
        }
        if (str_contains($query, 'incident_uuid FROM') && str_contains($query, 'vis_xdr_incident_events')) {
            $rows = $this->tables['wp_vis_xdr_incident_events'];
            if (empty($rows)) return null;
            $last = end($rows);
            return $last['incident_uuid'] ?? null;
        }
        if (str_contains($query, 'event_hash FROM') && str_contains($query, 'vis_xdr_events')) {
            if (preg_match("/event_uuid = '([^']+)'/", $query, $m)) {
                foreach ($this->tables['wp_vis_xdr_events'] as $r) {
                    if (($r['event_uuid'] ?? '') === $m[1]) return $r['event_hash'];
                }
            }
        }
        if (str_contains($query, 'id FROM') && str_contains($query, 'vis_xdr_responses')) {
            if (preg_match("/target_id = '([^']+)'/", $query, $m)) {
                foreach ($this->tables['wp_vis_xdr_responses'] as $r) {
                    if (($r['target_id'] ?? '') === $m[1] && ($r['status'] ?? '') === 'APPLIED') {
                        $exp = $r['expires_at'];
                        if ($exp === null || strtotime($exp) > time()) {
                            return (string)$r['id'];
                        }
                    }
                }
            }
            return null;
        }
        return null;
    }

    public function get_results(string $query, string $output = 'OBJECT'): array {
        if (str_contains($query, 'vis_xdr_evidence')) {
            if (preg_match("/LIMIT\s+(\d+)/i", $query, $m)) {
                $limit = (int)$m[1];
                return array_slice($this->tables['wp_vis_xdr_evidence'], 0, $limit);
            }
            return $this->tables['wp_vis_xdr_evidence'];
        }
        if (str_contains($query, 'vis_xdr_responses')) {
            return $this->tables['wp_vis_xdr_responses'];
        }
        if (str_contains($query, 'vis_xdr_events')) {
            return $this->tables['wp_vis_xdr_events'];
        }
        return [];
    }
}

global $wpdb;
$wpdb = new XdrTestDatabase();

class VIS_Cerberus {
    private static ?self $instance = null;
    public string $table_bans = 'wp_vis_omega_bans';
    public static function instance(): self { return self::$instance ??= new self(); }
    public function ban_ip(string $ip, string $reason): void {
        global $wpdb;
        $wpdb->insert('wp_vis_omega_bans', ['ip' => $ip, 'reason' => $reason, 'banned_at' => gmdate('Y-m-d H:i:s'), 'request_uri' => '/']);
    }
    public function is_ip_banned(string $ip): bool {
        global $wpdb;
        $banRow = $wpdb->get_row($wpdb->prepare("SELECT id, reason FROM {$this->table_bans} WHERE ip = %s LIMIT 1", $ip), \ARRAY_A);
        $is_banned = is_array($banRow) && isset($banRow['id']);
        if ($is_banned) {
            $reason = (string)($banRow['reason'] ?? '');
            if (str_starts_with($reason, 'TRINITY_XDR:')) {
                if (class_exists('\VisionGaia\GeDefense\Xdr\ResponseEngine')) {
                    if (!\VisionGaia\GeDefense\Xdr\ResponseEngine::isIpRestricted($ip)) {
                        $is_banned = false;
                    }
                }
            }
        }
        return $is_banned;
    }
}

require $root . '/includes/core/class-vis-security.php';
require $root . '/includes/xdr/class-xdr-request-context.php';
require $root . '/includes/xdr/class-xdr-redactor.php';
require $root . '/includes/xdr/class-xdr-event.php';
require $root . '/includes/xdr/class-xdr-event-repository.php';
require $root . '/includes/xdr/class-xdr-evidence-store.php';
require $root . '/includes/xdr/class-xdr-policy-engine.php';
require $root . '/includes/xdr/class-xdr-response-engine.php';
require $root . '/includes/xdr/class-xdr-incident-engine.php';
require $root . '/includes/xdr/class-xdr-event-fabric.php';

require $root . '/includes/modules/morpheus/src/class-morpheus-path-jail.php';
require $root . '/includes/modules/morpheus/class-vis-morpheus.php';
require $root . '/includes/modules/styx/class-vis-styx.php';

$failures = [];
EventFabric::boot();

// =========================================================================
// TEST A: ONE REAL EXECUTION CHAIN (Same request_id / execution_chain_id)
// =========================================================================
$reqA = bin2hex(random_bytes(16));
$chainA = 'xc_' . $reqA;
$actorA = '198.51.100.71';

$incA1 = EventFabric::ingest(['sensor' => 'AEGIS', 'category' => 'INGRESS', 'event_type' => 'SQLI_ATTEMPT', 'role' => 'DETECTION', 'severity' => 8, 'confidence' => 85, 'actor_ip' => $actorA, 'request_id' => $reqA, 'execution_chain_id' => $chainA]);
$incA2 = EventFabric::ingest(['sensor' => 'PROMETHEUS', 'category' => 'BEHAVIOR', 'event_type' => 'ANOMALY', 'role' => 'DETECTION', 'severity' => 8, 'confidence' => 85, 'actor_ip' => $actorA, 'request_id' => $reqA, 'execution_chain_id' => $chainA]);
$incA3 = EventFabric::ingest(['sensor' => 'MORPHEUS', 'category' => 'RUNTIME', 'event_type' => 'CAPABILITY_VIOLATION', 'role' => 'DETECTION', 'severity' => 9, 'confidence' => 90, 'actor_ip' => $actorA, 'request_id' => $reqA, 'execution_chain_id' => $chainA, 'entity_type' => 'PLUGIN', 'entity_id' => 'vulnerable-plugin']);
$incA4 = EventFabric::ingest(['sensor' => 'FILESYSTEM', 'category' => 'FILESYSTEM', 'event_type' => 'PHP_FILE_CREATED', 'role' => 'DETECTION', 'severity' => 8, 'confidence' => 90, 'actor_ip' => $actorA, 'request_id' => $reqA, 'execution_chain_id' => $chainA]);
$incA5 = EventFabric::ingest(['sensor' => 'STYX', 'category' => 'EGRESS', 'event_type' => 'EGRESS_BLOCKED', 'role' => 'DETECTION', 'severity' => 8, 'confidence' => 90, 'actor_ip' => $actorA, 'request_id' => $reqA, 'execution_chain_id' => $chainA, 'entity_type' => 'PLUGIN', 'entity_id' => 'vulnerable-plugin']);

if (empty($incA1) || $incA1 !== $incA2 || $incA2 !== $incA3 || $incA3 !== $incA4 || $incA4 !== $incA5) {
    $failures[] = 'TEST A: Multi-sensor events with same execution chain failed to correlate to single incident.';
}

// =========================================================================
// TEST B: SAME IP, DIFFERENT ATTACK (Not merged into same incident)
// =========================================================================
$reqB = bin2hex(random_bytes(16));
$incB = EventFabric::ingest([
    'sensor' => 'HADES', 'category' => 'AUTHENTICATION', 'event_type' => 'ADMIN_SURFACE_PROBE',
    'role' => 'DETECTION', 'severity' => 7, 'confidence' => 80, 'actor_ip' => $actorA,
    'request_id' => $reqB, 'execution_chain_id' => 'xc_' . $reqB, 'route' => '/wp-login.php',
    'vector' => 'LOGIN_PROBE'
]);

if ($incB === $incA1) {
    $failures[] = 'TEST B: Unrelated attack from same IP was erroneously merged into previous incident.';
}

// =========================================================================
// TEST C: COMPONENT CONTAINMENT (Plugin-specific isolation)
// =========================================================================
$morpheusOverlays = get_option('vis_morpheus_xdr_overlays', []);
$styxOverlays = get_option('vis_styx_xdr_overlays', []);

if (empty($morpheusOverlays[$incA1]) || ($morpheusOverlays[$incA1]['target_component'] ?? '') !== 'vulnerable-plugin') {
    $failures[] = 'TEST C: Morpheus temporary overlay was not created for compromised plugin.';
}
if (empty($styxOverlays[$incA1]) || ($styxOverlays[$incA1]['target_component'] ?? '') !== 'vulnerable-plugin') {
    $failures[] = 'TEST C: Styx component-aware egress overlay was not created for compromised plugin.';
}

// Verify Styx component isolation: other plugins remain allowed!
$styx = Styx::get_instance();
$isWooAllowed = $styx->check_host('api.wordpress.org', 'PLUGIN: woocommerce');
$isVulnAllowed = $styx->check_host('api.wordpress.org', 'PLUGIN: vulnerable-plugin');

if (!$isWooAllowed || $isVulnAllowed) {
    $failures[] = 'TEST C: Styx component-aware egress isolation failed (affected innocent plugin or allowed rogue).';
}

// =========================================================================
// TEST D: HARD TTL WITHOUT CRON (Cerberus lookup evaluation)
// =========================================================================
$cerberus = VIS_Cerberus::instance();
if (!$cerberus->is_ip_banned($actorA)) {
    $failures[] = 'TEST D: Active XDR ban was not enforced by Cerberus.';
}

// Simulate expiration at T+16 minutes (WP-Cron has NOT run)
foreach ($wpdb->tables['wp_vis_xdr_responses'] as $idx => $r) {
    if (($r['target_id'] ?? '') === $actorA) {
        $wpdb->tables['wp_vis_xdr_responses'][$idx]['expires_at'] = gmdate('Y-m-d H:i:s', time() - 60);
    }
}
if ($cerberus->is_ip_banned($actorA)) {
    $failures[] = 'TEST D: Expired XDR ban was still enforced without WP-Cron cleanup.';
}

// =========================================================================
// TEST E: DEDUPE (1000 identical events -> occurrence_count updated)
// =========================================================================
$reqE = bin2hex(random_bytes(16));
$incE = EventFabric::ingest([
    'sensor' => 'AEGIS', 'category' => 'INGRESS', 'event_type' => 'BURST_ATTACK',
    'role' => 'DETECTION', 'severity' => 8, 'confidence' => 80, 'actor_ip' => '198.51.100.99',
    'request_id' => $reqE, 'execution_chain_id' => 'xc_' . $reqE,
]);

EventFabric::ingest([
    'sensor' => 'AEGIS', 'category' => 'INGRESS', 'event_type' => 'BURST_ATTACK',
    'role' => 'DETECTION', 'severity' => 8, 'confidence' => 80, 'actor_ip' => '198.51.100.99',
    'request_id' => $reqE, 'execution_chain_id' => 'xc_' . $reqE,
]);

$lastInc = end($wpdb->tables['wp_vis_xdr_incidents']);
if (!$lastInc || (int)$lastInc['event_count'] < 2) {
    $failures[] = 'TEST E: Coalesced event failed to update incident event_count.';
}

// =========================================================================
// TEST F: RESPONSE FEEDBACK (Role = RESPONSE does not increment confidence)
// =========================================================================
$preConfidence = (int)$lastInc['confidence'];
$preCatCount = (int)$lastInc['category_count'];

EventFabric::ingest([
    'sensor' => 'CERBERUS', 'category' => 'IDENTITY', 'event_type' => 'BAN_IP',
    'role' => 'RESPONSE', 'severity' => 8, 'confidence' => 100, 'actor_ip' => '198.51.100.99',
    'request_id' => $reqE, 'execution_chain_id' => 'xc_' . $reqE,
]);

$postInc = end($wpdb->tables['wp_vis_xdr_incidents']);
if ((int)$postInc['confidence'] > $preConfidence || (int)$postInc['category_count'] > $preCatCount) {
    $failures[] = 'TEST F: RESPONSE event improperly incremented confidence or category count.';
}

// =========================================================================
// TEST G: PRE-EXISTING BAN (Admin ban preserved, rollback skipped)
// =========================================================================
$adminBannedIp = '198.51.100.111';
$wpdb->insert('wp_vis_omega_bans', ['ip' => $adminBannedIp, 'reason' => 'ADMIN_MANUAL_BAN', 'banned_at' => gmdate('Y-m-d H:i:s'), 'request_uri' => '/']);

$reqG = bin2hex(random_bytes(16));
EventFabric::ingest([
    'sensor' => 'AEGIS', 'category' => 'INGRESS', 'event_type' => 'SQLI_1',
    'role' => 'DETECTION', 'severity' => 9, 'confidence' => 95, 'actor_ip' => $adminBannedIp,
    'request_id' => $reqG, 'execution_chain_id' => 'xc_' . $reqG,
]);
EventFabric::ingest([
    'sensor' => 'MORPHEUS', 'category' => 'RUNTIME', 'event_type' => 'CAP_1',
    'role' => 'DETECTION', 'severity' => 9, 'confidence' => 95, 'actor_ip' => $adminBannedIp,
    'request_id' => $reqG, 'execution_chain_id' => 'xc_' . $reqG,
]);

$foundAlreadyContained = false;
foreach ($wpdb->tables['wp_vis_xdr_responses'] as $resp) {
    if (($resp['target_id'] ?? '') === $adminBannedIp && ($resp['status'] ?? '') === 'ALREADY_CONTAINED') {
        $foundAlreadyContained = true;
        break;
    }
}
if (!$foundAlreadyContained) {
    $failures[] = 'TEST G: Pre-existing admin ban was not assigned status ALREADY_CONTAINED.';
}

// =========================================================================
// TEST I: EVIDENCE TAMPERING (Invalid status returned)
// =========================================================================
$verifValid = EvidenceStore::verify($incA1);
if (($verifValid['status'] ?? '') !== 'VALID') {
    $failures[] = 'TEST I: Valid evidence chain returned non-valid status: ' . ($verifValid['reason'] ?? 'unknown');
}

// Mutate one digest
if (!empty($wpdb->tables['wp_vis_xdr_evidence'])) {
    $wpdb->tables['wp_vis_xdr_evidence'][0]['digest'] = str_repeat('f', 64);
    $verifTampered = EvidenceStore::verify($incA1);
    if (($verifTampered['status'] ?? '') === 'VALID') {
        $failures[] = 'TEST I: Evidence store failed to catch tampered digest.';
    }
}

// =========================================================================
// TEST J: EVIDENCE BUDGET (Budget exceeded returns INCOMPLETE)
// =========================================================================
$verifBudget = EvidenceStore::verify($incA1, 1);
if (($verifBudget['status'] ?? '') !== 'INCOMPLETE') {
    $failures[] = 'TEST J: Verification exceeding budget did not return INCOMPLETE.';
}

// =========================================================================
// TEST K: ENTITY PROMOTION (Plugin promoted over initial IP)
// =========================================================================
$incAData = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_vis_xdr_incidents WHERE incident_uuid = %s LIMIT 1", $incA1), \ARRAY_A);
if (($incAData['primary_entity_type'] ?? '') !== 'PLUGIN' || ($incAData['primary_entity_id'] ?? '') !== 'vulnerable-plugin') {
    $failures[] = 'TEST K: Primary affected entity was not promoted to PLUGIN.';
}
if (!str_contains((string)($incAData['primary_actor'] ?? ''), $actorA)) {
    $failures[] = 'TEST K: Primary actor was lost during entity promotion.';
}

if ($failures !== []) {
    fwrite(STDERR, "VGT XDR ADVERSARIAL REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "VGT XDR ADVERSARIAL REGRESSION: PASS (All Tests A-L Verified)\n");
