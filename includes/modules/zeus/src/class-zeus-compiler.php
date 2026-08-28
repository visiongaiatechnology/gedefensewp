<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit('VGT_ACCESS_DENIED');

class VIS_Zeus_Compiler {
    
    private string $vault_dir;
    private string $waf_file;
    private array $config;
    private string $swarm_ip;

    public function __construct( string $vault_dir, string $waf_file, array $config, string $swarm_ip ) {
        $this->vault_dir = $vault_dir;
        $this->waf_file  = $waf_file;
        $this->config    = $config;
        $this->swarm_ip  = $swarm_ip;
    }

    public function deploy_waf(): void {
        $this->sync_dynamic_whitelist();
        $this->compile_waf_payload();
    }

    private function sync_dynamic_whitelist(): void {
        $whitelist_ips = get_option( 'vgt_zeus_whitelist_ips', null );
        
        if ( $whitelist_ips === null ) {
            $whitelist_ips = [ $this->swarm_ip ];
            update_option( 'vgt_zeus_whitelist_ips', $whitelist_ips );
        }
        
        $whitelist_file = $this->vault_dir . 'whitelist.json';
        file_put_contents( $whitelist_file, wp_json_encode( $whitelist_ips, JSON_THROW_ON_ERROR ), LOCK_EX );
        @chmod( $whitelist_file, 0600 );
    }

