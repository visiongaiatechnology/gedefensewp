<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Policy_Compiler {
    public const PROFILES = ['compatible', 'balanced', 'strict', 'paranoid', 'custom', 'experimental_browser_zero_trust'];
    public const CSP_MODES = ['off', 'learning', 'report_only', 'enforce'];
    public const FETCH_MODES = ['off', 'audit', 'enforce_sensitive', 'strict'];
    public const TRUSTED_TYPES_MODES = ['off', 'report_only', 'gedefense_admin_only', 'strict_selected_surfaces'];
    public const COEP_MODES = ['off', 'require-corp', 'credentialless'];
    private const MAX_HEADER_BYTES = 16384;
    private const MAX_TOTAL_HEADER_BYTES = 32768;
    private const CSP_DIRECTIVES = ['default-src','script-src','script-src-elem','style-src','img-src','font-src','connect-src','frame-src','frame-ancestors','object-src','base-uri','form-action','worker-src','media-src','manifest-src','sandbox','require-trusted-types-for','trusted-types','report-uri'];
    private const PERMISSION_FEATURES = ['camera','microphone','geolocation','payment','usb','serial','bluetooth','fullscreen','display-capture','clipboard-read','clipboard-write','accelerometer','gyroscope','magnetometer'];

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public static function compile(array $config, string $surface): array {
        if (!in_array($surface, VIS_Titan_Surface_Resolver::all(), true)) throw new ValidationException('Unknown TITAN surface.');
        $profile = self::enum($config, 'titan_profile', self::PROFILES, 'balanced');
        $cspMode = self::enum($config, 'titan_csp_mode', self::CSP_MODES, $profile === 'compatible' ? 'off' : 'report_only');
        $fetchDefault = in_array($profile, ['compatible', 'balanced'], true) ? 'audit' : (in_array($profile, ['paranoid', 'experimental_browser_zero_trust'], true) ? 'strict' : 'enforce_sensitive');
        $fetchMode = self::enum($config, 'titan_fetch_mode', self::FETCH_MODES, $fetchDefault);
        $trustedTypes = self::enum($config, 'titan_trusted_types_mode', self::TRUSTED_TYPES_MODES, 'off');
        $coep = self::enum($config, 'titan_coep_mode', self::COEP_MODES, 'off');
        if ($profile !== 'experimental_browser_zero_trust' && $coep !== 'off') $coep = 'off';

        $nonceEnabled = !empty($config['titan_nonce_enabled']) && in_array($surface, [VIS_Titan_Surface_Resolver::LOGIN, VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN], true);
        $directives = self::cspDirectives($config, $profile, $surface, $nonceEnabled, $trustedTypes);
        $warnings = self::semanticWarnings($directives, $surface, $profile, $coep);
        $csp = self::serializeCsp($directives);
        $permissions = self::permissions($profile, $surface, $config);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => VIS_Titan_Surface_Resolver::isSensitive($surface) ? 'no-referrer' : 'strict-origin-when-cross-origin',
            'Permissions-Policy' => $permissions,
        ];
        if (in_array($surface, [VIS_Titan_Surface_Resolver::LOGIN, VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN, VIS_Titan_Surface_Resolver::ACTIVE_CONTENT_PREVIEW], true)) {
            $headers['X-Frame-Options'] = 'DENY';
        } elseif ($surface === VIS_Titan_Surface_Resolver::ADMIN) {
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
        }
        if ($cspMode !== 'off' && $csp !== '') {
            $headers[$cspMode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only'] = $csp;
        }
        if ($trustedTypes === 'report_only' && $surface === VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN) {
            $ttReport = self::serializeCsp(['require-trusted-types-for' => ["'script'"], 'trusted-types' => ['vgt-titan', 'default'], 'report-uri' => ['/wp-json/visiongaia/v1/titan/csp-report?surface=' . strtolower($surface)]]);
            if ($cspMode === 'report_only') $headers['Content-Security-Policy-Report-Only'] .= "; require-trusted-types-for 'script'; trusted-types default vgt-titan";
            else $headers['Content-Security-Policy-Report-Only'] = $ttReport;
            $warnings[] = ['level' => 'HIGH', 'code' => 'TRUSTED_TYPES_EXPERIMENTAL', 'message' => 'Trusted Types reports compatibility failures on the GeDefense admin surface.'];
        }
        if (in_array($surface, [VIS_Titan_Surface_Resolver::LOGIN, VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN], true)) {
            $headers['Cross-Origin-Opener-Policy'] = $profile === 'compatible' ? 'same-origin-allow-popups' : 'same-origin';
            $headers['Cross-Origin-Resource-Policy'] = 'same-site';
            $headers['Origin-Agent-Cluster'] = '?1';
            $headers['Cache-Control'] = 'no-store, private';
        }
        if ($surface === VIS_Titan_Surface_Resolver::ACTIVE_CONTENT_PREVIEW) {
            $headers['Cross-Origin-Opener-Policy'] = 'same-origin';
            $headers['Cross-Origin-Resource-Policy'] = 'same-origin';
            $headers['Origin-Agent-Cluster'] = '?1';
            $headers['Cache-Control'] = 'no-store, private';
        }
        if ($coep !== 'off' && in_array($surface, [VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN, VIS_Titan_Surface_Resolver::ACTIVE_CONTENT_PREVIEW], true)) {
            $headers['Cross-Origin-Embedder-Policy'] = $coep;
            $warnings[] = ['level' => 'HIGH', 'code' => 'EXPERIMENTAL_COEP', 'message' => 'COEP can block third-party frames, fonts and media.'];
        }
        if (!empty($config['titan_hsts_enabled'])) {
            $hsts = 'max-age=' . max(300, min(63072000, (int)($config['titan_hsts_max_age'] ?? 31536000)));
            if (!empty($config['titan_hsts_include_subdomains'])) $hsts .= '; includeSubDomains';
            if (!empty($config['titan_hsts_preload'])) {
                if (empty($config['titan_hsts_include_subdomains']) || (int)($config['titan_hsts_max_age'] ?? 31536000) < 31536000) {
                    throw new ValidationException('HSTS preload requires includeSubDomains and max-age of at least one year.');
                }
                $hsts .= '; preload';
                $warnings[] = ['level' => 'HIGH', 'code' => 'HSTS_PRELOAD', 'message' => 'HSTS preload has long-lived domain-wide consequences.'];
            }
            $headers['Strict-Transport-Security'] = $hsts;
        }

        self::validateHeaders($headers);
        ksort($headers, SORT_STRING);
        $canonical = ['profile' => $profile, 'surface' => $surface, 'csp_mode' => $cspMode, 'fetch_mode' => $fetchMode, 'trusted_types_mode' => $trustedTypes, 'headers' => $headers];
        $json = wp_json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return $canonical + [
            'nonce_enabled' => $nonceEnabled,
            'warnings' => array_values($warnings),
            'policy_hash' => hash('sha256', "GEDEFENSE:TITAN:POLICY:v1\0" . $json),
            'validation_state' => $warnings === [] ? 'PASS' : 'PASS_WITH_WARNINGS',
        ];
    }

    /** @param array<string, mixed> $config @return array<string, array<string, mixed>> */
    public static function compileAll(array $config): array {
        $compiled = [];
        foreach (VIS_Titan_Surface_Resolver::all() as $surface) $compiled[$surface] = self::compile($config, $surface);
        ksort($compiled, SORT_STRING);
        return $compiled;
    }

    /** @param array<string, list<string>> $directives */
    public static function serializeCsp(array $directives): string {
        $normalized = [];
        foreach ($directives as $name => $values) {
            if (!in_array($name, self::CSP_DIRECTIVES, true)) throw new ValidationException('Unsupported CSP directive.');
            $clean = [];
            foreach ($values as $value) {
                $value = trim($value);
                if (!self::validCspValue($value)) throw new SecurityException('CSP validation failed: invalid source expression.');
                $clean[$value] = true;
            }
            $values = array_keys($clean);
            sort($values, SORT_STRING);
            $normalized[$name] = $values;
        }
        ksort($normalized, SORT_STRING);
        $parts = [];
        foreach ($normalized as $name => $values) $parts[] = $name . ($values === [] ? '' : ' ' . implode(' ', $values));
        return implode('; ', $parts);
    }

    /** @param array<string, mixed> $config @return array<string, list<string>> */
    private static function cspDirectives(array $config, string $profile, string $surface, bool $nonceEnabled, string $trustedTypes): array {
        if ($surface === VIS_Titan_Surface_Resolver::ACTIVE_CONTENT_PREVIEW) {
            return ['sandbox' => [], 'default-src' => ["'none'"], 'img-src' => ["'self'", 'data:'], 'object-src' => ["'none'"], 'base-uri' => ["'none'"], 'form-action' => ["'none'"], 'frame-ancestors' => ["'none'"]];
        }
        $self = ["'self'"];
        $script = $self;
        $style = $self;
        if ($nonceEnabled) {
            $script[] = "'nonce-{nonce}'";
            $style[] = "'nonce-{nonce}'";
        } elseif (in_array($profile, ['compatible', 'balanced'], true)) {
            $script[] = "'unsafe-inline'";
            $style[] = "'unsafe-inline'";
        }
        foreach (self::origins($config, 'titan_script_origins') as $origin) $script[] = $origin;
        foreach (self::origins($config, 'titan_style_origins') as $origin) $style[] = $origin;
        $img = array_merge($self, ['data:'], self::origins($config, 'titan_img_origins'));
        $connect = array_merge($self, self::origins($config, 'titan_connect_origins'));
        $frames = self::origins($config, 'titan_frame_origins');
        if ($frames === []) $frames = ["'none'"];
        $directives = [
            'default-src' => $self,
            'script-src' => $script,
            'style-src' => $style,
            'img-src' => $img,
            'font-src' => $self,
            'connect-src' => $connect,
            'frame-src' => $frames,
            'frame-ancestors' => VIS_Titan_Surface_Resolver::isSensitive($surface) ? ["'none'"] : $self,
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'worker-src' => $self,
            'media-src' => $self,
            'manifest-src' => $self,
            'report-uri' => ['/wp-json/visiongaia/v1/titan/csp-report?surface=' . strtolower($surface)],
        ];
        $ttSurface = $trustedTypes === 'strict_selected_surfaces' || ($trustedTypes === 'gedefense_admin_only' && $surface === VIS_Titan_Surface_Resolver::GEDEFENSE_ADMIN);
        if ($ttSurface) {
            $directives['require-trusted-types-for'] = ["'script'"];
            $directives['trusted-types'] = ['vgt-titan', 'default'];
        }
        return $directives;
    }

    /** @param array<string, list<string>> $directives @return list<array{level:string,code:string,message:string}> */
    private static function semanticWarnings(array $directives, string $surface, string $profile, string $coep): array {
        $warnings = [];
        foreach ($directives as $name => $values) {
            if (in_array('*', $values, true)) $warnings[] = ['level' => in_array($name, ['script-src','object-src','frame-ancestors'], true) ? 'CRITICAL' : 'HIGH', 'code' => 'WILDCARD_' . strtoupper(str_replace('-', '_', $name)), 'message' => $name . ' contains a wildcard source.'];
            if (in_array("'unsafe-eval'", $values, true)) $warnings[] = ['level' => 'CRITICAL', 'code' => 'UNSAFE_EVAL', 'message' => 'Dynamic code evaluation is enabled.'];
            if (in_array("'unsafe-inline'", $values, true) && $name === 'script-src') $warnings[] = ['level' => 'HIGH', 'code' => 'UNSAFE_INLINE_SCRIPT', 'message' => 'Inline script execution is enabled for compatibility.'];
        }
        if ($coep !== 'off') $warnings[] = ['level' => 'HIGH', 'code' => 'COEP_COMPATIBILITY', 'message' => 'Cross-origin embeds require compatible CORP/CORS headers.'];
        if ($profile === 'experimental_browser_zero_trust') $warnings[] = ['level' => 'HIGH', 'code' => 'EXPERIMENTAL_PROFILE', 'message' => 'Experimental browser confinement may break plugins and themes.'];
        return $warnings;
    }

    /** @param array<string, mixed> $config @return list<string> */
    private static function origins(array $config, string $key): array {
        $raw = isset($config[$key]) && is_string($config[$key]) ? $config[$key] : '';
        $tokens = preg_split('/[\s,]+/', trim($raw)) ?: [];
        $out = [];
        foreach ($tokens as $token) {
            if ($token === '') continue;
            if (!self::validCspValue($token)) throw new SecurityException('CSP origin validation failed.');
            $out[$token] = true;
        }
        return array_keys($out);
    }

    /** @param array<string, mixed> $config */
    private static function permissions(string $profile, string $surface, array $config): string {
        $allowed = [];
        if (in_array($profile, ['compatible', 'balanced'], true) && $surface === VIS_Titan_Surface_Resolver::PUBLIC_FRONTEND) $allowed = ['fullscreen' => '(self)', 'payment' => '(self)'];
        if (isset($config['titan_permissions_self']) && is_string($config['titan_permissions_self'])) {
            foreach (preg_split('/[\s,]+/', $config['titan_permissions_self']) ?: [] as $feature) {
                $feature = strtolower(trim($feature));
                if ($feature !== '' && in_array($feature, self::PERMISSION_FEATURES, true)) $allowed[$feature] = '(self)';
            }
        }
        $parts = [];
        foreach (self::PERMISSION_FEATURES as $feature) $parts[] = $feature . '=' . ($allowed[$feature] ?? '()');
        return implode(', ', $parts);
    }

    /** @param array<string, string> $headers */
    private static function validateHeaders(array $headers): void {
        $total = 0;
        foreach ($headers as $name => $value) {
            if (preg_match('/^[A-Za-z0-9-]{1,64}$/D', $name) !== 1 || preg_match('/[\r\n\0]/', $value) === 1) throw new SecurityException('Header validation failed.');
            $bytes = strlen($name) + strlen($value) + 2;
            if ($bytes > self::MAX_HEADER_BYTES) throw new ValidationException('Compiled header exceeds size boundary.');
            $total += $bytes;
        }
        if ($total > self::MAX_TOTAL_HEADER_BYTES) throw new ValidationException('Compiled policy exceeds aggregate header boundary.');
    }

    private static function validCspValue(string $value): bool {
        if ($value === '' || preg_match('/[;\r\n\0]/', $value) === 1) return false;
        if (in_array($value, ["'self'", "'none'", "'unsafe-inline'", "'unsafe-eval'", "'strict-dynamic'", "'script'", '*', 'data:', 'blob:', 'https:', 'http:', 'vgt-titan', 'default'], true)) return true;
        if ($value === "'nonce-{nonce}'" || preg_match("/^'(?:nonce-[A-Za-z0-9+\/=]{8,}|sha(?:256|384|512)-[A-Za-z0-9+\/=]{20,})'$/D", $value) === 1) return true;
        if (preg_match('~^https://(?:\*\.)?[A-Za-z0-9.-]+(?::[0-9]{1,5})?(?:/[^\s;]*)?$~D', $value) === 1) return true;
        return str_starts_with($value, '/wp-json/visiongaia/');
    }

    /** @param array<string, mixed> $config @param list<string> $allowed */
    private static function enum(array $config, string $key, array $allowed, string $default): string {
        $value = isset($config[$key]) && is_string($config[$key]) ? strtolower($config[$key]) : $default;
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
