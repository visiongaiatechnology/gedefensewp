<?php
// STATUS: PLATIN
declare(strict_types=1);

$root = dirname(__DIR__);
$entrypoints = [
    $root . '/gedefense-wp.php',
    $root . '/0vision-integrity-sentinel.php',
    $root . '/vision-integrity-sentinel.php',
];
$entrypoint = null;
foreach ($entrypoints as $candidate) {
    if (is_file($candidate)) {
        $entrypoint = $candidate;
        break;
    }
}
if ($entrypoint === null) {
    fwrite(STDERR, "VGT INTEGRITY REGRESSION: FAILED\nEntrypoint unavailable.\n");
    exit(1);
}
$entrypointSource = file_get_contents($entrypoint);
if (!is_string($entrypointSource)
    || preg_match("/define\\('VIS_MANIFEST_DIGEST',\\s*'([a-f0-9]{64})'\\);/", $entrypointSource, $match) !== 1) {
    fwrite(STDERR, "VGT INTEGRITY REGRESSION: FAILED\nPinned manifest digest unavailable.\n");
    exit(1);
}

define('ABSPATH', $root . DIRECTORY_SEPARATOR);
define('VIS_PATH', str_replace('\\', '/', $root) . '/');
define('VIS_MANIFEST_DIGEST', $match[1]);

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }

require $root . '/includes/core/class-vis-module-integrity.php';

$failures = [];
$results = VIS_Module_Integrity::verify_all();
if (count($results) < 20) {
    $failures[] = 'Security component coverage is incomplete.';
}
foreach ($results as $id => $result) {
    if (($result['status'] ?? '') !== 'VERIFIED') {
        $failures[] = $id . ': ' . ($result['status'] ?? 'UNKNOWN');
    }
}

$manifest = file_get_contents($root . '/integrity/module-manifest.json');
if (!is_string($manifest) || !hash_equals(VIS_MANIFEST_DIGEST, hash('sha256', $manifest))) {
    $failures[] = 'Manifest trust anchor mismatch.';
}

if ($failures !== []) {
    fwrite(STDERR, "VGT INTEGRITY REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, 'VGT INTEGRITY REGRESSION: PASS (' . count($results) . " components)\n");
