<?php
// STATUS: PLATIN
declare(strict_types=1);

$workspace = dirname(__DIR__);
$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vgt-morpheus-regression-' . bin2hex(random_bytes(8));
if (!mkdir($testRoot, 0700, true) && !is_dir($testRoot)) {
    throw new RuntimeException('Regression sandbox creation failed.');
}

define('ABSPATH', $workspace . DIRECTORY_SEPARATOR);
define('WP_CONTENT_DIR', $testRoot);

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

function wp_unslash(string $value): string { return stripslashes($value); }
function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }

require $workspace . '/includes/modules/morpheus/src/class-morpheus-path-jail.php';
require $workspace . '/includes/modules/morpheus/class-vis-morpheus.php';

use VGT\Sentinel\Modules\Morpheus\Morpheus_Path_Jail;
use VGT\Sentinel\Modules\Morpheus\Vis_Morpheus;

$failures = [];

foreach (['../wp-config', '..', 'plugin/name', "plugin\0name", '<script>'] as $candidate) {
    try {
        Morpheus_Path_Jail::validate_slug($candidate);
        $failures[] = 'Rejected plugin identifier accepted: ' . bin2hex($candidate);
    } catch (SecurityException $e) {
        // Expected.
    }
}

$slug = Morpheus_Path_Jail::validate_slug('safe-plugin_1');
if ($slug !== 'safe-plugin_1') {
    $failures[] = 'Valid plugin identifier changed.';
}

$artifact = Morpheus_Path_Jail::file('audit', $slug, '.log');
$auditRoot = realpath(Morpheus_Path_Jail::directory('audit'));
if ($auditRoot === false || !str_starts_with($artifact, wp_normalize_path($auditRoot) . '/')) {
    $failures[] = 'Artifact escaped audit jail.';
}

$validMatrix = [
    'network' => ['api.example.com'],
    'db_write' => ['wp_vgt_plugin_'],
    'options' => ['vgt_plugin_'],
];
$invalidMatrices = [
    ['network' => ['127.0.0.1'], 'db_write' => [], 'options' => []],
    ['network' => [], 'db_write' => ['wp_users'], 'options' => []],
    ['network' => [], 'db_write' => [], 'options' => ['wp_options']],
    ['network' => [], 'db_write' => [], 'options' => [], 'extra' => []],
];
if (!Vis_Morpheus::validate_matrix($validMatrix)) {
    $failures[] = 'Valid permission matrix rejected.';
}
foreach ($invalidMatrices as $matrix) {
    if (Vis_Morpheus::validate_matrix($matrix)) {
        $failures[] = 'Unsafe permission matrix accepted.';
    }
}

$script = file_get_contents($workspace . '/includes/dashboard/views/morpheus/script.js');
if (!is_string($script)
    || str_contains($script, 'innerHTML +=')
    || str_contains($script, '${data.data}')) {
    $failures[] = 'Morpheus dashboard contains a dynamic HTML sink.';
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $entry) {
    if (!$entry instanceof SplFileInfo) {
        continue;
    }
    $path = $entry->getPathname();
    if (!str_starts_with(realpath(dirname($path)) ?: '', realpath($testRoot) ?: $testRoot)) {
        throw new RuntimeException('Regression cleanup boundary rejected.');
    }
    $entry->isDir() ? rmdir($path) : unlink($path);
}
rmdir($testRoot);

if ($failures !== []) {
    fwrite(STDERR, "VGT MORPHEUS REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT MORPHEUS REGRESSION: PASS\n");
