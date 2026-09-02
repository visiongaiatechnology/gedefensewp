<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vgt-ghost-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700, true) && !is_dir($root)) exit(1);
define('ABSPATH', str_replace('\\', '/', $root) . '/');
$GLOBALS['vgt_test_options'] = [];

function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['vgt_test_options'][$key] ?? $default; }
function add_option(string $key, mixed $value, string $deprecated = '', bool $autoload = true): bool {
    if (array_key_exists($key, $GLOBALS['vgt_test_options'])) return false;
    $GLOBALS['vgt_test_options'][$key] = $value;
    return true;
}

require dirname(__DIR__) . '/includes/modules/trap/src/class-ghost-trap-authenticator.php';

try {
    $authenticator = new VIS_Ghost_Trap_Authenticator();
    $issued = $authenticator->issue('signed-decoy.php', "http_response_code(404);\nexit;\n");
    $path = ABSPATH . 'signed-decoy.php';
    file_put_contents($path, $issued['payload'], LOCK_EX);
    $GLOBALS['vgt_test_options'][VIS_Ghost_Trap_Authenticator::MANIFEST_KEY] = ['signed-decoy.php' => $issued['record']];
    if (!$authenticator->isRegisteredTrap($path)) throw new RuntimeException('Authentic trap was rejected.');

    file_put_contents($path, $issued['payload'] . "\n// mutation", LOCK_EX);
    if ($authenticator->isRegisteredTrap($path)) throw new RuntimeException('Modified trap was trusted.');

    file_put_contents($path, $issued['payload'], LOCK_EX);
    $GLOBALS['vgt_test_options'][VIS_Ghost_Trap_Authenticator::MANIFEST_KEY] = [];
    if ($authenticator->isRegisteredTrap($path)) throw new RuntimeException('Unregistered trap was trusted.');

    unlink($path);
    rmdir($root);
    echo "PASS: Ghost Trap requires manifest registration, body hash and valid HMAC\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    if (is_file(ABSPATH . 'signed-decoy.php')) unlink(ABSPATH . 'signed-decoy.php');
    if (is_dir($root)) rmdir($root);
    exit(1);
}
