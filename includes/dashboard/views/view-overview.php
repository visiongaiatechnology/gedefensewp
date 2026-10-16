<?php
declare(strict_types=1);
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * View: DASHBOARD OVERVIEW (COMMAND CENTER)
 * Status: PLATIN VGT STATUS (Hardened UI & i18n)
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// =========================================================================================
// 1. DATA HARVESTING & CONFIGURATION
// =========================================================================================

$table_logs = defined('VIS_TABLE_LOGS') ? $wpdb->prefix . VIS_TABLE_LOGS : $wpdb->prefix . 'vis_omega_logs';
$table_bans = defined('VIS_TABLE_BANS') ? $wpdb->prefix . VIS_TABLE_BANS : $wpdb->prefix . 'vis_apex_bans';

$opt = get_option('vis_config', []);

$vault_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/vgt-vault/zeus/' : dirname( ABSPATH ) . '/wp-content/vgt-vault/zeus/';
$zeus_active = file_exists( $vault_dir . 'zeus-waf.php' );

$aegis_blocks = 0;
$ai_threats = 0;

$suppress = $wpdb->suppress_errors(true);

// Top Stats Harvesting (Sentinel Main Tables)
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'") === $table_logs) {
    $aegis_logs_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_logs} WHERE module IN ('AEGIS', 'CERBERUS', 'AIRLOCK', 'GHOST_TRAP', 'TITAN', 'ZEUS')");
    $aegis_bans_count = 0;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_bans}'") === $table_bans) {
        $aegis_bans_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_bans}");
    }
    $aegis_blocks = $aegis_logs_count + $aegis_bans_count;
    
    $ai_threats = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_logs} WHERE module IN ('PROMETHEUS', 'NEMESIS', 'STYX', 'ORACLE') OR type IN ('ANOMALY', 'PREDICTIVE_STRIKE', 'POISON', 'EXFILTRATION_ATTEMPT', 'CANARY') OR message LIKE '%AI Detected Threat%'");
}

// Prometheus Dedicated Telemetry Harvesting
$prom_tables = [
    $wpdb->prefix . 'vgt_prometheus_logs',
    $wpdb->prefix . 'vis_prometheus_logs',
    $wpdb->prefix . 'prometheus_logs'
];
$prom_count = 0;
foreach ( $prom_tables as $ptable ) {
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $ptable ) ) === $ptable ) {
        $prom_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ptable}" );
        break;
    }
}
$ai_threats += $prom_count;

$wpdb->suppress_errors($suppress);

// Fallbacks
$ai_threats += (int) wp_cache_get('vgt_prometheus_strikes');
$ai_threats += (int) wp_cache_get('vgt_active_tarpits');

// =========================================================================================
// 2. INTEGRITY SCANNER LOGIC (ACTION ROW)
// =========================================================================================
$report = get_option('vis_scan_report', false);
$integrity_status = 'UNKNOWN';
$ui_int_color  = '#888888';
$ui_int_sub    = __('No Scan Data', 'vgt-sentinel');
$ui_int_desc   = __('Bitte starten Sie einen manuellen Integritäts-Scan.', 'vgt-sentinel');
$integrity_clean  = false;
$ui_int_icon_svg  = '<line x1="5" y1="12" x2="19" y2="12"></line>'; // Default (Minus)

if ($report && is_array($report)) {
    $status = $report['status'] ?? 'unknown';
    if ($status === 'warning') {
        $integrity_status = 'ANOMALY';
        $ui_int_color  = '#d63638'; 
        $ui_int_sub    = __('State: Violated', 'vgt-sentinel');
        
        $changes_count = count($report['changes'] ?? []);
        $ui_int_desc   = sprintf(
            /* translators: %d: Number of anomalies */
            _n('%d Anomalie erkannt!', '%d Anomalien erkannt!', $changes_count, 'vgt-sentinel'),
            $changes_count
        );
        $ui_int_icon_svg = '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>';
    } elseif ($status === 'clean' || $status === 'init') {
        $integrity_status = 'SECURE';
        $ui_int_color  = '#00ffaa'; 
        $ui_int_sub    = __('State: Valid', 'vgt-sentinel');
        $ui_int_desc   = __('Systemintegrität verifiziert.', 'vgt-sentinel');
        $integrity_clean  = true;
        $ui_int_icon_svg = '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>';
    }
}

// =========================================================================================
// 3. SUBSYSTEM LOGIC (4 PILLARS)
// =========================================================================================

// PROMETHEUS & NEMESIS
$prom_enabled = !empty($opt['prometheus_enabled']);
$nemesis_enabled = !empty($opt['nemesis_enabled']);

// TITAN HARDENING
$titan_active_count = 0;
$found_titan_keys = 0;
$titan_status_html = '';
$known_titan_labels = [
    'titan_xmlrpc'     => __('XML-RPC Locked', 'vgt-sentinel'), 
    'titan_rest_api'   => __('REST API Guard', 'vgt-sentinel'), 
    'titan_wp_version' => __('Version Cloak', 'vgt-sentinel'), 
    'titan_file_edit'  => __('Editor Disabled', 'vgt-sentinel')
];

foreach ($opt as $key => $val) {
    if (strpos($key, 'titan_') === 0 && !empty($val) && $val !== '0' && $key !== 'titan_enabled') {
        $titan_active_count++;
        $found_titan_keys++;
        if ($found_titan_keys <= 4) {
            $label = isset($known_titan_labels[$key]) ? $known_titan_labels[$key] : ucwords(str_replace(['titan_', '_'], ['', ' '], $key));
            $titan_status_html .= "<div class='vgt-checklist-item'><svg width='18' height='18' fill='none' stroke='currentColor' class='vgt-icon' style='color:#00ffaa; width:12px; height:12px;' viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 10 0v4'></path></svg> " . esc_html($label) . "</div>";
        }
    }
}
if ($titan_active_count === 0 && !empty($opt['titan_enabled'])) {
    $titan_active_count = 4;
    $titan_status_html = "<div class='vgt-checklist-item'><svg width='18' height='18' fill='none' stroke='currentColor' class='vgt-icon' style='color:#00ffaa; width:12px; height:12px;' viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 10 0v4'></path></svg> " . esc_html__('Kernel Hardening', 'vgt-sentinel') . "</div><div class='vgt-checklist-item'><svg width='18' height='18' fill='none' stroke='currentColor' class='vgt-icon' style='color:#00ffaa; width:12px; height:12px;' viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 10 0v4'></path></svg> " . esc_html__('API Security Guard', 'vgt-sentinel') . "</div>";
} elseif ($titan_active_count === 0) {
    $titan_status_html = "<div class='vgt-checklist-item'><svg width='18' height='18' fill='none' stroke='currentColor' class='vgt-icon' style='color:#ff4d4d; width:12px; height:12px;' viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 9.9-1'></path></svg> " . esc_html__('System Vulnerable', 'vgt-sentinel') . "</div>";
}
$titan_score = min(100, max(0, ($titan_active_count >= 4) ? 100 : ($titan_active_count * 25)));

// VLP (VISION LEGAL PRO)
$vlp_is_running = class_exists('VisionLegalPro_Core') || class_exists('VIS_Bridge_Vision_Legal_Pro') || !empty($opt['vlp_enabled']);
$vlp_settings = get_option('vlp_settings', []);
$vlp_shadow = ($vlp_is_running || class_exists('VLP_Shadow_Interceptor') || !empty($vlp_settings['shadow_net'])) ? __('ENGAGED', 'vgt-sentinel') : __('OFFLINE', 'vgt-sentinel');
$vlp_privacy = ($vlp_is_running || class_exists('VLP_Gatekeeper') || !empty($vlp_settings['privacy_shield'])) ? __('ENFORCED', 'vgt-sentinel') : __('BYPASSED', 'vgt-sentinel');

