<?php
declare(strict_types=1);

/**
 * VISIONGAIA TECHNOLOGY CODE ARTIFACT
 * TITLE: VIS_Aegis_Assurance_Kernel_V18 (Immutable Request Pipeline)
 * TYPE: SECURITY KERNEL
 * VERSION: 18.0.0
 * STATUS: DIAMANT VGT SUPREME / PRODUCTION READY
 * KERNEL UPGRADE: O(1) Heuristic Bypass, Synchronous Execution, JSON CPU Optimization, Deep In-Memory Sanitization
 * OPTIMIZATION UPDATE: Fast Kernel Kill for Static Path Probes (AI-Bypass & Latency Zeroing)
 * HARDENING UPDATE: Two-Phase Pipeline (Atomic Checks First, Heuristics Second) & Intelligent SQL Comment Collapser
 * HOTFIX UPDATE: Anti-Slicing Quote-Slash Normalizer, Tag-Smuggling Event Blockers & Exploded Atomic Signatures
 */

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

final class VIS_Aegis {

    private bool $enabled;
    private string $mode;
    private int $scan_limit;
    private bool $assessment_mode = false;
    /** @var array{verdict:string,vector:string}|null */
    private ?array $assessment_verdict = null;
    private int $inspected_bytes = 0;
    private int $inspected_nodes = 0;
    private int $oracle_calls = 0;
    
    // VGT DYNAMIC WHITELIST
    private array $whitelist_ips = [];
    private array $whitelist_uas = [];
    
    private const SCAN_LIMIT_DEFAULT = 1048576; // 1MB Body Limit
    private const CHUNK_SIZE = 8192;
    private const MAX_RECURSION_DEPTH = 15;
    private const MAX_INSPECTED_BYTES = 4194304;
    private const MAX_INSPECTED_NODES = 10000;
    private const MAX_HEADER_BYTES = 65536;
    private const MAX_ORACLE_CALLS = 2;
    private const MAX_UPLOAD_BYTES = 26214400;
    private const UPLOAD_EDGE_SCAN_BYTES = 1048576;

    // L1 In-Memory Cache für Laufzeit-Optimierung
    private static ?array $combined_signatures = null;
    private static ?string $learning_regex_cache = null;
    private static ?array $nexus_signatures = null;
    
    // [ DIAMANT VGT FIX: ZERO-ALLOCATION GORGON CACHE ]
    private static ?int $gorgon_reputation_cache = null;

    // [ VGT APEX MATRIX UPDATE: GEHÄRTET, PARTITIONIERT & JIT-OPTIMIERT ]
    private const SIG_ATOMIC_KILL = [
        'rce_eval'           => '/(?i)\b(eval|assert|passthru|shell_exec|system|exec|proc_open|popen|pcntl_exec|create_function)\b\s*[\(\[]/S',
        'rce_callbacks'      => '/(?i)\b(call_user_func|call_user_func_array|array_map|array_filter|array_walk|usort|uasort|uksort|preg_replace_callback|register_shutdown_function|register_tick_function|ob_start)\b\s*[\(\[]/S',
        'rce_misc'           => '/(?i)\b(phpinfo|putenv|mail|dl|ffi_load|invokefunction)\b\s*[\(\[]/S',
        'rce_backticks'      => '/`[^`]{1,255}`/S',
        'rce_subshells'      => '/\$\((?:[^)]+)\)/S',
        'rce_jndi_env'       => '/(?i)\$\{(?>jndi|env):[^\}]+\}|\$\{jndi:(?>ldap|rmi|dns|iiop|http|https):\/\//S',
        'rce_cmd_extensions' => '/(?i)\b(cmd|bash|sh|powershell|pwsh|python|perl|ruby)\.exe/S',
        'rce_cmd_paths'      => '/\/(bin|usr|etc|var|tmp|opt)\/(sh|bash|zsh|dash|ksh|whoami|id|cat|ls|pwd|wget|curl|nc|powershell|uname)/S',
        'rce_cmd_standalone' => '/\b(cat|whoami|id|uname|hostname|tac|wget|curl|nc|nmap|nslookup|dig|ping|traceroute)\b/S',
        'rce_windows_chain'  => '/(?i)(?:&&|\|\||[;&|])\s*(?:net\s+user|net\s+localgroup|whoami|powershell|cmd(?:\.exe)?\s+\/c)\b/S',
        'rce_serialized'     => '/(?i)O:\d+:"[^"]+":\d+:\{|a:\d+:\{|C:\d+:"[^"]+":|rO0AB|\\\\x80\\\\x04\\\\x95/S',
        'lfi_rfi_os'         => '/(?i)(?>\.\.[\/\\\\])|(?>\/etc\/+[.\/]*?(?>passwd|shadow|hosts|group|issue|fstab|hostname))|(?>\/var\/log\/(?>auth|secure|messages|syslog|apache2|nginx|access|error)\.log)|(?>c:\\\\(?>windows|winnt)(?>\\\\(?>system32|repair|system|sam))?)|(?>\bboot\.ini\b)|(?>wp-config\.php)|(?>php:\/\/(?>filter|input|temp|memory))|(?>\b(?>file|zip|phar|data|expect|input|glob|ssh2|ogg|rar|zlib):\/\/)|(?>\/proc\/(?>self|version|cmdline|environ|net\/arp))|%00|%%32%65|%2e%2e/S',
        'shell_evasion'      => '/(?i)(?>\/(?>[a-z0-9_\-\.]+[\?\*]+[a-z0-9_\-\.]*|[a-z0-9_\-\.]*[\?\*]+[a-z0-9_\-\.]+)){2,}|(?>\/(?>bin|etc|usr|var|opt|dev|tmp|sys)\/[a-z0-9_\-\.]*[\?\*]+[a-z0-9_\-\.]*)|(?>\/[a-z0-9_\-\.]*[\?\*]+[a-z0-9_\-\.]*\/(?>cat|tail|more|less|head|sh|bash|zsh|dash|ksh|python|perl|ruby|nc|nmap|curl|wget|passwd|shadow|group|hosts)\b)/S',
        'xxe_ssrf'           => '/(?i)(?><!ENTITY\s+[^>]+(?>SYSTEM|PUBLIC)\s+["\'])|(?><!DOCTYPE\s+[^>]+\[)|(?>xmlns:xsi\s*=)|(?>xsi:schemaLocation\s*=)|(?>\b(?>gopher|dict|ldap|sftp):\/\/)|169\.254\.169\.254|metadata\.google\.internal|aws-env/S',
        'array_bypass'       => '/(?i)(?>\b[a-z0-9_]+(?:\[|%5B)[a-z0-9_\'"%]*?(?:\]|%5D)\s*=(?>\s|%20)*(?>system|exec|shell_exec|eval|assert|passthru|popen|proc_open|pcntl_exec|phpinfo))/S',
        'sqli_union'         => '/(?i)u[\W_]*n[\W_]*i[\W_]*o[\W_]*n(?>[\W_]+(?>all|distinct))?[\W_]+s[\W_]*e[\W_]*l[\W_]*e[\W_]*c[\W_]*t|\bunion\s*select\b|\bunionselect(?=\s|all\b|distinct\b|null\b|\d|\()/S',
        'sqli_comment_tail'  => '/(?i)(?:^|[\s(])[a-z0-9_.-]{1,64}[\'"]\s*(?:--(?:\s|$)|#)/S',
        'sqli_select'        => '/(?i)\bselect[^;]{1,250}?from/S',
        'sqli_functions'     => '/(?i)\b(benchmark|sleep|extractvalue|updatexml|hex|unhex|concat|char|cast|pg_sleep)\s*\(|waitfor[\s\/\*]+delay/S',
        'sqli_structure'     => '/(?i)\b(information_schema|order[\s\/\*]+by|group[\s\/\*]+by|having)\b|declare[^@]{1,128}?@[^=]{1,128}?=/S',
        'sqli_logic'         => '/(?i)\b(OR|AND|XOR)\b\s*\(?\s*select\b|(\|\||&&)\s*\(?\s*select\b|\b(OR|AND|XOR)\b[\s\/\*\(\'\"\-\+]+[a-zA-Z0-9_\-]+[\s\/\*\'\"]*[=><!]+|\bEXP\s*\(\s*~\s*\(\s*select\b/S',
        'sqli_hard_kill'     => '/(?i)(?>\b0x[0-9a-fA-F]{2,}\b)|(?>\$wpdb->(?:get_results|query|get_var|get_col|get_row|prepare))|(?>(?:"|\')?\$(?>where|ne|regex|gt|lt|exists|expr|nin)(?:"|\')?\s*:)/S',
        'gql_recon'          => '/(?i)(?>__(?>schema|type)\s*(?>\{|\(|:))/S',
        'node_rsc_pollution' => '/(?i)(?>\$[a-zA-Z0-9_]+:(?>__proto__|constructor:constructor))|(?>NEXT_REDIRECT;push;)|(?>\bthrow\s+Object\.assign\(\s*new\s+Error\(\s*[\'"]NEXT_REDIRECT)|(?>global\[[^\]]+\]\.from\()/S',
        'hex_buffer_eval'    => '/(?i)(?>global\[(?:[\'"]\\\\u0042\\\\u0075\\\\u0066\\\\u0066\\\\u0065\\\\u0072[\'"]|\[[\'"]B[\'"],[\'"]uff[\'"]).*?\]\.from)|(?>[0-9a-fA-F]{200,}[\'"],[\'"]hex[\'"]\)\.toString)/S',
        'xss_hard_kill'      => '/(?i)(?>data:text\/(?>html|xml)(?>;charset=[^;]+)?(?:;base64)?,)/S',
        'xss_script_tags'    => '/(?i)(?><\s*\/?\s*script\b)|(?><\s*iframe\b)/S',
        'xss_event_handlers' => '/(?i)\bon(?>load|error|click|pointer|mouse|focus|blur|drag|drop|touch|submit|reset|select|change|keydown|keypress|keyup)\s*=/S',
        'xss_uri_schemes'    => '/(?i)\bjavascript\s*:|srcdoc\s*=|formaction\s*=/S',
        'xss_tags_with_events'=> '/(?i)<\s*(svg|img|body|iframe|audio|video|input|button|details|link|select|textarea|style|div|a)\b[^>]*?\bon[a-z]+\s*=/S',
        'rce_source_hijack'  => '/(?i)(?>action|data|plugin)[^&]*?(?>source|url|install|path)[^&]*?=(?>https?%3A%2F%2F|https?:\/\/|ftps?%3A%2F%2F|%68%74%70|%48%54%54%50)/S',
        'sensitive_archive_probes' => '/(?i)\/(?:[a-z0-9_\-\.]+\/)*(?:web|site|db|database|dump|backup|full|prod|root|www|wordpress|config|archive|sql|data|export|mysql|store|system|temp|tmp|user|users)\.(?:tar\.gz|tgz|zip|rar|7z|sql|bak|env|db|gz|bz2)\b/S',
    ];

