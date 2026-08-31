<?php
// STATUS: PLATIN
declare(strict_types=1);

$workspace = dirname(__DIR__);
$resolved = realpath($workspace);
if ($resolved === false || !is_dir($resolved)) {
    fwrite(STDERR, "VGT SECURITY REGRESSION: FAILED\nWorkspace unavailable.\n");
    exit(1);
}

$failures = [];
$phpFiles = [];
$sourceFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }
    $extension = strtolower($file->getExtension());
    if (!in_array($extension, ['php', 'js'], true)) {
        continue;
    }
    $path = $file->getPathname();
    $content = file_get_contents($path);
    if (!is_string($content)) {
        $failures[] = 'Unreadable source: ' . $path;
        continue;
    }
    $sourceFiles[$path] = $content;
    if ($extension === 'php') {
        $phpFiles[] = $path;
    }
}

$forbidden = [
    'TLS verification disabled' => '/sslverify\s*["\']?\s*=>\s*false|CURLOPT_SSL_VERIFYPEER\s*=>\s*false/i',
    'Client-controlled upload size trusted' => '/\$_FILES\s*\[[^\]]+\]\s*\[\s*["\']size["\']\s*\]/i',
    'Error reporting disabled before handling' => '/error_reporting\s*\(\s*0\s*\)/i',
    'Unsafe preview sandbox' => '/sandbox=["\'][^"\']*allow-scripts[^"\']*allow-same-origin/i',
    'Legacy downloader AJAX route' => '/add_action\s*\(\s*["\']wp_ajax_vlp_download_asset["\']/i',
    'Non-cryptographic unique identifier' => '/\buniqid\s*\(/i',
    'Static emergency bypass' => '/vgt_emergency|VGT_EMERGENCY_OVERRIDE/i',
    'Morpheus static fallback salt' => '/fallback_salt|vgt_fallback_salt/i',
    'Morpheus world-readable directory' => '/modules[\/\\\\]morpheus[\s\S]{0,120}\b(?:mkdir|chmod)\s*\([^;\n]*0755/i',
    'Dynamic Morpheus HTML sink' => '/innerHTML\s*\+=|innerHTML\s*=\s*`[^`]*\$\{(?:data|slug|err)/i',
    'Nemesis worker retention' => '/ignore_user_abort\s*\(|set_time_limit\s*\(\s*0\s*\)|TARPIT_CHUNK_DELAY_MICROSEC|Content-Length:\s*10000000000/i',
    'Nemesis offensive response' => '/vgt_poison_jar_|gzdeflate\s*\(|TERMINAL SABOTAGE/i',
    'Prometheus blocking sleep' => '/@?sleep\s*\(\s*5\s*\)/i',
    'Integrity scanner follows symlinks' => '/RecursiveDirectoryIterator::FOLLOW_SYMLINKS/i',
    'Integrity scanner trusts transport input' => '~includes[/\\\\]+scanner[\s\S]{0,120}\$_POST~i',
];

foreach ($sourceFiles as $path => $content) {
    $normalizedPath = str_replace('\\', '/', $path);
    if (str_ends_with($normalizedPath, '/scripts/security-regression.php')) {
        continue;
    }
    foreach ($forbidden as $label => $pattern) {
        if ($label === 'Morpheus static fallback salt'
            && !str_contains($normalizedPath, '/includes/modules/morpheus/')) {
            continue;
        }
        if ($label === 'Morpheus world-readable directory'
            && !str_contains($normalizedPath, '/includes/modules/morpheus/')) {
            continue;
        }
        if ($label === 'Dynamic Morpheus HTML sink'
            && !str_ends_with($normalizedPath, '/includes/dashboard/views/morpheus/script.js')) {
            continue;
        }
        if (str_starts_with($label, 'Nemesis ')
            && !str_ends_with($normalizedPath, '/includes/modules/nemesis/class-vis-nemesis.php')) {
            continue;
        }
        if ($label === 'Prometheus blocking sleep'
            && !str_ends_with($normalizedPath, '/includes/modules/prometheus/class-vis-prometheus.php')) {
            continue;
        }
        if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $line = substr_count(substr($content, 0, $match[0][1]), "\n") + 1;
            $failures[] = sprintf('%s: %s:%d', $label, $path, $line);
        }
    }
}

