<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Violation_Collector {
    private const MAX_BODY_BYTES = 16384;
    private const MAX_RECORDS = 200;

    public static function register(): void {
        register_rest_route('visiongaia/v1', '/titan/csp-report', ['methods' => 'POST', 'callback' => [self::class, 'collect'], 'permission_callback' => '__return_true']);
    }

    private static function tokenSecret(): string {
        $salt = function_exists('wp_salt') ? (string)wp_salt('auth') : (defined('AUTH_SALT') ? (string)AUTH_SALT : '');
        if (strlen($salt) < 16) {
            $salt = hash('sha256', (string)get_option('vgt_sentinel_vault_system_salt', ABSPATH));
        }
        return hash('sha256', "GEDEFENSE:TITAN:CSP-REPORT:v1\0" . $salt, true);
    }

    public static function issueReportToken(string $surface): string {
        $exp = time() + 3600;
        $payload = strtoupper($surface) . '|' . $exp;
        $sig = hash_hmac('sha256', $payload, self::tokenSecret());
        return $exp . '.' . substr($sig, 0, 32);
    }

    public static function verifyReportToken(string $token, string $surface): bool {
        if ($token === '' || !str_contains($token, '.')) {
            return false;
        }
        [$expRaw, $sigRaw] = explode('.', $token, 2);
        if (!ctype_digit($expRaw) || preg_match('/^[a-f0-9]{32}$/D', $sigRaw) !== 1) {
            return false;
        }
        $exp = (int)$expRaw;
        if ($exp < time() || $exp > time() + 7200) {
            return false;
        }
        $expected = substr(hash_hmac('sha256', strtoupper($surface) . '|' . $exp, self::tokenSecret()), 0, 32);
        return hash_equals($expected, $sigRaw);
    }

    public static function collect($request) {
        try {
            $contentType = strtolower((string)($request->get_header('content-type') ?? ''));
            if (!str_starts_with($contentType, 'application/csp-report') && !str_starts_with($contentType, 'application/reports+json') && !str_starts_with($contentType, 'application/json')) {
                throw new SecurityException('CSP report content type rejected.');
            }
            $body = (string)$request->get_body();
            if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) throw new ValidationException('CSP report size boundary violation.');
            $surfaceInput = is_object($request) && method_exists($request, 'get_param') ? strtoupper((string)$request->get_param('surface')) : '';
            $surface = in_array($surfaceInput, VIS_Titan_Surface_Resolver::all(), true) ? $surfaceInput : VIS_Titan_Surface_Resolver::resolve();
            $reportToken = is_object($request) && method_exists($request, 'get_param') ? (string)$request->get_param('token') : '';
            // Unauthentifiziertes Vergiften von vis_titan_violations verhindern (FIND-008).
            if (!self::verifyReportToken($reportToken, $surface)) {
                throw new SecurityException('CSP report token rejected.');
            }
            self::rateLimit();
            $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            $report = self::extractReport($decoded);
            $docOrigin = self::origin((string)($report['document-uri'] ?? $report['url'] ?? ''));
            $siteHost = function_exists('home_url') ? strtolower((string)parse_url((string)home_url('/'), PHP_URL_HOST)) : '';
            $docHost = $docOrigin !== '' ? strtolower((string)parse_url($docOrigin, PHP_URL_HOST)) : '';
            if ($siteHost !== '' && ($docHost === '' || !hash_equals($siteHost, $docHost))) {
                throw new SecurityException('CSP report document origin mismatch.');
            }
            $record = [
                'surface' => $surface,
                'directive' => self::token((string)($report['effective-directive'] ?? $report['violated-directive'] ?? $report['body']['effectiveDirective'] ?? 'unknown'), 64),
                'blocked_origin' => self::origin((string)($report['blocked-uri'] ?? $report['body']['blockedURL'] ?? '')),
                'document_origin' => $docOrigin,
                'disposition' => self::token((string)($report['disposition'] ?? $report['body']['disposition'] ?? 'report'), 16),
            ];
            $key = hash('sha256', implode('|', $record));
            $records = get_option('vis_titan_violations', []);
            $records = is_array($records) ? $records : [];
            $existing = is_array($records[$key] ?? null) ? $records[$key] : [];
            $count = min(4294967295, (int)($existing['count'] ?? 0) + 1);
            $records[$key] = $record + ['count' => $count, 'first_seen' => (string)($existing['first_seen'] ?? gmdate('c')), 'last_seen' => gmdate('c')];
            uasort($records, static fn(array $a, array $b): int => strcmp((string)($b['last_seen'] ?? ''), (string)($a['last_seen'] ?? '')));
            $records = array_slice($records, 0, self::MAX_RECORDS, true);
            update_option('vis_titan_violations', $records, false);
            if ($count === 1 || ($count & ($count - 1)) === 0) self::emitAggregate($record, $count);
            return new WP_REST_Response(null, 204);
        } catch (ValidationException $e) {
            return new WP_Error('titan_report_invalid', $e->getMessage(), ['status' => 422]);
        } catch (SecurityException $e) {
            error_log('[TITAN REPORT SECURITY] ' . $e->getMessage());
            return new WP_Error('titan_report_rejected', 'Request rejected for security reasons.', ['status' => 403]);
        } catch (StorageException $e) {
            error_log('[TITAN REPORT STORAGE] ' . $e->getMessage());
            return new WP_Error('titan_report_storage', 'A server error occurred.', ['status' => 500]);
        } catch (Throwable $e) {
            error_log('[TITAN REPORT FATAL] ' . $e->getMessage());
            return new WP_Error('titan_report_fault', 'Critical system fault.', ['status' => 500]);
        }
    }

    private static function rateLimit(): void {
        $ip = class_exists('VIS_Security') ? VIS_Security::client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!class_exists('VIS_Security') || !VIS_Security::rate_limit('titan:csp-report:' . $ip, 20, 60)) {
            throw new SecurityException('CSP report rate limit exceeded.');
        }
    }

    /** @return array<string, mixed> */
    private static function extractReport(mixed $decoded): array {
        if (is_array($decoded) && isset($decoded['csp-report']) && is_array($decoded['csp-report'])) return $decoded['csp-report'];
        if (is_array($decoded) && array_is_list($decoded) && is_array($decoded[0] ?? null)) return $decoded[0];
        if (is_array($decoded)) return $decoded;
        throw new ValidationException('CSP report schema invalid.');
    }

    private static function origin(string $url): string {
        if ($url === '' || in_array($url, ['inline','eval','self'], true)) return substr($url, 0, 32);
        $parts = wp_parse_url($url);
        if (!is_array($parts)) return 'redacted';
        $scheme = isset($parts['scheme']) ? strtolower((string)$parts['scheme']) : '';
        $host = isset($parts['host']) ? strtolower((string)$parts['host']) : '';
        if (!in_array($scheme, ['http','https','data','blob'], true)) return 'redacted';
        if (in_array($scheme, ['data','blob'], true)) return $scheme . ':';
        if ($host === '' || preg_match('/^[a-z0-9.-]{1,253}$/D', $host) !== 1) return 'redacted';
        return $scheme . '://' . $host . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    }

    private static function token(string $value, int $limit): string {
        return substr(strtolower(preg_replace('/[^a-z0-9_-]/i', '_', $value) ?? 'unknown'), 0, $limit);
    }

    /** @param array<string, string> $record */
    private static function emitAggregate(array $record, int $count): void {
        $fabric = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
        if (!class_exists($fabric)) return;
        $fabric::ingest(['sensor' => 'TITAN', 'category' => 'CONFIGURATION', 'event_type' => 'TITAN_CSP_VIOLATION', 'role' => 'CONTEXT', 'severity' => 2, 'confidence' => 25, 'score' => 0, 'actor_ip' => '', 'user_id' => 0, 'entity_type' => 'POLICY', 'entity_id' => 'titan-csp:' . hash('sha256', implode('|', $record)), 'vector' => 'CSP_POLICY_CONTEXT', 'action_type' => 'OBSERVE', 'outcome' => 'AGGREGATED', 'metadata' => ['surface' => $record['surface'], 'directive' => $record['directive'], 'blocked_origin' => $record['blocked_origin'], 'count' => $count]]);
    }
}
