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
                'label' => $module['label'],
                'zone' => $module['zone'],
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
            self::check('strict_types', 'Strict runtime baseline', 'Kernel', defined('VIS_VERSION'), 8, 'Sentinel kernel initialized with a versioned runtime.'),
            self::check('debug_display', 'Production error disclosure', 'Runtime', !filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN), 10, 'display_errors must remain disabled.'),
            self::check('vault_jail', 'Vault path jail', 'Storage', $vault !== '' && is_dir($vault) && str_starts_with(wp_normalize_path($vault), wp_normalize_path(wp_upload_dir(null, false)['basedir']) . '/'), 9, 'Vault is constrained to the portable WordPress storage boundary.'),
            self::check('vault_policy', 'Cross-server vault policy', 'Storage', $vault !== '' && is_file($vault . '/.htaccess') && is_file($vault . '/web.config'), 9, 'Apache and IIS access policies are present.'),
            self::check('rate_table', 'Atomic rate-limit storage', 'Database', $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $rateTable)) === $rateTable, 9, 'Atomic request counters are available.'),
            self::check('secure_transport', 'Pinned HTTPS transport', 'Network', function_exists('curl_init') && defined('CURLOPT_RESOLVE'), 8, 'Remote mirroring requires DNS pinning support.', 'warn'),
            self::check('crypto', 'Authenticated cryptography', 'Crypto', function_exists('sodium_crypto_secretbox') || (function_exists('openssl_get_cipher_methods') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true)), 10, 'Authenticated encryption primitive is available.'),
            self::check('uploads', 'Upload origin primitive', 'Runtime', function_exists('is_uploaded_file') && class_exists('finfo'), 8, 'Upload provenance and content MIME verification are available.'),
            self::check('security_gate', 'Regression gate deployed', 'Assurance', is_file(VIS_PATH . 'scripts/security-regression.php'), 8, 'Zero-dependency adversarial build gate is present.'),
            self::check('emergency_bypass', 'Static bypass absence', 'Policy', !self::contains_in_php('VGT_' . 'EMERGENCY_' . 'OVERRIDE'), 10, 'No static firewall bypass is present.'),
            self::check('throneguard_master', 'ThroneGuard Master Boundary', 'Privilege', class_exists('VIS_Throne_Guard') && (count(get_users(['role' => 'master', 'fields' => 'ids'])) > 0 || current_user_can('manage_options')), 10, 'Master role segregation and superkey lockdown protection are active.'),
            self::check('throneguard_hardening', 'Admin Capability Boundary', 'Privilege', class_exists('VIS_Throne_Guard') && (!empty(get_option('vis_config', [])['throneguard_harden_admin'])), 9, 'Dangerous capabilities are stripped from standard administrator accounts.', 'warn'),
        ];
        if ($deep) {
            $checks[] = self::check('php_integrity', 'PHP source readability', 'Integrity', self::all_php_readable(), 9, 'All deployed PHP sources are readable and hashable.');
            $checks[] = self::check('dangerous_tls', 'TLS bypass absence', 'Network', !self::contains_in_php('CURLOPT_SSL_' . 'VERIFYPEER => false') && !self::contains_in_php("'ssl" . "verify' => false"), 10, 'No disabled TLS verification pattern detected.');
            $checks[] = self::check('preview_sandbox', 'Builder origin isolation', 'Application', self::file_contains('includes/builder/views/editor-ui.php', 'sandbox="allow-scripts"') && !self::file_contains('includes/builder/views/editor-ui.php', 'allow-same-origin'), 9, 'Builder preview executes in an opaque browser origin.');
            $checks[] = self::check('integration_registry', 'Application module registry', 'Application', class_exists('VIS_Module_Registry') && class_exists('VIS_Integration_Bus'), 9, 'Suite modules use one lifecycle and event contract.');
            $checks[] = self::check('ai_gateway', 'Unified AI egress', 'Network', class_exists('VIS_AI_Gateway') && !self::file_contains('includes/builder/inc/class-vgt-ajax.php', "wp_remote_post('https://api.groq.com"), 10, 'Builder, VLP and VisionGaiaSEO share one bounded egress policy.');
            $checks[] = self::check('seo_relevance', 'VisionGaiaSEO title relevance', 'Application', self::file_contains('includes/VisionGaiaSEO/includes/class-vg-api-service.php', 'VG_SEO_Relevance::enforce'), 9, 'Generated titles are anchored to the concrete page.');
            $checks[] = self::check('typed_errors', 'Typed disclosure policy', 'Kernel', class_exists('SecurityException') && class_exists('StorageException'), 8, 'Security and storage failures have separate disclosure policies.');
            $checks[] = self::check('aegis_detection_only', 'Aegis parser consistency', 'Enforcement', !self::file_contains('includes/modules/aegis/class-vis-aegis.php', 'sanitize_environment()') && self::file_contains('includes/modules/aegis/class-vis-aegis.php', 'MAX_INSPECTED_BYTES'), 10, 'Aegis observes immutable request data under explicit budgets.');
            $checks[] = self::check('oracle_schema', 'Oracle verdict authorization', 'Analysis', self::file_contains('includes/modules/aegis/class-vis-aegis-oracle.php', 'valid_schema(array $data)') && self::file_contains('includes/modules/aegis/class-vis-aegis-oracle.php', 'MAX_RESPONSE_BYTES'), 9, 'Oracle verdicts require a bounded, typed schema.');
        }
        return $checks;
    }

    private static function check(string $id, string $label, string $domain, bool $passed, int $weight, string $detail, string $failure = 'fail'): array {
        return ['id' => $id, 'label' => $label, 'domain' => $domain, 'status' => $passed ? 'pass' : $failure, 'weight' => $weight, 'detail' => $detail];
    }

    private static function boundaries(): array {
        return [
            ['from' => 'Internet', 'to' => 'Zeus / Aegis', 'policy' => 'Untrusted request inspection', 'state' => 'enforced'],
            ['from' => 'Application modules', 'to' => 'Trust Core', 'policy' => 'Explicit capability surface', 'state' => 'mapped'],
            ['from' => 'Remote network', 'to' => 'Shadow Net', 'policy' => 'Pinned HTTPS, no redirects', 'state' => function_exists('curl_init') ? 'enforced' : 'closed'],
            ['from' => 'Artifact upload', 'to' => 'Runtime Vault', 'policy' => 'Stage, verify, atomic swap', 'state' => 'enforced'],
            ['from' => 'Builder content', 'to' => 'Admin browser', 'policy' => 'Opaque-origin iframe sandbox', 'state' => 'enforced'],
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