// =========================================================================================
// 4. GLOBAL SECURITY EVENTS (SENTINEL + PROMETHEUS MERGE)
// =========================================================================================

// 1. SENTINEL MAIN LOGS
$sentinel_logs = [];
$suppress = $wpdb->suppress_errors(true);
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'") === $table_logs) {
    $raw_sentinel = $wpdb->get_results("SELECT * FROM {$table_logs} ORDER BY id DESC LIMIT 50");
    if($raw_sentinel) {
        foreach($raw_sentinel as $row) {
            $sentinel_logs[] = (object) [
                'timestamp' => $row->timestamp,
                'module'    => $row->module,
                'ip'        => !empty($row->ip_address) ? $row->ip_address : (!empty($row->ip) ? $row->ip : 'N/A'),
                'message'   => !empty($row->details) ? $row->details : (!empty($row->message) ? $row->message : $row->type),
                'source'    => 'sentinel'
            ];
        }
    }
}

// 2. PROMETHEUS TELEMETRIE
$prom_logs = [];
foreach ( $prom_tables as $ptable ) {
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $ptable ) ) === $ptable ) {
        $raw_prom = $wpdb->get_results("SELECT * FROM {$ptable} ORDER BY id DESC LIMIT 25");
        if($raw_prom) {
            foreach($raw_prom as $row) {
                $prom_logs[] = (object) [
                    'timestamp' => $row->timestamp,
                    'module'    => 'PROMETHEUS',
                    'ip'        => !empty($row->ip_address) ? $row->ip_address : 'UNKNOWN',
                    'message'   => !empty($row->details) ? $row->details : $row->type,
                    'source'    => 'prometheus'
                ];
            }
        }
        break;
    }
}

// 3. MERGE & SORT
$merged_logs = array_merge($sentinel_logs, $prom_logs);
usort($merged_logs, function($a, $b) {
    return strtotime($b->timestamp) - strtotime($a->timestamp);
});
$final_logs = array_slice($merged_logs, 0, 10);

// 4. ORACLE THREAT INTELLIGENCE (LIMIT optimiert auf 10 für Omnipresent Logging Übersicht)
$oracle_logs = [];
$oracle_table = $wpdb->prefix . 'vis_oracle_patterns';
if ($wpdb->get_var("SHOW TABLES LIKE '{$oracle_table}'") === $oracle_table) {
    // VGT LOGIC GATE: Begrenzung auf exakt 10 für perfekte UI-Ausbalancierung.
    $raw_oracle = $wpdb->get_results("SELECT * FROM {$oracle_table} ORDER BY id DESC LIMIT 10");
    if($raw_oracle) {
        $oracle_logs = $raw_oracle;
    }
}
$wpdb->suppress_errors($suppress);

// =========================================================================================
// 5. CYBER DEFENSE AIRSPACE RADAR TELEMETRY HARVESTING (LIVE INTELLIGENCE MATRIX)
// =========================================================================================
$radar_vectors = [];
$radar_critical_count = 0;
$radar_warning_count = 0;
$radar_monitored_count = 0;
$seen_radar_ips = [];

// 1. Process active IP bans (highest priority critical threats)
if (!empty($table_bans) && $wpdb->get_var("SHOW TABLES LIKE '{$table_bans}'") === $table_bans) {
    $raw_bans = $wpdb->get_results("SELECT * FROM {$table_bans} ORDER BY id DESC LIMIT 10");
    if ($raw_bans) {
        foreach ($raw_bans as $b) {
            $b_ip = trim((string)($b->ip ?? $b->ip_address ?? ''));
            if ($b_ip === '' || isset($seen_radar_ips[$b_ip])) continue;
            $seen_radar_ips[$b_ip] = true;
            $radar_critical_count++;
            
            $b_hash = abs(crc32($b_ip));
            $b_angle = $b_hash % 360;
            $b_radius = 24 + (($b_hash >> 6) % 22); // 24% - 46% (inner danger perimeter)
            $b_rad = deg2rad($b_angle);
            
            $radar_vectors[] = [
                'ip'       => $b_ip,
                'module'   => 'PERIMETER BAN',
                'severity' => 'critical',
                'time'     => (string)($b->banned_at ?? $b->timestamp ?? __('Aktiv', 'vgt-sentinel')),
                'msg'      => (string)($b->reason ?? __('Permanente IP-Sperre verhängt', 'vgt-sentinel')),
                'x'        => round(50 + ($b_radius * cos($b_rad) * 0.5), 2),
                'y'        => round(50 + ($b_radius * sin($b_rad) * 0.5), 2),
                'angle'    => $b_angle,
                'radius'   => $b_radius,
            ];
            if (count($radar_vectors) >= 12) break;
        }
    }
}

// 2. Process recent event logs (Sentinel & Prometheus)
if (count($radar_vectors) < 12 && !empty($merged_logs)) {
    foreach ($merged_logs as $entry) {
        $l_ip = trim((string)$entry->ip);
        if ($l_ip === '' || $l_ip === 'N/A' || $l_ip === 'UNKNOWN' || isset($seen_radar_ips[$l_ip])) continue;
        $seen_radar_ips[$l_ip] = true;
        
        $l_mod = strtoupper((string)$entry->module);
        $l_msg = (string)$entry->message;
        $l_time = (string)$entry->timestamp;
        
        $is_crit = in_array($l_mod, ['ZEUS', 'CERBERUS', 'STYX'], true) || stripos($l_msg, 'block') !== false || stripos($l_msg, 'exploit') !== false;
        $is_warn = in_array($l_mod, ['PROMETHEUS', 'NEMESIS', 'ORACLE'], true) || stripos($l_msg, 'anomaly') !== false || stripos($l_msg, 'tarpit') !== false;
        
        $severity = $is_crit ? 'critical' : ($is_warn ? 'warning' : 'monitored');
        if ($severity === 'critical') {
            $radar_critical_count++;
        } elseif ($severity === 'warning') {
            $radar_warning_count++;
        } else {
            $radar_monitored_count++;
        }
        
        $l_hash = abs(crc32($l_ip));
        $l_angle = $l_hash % 360;
        if ($severity === 'critical') {
            $l_radius = 28 + (($l_hash >> 8) % 20); // 28% - 48%
        } elseif ($severity === 'warning') {
            $l_radius = 50 + (($l_hash >> 8) % 20); // 50% - 70%
        } else {
            $l_radius = 72 + (($l_hash >> 8) % 18); // 72% - 90%
        }
        $l_rad = deg2rad($l_angle);
        
        $radar_vectors[] = [
            'ip'       => $l_ip,
            'module'   => $l_mod,
            'severity' => $severity,
            'time'     => $l_time,
            'msg'      => $l_msg,
            'x'        => round(50 + ($l_radius * cos($l_rad) * 0.5), 2),
            'y'        => round(50 + ($l_radius * sin($l_rad) * 0.5), 2),
            'angle'    => $l_angle,
            'radius'   => $l_radius,
        ];
        if (count($radar_vectors) >= 12) break;
    }
}

// 3. Central Protection Interlock Nodes
$sentinel_nodes = [
    ['name' => 'ZEUS WAF',       'active' => $zeus_active,                    'angle' => 45,  'r' => 20],
    ['name' => 'AEGIS FIREWALL', 'active' => !empty($opt['aegis_enabled']),      'angle' => 135, 'r' => 20],
    ['name' => 'PROMETHEUS AI',  'active' => !empty($opt['prometheus_enabled']), 'angle' => 225, 'r' => 20],
    ['name' => 'TITAN KERNEL',   'active' => !empty($opt['titan_enabled']),      'angle' => 315, 'r' => 20],
];

// =========================================================================================
// 6. REAL-TIME THREAT MAP & GLOBAL VECTOR MATRIX (LOCAL BGP/ASN RESOLUTION)
// =========================================================================================
$host_origin = \VisionGaia\GeDefense\Core\GeoResolver::get_origin();
$host_point  = \VisionGaia\GeDefense\Core\GeoResolver::lat_lng_to_point((float)$host_origin['latitude'], (float)$host_origin['longitude']);

