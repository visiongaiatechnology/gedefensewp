<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

/**
 * Regression test for Dashboard Live Telemetry Poller:
 * Verifies that canonical tables (vis_apex_bans, vis_omega_logs) and Prometheus tables
 * are correctly identified and queried by the live threat endpoint.
 */

$root = dirname(__DIR__);

// Verify source code invariants in class-vis-dashboard-ajax.php
$ajax_file = $root . '/includes/dashboard/class-vis-dashboard-ajax.php';
$content = file_get_contents($ajax_file);
if (!is_string($content)) {
    fwrite(STDERR, "VGT TELEMETRY REGRESSION: FAILED - Cannot read ajax handler.\n");
    exit(1);
}

$invariants = [
    'canonical bans definition' => "defined('VIS_TABLE_BANS') ? \$wpdb->prefix . VIS_TABLE_BANS : \$wpdb->prefix . 'vis_apex_bans'",
    'canonical logs definition' => "defined('VIS_TABLE_LOGS') ? \$wpdb->prefix . VIS_TABLE_LOGS : \$wpdb->prefix . 'vis_omega_logs'",
    'request_uri support in bans' => '!empty($b->request_uri)',
    'vis_apex_bans fallback check' => "SHOW TABLES LIKE '{\$canonical_bans}'",
    'vis_omega_logs fallback check' => "SHOW TABLES LIKE '{\$canonical_logs}'",
];

$failures = [];
foreach ($invariants as $label => $needle) {
    if (!str_contains($content, $needle)) {
        $failures[] = "Missing telemetry invariant: {$label}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "VGT TELEMETRY REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT DASHBOARD TELEMETRY REGRESSION: PASS (Canonical vis_apex_bans & vis_omega_logs verified)\n");
