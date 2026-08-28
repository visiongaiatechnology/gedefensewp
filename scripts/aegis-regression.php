<?php
// STATUS: PLATIN
declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
function get_option(string $key, mixed $default = false): mixed { return $default; }
function wp_cache_get(string $key, string $group = ''): false { return false; }
function wp_cache_set(string $key, mixed $value, string $group = '', int $ttl = 0): bool { return true; }
function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }

require dirname(__DIR__) . '/includes/modules/aegis/class-vis-aegis.php';
require dirname(__DIR__) . '/includes/modules/aegis/class-vis-aegis-oracle.php';

$failures = [];
$aegis = new VIS_Aegis(['aegis_enabled' => false]);
$normalize = new ReflectionMethod(VIS_Aegis::class, 'normalize_payload');

$evasion = '%2527%2520UN%2F%2Ahidden%2A%2FION%2520SELECT%25201--';
$normalized = $normalize->invoke($aegis, $evasion);
if (!is_string($normalized) || stripos($normalized, 'union') === false || stripos($normalized, 'select') === false) {
    $failures[] = 'Recursive URL/comment normalization failed.';
}

$schema = new ReflectionMethod(VIS_Aegis_Oracle::class, 'valid_schema');
$validBlock = ['violation' => 1, 'reasoning_channel' => 'Executable SQL structure.', 'category' => 'Database Attack', 'confidence' => 0.99];
$invalidStringVerdict = ['violation' => '1', 'reasoning_channel' => 'Type confusion.', 'category' => 'Database Attack'];
$invalidCategory = ['violation' => 1, 'reasoning_channel' => 'Unknown category.', 'category' => 'Arbitrary'];
$validSafe = ['violation' => 0, 'reasoning_channel' => 'Benign content.', 'category' => null];

if ($schema->invoke(null, $validBlock) !== true) $failures[] = 'Valid block schema rejected.';
if ($schema->invoke(null, $invalidStringVerdict) !== false) $failures[] = 'String verdict accepted.';
if ($schema->invoke(null, $invalidCategory) !== false) $failures[] = 'Unknown category accepted.';
if ($schema->invoke(null, $validSafe) !== true) $failures[] = 'Valid safe schema rejected.';

$fallback = VIS_Aegis_Oracle::judge("1' UNION SELECT password FROM users--");
if (($fallback['verdict'] ?? '') !== 'BLOCK' || ($fallback['source'] ?? '') !== 'deterministic_fallback') {
    $failures[] = 'Oracle dependency outage did not fail closed.';
}

if ($failures !== []) {
    fwrite(STDERR, "VGT AEGIS REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT AEGIS REGRESSION: PASS\n");
