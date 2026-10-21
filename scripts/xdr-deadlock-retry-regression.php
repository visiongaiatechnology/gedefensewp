<?php
// XDR incident update must survive a transient MySQL deadlock.
declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public string $last_error = '';
        public int $calls = 0;
        public int $failures_remaining = 2;
        public function update(string $table, array $data, array $where): int|false {
            $this->calls++;
            if ($this->failures_remaining > 0) {
                $this->failures_remaining--;
                $this->last_error = 'Deadlock found when trying to get lock; try restarting transaction';
                return false;
            }
            $this->last_error = '';
            return 1;
        }
    }
}
if (!function_exists('get_option')) { function get_option(string $key, mixed $default = false): mixed { return $default; } }
if (!function_exists('get_transient')) { function get_transient(string $key): mixed { return false; } }
if (!function_exists('set_transient')) { function set_transient(string $key, mixed $value, int $ttl = 0): bool { return true; } }
if (!function_exists('delete_transient')) { function delete_transient(string $key): bool { return true; } }
if (!function_exists('wp_cache_get')) { function wp_cache_get(string $key, string $group = ''): false { return false; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set(string $key, mixed $value, string $group = '', int $ttl = 0): bool { return true; } }
if (!function_exists('wp_cache_delete')) { function wp_cache_delete(string $key, string $group = ''): bool { return true; } }
if (!function_exists('add_action')) { function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): bool { return true; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled(string $hook): false { return false; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool { return true; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event(int $timestamp, string $hook): bool { return true; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode(mixed $value, int $flags = 0): string|false { return json_encode($value, $flags); } }
if (!function_exists('current_time')) { function current_time(string $type, bool $gmt = false): string { return gmdate('Y-m-d H:i:s'); } }

require dirname(__DIR__) . '/includes/xdr/class-xdr-response-engine.php';

$method = new ReflectionMethod(\VisionGaia\GeDefense\Xdr\ResponseEngine::class, 'update_incident_with_retry');
$db = new wpdb();
$ok = $method->invoke(null, $db, 'wp_vis_xdr_incidents', ['response_state' => 'CONTAIN'], 'abc123');

$failures = [];
if ($ok !== true) $failures[] = 'Deadlock was not retried.';
if ($db->calls !== 3) $failures[] = 'Unexpected retry count: ' . $db->calls;

// A non-deadlock error must not be retried.
class NonDeadlockWpdb extends wpdb {
    public function update(string $table, array $data, array $where): int|false {
        $this->calls++;
        $this->last_error = 'Duplicate entry for key PRIMARY';
        return false;
    }
}
$db3 = new NonDeadlockWpdb();
$ok3 = $method->invoke(null, $db3, 'wp_vis_xdr_incidents', ['response_state' => 'CONTAIN'], 'abc123');
if ($ok3 !== false) $failures[] = 'Non-deadlock error was falsely reported as success.';
if ($db3->calls !== 1) $failures[] = 'Non-deadlock error was retried ' . $db3->calls . ' times.';

if ($failures !== []) {
    fwrite(STDERR, "VGT XDR DEADLOCK RETRY REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT XDR DEADLOCK RETRY REGRESSION: PASS\n");

