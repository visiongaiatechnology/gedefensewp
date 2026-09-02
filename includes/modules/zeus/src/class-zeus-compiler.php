<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

/**
 * MASTER ZEUS WAF COMPILER
 * Compiles canonical policies into the ultra-fast, zero-dependency pre-boot admission kernel.
 */
class VIS_Zeus_Compiler {
    
    private string $vault_dir;
    private string $waf_file;
    private string $lkg_file;
    private array $config;
    private string $swarm_ip;

    public function __construct(string $vault_dir, string $waf_file, array $config = [], string $swarm_ip = '') {
        $this->vault_dir = function_exists('wp_normalize_path') ? wp_normalize_path($vault_dir) : str_replace('\\', '/', $vault_dir);
        if (!str_ends_with($this->vault_dir, '/')) {
            $this->vault_dir .= '/';
        }
        $this->waf_file  = function_exists('wp_normalize_path') ? wp_normalize_path($waf_file) : str_replace('\\', '/', $waf_file);
        $this->lkg_file  = function_exists('wp_normalize_path') ? wp_normalize_path($this->vault_dir . 'zeus-waf.lkg.php') : str_replace('\\', '/', $this->vault_dir . 'zeus-waf.lkg.php');
        $this->config    = $config;
        $this->swarm_ip  = $swarm_ip;
    }

    public function deploy_waf(): void {
        $this->sync_dynamic_whitelist();
        $this->compile_waf_payload();
    }

