<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Envelope {

    public const DEFAULT_ALLOWED_METHODS = ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'];
    public const DEFAULT_MAX_QUERY_LENGTH = 2048;
    public const DEFAULT_MAX_QUERY_PARAMS = 100;
    public const DEFAULT_MAX_HEADER_COUNT = 50;
    public const DEFAULT_MAX_HEADER_SIZE = 16384;
    public const DEFAULT_MAX_COOKIE_SIZE = 8192;
    public const DEFAULT_MAX_URI_LENGTH = 4096;
    public const DEFAULT_MAX_PATH_DEPTH = 32;

    /**
     * Cheap structural validation of incoming request envelope.
     * Evaluates HTTP method, Host, URI structure, canonicalization, header sizes, and encoding.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $get
     * @param array<string, mixed> $cookie
     * @param array<string, mixed> $config
     * @return array{rule_id: string, reason: string, severity: int, event_type: string, status_code: int}|null
     */
    public static function evaluate(array $server, array $get, array $cookie, array $config): ?array {
        // 1. HTTP METHOD VALIDATION
        $method = strtoupper(trim((string)($server['REQUEST_METHOD'] ?? 'GET')));
        if ($method === '' || !preg_match('/^[A-Z]{3,10}$/D', $method)) {
            return [
                'rule_id' => 'ENV_INVALID_METHOD_FORMAT',
                'reason' => 'HTTP request method is syntactically invalid or malformed.',
                'severity' => 8,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 400
            ];
        }

        $allowedMethods = $config['allowed_methods'] ?? self::DEFAULT_ALLOWED_METHODS;
        if (is_array($allowedMethods) && !in_array($method, $allowedMethods, true)) {
            return [
                'rule_id' => 'ENV_METHOD_NOT_ALLOWED',
                'reason' => sprintf('HTTP method %s is not permitted by edge policy.', $method),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_CONTRACT_VIOLATION',
                'status_code' => 405
            ];
        }

        // 2. RAW & NORMALIZED URI EVALUATION
        $rawUri = (string)($server['REQUEST_URI'] ?? '/');
        $uriLength = strlen($rawUri);
        $maxUriLength = (int)($config['max_uri_length'] ?? self::DEFAULT_MAX_URI_LENGTH);
        if ($uriLength > $maxUriLength) {
            return [
                'rule_id' => 'ENV_URI_TOO_LONG',
                'reason' => sprintf('Request URI length (%d bytes) exceeds boundary ceiling (%d bytes).', $uriLength, $maxUriLength),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 414
            ];
        }

        // 3. CANONICALIZATION GUARD (Ambiguous Path & Encoding Blocker)
        $canonViolation = self::checkCanonicalization($rawUri);
        if ($canonViolation !== null) {
            return $canonViolation;
        }

        // 4. HOST HEADER VALIDATION (HOST LOCK)
        $hostLockResult = self::checkHostLock($server, $config);
        if ($hostLockResult !== null) {
            return $hostLockResult;
        }

        // 5. QUERY STRING BOUNDARY CEILINGS
        $queryString = (string)($server['QUERY_STRING'] ?? '');
        $maxQueryLength = (int)($config['max_query_length'] ?? self::DEFAULT_MAX_QUERY_LENGTH);
        if (strlen($queryString) > $maxQueryLength) {
            return [
                'rule_id' => 'ENV_QUERY_TOO_LONG',
                'reason' => sprintf('Query string length (%d bytes) exceeds ceiling (%d bytes).', strlen($queryString), $maxQueryLength),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 414
            ];
        }

        $paramCount = count($get);
        $maxParams = (int)($config['max_query_params'] ?? self::DEFAULT_MAX_QUERY_PARAMS);
        if ($paramCount > $maxParams) {
            return [
                'rule_id' => 'ENV_EXCESSIVE_QUERY_PARAMS',
                'reason' => sprintf('Query parameter count (%d) exceeds boundary (%d).', $paramCount, $maxParams),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 400
            ];
        }

        // 6. HEADER COUNT & TOTAL SIZE BOUNDS
        $headerCheck = self::checkHeaders($server, $config);
        if ($headerCheck !== null) {
            return $headerCheck;
        }

        // 7. COOKIE BOUNDARY CEILING
        $cookieSize = 0;
        foreach ($cookie as $ck => $cv) {
            $cookieSize += strlen((string)$ck) + strlen((string)$cv);
        }
        $maxCookieSize = (int)($config['max_cookie_size'] ?? self::DEFAULT_MAX_COOKIE_SIZE);
        if ($cookieSize > $maxCookieSize) {
            return [
                'rule_id' => 'ENV_COOKIE_SIZE_EXCEEDED',
                'reason' => sprintf('Total cookie header payload (%d bytes) exceeds boundary (%d bytes).', $cookieSize, $maxCookieSize),
                'severity' => 6,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 400
            ];
        }

        // 8. CONTENT-LENGTH & CONTENT-TYPE ENVELOPE SANITY
        $contentLength = (int)($server['CONTENT_LENGTH'] ?? 0);
        if ($contentLength < 0) {
            return [
                'rule_id' => 'ENV_NEGATIVE_CONTENT_LENGTH',
                'reason' => 'Content-Length header contains negative or malformed integer value.',
                'severity' => 8,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 400
            ];
        }

        $contentType = (string)($server['CONTENT_TYPE'] ?? '');
        if ($contentType !== '' && strlen($contentType) > 256) {
            return [
                'rule_id' => 'ENV_CONTENT_TYPE_OVERSIZED',
                'reason' => 'Content-Type header exceeds permissible structural boundary.',
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 400
            ];
        }

        return null;
    }

    /**
     * Canonicalization Guard: Identifies ambiguous, polyglot, or hostile path representations.
     * Rejects input without dangerous heuristic "repair".
     *
     * @return array{rule_id: string, reason: string, severity: int, event_type: string, status_code: int}|null
     */
        public static function checkCanonicalization(string $rawUri): ?array {
        // Separate RAW PATH from QUERY STRING
        $parts = explode('?', $rawUri, 2);
        $rawPath = $parts[0];
        $rawQuery = $parts[1] ?? '';

        // A. Null Byte Injection (Checked across entire URI)
        if (str_contains($rawUri, "\0") || stripos($rawUri, '%00') !== false) {
            return [
                'rule_id' => 'CANON_NULL_BYTE_DETECTED',
                'reason' => 'Null byte character detected in request target.',
                'severity' => 9,
                'event_type' => 'ZEUS.CANONICALIZATION_REJECT',
                'status_code' => 400
            ];
        }

        // B. Double Percent-Encoding (%25xx) (Checked across entire URI)
        if (preg_match('/%25[0-9a-fA-F]{2}/', $rawUri)) {
            return [
                'rule_id' => 'CANON_DOUBLE_ENCODING',
                'reason' => 'Double percent-encoding (%25) detected in request URI.',
                'severity' => 8,
                'event_type' => 'ZEUS.CANONICALIZATION_REJECT',
                'status_code' => 400
            ];
        }

        // C. Encoded Slash / Backslash Ambiguity in RAW PATH ONLY (%2f, %2F, %5c, %5C)
        if (preg_match('/%(?:2f|5c)/i', $rawPath)) {
            return [
                'rule_id' => 'CANON_ENCODED_SLASH_AMBIGUITY',
                'reason' => 'Ambiguous encoded slash or backslash (%2F / %5C) detected in request path.',
                'severity' => 8,
                'event_type' => 'ZEUS.CANONICALIZATION_REJECT',
                'status_code' => 400
            ];
        }

        // D. Raw Backslash in PATH ONLY (Windows / IIS routing confusion)
        if (str_contains($rawPath, '\\')) {
            return [
                'rule_id' => 'CANON_BACKSLASH_PATH_AMBIGUITY',
                'reason' => 'Unescaped backslash character detected in request path.',
                'severity' => 7,
                'event_type' => 'ZEUS.CANONICALIZATION_REJECT',
                'status_code' => 400
            ];
        }

        // E. Dot-Segment Path Traversal (.. in RAW PATH ONLY)
        if (preg_match('/(?:\/|^)\.\.(?:\/|$)/', $rawPath)) {
            return [
                'rule_id' => 'CANON_DOT_SEGMENT_TRAVERSAL',
                'reason' => 'Directory traversal / relative dot-segment (..) detected in request path.',
                'severity' => 9,
                'event_type' => 'ZEUS.CANONICALIZATION_REJECT',
                'status_code' => 400
            ];
        }

        // F. Duplicate Path Separators in RAW PATH ONLY (//, ///)
        if (str_contains($rawPath, '//')) {
            return [
                'rule_id' => 'CANON_DUPLICATE_PATH_SEPARATORS',
                'reason' => 'Ambiguous duplicate path separators (//) detected in request path.',
                'severity' => 6,
                'event_type' => 'ZEUS.CANONICALIZATION_REJECT',
                'status_code' => 400
            ];
        }

        // G. Path Depth Boundary (Maximum 32 levels)
        $pathDepth = substr_count(trim($rawPath, '/'), '/');
        if ($pathDepth > self::DEFAULT_MAX_PATH_DEPTH) {
            return [
                'rule_id' => 'CANON_PATH_DEPTH_EXCEEDED',
                'reason' => sprintf('Request path depth (%d levels) exceeds maximum ceiling (%d levels).', $pathDepth, self::DEFAULT_MAX_PATH_DEPTH),
                'severity' => 7,
                'event_type' => 'ZEUS.CANONICALIZATION_REJECT',
                'status_code' => 400
            ];
        }

        return null;
    }

    /**
     * Evaluates Host header against RFC standards and canonical host whitelist.
     */
    public static function checkHostLock(array $server, array $config): ?array {
        $hostMode = (string)($config['host_lock_mode'] ?? 'DISABLED');
        if ($hostMode === 'DISABLED') {
            return null;
        }

        $hostHeader = trim((string)($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? ''));
        if ($hostHeader === '') {
            return [
                'rule_id' => 'HOST_HEADER_MISSING',
                'reason' => 'HTTP Host header is empty or missing from incoming request envelope.',
                'severity' => 8,
                'event_type' => 'ZEUS.HOST_REJECT',
                'status_code' => 400
            ];
        }

        // Strip port if present (e.g., example.com:8443)
        $hostClean = strtolower((string)preg_replace('/:\d+$/', '', $hostHeader));

        // Basic host syntax validation (RFC 1123 / RFC 952)
        if (!preg_match('/^[a-z0-9](?:[a-z0-9\-\.]{0,253}[a-z0-9])?$/D', $hostClean)) {
            return [
                'rule_id' => 'HOST_HEADER_MALFORMED',
                'reason' => sprintf('HTTP Host header "%s" contains illegal characters or formatting.', substr($hostHeader, 0, 64)),
                'severity' => 8,
                'event_type' => 'ZEUS.HOST_REJECT',
                'status_code' => 400
            ];
        }

        $canonicalHosts = $config['canonical_hosts'] ?? [];
        if (!is_array($canonicalHosts) || empty($canonicalHosts)) {
            return null;
        }

        $canonicalHosts = array_map('strtolower', array_map('trim', $canonicalHosts));
        if (!in_array($hostClean, $canonicalHosts, true)) {
            if ($hostMode === 'REJECT') {
                return [
                    'rule_id' => 'HOST_LOCK_REJECT',
                    'reason' => sprintf('Host header "%s" does not match any configured canonical host.', substr($hostHeader, 0, 64)),
                    'severity' => 8,
                    'event_type' => 'ZEUS.HOST_REJECT',
                    'status_code' => 421 // Misdirected Request
                ];
            }
        }

        return null;
    }

    /**
     * Header Count and Size inspection.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $config
     * @return array{rule_id: string, reason: string, severity: int, event_type: string, status_code: int}|null
     */
    private static function checkHeaders(array $server, array $config): ?array {
        $headerCount = 0;
        $totalHeaderSize = 0;

        foreach ($server as $k => $v) {
            if (str_starts_with((string)$k, 'HTTP_')) {
                $headerCount++;
                $totalHeaderSize += strlen((string)$k) + strlen((string)$v);
            }
        }

        $maxCount = (int)($config['max_header_count'] ?? self::DEFAULT_MAX_HEADER_COUNT);
        if ($headerCount > $maxCount) {
            return [
                'rule_id' => 'ENV_EXCESSIVE_HEADERS',
                'reason' => sprintf('Header count (%d) exceeds boundary ceiling (%d).', $headerCount, $maxCount),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 400
            ];
        }

        $maxSize = (int)($config['max_header_size'] ?? self::DEFAULT_MAX_HEADER_SIZE);
        if ($totalHeaderSize > $maxSize) {
            return [
                'rule_id' => 'ENV_HEADER_SIZE_EXCEEDED',
                'reason' => sprintf('Total header size (%d bytes) exceeds boundary (%d bytes).', $totalHeaderSize, $maxSize),
                'severity' => 7,
                'event_type' => 'ZEUS.REQUEST_MALFORMED',
                'status_code' => 431 // Request Header Fields Too Large
            ];
        }

        return null;
    }
}
