<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Contracts {

    /**
     * Returns default built-in contracts for critical WordPress surfaces.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getDefaultContracts(): array {
        return [
            'wp_login' => [
                'name' => 'WordPress Login Portal',
                'match_type' => 'EXACT',
                'path' => '/wp-login.php',
                'methods' => ['GET', 'POST'],
                'max_body_bytes' => 65536, // 64 KB
                'allowed_content_types' => ['application/x-www-form-urlencoded'],
                'max_query_length' => 1024,
                'max_query_params' => 20,
                'cross_site_policy' => 'DENY',
                'admission_required' => false,
                'rate_budget' => ['limit' => 20, 'window' => 60],
                'status' => 'ACTIVE',
                'is_system' => true,
            ],
            'wp_admin' => [
                'name' => 'WordPress Admin Core',
                'match_type' => 'PREFIX',
                'path' => '/wp-admin/',
                'methods' => ['GET', 'POST', 'HEAD'],
                'max_body_bytes' => 33554432, // 32 MB for uploads
                'allowed_content_types' => ['*'],
                'max_query_length' => 2048,
                'max_query_params' => 50,
                'cross_site_policy' => 'ALLOW',
                'admission_required' => false,
                'rate_budget' => ['limit' => 120, 'window' => 60],
                'status' => 'ACTIVE',
                'is_system' => true,
            ],
            'admin_ajax' => [
                'name' => 'WordPress AJAX Gateway',
                'match_type' => 'EXACT',
                'path' => '/wp-admin/admin-ajax.php',
                'methods' => ['GET', 'POST'],
                'max_body_bytes' => 16777216, // 16 MB
                'allowed_content_types' => ['application/x-www-form-urlencoded', 'multipart/form-data', 'application/json'],
                'max_query_length' => 2048,
                'max_query_params' => 50,
                'cross_site_policy' => 'ALLOW',
                'admission_required' => false,
                'rate_budget' => ['limit' => 300, 'window' => 60],
                'status' => 'ACTIVE',
                'is_system' => true,
            ],
            'admin_post' => [
                'name' => 'WordPress Admin POST Gateway',
                'match_type' => 'EXACT',
                'path' => '/wp-admin/admin-post.php',
                'methods' => ['GET', 'POST'],
                'max_body_bytes' => 16777216,
                'allowed_content_types' => ['application/x-www-form-urlencoded', 'multipart/form-data'],
                'max_query_length' => 2048,
                'max_query_params' => 50,
                'cross_site_policy' => 'ALLOW',
                'admission_required' => false,
                'rate_budget' => ['limit' => 120, 'window' => 60],
                'status' => 'ACTIVE',
                'is_system' => true,
            ],
            'wp_json' => [
                'name' => 'WordPress REST API',
                'match_type' => 'PREFIX',
                'path' => '/wp-json/',
                'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'max_body_bytes' => 16777216, // 16 MB
                'allowed_content_types' => ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data'],
                'max_query_length' => 2048,
                'max_query_params' => 50,
                'cross_site_policy' => 'ALLOW',
                'admission_required' => false,
                'rate_budget' => ['limit' => 300, 'window' => 60],
                'status' => 'ACTIVE',
                'is_system' => true,
            ],
            'xmlrpc' => [
                'name' => 'XML-RPC Legacy Interface',
                'match_type' => 'EXACT',
                'path' => '/xmlrpc.php',
                'methods' => ['POST'],
                'max_body_bytes' => 65536,
                'allowed_content_types' => ['text/xml', 'application/xml'],
                'max_query_length' => 256,
                'max_query_params' => 5,
                'cross_site_policy' => 'DENY',
                'admission_required' => false,
                'rate_budget' => ['limit' => 10, 'window' => 60],
                'status' => 'DISABLED', // Terminated by default
                'is_system' => true,
            ],
            'wp_cron' => [
                'name' => 'WordPress Virtual Cron',
                'match_type' => 'EXACT',
                'path' => '/wp-cron.php',
                'methods' => ['GET', 'POST'],
                'max_body_bytes' => 8192,
                'allowed_content_types' => ['*'],
                'max_query_length' => 512,
                'max_query_params' => 10,
                'cross_site_policy' => 'ALLOW',
                'admission_required' => false,
                'rate_budget' => ['limit' => 60, 'window' => 60],
                'status' => 'ACTIVE',
                'is_system' => true,
            ],
            'wp_comments_post' => [
                'name' => 'WordPress Comments Gateway',
                'match_type' => 'EXACT',
                'path' => '/wp-comments-post.php',
                'methods' => ['POST'],
                'max_body_bytes' => 65536,
                'allowed_content_types' => ['application/x-www-form-urlencoded', 'multipart/form-data'],
                'max_query_length' => 512,
                'max_query_params' => 10,
                'cross_site_policy' => 'DENY',
                'admission_required' => false,
                'rate_budget' => ['limit' => 15, 'window' => 60],
                'status' => 'ACTIVE',
                'is_system' => true,
            ],
        ];
    }

    /**
     * Evaluates incoming request against compiled Route Contracts.
     *
     * @param string $path
     * @param string $method
     * @param int $contentLength
     * @param string $contentType
     * @param int $queryLength
     * @param int $paramCount
     * @param array<string, mixed> $contracts
     * @return array{rule_id: string, reason: string, severity: int, event_type: string, status_code: int}|null
     */
    public static function evaluate(
        string $path,
        string $method,
        int $contentLength,
        string $contentType,
        int $queryLength,
        int $paramCount,
        array $contracts
    ): ?array {
        $cleanPath = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?? '/', '/');

        // Find most specific matching contract
        $matchedContract = null;
        $longestMatchLen = -1;

        foreach ($contracts as $id => $contract) {
            if (($contract['status'] ?? 'ACTIVE') !== 'ACTIVE') {
                // If contract is explicitly DISABLED and exact match (like xmlrpc.php), reject immediately!
                if (($contract['status'] ?? '') === 'DISABLED' && ($contract['path'] ?? '') === $cleanPath) {
                    return [
                        'rule_id' => 'CONTRACT_ROUTE_DISABLED',
                        'reason' => sprintf('Access to route "%s" is disabled by route contract.', $cleanPath),
                        'severity' => 8,
                        'event_type' => 'ZEUS.REQUEST_CONTRACT_VIOLATION',
                        'status_code' => 403
                    ];
                }
                continue;
            }

            $cPath = (string)($contract['path'] ?? '');
            $matchType = strtoupper((string)($contract['match_type'] ?? 'EXACT'));

            if ($matchType === 'EXACT') {
                if ($cleanPath === $cPath && strlen($cPath) > $longestMatchLen) {
                    $matchedContract = $contract;
                    $longestMatchLen = strlen($cPath);
                }
            } elseif ($matchType === 'PREFIX') {
                if (str_starts_with($cleanPath, $cPath) && strlen($cPath) > $longestMatchLen) {
                    $matchedContract = $contract;
                    $longestMatchLen = strlen($cPath);
                }
            }
        }

        if ($matchedContract === null) {
            return null; // No custom contract applied, passes to standard envelope
        }

        // 1. Method Check
        $allowedMethods = $matchedContract['methods'] ?? ['GET', 'POST'];
        if (is_array($allowedMethods) && !in_array($method, $allowedMethods, true)) {
            return [
                'rule_id' => 'CONTRACT_METHOD_VIOLATION',
                'reason' => sprintf('HTTP method %s is not permitted on route "%s" by contract.', $method, $cleanPath),
                'severity' => 8,
                'event_type' => 'ZEUS.REQUEST_CONTRACT_VIOLATION',
                'status_code' => 405
            ];
        }

        // 2. Max Body Ceiling
        $maxBody = (int)($matchedContract['max_body_bytes'] ?? 65536);
        if ($contentLength > $maxBody) {
            return [
                'rule_id' => 'CONTRACT_BODY_LIMIT_EXCEEDED',
                'reason' => sprintf('Request body size (%d bytes) exceeds route ceiling (%d bytes).', $contentLength, $maxBody),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_CONTRACT_VIOLATION',
                'status_code' => 413 // Payload Too Large
            ];
        }

        // 3. Content-Type Check
        $allowedTypes = $matchedContract['allowed_content_types'] ?? ['*'];
        if ($contentLength > 0 && is_array($allowedTypes) && !in_array('*', $allowedTypes, true)) {
            $baseContentType = strtolower(trim(explode(';', $contentType)[0]));
            if ($baseContentType !== '' && !in_array($baseContentType, $allowedTypes, true)) {
                return [
                    'rule_id' => 'CONTRACT_CONTENT_TYPE_VIOLATION',
                    'reason' => sprintf('Content-Type "%s" is not permitted on route "%s".', substr($baseContentType, 0, 64), $cleanPath),
                    'severity' => 7,
                    'event_type' => 'ZEUS.REQUEST_CONTRACT_VIOLATION',
                    'status_code' => 415 // Unsupported Media Type
                ];
            }
        }

        // 4. Query Limits
        $maxQuery = (int)($matchedContract['max_query_length'] ?? 2048);
        if ($queryLength > $maxQuery) {
            return [
                'rule_id' => 'CONTRACT_QUERY_LIMIT_EXCEEDED',
                'reason' => sprintf('Query string length (%d bytes) exceeds route ceiling (%d bytes).', $queryLength, $maxQuery),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_CONTRACT_VIOLATION',
                'status_code' => 414
            ];
        }

        $maxParams = (int)($matchedContract['max_query_params'] ?? 50);
        if ($paramCount > $maxParams) {
            return [
                'rule_id' => 'CONTRACT_PARAM_COUNT_EXCEEDED',
                'reason' => sprintf('Query parameter count (%d) exceeds route boundary (%d).', $paramCount, $maxParams),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_CONTRACT_VIOLATION',
                'status_code' => 400
            ];
        }

        return null;
    }
}
