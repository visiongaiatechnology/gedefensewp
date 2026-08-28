<?php
// STATUS: PLATIN
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path(string $path): string {
        return str_replace('\\', '/', $path);
    }
}

require_once dirname(__DIR__) . '/includes/modules/hades/class-vis-hades.php';

$config = [
    'hades_enabled'     => 1,
    'hades_map_uploads' => 'private/assets',
];

$expected = 'https://example.test/private/assets/vgt-shadow-net/assets/tailwind-jit.js';
$actual = VIS_Hades::rewrite_uploads_url(
    'https://example.test/wp-content/uploads/vgt-shadow-net/assets/tailwind-jit.js',
    $config
);

if (VIS_Hades::uploads_alias($config) !== 'private/assets' || !hash_equals($expected, $actual)) {
    fwrite(STDERR, "VGT HADES/SHADOW-NET REGRESSION: FAILED\n");
    exit(1);
}

fwrite(STDOUT, "VGT HADES/SHADOW-NET REGRESSION: PASS\n");