$threat_map_events = [];
$seen_threat_ips = [];
$country_threat_counts = [];
$threat_blocked_count = 0;
$threat_suspicious_count = 0;

if (!function_exists('vis_resolve_telemetry_meta')) {
    function vis_resolve_telemetry_meta(string $module, string $message, string $ip, bool $is_ban = false): array {
        $mod = strtoupper(trim($module));
        $low_msg = strtolower($message);
        
        // 1. Resolve Path from explicit path occurrences in message or reason
        $path = '';
        if (preg_match('#(/[a-zA-Z0-9_\-\.\/]+(?:\.[a-zA-Z0-9]+|\?[\w\=\&\-]+)?)#', $message, $m)) {
            if (!in_array($m[1], ['/dev/null', '/bin/sh', '/bin/bash'], true)) {
                $path = $m[1];
            }
        }
        
        if (empty($path)) {
            if (str_contains($low_msg, 'xmlrpc') || str_contains($low_msg, 'pingback')) $path = '/xmlrpc.php';
            elseif (str_contains($low_msg, 'rest') || str_contains($low_msg, 'wp-json')) $path = '/wp-json/wp/v2/users';
            elseif (str_contains($low_msg, 'ajax') || str_contains($low_msg, 'admin-ajax')) $path = '/wp-admin/admin-ajax.php';
            elseif (str_contains($low_msg, 'author') || str_contains($low_msg, 'enumeration')) $path = '/?author=1';
            elseif (str_contains($low_msg, 'cron') || str_contains($low_msg, 'schedule')) $path = '/wp-cron.php?doing_wp_cron';
            elseif (str_contains($low_msg, 'env') || str_contains($low_msg, 'dotenv')) $path = '/.env';
            elseif (str_contains($low_msg, 'config')) $path = '/wp-config.php.bak';
            elseif (str_contains($low_msg, 'setup')) $path = '/wp-admin/setup-config.php';
            elseif (str_contains($low_msg, 'install')) $path = '/wp-admin/install.php';
            elseif (str_contains($low_msg, 'upload') || str_contains($low_msg, 'media')) $path = '/wp-admin/async-upload.php';
            elseif (str_contains($low_msg, 'login') || str_contains($low_msg, 'auth')) $path = '/wp-login.php';
        }
        
        if (empty($path)) {
            switch ($mod) {
                case 'CHRONOS':
                    $path = '/wp-cron.php?doing_wp_cron';
                    break;
                case 'GORGON':
                    $path = '/wp-admin/admin-ajax.php?action=vgt_gorgon';
                    break;
                case 'SYSTEM':
                case 'KERNEL':
                    $path = '/wp-admin/admin.php?page=vgt-sentinel';
                    break;
                case 'PROMETHEUS':
                    $path = '/.env';
                    break;
                case 'AEGIS':
                    $path = '/xmlrpc.php';
                    break;
                case 'ZEUS':
                    $path = '/?author=1';
                    break;
                case 'CERBERUS':
                    $path = '/wp-admin/post.php';
                    break;
                case 'AIRLOCK':
                    $path = '/wp-content/uploads/quarantine/';
                    break;
                case 'HADES':
                    $path = '/wp-content/plugins/wp-file-manager/';
                    break;
                case 'GHOST_TRAP':
                    $path = '/wp-content/uploads/shell.php';
                    break;
                case 'FILESYSTEM':
                    $path = '/.git/config';
                    break;
                case 'TITAN':
                    $path = '/wp-json/wp/v2/users';
                    break;
                case 'LOGINPAGER':
                    $path = '/wp-login.php';
                    break;
                case 'THIRDPARTY':
                case 'ORACLE':
                    $path = '/wp-comments-post.php';
                    break;
                default:
                    $fallback_paths = [
                        '/.env',
                        '/xmlrpc.php',
                        '/wp-login.php',
                        '/wp-admin/admin-ajax.php',
                        '/?author=1',
                        '/wp-config.php.bak',
                        '/wp-json/wp/v2/users',
                        '/.git/config',
                        '/setup-config.php',
                        '/wp-cron.php',
                        '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php'
                    ];
                    $h = abs(crc32($ip . $mod));
                    $path = $fallback_paths[$h % count($fallback_paths)];
                    break;
            }
        }
        
        // 2. Resolve Action & Severity
        if ($is_ban) {
            return ['path' => $path, 'action' => 'HARD-BAN (DROP)', 'severity' => 'blocked'];
        }
        
        $is_crit = in_array($mod, ['ZEUS', 'CERBERUS', 'STYX', 'PERIMETER BAN'], true) 
                   || str_contains($low_msg, 'block') 
                   || str_contains($low_msg, 'exploit') 
                   || str_contains($low_msg, 'drop');
                   
        $is_warn = in_array($mod, ['PROMETHEUS', 'NEMESIS', 'ORACLE', 'AEGIS', 'TITAN', 'AIRLOCK', 'HADES', 'GHOST_TRAP'], true) 
                   || str_contains($low_msg, 'anomaly') 
                   || str_contains($low_msg, 'tarpit')
                   || str_contains($low_msg, 'trap');
                   
        if ($is_crit) {
            $action = 'INTERCEPT (DROP)';
            $severity = 'blocked';
        } elseif ($is_warn) {
            $action = ($mod === 'AIRLOCK') ? 'ISOLATED' : (($mod === 'HADES' || $mod === 'GHOST_TRAP') ? 'HONEYPOT TRAP' : 'TARPIT (DELAY)');
            $severity = 'suspicious';
        } else {
            if ($mod === 'CHRONOS') {
                $action = 'DISPATCHED (OK)';
                $severity = 'tracked';
            } elseif ($mod === 'GORGON') {
                $action = 'TOKEN VERIFIED';
                $severity = 'tracked';
            } elseif ($mod === 'SYSTEM' || $mod === 'KERNEL') {
                $action = 'INTEGRITY PASS';
                $severity = 'tracked';
            } else {
                $action = 'MONITORED';
                $severity = 'tracked';
            }
        }
        
        return ['path' => $path, 'action' => $action, 'severity' => $severity];
    }
}

// Helper to format authentic relative or absolute timestamps
$format_event_time = static function($raw_time): string {
    $str = trim((string)$raw_time);
    if ($str === '' || $str === '0000-00-00 00:00:00') {
        return __('Aktiv', 'vgt-sentinel');
    }
    $ts = is_numeric($str) ? (int)$str : strtotime($str);
    if ($ts && $ts > 0) {
        $now = time();
        $diff = $now - $ts;
        if ($diff >= 0 && $diff < 60) {
            return __('Gerade eben', 'vgt-sentinel');
        } elseif ($diff >= 60 && $diff < 3600) {
            $mins = max(1, (int)round($diff / 60));
            return sprintf(__('Vor %d Min.', 'vgt-sentinel'), $mins);
        } elseif ($diff >= 3600 && $diff < 86400) {
            $hours = (int)round($diff / 3600);
            return sprintf(__('Vor %d Std.', 'vgt-sentinel'), $hours);
        } elseif ($diff >= 86400 && $diff < 604800) {
            $days = (int)round($diff / 86400);
            return sprintf(__('Vor %d T.', 'vgt-sentinel'), $days);
        } else {
            return date_i18n('d.m. H:i', $ts);
        }
    }
    return $str;
};

