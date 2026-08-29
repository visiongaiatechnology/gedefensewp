<?php
// STATUS: PLATIN
declare(strict_types=1);

$root = dirname(__DIR__);
$optionalBuilder = $root . '/includes/builder/builder.php';
if (!is_file($optionalBuilder)) {
    fwrite(STDOUT, "VGT BUILDER LIVE PREVIEW: SKIP (optional Open-Core module not installed)\n");
    exit(0);
}
$engine = file_get_contents($root . '/includes/builder/assets/js/vgt-engine.js');
$admin = file_get_contents($root . '/includes/builder/assets/vgt-admin.js');
$view = file_get_contents($root . '/includes/builder/views/editor-ui.php');
$vault = file_get_contents($root . '/class-vis-vault.php');
$builder = file_get_contents($optionalBuilder);

$failures = [];
foreach ([
    'engine source' => $engine,
    'admin source'  => $admin,
    'preview view'  => $view,
    'vault source'  => $vault,
    'builder source' => $builder,
] as $label => $source) {
    if (!is_string($source)) {
        $failures[] = $label . ' unavailable';
    }
}

if (is_string($view)
    && (!str_contains($view, 'sandbox="allow-scripts"')
        || str_contains($view, 'allow-same-origin'))) {
    $failures[] = 'preview iframe lost its opaque-origin sandbox';
}
if (is_string($engine)) {
    if (!str_contains($engine, "'X-VGT-Admin-Token': adminToken")) {
        $failures[] = 'preview request admin-token header missing';
    }
    if (!str_contains($engine, 'vgt_admin_token: adminToken')) {
        $failures[] = 'preview request token compatibility field missing';
    }
    if (!str_contains($engine, "event.source !== DOM.iframe.contentWindow")) {
        $failures[] = 'preview message source identity gate missing';
    }
    if (preg_match(
        '/DOM\.iframe\.contentWindow\.postMessage\([^;]+window\.location\.origin\)/s',
        $engine
    ) === 1) {
        $failures[] = 'opaque preview still receives a concrete target origin';
    }
}
if (is_string($admin)
    && preg_match(
        '/DOM\.iframe\.contentWindow\.postMessage\([^;]+window\.location\.origin\)/s',
        $admin
    ) === 1) {
    $failures[] = 'admin bridge still targets a concrete iframe origin';
}
if (is_string($vault) && !str_contains($vault, '2 * HOUR_IN_SECONDS')) {
    $failures[] = 'builder-bound admin token lifetime regressed';
}
if (is_string($builder) && !str_contains($builder, "VGT_BUILDER_VERSION', '3.0.1")) {
    $failures[] = 'builder cache-busting version regressed';
}
if (is_string($engine) && !str_contains($engine, "./vgt-core.js?v=3.0.1")) {
    $failures[] = 'engine dependency cache-buster missing';
}
if (is_string($admin)
    && (!str_contains($admin, "./js/vgt-engine.js?v=3.0.1")
        || !str_contains($admin, "./js/vgt-core.js?v=3.0.1"))) {
    $failures[] = 'admin module dependency cache-buster missing';
}

if ($failures !== []) {
    fwrite(STDERR, "VGT BUILDER LIVE PREVIEW: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT BUILDER LIVE PREVIEW: PASS\n");
