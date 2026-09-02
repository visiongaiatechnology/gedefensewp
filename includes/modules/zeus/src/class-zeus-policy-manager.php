<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Policy_Manager {

    public const OPTION_ACTIVE_POLICY = 'vis_zeus_config';
    public const OPTION_LAST_KNOWN_GOOD = 'vis_zeus_last_known_good_config';

    /**
     * Computes a deterministic SHA-256 policy digest.
     *
     * @param array<string, mixed> $config
     * @return string
     */
    public static function computeDigest(array $config): string {
        $normalized = self::normalizeConfigForDigest($config);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return hash('sha256', 'GEDEFENSE:ZEUS:POLICY:v1|' . $json);
    }

    /**
     * Stages candidate policy, statically validates it, saves LKG, and activates atomically.
     *
     * @param array<string, mixed> $candidateConfig
     * @param callable $compiler
     * @return array{success: bool, digest: string, error: string|null}
     */
    public static function stageAndActivate(array $candidateConfig, callable $compiler): array {
        // 1. Static Validation
        $validationError = self::validatePolicySchema($candidateConfig);
        if ($validationError !== null) {
            return ['success' => false, 'digest' => '', 'error' => $validationError];
        }

        $currentActive = get_option(self::OPTION_ACTIVE_POLICY, []);
        $candidateDigest = self::computeDigest($candidateConfig);
        $candidateConfig['policy_digest'] = $candidateDigest;
        $candidateConfig['policy_compiled_at'] = gmdate('Y-m-d H:i:s');

        // 2. Stage to LKG (Save current working before overwriting)
        if (is_array($currentActive) && !empty($currentActive)) {
            update_option(self::OPTION_LAST_KNOWN_GOOD, $currentActive);
        }

        // 3. Save Candidate
        update_option(self::OPTION_ACTIVE_POLICY, $candidateConfig);

        // 4. Compile & Deploy
        try {
            $compiler($candidateConfig);
            return ['success' => true, 'digest' => $candidateDigest, 'error' => null];
        } catch (\Throwable $e) {
            // Rollback immediately to Last Known Good
            if (is_array($currentActive) && !empty($currentActive)) {
                update_option(self::OPTION_ACTIVE_POLICY, $currentActive);
                try {
                    $compiler($currentActive);
                } catch (\Throwable) {}
            }
            return ['success' => false, 'digest' => '', 'error' => 'Policy compilation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Explicit rollback to Last Known Good policy slot.
     *
     * @param callable $compiler
     * @return bool
     */
    public static function rollbackToLastKnownGood(callable $compiler): bool {
        $lkg = get_option(self::OPTION_LAST_KNOWN_GOOD, null);
        if (!is_array($lkg) || empty($lkg)) {
            return false;
        }

        update_option(self::OPTION_ACTIVE_POLICY, $lkg);
        try {
            $compiler($lkg);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function validatePolicySchema(array $config): ?string {
        if (isset($config['max_query_length']) && (int)$config['max_query_length'] < 64) {
            return 'Max query length cannot be less than 64 bytes.';
        }
        if (isset($config['max_header_size']) && (int)$config['max_header_size'] < 1024) {
            return 'Max header size cannot be less than 1024 bytes.';
        }
        if (isset($config['canonical_hosts']) && is_array($config['canonical_hosts'])) {
            foreach ($config['canonical_hosts'] as $host) {
                $clean = preg_replace('/:\d+$/', '', trim((string)$host));
                if ($clean !== '' && !preg_match('/^[a-z0-9](?:[a-z0-9\-\.]{0,253}[a-z0-9])?$/i', $clean)) {
                    return sprintf('Invalid canonical host format: %s', substr((string)$host, 0, 64));
                }
            }
        }
        return null;
    }

    private static function normalizeConfigForDigest(array $config): array {
        unset($config['policy_digest'], $config['policy_compiled_at']);
        ksort($config);
        return $config;
    }
}
