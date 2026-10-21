<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

$tempBase = realpath(sys_get_temp_dir());
if ($tempBase === false) {
    fwrite(STDERR, "VGT PHP UPLOAD-PROGRESS HARDENING: FAILED\nTemporary base unavailable.\n");
    exit(1);
}
$root = $tempBase . DIRECTORY_SEPARATOR . 'vgt-php-hardening-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700, true) && !is_dir($root)) {
    fwrite(STDERR, "VGT PHP UPLOAD-PROGRESS HARDENING: FAILED\nTemporary root unavailable.\n");
    exit(1);
}
define('ABSPATH', $root . DIRECTORY_SEPARATOR);

require dirname(__DIR__) . '/includes/modules/zeus/src/class-zeus-env.php';

function remove_php_hardening_root(string $path): void {
    $resolved = realpath($path);
    $temp = realpath(sys_get_temp_dir());
    if ($resolved === false
        || $temp === false
        || !str_starts_with($resolved, $temp . DIRECTORY_SEPARATOR . 'vgt-php-hardening-')) {
        throw new RuntimeException('Unsafe PHP hardening regression cleanup rejected.');
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

function php_hardening_read(string $path): string {
    $content = is_string($path) && is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        throw new RuntimeException('User INI is unreadable.');
    }
    return $content;
}

try {
    $ini = ABSPATH . '.user.ini';
    file_put_contents($ini, "memory_limit = 256M\n", LOCK_EX);

    $env = new VIS_Zeus_Env(ABSPATH . 'vault' . DIRECTORY_SEPARATOR, '/vault/zeus-waf.php', ['zeus_enabled' => true]);
    $sync = new ReflectionMethod($env, 'sync_user_ini');
    $sync->invoke($env);

    $content = php_hardening_read($ini);
    if (!str_contains($content, 'session.upload_progress.enabled = Off')) {
        throw new RuntimeException('The upload-progress directive was not written.');
    }
    if (!str_contains($content, 'auto_prepend_file = "/vault/zeus-waf.php"')) {
        throw new RuntimeException('The Zeus prespend directive was lost.');
    }
    if (!str_contains($content, 'memory_limit = 256M')) {
        throw new RuntimeException('Foreign .user.ini content was lost.');
    }
    if (substr_count($content, 'session.upload_progress.enabled = Off') !== 1) {
        throw new RuntimeException('The upload-progress directive is not written exactly once.');
    }
    if (substr_count($content, 'VGT PHP UPLOAD-PROGRESS HARDENING') !== 1) {
        throw new RuntimeException('The hardening marker is not written exactly once.');
    }

    // Idempotent: a second sync must not accumulate markers or directives.
    $before = hash_file('sha256', $ini);
    $sync->invoke($env);
    if (!hash_equals((string) $before, (string) hash_file('sha256', $ini))) {
        throw new RuntimeException('A repeated sync changed the file bytes.');
    }

    // The dedicated bootstrap write is idempotent as well and returns true when already hardened.
    if ($env->ensure_upload_progress_hardening() !== true) {
        throw new RuntimeException('The bootstrap hardening write did not confirm.');
    }
    if (!hash_equals((string) $before, (string) hash_file('sha256', $ini))) {
        throw new RuntimeException('The bootstrap hardening write changed an already hardened file.');
    }

    // With the WAF switched off the prespend is removed but the mitigation stays.
    $disabled = new VIS_Zeus_Env(ABSPATH . 'vault' . DIRECTORY_SEPARATOR, '/vault/zeus-waf.php', ['zeus_enabled' => false]);
    $syncDisabled = new ReflectionMethod($disabled, 'sync_user_ini');
    $syncDisabled->invoke($disabled);
    $disabledContent = php_hardening_read($ini);
    if (str_contains($disabledContent, 'auto_prepend_file')) {
        throw new RuntimeException('The disabled WAF configuration still prespends.');
    }
    if (!str_contains($disabledContent, 'session.upload_progress.enabled = Off')) {
        throw new RuntimeException('The mitigation disappeared when the WAF switch was off.');
    }

    // Pure classifier: every state must be reachable and unambiguous.
    $cases = [
        [true, true, true, false, 'protected'],
        [true, true, true, true, 'pending'],
        [false, true, false, true, 'blocked'],
        [false, false, true, true, 'unsupported'],
        [true, true, true, null, 'unverified'],
    ];
    foreach ($cases as [$configured, $userIni, $writable, $effective, $expected]) {
        $actual = VIS_Zeus_Env::classify_upload_progress_state($configured, $userIni, $writable, $effective);
        if ($actual !== $expected) {
            throw new RuntimeException(sprintf('Classifier expected %s, got %s.', $expected, $actual));
        }
    }

    // The live inspector must describe this installer without inventing a protected state.
    $status = VIS_Zeus_Env::upload_progress_hardening_status(ABSPATH);
    foreach (['state', 'configured', 'effective', 'user_ini', 'writable', 'path', 'detail'] as $key) {
        if (!array_key_exists($key, $status)) {
            throw new RuntimeException('Status field missing: ' . $key);
        }
    }
    if ($status['configured'] !== true) {
        throw new RuntimeException('The inspector does not see the written directive.');
    }
    if (!in_array($status['state'], ['pending', 'protected'], true)) {
        throw new RuntimeException('Unexpected inspector state for a written CLI fixture: ' . $status['state']);
    }

    // Static invariants the runtime and the dashboard surface depend on.
    $health = dirname(__DIR__) . '/includes/core/class-vis-security-health.php';
    $healthSource = is_readable($health) ? (string) file_get_contents($health) : '';
    if (!str_contains($healthSource, 'php_upload_progress_hardening')) {
        throw new RuntimeException('The security health harness does not expose the mitigation.');
    }

    @unlink($ini);
    echo "VGT PHP UPLOAD-PROGRESS HARDENING: PASS\n";
    remove_php_hardening_root($root);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "VGT PHP UPLOAD-PROGRESS HARDENING: FAILED\n" . $e->getMessage() . "\n");
    try {
        remove_php_hardening_root($root);
    } catch (Throwable) {
    }
    exit(1);
}
