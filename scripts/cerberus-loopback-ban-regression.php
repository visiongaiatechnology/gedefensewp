<?php
// Cerberus ban-target regression: proxy loopback must never be bannable.
declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
if (!function_exists('did_action')) { function did_action(string $hook): int { return 0; } }
if (!function_exists('add_action')) { function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): bool { return true; } }
if (!function_exists('add_filter')) { function add_filter(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): bool { return true; } }
if (!function_exists('get_option')) { function get_option(string $key, mixed $default = false): mixed { return $default; } }
if (!function_exists('wp_cache_get')) { function wp_cache_get(string $key, string $group = ''): false { return false; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set(string $key, mixed $value, string $group = '', int $ttl = 0): bool { return true; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled(string $hook): false { return false; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event(int $timestamp, string $hook): bool { return true; } }
if (!function_exists('current_time')) { function current_time(string $type, bool $gmt = false): string { return gmdate('Y-m-d H:i:s'); } }

require dirname(__DIR__) . '/includes/modules/cerberus/class-vis-cerberus.php';

$method = new ReflectionMethod(VIS_Cerberus::class, 'is_unbannable_address');
$failures = [];

$mustReject = ['127.0.0.1', '127.0.0.2', '127.0.0.0/8', '::1', '0.0.0.0', '::'];
$mustAllow = ['8.8.8.8', '198.51.100.10', '203.0.113.0/24', '2001:db8::1'];

foreach ($mustReject as $value) {
    if ($method->invoke(null, $value) !== true) {
        $failures[] = 'Not protected as loopback/reserved: ' . $value;
    }
}
foreach ($mustAllow as $value) {
    if ($method->invoke(null, $value) !== false) {
        $failures[] = 'Public ban target wrongly rejected: ' . $value;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "VGT CERBERUS LOOPBACK BAN REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT CERBERUS LOOPBACK BAN REGRESSION: PASS\n");
