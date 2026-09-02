<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . DIRECTORY_SEPARATOR);
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('AUTH_SALT')) define('AUTH_SALT', 'test-salt-secret-0123456789abcdef0123456789abcdef');

define('WP_CONTENT_DIR', sys_get_temp_dir() . '/vgt_test_content_' . md5($root));
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
@mkdir(WP_CONTENT_DIR . '/vgt-vault/morpheus', 0777, true);
@mkdir(WP_CONTENT_DIR . '/vgt-vault/zeus', 0777, true);
@mkdir(WP_PLUGIN_DIR, 0777, true);

if (!function_exists('add_action')) { function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): void {} }
if (!function_exists('add_filter')) { function add_filter(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): void {} }
if (!function_exists('did_action')) { function did_action(string $hook): int { return 1; } }
if (!function_exists('do_action')) { function do_action(string $hook, ...$args): void {} }
if (!function_exists('apply_filters')) { function apply_filters(string $hook, mixed $val, ...$args): mixed { return $val; } }
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
if (!function_exists('esc_url_raw')) { function esc_url_raw(string $url): string { return $url; } }
if (!function_exists('current_time')) { function current_time(string $type): string { return gmdate('Y-m-d H:i:s'); } }
if (!function_exists('get_transient')) { function get_transient(string $k): mixed { return false; } }
if (!function_exists('set_transient')) { function set_transient(string $k, mixed $v, int $e): bool { return true; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags(string $s): string { return strip_tags($s); } }

$GLOBALS['sim_cache'] = [];
function wp_cache_get(string $key, string $group = ''): mixed {
    return $GLOBALS['sim_cache'][$group . ':' . $key] ?? false;
}
function wp_cache_set(string $key, mixed $value, string $group = '', int $expire = 0): bool {
    $GLOBALS['sim_cache'][$group . ':' . $key] = $value;
    return true;
}
function wp_cache_delete(string $key, string $group = ''): bool {
    unset($GLOBALS['sim_cache'][$group . ':' . $key]);
    return true;
}

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

class XdrRealClassTestDatabase extends wpdb {
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
        if (str_starts_with($q, 'START TRANSACTION') || str_starts_with($q, 'COMMIT') || str_starts_with($q, 'ROLLBACK')) {
            return true;
        }
        if (str_starts_with($q, 'INSERT IGNORE INTO wp_vis_omega_bans')) {
            preg_match("/VALUES \('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']*)'\)/", $q, $m);
            if ($m) {
                foreach ($this->tables['wp_vis_omega_bans'] as $b) {
                    if ($b['ip'] === $m[1]) return true;
                }
                $this->insert_id++;
                $this->tables['wp_vis_omega_bans'][] = [
                    'id' => $this->insert_id,
                    'ip' => $m[1],
                    'reason' => $m[2],
                    'banned_at' => $m[3],
                    'request_uri' => $m[4]
                ];
            }
            return true;
        }
        if (str_starts_with($q, 'DELETE FROM wp_vis_omega_bans WHERE ip =')) {
            preg_match("/WHERE ip = '([^']+)'/", $q, $m);
            if ($m) {
                $target = $m[1];
                $this->tables['wp_vis_omega_bans'] = array_values(array_filter(
                    $this->tables['wp_vis_omega_bans'],
                    fn($row) => $row['ip'] !== $target
                ));
            }
            return true;
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
        foreach ($this->tables[$table] as &$row) {
            $match = true;
            foreach ($where as $wk => $wv) {
                if (!isset($row[$wk]) || (string)$row[$wk] !== (string)$wv) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                foreach ($data as $dk => $dv) {
                    $row[$dk] = $dv;
                }
                $updated++;
            }
        }
        return $updated;
    }

    public function prepare(string $query, ...$args): string {
        if (isset($args[0]) && is_array($args[0])) $args = $args[0];
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
            $query = preg_replace('/%[sdf]/', (string)$val, $query, 1);
        }
        return $query;
    }

    public function get_row(string $query, string $output = 'OBJECT'): mixed {
        if (str_contains($query, 'wp_vis_omega_bans WHERE ip =')) {
            preg_match("/WHERE ip = '([^']+)'/", $query, $m);
            $ip = $m[1] ?? '';
            foreach ($this->tables['wp_vis_omega_bans'] as $row) {
                if ($row['ip'] === $ip) return $output === 'ARRAY_A' ? $row : (object)$row;
            }
            return null;
        }
        if (str_contains($query, 'wp_vis_xdr_incidents WHERE incident_uuid =')) {
            preg_match("/WHERE incident_uuid = '([^']+)'/", $query, $m);
            $uuid = $m[1] ?? '';
            foreach ($this->tables['wp_vis_xdr_incidents'] as $row) {
                if ($row['incident_uuid'] === $uuid) return $output === 'ARRAY_A' ? $row : (object)$row;
            }
            return null;
        }
        if (str_contains($query, 'wp_vis_xdr_incidents WHERE execution_chain_id =')) {
            preg_match("/execution_chain_id = '([^']+)'/", $query, $m);
            $chain = $m[1] ?? '';
            foreach ($this->tables['wp_vis_xdr_incidents'] as $row) {
                if ($row['execution_chain_id'] === $chain) return $output === 'ARRAY_A' ? $row : (object)$row;
            }
            return null;
        }
        if (str_contains($query, 'wp_vis_xdr_incidents WHERE correlation_key =')) {
            preg_match("/correlation_key = '([^']+)'/", $query, $m);
            $key = $m[1] ?? '';
            foreach ($this->tables['wp_vis_xdr_incidents'] as $row) {
                if ($row['correlation_key'] === $key) return $output === 'ARRAY_A' ? $row : (object)$row;
            }
            return null;
        }
        if (str_contains($query, 'wp_vis_xdr_responses WHERE incident_uuid =')) {
            preg_match("/incident_uuid = '([^']+)' AND action_type = '([^']+)' AND target_type = '([^']+)' AND target_id = '([^']+)'/", $query, $m);
            if ($m) {
                foreach ($this->tables['wp_vis_xdr_responses'] as $row) {
                    if ($row['incident_uuid'] === $m[1] && $row['action_type'] === $m[2] && $row['target_type'] === $m[3] && $row['target_id'] === $m[4] && $row['status'] === 'APPLIED') {
                        return $output === 'ARRAY_A' ? $row : (object)$row;
                    }
                }
            }
            return null;
        }
        return null;
    }

    public function get_var(string $query): mixed {
        if (str_contains($query, 'wp_vis_xdr_responses')) {
            preg_match("/target_id = '([^']+)'/", $query, $m);
            $target_id = $m[1] ?? '';
            $now = gmdate('Y-m-d H:i:s');
            if (preg_match("/expires_at > '([^']+)'/", $query, $tm)) {
                $now = $tm[1];
            }
            foreach ($this->tables['wp_vis_xdr_responses'] as $row) {
                if ($row['target_id'] === $target_id && $row['status'] === 'APPLIED') {
                    if ($row['expires_at'] === null || $row['expires_at'] > $now) {
                        return $row['id'];
                    }
                }
            }
            return null;
        }
        if (str_contains($query, 'wp_vis_omega_bans')) {
            preg_match("/WHERE ip = '([^']+)'/", $query, $m);
            $ip = $m[1] ?? '';
            foreach ($this->tables['wp_vis_omega_bans'] as $row) {
                if ($row['ip'] === $ip) return $row['reason'];
            }
            return null;
        }
        return null;
    }

    public function get_results(string $query, string $output = 'OBJECT'): array {
        if (str_contains($query, 'wp_vis_xdr_responses WHERE owner = \'TRINITY_XDR\'')) {
            preg_match("/expires_at <= '([^']+)'/", $query, $m);
            $now = $m[1] ?? gmdate('Y-m-d H:i:s');
            $res = [];
            foreach ($this->tables['wp_vis_xdr_responses'] as $row) {
                if ($row['owner'] === 'TRINITY_XDR' && $row['status'] === 'APPLIED' && $row['expires_at'] !== null && $row['expires_at'] <= $now) {
                    $res[] = $output === 'ARRAY_A' ? $row : (object)$row;
                }
            }
            return $res;
        }
        if (str_contains($query, 'wp_vis_omega_bans')) {
            $res = [];
            foreach ($this->tables['wp_vis_omega_bans'] as $row) {
                $res[] = $output === 'ARRAY_A' ? $row : (object)$row;
            }
            return $res;
        }
        return [];
    }

    public function get_col(string $query): array {
        if (str_contains($query, 'wp_vis_omega_bans')) {
            return array_column($this->tables['wp_vis_omega_bans'], 'ip');
        }
        return [];
    }
}

