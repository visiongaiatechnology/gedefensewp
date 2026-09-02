<?php
// STATUS: PLATIN
declare(strict_types=1);

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vgt-zeus-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700, true) && !is_dir($root)) {
    fwrite(STDERR, "VGT ZEUS ENV: FAILED\nTemporary root unavailable.\n");
    exit(1);
}
define('ABSPATH', $root . DIRECTORY_SEPARATOR);

require dirname(__DIR__) . '/includes/modules/zeus/src/class-zeus-env.php';

function remove_zeus_test_root(string $path): void {
    $resolved = realpath($path);
    $temp = realpath(sys_get_temp_dir());
    if ($resolved === false
        || $temp === false
        || !str_starts_with($resolved, $temp . DIRECTORY_SEPARATOR . 'vgt-zeus-')) {
        throw new RuntimeException('Unsafe Zeus regression cleanup rejected.');
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
    $destination = ABSPATH . '.user.ini';
    $original = "memory_limit = 256M\n";
    $replacement = "memory_limit = 256M\n\nauto_prepend_file = \"/vault/zeus-waf.php\"\n";
    file_put_contents($destination, $original, LOCK_EX);

    $env = new VIS_Zeus_Env(ABSPATH . 'vault' . DIRECTORY_SEPARATOR, '/vault/zeus-waf.php', []);
    $fallback = new ReflectionMethod($env, 'locked_replace_existing');
    $success = $fallback->invoke($env, $destination, $replacement, 0600);

    if ($success !== true || file_get_contents($destination) !== $replacement) {
        throw new RuntimeException('Locked hosting fallback did not persist exact bytes.');
    }
    if (!hash_equals(hash('sha256', $replacement), (string)hash_file('sha256', $destination))) {
        throw new RuntimeException('Locked hosting fallback read-back failed.');
    }

    $source = file_get_contents(
        dirname(__DIR__) . '/includes/modules/zeus/src/class-zeus-env.php'
    );
    foreach ([
        'if (!@rename($temporary, $destination))',
        'locked_replace_existing',
        "catch (StorageException \$e)",
        'Zeus user INI is managed by the hosting platform.',
    ] as $invariant) {
        if (!is_string($source) || !str_contains($source, $invariant)) {
            throw new RuntimeException('Zeus hosting invariant missing: ' . $invariant);
        }
    }

    @unlink(ABSPATH . '.user.ini');
if (defined('VIS_PATH')) @unlink(VIS_PATH . '.user.ini');
echo "VGT ZEUS ENV: PASS\n";
    remove_zeus_test_root($root);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "VGT ZEUS ENV: FAILED\n" . $e->getMessage() . "\n");
    try {
        remove_zeus_test_root($root);
    } catch (Throwable) {
    }
    exit(1);
}