$required = [
    'includes/core/class-vis-security.php' => [
        'pinned HTTPS transport' => 'function pinned_https_get(',
        'atomic database limiter' => 'vis_rate_limits',
        'typed security exception' => 'class SecurityException',
        'canonical client identity' => 'public static function client_ip()',
        'CIDR enforcement primitive' => 'public static function ip_in_cidr(',
    ],
    'includes/core/class-vis-trinity-grid.php' => [
        'AEGIS interlock route' => 'public static function onAegisStrike(',
        'Prometheus mitigation route' => 'public static function onPrometheusMitigation(',
        'bounded WAF penalty' => "max(0.0, min(100.0",
    ],
    'includes/modules/aegis/class-vis-aegis.php' => [
        'public deterministic assessment' => 'public function assess_payload(',
        'URI deterministic assessment' => 'public function assess_uri(',
        'runtime fail closed' => 'private function fail_closed_runtime(): never',
        'Aegis byte budget' => 'MAX_INSPECTED_BYTES',
        'measured upload size' => 'filesize($tmp_path)',
    ],
    'includes/modules/morpheus/src/class-morpheus-path-jail.php' => [
        'resolved path jail' => '$resolvedDir = realpath($input);',
        'post-construction jail' => 'str_starts_with($destination, $resolvedDir . DIRECTORY_SEPARATOR)',
        'symlink rejection' => 'is_link($destination)',
    ],
    'includes/modules/morpheus/src/class-morpheus-dashboard.php' => [
        'typed validation catch' => 'catch (\ValidationException $e)',
        'opaque security catch' => 'catch (\SecurityException $e)',
        'opaque storage catch' => 'catch (\StorageException $e)',
        'fatal catch' => 'catch (\Throwable $e)',
    ],
    'includes/core/class-vis-module-integrity.php' => [
        'entrypoint trust anchor' => 'VIS_MANIFEST_DIGEST',
        'complete component verification' => 'public static function verify_all()',
        'constant-time digest match' => 'hash_equals((string)VIS_MANIFEST_DIGEST',
    ],
    'includes/scanner/class-vis-scanner-engine.php' => [
        'resumable jailed indexing' => 'private function continueIndexing(',
        'append-only scan state' => 'current_scan.ndjson',
        'symlink rejection' => 'is_link($candidate)',
        'malware correlation' => 'VIS_Trinity_Grid::onMalwareFinding(',
        'quarantine integration' => 'new VIS_Quarantine_Store()',
    ],
    'includes/scanner/class-vis-malware-engine.php' => [
        'shared malware kernel' => 'final class VIS_Malware_Engine',
        'bounded detector execution' => '$budget->maxMilliseconds',
        'detector composition' => 'new VIS_Php_Lexical_Detector()',
    ],
    'includes/dashboard/class-vis-dashboard-core.php' => [
        'admin IP whitelist notice' => 'display_admin_whitelist_notice',
        'shared client IP resolution' => "VIS_Security', 'client_ip'",
        'AEGIS whitelist coverage' => "aegis_whitelist_ips",
        'Prometheus whitelist coverage' => "prometheus_whitelist_ips",
    ],
    'assets/js/vis-scanner-client.js' => [
        'accepted baseline terminal state' => "STATE.mode === 'reindex' && (status === 'clean' || status === 'init')",
        'accepted baseline remains live' => 'if (!baselineAccepted)',
        'single completion timer' => 'window.clearTimeout(completionTimer)',
    ],
    'scripts/sentinel-threat-benchmark.php' => [
        'supported payload API' => 'assess_payload(',
        'supported URI API' => 'assess_uri(',
        'failing process exit' => 'exit($runner->run() ? 0 : 1);',
    ],
];

foreach ($required as $relative => $invariants) {
    $path = $workspace . '/' . $relative;
    $content = file_get_contents($path);
    if (!is_string($content)) {
        $failures[] = 'Invariant source unavailable: ' . $relative;
        continue;
    }
    foreach ($invariants as $label => $needle) {
        if (!str_contains($content, $needle)) {
            $failures[] = 'Missing invariant: ' . $label;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "VGT SECURITY REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "VGT SECURITY REGRESSION: PASS (%d local PHP files, %d local source files)\n",
        count($phpFiles),
        count($sourceFiles)
    )
);