global $wpdb;
$wpdb = new XdrRealClassTestDatabase();

// Load real production classes
require_once $root . '/includes/core/class-vis-security.php';
require_once $root . '/includes/xdr/class-xdr-request-context.php';
require_once $root . '/includes/xdr/class-xdr-redactor.php';
require_once $root . '/includes/xdr/class-xdr-event.php';
require_once $root . '/includes/xdr/class-xdr-policy-engine.php';
require_once $root . '/includes/xdr/class-xdr-evidence-store.php';
require_once $root . '/includes/xdr/class-xdr-response-engine.php';
require_once $root . '/includes/xdr/class-xdr-event-repository.php';
require_once $root . '/includes/xdr/class-xdr-incident-engine.php';
require_once $root . '/includes/xdr/class-xdr-event-fabric.php';
require_once $root . '/includes/core/class-vis-event-bus.php';
require_once $root . '/includes/modules/cerberus/class-vis-cerberus.php';
require_once $root . '/includes/modules/morpheus/src/class-morpheus-tracer.php';
require_once $root . '/includes/modules/morpheus/src/class-morpheus-path-jail.php';
require_once $root . '/includes/modules/morpheus/src/class-morpheus-ui.php';
require_once $root . '/includes/modules/morpheus/class-vis-morpheus.php';
require_once $root . '/includes/modules/styx/class-vis-styx.php';