    private const SIG_AI_DELEGATION = [
        'xss_unicode'   => '/(?i)(?>%ef%bc%9c|\xef\xbc\x9c|＜|\\\\uFF1C|%c0%bc|\\\\x3c)[\s]*(?>%ef|%u|\xef|\\\\u|ｓ|Ｓ|ｉ|Ｉ|ｏ|Ｏ|ｅ|Ｅ|ｍ|聲|[a-z\/])|(?>\xef\xbd\x8a|%ef%bd\x8a|ｊ)(?>\xef\xbd\x81|%ef%bd\x81|ａ)(?>\xef\xbd\x96|%ef%bd\x96|ｖ)|(?>\xef\xbd\x81|%ef%bd\x81|ａ)(?>\xef\xbd\x8e|%ef%bd\x8e|ｎ)(?>\xef\xbd\x8c|%ef%bd\x8c|ｌ)(?>\xef\xbd\x85|%ef%bd\x85|ｅ)(?>\xef\xbd\x8e|%ef%bd\x8e|ｎ)(?>\xef\xbd\x94|%ef%bd\x94|ｔ)(?>\xef\xbd\x85|%ef%bd\x85|ｅ)(?>\xef\xbd\x92|%ef%bd\x92|ｒ)(?>\xef\xbd\x8f|%ef%bd\x8f|ｏ)(?>\xef\xbd\x96|%ef%bd\x96|ｖ)(?>\xef\xbd\x85|%ef%bd\x85|ｅ)(?>\xef\xbc\x88|%ef%bc\x88|（|\\\\uFF08|\()/S',
        'xss_dom'       => '/(?i)(?><(?>script|iframe|object|embed|math|applet|meta|bgsound|blink|keygen|marquee|template))|(?>\b(?>javascript|vbs|vbscript):)|(?>on(?>load|error|click|dblclick|mouseover|mouseenter|mouseleave|submit|reset|focus|blur|contextmenu|animationstart|toggle|keyup|keydown|pointer|touch|drag|drop|wheel|copy|paste|cut)[\s\/\*]*=)|(?>base64_decode|data:text\/(?>html|xml))|(?>\b(?>alert|confirm|prompt)\s*\()|<svg|xlink:href|&#|(?>srcdoc[\s\/\*]*=)|importmap|-moz-binding/S',
        'framework'     => '/(?i)(?>\b(?>wp_set_current_user|wp_insert_user|wp_update_user)\b)|(?>update_option\s*\(\s*[\'"](?>siteurl|home|users_can_register|default_role)[\'"])|eval-stdin|_ignition\/execute-solution|telescope\/requests|api\/swagger|actuator\/(?>env|refresh|restart|heapdump)|(?>__(?>schema|type)\s*(?>\{|\(|:))/S',
        'probes'        => '/(?i)(?>\.(?>env|git|htaccess|php_bak|old|bak|sql|tar\.gz|zip|remote-sync|ds_store|idea|vscode))|config\.php|wp-config\.php|\.aws\/credentials|vendor\/phpunit|composer\.json|phpunit\/src|\/\.svn\/|\/\.hg\/|\/web\.config|\/\.user\.ini|\/telescope\/|\/horizon\/|\/_profiler\//S',
    ];

