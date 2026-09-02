<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $value = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    return is_string($value) ? $value : '';
};

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$activeTabs = [
    'overview', 'thread', 'oracle', 'integrity', 'security_center', 'trinity', 'zeus', 'aegis',
    'prometheus', 'cerberus', 'airlock', 'nemesis', 'ghost_trap', 'hades', 'morpheus', 'titan',
    'kernel', 'styx', 'chronos', 'vlp', 'filesystem', 'vault', 'throneguard', 'loginpager',
    'downloads', 'modules', 'setup_wizard',
];
$configTabs = ['trinity', 'aegis', 'titan', 'hades', 'zeus', 'prometheus', 'nemesis', 'styx', 'loginpager', 'chronos', 'modules', 'setup_wizard', 'airlock', 'ghost_trap'];
$activeScripts = ['cerberus', 'gorgon', 'integrity', 'kernel', 'modules', 'morpheus', 'nemesis', 'overview', 'prometheus', 'setup_wizard', 'styx', 'thread', 'throneguard', 'vlp'];

$assets = $read('includes/dashboard/class-vis-dashboard-assets.php');
$shell = $read('includes/dashboard/class-vis-dashboard-view.php');
$settings = $read('includes/dashboard/class-vis-dashboard-settings.php');
$modern = $read('assets/css/vis-dashboard-modern.css');

$expect(str_contains($assets, 'vis-dashboard-modern.css'), 'Final design system is not enqueued.');
$expect(str_contains($assets, "'/^[a-z0-9_]{1,32}$/D'"), 'View asset path allowlist is missing.');
$expect(str_contains($modern, '@media (max-width: 960px)'), 'Responsive navigation breakpoint is missing.');
$expect(str_contains($modern, ':focus-visible'), 'Keyboard focus states are missing.');
$expect(str_contains($modern, 'prefers-reduced-motion'), 'Reduced-motion support is missing.');
$expect(!preg_match('/@import|url\(["\']?https?:\/\//i', $modern), 'Modern design system contains an external dependency.');

foreach ($activeTabs as $tab) {
    $view = $read('includes/dashboard/views/view-' . $tab . '.php');
    $expect($view !== '', 'Missing active view: ' . $tab . '.');
    $expect(!str_contains($view, '<style'), 'Inline CSS remains in active view: ' . $tab . '.');
    $withoutJson = preg_replace('#<script\s+type="application/json"[^>]*>.*?</script>#si', '', $view) ?? $view;
    $expect(!str_contains($withoutJson, '<script'), 'Executable inline JavaScript remains in active view: ' . $tab . '.');
    $expect(preg_match('/\son(?:click|submit|change|input|focus)\s*=/i', $view) !== 1, 'Inline event handler remains in active view: ' . $tab . '.');
    if (in_array($tab, $configTabs, true)) {
        $expect(preg_match('/<form\b/i', $view) !== 1, 'Nested form risk in shell-managed config view: ' . $tab . '.');
    }
}

foreach ($activeScripts as $tab) {
    $script = $read('includes/dashboard/views/' . $tab . '/script.js');
    $expect($script !== '', 'Missing script for active controller: ' . $tab . '.');
    $expect(!str_contains($script, '<?php'), 'PHP template code remains in static JavaScript: ' . $tab . '.');
    $expect(preg_match('/innerHTML|insertAdjacentHTML|outerHTML|document\.write|\.html\s*\(/', $script) !== 1, 'Unsafe HTML sink in controller: ' . $tab . '.');
    $expect(str_contains($assets, "'" . $tab . "'"), 'Controller is not connected to asset loader: ' . $tab . '.');
}

$expect(preg_match('/innerHTML|insertAdjacentHTML|outerHTML|document\.write|\.html\s*\(/', $read('assets/js/vis-dashboard.js')) !== 1, 'Unsafe HTML sink in global dashboard controller.');
$expect(!preg_match('/(?:cdn\.|jsdelivr|unpkg|googleapis|bootstrapcdn)/i', $assets . $modern), 'CDN dependency detected.');
$expect(str_contains($settings, "'aegis'      => ['aegis_enabled', 'aegis_mode', 'aegis_whitelist_ips', 'aegis_whitelist_uas']"), 'AEGIS controls are not fully persisted.');
$expect(str_contains($settings, "'chronos_email_body'"), 'Chronos email body is not persisted.');
$expect(str_contains($settings, 'array_intersect_key($new_sanitized'), 'Submitted config is not constrained to its module scope.');
$expect(str_contains($read('includes/dashboard/views/view-setup_wizard.php'), "VIS_Security::client_ip()"), 'Setup wizard does not use the canonical client IP resolver.');
$expect(str_contains($read('includes/dashboard/class-vis-dashboard-ajax.php'), 'wp_ajax_vis_upload_addon'), 'Add-on upload controller is disconnected.');
$expect(str_contains($read('includes/dashboard/class-vis-dashboard-ajax.php'), 'wp_ajax_vis_uninstall_addon'), 'Add-on uninstall controller is disconnected.');
$expect(str_contains($read('includes/dashboard/class-vis-dashboard-ajax.php'), 'wp_ajax_vis_inspect_file'), 'Integrity inspection controller is disconnected.');
$expect(str_contains($read('includes/dashboard/class-vis-dashboard-ajax.php'), 'wp_ajax_vis_oracle_ping'), 'Oracle diagnostic controller is disconnected.');

if ($failures !== []) {
    fwrite(STDERR, "VGT DASHBOARD UI REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT DASHBOARD UI REGRESSION: PASS\n");