use VisionGaia\GeDefense\Xdr\EventFabric;
use VisionGaia\GeDefense\Xdr\EventRepository;
use VisionGaia\GeDefense\Xdr\EvidenceStore;
use VisionGaia\GeDefense\Xdr\IncidentEngine;
use VisionGaia\GeDefense\Xdr\PolicyEngine;
use VisionGaia\GeDefense\Xdr\ResponseEngine;
use VisionGaia\GeDefense\Xdr\XdrEvent;
use VisionGaia\GeDefense\Modules\Morpheus\Morpheus;
use VisionGaia\GeDefense\Modules\Styx\Styx;

EventFabric::boot();
VIS_Event_Bus::init();

echo "===============================================================\n";
echo "TRINITY XDR FINAL RELEASE BLOCKERS REGRESSION SUITE\n";
echo "===============================================================\n";

// =========================================================================
// TEST 1: PRESERVE REAL COMPONENT IDENTITY (Morpheus -> EventBus -> XDR)
// =========================================================================
echo "\n--- TEST 1: PRESERVE REAL COMPONENT IDENTITY ---\n";
// Morpheus emits violation for "vulnerable-plugin"
VIS_Event_Bus::emit(
    'MORPHEUS',
    'PLUGIN_CAPABILITY_VIOLATION',
    'Unauthorized DB write outside whitelist',
    [
        'component_key' => 'vulnerable-plugin',
        'plugin' => 'vulnerable-plugin',
        'violation' => 'db_write',
        'details' => 'wp_options UPDATE attempted',
        'attribution_confidence' => 100,
        'category' => 'RUNTIME',
        'severity' => 9,
    ],
    9
);

// Secondary confirmation signal to satisfy multi-sensor containment threshold
VIS_Event_Bus::emit(
    'FILESYSTEM_GUARD',
    'UNAUTHORIZED_CODE_EXECUTION',
    'Executable script dropped in uploads by vulnerable-plugin',
    [
        'component_key' => 'vulnerable-plugin',
        'plugin' => 'vulnerable-plugin',
        'attribution_confidence' => 100,
        'category' => 'FILESYSTEM',
        'severity' => 9,
    ],
    9
);

// Verify Morpheus overlay was registered for "vulnerable-plugin" (NOT a hash!)
$overlays = get_option('vis_morpheus_xdr_overlays', []);
$foundComponentOverlay = false;
foreach ($overlays as $incId => $ov) {
    if (($ov['target_component'] ?? '') === 'vulnerable-plugin') {
        $foundComponentOverlay = true;
        break;
    }
}

