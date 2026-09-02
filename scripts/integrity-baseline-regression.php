<?php
// STATUS: PLATIN
declare(strict_types=1);

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vgt-integrity-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700, true) && !is_dir($root)) {
    fwrite(STDERR, "FAIL: temporary root unavailable\n");
    exit(1);
}

define('ABSPATH', str_replace('\\', '/', $root) . '/');
$GLOBALS['vgt_test_options'] = [];

function wp_upload_dir(): array {
    return ['basedir' => ABSPATH . 'uploads'];
}
function wp_normalize_path(string $path): string {
    return str_replace('\\', '/', $path);
}
function current_time(string $type): string {
    return $type === 'mysql' ? '2026-01-01 00:00:00' : '';
}
function wp_json_encode(mixed $value, int $flags = 0): string|false {
    return json_encode($value, $flags);
}
function wp_cache_delete(string $key, string $group = ''): bool {
    return true;
}
function update_option(string $key, mixed $value, mixed $autoload = null): bool {
    $changed = !array_key_exists($key, $GLOBALS['vgt_test_options'])
        || $GLOBALS['vgt_test_options'][$key] !== $value;
    $GLOBALS['vgt_test_options'][$key] = $value;
    return $changed;
}
function get_option(string $key, mixed $default = false): mixed {
    return $GLOBALS['vgt_test_options'][$key] ?? $default;
}

require dirname(__DIR__) . '/includes/scanner/class-vis-scanner-engine.php';

function run_cycle(VIS_Scanner_Engine_Omega $engine, string $mode): array {
    $phase = 'init';
    $offset = 0;
    for ($guard = 0; $guard < 20; $guard++) {
        $result = $engine->run_scan_cycle($phase, $offset, $mode);
        $status = (string)($result['status'] ?? 'error');
        if (!in_array($status, ['next_phase', 'processing'], true)) {
            return $result;
        }
        $phase = (string)($result['phase'] ?? $phase);
        $offset = (int)($result['offset'] ?? $offset);
    }
    return ['status' => 'error', 'message' => 'cycle guard reached'];
}

function remove_test_root(string $path): void {
    $resolved = realpath($path);
    $temp = realpath(sys_get_temp_dir());
    if ($resolved === false || $temp === false || !str_starts_with($resolved, $temp . DIRECTORY_SEPARATOR . 'vgt-integrity-')) {
        throw new RuntimeException('Refusing unsafe regression cleanup.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($resolved);
}

try {
    mkdir(ABSPATH . 'wp-content', 0700, true);
    file_put_contents(ABSPATH . 'index.php', '<?php echo "A";', LOCK_EX);
    file_put_contents(ABSPATH . 'wp-content/plugin.php', '<?php echo "B";', LOCK_EX);

    $engine = new VIS_Scanner_Engine_Omega();
    $initial = run_cycle($engine, 'scan');
    if (($initial['status'] ?? '') !== 'init') {
        throw new RuntimeException('Initial baseline was not created.');
    }

    $target = ABSPATH . 'wp-content/plugin.php';
    $original_mtime = filemtime($target);
    file_put_contents($target, '<?php echo "C";', LOCK_EX);
    touch($target, $original_mtime);

    $warning = run_cycle($engine, 'scan');
    if (($warning['status'] ?? '') !== 'warning' || count($warning['changes'] ?? []) !== 1) {
        throw new RuntimeException('Content change with preserved metadata was not detected.');
    }

    $approved = run_cycle($engine, 'reindex');
    if (($approved['status'] ?? '') !== 'clean' || ($approved['changes'] ?? null) !== []) {
        throw new RuntimeException('Baseline approval did not invalidate findings.');
    }
    if ((get_option('vis_scan_report', [])['changes'] ?? null) !== []) {
        throw new RuntimeException('Persisted anomaly report remained stale.');
    }

    $manifestPath = ABSPATH . 'uploads/vis-vault-omega/integrity_matrix.json';
    $stableManifest = json_decode((string)file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
    $legacyManifest = [];
    foreach ($stableManifest as $path => $record) $legacyManifest['/legacy/wordpress-root/' . $path] = $record;
    file_put_contents($manifestPath, json_encode($legacyManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);

    $migrated = run_cycle($engine, 'scan');
    if (($migrated['status'] ?? '') !== 'clean' || ($migrated['changes'] ?? null) !== []) {
        throw new RuntimeException('Legacy absolute baseline paths produced synthetic deleted/new findings.');
    }

    $verified = run_cycle($engine, 'scan');
    if (($verified['status'] ?? '') !== 'clean' || ($verified['changes'] ?? null) !== []) {
        throw new RuntimeException('Approved baseline did not remain clean.');
    }

    echo "PASS: baseline hashes remain stable across approval and legacy root migration\n";
    remove_test_root($root);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    try {
        remove_test_root($root);
    } catch (Throwable) {
    }
    exit(1);
}
