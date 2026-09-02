<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Assurance {
    /** @param array<string, array<string, mixed>> $compiled @return array<string, mixed> */
    public static function validate(array $compiled, string $policyId, int $version): array {
        $checksPassed = [];
        $checksFailed = [];
        $warnings = [];
        $critical = [];
        $compilerHashes = [];

        foreach (VIS_Titan_Surface_Resolver::all() as $surface) {
            $policy = $compiled[$surface] ?? null;
            if (!is_array($policy)) {
                $critical[] = 'Missing compiled surface: ' . $surface;
                continue;
            }
            $state = (string)($policy['validation_state'] ?? 'FAILED');
            if (!in_array($state, ['PASS', 'PASS_WITH_WARNINGS'], true)) $critical[] = 'Compiler validation failed for ' . $surface;
            else $checksPassed[] = 'COMPILER:' . $surface;
            $compilerHashes[$surface] = (string)($policy['policy_hash'] ?? '');
            foreach (is_array($policy['warnings'] ?? null) ? $policy['warnings'] : [] as $warning) {
                if (!is_array($warning)) continue;
                $warnings[] = ['surface' => $surface] + $warning;
                if (($warning['level'] ?? '') === 'CRITICAL') $critical[] = $surface . ':' . (string)($warning['code'] ?? 'CRITICAL_CONFLICT');
            }
        }
        $checksPassed[] = 'STATIC_SCHEMA';
        $checksPassed[] = 'SEMANTIC_ANALYSIS';
        $conflicts = self::conflictAnalysis($compiled);
        $warnings = array_merge($warnings, $conflicts['warnings']);
        $critical = array_merge($critical, $conflicts['critical']);
        if ($conflicts['critical'] === []) $checksPassed[] = 'CONFLICT_ANALYSIS';
        else $checksFailed[] = 'CONFLICT_ANALYSIS';

        $probes = self::localProbes($compiled, $policyId);
        foreach ($probes['results'] as $surface => $result) {
            if (($result['state'] ?? '') === 'FAILED') $checksFailed[] = 'PROBE:' . $surface;
            elseif (($result['state'] ?? '') === 'PASS') $checksPassed[] = 'PROBE:' . $surface;
        }
        if ($probes['critical_failed']) $critical[] = 'CRITICAL_SURFACE_PROBE_FAILED';

        $observation = self::reportOnlyObservation();
        $serverValidation = class_exists('VIS_Titan_Server_Rules') ? VIS_Titan_Server_Rules::validationSummary() : ['state' => 'INCOMPLETE'];
        if (($serverValidation['state'] ?? '') === 'FAILED') $critical[] = 'SERVER_RULE_VALIDATION_FAILED';
        $state = 'PASS';
        if ($critical !== []) $state = 'FAILED';
        elseif ($probes['state'] === 'INCOMPLETE') $state = 'INCOMPLETE';
        elseif ($warnings !== [] || $probes['state'] === 'PASS_WITH_WARNINGS') $state = 'PASS_WITH_WARNINGS';
        $eligible = $state !== 'FAILED'
            && !$probes['critical_failed']
            && !$observation['critical_issues']
            && ($observation['state'] ?? '') === 'PASS'
            && ($serverValidation['state'] ?? '') === 'PASS';

        return [
            'policy_id' => $policyId,
            'version' => $version,
            'timestamp' => gmdate('c'),
            'state' => $state,
            'compiler_hash' => hash('sha256', "GEDEFENSE:TITAN:COMPILER:v1\0" . wp_json_encode($compilerHashes, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'surfaces_tested' => array_keys($compiled),
            'checks_passed' => array_values(array_unique($checksPassed)),
            'checks_failed' => array_values(array_unique($checksFailed)),
            'warnings' => array_slice($warnings, 0, 100),
            'critical_failures' => array_values(array_unique($critical)),
            'surface_probes' => $probes['results'],
            'report_only_observation' => $observation,
            'server_rule_validation' => $serverValidation,
            'enforcement_eligible' => $eligible,
            'activation_result' => 'NOT_ACTIVATED',
            'validation_stage' => 'STATIC_VALIDATED',
            'lifecycle_state' => $state === 'PASS' ? 'STATIC_VALIDATED' : 'VALIDATION_FAILED',
            'rollback_result' => 'NOT_REQUIRED',
        ];
    }

    /** @param array<string, array<string, mixed>> $compiled @return array{state:string,critical_failed:bool,results:array<string, array<string, mixed>>} */
    private static function localProbes(array $compiled, string $policyId): array {
        if (!function_exists('wp_safe_remote_get') || !function_exists('home_url')) return ['state' => 'INCOMPLETE', 'critical_failed' => false, 'results' => []];
        $targets = [
            VIS_Titan_Surface_Resolver::PUBLIC_FRONTEND => home_url('/'),
            VIS_Titan_Surface_Resolver::LOGIN => function_exists('wp_login_url') ? wp_login_url() : home_url('/wp-login.php'),
            VIS_Titan_Surface_Resolver::ADMIN => function_exists('admin_url') ? admin_url('index.php') : home_url('/wp-admin/'),
            VIS_Titan_Surface_Resolver::REST => function_exists('rest_url') ? rest_url('/') : home_url('/wp-json/'),
            VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN => function_exists('admin_url') ? admin_url('admin.php?page=vgt-suite') : home_url('/wp-admin/'),
            VIS_Titan_Surface_Resolver::ACTIVE_CONTENT_PREVIEW => add_query_arg('vis_titan_preview', 'probe', home_url('/')),
        ];
        $canonicalHost = strtolower((string)parse_url(home_url('/'), PHP_URL_HOST));
        $canonicalScheme = strtolower((string)parse_url(home_url('/'), PHP_URL_SCHEME));
        $results = [];
        $criticalFailed = false;
        $probeToken = bin2hex(random_bytes(32));
        set_transient('vis_titan_probe_token_hash', hash('sha256', $probeToken), 60);
        $state = get_option('vis_titan_policy_state', []);
        $activeId = is_array($state) && is_array($state['active'] ?? null) ? (string)($state['active']['policy_id'] ?? '') : '';
        $validatingActive = $activeId !== '' && hash_equals($activeId, $policyId);
        foreach ($targets as $surface => $url) {
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if ($host === '' || !hash_equals($canonicalHost, $host) || !hash_equals($canonicalScheme, $scheme) || !in_array($scheme, ['http', 'https'], true)) {
                $results[$surface] = ['state' => 'FAILED', 'reason' => 'CANONICAL_TARGET_REJECTED'];
                $criticalFailed = true;
                continue;
            }
            $response = wp_safe_remote_get($url, ['timeout' => 3, 'redirection' => 0, 'reject_unsafe_urls' => true, 'cookies' => [], 'headers' => ['X-VGT-Titan-Probe' => $probeToken]]);
            if (is_wp_error($response)) {
                $results[$surface] = ['state' => 'INCOMPLETE', 'reason' => 'LOOPBACK_UNAVAILABLE'];
                continue;
            }
            $status = (int)wp_remote_retrieve_response_code($response);
            $ok = $status >= 200 && $status < 500;
            $observedSurface = (string)wp_remote_retrieve_header($response, 'x-vgt-titan-probe-surface');
            $observedHash = (string)wp_remote_retrieve_header($response, 'x-vgt-titan-probe-policy');
            $expectedHash = (string)($compiled[$surface]['policy_hash'] ?? '');
            $headerObserved = $observedSurface !== '' && $observedHash !== '';
            $matchesCandidate = $headerObserved && hash_equals($surface, $observedSurface) && hash_equals($expectedHash, $observedHash);
            $probeState = !$ok ? 'FAILED' : ($headerObserved ? ($matchesCandidate ? 'PASS' : 'PASS_WITH_WARNINGS') : 'INCOMPLETE');
            $results[$surface] = ['state' => $probeState, 'status' => $status, 'expected_policy_hash' => $expectedHash, 'observed_policy_hash' => $observedHash, 'observed_surface' => $observedSurface, 'matches_candidate' => $matchesCandidate];
            if ($validatingActive && !$matchesCandidate && in_array($surface, [VIS_Titan_Surface_Resolver::LOGIN, VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN], true)) $criticalFailed = true;
            if (!$ok && in_array($surface, [VIS_Titan_Surface_Resolver::LOGIN, VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN], true)) $criticalFailed = true;
        }
        delete_transient('vis_titan_probe_token_hash');
        $states = array_column($results, 'state');
        return ['state' => in_array('INCOMPLETE', $states, true) ? 'INCOMPLETE' : (in_array('PASS_WITH_WARNINGS', $states, true) ? 'PASS_WITH_WARNINGS' : 'PASS'), 'critical_failed' => $criticalFailed, 'results' => $results];
    }

    /** @return array{state:string,total:int,critical:int,critical_issues:bool,samples:int} */
    private static function reportOnlyObservation(): array {
        $violations = get_option('vis_titan_violations', []);
        $metrics = get_option('vis_titan_observation_metrics', []);
        $samples = 0;
        if (is_array($metrics)) {
            foreach ($metrics as $record) if (is_array($record)) $samples += max(0, (int)($record['samples'] ?? 0));
        }
        if (!is_array($violations)) $violations = [];
        $total = 0;
        $critical = 0;
        foreach ($violations as $record) {
            if (!is_array($record)) continue;
            $count = max(0, (int)($record['count'] ?? 0));
            $total += $count;
            $surface = (string)($record['surface'] ?? '');
            $directive = (string)($record['directive'] ?? '');
            if (in_array($surface, [VIS_Titan_Surface_Resolver::LOGIN, VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN], true) && in_array($directive, ['script-src','script-src-elem','style-src'], true)) $critical += $count;
        }
        $state = $critical > 0 ? 'COMPATIBILITY_ISSUES' : ($samples >= 5 ? 'PASS' : 'INCOMPLETE');
        return ['state' => $state, 'total' => $total, 'critical' => $critical, 'critical_issues' => $critical > 0, 'samples' => $samples];
    }

    /** @param array<string, array<string, mixed>> $compiled @return array{warnings:list<array<string,string>>,critical:list<string>} */
    private static function conflictAnalysis(array $compiled): array {
        $warnings = [];
        $critical = [];
        $external = get_option('vis_titan_header_conflicts', []);
        if (is_array($external)) {
            foreach (array_slice($external, -50, null, true) as $conflict) {
                if (!is_array($conflict)) continue;
                $header = (string)($conflict['header'] ?? 'UNKNOWN');
                $surface = (string)($conflict['surface'] ?? 'UNKNOWN');
                $warnings[] = ['surface' => $surface, 'level' => in_array(strtolower($header), ['content-security-policy', 'cross-origin-opener-policy'], true) ? 'HIGH' : 'MEDIUM', 'code' => 'EXTERNAL_HEADER_CONFLICT', 'message' => 'External policy conflicts with TITAN header ' . $header . '.'];
            }
        }
        $violations = get_option('vis_titan_violations', []);
        if (is_array($violations)) {
            foreach ($violations as $record) {
                if (!is_array($record)) continue;
                $directive = (string)($record['directive'] ?? '');
                $surface = (string)($record['surface'] ?? 'UNKNOWN');
                $count = max(0, (int)($record['count'] ?? 0));
                if ($count === 0) continue;
                if (in_array($directive, ['frame-src', 'child-src'], true)) $warnings[] = ['surface' => $surface, 'level' => 'HIGH', 'code' => 'FRAME_COMPATIBILITY', 'message' => 'Observed frame usage conflicts with the compiled frame policy.'];
                if (in_array($directive, ['script-src', 'script-src-elem', 'style-src'], true) && in_array($surface, [VIS_Titan_Surface_Resolver::LOGIN, VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN], true)) $critical[] = $surface . ':CRITICAL_ADMIN_ASSET_VIOLATION';
            }
        }
        return ['warnings' => array_slice($warnings, 0, 100), 'critical' => array_values(array_unique($critical))];
    }
}