// Verify Styx overlay was registered for "vulnerable-plugin"
$styxOverlays = get_option('vis_styx_xdr_overlays', []);
$foundStyxOverlay = false;
foreach ($styxOverlays as $incId => $ov) {
    if (($ov['target_component'] ?? '') === 'vulnerable-plugin') {
        $foundStyxOverlay = true;
        break;
    }
}

// Check Responses table
$responses = $wpdb->tables['wp_vis_xdr_responses'];
$foundPluginResponse = false;
foreach ($responses as $resp) {
    if ($resp['target_type'] === 'PLUGIN' && $resp['target_id'] === 'vulnerable-plugin' && $resp['status'] === 'APPLIED') {
        $foundPluginResponse = true;
        break;
    }
}

if ($foundComponentOverlay && $foundStyxOverlay && $foundPluginResponse) {
    echo "[PASS] Fix 1: Morpheus emitted 'vulnerable-plugin' -> preserved component_key -> Morpheus/Styx overlays targeted 'vulnerable-plugin' directly (NO hash)\n";
    $fix1_pass = true;
} else {
    echo "[FAIL] Fix 1: Component identity not preserved! Morpheus=" . ($foundComponentOverlay ? 'OK' : 'MISSING') . " Styx=" . ($foundStyxOverlay ? 'OK' : 'MISSING') . " Response=" . ($foundPluginResponse ? 'OK' : 'MISSING') . "\n";
    $fix1_pass = false;
}

// =========================================================================
// TEST 2: HARD SEMANTIC CERBERUS XDR TTL ENFORCEMENT
// =========================================================================
echo "\n--- TEST 2: HARD SEMANTIC CERBERUS XDR TTL ENFORCEMENT ---\n";
$cerberus = VIS_Cerberus::instance();
$testIp = '198.51.100.42';
$adminBannedIp = '198.51.100.99';

// 1. Create an administrator permanent ban on 198.51.100.99
$cerberus->ban_ip($adminBannedIp, 'Manual administrator permanent ban');

// 2. Create an XDR IP restriction on 198.51.100.42 with 15-minute TTL at T0
$t0 = time();
$incidentId = bin2hex(random_bytes(16));
$cerberus->ban_ip($testIp, 'TRINITY_XDR:' . $incidentId);

$wpdb->tables['wp_vis_xdr_responses'][] = [
    'id' => 999,
    'response_uuid' => bin2hex(random_bytes(16)),
    'incident_uuid' => $incidentId,
    'owner' => 'TRINITY_XDR',
    'action_type' => 'CONTAIN_IP',
    'target_type' => 'IP',
    'target_id' => $testIp,
    'reason_code' => 'TEST_CONTAINMENT',
    'confidence' => 95,
    'authorized_by' => 'XDR_POLICY',
    'started_at' => gmdate('Y-m-d H:i:s', $t0),
    'expires_at' => gmdate('Y-m-d H:i:s', $t0 + 900), // 15 min TTL
    'status' => 'APPLIED',
    'rollback_json' => '[]',
    'evidence_ref' => '',
];

// Test T+14 (at 14 minutes): must be blocked!
$blocked_at_14 = $cerberus->is_ip_banned($testIp);

// Export firewall rules at T+14: verify temporary XDR ban is excluded from static edge files
$cerberus->sync_os_firewall_rules();
$vault_dir = defined('VIS_VAULT_DIR') ? VIS_VAULT_DIR . '/zeus' : WP_CONTENT_DIR . '/vgt-vault/zeus';
$nginx_14 = (string)@file_get_contents($vault_dir . '/nginx_deny.conf');
$temporary_ban_excluded_from_static_edge = !str_contains($nginx_14, "deny {$testIp};");

// Now simulate T+16 WITHOUT running WP-Cron by setting expires_at to 1 minute in the past
foreach ($wpdb->tables['wp_vis_xdr_responses'] as &$r) {
    if ($r['target_id'] === $testIp) {
        $r['expires_at'] = gmdate('Y-m-d H:i:s', time() - 60);
    }
}
unset($r);