    private const SIG_SUSPICIOUS = [
        'obfuscation'  => '/(?i)(?>\b(?>base64_decode|base64_encode|str_rot13|gzinflate|gzuncompress|deflate)\b\s*\()/S',
        'globals_mod'  => '/(?i)(?>\$(?>GLOBALS|_SERVER|_GET|_POST|_FILES|_COOKIE|_SESSION|_REQUEST|_ENV)\s*\[)/S',
        'file_ops'     => '/(?i)(?>\b(?>fopen|fwrite|file_put_contents|file_get_contents|readfile|unlink|rename|copy|mkdir|rmdir|chmod|chown)\b\s*\()/S',
        'db_direct'    => '/(?i)\$wpdb->|(?>\b(?>mysql_query|mysqli_query|pg_query|sqlite_query|PDO::exec)\b)/S',
        'sqli_wpdb'    => '/\$wpdb->(?:get_results|query|get_var|get_col|get_row)\s*\(/i',
        'hex_encode'   => '/(?i)(?>\\\\x[0-9a-f]{2}){4,}|(?>%[0-9a-f]{2}){6,}/S',
        'high_entropy' => '/(?:[\(\)\`\$\<\>\[\]\{\}]){6,}/S',
        'crypto_miner' => '/(?i)\b(?>coinhive|webminer|cryptonight|stratum\+tcp|monero|xmr\.omine|coinimp|minr\.js)\b/S',
        'dynamic_exec' => '/(?i)(?>\$\s*[\'"][a-zA-Z0-9_\x7f-\xff]+[\'"]\s*\()|(?>\$_?(?>GET|POST|REQUEST|COOKIE|SERVER)\s*\[[^\]]+\]\s*\()/S'
    ];

    private const SIG_HEADERS_ONLY = [
        'ua_malicious' => '/(?i)\b(?>sqlmap|nikto|wpscan|masscan|havij|netsparker|acunetix|nessus|gobuster|dirbuster|zgrab|nuclei|ffuf|projectdiscovery|zmap|nmap|shodan|census|arachni|hydra|medusa|w3af|owasp-zap|blackwidow|jbrofuzz|sqlninja|webinspect)\b/S'
    ];

    public function __construct(array $options = []) {
        if (empty($options)) {
             $options = get_option('vis_config', []);
             if (!is_array($options)) {
                 $options = [];
             }
        }

        $this->enabled    = !empty($options['aegis_enabled']);
        
        // [ DIAMANT VGT FIX: ZERO-TRUST OPTION SANITIZATION (NATIVE) ]
        $raw_mode         = (string)($options['aegis_mode'] ?? 'strict');
        $candidate_mode   = preg_replace('/[^a-z0-9_-]/', '', strtolower($raw_mode));
        $this->mode       = in_array($candidate_mode, ['strict', 'balanced', 'learning'], true) ? $candidate_mode : 'strict';
        $this->scan_limit = max(65536, min(2097152, (int)($options['aegis_scan_limit'] ?? self::SCAN_LIMIT_DEFAULT)));
        
        $raw_ips = $options['aegis_whitelist_ips'] ?? ($options['whitelist_ips'] ?? '');
        $raw_uas = $options['aegis_whitelist_uas'] ?? ($options['whitelist_uas'] ?? '');

        // VGT NATIVE IP VALIDATION
        $this->whitelist_ips = array_filter(array_map('trim', explode("\n", $raw_ips)), static function($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP);
        });
        
