<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final readonly class XdrEvent {
    public const SCHEMA_VERSION = 2;
    public const ROLES = ['DETECTION', 'CONFIRMATION', 'CONTEXT', 'RESPONSE'];
    public const CATEGORIES = [
        'INGRESS', 'BEHAVIOR', 'IDENTITY', 'INTEGRITY',
        'FILESYSTEM', 'MALWARE', 'RUNTIME', 'EGRESS',
        'DECEPTION', 'AUTHENTICATION', 'CONFIGURATION'
    ];
    public const CAUSAL_EDGES = [
        'SAME_EXECUTION_CHAIN',
        'SAME_REQUEST', 'SAME_ACTOR', 'SAME_ENTITY', 'PRECEDES',
        'CONFIRMS', 'TRIGGERED_RESPONSE', 'RELATED_FILE',
        'RELATED_EGRESS', 'RELATED_SESSION', 'INDEPENDENT'
    ];

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $eventId,
        public string $timestamp,
        public string $sensor,
        public string $category,
        public string $eventType,
        public string $role,
        public int $severity,
        public int $confidence,
        public float $score,
        public int $attributionConfidence,
        public string $requestId,
        public string $correlationId,
        public string $executionChainId,
        public string $actorIp,
        public int $userId,
        public string $sessionId,
        public string $entityType,
        public string $entityId,
        public string $componentKey,
        public string $route,
        public string $vector,
        public string $actionType,
        public string $outcome,
        public string $privacyClass,
        public ?int $causalParentId,
        public string $causalEdge,
        public array $metadata,
    ) {}

    /** @param array<string, mixed> $event */
    public static function fromArray(array $event): self {
        $routeCandidate = (string)($event['route'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        $systemExecution = self::isSystemExecution($routeCandidate);
        $sensor = self::token((string)($event['sensor'] ?? $event['module'] ?? 'SYSTEM'), 32);
        $category = self::token((string)($event['category'] ?? self::categoryFor($sensor)), 32);
        if (!in_array($category, self::CATEGORIES, true)) $category = 'RUNTIME';

        $role = strtoupper(trim((string)($event['role'] ?? ($systemExecution ? 'CONTEXT' : self::defaultRoleFor($sensor, (string)($event['event_type'] ?? $event['type'] ?? ''))))));
        if (!in_array($role, self::ROLES, true)) $role = 'DETECTION';

        $ipSource = $systemExecution && !array_key_exists('actor_ip', $event) ? '' : (string)($event['actor_ip'] ?? $event['ip'] ?? '');
        $ip = filter_var($ipSource, FILTER_VALIDATE_IP) ?: '';
        if (in_array($ip, ['0.0.0.0', '::'], true)) $ip = '';

        $requestId = self::hexId((string)($event['request_id'] ?? '')) ?: RequestContext::id();
        $correlationId = self::hexId((string)($event['correlation_id'] ?? '')) ?: $requestId;
        $executionChainId = self::hexId((string)($event['execution_chain_id'] ?? '')) ?: $requestId;

        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : (is_array($event['context'] ?? null) ? $event['context'] : []);

        $causalEdge = strtoupper(trim((string)($event['causal_edge'] ?? 'SAME_REQUEST')));
        if (!in_array($causalEdge, self::CAUSAL_EDGES, true)) $causalEdge = 'SAME_REQUEST';

        $causalParentId = isset($event['causal_parent_id']) && is_numeric($event['causal_parent_id']) ? (int)$event['causal_parent_id'] : null;

        $severity = max(1, min(10, (int)($event['severity'] ?? 1)));
        $confidence = max(0, min(100, (int)($event['confidence'] ?? self::defaultConfidence($severity, $role))));
        $attributionConf = max(0, min(100, (int)($event['attribution_confidence'] ?? 100)));

        return new self(
            bin2hex(random_bytes(16)),
            gmdate('Y-m-d H:i:s'),
            $sensor,
            $category,
            self::token((string)($event['event_type'] ?? $event['type'] ?? 'EVENT'), 64),
            $role,
            $severity,
            $confidence,
            max(0.0, min(1000.0, (float)($event['score'] ?? 0.0))),
            $attributionConf,
            $requestId,
            $correlationId,
            $executionChainId,
            $ip,
            $systemExecution ? 0 : max(0, (int)($event['user_id'] ?? (function_exists('get_current_user_id') ? get_current_user_id() : 0))),
            substr(hash('sha256', (string)($event['session_id'] ?? ($systemExecution ? 'gedefense-system-cron' : self::sessionSeed()))), 0, 32),
            self::token((string)($event['entity_type'] ?? self::entityType($metadata, $ip)), 32),
            substr((string)($event['entity_id'] ?? self::entityId($metadata, $ip)), 0, 191),
            self::sanitizeComponentKey((string)($event['component_key'] ?? $metadata['component_key'] ?? $metadata['plugin'] ?? $metadata['component'] ?? $metadata['theme'] ?? '')),
            substr(self::normalizedRoute($routeCandidate), 0, 191),
            self::token((string)($event['vector'] ?? $event['classification'] ?? $event['type'] ?? ($systemExecution ? 'SYSTEM_SCHEDULED_TASK' : 'UNKNOWN_ANOMALY')), 64),
            self::token((string)($event['action_type'] ?? ($role === 'RESPONSE' ? 'CONTAIN' : 'OBSERVE')), 32),
            self::token((string)($event['outcome'] ?? 'RECORDED'), 32),
            self::token((string)($event['privacy_class'] ?? 'LOCAL_SECURITY'), 32),
            $causalParentId,
            $causalEdge,
            Redactor::sanitize($metadata),
        );
    }

    public function isDetection(): bool {
        return $this->role === 'DETECTION';
    }

    public function isConfirmation(): bool {
        return $this->role === 'CONFIRMATION';
    }

    public function isResponse(): bool {
        return $this->role === 'RESPONSE';
    }

    public function isContext(): bool {
        return $this->role === 'CONTEXT';
    }

    public function hash(): string {
        return hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return get_object_vars($this) + ['schemaVersion' => self::SCHEMA_VERSION];
    }

    private static function token(string $value, int $limit): string {
        $safe = strtoupper(preg_replace('/[^A-Z0-9_:-]/i', '_', $value) ?? 'UNKNOWN');
        return substr(trim($safe, '_') ?: 'UNKNOWN', 0, $limit);
    }

    private static function hexId(string $value): string {
        return preg_match('/^[a-f0-9]{32}$/D', $value) === 1 ? $value : '';
    }

    private static function categoryFor(string $sensor): string {
        return match ($sensor) {
            'ZEUS', 'AEGIS' => 'INGRESS',
            'PROMETHEUS' => 'BEHAVIOR',
            'CERBERUS', 'THRONEGUARD', 'HADES' => 'IDENTITY',
            'INTEGRITY', 'CHRONOS' => 'INTEGRITY',
            'FILESYSTEM', 'FILESYSTEM_GUARD' => 'FILESYSTEM',
            'AIRLOCK', 'SCANNER' => 'MALWARE',
            'MORPHEUS' => 'RUNTIME',
            'STYX' => 'EGRESS',
            'NEMESIS', 'GHOST_TRAP' => 'DECEPTION',
            'LOGINPAGER' => 'AUTHENTICATION',
            'TITAN', 'ORACLE', 'VAULT' => 'CONFIGURATION',
            default => 'RUNTIME',
        };
    }

    private static function defaultRoleFor(string $sensor, string $eventType): string {
        $upperType = strtoupper($eventType);
        if (str_contains($upperType, 'BAN') || str_contains($upperType, 'BLOCK') || str_contains($upperType, 'QUARANTINE') || str_contains($upperType, 'RESTRICT') || str_contains($upperType, 'KILL')) {
            if ($sensor === 'CERBERUS' || $sensor === 'TRINITY' || $sensor === 'RESPONSE_ENGINE') return 'RESPONSE';
        }
        if (str_contains($upperType, 'CONFIRMED') || str_contains($upperType, 'CANARY_OBSERVED')) {
            return 'CONFIRMATION';
        }
        if (str_contains($upperType, 'CONTEXT') || str_contains($upperType, 'ALLOWED') || str_contains($upperType, 'INFO')) {
            return 'CONTEXT';
        }
        return 'DETECTION';
    }

    private static function defaultConfidence(int $severity, string $role): int {
        if ($role === 'CONTEXT' || $role === 'RESPONSE') return 100;
        return max(10, min(85, $severity * 9));
    }

    /** @param array<string, mixed> $metadata */
    private static function entityType(array $metadata, string $ip): string {
        foreach (['plugin','theme','file','upload','route','host','subnet'] as $type) {
            if (!empty($metadata[$type])) return strtoupper($type);
        }
        return $ip !== '' ? 'IP' : 'HOST';
    }

    /** @param array<string, mixed> $metadata */
    private static function entityId(array $metadata, string $ip): string {
        foreach (['plugin','theme','file','upload','route','host','subnet'] as $type) {
            if (isset($metadata[$type]) && is_scalar($metadata[$type])) {
                return $type . ':' . hash('sha256', (string)$metadata[$type]);
            }
        }
        return $ip !== '' ? 'ip:' . $ip : 'host:' . hash('sha256', (string)(function_exists('home_url') ? home_url('/') : 'local'));
    }

    public static function sanitizeComponentKey(string $slug): string {
        $clean = strtolower(trim($slug));
        return preg_replace('/[^a-z0-9_-]/', '', $clean) ?? '';
    }

    private static function normalizedRoute(string $route): string {
        $path = parse_url($route, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? '/' . ltrim($path, '/') : '/';
    }

    private static function sessionSeed(): string {
        $cookie = defined('LOGGED_IN_COOKIE') ? (string)($_COOKIE[LOGGED_IN_COOKIE] ?? '') : '';
        return $cookie !== '' ? $cookie : RequestContext::id();
    }

    private static function isSystemExecution(string $route): bool {
        return (defined('DOING_CRON') && DOING_CRON)
            || (defined('WP_CLI') && WP_CLI)
            || str_contains(strtolower($route), '/wp-cron.php');
    }
}
