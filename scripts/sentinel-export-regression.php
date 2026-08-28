<?php
// STATUS: PLATIN
declare(strict_types=1);

define('ABSPATH', __DIR__);

class AppException        extends Exception {}
class ValidationException extends AppException {}
class SecurityException   extends AppException {}
class StorageException    extends AppException {}

function wp_salt(string $scheme = 'auth'): string {
    return 'regression-only-secret-' . $scheme;
}

require dirname(__DIR__) . '/includes/dashboard/class-vis-sentinel-export.php';

try {
    $exporter = new VIS_Sentinel_Export('0123456789abcdef0123456789abcdef');
    $method = new ReflectionMethod($exporter, 'sanitize');

    $result = $method->invoke($exporter, [
        'api_key'     => 'must-not-export',
        'ip_address'  => '203.0.113.7',
        'request_uri' => '/wp-login.php?token=secret',
        'message'     => 'Hit from 203.0.113.7 by admin@example.test',
    ]);

    if (($result['api_key'] ?? '') !== '[redacted]') {
        throw new RuntimeException('Secret redaction failed.');
    }
    if (!preg_match('/^ip_[a-f0-9]{20}$/', (string)($result['ip_address'] ?? ''))) {
        throw new RuntimeException('IP pseudonymization failed.');
    }
    if (($result['request_uri'] ?? '') !== '/wp-login.php') {
        throw new RuntimeException('URL query redaction failed.');
    }
    if (str_contains((string)($result['message'] ?? ''), '203.0.113.7')
        || str_contains((string)($result['message'] ?? ''), 'admin@example.test')) {
        throw new RuntimeException('Embedded identifier redaction failed.');
    }

    $source = file_get_contents(dirname(__DIR__) . '/includes/dashboard/class-vis-sentinel-export.php');
    foreach ([
        "wp_verify_nonce(\$nonce, self::NONCE_ACTION)",
        "current_user_can('manage_options')",
        "'completeness'     => 'all_rows'",
        'WHERE `id` < %d ORDER BY `id` DESC LIMIT %d',
        "JSON_THROW_ON_ERROR",
        "catch (SecurityException \$e)",
    ] as $required) {
        if (!is_string($source) || !str_contains($source, $required)) {
            throw new RuntimeException('Export security invariant missing: ' . $required);
        }
    }

    echo "PASS: export CSRF, authorization, redaction and pseudonymization invariants\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