    private function sync_dynamic_whitelist(): void {
        $whitelist_ips = (function_exists('get_option') ? get_option('vgt_zeus_whitelist_ips', null) : null);
        
        if ($whitelist_ips === null) {
            $whitelist_ips = [$this->swarm_ip];
            update_option('vgt_zeus_whitelist_ips', $whitelist_ips);
        }
        
        $whitelist_file = $this->vault_dir . 'whitelist.json';
        file_put_contents($whitelist_file, wp_json_encode($whitelist_ips, JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($whitelist_file, 0600);
    }

    public function generate_payload_code(): string {
        $whitelist_ips = $this->config['ip_whitelist'] ?? (function_exists('get_option') ? get_option('vgt_zeus_whitelist_ips', []) : []);
        if (!is_array($whitelist_ips)) $whitelist_ips = [$whitelist_ips];
        if ($this->swarm_ip !== '' && !in_array($this->swarm_ip, $whitelist_ips, true)) {
            $whitelist_ips[] = $this->swarm_ip;
        }
        $wl_export = var_export(array_values(array_filter($whitelist_ips)), true);

        // Compile Cloudflare CIDRs
        $cf_v4 = ['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13','104.24.0.0/14','172.64.0.0/13','131.0.72.0/22'];
        $compiled_v4 = [];
        foreach ($cf_v4 as $cidr) {
            [$net, $mask] = explode('/', $cidr);
            $compiled_v4[] = '[' . ip2long($net) . ', ' . ~((1 << (32 - (int)$mask)) - 1) . ']';
        }
        $cf_array_v4 = '[' . implode(',', $compiled_v4) . ']';

        $cf_v6 = ['2400:cb00::/32','2606:4700::/32','2803:f800::/32','2405:b500::/32','2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32'];
        $compiled_v6 = [];
        foreach ($cf_v6 as $cidr) {
            [$net, $mask] = explode('/', $cidr);
            $bin_net = bin2hex((string)inet_pton($net));
            $bin_mask = str_repeat("\xff", (int)($mask / 8));
            if ($mask % 8 !== 0) $bin_mask .= chr(256 - (1 << (8 - ($mask % 8))));
            $bin_mask = str_pad($bin_mask, 16, "\x00");
            $bin_mask_hex = bin2hex($bin_mask);
            $compiled_v6[] = "[hex2bin('{$bin_net}'), hex2bin('{$bin_mask_hex}')]";
        }
        $cf_array_v6 = '[' . implode(',', $compiled_v6) . ']';

        // Compile Route Contracts
        $contracts = Zeus_Contracts::getDefaultContracts();
        $customContracts = (function_exists('get_option') ? get_option('vis_zeus_custom_contracts', []) : []);
        if (is_array($customContracts)) {
            $contracts = array_merge($contracts, $customContracts);
        }
        $contracts_export = var_export($contracts, true);

        // Compile XDR Route Containment Array
        $xdrRoutes = Zeus_Xdr_Bridge::getActiveRouteContainments();
        $xdr_routes_export = var_export($xdrRoutes, true);

        // Compile Config
        $config_export = var_export($this->config, true);
        $vault_dir_export = var_export($this->vault_dir, true);
        $date_utc = gmdate('Y-m-d H:i:s');

        return <<<WAF_PAYLOAD
<?php
/**
 * GEDEFENSE ZEUS — PRE-BOOT ADMISSION CONTROL & EDGE DEFENSE KERNEL
 * COMPILED: {$date_utc} UTC
 * STATUS: DIAMANT VGT SUPREME (ZERO-ALLOCATION L0 ADMISSION)
 */
if (defined('VGT_ZEUS_WAF_ACTIVE')) return;
define('VGT_ZEUS_WAF_ACTIVE', true);
if (!defined('VGT_ZEUS_PREBOOT')) define('VGT_ZEUS_PREBOOT', true);

(static function() {
    \$vaultDir = {$vault_dir_export};
    \$config = {$config_export};
    \$contracts = {$contracts_export};
    \$xdrContainedRoutes = {$xdr_routes_export};

    // P0: ZEUS MASTER SWITCH (Authoritative check — returns immediately if disabled)
    if (empty(\$config['zeus_enabled'])) {
        return;
    }

    // 1. DETERMINISTIC IP EXTRACTION & TRUSTED PROXY RESOLUTION
    \$ip = \$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    \$cf_ip = \$_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (\$cf_ip !== '' && filter_var(\$cf_ip, FILTER_VALIDATE_IP)) {
        \$is_cf = false;
        if (strpos(\$ip, ':') === false) {
            \$cf_ranges = {$cf_array_v4};
            \$ip_long = ip2long(\$ip);
            if (\$ip_long !== false) {
                foreach (\$cf_ranges as \$r) {
                    if ((\$ip_long & \$r[1]) === \$r[0]) { \$is_cf = true; break; }
                }
            }
        } else {
            \$cf_ranges_v6 = {$cf_array_v6};
            \$ip_bin = inet_pton(\$ip);
            if (\$ip_bin !== false) {
                foreach (\$cf_ranges_v6 as \$r) {
                    if ((\$ip_bin & \$r[1]) === \$r[0]) { \$is_cf = true; break; }
                }
            }
        }
        if (\$is_cf) \$ip = \$cf_ip;
    }

    \$rawUri = \$_SERVER['REQUEST_URI'] ?? '/';
    \$pathOnly = parse_url(\$rawUri, PHP_URL_PATH);
    if (!is_string(\$pathOnly) || \$pathOnly === '') \$pathOnly = '/';
    \$method = strtoupper(trim((string)(\$_SERVER['REQUEST_METHOD'] ?? 'GET')));
    \$learningMode = !empty(\$config['learning_mode_enabled']);

    // CANONICAL PRE-BOOT BLACKBOX RECORDER (With True Coalescing, Windows Safe Rotation, No Fake Keys)
    \$record_event = static function(\$eventType, \$ruleId, \$reason, \$severity, \$decision, \$statusCode = 400) use (\$ip, \$pathOnly, \$method, \$vaultDir) {
        \$spoolFile = \$vaultDir . 'blackbox.spool';
        \$now = gmdate('Y-m-d\TH:i:s\Z');
        \$nowTs = time();
        
        \$packed = @inet_pton(\$ip);
        \$actorMasked = \$ip;
        if (\$packed !== false) {
            if (strlen(\$packed) === 4) {
                \$parts = explode('.', \$ip);
                \$actorMasked = \$parts[0] . '.' . \$parts[1] . '.' . \$parts[2] . '.0';
            } elseif (strlen(\$packed) === 16) {
                \$parts = explode(':', \$ip);
                \$actorMasked = implode(':', array_slice(\$parts, 0, 4)) . '::';
            }
        }

        \$routeSafe = substr(\$pathOnly, 0, 64);

        \$fh = @fopen(\$spoolFile, 'c+b');
        if (\$fh) {
            if (flock(\$fh, LOCK_EX)) {
                \$fstat = fstat(\$fh);
                \$fileSize = (int)(\$fstat['size'] ?? 0);

                // Windows-Safe Spool Rotation (2MB bound)
                if (\$fileSize > 2097152) {
                    flock(\$fh, LOCK_UN);
                    fclose(\$fh);
                    \$oldSpool = \$vaultDir . 'blackbox.spool.old';
                    if (is_file(\$oldSpool)) @unlink(\$oldSpool);
                    @rename(\$spoolFile, \$oldSpool);
                    \$fh = @fopen(\$spoolFile, 'c+b');
                    if (\$fh && flock(\$fh, LOCK_EX)) {
                        \$fileSize = 0;
                    }
                }

                if (\$fh) {
                    \$prevHash = '0000000000000000000000000000000000000000000000000000000000000000';
                    \$lastLinePos = -1;
                    \$lastRecord = null;

                    if (\$fileSize > 0) {
                        \$readLen = min(\$fileSize, 2048);
                        fseek(\$fh, \$fileSize - \$readLen);
                        \$tail = (string)fread(\$fh, \$readLen);
                        \$lines = explode("\n", \$tail);
                        \$lines = array_values(array_filter(\$lines, static fn(\$l) => trim(\$l) !== ''));

                        if (!empty(\$lines)) {
                            \$lastLineRaw = end(\$lines);
                            \$decoded = @json_decode(\$lastLineRaw, true);
                            if (is_array(\$decoded)) {
                                \$lastRecord = \$decoded;
                                if (!empty(\$decoded['hash'])) {
                                    \$prevHash = (string)\$decoded['hash'];
                                }
                            }
                            \$lastLinePos = \$fileSize - strlen(\$lastLineRaw) - 1;
                            if (\$lastLinePos < 0) \$lastLinePos = 0;
                        }
                    }

                    // True Flood Coalescing within 300s window
                    \$canCoalesce = (\$lastRecord !== null)
                        && (\$lastRecord['actor'] ?? '') === \$actorMasked
                        && (\$lastRecord['rule_id'] ?? '') === \$ruleId
                        && (\$lastRecord['route'] ?? '') === \$routeSafe
                        && (\$lastRecord['decision'] ?? '') === \$decision
                        && (\$nowTs - (int)strtotime(\$lastRecord['last_seen'] ?? '1970-01-01')) < 300;

                    \$keyFile = \$vaultDir . 'vgt-master.php';
                    \$mKey = defined('VGT_MASTER_KEY') ? VGT_MASTER_KEY : null;
                    if (\$mKey === null && is_file(\$keyFile)) {
                        @include_once \$keyFile;
                        if (defined('VGT_MASTER_KEY')) \$mKey = VGT_MASTER_KEY;
                    }

                    if (\$canCoalesce && \$lastLinePos >= 0) {
                        \$lastRecord['count'] = ((int)(\$lastRecord['count'] ?? 1)) + 1;
                        \$lastRecord['last_seen'] = \$now;
                        \$prevOfLast = (string)(\$lastRecord['prev_hash'] ?? '0000000000000000000000000000000000000000000000000000000000000000');
                        unset(\$lastRecord['hash']);
                        \$canonicalJson = json_encode(\$lastRecord, JSON_UNESCAPED_SLASHES);
                        \$lastRecord['hash'] = (\$mKey !== null)
                            ? hash_hmac('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:v1|' . \$prevOfLast . '|' . \$canonicalJson, \$mKey)
                            : hash('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:UNAUTHENTICATED:v1|' . \$prevOfLast . '|' . \$canonicalJson);

                        fseek(\$fh, \$lastLinePos);
                        ftruncate(\$fh, \$lastLinePos);
                        fwrite(\$fh, json_encode(\$lastRecord, JSON_UNESCAPED_SLASHES) . "\n");
                    } else {
                        \$record = [
                            'event_id'   => bin2hex(random_bytes(16)),
                            'timestamp'  => \$now,
                            'sensor'     => 'ZEUS',
                            'event_type' => \$eventType,
                            'severity'   => (int)\$severity,
                            'actor'      => \$actorMasked,
                            'route'      => \$routeSafe,
                            'method'     => \$method,
                            'rule_id'    => \$ruleId,
                            'decision'   => \$decision,
                            'reason'     => substr(\$reason, 0, 160),
                            'count'      => 1,
                            'first_seen' => \$now,
                            'last_seen'  => \$now,
                            'prev_hash'  => \$prevHash
                        ];

                        \$canonicalJson = json_encode(\$record, JSON_UNESCAPED_SLASHES);
                        \$recordHash = (\$mKey !== null)
                            ? hash_hmac('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:v1|' . \$prevHash . '|' . \$canonicalJson, \$mKey)
                            : hash('sha256', 'GEDEFENSE:ZEUS:BLACKBOX:UNAUTHENTICATED:v1|' . \$prevHash . '|' . \$canonicalJson);
                        \$record['hash'] = \$recordHash;

                        fseek(\$fh, 0, SEEK_END);
                        fwrite(\$fh, json_encode(\$record, JSON_UNESCAPED_SLASHES) . "\n");
                    }
                    flock(\$fh, LOCK_UN);
                }
            }
            if (\$fh) fclose(\$fh);
        }

        if (\$decision === 'BLOCK') {
            http_response_code(\$statusCode);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-VGT-Defense: ZEUS-L0');
            echo "VGT_ZEUS_ADMISSION_DENIED: {\$ruleId}\n";
            exit;
        }
    };

    \$record_and_kill = static function(\$eventType, \$ruleId, \$reason, \$severity, \$statusCode) use (\$record_event, \$learningMode) {
        // In learning mode, non-integrity violations are audited instead of blocked
        \$decision = \$learningMode ? 'AUDIT' : 'BLOCK';
        \$record_event(\$eventType, \$ruleId, \$reason, \$severity, \$decision, \$statusCode);
    };

    \$record_and_force_kill = static function(\$eventType, \$ruleId, \$reason, \$severity, \$statusCode) use (\$record_event) {
        // Critical invariants are ALWAYS blocked, even in learning mode
        \$record_event(\$eventType, \$ruleId, \$reason, \$severity, 'BLOCK', \$statusCode);
    };

    // CANONICAL ADMISSION TOKEN VALIDATION ENGINE (HMAC, Expiry, Surface, Purpose, Nonce/Session)
    \$verify_admission_token = static function(?string \$token, string \$expectedSurface, ?string \$expectedPurpose = null) use (\$vaultDir): array {
        if (!is_string(\$token) || \$token === '') {
            return ['valid' => false, 'reason' => 'TOKEN_MISSING'];
        }

        \$parts = explode('.', \$token);
        if (count(\$parts) !== 3 || \$parts[0] !== 'vgt1') {
            return ['valid' => false, 'reason' => 'TOKEN_FORMAT_INVALID'];
        }

        \$b64Payload = \$parts[1];
        \$providedSig = \$parts[2];

        \$keyFile = \$vaultDir . 'vgt-master.php';
        \$admKey = defined('VGT_MASTER_KEY') ? VGT_MASTER_KEY : null;
        if (\$admKey === null && is_file(\$keyFile)) {
            @include_once \$keyFile;
            if (defined('VGT_MASTER_KEY')) \$admKey = VGT_MASTER_KEY;
        }

        if (\$admKey === null || \$admKey === '') {
            return ['valid' => false, 'reason' => 'KEY_MATERIAL_UNAVAILABLE'];
        }

        \$expectedSig = hash_hmac('sha256', 'vgt1.' . \$b64Payload, \$admKey);
        if (!hash_equals(\$expectedSig, \$providedSig)) {
            return ['valid' => false, 'reason' => 'TOKEN_SIGNATURE_MISMATCH'];
        }

        \$remainder = strlen(\$b64Payload) % 4;
        if (\$remainder) {
            \$b64Payload .= str_repeat('=', 4 - \$remainder);
        }
        \$json = base64_decode(strtr(\$b64Payload, '-_', '+/'), true);
        if (\$json === false) {
            return ['valid' => false, 'reason' => 'TOKEN_PAYLOAD_CORRUPT'];
        }

        \$payload = @json_decode(\$json, true);
        if (!is_array(\$payload)) {
            return ['valid' => false, 'reason' => 'TOKEN_JSON_INVALID'];
        }

        if ((\$payload['v'] ?? 0) !== 1) {
            return ['valid' => false, 'reason' => 'TOKEN_VERSION_UNSUPPORTED'];
        }

        \$now = time();
        if ((int)(\$payload['iat'] ?? 0) > \$now + 60) {
            return ['valid' => false, 'reason' => 'TOKEN_FUTURE_TIMESTAMP'];
        }

        if ((\$payload['exp'] ?? 0) < \$now) {
            return ['valid' => false, 'reason' => 'TOKEN_EXPIRED'];
        }

        if ((\$payload['s'] ?? '') !== \$expectedSurface && (\$payload['s'] ?? '') !== 'all') {
            return ['valid' => false, 'reason' => 'TOKEN_SURFACE_MISMATCH'];
        }

        if (\$expectedPurpose !== null && (\$payload['p'] ?? '') !== \$expectedPurpose && (\$payload['p'] ?? '') !== 'all') {
            return ['valid' => false, 'reason' => 'TOKEN_PURPOSE_MISMATCH'];
        }

        \$type = (string)(\$payload['t'] ?? 'entry');
        \$nonce = (string)(\$payload['nonce'] ?? '');
        if (\$type === 'entry') {
            if (\$nonce === '') {
                return ['valid' => false, 'reason' => 'ENTRY_TOKEN_NONCE_MISSING'];
            }
            \$nonceKey = 'vgt_adm_nonce_' . md5(\$nonce);
            if (function_exists('apcu_fetch')) {
                \$s = false;
                if (apcu_fetch(\$nonceKey, \$s) === 1 && \$s) {
                    return ['valid' => false, 'reason' => 'TOKEN_REPLAY_DETECTED'];
                }
                @apcu_store(\$nonceKey, 1, max(60, (int)(\$payload['exp'] - \$now)));
            } else {
                \$lf = \$vaultDir . 'cache/' . \$nonceKey . '.lock';
                if (file_exists(\$lf)) {
                    \$mt = @filemtime(\$lf);
                    if (\$mt !== false && (\$now - \$mt) < 300) {
                        return ['valid' => false, 'reason' => 'TOKEN_REPLAY_DETECTED'];
                    }
                }
                @file_put_contents(\$lf, (string)\$now, LOCK_EX);
                @chmod(\$lf, 0600);
            }
        }

        return ['valid' => true, 'payload' => \$payload, 'key' => \$admKey];
    };

    // Mint session capability token from valid entry token
    \$mint_session_cookie = static function(array \$entryPayload, string \$admKey) {
        \$now = time();
        \$surface = (string)(\$entryPayload['s'] ?? 'all');
        \$purpose = (string)(\$entryPayload['p'] ?? 'login');
        \$ttl = max(60, (int)((\$entryPayload['exp'] ?? 0) - \$now));
        \$sessPayload = [
            'v' => 1,
            't' => 'session',
            's' => \$surface,
            'iat' => \$now,
            'exp' => \$now + \$ttl,
            'nonce' => bin2hex(random_bytes(8)),
            'p' => \$purpose
        ];
        \$b64 = rtrim(strtr(base64_encode(json_encode(\$sessPayload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        \$sig = hash_hmac('sha256', 'vgt1.' . \$b64, \$admKey);
        \$sessToken = 'vgt1.' . \$b64 . '.' . \$sig;

        \$isHttps = !empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off';
        setcookie('vgt_zeus_adm', \$sessToken, [
            'expires'  => \$now + \$ttl,
            'path'     => '/',
            'domain'   => '',
            'secure'   => \$isHttps,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        \$_COOKIE['vgt_zeus_adm'] = \$sessToken;
    };

    // 1. CLEAN URL ADMISSION TOKEN EXCHANGE
    if (isset(\$_GET['vgt_adm']) && is_string(\$_GET['vgt_adm'])) {
        \$vRes = \$verify_admission_token(\$_GET['vgt_adm'], 'all');
        if (\$vRes['valid']) {
            \$mint_session_cookie(\$vRes['payload'], \$vRes['key']);
            \$cleanUri = preg_replace('/([?&])vgt_adm=[^&]*(&|$)/', '$1', \$rawUri);
            \$cleanUri = rtrim(\$cleanUri, '?&');
            if (\$cleanUri === '') \$cleanUri = '/';
            header('Location: ' . \$cleanUri, true, 302);
            exit;
        }
    }

    // 2. INCIDENT LOCKDOWN & FORTRESS MODE (Full Cryptographic Admission Check)
    \$lockdown = \$config['lockdown_state'] ?? 'NORMAL';
    if (\$lockdown === 'INCIDENT_LOCKDOWN' || \$lockdown === 'FORTRESS') {
        \$admToken = \$_COOKIE['vgt_zeus_adm'] ?? (\$_GET['vgt_adm'] ?? null);
        \$res = \$verify_admission_token(\$admToken, 'all');
        if (!\$res['valid']) {
            \$record_and_force_kill('ZEUS.EMERGENCY_POLICY_MATCH', 'LOCKDOWN_INCIDENT_REJECT', 'System in Lockdown: ' . (\$res['reason'] ?? 'ADMISSION_REQUIRED'), 10, 503);
        }
    }

    // 3. XDR VIRTUAL EMERGENCY ROUTE CONTAINMENT CHECK (Hard Semantic TTL)
    if (!empty(\$xdrContainedRoutes)) {
        \$nowUtc = gmdate('Y-m-d H:i:s');
        foreach (\$xdrContainedRoutes as \$prefix => \$info) {
            if ((\$info['expires_at'] ?? '') > \$nowUtc && str_starts_with(\$pathOnly, \$prefix)) {
                \$record_and_force_kill('ZEUS.EMERGENCY_POLICY_MATCH', 'XDR_ROUTE_ISOLATED', 'Route under active Trinity XDR emergency containment.', 9, 503);
            }
        }
    }

    // 4. CANONICALIZATION GUARD (Hostile Path / Encoding / Polyglot Invariants - Force Kill)
    // A. Null Byte Check across entire URI
    if (str_contains(\$rawUri, chr(0)) || stripos(\$rawUri, '%00') !== false) {
        \$record_and_force_kill('ZEUS.CANONICALIZATION_REJECT', 'CANON_NULL_BYTE', 'Null byte detected in request target.', 9, 400);
    }
    // B. Double Percent-Encoding across entire URI
    if (preg_match('/%25[0-9a-fA-F]{2}/', \$rawUri)) {
        \$record_and_force_kill('ZEUS.CANONICALIZATION_REJECT', 'CANON_DOUBLE_ENCODING', 'Double percent-encoding detected.', 8, 400);
    }
    // C. Encoded Slashes / Backslashes IN RAW PATH ONLY (%2f, %5c)
    if (preg_match('/%(?:2f|5c)/i', \$pathOnly)) {
        \$record_and_force_kill('ZEUS.CANONICALIZATION_REJECT', 'CANON_ENCODED_SLASH', 'Ambiguous encoded slash detected in path.', 8, 400);
    }
    // D. Unescaped Backslash IN RAW PATH ONLY
    if (str_contains(\$pathOnly, chr(92))) {
        \$record_and_force_kill('ZEUS.CANONICALIZATION_REJECT', 'CANON_BACKSLASH', 'Unescaped backslash detected in path.', 7, 400);
    }
    // E. Dot-Segment Traversal IN RAW PATH ONLY
    if (preg_match('/(?:\/|^)\.\.(?:\/|$)/', \$pathOnly) || preg_match('/%(?:2e|\.){2}/i', \$pathOnly)) {
        \$record_and_force_kill('ZEUS.CANONICALIZATION_REJECT', 'CANON_DOT_SEGMENT', 'Dot-segment directory traversal detected in path.', 9, 400);
    }
    // F. Duplicate Path Separators IN RAW PATH ONLY
    if (str_starts_with(\$rawUri, '//') || str_contains(\$pathOnly, '//')) {
        \$record_and_force_kill('ZEUS.CANONICALIZATION_REJECT', 'CANON_DUPLICATE_SLASHES', 'Duplicate path separators detected.', 6, 400);
    }
    // G. Path Depth Ceiling
    if (substr_count(\$pathOnly, '/') > 32) {
        \$record_and_force_kill('ZEUS.CANONICALIZATION_REJECT', 'CANON_PATH_DEPTH_EXCEEDED', 'Path segment depth exceeded 32 levels.', 7, 400);
    }

    // 5. HOST INVARIANT & HOST LOCK
    \$rawHost = \$_SERVER['HTTP_HOST'] ?? '';
    if (\$rawHost === '' || strlen(\$rawHost) > 253 || preg_match('/[^a-zA-Z0-9.:-]/', \$rawHost) === 1) {
        \$record_and_force_kill('ZEUS.HOST_REJECT', 'HOST_HEADER_MALFORMED', 'Malformed Host header envelope.', 8, 400);
    }
    if ((\$config['host_lock_mode'] ?? 'DISABLED') === 'REJECT') {
        \$hostHeader = strtolower((string)preg_replace('/:\d+$/', '', trim(\$rawHost)));
        \$canonicalHosts = array_map('strtolower', array_map('trim', \$config['canonical_hosts'] ?? []));
        if (!empty(\$canonicalHosts) && !in_array(\$hostHeader, \$canonicalHosts, true)) {
            \$record_and_kill('ZEUS.HOST_REJECT', 'HOST_LOCK_MISMATCH', "Host {\$hostHeader} mismatch canonical hosts.", 8, 421);
        }
    }

    // 6. HTTP METHOD INVARIANT
    \$allowedMethods = \$config['allowed_methods'] ?? ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'];
    if (!in_array(\$method, \$allowedMethods, true)) {
        \$record_and_kill('ZEUS.REQUEST_CONTRACT_VIOLATION', 'ENV_METHOD_DISALLOWED', "HTTP method {\$method} not permitted.", 7, 405);
    }

    // 7. STRICT STATIC ASSET FAST-PATH
    \$ext = strtolower(substr((string)strrchr(\$pathOnly, '.'), 1));
    \$static_exts = ['ico'=>1,'map'=>1,'woff'=>1,'woff2'=>1,'ttf'=>1,'png'=>1,'jpg'=>1,'jpeg'=>1,'svg'=>1,'css'=>1,'js'=>1,'webp'=>1,'gif'=>1];
    if (
        (\$method === 'GET' || \$method === 'HEAD') &&
        isset(\$static_exts[\$ext]) &&
        !str_contains(\$pathOnly, '.php') &&
        !str_starts_with(\$pathOnly, '/wp-json') &&
        !str_starts_with(\$pathOnly, '/wp-admin') &&
        (str_starts_with(\$pathOnly, '/wp-content/') || str_starts_with(\$pathOnly, '/wp-includes/') || str_starts_with(\$pathOnly, '/assets/'))
    ) {
        return;
    }

    // 8. BINARY CIDR WHITELIST CHECK
    \$wl = {$wl_export};
    \$cidr_match = static function(string \$clientIp, string \$network): bool {
        if (\$clientIp === \$network) return true;
        if (!str_contains(\$network, '/')) return false;
        [\$subnet, \$mask] = explode('/', \$network, 2);
        \$clientPacked = @inet_pton(\$clientIp);
        \$subnetPacked = @inet_pton(\$subnet);
        if (\$clientPacked === false || \$subnetPacked === false || strlen(\$clientPacked) !== strlen(\$subnetPacked)) {
            return false;
        }
        \$maskBits = (int)\$mask;
        \$maxBits = strlen(\$clientPacked) * 8;
        if (\$maskBits < 0 || \$maskBits > \$maxBits) return false;
        \$fullBytes = intdiv(\$maskBits, 8);
        \$remBits = \$maskBits % 8;
        if (\$fullBytes > 0 && substr(\$clientPacked, 0, \$fullBytes) !== substr(\$subnetPacked, 0, \$fullBytes)) {
            return false;
        }
        if (\$remBits > 0) {
            \$maskByte = chr((0xFF << (8 - \$remBits)) & 0xFF);
            if ((\$clientPacked[\$fullBytes] & \$maskByte) !== (\$subnetPacked[\$fullBytes] & \$maskByte)) {
                return false;
            }
        }
        return true;
    };

    \$isWhitelisted = false;
    foreach (\$wl as \$wEntry) {
        if (is_string(\$wEntry) && \$cidr_match(\$ip, \$wEntry)) {
            \$isWhitelisted = true;
            break;
        }
    }

    // 9. REQUEST ENVELOPE CEILINGS
    \$queryStr = (string)(\$_SERVER['QUERY_STRING'] ?? '');
    \$queryLen = strlen(\$queryStr);
    \$paramCount = count(\$_GET);
    \$maxQuery = (int)(\$config['max_query_length'] ?? 2048);
    \$maxParams = (int)(\$config['max_query_params'] ?? 100);

    if (\$queryLen > \$maxQuery) {
        \$record_and_kill('ZEUS.REQUEST_MALFORMED', 'ENV_QUERY_TOO_LONG', "Query length {\$queryLen} exceeds ceiling {\$maxQuery}.", 7, 414);
    }
    if (\$paramCount > \$maxParams) {
        \$record_and_kill('ZEUS.REQUEST_MALFORMED', 'ENV_PARAMS_EXCEEDED', "Parameter count {\$paramCount} exceeds ceiling {\$maxParams}.", 7, 400);
    }

    \$headerCount = 0;
    \$totalHeaderSize = 0;
    foreach (\$_SERVER as \$k => \$v) {
        if (str_starts_with(\$k, 'HTTP_') || in_array(\$k, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'AUTHORIZATION'], true)) {
            \$headerCount++;
            \$totalHeaderSize += strlen(\$k) + (is_string(\$v) ? strlen(\$v) : 0);
        }
    }
    \$maxHeaders = (int)(\$config['max_header_count'] ?? 50);
    \$maxHeaderSize = (int)(\$config['max_header_size'] ?? 16384);
    if (\$headerCount > \$maxHeaders) {
        \$record_and_kill('ZEUS.REQUEST_MALFORMED', 'ENV_HEADER_COUNT_EXCEEDED', "Header count {\$headerCount} exceeds ceiling {\$maxHeaders}.", 6, 431);
    }
    if (\$totalHeaderSize > \$maxHeaderSize) {
        \$record_and_kill('ZEUS.REQUEST_MALFORMED', 'ENV_HEADER_SIZE_EXCEEDED', "Total header size {\$totalHeaderSize} exceeds ceiling {\$maxHeaderSize}.", 6, 431);
    }

    \$rawCookie = (string)(\$_SERVER['HTTP_COOKIE'] ?? '');
    \$cookieSize = strlen(\$rawCookie);
    \$maxCookie = (int)(\$config['max_cookie_size'] ?? 8192);
    if (\$cookieSize > \$maxCookie) {
        \$record_and_kill('ZEUS.REQUEST_MALFORMED', 'ENV_COOKIE_SIZE_EXCEEDED', "Cookie payload {\$cookieSize} exceeds ceiling {\$maxCookie}.", 6, 400);
    }

    // 10. ROUTE CONTRACT MATCHING & LIVE ENFORCEMENT
    \$contentLength = (int)(\$_SERVER['CONTENT_LENGTH'] ?? 0);
    \$contentType = (string)(\$_SERVER['CONTENT_TYPE'] ?? '');
    \$matchedContract = null;
    \$longestMatchLen = -1;

    foreach (\$contracts as \$cId => \$c) {
        if ((\$c['status'] ?? 'ACTIVE') !== 'ACTIVE') {
            if ((\$c['status'] ?? '') === 'DISABLED' && (\$c['path'] ?? '') === \$pathOnly) {
                \$record_and_kill('ZEUS.REQUEST_CONTRACT_VIOLATION', 'CONTRACT_ROUTE_DISABLED', "Route {\$pathOnly} disabled by contract.", 8, 403);
            }
            continue;
        }
        \$cPath = (string)(\$c['path'] ?? '');
        \$matchType = strtoupper((string)(\$c['match_type'] ?? 'EXACT'));
        if (\$matchType === 'EXACT' && \$pathOnly === \$cPath && strlen(\$cPath) > \$longestMatchLen) {
            \$matchedContract = \$c;
            \$longestMatchLen = strlen(\$cPath);
        } elseif (\$matchType === 'PREFIX' && str_starts_with(\$pathOnly, \$cPath) && strlen(\$cPath) > \$longestMatchLen) {
            \$matchedContract = \$c;
            \$longestMatchLen = strlen(\$cPath);
        }
    }

    if (\$matchedContract !== null) {
        \$cMethods = \$matchedContract['methods'] ?? ['GET', 'POST'];
        if (is_array(\$cMethods) && !in_array(\$method, \$cMethods, true)) {
            \$record_and_kill('ZEUS.REQUEST_CONTRACT_VIOLATION', 'CONTRACT_METHOD_VIOLATION', "Method {\$method} not permitted on route {\$pathOnly}.", 8, 405);
        }
        \$cMaxBody = (int)(\$matchedContract['max_body_bytes'] ?? 65536);
        if (\$contentLength > \$cMaxBody) {
            \$record_and_kill('ZEUS.REQUEST_CONTRACT_VIOLATION', 'CONTRACT_BODY_LIMIT', "Body size {\$contentLength} exceeds ceiling {\$cMaxBody}.", 7, 413);
        }
        \$allowedTypes = \$matchedContract['allowed_content_types'] ?? null;
        if (is_array(\$allowedTypes) && !empty(\$allowedTypes) && in_array(\$method, ['POST', 'PUT', 'PATCH'], true)) {
            \$cleanType = strtolower(trim((string)explode(';', \$contentType)[0]));
            if (\$cleanType !== '' && !in_array(\$cleanType, \$allowedTypes, true)) {
                \$record_and_kill('ZEUS.REQUEST_CONTRACT_VIOLATION', 'CONTRACT_CONTENT_TYPE_REJECT', "Content type {\$cleanType} not permitted.", 7, 415);
            }
        }

        // Contract route-specific query bounds
        if (!empty(\$matchedContract['max_query_length']) && \$queryLen > (int)\$matchedContract['max_query_length']) {
            \$record_and_kill('ZEUS.REQUEST_CONTRACT_VIOLATION', 'CONTRACT_QUERY_TOO_LONG', "Route query length exceeds contract ceiling.", 7, 414);
        }
        if (!empty(\$matchedContract['max_query_params']) && \$paramCount > (int)\$matchedContract['max_query_params']) {
            \$record_and_kill('ZEUS.REQUEST_CONTRACT_VIOLATION', 'CONTRACT_PARAMS_EXCEEDED', "Route param count exceeds contract ceiling.", 7, 400);
        }

        // Contract cross-site policy check (Sec-Fetch-Site on state-changing requests)
        \$crossSitePolicy = strtoupper((string)(\$matchedContract['cross_site_policy'] ?? 'SAME_ORIGIN'));
        if (\$crossSitePolicy === 'SAME_ORIGIN' && in_array(\$method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            \$secFetchSite = strtolower((string)(\$_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
            if (\$secFetchSite === 'cross-site') {
                \$record_and_kill('ZEUS.REQUEST_CONTRACT_VIOLATION', 'CONTRACT_CROSS_SITE_VIOLATION', "Cross-site state mutation blocked by contract.", 8, 403);
            }
        }

        // Contract route-specific rate budget
        if (!empty(\$matchedContract['rate_budget']) && is_array(\$matchedContract['rate_budget']) && !\$isWhitelisted) {
            \$rLimit = (int)(\$matchedContract['rate_budget']['limit'] ?? 60);
            \$rWindow = (int)(\$matchedContract['rate_budget']['window'] ?? 60);
            \$rKey = 'vgt_zrb_' . md5(\$ip . '|' . (\$matchedContract['path'] ?? \$pathOnly));
            \$rExceeded = false;
            if (function_exists('apcu_inc')) {
                \$count = apcu_inc(\$rKey, 1, \$success, \$rWindow);
                if (\$success && \$count > \$rLimit) \$rExceeded = true;
            } else {
                \$bFile = \$vaultDir . 'cache/' . \$rKey . '.dat';
                \$fh = @fopen(\$bFile, 'c+b');
                if (\$fh && flock(\$fh, LOCK_EX)) {
                    [\$cCount, \$cExp] = explode(':', (string)fread(\$fh, 64) . ':0:0');
                    \$cCount = (int)\$cCount; \$cExp = (int)\$cExp;
                    if (time() > \$cExp) { \$cCount = 1; \$cExp = time() + \$rWindow; } else { \$cCount++; }
                    fseek(\$fh, 0); ftruncate(\$fh, 0); fwrite(\$fh, \$cCount . ':' . \$cExp);
                    flock(\$fh, LOCK_UN); fclose(\$fh);
                    if (\$cCount > \$rLimit) \$rExceeded = true;
                } elseif (\$fh) { fclose(\$fh); }
            }
            if (\$rExceeded) {
                header('Retry-After: ' . \$rWindow);
                \$record_and_kill('ZEUS.BUDGET_EXCEEDED', 'CONTRACT_RATE_EXCEEDED', "Route rate budget exceeded.", 7, 429);
            }
        }

        // Contract Admission Requirement
        if (!empty(\$matchedContract['admission_required'])) {
            \$admToken = \$_COOKIE['vgt_zeus_adm'] ?? (\$_GET['vgt_adm'] ?? null);
            \$surface = str_starts_with(\$pathOnly, '/wp-admin/') ? 'admin' : (str_starts_with(\$pathOnly, '/wp-login.php') ? 'login' : 'all');
            \$res = \$verify_admission_token(\$admToken, \$surface);
            if (!\$res['valid']) {
                \$record_and_kill('ZEUS.ADMISSION_REJECT', 'ADMISSION_TOKEN_REJECTED', 'Admission required: ' . (\$res['reason'] ?? 'TOKEN_INVALID'), 8, 403);
            }

            // Clean URL Exchange: If token came via ?vgt_adm=..., set Secure HttpOnly cookie and 302 redirect
            if (isset(\$_GET['vgt_adm'])) {
                \$secure = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') || (isset(\$_SERVER['SERVER_PORT']) && \$_SERVER['SERVER_PORT'] === '443');
                setcookie('vgt_zeus_adm', \$admToken, [
                    'expires'  => (int)(\$res['payload']['exp'] ?? (time() + 300)),
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => \$secure,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                \$clean = preg_replace('/([?&])vgt_adm=[^&]*(&|$)/', '$1', \$rawUri);
                \$clean = rtrim(\$clean, '?&');
                if (\$clean === '') \$clean = '/';
                header('Location: ' . \$clean, true, 302);
                header('Cache-Control: no-store, no-cache, must-revalidate');
                exit;
            }
        }
    }

    // 11. GLOBAL LOGIN ADMISSION MODE (If configured in dashboard)
    if ((\$config['login_admission_mode'] ?? 'DISABLED') === 'ADMISSION_TOKEN') {
        if (\$pathOnly === '/wp-login.php' || str_starts_with(\$pathOnly, '/wp-admin/')) {
            \$admToken = \$_COOKIE['vgt_zeus_adm'] ?? (\$_GET['vgt_adm'] ?? null);
            \$surface = str_starts_with(\$pathOnly, '/wp-admin/') ? 'admin' : 'login';
            \$res = \$verify_admission_token(\$admToken, \$surface);
            if (!\$res['valid']) {
                \$record_and_kill('ZEUS.ADMISSION_REJECT', 'LOGIN_ADMISSION_REQUIRED', 'Admission required for login surface: ' . (\$res['reason'] ?? ''), 8, 403);
            }
        }
    }

    // 12. REQUEST BUDGET ENGINE (Actor IP + Subnet Rate Counters with Action Mode)
    if (!\$isWhitelisted && !empty(\$config['budget_enabled'])) {
        \$ipLimit = (int)(\$config['budget_ip_limit'] ?? 180);
        \$subnetLimit = (int)(\$config['budget_subnet_limit'] ?? 450);
        \$actionMode = strtoupper((string)(\$config['budget_action_mode'] ?? 'THROTTLE'));

        \$subnet = (strpos(\$ip, ':') !== false)
            ? implode(':', array_slice(explode(':', \$ip), 0, 4)) . '::/64'
            : preg_replace('/\.\d+$/', '.0/24', \$ip);

        \$nowTs = time();
        \$window = 60;
        \$rateExceeded = false;
        \$exceededType = 'IP';

        // Check Actor IP
        \$ipKey = 'vgt_zb_' . md5(\$ip);
        if (function_exists('apcu_inc')) {
            \$cIp = apcu_inc(\$ipKey, 1, \$s1, \$window);
            if (\$s1 && \$cIp > \$ipLimit) { \$rateExceeded = true; \$exceededType = 'IP'; }
        } else {
            \$f = \$vaultDir . 'cache/' . \$ipKey . '.dat';
            \$fh = @fopen(\$f, 'c+b');
            if (\$fh && flock(\$fh, LOCK_EX)) {
                [\$cCount, \$cExp] = explode(':', (string)fread(\$fh, 64) . ':0:0');
                \$cCount = (int)\$cCount; \$cExp = (int)\$cExp;
                if (\$nowTs > \$cExp) { \$cCount = 1; \$cExp = \$nowTs + \$window; } else { \$cCount++; }
                fseek(\$fh, 0); ftruncate(\$fh, 0); fwrite(\$fh, \$cCount . ':' . \$cExp);
                flock(\$fh, LOCK_UN); fclose(\$fh);
                if (\$cCount > \$ipLimit) { \$rateExceeded = true; \$exceededType = 'IP'; }
            } elseif (\$fh) { fclose(\$fh); }
        }

        // Check Subnet
        if (!\$rateExceeded) {
            \$subKey = 'vgt_zbs_' . md5(\$subnet);
            if (function_exists('apcu_inc')) {
                \$cSub = apcu_inc(\$subKey, 1, \$s2, \$window);
                if (\$s2 && \$cSub > \$subnetLimit) { \$rateExceeded = true; \$exceededType = 'SUBNET'; }
            } else {
                \$f = \$vaultDir . 'cache/' . \$subKey . '.dat';
                \$fh = @fopen(\$f, 'c+b');
                if (\$fh && flock(\$fh, LOCK_EX)) {
                    [\$cCount, \$cExp] = explode(':', (string)fread(\$fh, 64) . ':0:0');
                    \$cCount = (int)\$cCount; \$cExp = (int)\$cExp;
                    if (\$nowTs > \$cExp) { \$cCount = 1; \$cExp = \$nowTs + \$window; } else { \$cCount++; }
                    fseek(\$fh, 0); ftruncate(\$fh, 0); fwrite(\$fh, \$cCount . ':' . \$cExp);
                    flock(\$fh, LOCK_UN); fclose(\$fh);
                    if (\$cCount > \$subnetLimit) { \$rateExceeded = true; \$exceededType = 'SUBNET'; }
                } elseif (\$fh) { fclose(\$fh); }
            }
        }

        if (\$rateExceeded) {
            if (\$actionMode === 'XDR_SIGNAL') {
                \$record_event('ZEUS.BUDGET_EXCEEDED', 'BUDGET_' . \$exceededType . '_SIGNAL', "Actor {\$ip} ({\$subnet}) exceeded budget limit.", 6, 'SIGNAL');
            } elseif (\$actionMode === 'TEMPORARY_REJECT') {
                header('Retry-After: 60');
                \$record_and_kill('ZEUS.BUDGET_EXCEEDED', 'BUDGET_' . \$exceededType . '_REJECTED', "Actor exceeded rate budget.", 7, 503);
            } else { // THROTTLE
                header('Retry-After: 60');
                \$record_and_kill('ZEUS.BUDGET_EXCEEDED', 'BUDGET_' . \$exceededType . '_THROTTLED', "Actor exceeded rate budget.", 7, 429);
            }
        }
    }

    // Request Admitted cleanly to boot WordPress!
})();
WAF_PAYLOAD;
    }

    public function compile_waf_payload(): void {
        Zeus_Vault_Resolver::ensureDirectories();

        $payload = $this->generate_payload_code();

        // 1. Stage candidate to temporary file
        $temp_file = wp_normalize_path($this->vault_dir . 'zeus-waf.tmp.' . bin2hex(random_bytes(16)) . '.php');
        file_put_contents($temp_file, $payload, LOCK_EX);
        @chmod($temp_file, 0600);

        // 2. Syntax Validation
        if (function_exists('token_get_all')) {
            try {
                token_get_all($payload, TOKEN_PARSE);
            } catch (\ParseError $e) {
                @unlink($temp_file);
                throw new \RuntimeException('WAF compilation syntax validation failed: ' . $e->getMessage());
            }
        }

        // 3. Backup active WAF to Last Known Good (LKG) slot
        if (is_file($this->waf_file) && is_readable($this->waf_file)) {
            @copy($this->waf_file, $this->lkg_file);
            @chmod($this->lkg_file, 0600);
        }

        // 4. Atomic Swap
        if (!@rename($temp_file, $this->waf_file)) {
            if (is_file($this->waf_file)) @unlink($this->waf_file);
            @rename($temp_file, $this->waf_file);
        }
        @chmod($this->waf_file, 0600);

        // 5. Mirror to secondary VIS_VAULT_DIR if defined and distinct
        $sec_vault = Zeus_Vault_Resolver::getSecondaryVaultDir();
        if ($sec_vault !== null) {
            if (!is_dir($sec_vault)) @mkdir($sec_vault, 0700, true);
            @copy($this->waf_file, $sec_vault . 'zeus-waf.php');
            @chmod($sec_vault . 'zeus-waf.php', 0600);
        }
    }
}