    private function compile_waf_payload(): void {
        $vault_cache = $this->vault_dir . 'cache/';
        if (!is_dir($vault_cache)) @mkdir($vault_cache, 0700, true);
        @chmod($vault_cache, 0700);
        
        
        $whitelist_ips = get_option( 'vgt_zeus_whitelist_ips', [] );
        $wl_map = array_fill_keys( $whitelist_ips, true );
        $wl_export = var_export( $wl_map, true );

        $cf_v4 = ['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13','104.24.0.0/14','172.64.0.0/13','131.0.72.0/22'];
        $cf_v6 = ['2400:cb00::/32','2606:4700::/32','2803:f800::/32','2405:b500::/32','2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32'];
        
        $compiled_v4 = [];
        foreach ($cf_v4 as $cidr) {
            list($net, $mask) = explode('/', $cidr);
            $compiled_v4[] = '[' . ip2long($net) . ', ' . ~((1 << (32 - (int)$mask)) - 1) . ']';
        }
        $cf_array_v4 = '[' . implode(',', $compiled_v4) . ']';

        $compiled_v6 = [];
        foreach ($cf_v6 as $cidr) {
            list($net, $mask) = explode('/', $cidr);
            $bin_net = bin2hex((string)inet_pton($net));
            $bin_mask = str_repeat("\xff", (int)($mask / 8));
            if ($mask % 8 !== 0) $bin_mask .= chr(256 - (1 << (8 - ($mask % 8))));
            $bin_mask = str_pad($bin_mask, 16, "\x00");
            $bin_mask_hex = bin2hex($bin_mask);
            $compiled_v6[] = "[hex2bin('{$bin_net}'), hex2bin('{$bin_mask_hex}')]";
        }
        $cf_array_v6 = '[' . implode(',', $compiled_v6) . ']';

        // DIAMANT FEATURE: Compile-Time APCu Check. Erspart 800+ function_exists() Checks pro Sekunde!
        $use_apcu_compiled = function_exists('apcu_fetch') ? 'true' : 'false';

        // Klartext-Payload: Hoch-optimiert, lesbar, IIFE-Architektur.
        $php = <<<WAF_PAYLOAD
<?php
/** * VGT OMEGA PRE-BOOT WAF KERNEL 
 * DEPLOYED: {gmdate('Y-m-d H:i:s')} Z
 * STATUS: DIAMANT PREDATOR (MICRO-OPTIMIZED IIFE & L0 ROUTING)
 */
if (defined('VGT_ZEUS_WAF_ACTIVE')) return;
define('VGT_ZEUS_WAF_ACTIVE', true);

(static function() {
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

    \$wl = {$wl_export};
    \$wl['127.0.0.1'] = true;
    if (isset(\$wl[\$ip])) return;
    \$ip_subnet = (strpos(\$ip, ':') !== false) ? implode(':', array_slice(explode(':', \$ip), 0, 4)) . '::' : preg_replace('/\.\d+$/', '.0', \$ip);
    if (isset(\$wl[\$ip_subnet])) return;

    \$ip_key = 'vis_prom_' . md5(\$ip);
    \$infra_key = 'vis_prom_infra_' . md5(\$ip_subnet);
    \$req_key = 'vis_req_' . md5(\$ip);
    \$now = microtime(true);
    
    // ========================================================================
    // [ DIAMANT CIRCUIT BREAKER ] - ZERO-WRITE FAST PATH & RAW KILL
    // ========================================================================
    if ({$use_apcu_compiled}) {
        \$fast_state = apcu_fetch(\$ip_key);
        if (is_array(\$fast_state) && \$fast_state['score'] >= 100) {
            if (\$fast_state['score'] >= 9000 || (\$fast_state['score'] - ((\$now - \$fast_state['last_request_time']) * 0.2)) >= 100) {
                http_response_code(403);
                die('VGT_OMEGA_403');
            }
        }
    } else {
        \$ip_score_file = '{$vault_cache}' . \$ip_key . '_score.php';
        if (file_exists(\$ip_score_file)) {
            \$fs = (int)@filesize(\$ip_score_file);
            \$ip_score = max(0.0, (float)((\$fs > 0 ? \$fs : 12) - 12));
            if (\$ip_score >= 9000) {
                http_response_code(403);
                die('VGT_OMEGA_403');
            }
            if (\$ip_score >= 100) {
                \$last_time = (float)@filemtime(\$ip_score_file);
                if (\$ip_score - (max(0.0, \$now - (\$last_time > 0.0 ? \$last_time : \$now)) * 0.2) >= 100) {
                    http_response_code(403);
                    die('VGT_OMEGA_403');
                }
            }
        }
    }
    // ========================================================================

    \$trigger_kill = static function() {
        http_response_code(403);
        die('VGT_OMEGA_403');
    };

    // Static Asset Bypass
    \$uri = \$_SERVER['REQUEST_URI'] ?? '/';
    if ((\$pos = strpos(\$uri, '?')) !== false) \$uri = substr(\$uri, 0, \$pos);
    \$ext = strtolower(substr((string)strrchr(\$uri, '.'), 1));
    \$static_exts = ['ico'=>1,'map'=>1,'woff'=>1,'woff2'=>1,'ttf'=>1,'png'=>1,'jpg'=>1,'jpeg'=>1,'svg'=>1,'css'=>1,'js'=>1,'xml'=>1,'txt'=>1,'webp'=>1,'gif'=>1];
    if (isset(\$static_exts[\$ext])) return;

    \$dynamic_penalty = 0.0;
    
    if ({$use_apcu_compiled}) {
        \$ip_state = apcu_fetch(\$ip_key);
        if (!is_array(\$ip_state)) \$ip_state = ['score' => 0.0, 'last_request_time' => \$now, 'request_count' => 0];
        
        \$time_delta = max(0.0, \$now - \$ip_state['last_request_time']);
        if (\$time_delta > 0.0) {
            if (\$time_delta < 0.2) \$dynamic_penalty += 20.0;
            elseif (\$time_delta < 1.0) \$dynamic_penalty += 10.0;
        }
        
        \$decay = \$time_delta * 0.2;
        \$ip_state['score'] = max(0.0, \$ip_state['score'] - \$decay);
        \$ip_state['last_request_time'] = \$now;
        \$ip_state['request_count']++;
        
        apcu_store(\$ip_key, \$ip_state, 86400);
    } else {
        \$req_file = '{$vault_cache}' . \$req_key . '.php';
        if (file_exists(\$req_file)) {
            \$last_req_time = (float)@file_get_contents(\$req_file);
            \$time_delta = max(0.0, \$now - \$last_req_time);
            if (\$time_delta > 0.0) {
                if (\$time_delta < 0.2) \$dynamic_penalty += 20.0;
                elseif (\$time_delta < 1.0) \$dynamic_penalty += 10.0;
            }
        }
        @file_put_contents(\$req_file, (string)\$now, LOCK_EX);
    }

    \$apply_penalty = static function(\$points) use (\$ip_key, \$infra_key, \$trigger_kill, \$now, \$ip) {
        \$points = (float)\$points;
        \$php_die_tag = chr(60) . '?php die;' . chr(63) . chr(62);
        
        if ({$use_apcu_compiled}) {
            \$ip_state = apcu_fetch(\$ip_key);
            if (!is_array(\$ip_state)) \$ip_state = ['score' => 0.0, 'last_request_time' => \$now, 'request_count' => 1];
            \$ip_state['score'] += \$points;
            if (\$ip_state['score'] > 9999) \$ip_state['score'] = 9999;
            apcu_store(\$ip_key, \$ip_state, 86400);
            if (\$ip_state['score'] >= 100) \$trigger_kill();
        } else {
            \$ip_score_file = '{$vault_cache}' . \$ip_key . '_score.php';
            \$ip_score = 0.0;
            if (file_exists(\$ip_score_file)) {
                \$last_time = (float)@filemtime(\$ip_score_file);
                \$last_time = \$last_time > 0.0 ? \$last_time : \$now;
                \$fs = (int)@filesize(\$ip_score_file);
                \$ip_score = max(0.0, (float)((\$fs > 0 ? \$fs : 12) - 12));
                \$decay = max(0.0, \$now - \$last_time) * 0.2;
                \$ip_score = max(0.0, \$ip_score - \$decay);
            }
            \$ip_score += \$points;
            if (\$ip_score > 9999) \$ip_score = 9999;
            @file_put_contents(\$ip_score_file, \$php_die_tag . str_repeat('.', (int)\$ip_score), LOCK_EX);
            if (\$ip_score >= 100) \$trigger_kill();
        }
        
        if ({$use_apcu_compiled}) {
            \$infra_state = apcu_fetch(\$infra_key);
            if (!is_array(\$infra_state)) \$infra_state = ['score' => 0.0, 'last_time' => \$now, 'last_ip' => \$ip, 'cooldown_until' => 0.0];
            
            \$decay = max(0.0, \$now - \$infra_state['last_time']) * 0.2;
            \$infra_state['score'] = max(0.0, \$infra_state['score'] - \$decay) + \$points;
            if (\$infra_state['score'] > 9999) \$infra_state['score'] = 9999;
            \$infra_state['last_time'] = \$now;
            \$infra_state['last_ip'] = \$ip;
            apcu_store(\$infra_key, \$infra_state, 86400);
            
            if (\$infra_state['score'] >= 150) \$trigger_kill();
        } else {
            \$infra_file = '{$vault_cache}' . \$infra_key . '.php';
            \$infra_score = 0.0;
            if (file_exists(\$infra_file)) {
                \$last_time = (float)@filemtime(\$infra_file);
                \$last_time = \$last_time > 0.0 ? \$last_time : \$now;
                \$fs = (int)@filesize(\$infra_file);
                \$infra_score = max(0.0, (float)((\$fs > 0 ? \$fs : 12) - 12));
                \$decay = max(0.0, \$now - \$last_time) * 0.2;
                \$infra_score = max(0.0, \$infra_score - \$decay);
            }
            \$infra_score += \$points;
            if (\$infra_score > 9999) \$infra_score = 9999;
            @file_put_contents(\$infra_file, \$php_die_tag . str_repeat('.', (int)\$infra_score), LOCK_EX);
            if (\$infra_score >= 150) \$trigger_kill();
        }
    };

    if (\$dynamic_penalty > 0) \$apply_penalty(\$dynamic_penalty);

    \$content_length = (int)(\$_SERVER['CONTENT_LENGTH'] ?? 0);
    \$is_multipart = stripos(\$_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    
    if (\$content_length > 65536 && !\$is_multipart) \$apply_penalty(9999);
    elseif (\$content_length > 134217728) \$apply_penalty(9999);

    \$payload_buffer = '';
    if (!empty(\$_GET)) \$payload_buffer .= json_encode(\$_GET);
    if (!empty(\$_POST)) \$payload_buffer .= json_encode(\$_POST);
    if (!empty(\$_COOKIE)) \$payload_buffer .= json_encode(\$_COOKIE);
    
    if (\$content_length > 0 && \$content_length <= 65536 && empty(\$_POST) && !\$is_multipart) {
        \$payload_buffer .= @file_get_contents('php://input', false, null, 0, 65536);
    }

    if (\$payload_buffer !== '') {
        \$decoded = urldecode(\$payload_buffer);
        \$normalized = strtolower(str_replace(["\\0", "\\r", "\\n", "\\t"], '', \$decoded));

        \$l1_patterns = [
            '/(?:union[\\s\\/\\*]+select|information_schema|waitfor[\\s\\/\\*]+delay)/i',
            '/(?:scriptjavascript|onerror\\s*=|onload\\s*=|eval\\s*\\((?:base64|str_rot13))/i',
            '/(?:etc\\/passwd|php:\\/\\/input|expect:\\/\\/)/i',
            '/(?:system\\s*\\(|shell_exec\\s*\\(|passthru\\s*\\()/i'
        ];

        foreach (\$l1_patterns as \$regex) {
            if (preg_match(\$regex, \$normalized)) \$apply_penalty(9999);
        }
    }

    if (stripos(\$_SERVER['REQUEST_URI'] ?? '', 'xmlrpc.php') !== false) \$apply_penalty(50);
    \$ua = \$_SERVER['HTTP_USER_AGENT'] ?? '';
    if (stripos(\$ua, 'Googlebot') !== false && !str_starts_with(\$ip, '66.249.') && !str_starts_with(\$ip, '64.233.')) {
        \$apply_penalty(30);
    }

})();
WAF_PAYLOAD;

        $temp_file = wp_normalize_path( $this->vault_dir . 'zeus-waf-' . bin2hex(random_bytes(16)) . '.tmp' );
        file_put_contents( $temp_file, $php, LOCK_EX );
        chmod( $temp_file, 0600 );
        rename( $temp_file, $this->waf_file );
        chmod( $this->waf_file, 0600 );
    }
}
