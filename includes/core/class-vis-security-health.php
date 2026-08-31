<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Security_Health {

    /** @return array<int, array{id:string,label:string,status:string,severity:int,detail:string}> */
    public static function run(): array {
        $checks = [
            self::pattern_absent('no_tls_bypass', 'No disabled TLS verification', '/sslverify\s*=>\s*false/i', 9),
            self::pattern_absent('no_wildcard_cors', 'No wildcard telemetry CORS', '/Access-Control-Allow-Origin:\s*\*/i', 8),
            self::pattern_absent('no_static_fallback_secret', 'No static Zeus fallback secret', '/vgt_' . 'fallback_secret/i', 8),
            self::pattern_absent('no_raw_wp_redirect', 'No raw wp_redirect calls', '/\bwp_redirect\s*\(/i', 6),
            self::pattern_absent('no_error_reporting_zero', 'No disabled PHP error reporting handler', '/error_reporting\s*\(\s*0\s*\)/i', 7),
            self::pattern_absent('no_x_xss_protection', 'No deprecated browser XSS header', '/X-' . 'XSS-Protection/i', 5),
            self::pattern_absent('no_dangerous_file_size_pattern', 'No client-side upload size trust', '/\$' . '_FILES\s*\[[^\]]+\]\s*\[\s*[\'"]size[\'"]\s*\]/i', 8),
            self::pattern_present('safe_http_core', 'Safe HTTP validator available', '/function\s+validate_public_http_url\s*\(/i', 7, VIS_PATH . 'includes/core/class-vis-security.php'),
            self::pattern_present('event_bus_core', 'Security event bus available', '/final\s+class\s+VIS_Event_Bus/i', 6, VIS_PATH . 'includes/core/class-vis-event-bus.php'),
            self::pattern_present('vault_versioned_payload', 'Vault payload versioning active', '/PAYLOAD_PREFIX\s*=\s*[\'"]vgt1:/i', 7, VIS_PATH . 'class-vis-vault.php'),
            self::pattern_present('dattrack_nonce', 'Dattrack nonce verification active', '/wp_verify_nonce\(\$nonce,\s*[\'"]vgt_dattrack_pulse[\'"]\)/i', 7, VIS_PATH . 'includes/VLP/includes/modules/dattrack/class-collector.php'),
            self::pattern_present('airlock_uploaded_file', 'Airlock verifies uploaded tmp origin', '/is_uploaded_file\s*\(/i', 7, VIS_PATH . 'includes/modules/airlock/src/class-airlock-scanner.php'),
                        self::pattern_present('throneguard_core', 'ThroneGuard privilege boundary active', '/final\s+class\s+VIS_Throne_Guard/i', 9, VIS_PATH . 'includes/modules/throneguard/class-vis-throne-guard.php'),
            self::pattern_present('loginpager_core', 'LoginPager gateway active', '/final\s+class\s+VIS_LoginPager/i', 6, VIS_PATH . 'includes/modules/loginpager/class-vis-loginpager.php'),
            self::legacy_gorgon_inactive(),
        ];

        return $checks;
    }

    public static function score(): int {
        $checks = self::run();
        $total = 0;
        $passed = 0;

        foreach ($checks as $check) {
            $weight = max(1, (int)$check['severity']);
            $total += $weight;
            if ($check['status'] === 'pass') {
                $passed += $weight;
            }
        }

        return $total > 0 ? (int)floor(($passed / $total) * 100) : 0;
    }

    private static function pattern_absent(string $id, string $label, string $pattern, int $severity): array {
        $matches = self::scan($pattern);
        return [
            'id' => $id,
            'label' => $label,
            'status' => $matches === [] ? 'pass' : 'fail',
            'severity' => $severity,
            'detail' => $matches === [] ? 'Pattern absent.' : implode(', ', array_slice($matches, 0, 3)),
        ];
    }

    private static function pattern_present(string $id, string $label, string $pattern, int $severity, string $file): array {
        $content = is_readable($file) ? (string)file_get_contents($file) : '';
        $ok = $content !== '' && preg_match($pattern, $content) === 1;
        return [
            'id' => $id,
            'label' => $label,
            'status' => $ok ? 'pass' : 'fail',
            'severity' => $severity,
            'detail' => $ok ? 'Pattern present.' : 'Pattern missing in ' . str_replace(VIS_PATH, '', $file),
        ];
    }

    private static function legacy_gorgon_inactive(): array {
        $main = is_readable(VIS_PATH . 'vision-integrity-sentinel.php')
            ? (string)file_get_contents(VIS_PATH . 'vision-integrity-sentinel.php')
            : '';
        $legacy = 'VIS_' . 'Gorgon_' . 'API::' . 'register_endpoints';
        $ok = strpos($main, $legacy) === false;

        return [
            'id' => 'gorgon_single_router',
            'label' => 'Gorgon modular router is authoritative',
            'status' => $ok ? 'pass' : 'fail',
            'severity' => 7,
            'detail' => $ok ? 'Legacy root router is not registered.' : 'Legacy root router still registers AJAX endpoints.',
        ];
    }

    /** @return array<int, string> */
    private static function scan(string $pattern): array {
        $matches = [];
        $root = realpath(VIS_PATH);
        if ($root === false) {
            return ['VIS_PATH unresolved'];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (self::skip_path($path)) {
                continue;
            }

            $content = @file_get_contents($path);
            if (!is_string($content) || $content === '') {
                continue;
            }

            if (preg_match($pattern, $content) === 1) {
                $matches[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            }
        }

        return $matches;
    }

    private static function skip_path(string $path): bool {
        $normalized = wp_normalize_path($path);
        return str_contains($normalized, '/.git/')
            || str_ends_with($normalized, '/includes/core/class-vis-security-health.php')
            || str_ends_with($normalized, '.zip')
            || str_ends_with($normalized, '.tar.gz')
            || str_contains($normalized, '/node_modules/')
            || str_contains($normalized, '/vendor/');
    }
}