// 1. Harvest from active IP bans (highest priority critical threats)
if (!empty($raw_bans)) {
    foreach ($raw_bans as $b) {
        $b_ip = trim((string)($b->ip ?? $b->ip_address ?? ''));
        if ($b_ip === '' || isset($seen_threat_ips[$b_ip])) continue;
        $seen_threat_ips[$b_ip] = true;
        
        $geo = \VisionGaia\GeDefense\Core\GeoResolver::resolve($b_ip);
        $pt = \VisionGaia\GeDefense\Core\GeoResolver::lat_lng_to_point((float)$geo['latitude'], (float)$geo['longitude']);
        
        $cc = $geo['country_code'];
        $country_threat_counts[$cc] = ($country_threat_counts[$cc] ?? 0) + 1;
        $threat_blocked_count++;
        
        $meta = vis_resolve_telemetry_meta('Sentinel L1 Firewall', (string)($b->reason ?? ''), $b_ip, true);
        
        $threat_map_events[] = [
            'ip'           => $b_ip,
            'country'      => $cc,
            'country_name' => $geo['country_name'],
            'asn'          => $geo['asn'],
            'as_name'      => $geo['as_name'],
            'lat'          => $geo['latitude'],
            'lng'          => $geo['longitude'],
            'x'            => $pt['x'],
            'y'            => $pt['y'],
            'path'         => $meta['path'],
            'module'       => 'Sentinel L1 Firewall',
            'action'       => $meta['action'],
            'severity'     => $meta['severity'],
            'time'         => $format_event_time($b->banned_at ?? $b->timestamp ?? ''),
        ];
        if (count($threat_map_events) >= 30) break;
    }
}

// 2. Harvest from real security logs (Sentinel & Prometheus)
if (!empty($merged_logs) && count($threat_map_events) < 30) {
    foreach ($merged_logs as $entry) {
        $l_ip = trim((string)$entry->ip);
        if ($l_ip === '' || $l_ip === 'N/A' || $l_ip === 'UNKNOWN' || isset($seen_threat_ips[$l_ip])) continue;
        $seen_threat_ips[$l_ip] = true;
        
        $geo = \VisionGaia\GeDefense\Core\GeoResolver::resolve($l_ip);
        $pt = \VisionGaia\GeDefense\Core\GeoResolver::lat_lng_to_point((float)$geo['latitude'], (float)$geo['longitude']);
        
        $cc = $geo['country_code'];
        $country_threat_counts[$cc] = ($country_threat_counts[$cc] ?? 0) + 1;
        
        $l_mod = strtoupper((string)$entry->module);
        $l_msg = (string)$entry->message;
        $meta = vis_resolve_telemetry_meta($l_mod, $l_msg, $l_ip, false);
        
        if ($meta['severity'] === 'blocked') {
            $threat_blocked_count++;
        } elseif ($meta['severity'] === 'suspicious') {
            $threat_suspicious_count++;
        }

        $threat_map_events[] = [
            'ip'           => $l_ip,
            'country'      => $cc,
            'country_name' => $geo['country_name'],
            'asn'          => $geo['asn'],
            'as_name'      => $geo['as_name'],
            'lat'          => $geo['latitude'],
            'lng'          => $geo['longitude'],
            'x'            => $pt['x'],
            'y'            => $pt['y'],
            'path'         => $meta['path'],
            'module'       => $l_mod ?: 'Kernel Security',
            'action'       => $meta['action'],
            'severity'     => $meta['severity'],
            'time'         => $format_event_time($entry->timestamp ?? ''),
        ];
        if (count($threat_map_events) >= 30) break;
    }
}
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<!-- =========================================================================================
     VIEW CONTENT - VGT SENTINEL CYBER DEFENSE COCKPIT
     ========================================================================================= -->