// Test T+16: must NOT be blocked (Hard Semantic TTL check in PHP without Cron!)
$blocked_at_16 = $cerberus->is_ip_banned($testIp);

// Verify admin permanent ban STILL blocks at T+16
$admin_still_blocked = $cerberus->is_ip_banned($adminBannedIp);

// Export firewall rules at T+16: MUST contain adminBannedIp and NOT contain testIp
$cerberus->sync_os_firewall_rules();
$nginx_16 = (string)@file_get_contents($vault_dir . '/nginx_deny.conf');
$exported_at_16 = str_contains($nginx_16, "deny {$testIp};");
$admin_exported_at_16 = str_contains($nginx_16, "deny {$adminBannedIp};");

if ($blocked_at_14 && $temporary_ban_excluded_from_static_edge && !$blocked_at_16 && $admin_still_blocked && $admin_exported_at_16) {
    echo "[PASS] Fix 2: T0 ban active -> T+14 blocked in PHP & excluded from static edge -> T+16 unblocked in PHP (WITHOUT Cron). Admin ban permanently exported.\n";
    $fix2_pass = true;
} else {
    echo "[FAIL] Fix 2: TTL Invariant violated! T14_blocked=" . ($blocked_at_14 ? '1' : '0') . " T14_edge_excluded=" . ($temporary_ban_excluded_from_static_edge ? '1' : '0') . " T16_blocked=" . ($blocked_at_16 ? '1' : '0') . " Admin_blocked=" . ($admin_still_blocked ? '1' : '0') . " Admin_exported=" . ($admin_exported_at_16 ? '1' : '0') . "\n";
    $fix2_pass = false;
}

// =========================================================================
// TEST 3: RESPONSE IDEMPOTENCY + RESPONSE-OWNED STATE & ROLLBACK
// =========================================================================
echo "\n--- TEST 3: RESPONSE IDEMPOTENCY + OWNERSHIP ---\n";
$incidentX = bin2hex(random_bytes(16));
$targetPlugin = 'payment-gateway';

// Clear responses for clean test
$wpdb->tables['wp_vis_xdr_responses'] = [];
$wpdb->tables['wp_vis_xdr_incidents'] = [];

// Create incident X with high confidence
$now1200 = gmdate('Y-m-d H:i:s', time());
$story1 = [
    [
        'timestamp' => $now1200,
        'sensor' => 'MORPHEUS',
        'category' => 'RUNTIME',
        'event_type' => 'PLUGIN_VIOLATION',
        'role' => 'DETECTION',
        'entity_type' => 'PLUGIN',
        'entity_id' => 'plugin:' . hash('sha256', $targetPlugin),
        'component_key' => $targetPlugin,
        'actor' => 'ip:203.0.113.50',
        'severity' => 9,
        'confidence' => 90,
        'causal_edge' => 'SAME_REQUEST',
        'causal_parent_id' => null,
        'event_uuid' => bin2hex(random_bytes(16)),
    ]
];

$wpdb->tables['wp_vis_xdr_incidents'][] = [
    'id' => 101,
    'incident_uuid' => $incidentX,
    'correlation_key' => hash('sha256', 'test_key'),
    'execution_chain_id' => bin2hex(random_bytes(16)),
    'created_at' => $now1200,
    'updated_at' => $now1200,
    'status' => 'OPEN',
    'classification' => 'RUNTIME_COMPROMISE',
    'severity' => 9,
    'confidence' => 95,
    'primary_actor' => 'ip:203.0.113.50',
    'primary_entity_type' => 'PLUGIN',
    'primary_entity_id' => 'plugin:' . hash('sha256', $targetPlugin),
    'event_count' => 1,
    'sensor_count' => 2,
    'category_count' => 2,
    'sensor_set' => '["MORPHEUS","STYX"]',
    'category_set' => '["RUNTIME","EGRESS"]',
    'response_state' => 'OBSERVE',
    'evidence_root' => '',
    'attack_story' => wp_json_encode($story1),
    'related_entities' => '[]',
    'resolution' => '',
];

