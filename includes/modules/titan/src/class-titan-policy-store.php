<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Policy_Store {
    private const OPTION = 'vis_titan_policy_state';
    private const MAX_HISTORY = 5;

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public static function stage(array $config): array {
        $state = self::state();
        $version = max(1, (int)($state['version'] ?? 0) + 1);
        $policyId = bin2hex(random_bytes(16));
        try {
            $safeConfig = self::policyConfig($config);
            $compiled = VIS_Titan_Policy_Compiler::compileAll($safeConfig);
            $report = VIS_Titan_Assurance::validate($compiled, $policyId, $version);
            $candidate = ['policy_id' => $policyId, 'version' => $version, 'created_at' => gmdate('c'), 'config' => $safeConfig, 'compiled' => $compiled, 'validation' => $report, 'lifecycle' => $report['state'] === 'FAILED' ? 'REJECTED' : 'VALIDATED'];
            $state['candidate'] = $candidate;
            $state['version'] = $version;
            self::persist($state);
            return $candidate;
        } catch (ValidationException $e) {
            return self::failedCandidate($state, $policyId, $version, $e->getMessage());
        } catch (SecurityException $e) {
            error_log('[TITAN SECURITY] ' . $e->getMessage());
            return self::failedCandidate($state, $policyId, $version, 'Request rejected for security reasons.');
        } catch (StorageException $e) {
            error_log('[TITAN STORAGE] ' . $e->getMessage());
            return self::failedCandidate($state, $policyId, $version, 'A server error occurred.');
        } catch (Throwable $e) {
            error_log('[TITAN FATAL] ' . $e->getMessage());
            return self::failedCandidate($state, $policyId, $version, 'Critical system fault.');
        }
    }

    public static function activate(string $mode): bool {
        if (!in_array($mode, ['report_only', 'enforce'], true)) throw new ValidationException('Invalid TITAN activation mode.');
        $state = self::state();
        $candidate = is_array($state['candidate'] ?? null) ? $state['candidate'] : [];
        if ($candidate === [] || ($candidate['lifecycle'] ?? '') === 'REJECTED') throw new ValidationException('No validated TITAN candidate exists.');
        $config = is_array($candidate['config'] ?? null) ? $candidate['config'] : [];
        $config['titan_csp_mode'] = $mode;
        $compiled = VIS_Titan_Policy_Compiler::compileAll($config);
        $report = VIS_Titan_Assurance::validate($compiled, (string)$candidate['policy_id'], (int)$candidate['version']);
        if (($report['state'] ?? '') === 'FAILED') throw new SecurityException('TITAN policy activation validation failed.');
        if ($mode === 'enforce' && empty($report['enforcement_eligible'])) throw new SecurityException('TITAN policy is not enforcement eligible.');

        $candidate['lifecycle'] = 'STAGED';
        $candidate['config'] = $config;
        $candidate['compiled'] = $compiled;
        $candidate['validation'] = $report;
        $state['candidate'] = $candidate;
        self::persist($state);

        $active = ['policy_id' => $candidate['policy_id'], 'version' => $candidate['version'], 'activated_at' => gmdate('c'), 'mode' => $mode, 'config' => $config, 'compiled' => $compiled, 'validation' => array_replace($report, ['activation_result' => 'ACTIVATED']), 'lifecycle' => 'ACTIVATED'];
        $previous = is_array($state['active'] ?? null) ? $state['active'] : [];
        if ($previous !== []) {
            $history = is_array($state['history'] ?? null) ? $state['history'] : [];
            array_unshift($history, $previous);
            $state['history'] = array_slice($history, 0, self::MAX_HISTORY);
            $state['last_known_good'] = $previous;
        }
        $state['active'] = $active;
        $state['candidate'] = $active;
        $state['pending_confirmation'] = ['policy_id' => $active['policy_id'], 'version' => $active['version'], 'expires_at' => time() + 300];
        self::persist($state);
        self::emitChange('TITAN_POLICY_ACTIVATED', $active, 'CONTEXT');
        return true;
    }

    public static function rollback(string $reason = 'ADMIN_ROLLBACK'): bool {
        $state = self::state();
        $known = is_array($state['last_known_good'] ?? null) ? $state['last_known_good'] : [];
        if ($known === []) {
            $history = is_array($state['history'] ?? null) ? $state['history'] : [];
            $known = is_array($history[0] ?? null) ? $history[0] : [];
        }
        if ($known === []) return false;
        $current = is_array($state['active'] ?? null) ? $state['active'] : [];
        if ($current !== []) {
            $history = is_array($state['history'] ?? null) ? $state['history'] : [];
            array_unshift($history, $current);
            $state['history'] = array_slice($history, 0, self::MAX_HISTORY);
        }
        $known['lifecycle'] = 'ROLLED_BACK_ACTIVE';
        $known['validation']['rollback_result'] = $reason;
        $state['active'] = $known;
        $state['candidate'] = $known;
        unset($state['pending_confirmation']);
        self::persist($state);
        self::emitChange('TITAN_POLICY_ROLLBACK', $known, 'RESPONSE');
        return true;
    }

    /** @param array<string, mixed> $config */
    public static function emergencyRecover(array $config): void {
        if (!defined('WP_CLI') || WP_CLI !== true) throw new SecurityException('TITAN emergency recovery requires local WP-CLI execution.');
        $safeConfig = self::policyConfig($config);
        $safeConfig['titan_profile'] = 'compatible';
        $safeConfig['titan_csp_mode'] = 'report_only';
        $safeConfig['titan_fetch_mode'] = 'audit';
        $compiled = VIS_Titan_Policy_Compiler::compileAll($safeConfig);
        $state = self::state();
        $version = max(1, (int)($state['version'] ?? 0) + 1);
        $policy = [
            'policy_id' => bin2hex(random_bytes(16)),
            'version' => $version,
            'activated_at' => gmdate('c'),
            'mode' => 'report_only',
            'config' => $safeConfig,
            'compiled' => $compiled,
            'validation' => ['state' => 'LOCAL_RECOVERY', 'enforcement_eligible' => false, 'activation_result' => 'LOCAL_WP_CLI_RECOVERY'],
            'lifecycle' => 'RECOVERY_REPORT_ONLY',
        ];
        $previous = is_array($state['active'] ?? null) ? $state['active'] : [];
        if ($previous !== []) {
            $history = is_array($state['history'] ?? null) ? $state['history'] : [];
            array_unshift($history, $previous);
            $state['history'] = array_slice($history, 0, self::MAX_HISTORY);
        }
        $state['active'] = $policy;
        $state['candidate'] = $policy;
        $state['version'] = $version;
        unset($state['pending_confirmation']);
        self::persist($state);
        self::emitChange('TITAN_LOCAL_RECOVERY', $policy, 'RESPONSE');
    }

    /** @return array<string, mixed> */
    public static function activePolicy(string $surface, array $fallbackConfig): array {
        static $cache = [];
        if (isset($cache[$surface])) return $cache[$surface];
        $state = self::state();
        $active = is_array($state['active'] ?? null) ? $state['active'] : [];
        $compiled = is_array($active['compiled'] ?? null) ? $active['compiled'] : [];
        if (is_array($compiled[$surface] ?? null)) return $cache[$surface] = $compiled[$surface];
        try {
            return $cache[$surface] = VIS_Titan_Policy_Compiler::compile($fallbackConfig, $surface);
        } catch (ValidationException $e) {
            error_log('[TITAN VALIDATION] Invalid fallback configuration rejected: ' . $e->getMessage());
        } catch (SecurityException $e) {
            error_log('[TITAN SECURITY] Unsafe fallback configuration rejected: ' . $e->getMessage());
        } catch (StorageException $e) {
            error_log('[TITAN STORAGE] Fallback policy unavailable: ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log('[TITAN FATAL] Fallback policy compilation fault: ' . $e->getMessage());
        }
        return $cache[$surface] = VIS_Titan_Policy_Compiler::compile([
            'titan_profile' => 'compatible',
            'titan_csp_mode' => 'off',
            'titan_fetch_mode' => 'audit',
            'titan_hsts_enabled' => 0,
        ], $surface);
    }

    /** @return array<string, mixed> */
    public static function snapshot(): array { return self::state(); }

    public static function confirmOrRollback(string $surface, array $sentHeaders): void {
        $state = self::state();
        $pending = is_array($state['pending_confirmation'] ?? null) ? $state['pending_confirmation'] : [];
        if ($pending === []) return;
        $active = is_array($state['active'] ?? null) ? $state['active'] : [];
        if (!hash_equals((string)($pending['policy_id'] ?? ''), (string)($active['policy_id'] ?? ''))) return;
        $expected = is_array($active['compiled'][$surface]['headers'] ?? null) ? $active['compiled'][$surface]['headers'] : [];
        $criticalNames = ['X-Content-Type-Options'];
        if (in_array($surface, [VIS_Titan_Surface_Resolver::LOGIN, VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN], true)) $criticalNames[] = 'Cache-Control';
        foreach ($criticalNames as $name) {
            if (!isset($expected[$name])) continue;
            $actual = $sentHeaders[strtolower($name)] ?? null;
            if (!is_string($actual) || !hash_equals($expected[$name], $actual)) {
                self::rollback('POST_ACTIVATION_HEADER_MISMATCH');
                return;
            }
        }
        unset($state['pending_confirmation']);
        $state['active']['lifecycle'] = 'CONFIRMED';
        $state['active']['validation']['activation_result'] = 'CONFIRMED';
        self::persist($state);
    }

    /** @return array<string, mixed> */
    private static function state(): array {
        $state = get_option(self::OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function persist(array $state): void {
        $json = wp_json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 2097152) throw new StorageException('TITAN policy state exceeds storage boundary.');
        $updated = update_option(self::OPTION, $state, false);
        if (!$updated && get_option(self::OPTION, null) !== $state) throw new StorageException('TITAN policy persistence failed.');
        wp_cache_delete(self::OPTION, 'options');
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    private static function policyConfig(array $config): array {
        $safe = [];
        foreach ($config as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'titan_') || str_contains($key, 'secret') || str_contains($key, 'key')) continue;
            if (is_scalar($value) || $value === null) $safe[$key] = is_string($value) ? substr($value, 0, 8192) : $value;
        }
        ksort($safe, SORT_STRING);
        return $safe;
    }

    /** @return array<string, mixed> */
    private static function failedCandidate(array $state, string $policyId, int $version, string $message): array {
        $candidate = ['policy_id' => $policyId, 'version' => $version, 'created_at' => gmdate('c'), 'config' => [], 'compiled' => [], 'validation' => ['state' => 'FAILED', 'critical_failures' => [$message], 'enforcement_eligible' => false, 'activation_result' => 'NOT_ACTIVATED', 'rollback_result' => 'NOT_REQUIRED'], 'lifecycle' => 'REJECTED'];
        $state['candidate'] = $candidate;
        $state['version'] = $version;
        self::persist($state);
        return $candidate;
    }

    /** @param array<string, mixed> $policy */
    private static function emitChange(string $eventType, array $policy, string $role): void {
        $fabric = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
        if (!class_exists($fabric)) return;
        $fabric::ingest(['sensor' => 'TITAN', 'category' => 'CONFIGURATION', 'event_type' => $eventType, 'role' => $role, 'severity' => $role === 'RESPONSE' ? 4 : 2, 'confidence' => $role === 'RESPONSE' ? 100 : 30, 'score' => 0, 'user_id' => function_exists('get_current_user_id') ? get_current_user_id() : 0, 'entity_type' => 'POLICY', 'entity_id' => 'titan-policy:' . (string)($policy['policy_id'] ?? 'unknown'), 'vector' => $eventType, 'action_type' => $role === 'RESPONSE' ? 'ROLLBACK' : 'OBSERVE', 'outcome' => 'RECORDED', 'metadata' => ['policy_id' => (string)($policy['policy_id'] ?? ''), 'version' => (int)($policy['version'] ?? 0)]]);
    }
}