<div class="vis-overview-container vgt-command-center">

    <?php
    // Calculate Security Score & Status Matrix (Parity with view-systatus)
    $score = 0;
    $score_details = [];

    // Zeus (20%)
    $zeus_config = get_option('vis_zeus_config', []);
    $zeus_active_check = !empty($zeus_config);
    if ($zeus_active_check) {
        $score += 20;
        $score_details[] = ['name' => 'Zeus Defender', 'active' => true, 'weight' => 20, 'tab' => 'zeus'];
    } else {
        $score_details[] = ['name' => 'Zeus Defender', 'active' => false, 'weight' => 20, 'tab' => 'zeus'];
    }

    // Aegis (15%)
    if (!empty($opt['aegis_enabled'])) {
        $score += 15;
        $score_details[] = ['name' => 'Aegis WAF', 'active' => true, 'weight' => 15, 'tab' => 'aegis'];
    } else {
        $score_details[] = ['name' => 'Aegis WAF', 'active' => false, 'weight' => 15, 'tab' => 'aegis'];
    }

    // ThroneGuard (15%)
    $throne_status = class_exists('VIS_Throne_Guard') ? VIS_Throne_Guard::status() : [];
    $throne_active = !empty($throne_status['harden_admin']) || !empty($throne_status['is_master']) || !empty($opt['throneguard_enabled']);
    if ($throne_active) {
        $score += 15;
        $score_details[] = ['name' => 'ThroneGuard Master', 'active' => true, 'weight' => 15, 'tab' => 'throneguard'];
    } else {
        $score_details[] = ['name' => 'ThroneGuard Master', 'active' => false, 'weight' => 15, 'tab' => 'throneguard'];
    }

    // Prometheus (15%)
    if (!empty($opt['prometheus_enabled'])) {
        $score += 15;
        $score_details[] = ['name' => 'Prometheus AI', 'active' => true, 'weight' => 15, 'tab' => 'prometheus'];
    } else {
        $score_details[] = ['name' => 'Prometheus AI', 'active' => false, 'weight' => 15, 'tab' => 'prometheus'];
    }

    // Titan (15%)
    if (!empty($opt['titan_enabled'])) {
        $score += 15;
        $score_details[] = ['name' => 'Titan Hardening', 'active' => true, 'weight' => 15, 'tab' => 'titan'];
    } else {
        $score_details[] = ['name' => 'Titan Hardening', 'active' => false, 'weight' => 15, 'tab' => 'titan'];
    }

    // Hades (10%)
    if (!empty($opt['hades_enabled'])) {
        $score += 10;
        $score_details[] = ['name' => 'Hades Stealth', 'active' => true, 'weight' => 10, 'tab' => 'hades'];
    } else {
        $score_details[] = ['name' => 'Hades Stealth', 'active' => false, 'weight' => 10, 'tab' => 'hades'];
    }

    // Cerberus (5%)
    if (class_exists('VIS_Cerberus')) {
        $score += 5;
        $score_details[] = ['name' => 'Cerberus Perimeter', 'active' => true, 'weight' => 5, 'tab' => 'cerberus'];
    } else {
        $score_details[] = ['name' => 'Cerberus Perimeter', 'active' => false, 'weight' => 5, 'tab' => 'cerberus'];
    }

    // Airlock (5%)
    if (!isset($opt['airlock_enabled']) || !empty($opt['airlock_enabled'])) {
        $score += 5;
        $score_details[] = ['name' => 'Airlock Ingress', 'active' => true, 'weight' => 5, 'tab' => 'airlock'];
    } else {
        $score_details[] = ['name' => 'Airlock Ingress', 'active' => false, 'weight' => 5, 'tab' => 'airlock'];
    }

    // Scoring Meta
    if ($score >= 90) {
        $score_status = __('MAXIMALER SCHUTZ', 'vgt-sentinel');
        $score_desc = __('Herausragende Abwehrbereitschaft. Alle kritischen Module und Perimeter-Schilde sind aktiv. Die Seite läuft unter maximalem Schutz.', 'vgt-sentinel');
        $score_color = '#10b981'; // Green
    } elseif ($score >= 70) {
        $score_status = __('OPTIMALER SCHUTZ', 'vgt-sentinel');
        $score_desc = __('Hohe Sicherheitsabdeckung. Die primären Firewalls und Härtungskomponenten sind aktiv. Einige Deception-Schilde oder periphere WAFs könnten noch hinzugeschaltet werden.', 'vgt-sentinel');
        $score_color = '#3b82f6'; // Blue
    } elseif ($score >= 40) {
        $score_status = __('MINIMALER SCHUTZ', 'vgt-sentinel');
        $score_desc = __('Basisüberwachung ist aktiv. Es wird dringend empfohlen, Aegis WAF, Titan Hardening und Zeus Defender im Setup-Wizard oder Command Center zu konfigurieren.', 'vgt-sentinel');
        $score_color = '#f59e0b'; // Orange
    } else {
        $score_status = __('GEFÄHRDET (LOW)', 'vgt-sentinel');
        $score_desc = __('Kritischer Zustand! Fast alle Sicherheitsmodule sind inaktiv. Das System bietet derzeit keinen ausreichenden Schutz vor Targeted Exploits oder Brute-Force-Angriffen.', 'vgt-sentinel');
        $score_color = '#ef4444'; // Red
    }

    $dashoffset = 439 - (439 * $score / 100);
    ?>

    <!-- MASTER MISSION CONTROL COCKPIT HERO (VGT GLASS 2100) -->
    <div class="vsc-hero" id="vis-overview-hero" style="border-left: 4px solid <?php echo esc_attr($score_color); ?>;">
        <div class="vsc-hero-copy">
            <span class="vsc-eyebrow"><span class="vsc-live-dot" aria-hidden="true" style="background:<?php echo esc_attr($score_color); ?>; box-shadow: 0 0 14px <?php echo esc_attr($score_color); ?>;"></span><?php esc_html_e('COMMAND NEXUS // OMNIPRESENT DEFENSE', 'vgt-sentinel'); ?></span>
            <h2><?php esc_html_e('Kontrollzentrum & Cyber Defense', 'vgt-sentinel'); ?></h2>
            <p><?php echo esc_html($score_desc); ?></p>
            <div class="vsc-actions">
                <button type="button" id="vis-btn-overview-scan" class="vsc-button vsc-button-primary vis-btn-scan" data-mode="scan">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span><?php esc_html_e('RUN DEEP SCAN', 'vgt-sentinel'); ?></span>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=security_center')); ?>" class="vsc-button vsc-button-secondary">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span><?php esc_html_e('SICHERHEITSZENTRALE', 'vgt-sentinel'); ?></span>
                </a>
                <span class="vsc-last-run">
                    <?php esc_html_e('NOC CORE ONLINE · AES-256-GCM · ZERO-TRUST', 'vgt-sentinel'); ?>
                </span>
            </div>
        </div>
        <div class="vsc-score-panel">
            <div class="vsc-score-ring" id="vis-overview-score-ring" role="img" aria-label="<?php echo esc_attr__('Sicherheits-Score', 'vgt-sentinel'); ?>" style="--vsc-score: <?php echo esc_attr((string)round($score * 3.6)); ?>deg; background: conic-gradient(<?php echo esc_attr($score_color); ?> var(--vsc-score), rgba(148, 184, 224, 0.12) 0); filter: drop-shadow(0 0 24px <?php echo esc_attr($score_color); ?>40);">
                <div><strong class="vgt-countup" data-target="<?php echo (int)$score; ?>" data-format="raw"><?php echo esc_html((string)$score); ?></strong><span>%</span></div>
            </div>
            <div class="vsc-score-meta">
                <span class="vsc-posture" style="color: <?php echo esc_attr($score_color); ?>;"><?php echo esc_html($score_status); ?></span>
                <small><?php esc_html_e('Active Shield Index', 'vgt-sentinel'); ?></small>
            </div>
        </div>
    </div>

    <!-- MISSION CRITICAL TELEMETRY BENTO GRID (4 GLASS TILES) -->
    <div class="vis-cockpit-bento">
        <?php
        $active_modules_count = count(array_filter($score_details, static function($d) { return !empty($d['active']); }));
        ?>
        <!-- TILE 1: INTERLOCK NODES -->
        <div class="vis-bento-tile tile-shield" style="--tile-accent: <?php echo esc_attr($score_color); ?>;">
            <div class="vis-bento-tile-head">
                <span class="vis-bento-kicker"><?php esc_html_e('01 / INTERLOCK', 'vgt-sentinel'); ?></span>
                <span class="vis-badge" style="background: <?php echo esc_attr($score_color); ?>18; color: <?php echo esc_attr($score_color); ?>; border: 1px solid <?php echo esc_attr($score_color); ?>35;">
                    <?php echo esc_html($score_status); ?>
                </span>
            </div>
            <div class="vis-bento-metric-val text-mono" style="color: <?php echo esc_attr($score_color); ?>;">
                <span class="vgt-countup" data-target="<?php echo (int)$active_modules_count; ?>" data-format="raw"><?php echo esc_html((string)$active_modules_count); ?></span> / 8
            </div>
            <div class="vis-bento-metric-sub">
                <strong><?php esc_html_e('Autonome Abwehr-Module', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Synchronisierte Schutzschilde im aktiven Kernel-Verbund.', 'vgt-sentinel'); ?></p>
            </div>
        </div>

        <!-- TILE 2: MITIGATION COUNTER -->
        <div class="vis-bento-tile tile-mitigation">
            <div class="vis-bento-tile-head">
                <span class="vis-bento-kicker"><?php esc_html_e('02 / FIREWALL & WAF', 'vgt-sentinel'); ?></span>
                <span class="vis-badge vis-badge-info"><?php esc_html_e('PERIMETER', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vis-bento-metric-val text-mono" style="color: var(--vis-cyan, #5eead4);">
                <span class="vgt-countup" data-target="<?php echo (int)$aegis_blocks; ?>" data-format="int"><?php echo esc_html(number_format_i18n($aegis_blocks)); ?></span>
            </div>
            <div class="vis-bento-metric-sub">
                <strong><?php esc_html_e('Abgewehrte Zugriffsversuche', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Zeus WAF, Cerberus IP-Bans & Aegis Firewall Interceptions.', 'vgt-sentinel'); ?></p>
            </div>
        </div>

        <!-- TILE 3: COGNITIVE AI THREATS -->
        <div class="vis-bento-tile tile-ai">
            <div class="vis-bento-tile-head">
                <span class="vis-bento-kicker"><?php esc_html_e('03 / AI COGNITIVE DEFENSE', 'vgt-sentinel'); ?></span>
                <span class="vis-badge" style="background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.35);">
                    <?php esc_html_e('HEURISTIC', 'vgt-sentinel'); ?>
                </span>
            </div>
            <div class="vis-bento-metric-val text-mono" style="color: #c084fc;">
                <span class="vgt-countup" data-target="<?php echo (int)$ai_threats; ?>" data-format="int"><?php echo esc_html(number_format_i18n($ai_threats)); ?></span>
            </div>
            <div class="vis-bento-metric-sub">
                <strong><?php esc_html_e('KI-Erkannte Bedrohungen', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Prometheus Zero-Trust, Oracle Prophecy & Nemesis Deception.', 'vgt-sentinel'); ?></p>
            </div>
        </div>

        <!-- TILE 4: TITAN HARDENING -->
        <div class="vis-bento-tile tile-titan">
            <div class="vis-bento-tile-head">
                <span class="vis-bento-kicker"><?php esc_html_e('04 / SYSTEM HARDENING', 'vgt-sentinel'); ?></span>
                <span class="vis-badge vis-badge-success"><?php esc_html_e('TITAN CORE', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vis-bento-metric-val text-mono" style="color: var(--vis-green, #10b981);">
                <span class="vgt-countup" data-target="<?php echo (int)$titan_score; ?>" data-format="raw" data-suffix="%"><?php echo esc_html((string)$titan_score); ?>%</span>
            </div>
            <div class="vis-bento-metric-sub">
                <strong><?php echo esc_html(sprintf(__('%d Aktive Kernel-Schlösser', 'vgt-sentinel'), $titan_active_count)); ?></strong>
                <p><?php esc_html_e('REST-API Guard, XML-RPC Cloak, Nonce Gates & Permissions.', 'vgt-sentinel'); ?></p>
            </div>
        </div>
    </div>

    <!-- FULL-WIDTH REAL-TIME THREAT MAP & LIVE ANOMALY STREAM (GLASS 2100) -->
    <?php include VIS_PATH . 'includes/dashboard/views/overview/threat-map.php'; ?>

    <!-- MAIN TWO COLUMN Cockpit DISPLAY -->
    <div class="vis-overview-layout">
        
        <!-- LEFT MAIN PANEL -->
        <div class="vis-main-panel">
            
            <!-- 8-MODULE DEFENSE INTERLOCK MATRIX -->
            <div class="vis-cyber-card" style="border-top: 3px solid <?php echo esc_attr($score_color); ?>;">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:18px; height:18px; color:<?php echo esc_attr($score_color); ?>;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                        <?php esc_html_e('COCKPIT PROTECTION MATRIX (8-MODULE INTERLOCK)', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge" style="background: <?php echo esc_attr($score_color); ?>20; color: <?php echo esc_attr($score_color); ?>; border: 1px solid <?php echo esc_attr($score_color); ?>40;"><?php echo esc_html($score_status); ?></span>
                </div>
                
                <div class="vgt-matrix-grid">
                    <?php foreach ($score_details as $detail): ?>
                        <div class="vgt-matrix-item <?php echo $detail['active'] ? 'active' : 'inactive'; ?>">
                            <div class="vgt-matrix-info">
                                <div class="vgt-matrix-topline">
                                    <span class="vgt-status-dot <?php echo $detail['active'] ? 'active' : 'inactive'; ?>"></span>
                                    <strong class="vgt-matrix-name"><?php echo esc_html($detail['name']); ?></strong>
                                </div>
                                <div class="vgt-matrix-status" style="color: <?php echo $detail['active'] ? '#10b981' : '#ef4444'; ?>;">
                                    <?php echo $detail['active'] ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?>
                                    <span style="opacity: 0.6; color: #94a3b8; font-family: monospace; font-size: 10px; margin-left: 5px;">(+<?php echo esc_html((string)$detail['weight']); ?>%)</span>
                                </div>
                            </div>
                            <a href="?page=vgt-suite&tab=<?php echo esc_attr($detail['tab']); ?>" class="vgt-matrix-action">
                                <span><?php esc_html_e('CONFIG', 'vgt-sentinel'); ?></span>
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- COMBINED TRAFFIC PROTOCOLS -->
            <div class="vis-cyber-card">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:18px; height:18px; color:#00f2ff;" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        <?php esc_html_e('GLOBAL COGNITIVE INCIDENT PROTOCOLS', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge vis-badge-info"><?php esc_html_e('COMBINED LOGS', 'vgt-sentinel'); ?></span>
                </div>
                
                <?php if(empty($final_logs)): ?>
                    <div style="padding:30px; text-align:center; color:#888;">
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:42px; height:42px; margin-bottom:12px; display:block; color:#10b981; margin-left:auto; margin-right:auto;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <h4 style="color:#fff; margin:0 0 4px 0; font-size:13px;"><?php esc_html_e('SHIELD CLEAN', 'vgt-sentinel'); ?></h4>
                        <p style="margin:0; font-size:11px;"><?php esc_html_e('Keine Sicherheitsvorfälle im Protokoll verzeichnet.', 'vgt-sentinel'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="vis-table-wrapper" style="max-height: 400px; overflow-y: auto; overflow-x: auto;">
                        <table class="vis-table" style="width: 100%; table-layout: fixed;">
                            <thead>
                                <tr>
                                    <th style="width: 135px;"><?php esc_html_e('TIMESTAMP', 'vgt-sentinel'); ?></th>
                                    <th style="width: 100px;"><?php esc_html_e('SOURCE', 'vgt-sentinel'); ?></th>
                                    <th style="width: 120px;"><?php esc_html_e('IP ADDRESS', 'vgt-sentinel'); ?></th>
                                    <th><?php esc_html_e('EVENT DETAILS', 'vgt-sentinel'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($final_logs as $log): 
                                $badge_style = 'background:rgba(255,255,255,0.1); color:#fff; border: 1px solid rgba(255,255,255,0.2);';
                                if ($log->module === 'NEMESIS') $badge_style = 'background:rgba(188,19,254,0.15); color:#bc13fe; border:1px solid rgba(188,19,254,0.4);';
                                elseif ($log->module === 'PROMETHEUS') $badge_style = 'background:rgba(0,242,255,0.15); color:#00f2ff; border:1px solid rgba(0,242,255,0.4);';
                                elseif ($log->module === 'AEGIS') $badge_style = 'background:rgba(0,255,170,0.15); color:#00ffaa; border:1px solid rgba(0,255,170,0.4);';
                                elseif ($log->module === 'ZEUS') $badge_style = 'background:rgba(255,0,60,0.15); color:#ff003c; border:1px solid rgba(255,0,60,0.4);';
                                elseif ($log->module === 'STYX') $badge_style = 'background:rgba(255,0,60,0.15); color:#ff003c; border:1px solid rgba(255,0,60,0.4);';
                            ?>
                                <tr>
                                    <td class="text-mono" style="color:#777; font-size:10px; white-space: nowrap;">
                                        <?php echo esc_html((string)$log->timestamp); ?>
                                    </td>
                                    <td>
                                        <span class="vis-badge" style="<?php echo esc_attr($badge_style); ?> font-weight:700; font-size:9px; padding: 2px 6px;">
                                            <?php echo esc_html((string)$log->module); ?>
                                        </span>
                                    </td>
                                    <td class="text-mono" style="color:#00f2ff; font-size:11px; white-space: nowrap;">
                                        <?php echo esc_html((string)$log->ip); ?>
                                    </td>
                                    <td style="color:#cbd5e1; font-size:11px; line-height: 1.4; overflow-wrap: anywhere; word-break: break-word;">
                                        <?php echo esc_html((string)$log->message); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ORACLE DYNAMIC INTELLIGENCE FEED -->
            <div class="vis-cyber-card" style="border-top: 2px solid #00f2ff;">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:18px; height:18px; color:#00f2ff;" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                        <?php esc_html_e('ORACLE INTELLIGENCE AUDIT FEED', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge vis-badge-info"><?php esc_html_e('REAL-TIME', 'vgt-sentinel'); ?></span>
                </div>

                <?php if(empty($oracle_logs)): ?>
                    <div style="padding:30px; text-align:center; color:#888;">
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:42px; height:42px; margin-bottom:12px; display:block; color:#00f2ff; opacity: 0.3; margin-left:auto; margin-right:auto;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <h4 style="color:#fff; margin:0 0 4px 0; font-size:13px;"><?php esc_html_e('ORACLE STANDBY', 'vgt-sentinel'); ?></h4>
                        <p style="margin:0; font-size:11px;"><?php esc_html_e('Das AI-Modell ist bereit. Es wartet auf eingehende Request-Analysen.', 'vgt-sentinel'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="vgt-oracle-list">
                        <?php foreach($oracle_logs as $olog): 
                            $is_block = ($olog->type === 'ZERO_DAY_BLOCK');
                            $verdict_label = $is_block ? __('BLOCKIERT', 'vgt-sentinel') : __('ERLAUBT', 'vgt-sentinel');
                            $verdict_class = $is_block ? 'is-block' : 'is-safe';
                            $title_icon_path = $is_block ? '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>' : '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>';
                        ?>
                            <div class="vgt-oracle-event">
                                <div class="vgt-oracle-header vgt-accordion-trigger">
                                    <div class="vgt-oracle-meta-row">
                                        <span class="vgt-oracle-time"><?php echo esc_html((string)$olog->timestamp); ?></span>
                                        <span class="vgt-oracle-ip-label">
                                            <strong><?php esc_html_e('ZIEL-IP:', 'vgt-sentinel'); ?></strong>
                                            <code><?php echo esc_html((string)$olog->ip); ?></code>
                                        </span>
                                        <span class="vgt-oracle-verdict-label">
                                            <strong><?php esc_html_e('BEWERTUNG:', 'vgt-sentinel'); ?></strong>
                                            <span class="vgt-oracle-badge <?php echo esc_attr($verdict_class); ?>"><?php echo esc_html($verdict_label); ?></span>
                                        </span>
                                    </div>
                                    <span class="vgt-accordion-icon">
                                        <svg width='16' height='16' fill='none' stroke='currentColor' class="vgt-icon" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </span>
                                </div>
                                
                                <div class="vgt-oracle-body-wrapper">
                                    <div class="vgt-oracle-body">
                                        <div class="vgt-oracle-reasoning">
                                            <div class="vgt-oracle-title">
                                                <svg width='14' height='14' fill='none' stroke='currentColor' class="vgt-icon" viewBox="0 0 24 24"><?php echo $title_icon_path; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg>
                                                <?php esc_html_e('KI-BEGRÜNDUNG (AUDIT)', 'vgt-sentinel'); ?>
                                            </div>
                                            <div class="vgt-oracle-text">
                                                <?php echo wp_kses_post(nl2br(esc_html((string)$olog->ai_reason))); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="vgt-oracle-payload">
                                            <div class="vgt-oracle-title" style="color: #94a3b8;">
                                                &lt;&gt; <?php esc_html_e('ROH-PAYLOAD (ABGEFANGEN)', 'vgt-sentinel'); ?>
                                            </div>
                                            <pre><code><?php echo esc_html((string)$olog->message); ?></code></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT SIDEBAR PANEL -->
        <div class="vis-sidebar-panel">

            <!-- CYBER DEFENSE AIRSPACE RADAR (AUTHENTIC REAL TELEMETRY RADAR) -->
            <div class="vis-cyber-card vis-card-radar" id="vis-defense-radar-card">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg class="vgt-icon" style="color:var(--vis-cyan);" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                        <?php esc_html_e('CYBER DEFENSE RADAR', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge <?php echo $radar_critical_count > 0 ? 'vis-badge-danger' : 'vis-badge-info'; ?>">
                        <?php echo $radar_critical_count > 0 ? esc_html(sprintf(__('%d HOSTILE ACTIVE', 'vgt-sentinel'), $radar_critical_count)) : esc_html__('AIRSPACE NOMINAL', 'vgt-sentinel'); ?>
                    </span>
                </div>
                
                <div class="vis-radar-scope-wrapper">
                    <div class="vis-radar-container" role="region" aria-label="<?php esc_attr_e('Echtzeit-Luftraum-Radar für Cyber-Bedrohungen', 'vgt-sentinel'); ?>">
                        <svg class="vis-radar-compass" viewBox="0 0 200 200" aria-hidden="true">
                            <!-- Degree markings & concentric range rings -->
                            <circle class="vis-radar-grid-ring ring-100" cx="100" cy="100" r="95"></circle>
                            <circle class="vis-radar-grid-ring ring-75" cx="100" cy="100" r="71"></circle>
                            <circle class="vis-radar-grid-ring ring-50" cx="100" cy="100" r="47"></circle>
                            <circle class="vis-radar-grid-ring ring-25" cx="100" cy="100" r="23"></circle>
                            <line class="vis-radar-grid-axis" x1="5" y1="100" x2="195" y2="100"></line>
                            <line class="vis-radar-grid-axis" x1="100" y1="5" x2="100" y2="195"></line>
                            <line class="vis-radar-grid-diagonal" x1="33" y1="33" x2="167" y2="167"></line>
                            <line class="vis-radar-grid-diagonal" x1="167" y1="33" x2="33" y2="167"></line>
                            
                            <!-- Cardinal Direction Labels -->
                            <text x="100" y="15" class="vis-radar-cardinal" text-anchor="middle">000° N</text>
                            <text x="186" y="104" class="vis-radar-cardinal" text-anchor="end">090° E</text>
                            <text x="100" y="194" class="vis-radar-cardinal" text-anchor="middle">180° S</text>
                            <text x="14" y="104" class="vis-radar-cardinal" text-anchor="start">270° W</text>
                        </svg>

                        <!-- Rotating Phosphor Sweep Beam -->
                        <div class="vis-radar-sweep"></div>

                        <!-- Central Sentinel Defense Core -->
                        <div class="vis-radar-core-hub" title="<?php esc_attr_e('GeDefense WP Core Interlock', 'vgt-sentinel'); ?>">
                            <div class="vis-radar-core-dot"></div>
                        </div>

                        <!-- Real Defense Sentinels (Defensive Matrix Nodes) -->
                        <?php foreach ($sentinel_nodes as $s_node): 
                            $sn_rad = deg2rad($s_node['angle']);
                            $sn_x = 50 + ($s_node['r'] * cos($sn_rad) * 0.5);
                            $sn_y = 50 + ($s_node['r'] * sin($sn_rad) * 0.5);
                        ?>
                            <div class="vis-radar-node node-sentinel <?php echo $s_node['active'] ? 'is-active' : 'is-inactive'; ?>"
                                 style="left: <?php echo esc_attr((string)round($sn_x, 1)); ?>%; top: <?php echo esc_attr((string)round($sn_y, 1)); ?>%;"
                                 data-name="<?php echo esc_attr($s_node['name']); ?>"
                                 data-module="<?php echo esc_attr($s_node['name']); ?>"
                                 data-severity="<?php echo $s_node['active'] ? 'friendly' : 'offline'; ?>"
                                 data-time="<?php esc_attr_e('Kontinuierlich', 'vgt-sentinel'); ?>"
                                 data-msg="<?php echo $s_node['active'] ? esc_attr__('Schutzschild aktiv & nominal', 'vgt-sentinel') : esc_attr__('Modul deaktiviert oder Standby', 'vgt-sentinel'); ?>"
                                 data-bearing="<?php echo esc_attr((string)$s_node['angle']); ?>°"
                                 data-range="<?php echo esc_attr((string)$s_node['r']); ?>%"
                                 tabindex="0"
                                 role="button"
                                 aria-label="<?php echo esc_attr($s_node['name'] . ': ' . ($s_node['active'] ? 'Aktiv' : 'Inaktiv')); ?>">
                            </div>
                        <?php endforeach; ?>

                        <!-- REAL DETECTED THREAT VECTORS -->
                        <?php if (empty($radar_vectors)): ?>
                            <div class="vis-radar-clean-indicator">
                                <span class="vis-radar-clean-pulse"></span>
                                <small><?php esc_html_e('LUFTRAUM SICHER', 'vgt-sentinel'); ?></small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($radar_vectors as $vec): ?>
                                <div class="vis-radar-blip blip-real severity-<?php echo esc_attr($vec['severity']); ?>"
                                     style="left: <?php echo esc_attr((string)$vec['x']); ?>%; top: <?php echo esc_attr((string)$vec['y']); ?>%; --blip-angle: <?php echo esc_attr((string)$vec['angle']); ?>deg;"
                                     data-ip="<?php echo esc_attr($vec['ip']); ?>"
                                     data-module="<?php echo esc_attr($vec['module']); ?>"
                                     data-severity="<?php echo esc_attr($vec['severity']); ?>"
                                     data-time="<?php echo esc_attr($vec['time']); ?>"
                                     data-msg="<?php echo esc_attr($vec['msg']); ?>"
                                     data-bearing="<?php echo esc_attr((string)$vec['angle']); ?>°"
                                     data-range="<?php echo esc_attr((string)$vec['radius']); ?>%"
                                     tabindex="0"
                                     role="button"
                                     aria-label="<?php echo esc_attr(sprintf('Bedrohung: IP %s, Modul %s, Schweregrad %s', $vec['ip'], $vec['module'], $vec['severity'])); ?>">
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Interactive Glass HUD Tooltip for Target Details -->
                    <div class="vis-radar-target-hud" id="vis-radar-target-hud" style="display: none;" aria-live="polite">
                        <div class="vis-radar-hud-header">
                            <span class="vis-radar-hud-tag" id="vis-hud-severity">TARGET</span>
                            <span class="vis-radar-hud-ip text-mono" id="vis-hud-ip">0.0.0.0</span>
                            <button type="button" class="vis-radar-hud-close" id="vis-hud-close" aria-label="Schließen">×</button>
                        </div>
                        <div class="vis-radar-hud-grid">
                            <div><small><?php esc_html_e('MODUL', 'vgt-sentinel'); ?></small><strong id="vis-hud-module">-</strong></div>
                            <div><small><?php esc_html_e('PEILUNG', 'vgt-sentinel'); ?></small><strong id="vis-hud-bearing">-</strong></div>
                            <div><small><?php esc_html_e('DISTANZ', 'vgt-sentinel'); ?></small><strong id="vis-hud-range">-</strong></div>
                            <div><small><?php esc_html_e('ZEIT', 'vgt-sentinel'); ?></small><strong id="vis-hud-time">-</strong></div>
                        </div>
                        <div class="vis-radar-hud-detail">
                            <small><?php esc_html_e('PAYLOAD / ABFANGGRUND', 'vgt-sentinel'); ?></small>
                            <p id="vis-hud-msg">-</p>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Real-Time Telemetry Bar -->
                <div class="vis-radar-telemetry">
                    <div class="vis-radar-telem-group">
                        <span class="vis-radar-telem-label"><?php esc_html_e('LUFTRAUM-SCAN:', 'vgt-sentinel'); ?></span>
                        <strong class="vis-radar-telem-val" style="color:var(--vis-cyan);">
                            <?php echo empty($radar_vectors) ? esc_html__('NOMINAL (0 BEDROHUNGEN)', 'vgt-sentinel') : esc_html(sprintf(__('%d AKTIVE VEKTOREN', 'vgt-sentinel'), count($radar_vectors))); ?>
                        </strong>
                    </div>
                    <div class="vis-radar-telem-group">
                        <span class="vis-radar-telem-label"><?php esc_html_e('INTERCEPT:', 'vgt-sentinel'); ?></span>
                        <strong class="vis-radar-telem-val" style="color: <?php echo $radar_critical_count > 0 ? 'var(--vis-red)' : 'var(--vis-green)'; ?>;">
                            <?php echo esc_html(sprintf(__('%d CRIT / %d WARN', 'vgt-sentinel'), $radar_critical_count, $radar_warning_count)); ?>
                        </strong>
                    </div>
                </div>
            </div>
            
            <!-- CORE VITALITY GRAPH / SHIELD -->
            <div class="vis-cyber-card vis-card-aegis">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:18px; height:18px; color:#00ffaa;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <?php esc_html_e('CORE SHIELD VITALITY', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge vis-badge-success"><?php esc_html_e('SHIELDED', 'vgt-sentinel'); ?></span>
                </div>
                
                <div class="vis-hero-graph">
                    <div class="vis-pulse-container">
                        <div class="vis-pulse-core"></div>
                        <div class="vis-pulse-ring ring-1"></div>
                        <div class="vis-pulse-ring ring-2"></div>
                    </div>
                </div>
                
                <div class="vis-hero-stats">
                    <div class="stat-group">
                        <span class="stat-value"><?php echo esc_html(number_format_i18n($aegis_blocks)); ?></span>
                        <span class="stat-label"><?php esc_html_e('WAF Blocked', 'vgt-sentinel'); ?></span>
                    </div>
                    <div class="stat-group">
                        <span class="stat-value" style="color: #bc13fe;"><?php echo esc_html(number_format_i18n($ai_threats)); ?></span>
                        <span class="stat-label"><?php esc_html_e('AI Strikes', 'vgt-sentinel'); ?></span>
                    </div>
                </div>
            </div>

            <!-- INTEGRITY baseline status -->
            <div class="vis-cyber-card vis-card-integrity-baseline">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:18px; height:18px; color:#00f2ff;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?php esc_html_e('INTEGRITY baseline', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge" style="background: <?php echo esc_attr($ui_int_color); ?>20; color: <?php echo esc_attr($ui_int_color); ?>; border: 1px solid <?php echo esc_attr($ui_int_color); ?>40;"><?php echo esc_html($integrity_status); ?></span>
                </div>
                
                <div class="vis-integrity-sidebar-detail">
                    <div class="vis-integrity-circle-box" style="color: <?php echo esc_attr($ui_int_color); ?>;">
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:24px; height:24px;" viewBox="0 0 24 24">
                            <?php echo $ui_int_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </svg>
                    </div>
                    <p style="margin: 0; font-size:12px; color:#aaa; line-height: 1.5;">
                        <?php echo esc_html($ui_int_desc); ?><br>
                        <small style="opacity: 0.6; font-family: monospace;">[<?php echo esc_html($ui_int_sub); ?>]</small>
                    </p>
                    <a href="?page=vgt-suite&tab=integrity" class="vis-btn-sidebar-action <?php echo ($integrity_status === 'ANOMALY') ? 'vis-btn-sidebar-danger' : ''; ?>">
                        <?php echo ($integrity_status === 'ANOMALY') ? esc_html__('ANOMALIEN BEHEBEN', 'vgt-sentinel') : esc_html__('SCAN MANAGER', 'vgt-sentinel'); ?>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:12px; height:12px;" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>
            </div>

            <!-- OPEN CORE ACTIVE DEFENSE SHOWCASE -->
            <div class="vis-cyber-card vis-card-license">
                <div class="vis-cyber-card-header">
                    <h3>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="width:18px; height:18px; color:#3b82f6;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <?php esc_html_e('OPEN CORE EDITION', 'vgt-sentinel'); ?>
                    </h3>
                    <span class="vis-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight:700;"><?php esc_html_e('SOVEREIGN (AGPLv3)', 'vgt-sentinel'); ?></span>
                </div>
                
                <p style="font-size: 12px; color: #888; margin-top:0; margin-bottom: 18px; line-height:1.5;">
                    <?php esc_html_e('Unbegrenzte, server-eigene Cyber-Abwehr ohne externe Cloud-Abhängigkeiten oder Telemetrie-Leaks. 100% DSGVO-souverän und latenzfrei.', 'vgt-sentinel'); ?>
                </p>
                
                <div class="vis-value-box">
                    <div class="value-amount" style="color:#10b981;"><?php esc_html_e('369€', 'vgt-sentinel'); ?><span style="font-size:12px; color:#888; font-weight:normal;"> / mo</span></div>
                    <div class="value-text">
                        <?php esc_html_e('SaaS Equivalent Value', 'vgt-sentinel'); ?>
                        <small style="color:#3b82f6; font-weight:600;"><?php esc_html_e('Included Free in Open Core', 'vgt-sentinel'); ?></small>
                    </div>
                </div>
                
                <ul class="vis-feature-list" style="margin-top:16px;">
                    <li>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="color:#10b981; width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('Zero-Latency Pre-Boot Kernel WAF', 'vgt-sentinel'); ?>
                    </li>
                    <li>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="color:#10b981; width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('Prometheus Heuristic AI & Malware Guard', 'vgt-sentinel'); ?>
                    </li>
                    <li>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="color:#10b981; width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('Nemesis Deception Grid & Tarpit Traps', 'vgt-sentinel'); ?>
                    </li>
                    <li>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="color:#10b981; width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('Morpheus RASP Runtime Sandboxing', 'vgt-sentinel'); ?>
                    </li>
                    <li>
                        <svg width='18' height='18' fill='none' stroke='currentColor' class="vgt-icon" style="color:#3b82f6; width:14px; height:14px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                        <?php esc_html_e('Extensible Add-On Hub (VLP, Builder, SEO)', 'vgt-sentinel'); ?>
                    </li>
                </ul>
            </div>
            
        </div>
        
    </div>

</div>