// 1. Initial Evaluation at 12:00
ResponseEngine::evaluate($incidentX);
$count_at_1200 = count($wpdb->tables['wp_vis_xdr_responses']);
$resp_ip_1 = null;
$resp_plugin_1 = null;
foreach ($wpdb->tables['wp_vis_xdr_responses'] as $r) {
    if ($r['target_type'] === 'IP') $resp_ip_1 = $r;
    if ($r['target_type'] === 'PLUGIN' && $r['action_type'] === 'RESTRICT_CAPABILITIES') $resp_plugin_1 = $r;
}

// Verify deterministic stable UUID
$expected_plugin_uuid = substr(hash('sha256', implode('|', [$incidentX, 'RESTRICT_CAPABILITIES', 'PLUGIN', $targetPlugin])), 0, 32);
$stable_id_ok = ($resp_plugin_1['response_uuid'] ?? '') === $expected_plugin_uuid;

// 2. Second Evaluation at 12:03 (Same incident receives new signal)
ResponseEngine::evaluate($incidentX);
$count_at_1203 = count($wpdb->tables['wp_vis_xdr_responses']);

// Verify NO duplicate responses created!
$no_duplicate_ok = ($count_at_1200 === $count_at_1203);

// Verify Morpheus overlay ownership
$overlays = get_option('vis_morpheus_xdr_overlays', []);
$overlay_owned = isset($overlays[$incidentX]) && ($overlays[$incidentX]['response_id'] ?? '') === $expected_plugin_uuid;

// 3. Rollback test: Expired old response should NOT remove active extended overlay
$staleIncident = bin2hex(random_bytes(16));
$staleRespId = bin2hex(random_bytes(16));
$wpdb->tables['wp_vis_xdr_responses'][] = [
    'id' => 888,
    'response_uuid' => $staleRespId,
    'incident_uuid' => $staleIncident,
    'owner' => 'TRINITY_XDR',
    'action_type' => 'RESTRICT_CAPABILITIES',
    'target_type' => 'PLUGIN',
    'target_id' => $targetPlugin,
    'reason_code' => 'STALE_TEST',
    'confidence' => 80,
    'authorized_by' => 'XDR_POLICY',
    'started_at' => gmdate('Y-m-d H:i:s', time() - 3600),
    'expires_at' => gmdate('Y-m-d H:i:s', time() - 1800), // Expired 30 min ago
    'status' => 'APPLIED',
    'rollback_json' => wp_json_encode(['target_plugin' => $targetPlugin]),
    'evidence_ref' => '',
];

ResponseEngine::rollbackExpired();

// Verify active overlay for incidentX was PRESERVED because it is active & owned by expected_plugin_uuid
$overlays_after_rollback = get_option('vis_morpheus_xdr_overlays', []);
$active_overlay_preserved = isset($overlays_after_rollback[$incidentX]);

// Verify stale response was rolled back
$stale_row = null;
foreach ($wpdb->tables['wp_vis_xdr_responses'] as $r) {
    if ($r['id'] === 888) $stale_row = $r;
}
$stale_rolled_back = ($stale_row['status'] ?? '') === 'ROLLED_BACK';

if ($stable_id_ok && $no_duplicate_ok && $overlay_owned && $active_overlay_preserved && $stale_rolled_back) {
    echo "[PASS] Fix 3: Response UUID is stable & deterministic, 12:03 signal reused/extended response without duplicates, overlay is owned and protected from stale rollback.\n";
    $fix3_pass = true;
} else {
    echo "[FAIL] Fix 3: Idempotency or ownership failed! Stable=" . ($stable_id_ok ? '1' : '0') . " NoDup=" . ($no_duplicate_ok ? '1' : '0') . " Owned=" . ($overlay_owned ? '1' : '0') . " Preserved=" . ($active_overlay_preserved ? '1' : '0') . " StaleRolledBack=" . ($stale_rolled_back ? '1' : '0') . "\n";
    $fix3_pass = false;
}

echo "\n===============================================================\n";
if ($fix1_pass && $fix2_pass && $fix3_pass) {
    echo "ALL THREE FINAL RELEASE BLOCKERS VERIFIED PASS (100%)\n";
    exit(0);
} else {
    echo "ONE OR MORE RELEASE BLOCKERS FAILED\n";
    exit(1);
}
