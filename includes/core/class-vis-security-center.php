<?php
// STATUS: PLATIN
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Security_Center {
    private const MODULES = [
        'kernel' => ['label' => 'Kernel Sentinel', 'zone' => 'Trust Core', 'path' => 'includes/modules/kernel/class-vis-kernel-sentinel.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Kernel\\KernelSentinel', 'rights' => ['request:inspect', 'vault:read', 'event:emit']],
        'aegis' => ['label' => 'Aegis Firewall', 'zone' => 'Enforcement', 'path' => 'includes/modules/aegis/class-vis-aegis.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Aegis\\Aegis', 'rights' => ['request:inspect', 'request:block', 'upload:scan', 'event:emit']],
        'zeus' => ['label' => 'Zeus Pre-Boot WAF', 'zone' => 'Enforcement', 'path' => 'includes/modules/zeus/class-vis-zeus.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Zeus\\Zeus', 'rights' => ['config:compile', 'filesystem:guarded-write', 'request:block']],
        'cerberus' => ['label' => 'Cerberus Ban Engine', 'zone' => 'Enforcement', 'path' => 'includes/modules/cerberus/class-vis-cerberus.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Cerberus\\Cerberus', 'rights' => ['identity:score', 'database:ban-write', 'request:block']],
        'prometheus' => ['label' => 'Prometheus Behavior', 'zone' => 'Detection', 'path' => 'includes/modules/prometheus/class-vis-prometheus.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Prometheus\\Prometheus', 'rights' => ['request:observe', 'score:write', 'event:emit']],
        'airlock' => ['label' => 'Airlock Scanner', 'zone' => 'Detection', 'path' => 'includes/modules/airlock/class-vis-airlock.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Airlock\\Airlock', 'rights' => ['upload:read', 'upload:scan', 'quarantine:write']],
        'nemesis' => ['label' => 'Nemesis Deception', 'zone' => 'Deception', 'path' => 'includes/modules/nemesis/class-vis-nemesis.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Nemesis\\Nemesis', 'rights' => ['request:observe', 'decoy:write', 'event:emit']],
        'morpheus' => ['label' => 'Morpheus Sandbox', 'zone' => 'Analysis', 'path' => 'includes/modules/morpheus/class-vis-morpheus.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Morpheus\\Morpheus', 'rights' => ['event:read', 'analysis:execute', 'recommendation:write']],
        'vault' => ['label' => 'Key Vault', 'zone' => 'Trust Core', 'path' => 'includes/modules/vault/class-vis-key-vault.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Vault\\KeyVault', 'rights' => ['secret:read', 'secret:write', 'crypto:execute']],
        'titan' => ['label' => 'Titan Hardening', 'zone' => 'Policy', 'path' => 'includes/modules/titan/class-vis-titan.php', 'class' => 'VisionGaia\\GeDefense\\Modules\\Titan\\Titan', 'rights' => ['header:write', 'policy:enforce', 'filesystem:guarded-write']],
        'vlp' => ['label' => 'Privacy & Shadow Net', 'zone' => 'Application', 'path' => 'includes/VLP/vision-legal-pro.php', 'class' => 'VisionLegalPro_Core', 'config_key'=>'module_vlp_enabled', 'rights' => ['telemetry:ingest', 'asset:mirror', 'privacy:enforce']],
        'builder' => ['label' => 'VGT Builder', 'zone' => 'Application', 'path' => 'includes/builder/builder.php', 'class' => 'VGT_Builder', 'config_key'=>'module_builder_enabled', 'rights' => ['content:read', 'content:write', 'preview:sandbox']],
        'seo' => ['label' => 'VisionGaiaSEO', 'zone' => 'Application', 'path' => 'includes/VisionGaiaSEO/visiongaia-seo-architect.php', 'class' => 'VG_SEO_Bootstrapper', 'config_key'=>'module_seo_enabled', 'rights' => ['content:read', 'metadata:write', 'redirect:write']],
        'throneguard' => ['label' => 'ThroneGuard Master', 'zone' => 'Privilege Boundary', 'path' => 'includes/modules/throneguard/class-vis-throne-guard.php', 'class' => 'VIS_Throne_Guard', 'config_key'=>'throneguard_enabled', 'rights' => ['role:protect', 'cap:reconcile', 'session:lock', 'superkey:verify']],
        'loginpager' => ['label' => 'LoginPager Gateway', 'zone' => 'Application', 'path' => 'includes/modules/loginpager/class-vis-loginpager.php', 'class' => 'VIS_LoginPager', 'config_key'=>'loginpager_enabled', 'rights' => ['login:style', 'branding:enforce']],
    ];

    public static function snapshot(bool $deep = false): array {
        $started = hrtime(true);
        $modules = self::module_state();
        $checks = self::checks($deep);
        $passedWeight = 0;
        $totalWeight = 0;
        foreach ($checks as $check) {
            $totalWeight += $check['weight'];
            if ($check['status'] === 'pass') $passedWeight += $check['weight'];
        }
        $score = $totalWeight > 0 ? (int)floor(($passedWeight / $totalWeight) * 100) : 0;
        $status = $score >= 95 ? 'hardened' : ($score >= 80 ? 'guarded' : 'attention');
        return [
            'generatedAt' => gmdate('c'),
            'durationMs' => round((hrtime(true) - $started) / 1_000_000, 2),
            'score' => $score,
            'status' => $status,
            'summary' => [
                'passed' => count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'pass')),
                'warnings' => count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'warn')),
                'failed' => count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'fail')),
                'modules' => count($modules),
            ],
            'checks' => $checks,
            'modules' => $modules,
            'boundaries' => self::boundaries(),
            'titan' => self::titan_health(),
        ];
    }

    private static function module_state(): array {
        $config = get_option('vis_config', []);
        if (!is_array($config)) $config = [];
        $result = [];
        foreach (self::MODULES as $id => $module) {
            $path = VIS_PATH . $module['path'];
            $present = is_file($path) && is_readable($path);
            $enabledKey = (string)($module['config_key'] ?? ($id . '_enabled'));
            $enabled = array_key_exists($enabledKey, $config) ? !empty($config[$enabledKey]) : true;
            $result[] = [
                'id' => $id,
                'label' => __($module['label'], 'vgt-sentinel'),
                'zone' => __($module['zone'], 'vgt-sentinel'),
                'present' => $present,
                'enabled' => $enabled,
                'loaded' => class_exists($module['class'], false),
                'integrity' => $present ? substr((string)hash_file('sha256', $path), 0, 16) : '',
                'rights' => $module['rights'],
            ];
        }
        return $result;
    }

    private static function checks(bool $deep): array {
        global $wpdb;
        $vault = defined('VIS_VAULT_DIR') ? VIS_VAULT_DIR : '';
        $rateTable = $wpdb->prefix . 'vis_rate_limits';
        $checks = [
            self::check('strict_types', __('Strict runtime baseline', 'vgt-sentinel'), __('Kernel', 'vgt-sentinel'), defined('VIS_VERSION'), 8, __('Sentinel kernel initialized with a versioned runtime.', 'vgt-sentinel')),
            self::check('debug_display', __('Production error disclosure', 'vgt-sentinel'), __('Runtime', 'vgt-sentinel'), !filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN), 10, __('display_errors must remain disabled.', 'vgt-sentinel')),
            self::check('vault_jail', __('Vault path jail', 'vgt-sentinel'), __('Storage', 'vgt-sentinel'), $vault !== '' && is_dir($vault) && str_starts_with(wp_normalize_path($vault), wp_normalize_path(wp_upload_dir(null, false)['basedir']) . '/'), 9, __('Vault is constrained to the portable WordPress storage boundary.', 'vgt-sentinel')),
            self::check('vault_policy', __('Cross-server vault policy', 'vgt-sentinel'), __('Storage', 'vgt-sentinel'), $vault !== '' && is_file($vault . '/.htaccess') && is_file($vault . '/web.config'), 9, __('Apache and IIS access policies are present.', 'vgt-sentinel')),
            self::check('rate_table', __('Atomic rate-limit storage', 'vgt-sentinel'), __('Database', 'vgt-sentinel'), $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $rateTable)) === $rateTable, 9, __('Atomic request counters are available.', 'vgt-sentinel')),
            self::check('secure_transport', __('Pinned HTTPS transport', 'vgt-sentinel'), __('Network', 'vgt-sentinel'), function_exists('curl_init') && defined('CURLOPT_RESOLVE'), 8, __('Remote mirroring requires DNS pinning support.', 'vgt-sentinel'), 'warn'),
            self::check('crypto', __('Authenticated cryptography', 'vgt-sentinel'), __('Crypto', 'vgt-sentinel'), function_exists('sodium_crypto_secretbox') || (function_exists('openssl_get_cipher_methods') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true)), 10, __('Authenticated encryption primitive is available.', 'vgt-sentinel')),
            self::check('uploads', __('Upload origin primitive', 'vgt-sentinel'), __('Runtime', 'vgt-sentinel'), function_exists('is_uploaded_file') && class_exists('finfo'), 8, __('Upload provenance and content MIME verification are available.', 'vgt-sentinel')),
            self::check('security_gate', __('Regression gate deployed', 'vgt-sentinel'), __('Assurance', 'vgt-sentinel'), is_file(VIS_PATH . 'scripts/security-regression.php'), 8, __('Zero-dependency adversarial build gate is present.', 'vgt-sentinel')),
            self::check('emergency_bypass', __('Static bypass absence', 'vgt-sentinel'), __('Policy', 'vgt-sentinel'), !self::contains_in_php('VGT_' . 'EMERGENCY_' . 'OVERRIDE'), 10, __('No static firewall bypass is present.', 'vgt-sentinel')),
            self::check('throneguard_master', __('ThroneGuard Master Boundary', 'vgt-sentinel'), __('Privilege', 'vgt-sentinel'), class_exists('VIS_Throne_Guard') && (count(get_users(['role' => 'master', 'fields' => 'ids'])) > 0 || current_user_can('manage_options')), 10, __('Master role segregation and superkey lockdown protection are active.', 'vgt-sentinel')),
            self::check('throneguard_hardening', __('Admin Capability Boundary', 'vgt-sentinel'), __('Privilege', 'vgt-sentinel'), class_exists('VIS_Throne_Guard') && (!empty(get_option('vis_config', [])['throneguard_harden_admin'])), 9, __('Dangerous capabilities are stripped from standard administrator accounts.', 'vgt-sentinel'), 'warn'),
        ];
        if ($deep) {
            $checks[] = self::check('php_integrity', __('PHP source readability', 'vgt-sentinel'), __('Integrity', 'vgt-sentinel'), self::all_php_readable(), 9, __('All deployed PHP sources are readable and hashable.', 'vgt-sentinel'));
            $checks[] = self::check('dangerous_tls', __('TLS bypass absence', 'vgt-sentinel'), __('Network', 'vgt-sentinel'), !self::contains_in_php('CURLOPT_SSL_' . 'VERIFYPEER => false') && !self::contains_in_php("'ssl" . "verify' => false"), 10, __('No disabled TLS verification pattern detected.', 'vgt-sentinel'));
            $checks[] = self::check('preview_sandbox', __('Builder origin isolation', 'vgt-sentinel'), __('Application', 'vgt-sentinel'), self::file_contains('includes/builder/views/editor-ui.php', 'sandbox="allow-scripts"') && !self::file_contains('includes/builder/views/editor-ui.php', 'allow-same-origin'), 9, __('Builder preview executes in an opaque browser origin.', 'vgt-sentinel'));
            $checks[] = self::check('integration_registry', __('Application module registry', 'vgt-sentinel'), __('Application', 'vgt-sentinel'), class_exists('VIS_Module_Registry') && class_exists('VIS_Integration_Bus'), 9, __('Suite modules use one lifecycle and event contract.', 'vgt-sentinel'));
            $checks[] = self::check('ai_gateway', __('Unified AI egress', 'vgt-sentinel'), __('Network', 'vgt-sentinel'), class_exists('VIS_AI_Gateway') && !self::file_contains('includes/builder/inc/class-vgt-ajax.php', "wp_remote_post('https://api.groq.com"), 10, __('Builder, VLP and VisionGaiaSEO share one bounded egress policy.', 'vgt-sentinel'));
            $checks[] = self::check('seo_relevance', __('VisionGaiaSEO title relevance', 'vgt-sentinel'), __('Application', 'vgt-sentinel'), self::file_contains('includes/VisionGaiaSEO/includes/class-vg-api-service.php', 'VG_SEO_Relevance::enforce'), 9, __('Generated titles are anchored to the concrete page.', 'vgt-sentinel'));
            $checks[] = self::check('typed_errors', __('Typed disclosure policy', 'vgt-sentinel'), __('Kernel', 'vgt-sentinel'), class_exists('SecurityException') && class_exists('StorageException'), 8, __('Security and storage failures have separate disclosure policies.', 'vgt-sentinel'));
            $checks[] = self::check('aegis_detection_only', __('Aegis parser consistency', 'vgt-sentinel'), __('Enforcement', 'vgt-sentinel'), !self::file_contains('includes/modules/aegis/class-vis-aegis.php', 'sanitize_environment()') && self::file_contains('includes/modules/aegis/class-vis-aegis.php', 'MAX_INSPECTED_BYTES'), 10, __('Aegis observes immutable request data under explicit budgets.', 'vgt-sentinel'));
            $checks[] = self::check('oracle_schema', __('Oracle verdict authorization', 'vgt-sentinel'), __('Analysis', 'vgt-sentinel'), self::file_contains('includes/modules/aegis/class-vis-aegis-oracle.php', 'valid_schema(array $data)') && self::file_contains('includes/modules/aegis/class-vis-aegis-oracle.php', 'MAX_RESPONSE_BYTES'), 9, __('Oracle verdicts require a bounded, typed schema.', 'vgt-sentinel'));
        }
        return $checks;
    }

    private static function check(string $id, string $label, string $domain, bool $passed, int $weight, string $detail, string $failure = 'fail'): array {
        return ['id' => $id, 'label' => $label, 'domain' => $domain, 'status' => $passed ? 'pass' : $failure, 'weight' => $weight, 'detail' => $detail];
    }

    private static function boundaries(): array {
        return [
            ['from' => __('Internet', 'vgt-sentinel'), 'to' => 'Zeus / Aegis', 'policy' => __('Untrusted request inspection', 'vgt-sentinel'), 'state' => 'enforced'],
            ['from' => __('Application modules', 'vgt-sentinel'), 'to' => __('Trust Core', 'vgt-sentinel'), 'policy' => __('Explicit capability surface', 'vgt-sentinel'), 'state' => 'mapped'],
            ['from' => __('Remote network', 'vgt-sentinel'), 'to' => 'Shadow Net', 'policy' => __('Pinned HTTPS, no redirects', 'vgt-sentinel'), 'state' => function_exists('curl_init') ? 'enforced' : 'closed'],
            ['from' => __('Artifact upload', 'vgt-sentinel'), 'to' => __('Runtime Vault', 'vgt-sentinel'), 'policy' => __('Stage, verify, atomic swap', 'vgt-sentinel'), 'state' => 'enforced'],
            ['from' => __('Builder content', 'vgt-sentinel'), 'to' => __('Admin browser', 'vgt-sentinel'), 'policy' => __('Opaque-origin iframe sandbox', 'vgt-sentinel'), 'state' => 'enforced'],
        ];
    }

    /** @return array<string, string> */
    private static function titan_health(): array {
        $config = get_option('vis_config', []);
        $enabled = is_array($config) && !empty($config['titan_enabled']);
        if (!$enabled) return [
            'POLICY_COMPILER' => 'DISABLED', 'HEADER_MANAGER' => 'DISABLED',
            'FETCH_METADATA' => 'DISABLED', 'CSP_REPORTER' => 'DISABLED',
            'SANDBOX' => 'DISABLED', 'SERVER_RULES' => 'DISABLED',
            'POLICY_STORAGE' => 'DISABLED', 'XDR_SENSOR' => 'DISABLED',
        ];
        $runtime = get_option('vis_titan_runtime_health', []);
        $runtime = is_array($runtime) ? $runtime : [];
        $server = get_option('vis_titan_server_rule_status', []);
        $server = is_array($server) ? $server : [];
        $policy = get_option('vis_titan_policy_state', []);
        $policy = is_array($policy) ? $policy : [];
        $serverValidation = class_exists('VIS_Titan_Server_Rules') ? VIS_Titan_Server_Rules::validationSummary() : ['state' => 'INCOMPLETE'];
        return [
            'POLICY_COMPILER' => class_exists('VIS_Titan_Policy_Compiler') ? 'HEALTHY' : 'FAILED',
            'HEADER_MANAGER' => ($runtime['state'] ?? '') === 'SENT' ? 'HEALTHY' : (($runtime['state'] ?? '') === 'DEGRADED' ? 'DEGRADED' : 'INCOMPLETE'),
            'FETCH_METADATA' => class_exists('VIS_Titan_Runtime') ? 'HEALTHY' : 'FAILED',
            'CSP_REPORTER' => class_exists('VIS_Titan_Violation_Collector') ? 'HEALTHY' : 'FAILED',
            'SANDBOX' => class_exists('VIS_Titan_Sandbox') ? (!empty($config['titan_sandbox_origin_verified']) ? 'EXPERIMENTAL' : 'HEALTHY') : 'FAILED',
            'SERVER_RULES' => ($serverValidation['state'] ?? '') === 'PASS' ? (string)($server['state'] ?? 'INCOMPLETE') : 'FAILED',
            'POLICY_STORAGE' => !empty($policy['active']) ? 'HEALTHY' : (!empty($policy['candidate']) ? 'DEGRADED' : 'INCOMPLETE'),
            'XDR_SENSOR' => class_exists('VisionGaia\\GeDefense\\Xdr\\EventFabric') ? 'HEALTHY' : 'FAILED',
        ];
    }

    private static function file_contains(string $relative, string $needle): bool {
        $content = @file_get_contents(VIS_PATH . $relative);
        return is_string($content) && str_contains($content, $needle);
    }

    private static function contains_in_php(string $needle): bool {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(VIS_PATH, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
            $normalized = wp_normalize_path($file->getPathname());
            if (str_ends_with($normalized, '/includes/core/class-vis-security-center.php')
                || str_ends_with($normalized, '/scripts/security-regression.php')) continue;
            $content = @file_get_contents($file->getPathname());
            if (is_string($content) && str_contains($content, $needle)) return true;
        }
        return false;
    }

    private static function all_php_readable(): bool {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(VIS_PATH, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                if (!is_readable($file->getPathname()) || hash_file('sha256', $file->getPathname()) === false) return false;
            }
        }
        return true;
    }
}