        // VGT NATIVE UA SANITIZATION
        $this->whitelist_uas = array_filter(array_map(static function($ua) {
            return preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string)$ua));
        }, explode("\n", $raw_uas)));

        // Dynamic Constant Definitions linked to native Dashboard Logging Layout
        if (!defined('VIS_TABLE_LOGS')) {
            define('VIS_TABLE_LOGS', 'vis_omega_logs'); // Fixes dashboard loading mismatch!
        }
        if (!defined('VIS_TABLE_BANS')) {
            define('VIS_TABLE_BANS', 'vis_apex_bans');
        }

        $downloads = '\\VisionGaia\\GeDefense\\Modules\\SecureDownloads\\DownloadManager';
        if (class_exists($downloads) && $downloads::isTrustedRequest()) {
            return;
        }

        // [ VGT OMEGA FIX: SYNCHRONOUS EXECUTION ]
        $this->run_guard();
    }

    private static function get_combined_signatures(): array {
        if (self::$combined_signatures === null) {
            self::$combined_signatures = array_merge(self::SIG_ATOMIC_KILL, self::SIG_AI_DELEGATION, self::SIG_HEADERS_ONLY);
        }
        return self::$combined_signatures;
    }

    private static function get_nexus_signatures(): array {
        if (self::$nexus_signatures !== null) {
            return self::$nexus_signatures;
        }

        $threat_map = wp_cache_get('vgt_nexus_matrix_map', 'vgt');
        if ($threat_map === false) {
            $vault_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/vgt-vault/gorgon/' : dirname(ABSPATH) . '/wp-content/vgt-vault/gorgon/';
            $matrix_file = wp_normalize_path($vault_dir . 'nexus_matrix.json');
            
            if (is_readable($matrix_file)) {
                $json_data = json_decode((string)file_get_contents($matrix_file), true);
                $threat_map = $json_data['patterns'] ?? [];
            } else {
                $threat_map = [];
            }
            wp_cache_set('vgt_nexus_matrix_map', $threat_map, 'vgt', 3600);
        }

        self::$nexus_signatures = [];
        if (is_array($threat_map)) {
            foreach ($threat_map as $name => $pattern_data) {
                if (!empty($pattern_data['signature'])) {
                    $signature = (string)$pattern_data['signature'];
                    if ($signature === '' || strlen($signature) > 512 || str_contains($signature, "\0")) continue;
                    $pattern = '~' . preg_quote($signature, '~') . '~iu';
                    self::$nexus_signatures[strtoupper((string)$name)] = $pattern;
                }
            }
        }

        return self::$nexus_signatures;
    }

    public function run_guard(): void {
        if (defined('VIS_AEGIS_GUARD_RUN')) {
            return;
        }
        if (!$this->enabled) {
            return;
        }
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        $ip = $this->get_secure_ip();
        
        if ($this->is_whitelisted($ip)) {
            return;
        }
        
        $old_backtrack = false;
        $old_recursion = false;
        try {
            $old_backtrack = ini_set('pcre.backtrack_limit', '1000000');
            $old_recursion = ini_set('pcre.recursion_limit', '1000000');

            $this->guard($ip);
            define('VIS_AEGIS_GUARD_RUN', true);

        } catch (\Throwable $e) {
            error_log('[VIS AEGIS CRITICAL] ' . $e->getMessage());
            $this->fail_closed_runtime();
        } finally {
            if ($old_backtrack !== false) ini_set('pcre.backtrack_limit', $old_backtrack);
            if ($old_recursion !== false) ini_set('pcre.recursion_limit', $old_recursion);
        }
    }

    /**
     * Executes the production detection pipeline without logging, banning, network
     * calls or terminating the process. This is the sole supported benchmark API.
     *
     * @return array{verdict:string,vector:string}
     */
    public function assess_payload(string $input, string $context = 'ASSESSMENT_PAYLOAD'): array {
        return $this->assessment(static function(self $aegis) use ($input, $context): void {
            $normalized = $aegis->normalize_payload($input);
            $aegis->scan_string_atomic($input, $context . '_RAW');
            if ($normalized !== $input) {
                $aegis->scan_string_atomic($normalized, $context . '_NORMALIZED');
            }
            $aegis->scan_string_heuristic($input, $context . '_RAW');
            if ($normalized !== $input) {
                $aegis->scan_string_heuristic($normalized, $context . '_NORMALIZED');
            }
        });
    }

    /** @return array{verdict:string,vector:string} */
    public function assess_uri(string $uri): array {
        if ($uri === '' || strlen($uri) > self::MAX_HEADER_BYTES || str_contains($uri, "\0")) {
            return ['verdict' => 'BLOCK', 'vector' => 'uri_boundary'];
        }

        return $this->assessment(static function(self $aegis) use ($uri): void {
            $aegis->inspect_uri($uri);
        });
    }

    /**
     * @param callable(self):void $operation
     * @return array{verdict:string,vector:string}
     */
    private function assessment(callable $operation): array {
        if ($this->assessment_mode) {
            throw new \LogicException('Nested Aegis assessment rejected.');
        }

        $this->assessment_mode = true;
        $this->assessment_verdict = null;
        $this->assessment_verdict = null;

        try {
            $operation($this);
        } catch (\RuntimeException $e) {
            if ($this->assessment_verdict === null) {
                throw $e;
            }
        } finally {
            $this->assessment_mode = false;
        }

        $verdict = $this->assessment_verdict ?? ['verdict' => 'ALLOW', 'vector' => 'none'];
        $this->assessment_verdict = null;
        return $verdict;
    }

    private function guard(string $ip): void {
        // Phase 1: Deep Inspection (Read-Only)
        $this->inspect_headers();
        $this->inspect_uri();
        $this->inspect_payload();
        
        // Enforcement is detection-only. Application input remains byte-identical
        // to prevent parser desynchronization between Aegis and WordPress.
    }

    private function consume_budget(int $bytes): void {
        $this->inspected_nodes++;
        $this->inspected_bytes += max(0, $bytes);
        if ($this->inspected_nodes > self::MAX_INSPECTED_NODES
            || $this->inspected_bytes > self::MAX_INSPECTED_BYTES) {
            $this->terminate('Request inspection budget exhausted', 'BLOCK', 'inspection_budget');
        }
    }

    private function inspect_payload(): void {
        // [ VGT OMEGA DETECT: DEEP QUERY STRING RAW DPI ]
        if (!empty($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING'])) {
            $qs = $_SERVER['QUERY_STRING'];
            $normalized_qs = $this->normalize_payload($qs);
            $this->scan_string_atomic($qs, 'RAW_QUERY_STRING');
            if ($normalized_qs !== $qs) {
                $this->scan_string_atomic($normalized_qs, 'VAL_QUERY_STRING');
            }
            $this->scan_string_heuristic($qs, 'RAW_QUERY_STRING');
            if ($normalized_qs !== $qs) {
                $this->scan_string_heuristic($normalized_qs, 'VAL_QUERY_STRING');
            }
        }

        if (!empty($_GET)) {
            $this->inspect_superglobals($_GET, 'GET_DATA');
        }

        if (!empty($_COOKIE)) {
            $filtered_cookies = array_filter($_COOKIE, function($key) {
                return !preg_match('/^(_ga|_gid|_gcl|_fbp|wp-settings)/i', (string)$key);
            }, ARRAY_FILTER_USE_KEY);
            
            if (!empty($filtered_cookies)) {
                $this->inspect_superglobals($filtered_cookies, 'COOKIE_DATA');
            }
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $content_type = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        $is_multipart = str_contains($content_type, 'multipart/form-data');
        $is_json      = str_contains($content_type, 'application/json');

        if (!empty($_POST)) {
            $this->inspect_superglobals($_POST, 'POST_DATA');
        }
        if (!empty($_FILES)) {
            $this->inspect_files($_FILES);
        }

        if (!$is_multipart) {
            $this->inspect_body_stream($is_json);
        }
    }

    private function inspect_superglobals(array $data, string $context, int $depth = 0): void {
        if ($depth > self::MAX_RECURSION_DEPTH) {
            $this->terminate("Deep Recursion Attack detected in $context", 'BLOCK', 'dos');
        }

        foreach ($data as $key => $value) {
            $this->consume_budget(strlen((string)$key));
            $this->scan_string_atomic((string)$key, $context . '_KEY');
            $this->scan_string_heuristic((string)$key, $context . '_KEY');

            if (is_array($value)) {
                $this->inspect_superglobals($value, $context, $depth + 1);
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }
            
            $value_str = (string) $value;
            $this->consume_budget(strlen($value_str));
            if (strlen($value_str) < 3) {
                continue;
            }

            $normalized_value = $this->normalize_payload($value_str);
            
            // Phase 1: Schneller Atomic-Signaturencheck auf RAW & VAL
            $this->scan_string_atomic($value_str, $context . '_RAW');
            if ($normalized_value !== $value_str) {
                $this->scan_string_atomic($normalized_value, $context . '_VAL');
            }

            // Phase 2: Heuristiken & AI-Abfragen nur wenn kein Atomic Block getriggert wurde
            $this->scan_string_heuristic($value_str, $context . '_RAW');
            if ($normalized_value !== $value_str) {
                $this->scan_string_heuristic($normalized_value, $context . '_VAL');
            }
        }
    }

    private function inspect_files(array $files, int $depth = 0): void {
        if ($depth > self::MAX_RECURSION_DEPTH) {
            return;
        }
        foreach ($files as $fileInfo) {
            if (is_array($fileInfo)) {
                if (isset($fileInfo['tmp_name'])) {
                    if (is_array($fileInfo['tmp_name'])) {
                        $this->inspect_files_recursive_structure($fileInfo['tmp_name'], $fileInfo['name']);
                    } else {
                        $this->scan_file_content((string)$fileInfo['tmp_name'], (string)$fileInfo['name']);
                    }
                } else {
                    $this->inspect_files($fileInfo, $depth + 1);
                }
            }
        }
    }

    private function inspect_files_recursive_structure(array $tmp_names, array $names): void {
        foreach ($tmp_names as $key => $tmp) {
            $name = $names[$key] ?? 'unknown_file';
            if (is_array($tmp)) {
                $sub_names = is_array($names[$key] ?? null) ? $names[$key] : [];
                $this->inspect_files_recursive_structure($tmp, $sub_names);
            } else {
                $this->scan_file_content((string)$tmp, (string)$name);
            }
        }
    }

    private function scan_file_content(string $tmp_path, string $original_name): void {
        if ($tmp_path === '' || !is_uploaded_file($tmp_path)) {
            $this->terminate('Upload origin validation failed', 'BLOCK', 'upload_origin');
        }
        $realSize = filesize($tmp_path);
        if ($realSize === false || $realSize === 0 || $realSize > self::MAX_UPLOAD_BYTES) {
            $this->terminate('Size boundary violation.', 'BLOCK', 'limit_exhaustion');
        }

        $normalized_name = $this->normalize_payload($original_name);
        $this->scan_string_atomic($normalized_name, 'FILE_NAME');
        $this->scan_string_heuristic($normalized_name, 'FILE_NAME');
        if (!is_readable($tmp_path)) {
            return;
        }
        
        $handle = @fopen($tmp_path, 'rb');
        if (!$handle) {
            return;
        }
        
        $ranges = $realSize <= (self::UPLOAD_EDGE_SCAN_BYTES * 2)
            ? [[0, $realSize]]
            : [[0, self::UPLOAD_EDGE_SCAN_BYTES], [$realSize - self::UPLOAD_EDGE_SCAN_BYTES, self::UPLOAD_EDGE_SCAN_BYTES]];

        foreach ($ranges as [$offset, $length]) {
            if (fseek($handle, $offset, SEEK_SET) !== 0) {
                fclose($handle);
                $this->terminate('Upload scan seek failed', 'BLOCK', 'upload_io');
            }
            $scanned_bytes = 0;
            $overlap = '';
            while ($scanned_bytes < $length && !feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $scanned_bytes += strlen($chunk);
                $buffer = $overlap . $chunk;
                
                if (preg_match('/<\?php|<\?=|#! \/bin\/|eval\s*\(/i', $buffer)) {
                    fclose($handle);
                    $this->terminate("Polyglot Code Injection in File: $original_name", 'BLOCK', 'rce_upload_polyglot');
                }
                $overlap = substr($chunk, -256);
            }
        }
        fclose($handle);
    }

    /**
     * VGT KERNEL DIRECTIVE: ATOMIC SIGNATURE CHECK (Phase 1)
     * Abgleich gegen unüberwindbare atomare Block-Ausdrücke. 100% ReDoS- & JIT-sicher.
     */
    private function scan_string_atomic(string $input, string $context): void {
        if ($input === '' || strlen($input) < 3) {
            return;
        }

        // Härtung gegen WP Magic Quotes Slashes und nested quote obfuskation
        $mutations = [
            'raw'                  => $input,
            'no_quotes'            => str_replace(["'", '"', '`', '\\'], '', $input),
            'no_comments_space'    => preg_replace('/(?:\/\*.*?\*\/|<!\-\-.*?\-\->)/s', ' ', $input) ?? $input,
            'no_comments_stripped' => preg_replace('/(?:\/\*.*?\*\/|<!\-\-.*?\-\->)/s', '', $input) ?? $input,
        ];

        foreach ($mutations as $mutation_type => $mutated_input) {
            if ($mutated_input === '' || strlen($mutated_input) < 3) {
                continue;
            }

            foreach (self::SIG_ATOMIC_KILL as $type => $regex) {
                $match = @preg_match($regex, $mutated_input);
                if ($match === 1) {
                    $this->terminate("Threat detected in $context ($mutation_type): [$type]", 'BLOCK', $type);
                } elseif ($match === false) {
                    $last_error = preg_last_error();
                    if ($last_error !== PREG_NO_ERROR) {
                        $this->terminate("PCRE Engine Error in $context ($mutation_type) on [$type] | Error Code: $last_error", 'BLOCK', 'pcre_error');
                    }
                }
            }
        }
    }

    /**
     * VGT KERNEL DIRECTIVE: HEURISTIC / SUSPICION CHECK (Phase 2)
     * Berechnet den Risiko-Score und leitet an das GORGON-Netzwerk oder VIS Oracle weiter.
     */
    private function scan_string_heuristic(string $input, string $context): void {
        if ($input === '' || strlen($input) < 3) {
            return;
        }

        $mutations = [
            'raw'                  => $input,
            'no_quotes'            => str_replace(["'", '"', '`', '\\'], '', $input),
            'no_comments_space'    => preg_replace('/(?:\/\*.*?\*\/|<!\-\-.*?\-\->)/s', ' ', $input) ?? $input,
            'no_comments_stripped' => preg_replace('/(?:\/\*.*?\*\/|<!\-\-.*?\-\->)/s', '', $input) ?? $input,
        ];

        $suspicion_score = 0;
        foreach ($mutations as $mutation_type => $mutated_input) {
            if ($mutated_input === '' || strlen($mutated_input) < 3) {
                continue;
            }
            foreach (self::SIG_AI_DELEGATION as $type => $regex) {
                if (@preg_match($regex, $mutated_input) === 1) {
                    $suspicion_score += 50; 
                    break 2; // Beendet beide Loops um doppeltes Scoring zu unterdrücken
                }
            }
        }

        if (self::$learning_regex_cache === null) {
            $candidate = get_option('vgt_learning_regex', '');
            self::$learning_regex_cache = is_string($candidate) && strlen($candidate) <= 1024 ? $candidate : '';
        }

        if (self::$learning_regex_cache !== '') {
            $match = @preg_match(self::$learning_regex_cache, $input);
            if ($match === 1) {
                $this->terminate("Neural Swarm Threat detected in $context: [GORGON_SWARM_THREAT]", 'BLOCK', 'GORGON_SWARM_THREAT');
            } elseif ($match === false) {
                $last_error = preg_last_error();
                if ($last_error !== PREG_NO_ERROR) {
                    $this->terminate("PCRE Engine Error in Learning Pattern | Error Code: $last_error", 'BLOCK', 'pcre_error');
                }
            }
        }

        $nexus_sigs = self::get_nexus_signatures();
        foreach ($nexus_sigs as $threat_name => $pattern) {
            $match = @preg_match($pattern, $input);
            if ($match === 1) {
                $this->terminate("Neural Swarm Threat detected in $context: [$threat_name]", 'BLOCK', $threat_name);
            } elseif ($match === false) {
                $last_error = preg_last_error();
                if ($last_error !== PREG_NO_ERROR) {
                    $this->terminate("PCRE Engine Error in Nexus Pattern [$threat_name] | Error Code: $last_error", 'BLOCK', 'pcre_error');
                }
            }
        }

        $len = strlen($input);
        if ($len > 6) {
            if (@preg_match('/(?i)\b(ignore|bypass|override|instruction|rule|system|schema|drop|union|select|sleep)\b/S', $input) === 1) {
                $suspicion_score += 15; 
            }
            if (@preg_match('/(?:[\'\"`\$\{\}\[\]\(\)]\s*){4,}/S', $input) === 1) {
                $suspicion_score += 5; 
            }
        }

        if ($len > 32 && !str_starts_with($input, 'data:image/')) {
            $entropy = self::calculate_shannon_entropy($input);
            if ($entropy > 5.8) {
                $suspicion_score += 25;
                if ($entropy > 6.2 && @preg_match('/(?i)(base64_decode|gzinflate|eval|str_rot13|exec|passthru|shell_exec)/S', $input) === 1) {
                    $this->terminate("High-Entropy Obfuscated Code Attack in $context (Entropy: $entropy)", 'BLOCK', 'HIGH_ENTROPY_OBFUSCATION');
                }
            }
        }

        foreach (self::SIG_SUSPICIOUS as $type => $regex) {
             if (@preg_match($regex, $input) === 1) {
                 $suspicion_score += 35; 
             }
        }

        if ($suspicion_score >= 40) {
            if ($this->assessment_mode) {
                $this->terminate(
                    "Deterministic heuristic threshold exceeded in $context.",
                    'BLOCK',
                    'heuristic_threshold'
                );
            }

            if (self::$gorgon_reputation_cache === null && class_exists('\VisionGaia\GeDefense\Modules\Gorgon\Gorgon')) {
                try {
                    $reputation_ip = defined('VIS_RESOLVED_IP') ? VIS_RESOLVED_IP : $this->get_secure_ip();
                    self::$gorgon_reputation_cache = (int) \VisionGaia\GeDefense\Modules\Gorgon\Gorgon::get_instance()->query_global_reputation($reputation_ip);
                } catch (\Throwable $e) {
                    self::$gorgon_reputation_cache = 0;
                }
            }
            
            $total_threat_score = $suspicion_score + (self::$gorgon_reputation_cache ?? 0);

            if ($total_threat_score >= 100) {
                 $this->terminate("Global Reputation Block in $context (Score: $total_threat_score)", 'BLOCK', 'GORGON_REPUTATION_KILL');
            }

            if (class_exists('VIS_Aegis_Oracle') && $this->oracle_calls < self::MAX_ORACLE_CALLS) {
                try {
                    $this->oracle_calls++;
                    $oracle_data = VIS_Aegis_Oracle::judge($input);
                    $verdict = is_array($oracle_data) ? $oracle_data['verdict'] : $oracle_data;
                    
                    if ($verdict === 'BLOCK') {
                        $ai_reason = is_array($oracle_data) && !empty($oracle_data['reason']) ? $oracle_data['reason'] : 'Unknown AI Heuristic';
                        $clean_reason = substr(strip_tags($ai_reason), 0, 255);
                        $this->terminate("AI Threat [$context] | Oracle: $clean_reason", 'BLOCK', 'AI_DETECTED_THREAT');
                    }
                } catch (\Throwable $e) {
                    error_log("VIS_AEGIS_ORACLE_CRITICAL_FAILURE: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * VGT KERNEL DEEP SCAN NORMALIZER
     * Normalisiert Payloads über 5 rekursive URL-Ebenen, HTML-Entities, Hex und Unicode (IIS %u).
     * Enthält das intelligente comment-stripping zur SQL-Evasion-Abwehr.
     */
    private function normalize_payload(string $input): string {
        // Layer 0: Strip null bytes and control whitespace
        $normalized = str_replace(["\0", "\r", "\n", "\t"], ' ', $input);

        // Layer 1: URL Decoding (up to 5 nested levels)
        $loops = 0;
        do {
            $old = $normalized;
            $normalized = urldecode($normalized);
            $loops++;
        } while ($old !== $normalized && $loops < 5);

        // Layer 2: HTML Entity Decoding
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Layer 3: Unicode Escape Decoding (Hardened für \uXXXX und IIS-style %uXXXX)
        $normalized = preg_replace_callback(
            '/(?:\\\\u|%u)([0-9a-fA-F]{4})/i',
            static function (array $match): string {
                $decoded = html_entity_decode(
                    '&#x' . $match[1] . ';',
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );
                return $decoded === '' ? "\u{FFFD}" : $decoded;
            },
            $normalized
        ) ?? $normalized;

        // Layer 4: Hex Escape Decoding
        $normalized = preg_replace_callback(
            '/\\\\x([0-9a-fA-F]{2})/',
            static function ($m) {
                return chr(hexdec($m[1]));
            },
            $normalized
        ) ?? $normalized;

        // Layer 5: Intelligent Comment Slicing Defeater
        // Löscht Kommentare restlos, wenn sie zwischen Buchstaben/Zahlen/Underscores sitzen (sel/**/ect -> select)
        // Ersetzt Kommentare ansonsten durch ein Leerzeichen, um Syntaxgrenzen zu wahren (select/**/1 -> select 1)
        $normalized = preg_replace('/(?<=[a-zA-Z0-9_])\/\*(?:.*?)\*\/(?=[a-zA-Z0-9_])/s', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/(?:\/\*(?:.*?)\*\/|<!\-\--(?:.*?)\-\->|--[\s\r\n]|#.*$)/sm', ' ', $normalized) ?? '';

        return $normalized;
    }

    private function is_whitelisted(string $ip): bool {
        // VGT KERNEL: Cryptographic Admin Token Whitelisting (Hardened against Session Hijacking)
        $token = $_SERVER['HTTP_X_VGT_ADMIN_TOKEN'] ?? ($_POST['vgt_admin_token'] ?? ($_GET['vgt_admin_token'] ?? ''));
        if (!empty($token) && class_exists('VIS_Vault') && method_exists('VIS_Vault', 'verify_admin_token')) {
            if (VIS_Vault::verify_admin_token((string)$token)) {
                return true;
            }
        }

        // Authenticated WordPress Administrator / Editor Whitelisting
        if (function_exists('wp_get_current_user')) {
            $current_user = wp_get_current_user();
            if ($current_user && $current_user->exists() && ($current_user->has_cap('edit_posts') || $current_user->has_cap('manage_options'))) {
                return true;
            }
        }

        // Early bootstrap fallback: validate logged-in auth cookie if available
        if (function_exists('wp_validate_auth_cookie')) {
            foreach ($_COOKIE as $cookie_name => $cookie_val) {
                if (str_starts_with($cookie_name, 'wordpress_logged_in_') && is_string($cookie_val)) {
                    $user_id = wp_validate_auth_cookie($cookie_val, 'logged_in');
                    if ($user_id && function_exists('user_can') && (user_can($user_id, 'edit_posts') || user_can($user_id, 'manage_options'))) {
                        return true;
                    }
                }
            }
        }

        if (apply_filters('vis_aegis_skip_injection', false)) {
            return true;
        }
        
        // Zero-Trust: Localhost Whitelisting nur auf echter physikalischer Socket-Ebene!
        $socket_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (in_array($socket_ip, ['127.0.0.1', '::1', 'fe80::1'], true)) {
            return true;
        }
        
        if (defined('DOING_CRON') && DOING_CRON) {
            $server_ip = $_SERVER['SERVER_ADDR'] ?? null;
            if ($server_ip && $ip === $server_ip) return true;
        }

        if (!empty($this->whitelist_ips) && in_array($ip, $this->whitelist_ips, true)) {
            return true;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!empty($this->whitelist_uas) && $ua !== '') {
            foreach ($this->whitelist_uas as $safe_ua) {
                if ($safe_ua === '') continue;
                if (hash_equals(strtolower($safe_ua), strtolower($ua))) return true;
            }
        }

        if ($ua !== '' && preg_match('/(googlebot|bingbot|duckduckbot|yandexbot)/i', $ua)) {
            $hostname = gethostbyaddr($ip);
            if ($hostname !== false && $hostname !== $ip) {
                $forward_ips = gethostbynamel($hostname);
                if (is_array($forward_ips) && in_array($ip, $forward_ips, true)) {
                    if (preg_match('/(?:googlebot\.com|search\.msn\.com|yandex\.com|yandex\.net|yandex\.ru|duckduckgo\.com)$/i', $hostname)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function inspect_body_stream(bool $is_json): void {
        $content_length = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        if ($content_length > $this->scan_limit) {
            $this->terminate("Payload Size Exhaustion", 'BLOCK', 'limit_exhaustion');
        }
        $raw_body = @file_get_contents('php://input', false, null, 0, $this->scan_limit + 1);
        if ($raw_body === false || $raw_body === '') {
            return;
        }

        if (strlen($raw_body) > $this->scan_limit) {
            $this->terminate("Payload Size Exhaustion", 'BLOCK', 'limit_exhaustion');
        }
        $this->consume_budget(strlen($raw_body));

        if ($is_json) {
            $json_data = json_decode($raw_body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                $this->inspect_superglobals($json_data, 'JSON_BODY');
            } else {
                $normalized_body = $this->normalize_payload($raw_body);
                $this->scan_string_atomic($raw_body, 'RAW_MALFORMED_JSON');
                if ($normalized_body !== $raw_body) {
                    $this->scan_string_atomic($normalized_body, 'VAL_MALFORMED_JSON');
                }
                $this->scan_string_heuristic($raw_body, 'RAW_MALFORMED_JSON');
                if ($normalized_body !== $raw_body) {
                    $this->scan_string_heuristic($normalized_body, 'VAL_MALFORMED_JSON');
                }
            }
        } else {
            $normalized_body = $this->normalize_payload($raw_body);
            $this->scan_string_atomic($raw_body, 'RAW_BODY_STREAM_RAW');
            if ($normalized_body !== $raw_body) {
                $this->scan_string_atomic($normalized_body, 'RAW_BODY_STREAM_VAL');
            }
            $this->scan_string_heuristic($raw_body, 'RAW_BODY_STREAM_RAW');
            if ($normalized_body !== $raw_body) {
                $this->scan_string_heuristic($normalized_body, 'RAW_BODY_STREAM_VAL');
            }
        }
    }

    private static function is_local_or_private_ip(string $ip): bool {
        $ip = trim($ip);
        if (strcasecmp($ip, 'localhost') === 0) {
            return true;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function inspect_headers(): void {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $request_method = $_SERVER['REQUEST_METHOD'] ?? 'CLI_EXECUTION';

        if ($request_method === 'POST') {
            $ref = $_SERVER['HTTP_REFERER'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            
            $is_allowed = (
                (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) || 
                str_contains($uri, '/wp-json/') ||
                str_contains($uri, 'wp-cron.php') ||
                str_contains($uri, '/wc-api/') ||
                str_contains($uri, 'admin-ajax.php')
            );
            
            if ($ua === '' && $ref === '' && !$is_allowed) {
                $this->terminate("Ghost POST (No UA/Ref)", 'BLOCK', 'bot');
            }
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $fwd_host = (string)$_SERVER['HTTP_X_FORWARDED_HOST'];
            $host = (string)($_SERVER['HTTP_HOST'] ?? '');
            if ($fwd_host !== '' && $host !== '' && $fwd_host !== $host) {
                $this->terminate("Protocol Smuggling (Host Override) detected.", 'BLOCK', 'protocol_smuggling');
            }
        }

        $all_patterns = self::get_combined_signatures();

        if ($ua !== '') {
            $normalized_ua = $this->normalize_payload($ua);
            foreach ($all_patterns as $type => $regex) {
                if (@preg_match($regex, $normalized_ua) === 1) {
                    $this->terminate('Bad User-Agent', 'BLOCK', 'bot');
                }
            }
        }

        $headers = $this->get_request_headers();
        if (count($headers) > 100) {
            $this->terminate('Header count boundary exceeded', 'BLOCK', 'header_exhaustion');
        }
        $header_bytes = 0;
        foreach ($headers as $header_name => $header_value) {
            $header_bytes += strlen((string)$header_name) + strlen((string)$header_value);
        }
        if ($header_bytes > self::MAX_HEADER_BYTES) {
            $this->terminate('Header size boundary exceeded', 'BLOCK', 'header_exhaustion');
        }
        $this->consume_budget($header_bytes);
        
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $headers['Raw-Cookie'] = $_SERVER['HTTP_COOKIE'];
        }

        foreach ($headers as $name => $value) {
            $name_lower = strtolower($name);
            
            if (in_array($name_lower, [
                'cookie', 
                'raw-cookie', 
                'x-vgt-signature', 
                'x-vgt-timestamp', 
                'x-vis-antibot-pow'
            ], true)) {
                continue; 
            }
            
            $val_str = (string)$value;

            // [ VGT OMEGA DETECT: PROXY HEADER SPOOFING SHIELD ]
            if (in_array($name_lower, ['x-forwarded-for', 'x-real-ip', 'true-client-ip', 'client-ip', 'x-forwarded-host'], true)) {
                if (self::is_local_or_private_ip($val_str)) {
                    $this->terminate("Proxy Spoofing detected in header: $name", 'BLOCK', 'proxy_spoofing');
                }
            }

            $normalized_str = $this->normalize_payload($val_str);

            if (str_starts_with($name_lower, 'sec-ch-ua')) {
                if (preg_match('/^["a-zA-Z0-9\s\-\(\)\.;=\?]+$/', $normalized_str)) {
                    continue; 
                }
            }

            $this->scan_string_atomic($val_str, "HEADER_RAW_$name");
            if ($normalized_str !== $val_str) {
                $this->scan_string_atomic($normalized_str, "HEADER_VAL_$name");
            }

            $this->scan_string_heuristic($val_str, "HEADER_RAW_$name");
            if ($normalized_str !== $val_str) {
                $this->scan_string_heuristic($normalized_str, "HEADER_VAL_$name");
            }
            
            foreach ($all_patterns as $type => $regex) {
                 if (@preg_match($regex, $normalized_str) === 1) {
                    $this->terminate("Header Injection: $name", 'BLOCK', $type);
                }
            }
        }
    }

    private function inspect_uri(?string $candidate_uri = null): void {
        $full_uri = $candidate_uri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        
        $fast_probe_regex = '/(?i)(?>\.(?>env|git|htaccess|php_bak|old|bak|sql|tar\.gz|tgz|zip|rar|7z|gz|bz2|remote-sync|ds_store|idea|vscode))|config\.php|wp-config\.php|\.aws\/credentials|vendor\/phpunit|phpunit\/src|\/_profiler\//S';
        if (@preg_match($fast_probe_regex, $full_uri) === 1) {
            $this->terminate("Static URI Probe Intercepted (Fast Kernel Kill)", 'BLOCK', 'probes');
        }

        $normalized_uri = $this->normalize_payload($full_uri);
        if ($normalized_uri !== $full_uri) {
            if (@preg_match($fast_probe_regex, $normalized_uri) === 1) {
                $this->terminate("Static URI Probe Intercepted (Fast Kernel Kill)", 'BLOCK', 'probes');
            }
        }

        $this->scan_string_atomic($full_uri, 'URI_RAW');
        if ($normalized_uri !== $full_uri) {
            $this->scan_string_atomic($normalized_uri, 'URI_NORMALIZED');
        }

        $this->scan_string_heuristic($full_uri, 'URI_RAW');
        if ($normalized_uri !== $full_uri) {
            $this->scan_string_heuristic($normalized_uri, 'URI_NORMALIZED');
        }
        
        $all_patterns = self::get_combined_signatures();
        foreach ($all_patterns as $type => $regex) {
            if (@preg_match($regex, $normalized_uri) === 1) {
                 $this->terminate("URI Threat: [$type]", 'BLOCK', $type);
            }
        }
    }

    private function get_request_headers(): array {
        if (function_exists('getallheaders')) {
            $native_headers = getallheaders();
            if (is_array($native_headers)) return $native_headers;
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $header_key = str_replace('_', '-', strtolower(substr($name, 5)));
                $header_key = ucwords($header_key, '-');
                $headers[$header_key] = $value;
            } 
            elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                 $header_key = str_replace('_', '-', strtolower($name));
                 $header_key = ucwords($header_key, '-');
                 $headers[$header_key] = $value;
            }
        }
        return $headers;
    }

    private function get_secure_ip(): string {
        if (class_exists('VIS_Security')) {
            return \VIS_Security::client_ip();
        }

        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        if (defined('VIS_TRUST_PROXY') && VIS_TRUST_PROXY === true) {
            $trusted_proxies = defined('VIS_TRUSTED_PROXY_IPS') ? VIS_TRUSTED_PROXY_IPS : [];
            
            if (!empty($trusted_proxies) && in_array($remote_addr, $trusted_proxies, true)) {
                
                $edge_headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP'];
                foreach ($edge_headers as $header) {
                    if (!empty($_SERVER[$header])) {
                        $edge_ip = trim($_SERVER[$header]);
                        if (filter_var($edge_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            return $edge_ip;
                        }
                    }
                }

                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
                    $ips = array_reverse($ips); 
                    
                    foreach ($ips as $ip) {
                        if (in_array($ip, $trusted_proxies, true)) {
                            continue;
                        }
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            return $ip; 
                        }
                    }
                }
            }
        }
        return $remote_addr;
    }

    private function terminate(string $reason, string $action, string $vector = 'unknown'): void {
        if ($this->assessment_mode) {
            $this->assessment_verdict = ['verdict' => 'BLOCK', 'vector' => $vector];
            throw new \RuntimeException('VGT assessment block.');
        }

        $ip = defined('VIS_RESOLVED_IP') ? VIS_RESOLVED_IP : $this->get_secure_ip();
        $incident_id = 'vis_' . bin2hex(random_bytes(8));
        $this->log_incident($ip, $reason, $action, $vector);
        
        if (class_exists('VIS_Trinity_Grid')) {
            try {
                \VIS_Trinity_Grid::onAegisStrike($ip, $vector);
            } catch (\Throwable $e) {
                error_log('[VIS TRINITY] AEGIS interlock failed closed to local rejection.');
            }
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        
        if (!headers_sent()) {
            http_response_code(403);
            header('X-Aegis-Block: REJECTED');
            header('Content-Type: application/json; charset=utf-8'); 
            header('Connection: close');
            header('Cache-Control: no-store, max-age=0');
        } else {
            $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
            header("$protocol 403 Forbidden", true, 403);
            header('X-Aegis-Block: REJECTED');
        }
        
        die(json_encode([
            'status' => 'error',
            'code' => 403,
            'message' => 'VISIONGAIATECHNOLOGY AEGIS PROTOCOL: Access Denied',
            'incident_id' => $incident_id
        ], JSON_THROW_ON_ERROR));
    }

    private function fail_closed_runtime(): never {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('Retry-After: 60');
            header('Connection: close');
        }

        die(json_encode([
            'status' => 'error',
            'code' => 503,
            'message' => 'Security inspection temporarily unavailable.',
            'incident_id' => 'vis_' . bin2hex(random_bytes(8)),
        ], JSON_THROW_ON_ERROR));
    }

    private function log_incident(string $ip, string $reason, string $action, string $vector): void {
        $wpdb_handle = $this->get_db_handle();
        if (!$wpdb_handle) {
            return;
        }
        try {
            $table_logs = $wpdb_handle->prefix . 'vis_omega_logs'; 
            
            $critical_vectors = [
                'rce_eval', 'rce_callbacks', 'rce_misc', 'rce_backticks', 'rce_subshells',
                'rce_jndi_env', 'rce_serialized', 'rce_cmd_extensions', 'rce_cmd_paths', 'rce_cmd_standalone',
                'lfi_rfi_os', 'sqli_union', 'sqli_select', 'sqli_functions', 
                'sqli_structure', 'sqli_logic', 'sqli_hard_kill', 'xxe_ssrf', 'rce_source_hijack',
                'framework', 'gql_recon', 'rce_upload', 'rce_upload_polyglot',
                'AI_DETECTED_THREAT', 'GORGON_REPUTATION_KILL', 'redos', 'pcre_error',
                'ua_malicious', 'limit_exhaustion', 'protocol_smuggling',
                'xss_script_tags', 'xss_event_handlers', 'xss_uri_schemes', 'xss_tags_with_events'
            ];
            
            $severity = in_array($vector, $critical_vectors, true) || str_starts_with($vector, 'ZERO_DAY') ? 10 : 5;
            $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

            if (class_exists('VIS_Event_Bus')) {
                VIS_Event_Bus::emit('AEGIS', $action, $reason, [
                    'vector' => $vector,
                    'method' => $method,
                ], $severity);
            }
            
            if (!class_exists('VIS_Event_Bus')) {
                $wpdb_handle->insert($table_logs, [
                    'module'    => 'AEGIS',
                    'type'      => $action,
                    'message'   => $reason . " | Method: " . $method,
                    'ip'        => $ip,
                    'severity'  => $severity
                ], ['%s', '%s', '%s', '%s', '%d']);
            }
            
            if (($severity === 10 || $vector === 'probes') && $this->mode !== 'learning') {
                $uri_log = esc_url_raw($_SERVER['REQUEST_URI'] ?? '');
                if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                    $uri_log .= ' [BODY-INJECTION INTERCEPTED]';
                }

                $ban_reason = "AEGIS: Auto-Ban ($vector) [$method]";
                $cerberus_class = '\VIS_Cerberus';
                $banned_via_cerberus = false;

                if (class_exists($cerberus_class)) {
                    try {
                        if (method_exists($cerberus_class, 'instance')) {
                            $cerberus = $cerberus_class::instance();
                            if (method_exists($cerberus, 'ban_ip')) {
                                $cerberus->ban_ip($ip, $ban_reason);
                                $banned_via_cerberus = true;
                            }
                        }
                    } catch (\Throwable $e) {
                        // fallback to raw query below
                    }
                }

                if (!$banned_via_cerberus) {
                    $table_bans = $wpdb_handle->prefix . (defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_apex_bans');
                    $query = $wpdb_handle->prepare(
                        "INSERT IGNORE INTO $table_bans (ip, reason, banned_at, request_uri) VALUES (%s, %s, %s, %s)",
                        $ip, $ban_reason, current_time('mysql'), $uri_log
                    );
                    $wpdb_handle->query($query);
                }
            }
        } catch (\Throwable $e) {
            // Fail silent in logging to prevent fatal loops on DB crash
        }
    }
    
    private function get_db_handle(): ?object {
        global $wpdb;
        return (isset($wpdb) && $wpdb instanceof \wpdb) ? $wpdb : null;
    }

    /**
     * STRUCTURAL SHANNON-ENTROPY ENGINE
     * Calculates mathematical information entropy H(X) = -sum(P(x) * log2(P(x))) in pure native PHP.
     */
    public static function calculate_shannon_entropy(string $payload): float {
        $len = strlen($payload);
        if ($len === 0) return 0.0;

        $byte_counts = count_chars($payload, 1);
        $entropy = 0.0;
        $log2 = log(2);

        foreach ($byte_counts as $count) {
            $p = $count / $len;
            $entropy -= $p * (log($p) / $log2);
        }

        return round($entropy, 4);
    }
}
