<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Runtime {
    private string $surface;
    private array $policy;
    private string $nonce = '';

    /** @param array<string, mixed> $config */
    public function __construct(private array $config) {
        $this->surface = VIS_Titan_Surface_Resolver::resolve();
        $this->policy = VIS_Titan_Policy_Store::activePolicy($this->surface, $config);
        if (!empty($this->policy['nonce_enabled'])) $this->nonce = base64_encode(random_bytes(18));
    }

    public function boot(): void {
        add_action('send_headers', [$this, 'sendHeaders'], 0);
        add_filter('wp_headers', [$this, 'filterHeaders'], 1000);
        add_action('init', [$this, 'enforceFetchMetadata'], 0);
        add_action('rest_api_init', [VIS_Titan_Violation_Collector::class, 'register']);
        add_filter('wp_script_attributes', [$this, 'nonceAttributes']);
        add_filter('wp_inline_script_attributes', [$this, 'nonceAttributes']);
        add_filter('wp_inline_style_attributes', [$this, 'nonceAttributes']);
        add_filter('script_loader_src', [$this, 'learnScriptSource'], 5);
        add_filter('style_loader_src', [$this, 'learnStyleSource'], 5);
        add_action('shutdown', [$this, 'postActivationCheck'], PHP_INT_MAX);
    }

    public function sendHeaders(): void {
        if (headers_sent()) {
            $this->recordHealth(['state' => 'DEGRADED', 'reason' => 'HEADERS_ALREADY_SENT']);
            return;
        }
        $existing = $this->currentHeaders();
        foreach ($this->effectiveHeaders() as $name => $value) {
            $key = strtolower($name);
            if (isset($existing[$key]) && !hash_equals($existing[$key], $value) && $this->conflictStrategy() === 'observe') {
                $this->recordConflict($name, $existing[$key], $value);
                continue;
            }
            header($name . ': ' . $value, true);
        }
        if (!empty($this->config['titan_server_spoof'])) {
            header_remove('Server');
            header('Server: VGT_OS/1.0.0', true);
        }
        header_remove('X-Powered-By');
        header_remove('X-Pingback');
        if ($this->validProbeRequest()) {
            header('X-VGT-Titan-Probe-Surface: ' . $this->surface, true);
            header('X-VGT-Titan-Probe-Policy: ' . (string)($this->policy['policy_hash'] ?? ''), true);
        }
        $this->recordHealth(['state' => 'SENT', 'surface' => $this->surface, 'policy_hash' => (string)($this->policy['policy_hash'] ?? '')]);
        $this->recordObservation();
    }

    /** @param array<string, string> $headers @return array<string, string> */
    public function filterHeaders(array $headers): array {
        unset($headers['X-Powered-By'], $headers['X-Pingback']);
        foreach ($this->effectiveHeaders() as $name => $value) {
            if (isset($headers[$name]) && is_string($headers[$name]) && !hash_equals($headers[$name], $value) && $this->conflictStrategy() === 'observe') {
                $this->recordConflict($name, $headers[$name], $value);
                continue;
            }
            $headers[$name] = $value;
        }
        if ($this->validProbeRequest()) {
            $headers['X-VGT-Titan-Probe-Surface'] = $this->surface;
            $headers['X-VGT-Titan-Probe-Policy'] = (string)($this->policy['policy_hash'] ?? '');
        }
        if (!empty($this->config['titan_server_spoof'])) $headers['Server'] = 'VGT_OS/1.0.0';
        else unset($headers['Server']);
        return $headers;
    }

    public function enforceFetchMetadata(): void {
        $mode = (string)($this->policy['fetch_mode'] ?? 'off');
        if ($mode === 'off' || VIS_Titan_Surface_Resolver::isMachineCompatible($this->surface)) return;
        $site = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
        $fetchMode = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '')));
        $destination = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')));
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $decision = self::evaluateFetchMetadata($mode, $this->surface, $site, $fetchMode, $destination, $method);
        if ($decision === 'NO_BROWSER_METADATA') return;
        $crossSite = $site === 'cross-site';
        $deny = $decision === 'DENY';
        if ($crossSite) $this->emitFetchEvent($deny, $site, $fetchMode, $destination, $method);
        if (!$deny) return;
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        exit('Request rejected for security reasons.');
    }

    public static function evaluateFetchMetadata(string $mode, string $surface, string $site, string $fetchMode, string $destination, string $method): string {
        if (!in_array($mode, VIS_Titan_Policy_Compiler::FETCH_MODES, true)) return 'ALLOW';
        if ($mode === 'off' || VIS_Titan_Surface_Resolver::isMachineCompatible($surface)) return 'ALLOW';
        if ($site === '' && $fetchMode === '' && $destination === '') return 'NO_BROWSER_METADATA';
        $unsafeMethod = !in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
        if ($site === 'cross-site' && $unsafeMethod && ($mode === 'strict' || ($mode === 'enforce_sensitive' && VIS_Titan_Surface_Resolver::isSensitive($surface)))) return 'DENY';
        return $mode === 'audit' && $site === 'cross-site' ? 'AUDIT' : 'ALLOW';
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function nonceAttributes(array $attributes): array {
        if ($this->nonce !== '') $attributes['nonce'] = $this->nonce;
        return $attributes;
    }

    public function learnScriptSource(mixed $source): mixed { return $this->learnSource($source, 'script'); }
    public function learnStyleSource(mixed $source): mixed { return $this->learnSource($source, 'style'); }

    private function learnSource(mixed $source, string $type): mixed {
        if (empty($this->config['titan_learning_enabled']) || !is_string($source) || $source === '') return $source;
        if (!in_array($type, ['script', 'style'], true)) return $source;
        $parts = wp_parse_url($source);
        if (!is_array($parts) || empty($parts['host'])) return $source;
        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        $host = strtolower((string)$parts['host']);
        if (!in_array($scheme, ['http','https'], true) || preg_match('/^[a-z0-9.-]{1,253}$/D', $host) !== 1) return $source;
        $origin = $scheme . '://' . $host . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
        $origins = get_option('vis_titan_learned_origins', []);
        $origins = is_array($origins) ? $origins : [];
        $key = hash('sha256', $this->surface . '|' . $type . '|' . $origin);
        $record = is_array($origins[$key] ?? null) ? $origins[$key] : [];
        $origins[$key] = ['surface' => $this->surface, 'type' => $type, 'origin' => $origin, 'count' => min(4294967295, (int)($record['count'] ?? 0) + 1), 'first_seen' => (string)($record['first_seen'] ?? gmdate('c')), 'last_seen' => gmdate('c')];
        if (count($origins) > 200) {
            uasort($origins, static fn(array $a, array $b): int => strcmp((string)$b['last_seen'], (string)$a['last_seen']));
            $origins = array_slice($origins, 0, 200, true);
        }
        update_option('vis_titan_learned_origins', $origins, false);
        return $source;
    }

    public function postActivationCheck(): void {
        $sent = [];
        foreach (headers_list() as $line) {
            $position = strpos($line, ':');
            if ($position === false) continue;
            $sent[strtolower(trim(substr($line, 0, $position)))] = trim(substr($line, $position + 1));
        }
        VIS_Titan_Policy_Store::confirmOrRollback($this->surface, $sent);
    }

    /** @return array<string, string> */
    private function effectiveHeaders(): array {
        $headers = is_array($this->policy['headers'] ?? null) ? $this->policy['headers'] : [];
        if (!function_exists('is_ssl') || !is_ssl()) unset($headers['Strict-Transport-Security']);
        if ($this->nonce !== '') {
            foreach ($headers as $name => $value) $headers[$name] = str_replace('{nonce}', $this->nonce, (string)$value);
        }
        return array_map('strval', $headers);
    }

    private function emitFetchEvent(bool $denied, string $site, string $mode, string $destination, string $method): void {
        $fabric = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
        if (!class_exists($fabric)) return;
        $fabric::ingest(['sensor' => 'TITAN', 'category' => 'INGRESS', 'event_type' => $denied ? 'TITAN_FETCH_CROSS_SITE_BLOCK' : 'TITAN_FETCH_CROSS_SITE_CONTEXT', 'role' => $denied ? 'DETECTION' : 'CONTEXT', 'severity' => $denied ? 8 : 2, 'confidence' => $denied ? 90 : 25, 'score' => $denied ? 85 : 0, 'actor_ip' => class_exists('VIS_Security') ? VIS_Security::client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'entity_type' => 'ROUTE', 'entity_id' => 'route:' . hash('sha256', $this->surface), 'vector' => $denied ? 'CROSS_SITE_SENSITIVE_MUTATION' : 'FETCH_METADATA_CONTEXT', 'action_type' => $denied ? 'BLOCK' : 'OBSERVE', 'outcome' => $denied ? 'INTERCEPTED' : 'RECORDED', 'metadata' => ['surface' => $this->surface, 'sec_fetch_site' => $site, 'sec_fetch_mode' => $mode, 'sec_fetch_dest' => $destination, 'method' => $method]]);
    }

    /** @return array<string, string> */
    private function currentHeaders(): array {
        $headers = [];
        foreach (headers_list() as $line) {
            $position = strpos($line, ':');
            if ($position === false) continue;
            $headers[strtolower(trim(substr($line, 0, $position)))] = trim(substr($line, $position + 1));
        }
        return $headers;
    }

    private function conflictStrategy(): string {
        $strategy = strtolower((string)($this->config['titan_header_conflict_strategy'] ?? 'observe'));
        return in_array($strategy, ['observe', 'override_titan_owned'], true) ? $strategy : 'observe';
    }

    private function recordConflict(string $name, string $observed, string $compiled): void {
        $key = 'vis_titan_conflict_' . hash('sha256', $this->surface . '|' . strtolower($name));
        if (get_transient($key) !== false) return;
        set_transient($key, '1', 300);
        $conflicts = get_option('vis_titan_header_conflicts', []);
        $conflicts = is_array($conflicts) ? $conflicts : [];
        $id = hash('sha256', $this->surface . '|' . strtolower($name));
        $conflicts[$id] = ['surface' => $this->surface, 'header' => substr($name, 0, 64), 'observed_hash' => hash('sha256', $observed), 'compiled_hash' => hash('sha256', $compiled), 'last_seen' => gmdate('c'), 'state' => 'DETECTED_EXTERNAL_POLICY'];
        if (count($conflicts) > 50) $conflicts = array_slice($conflicts, -50, null, true);
        update_option('vis_titan_header_conflicts', $conflicts, false);
    }

    private function validProbeRequest(): bool {
        $token = isset($_SERVER['HTTP_X_VGT_TITAN_PROBE']) ? (string)$_SERVER['HTTP_X_VGT_TITAN_PROBE'] : '';
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) return false;
        $expected = get_transient('vis_titan_probe_token_hash');
        return is_string($expected) && strlen($expected) === 64 && hash_equals($expected, hash('sha256', $token));
    }

    /** @param array<string, string> $health */
    private function recordHealth(array $health): void {
        $key = 'vis_titan_health_' . hash('sha256', $this->surface . '|' . ($health['state'] ?? 'UNKNOWN') . '|' . ($health['reason'] ?? ''));
        if (get_transient($key) !== false) return;
        set_transient($key, '1', 60);
        update_option('vis_titan_runtime_health', $health + ['timestamp' => gmdate('c')], false);
    }

    private function recordObservation(): void {
        if (!in_array((string)($this->policy['csp_mode'] ?? 'off'), ['learning', 'report_only'], true)) return;
        $key = 'vis_titan_observed_' . hash('sha256', $this->surface);
        if (get_transient($key) !== false) return;
        set_transient($key, '1', 30);
        $metrics = get_option('vis_titan_observation_metrics', []);
        $metrics = is_array($metrics) ? $metrics : [];
        $record = is_array($metrics[$this->surface] ?? null) ? $metrics[$this->surface] : [];
        $metrics[$this->surface] = [
            'samples' => min(4294967295, (int)($record['samples'] ?? 0) + 1),
            'first_seen' => (string)($record['first_seen'] ?? gmdate('c')),
            'last_seen' => gmdate('c'),
            'policy_hash' => (string)($this->policy['policy_hash'] ?? ''),
        ];
        update_option('vis_titan_observation_metrics', $metrics, false);
    }
}
