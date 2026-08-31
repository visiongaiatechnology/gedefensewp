<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vgt-resume-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700, true) && !is_dir($root)) exit(1);
define('ABSPATH', str_replace('\\', '/', $root) . '/');
$GLOBALS['vgt_test_options'] = [];

function wp_upload_dir(): array { return ['basedir' => ABSPATH . 'uploads']; }
function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
function current_time(string $type): string { return '2026-01-01 00:00:00'; }
function wp_json_encode(mixed $value, int $flags = 0): string|false { return json_encode($value, $flags); }
function wp_cache_delete(string $key, string $group = ''): bool { return true; }
function update_option(string $key, mixed $value, mixed $autoload = null): bool { $GLOBALS['vgt_test_options'][$key] = $value; return true; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['vgt_test_options'][$key] ?? $default; }

require dirname(__DIR__) . '/includes/scanner/class-vis-scanner-engine.php';

function cleanup_resume_root(string $root): void {
    $resolved = realpath($root);
    $temp = realpath(sys_get_temp_dir());
    if ($resolved === false || $temp === false || !str_starts_with($resolved, $temp . DIRECTORY_SEPARATOR . 'vgt-resume-')) {
        throw new RuntimeException('Unsafe resumption cleanup refused.');
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($resolved);
}

try {
    for ($i = 0; $i < 320; $i++) {
        $directory = ABSPATH . 'wp-content/plugins/module-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        if (!mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Fixture directory failed.');
        file_put_contents($directory . '/module.php', '<?php return ' . $i . ';', LOCK_EX);
    }
    file_put_contents(ABSPATH . 'index.php', '<?php return true;', LOCK_EX);

    $engine = new VIS_Scanner_Engine_Omega();
    $phase = 'init';
    $offset = 0;
    $resumed = false;
    $result = [];
    for ($guard = 0; $guard < 40; $guard++) {
        $result = $engine->run_scan_cycle($phase, $offset, 'scan');
        if (($result['phase'] ?? '') === 'index' && ($result['status'] ?? '') === 'processing') $resumed = true;
        if (!in_array($result['status'] ?? 'error', ['next_phase', 'processing'], true)) break;
        $phase = (string)($result['phase'] ?? $phase);
        $offset = (int)($result['offset'] ?? $offset);
    }
    if (!$resumed || ($result['status'] ?? '') !== 'init') throw new RuntimeException('Resumable indexing lifecycle failed.');
    $baseline = json_decode((string)file_get_contents(ABSPATH . 'uploads/vis-vault-omega/integrity_matrix.json'), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($baseline) || count($baseline) !== 321) throw new RuntimeException('Resumed baseline is incomplete.');

    echo "PASS: resumable indexing committed all 321 files without a partial baseline\n";
    cleanup_resume_root($root);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    try { cleanup_resume_root($root); } catch (Throwable) {}
    exit(1);
}
