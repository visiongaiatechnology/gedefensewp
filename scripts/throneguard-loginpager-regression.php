<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
$sources = [
    'throne' => file_get_contents($root . '/includes/modules/throneguard/class-vis-throne-guard.php'),
    'login' => file_get_contents($root . '/includes/modules/loginpager/class-vis-loginpager.php'),
    'bootstrap' => file_get_contents($root . '/class-vis-bootstrapper.php'),
    'dashboard' => file_get_contents($root . '/includes/dashboard/class-vis-dashboard-view.php'),
    'settings' => file_get_contents($root . '/includes/dashboard/class-vis-dashboard-settings.php'),
    'wizard' => file_get_contents($root . '/includes/dashboard/views/view-setup_wizard.php'),
];
foreach ($sources as $name => $source) {
    if (!is_string($source)) throw new RuntimeException('Regression source unavailable: ' . $name);
}

$required = [
    'throne' => ['final class VIS_Throne_Guard', 'mcp_master_access', 'password_hash(', 'password_verify(', 'hash_equals(', 'rest_authentication_errors', 'apply_administrator_policy'],
    'login' => ['final class VIS_LoginPager', 'login_enqueue_scripts', 'esc_url_raw(', 'wp_add_inline_style('],
    'bootstrap' => ["if (class_exists('VIS_Throne_Guard')) VIS_Throne_Guard::get_instance();", 'VIS_LoginPager'],
    'dashboard' => ["'throneguard'", "'loginpager'"],
    'settings' => ['loginpager_bg_color', 'loginpager_bg_image', 'provision_current_master'],
    'wizard' => ['vis_config[throneguard_enabled]', 'vis_config[loginpager_enabled]'],
];
$failures = [];
foreach ($required as $name => $needles) {
    foreach ($needles as $needle) if (!str_contains($sources[$name], $needle)) $failures[] = "Missing {$name} invariant: {$needle}";
}
foreach (['Content-Security-Policy', 'pre_update_option_active_plugins', "\$_FILES"] as $forbidden) {
    if (str_contains($sources['throne'], $forbidden)) $failures[] = 'ThroneGuard duplicates another GeDefense domain: ' . $forbidden;
}
if ($failures !== []) {
    fwrite(STDERR, "VGT THRONEGUARD/LOGINPAGER REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "VGT THRONEGUARD/LOGINPAGER REGRESSION: PASS\n");
