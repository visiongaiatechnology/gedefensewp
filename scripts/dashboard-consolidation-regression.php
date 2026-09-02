<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [];
foreach ([
    'prometheus' => 'includes/modules/prometheus/class-vis-prometheus.php',
    'settings' => 'includes/dashboard/class-vis-dashboard-settings.php',
    'prometheus_view' => 'includes/dashboard/views/view-prometheus.php',
    'aegis_view' => 'includes/dashboard/views/view-aegis.php',
    'ajax' => 'includes/dashboard/class-vis-dashboard-ajax.php',
    'assets' => 'includes/dashboard/class-vis-dashboard-assets.php',
    'dashboard' => 'includes/dashboard/class-vis-dashboard-view.php',
    'security_center' => 'includes/dashboard/views/view-security_center.php',
    'notice' => 'includes/dashboard/class-vis-dashboard-core.php',
    'oracle_js' => 'assets/js/vis-oracle-diagnostics.js',
] as $key => $relative) $files[$key] = file_get_contents($root . '/' . $relative);

$failures = [];
$required = [
    ['prometheus', 'DEFAULT_EVENT_HORIZON_SCORE       = 200.0', 'Prometheus runtime default'],
    ['prometheus', "self::DEFAULT_EVENT_HORIZON_SCORE, 200.0, 1000.0", 'Prometheus runtime minimum'],
    ['settings', "'event_horizon_score' => [200.0, 200.0, 1000.0]", 'Prometheus stored minimum'],
    ['prometheus_view', 'min="200" max="1000"', 'Prometheus UI bound'],
    ['aegis_view', 'admin.php?page=vgt-suite&tab=vault', 'Oracle vault route'],
    ['ajax', "wp_ajax_vis_oracle_ping", 'Oracle authenticated endpoint'],
    ['ajax', "https://api.groq.com/openai/v1/models", 'Oracle fixed diagnostic origin'],
    ['ajax', "'sslverify' => true", 'Oracle TLS verification'],
    ['assets', 'vis-oracle-diagnostics.js', 'Oracle external diagnostics client'],
    ['security_center', "require __DIR__ . '/view-systatus.php'", 'Systemstatus consolidation'],
    ['security_center', "require __DIR__ . '/view-logs.php'", 'System log consolidation'],
    ['notice', "if (!get_option('vgt_setup_wizard_completed'))", 'Completed wizard notice boundary'],
];
foreach ($required as [$file, $needle, $label]) if (!is_string($files[$file]) || !str_contains($files[$file], $needle)) $failures[] = 'Missing ' . $label . '.';
if (is_string($files['dashboard']) && (str_contains($files['dashboard'], "'systatus'   =>") || str_contains($files['dashboard'], "'logs'       =>"))) $failures[] = 'Legacy standalone Systemstatus/System-Protokolle navigation remains.';
if (is_string($files['oracle_js']) && preg_match('/innerHTML\s*=/', $files['oracle_js']) === 1) $failures[] = 'Oracle diagnostics introduced a dynamic HTML sink.';

if ($failures !== []) {
    fwrite(STDERR, "VGT DASHBOARD CONSOLIDATION REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "VGT DASHBOARD CONSOLIDATION REGRESSION: PASS\n");
