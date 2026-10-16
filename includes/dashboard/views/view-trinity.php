<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

$xdr_section = isset($_GET['xdr_section']) && is_string($_GET['xdr_section']) ? sanitize_key($_GET['xdr_section']) : 'grid';
$xdr_sections = [
    'grid' => __('Grid-Topologie & Abfang-Routen', 'vgt-sentinel'),
    'overview' => __('Lagebild', 'vgt-sentinel'),
    'incidents' => __('Incidents', 'vgt-sentinel'),
    'stories' => __('Attack Stories', 'vgt-sentinel'),
    'entities' => __('Entities', 'vgt-sentinel'),
    'responses' => __('Responses', 'vgt-sentinel'),
    'evidence' => __('Evidence', 'vgt-sentinel'),
    'policy' => __('Policy', 'vgt-sentinel'),
    'events' => __('Events', 'vgt-sentinel'),
    'health' => __('Health', 'vgt-sentinel'),
];
if (!array_key_exists($xdr_section, $xdr_sections)) $xdr_section = 'grid';
?>
<nav class="vgt-xdr-nav" aria-label="<?php echo esc_attr__('TRINITY XDR Bereiche', 'vgt-sentinel'); ?>">
    <?php foreach ($xdr_sections as $section_key => $section_label): ?>
        <a class="vgt-xdr-nav-link<?php echo $xdr_section === $section_key ? ' is-active' : ''; ?>"
           href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=trinity&xdr_section=' . $section_key)); ?>"
           <?php echo $xdr_section === $section_key ? 'aria-current="page"' : ''; ?>><?php echo esc_html($section_label); ?></a>
    <?php endforeach; ?>
</nav>
<?php
if ($xdr_section !== 'grid') {
    require __DIR__ . '/view-xdr.php';
    return;
}

global $wpdb;

$trinity_config = get_option('vis_trinity_config', []);
$interlock_enabled = !isset($trinity_config['interlock_enabled']) || !empty($trinity_config['interlock_enabled']);
$prom_waf_penalty = $trinity_config['prom_waf_penalty'] ?? 50.0;
$micro_tarpit_score = $trinity_config['micro_tarpit_score'] ?? 75.0;

$opt = get_option('vis_config', []);
$aegis_on = !empty($opt['aegis_enabled']);
$prom_on = !empty($opt['prometheus_enabled']);
$nemesis_on = !empty($opt['nemesis_enabled']);
$cerberus_on = true; // Always booted if exists

$trinity_active = $interlock_enabled && $aegis_on && $prom_on && $nemesis_on;

// Memory status
$use_apcu = function_exists('apcu_add');
$use_memcache = wp_using_ext_object_cache();
$ram_status = ($use_apcu || $use_memcache) ? '100% APCu / Memcached' : 'DB-Cached / Transients';

// --- DATA AGGREGATION FOR OMEGA SECURITY MATRIX ---
$table_bans = $wpdb->prefix . (defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_apex_bans');
$table_logs = $wpdb->prefix . (defined('VIS_TABLE_LOGS') ? VIS_TABLE_LOGS : 'vis_omega_logs');
$prom_table_logs = $wpdb->prefix . 'vis_prometheus_logs';

$trinity_blocks = 0;
$suspicious_events = 0;

$aegis_count = 0;
$prom_count = 0;
$nemesis_count = 0;
$cerberus_count = 0;
$recent_intercepts = [];

$suppress = $wpdb->suppress_errors(true);

if ($wpdb->get_var("SHOW TABLES LIKE '{$table_bans}'")) {
    $trinity_blocks = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bans} WHERE reason LIKE '%NEMESIS%' OR reason LIKE '%PROMETHEUS%' OR reason LIKE '%AEGIS%' OR reason LIKE '%CERBERUS%'");
    
    $aegis_count    = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bans} WHERE reason LIKE '%AEGIS%'");
    $prom_count     = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bans} WHERE reason LIKE '%PROMETHEUS%'");
    $nemesis_count  = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bans} WHERE reason LIKE '%NEMESIS%'");
    $cerberus_count = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bans} WHERE reason NOT LIKE '%AEGIS%' AND reason NOT LIKE '%PROMETHEUS%' AND reason NOT LIKE '%NEMESIS%'");

    $bans_rows = $wpdb->get_results("SELECT ip, reason, banned_at, request_uri FROM {$table_bans} ORDER BY id DESC LIMIT 8", ARRAY_A);
    if (is_array($bans_rows)) {
        foreach ($bans_rows as $row) {
            $reason = (string)($row['reason'] ?? 'GLOBAL_PERIMETER_BAN');
            $module_tag = 'CERBERUS';
            if (str_contains($reason, 'AEGIS')) {
                $module_tag = 'AEGIS';
            } elseif (str_contains($reason, 'PROMETHEUS')) {
                $module_tag = 'PROMETHEUS';
            } elseif (str_contains($reason, 'NEMESIS')) {
                $module_tag = 'NEMESIS';
            }

            $recent_intercepts[] = [
                'module'    => $module_tag,
                'ip'        => $row['ip'],
                'reason'    => $reason,
                'time'      => $row['banned_at'],
                'uri'       => $row['request_uri'] ?: '/',
                'status'    => 'DROP / BANNED'
            ];
        }
    }
}

if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'")) {
    $suspicious_events = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE severity < 10 AND (module = %s OR module = %s OR module = %s)",
        'AEGIS', 'PROMETHEUS', 'NEMESIS'
    ));
}

if ($wpdb->get_var("SHOW TABLES LIKE '{$prom_table_logs}'")) {
    $suspicious_events += (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$prom_table_logs} WHERE type = %s OR type = %s",
        'ANOMALY', 'MICRO_TARPIT'
    ));
}

$total_vectors = max(1, $aegis_count + $prom_count + $nemesis_count + $cerberus_count);
$aegis_pct   = round(($aegis_count / $total_vectors) * 100);
$prom_pct    = round(($prom_count / $total_vectors) * 100);
$nemesis_pct = round(($nemesis_count / $total_vectors) * 100);
$cerberus_pct= round(($cerberus_count / $total_vectors) * 100);

// --- TOP ATTACK ROUTES & INTERCEPTION PATH TELEMETRY ---
$top_routes = [];
$total_route_strikes = 0;
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_bans}'")) {
    $routes_rows = $wpdb->get_results(
        "SELECT request_uri, COUNT(id) as hit_count, 
         MAX(reason) as sample_reason,
         MAX(banned_at) as last_intercept 
         FROM {$table_bans} 
         WHERE request_uri IS NOT NULL AND request_uri != '' 
         GROUP BY request_uri 
         ORDER BY hit_count DESC 
         LIMIT 6",
        ARRAY_A
    );
    if (!empty($routes_rows) && is_array($routes_rows)) {
        $top_routes = $routes_rows;
    }
}

// Fallback / merge from table_logs if fewer than 4 routes in bans
if (count($top_routes) < 4 && $wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'")) {
    $existing_uris = array_column($top_routes, 'request_uri');
    $cols = $wpdb->get_col("DESC {$table_logs}", 0);
    $uri_field = in_array('request_uri', $cols, true) ? 'request_uri' : (in_array('uri', $cols, true) ? 'uri' : null);
    if ($uri_field) {
        $log_routes = $wpdb->get_results(
            "SELECT {$uri_field} as request_uri, COUNT(id) as hit_count, 
             MAX(module) as sample_reason, 
             MAX(timestamp) as last_intercept 
             FROM {$table_logs} 
             WHERE {$uri_field} IS NOT NULL AND {$uri_field} != '' 
             GROUP BY {$uri_field} 
             ORDER BY hit_count DESC 
             LIMIT 6",
            ARRAY_A
        );
        if (!empty($log_routes) && is_array($log_routes)) {
            foreach ($log_routes as $lr) {
                if (!in_array($lr['request_uri'], $existing_uris, true)) {
                    $top_routes[] = $lr;
                    $existing_uris[] = $lr['request_uri'];
                    if (count($top_routes) >= 6) break;
                }
            }
        }
    }
}

// If no real attacks intercepted yet (fresh or clean state), provide active monitored routes
if (empty($top_routes)) {
    $top_routes = [
        [
            'request_uri'   => '/wp-login.php',
            'hit_count'     => max($aegis_count, 0),
            'sample_reason' => 'AEGIS // Brute-Force & Credential Shield',
            'last_intercept'=> __('Aktiv überwacht', 'vgt-sentinel'),
            'sensor'        => 'AEGIS',
            'sensor_badge'  => 'tag-aegis'
        ],
        [
            'request_uri'   => '/xmlrpc.php',
            'hit_count'     => max($cerberus_count, 0),
            'sample_reason' => 'CERBERUS // Pingback & Amplification Gate',
            'last_intercept'=> __('Aktiv überwacht', 'vgt-sentinel'),
            'sensor'        => 'CERBERUS',
            'sensor_badge'  => 'tag-cerberus'
        ],
        [
            'request_uri'   => '/wp-admin/admin-ajax.php',
            'hit_count'     => max($prom_count, 0),
            'sample_reason' => 'PROMETHEUS // Rate-Decay & Anomaly Analysis',
            'last_intercept'=> __('Aktiv überwacht', 'vgt-sentinel'),
            'sensor'        => 'PROMETHEUS',
            'sensor_badge'  => 'tag-prometheus'
        ],
        [
            'request_uri'   => '/wp-json/wp/v2/users',
            'hit_count'     => max($nemesis_count, 0),
            'sample_reason' => 'NEMESIS // REST Enumeration Tarpit Trap',
            'last_intercept'=> __('Aktiv überwacht', 'vgt-sentinel'),
            'sensor'        => 'NEMESIS',
            'sensor_badge'  => 'tag-nemesis'
        ],
    ];
} else {
    foreach ($top_routes as &$r_item) {
        $reason = (string)($r_item['sample_reason'] ?? '');
        $sensor = 'CERBERUS';
        $badge = 'tag-cerberus';
        if (str_contains($reason, 'AEGIS')) {
            $sensor = 'AEGIS';
            $badge = 'tag-aegis';
        } elseif (str_contains($reason, 'PROMETHEUS')) {
            $sensor = 'PROMETHEUS';
            $badge = 'tag-prometheus';
        } elseif (str_contains($reason, 'NEMESIS')) {
            $sensor = 'NEMESIS';
            $badge = 'tag-nemesis';
        }
        $r_item['sensor'] = $sensor;
        $r_item['sensor_badge'] = $badge;
        $total_route_strikes += (int)$r_item['hit_count'];
    }
    unset($r_item);
}

// Map the first 4 routes to the 4 nodes for the SVG animation
$svg_route_aegis = $top_routes[0]['request_uri'] ?? '/wp-login.php';
$svg_route_prom = $top_routes[1]['request_uri'] ?? '/wp-admin/admin-ajax.php';
$svg_route_nemesis = $top_routes[2]['request_uri'] ?? '/wp-json/wp/v2/users';
$svg_route_cerberus = $top_routes[3]['request_uri'] ?? '/xmlrpc.php';

$wpdb->suppress_errors($suppress);
?>

<div class="vgt-module-container trinity-core">
    
    <!-- HEADER SECTION -->
    <div class="vgt-header">
        <div class="vgt-title-group">
            <h1 class="vgt-glitch-text" data-text="<?php esc_attr_e('TRINITY GRID V8.0.0', 'vgt-sentinel'); ?>"><?php esc_html_e('TRINITY GRID V8.0.0', 'vgt-sentinel'); ?></h1>
            <p class="vgt-subtitle"><?php esc_html_e('Coordinated Real-Time Defense Interlock Matrix & Visual Topology', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-status-badge <?php echo $trinity_active ? 'active' : 'offline'; ?>">
            <span class="pulse-dot"></span> 
            <span><?php echo $trinity_active ? esc_html__('TRINITY: INTERLOCKED', 'vgt-sentinel') : esc_html__('TRINITY: DEGRADED', 'vgt-sentinel'); ?></span>
        </div>
    </div>

    <!-- HUD METRICS ROW (3x2 Balanced Configuration) -->
    <div class="hud-grid">
        <!-- Configuration Cards -->
        <div class="hud-card">
            <div class="card-label"><?php esc_html_e('Scoring-Kopplung', 'vgt-sentinel'); ?></div>
            <div class="card-value">+<?php echo esc_html((string)$prom_waf_penalty); ?></div>
            <div class="card-desc"><?php esc_html_e('Strafpunkte bei WAF-Strike', 'vgt-sentinel'); ?></div>
        </div>
        <div class="hud-card">
            <div class="card-label"><?php esc_html_e('Pre-Lock-Schwelle', 'vgt-sentinel'); ?></div>
            <div class="card-value"><?php echo esc_html((string)$micro_tarpit_score); ?></div>
            <div class="card-desc"><?php esc_html_e('Ressourcenneutrale Eskalationstelemetrie', 'vgt-sentinel'); ?></div>
        </div>
        <div class="hud-card">
            <div class="card-label"><?php esc_html_e('Response-Modus', 'vgt-sentinel'); ?></div>
            <div class="card-value"><?php esc_html_e('BOUNDED', 'vgt-sentinel'); ?></div>
            <div class="card-desc"><?php esc_html_e('Keine blockierten PHP-Worker', 'vgt-sentinel'); ?></div>
        </div>
        
        <!-- Live Performance / Scoreboard Cards -->
        <div class="hud-card stat-card">
            <div class="card-label"><?php esc_html_e('Verdächtige Aktivitäten', 'vgt-sentinel'); ?></div>
            <div class="card-value"><?php echo esc_html((string)$suspicious_events); ?></div>
            <div class="card-desc"><?php esc_html_e('Erfasste Anomalien & Warnungen', 'vgt-sentinel'); ?></div>
        </div>
        <div class="hud-card alert-card">
            <div class="card-label"><?php esc_html_e('Trinity Lockouts', 'vgt-sentinel'); ?></div>
            <div class="card-value"><?php echo esc_html((string)$trinity_blocks); ?></div>
            <div class="card-desc"><?php esc_html_e('Aktive interlock IP-Sperren', 'vgt-sentinel'); ?></div>
        </div>
        <div class="hud-card">
            <div class="card-label"><?php esc_html_e('RAM Cache Coverage', 'vgt-sentinel'); ?></div>
            <div class="card-value" style="font-size: 1.15rem; font-weight: 800;"><?php echo esc_html($ram_status); ?></div>
            <div class="card-desc"><?php esc_html_e('Perimeter Shield RAM check', 'vgt-sentinel'); ?></div>
        </div>
    </div>

    <!-- GRAPHICAL NOC TOPOLOGY SCHEMATIC (INTERLOCK & ATTACK ROUTE RADAR) -->
    <div class="visual-panel">
        <div class="panel-header-flex">
            <div>
                <div class="panel-title-text"><?php esc_html_e('TRINITY REAL-TIME INTERLOCK TOPOLOGY & ATTACK VECTORS', 'vgt-sentinel'); ?></div>
                <div class="vgt-panel-sub"><?php esc_html_e('Visuelle Echtzeit-Darstellung der eingehenden Abfang-Routen in die Trinity-Sensoren und Kernverriegelung.', 'vgt-sentinel'); ?></div>
            </div>
            <div class="panel-badge"><?php echo $trinity_active ? esc_html__('MATRIX ACTIVE (0-LATENCY)', 'vgt-sentinel') : esc_html__('PARTIAL INTERLOCK', 'vgt-sentinel'); ?></div>
        </div>

        <div class="topology-container">
            <svg class="topology-svg" viewBox="0 0 940 340" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <linearGradient id="aegisGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#00bcff" stop-opacity="0.8"/>
                        <stop offset="100%" stop-color="#00ff88" stop-opacity="0.2"/>
                    </linearGradient>
                    <linearGradient id="promGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#a855f7" stop-opacity="0.8"/>
                        <stop offset="100%" stop-color="#00bcff" stop-opacity="0.2"/>
                    </linearGradient>
                    <linearGradient id="nemesisGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.8"/>
                        <stop offset="100%" stop-color="#00ff88" stop-opacity="0.2"/>
                    </linearGradient>
                    <linearGradient id="cerberusGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#ff3366" stop-opacity="0.8"/>
                        <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.2"/>
                    </linearGradient>
                    <linearGradient id="coreGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#00ff88" stop-opacity="0.9"/>
                        <stop offset="100%" stop-color="#00bcff" stop-opacity="0.9"/>
                    </linearGradient>

                    <!-- Attack Trajectory Paths (Ingress into Sensors) -->
                    <path id="pathAttackAegis" d="M 15,75 L 230,75" />
                    <path id="pathAttackProm" d="M 925,75 L 710,75" />
                    <path id="pathAttackNemesis" d="M 15,265 L 230,265" />
                    <path id="pathAttackCerberus" d="M 925,265 L 710,265" />

                    <!-- Interlock Core Paths (Sensor Interconnection) -->
                    <path id="pathCoreAegis" d="M 470,170 L 230,75" />
                    <path id="pathCoreProm" d="M 470,170 L 710,75" />
                    <path id="pathCoreNemesis" d="M 470,170 L 230,265" />
                    <path id="pathCoreCerberus" d="M 470,170 L 710,265" />
                </defs>

                <!-- Ingress Attack Vector Guidance Lines -->
                <line x1="15" y1="75" x2="230" y2="75" class="top-line attack-route-line" stroke="#00bcff" stroke-opacity="0.3" stroke-width="2" />
                <line x1="925" y1="75" x2="710" y2="75" class="top-line attack-route-line" stroke="#a855f7" stroke-opacity="0.3" stroke-width="2" />
                <line x1="15" y1="265" x2="230" y2="265" class="top-line attack-route-line" stroke="#10b981" stroke-opacity="0.3" stroke-width="2" />
                <line x1="925" y1="265" x2="710" y2="265" class="top-line attack-route-line" stroke="#ff3366" stroke-opacity="0.3" stroke-width="2" />

                <!-- Ingress Attack Packets (Streaming into Nodes) -->
                <circle r="3.5" fill="#38bdf8" class="particle">
                    <animateMotion dur="1.7s" repeatCount="indefinite"><mpath href="#pathAttackAegis"/></animateMotion>
                </circle>
                <circle r="3.5" fill="#c084fc" class="particle">
                    <animateMotion dur="2.1s" repeatCount="indefinite"><mpath href="#pathAttackProm"/></animateMotion>
                </circle>
                <circle r="3.5" fill="#34d399" class="particle">
                    <animateMotion dur="2.4s" repeatCount="indefinite"><mpath href="#pathAttackNemesis"/></animateMotion>
                </circle>
                <circle r="3.5" fill="#ff3366" class="particle">
                    <animateMotion dur="1.5s" repeatCount="indefinite"><mpath href="#pathAttackCerberus"/></animateMotion>
                </circle>

                <!-- Interlock Core Connection Lines -->
                <line x1="470" y1="170" x2="230" y2="75" class="top-line <?php echo $aegis_on ? 'pulse-line-cyan' : ''; ?>" />
                <line x1="470" y1="170" x2="710" y2="75" class="top-line <?php echo $prom_on ? 'pulse-line-purple' : ''; ?>" />
                <line x1="470" y1="170" x2="230" y2="265" class="top-line <?php echo $nemesis_on ? 'pulse-line-emerald' : ''; ?>" />
                <line x1="470" y1="170" x2="710" y2="265" class="top-line <?php echo $cerberus_on ? 'pulse-line-red' : ''; ?>" />

                <!-- Interlock Core Particles -->
                <?php if ($trinity_active): ?>
                <circle r="4" fill="#00bcff" class="particle">
                    <animateMotion dur="2.4s" repeatCount="indefinite"><mpath href="#pathCoreAegis"/></animateMotion>
                </circle>
                <circle r="4" fill="#a855f7" class="particle">
                    <animateMotion dur="2.1s" repeatCount="indefinite"><mpath href="#pathCoreProm"/></animateMotion>
                </circle>
                <circle r="4" fill="#10b981" class="particle">
                    <animateMotion dur="2.7s" repeatCount="indefinite"><mpath href="#pathCoreNemesis"/></animateMotion>
                </circle>
                <circle r="4" fill="#ff3366" class="particle">
                    <animateMotion dur="1.9s" repeatCount="indefinite"><mpath href="#pathCoreCerberus"/></animateMotion>
                </circle>
                <?php endif; ?>

                <!-- CENTER NODE: TRINITY INTERLOCK CORE -->
                <g class="topology-node core-node">
                    <circle cx="470" cy="170" r="50" fill="url(#coreGlow)" opacity="0.15" class="core-pulse-bg" />
                    <circle cx="470" cy="170" r="38" fill="rgba(8,16,28,0.96)" stroke="#00ff88" stroke-width="2.5" class="core-circle" />
                    <text x="470" y="166" text-anchor="middle" fill="#00ff88" font-size="11" font-weight="900" letter-spacing="1">TRINITY</text>
                    <text x="470" y="180" text-anchor="middle" fill="#64748b" font-size="8" font-weight="700">CORE MATRIX</text>
                </g>

                <!-- NODE 1: AEGIS (WAF DPI) -->
                <g class="topology-node node-aegis <?php echo $aegis_on ? 'online' : 'offline'; ?>">
                    <?php if ($aegis_on): ?><circle cx="230" cy="75" r="32" fill="none" stroke="#00bcff" stroke-width="1.5" class="sensor-sonar-ring" /><?php endif; ?>
                    <circle cx="230" cy="75" r="32" fill="rgba(10,20,35,0.92)" stroke="<?php echo $aegis_on ? '#00bcff' : '#475569'; ?>" stroke-width="2" />
                    <text x="230" y="71" text-anchor="middle" fill="<?php echo $aegis_on ? '#00bcff' : '#94a3b8'; ?>" font-size="10" font-weight="800">AEGIS</text>
                    <text x="230" y="84" text-anchor="middle" fill="#64748b" font-size="8">DPI / AI WAF</text>
                </g>

                <!-- NODE 2: PROMETHEUS (TELEMETRY / SWARM) -->
                <g class="topology-node node-prom <?php echo $prom_on ? 'online' : 'offline'; ?>">
                    <?php if ($prom_on): ?><circle cx="710" cy="75" r="32" fill="none" stroke="#a855f7" stroke-width="1.5" class="sensor-sonar-ring" /><?php endif; ?>
                    <circle cx="710" cy="75" r="32" fill="rgba(20,10,35,0.92)" stroke="<?php echo $prom_on ? '#a855f7' : '#475569'; ?>" stroke-width="2" />
                    <text x="710" y="71" text-anchor="middle" fill="<?php echo $prom_on ? '#c084fc' : '#94a3b8'; ?>" font-size="8.5" font-weight="800" letter-spacing="-0.15">PROMETHEUS</text>
                    <text x="710" y="84" text-anchor="middle" fill="#64748b" font-size="8">SWARM / DECAY</text>
                </g>

                <!-- NODE 3: NEMESIS (DECEPTION GRID) -->
                <g class="topology-node node-nemesis <?php echo $nemesis_on ? 'online' : 'offline'; ?>">
                    <?php if ($nemesis_on): ?><circle cx="230" cy="265" r="32" fill="none" stroke="#10b981" stroke-width="1.5" class="sensor-sonar-ring" /><?php endif; ?>
                    <circle cx="230" cy="265" r="32" fill="rgba(10,30,20,0.92)" stroke="<?php echo $nemesis_on ? '#10b981' : '#475569'; ?>" stroke-width="2" />
                    <text x="230" y="261" text-anchor="middle" fill="<?php echo $nemesis_on ? '#34d399' : '#94a3b8'; ?>" font-size="10" font-weight="800">NEMESIS</text>
                    <text x="230" y="274" text-anchor="middle" fill="#64748b" font-size="8">TARPIT GRID</text>
                </g>

                <!-- NODE 4: CERBERUS (PERIMETER GATE) -->
                <g class="topology-node node-cerberus <?php echo $cerberus_on ? 'online' : 'offline'; ?>">
                    <?php if ($cerberus_on): ?><circle cx="710" cy="265" r="32" fill="none" stroke="#ff3366" stroke-width="1.5" class="sensor-sonar-ring" /><?php endif; ?>
                    <circle cx="710" cy="265" r="32" fill="rgba(35,10,20,0.92)" stroke="<?php echo $cerberus_on ? '#ff3366' : '#475569'; ?>" stroke-width="2" />
                    <text x="710" y="261" text-anchor="middle" fill="<?php echo $cerberus_on ? '#ff3366' : '#94a3b8'; ?>" font-size="10" font-weight="800">CERBERUS</text>
                    <text x="710" y="274" text-anchor="middle" fill="#64748b" font-size="8">PRE-PHP GATE</text>
                </g>

                <!-- ATTACK ROUTE BADGE CHIPS (SVG OVERLAYS) -->
                <g class="route-node-chip">
                    <rect x="15" y="48" width="145" height="26" rx="6" fill="rgba(6,18,32,0.94)" stroke="#00bcff" stroke-width="1.2" />
                    <circle cx="27" cy="61" r="3" fill="#00bcff" />
                    <text x="36" y="64" fill="#38bdf8" font-size="9" font-family="monospace" font-weight="700"><?php echo esc_html(substr($svg_route_aegis, 0, 16)); ?></text>
                </g>
                <g class="route-node-chip">
                    <rect x="780" y="48" width="145" height="26" rx="6" fill="rgba(24,10,32,0.94)" stroke="#a855f7" stroke-width="1.2" />
                    <circle cx="792" cy="61" r="3" fill="#a855f7" />
                    <text x="801" y="64" fill="#c084fc" font-size="9" font-family="monospace" font-weight="700"><?php echo esc_html(substr($svg_route_prom, 0, 16)); ?></text>
                </g>
                <g class="route-node-chip">
                    <rect x="15" y="274" width="145" height="26" rx="6" fill="rgba(8,24,18,0.94)" stroke="#10b981" stroke-width="1.2" />
                    <circle cx="27" cy="287" r="3" fill="#10b981" />
                    <text x="36" y="290" fill="#34d399" font-size="9" font-family="monospace" font-weight="700"><?php echo esc_html(substr($svg_route_nemesis, 0, 16)); ?></text>
                </g>
                <g class="route-node-chip">
                    <rect x="780" y="274" width="145" height="26" rx="6" fill="rgba(32,10,20,0.94)" stroke="#ff3366" stroke-width="1.2" />
                    <circle cx="792" cy="287" r="3" fill="#ff3366" />
                    <text x="801" y="290" fill="#fb7185" font-size="9" font-family="monospace" font-weight="700"><?php echo esc_html(substr($svg_route_cerberus, 0, 16)); ?></text>
                </g>
            </svg>
        </div>
    </div>

    <!-- TOP INTERCEPTED ATTACK ROUTES & VECTOR TELEMETRY -->
    <div class="vgt-routes-panel">
        <div class="panel-header-flex">
            <div>
                <div class="panel-title-text"><?php esc_html_e('TOP ABFANG-ROUTEN & ANGRIFFS-VEKTOREN (ECHTZEIT-INTERCEPTION)', 'vgt-sentinel'); ?></div>
                <div class="vgt-panel-sub"><?php esc_html_e('Die am häufigsten attackierten Endpunkte, analysiert und isoliert durch die Trinity-Sensoren.', 'vgt-sentinel'); ?></div>
            </div>
            <div class="panel-badge" style="background: rgba(56, 189, 248, 0.1); color: var(--vis-cyan); border-color: rgba(56, 189, 248, 0.3);">
                <span class="pulse-dot"></span> <?php esc_html_e('AKTIVE VEKTOR-ANALYSE', 'vgt-sentinel'); ?>
            </div>
        </div>

        <div class="vgt-routes-grid">
            <?php foreach ($top_routes as $idx => $route_item): 
                $r_uri = (string)($route_item['request_uri'] ?? '/');
                $r_hits = (int)($route_item['hit_count'] ?? 0);
                $r_sensor = (string)($route_item['sensor'] ?? 'CERBERUS');
                $r_badge = (string)($route_item['sensor_badge'] ?? 'tag-cerberus');
                $r_reason = (string)($route_item['sample_reason'] ?? 'Perimeter Defense');
                $r_time = (string)($route_item['last_intercept'] ?? '-');
                $pct_of_total = $total_route_strikes > 0 ? round(($r_hits / $total_route_strikes) * 100) : 0;
            ?>
            <div class="vgt-route-card">
                <div class="vgt-route-card-head">
                    <span class="vgt-route-index">#0<?php echo (int)($idx + 1); ?></span>
                    <span class="tag-badge <?php echo esc_attr($r_badge); ?>"><?php echo esc_html($r_sensor); ?></span>
                </div>
                <div class="vgt-route-uri-box">
                    <code class="vgt-route-uri" title="<?php echo esc_attr($r_uri); ?>"><?php echo esc_html($r_uri); ?></code>
                </div>
                <div class="vgt-route-vector-reason"><?php echo esc_html($r_reason); ?></div>
                <div class="vgt-route-stat-row">
                    <span class="vgt-route-hits-label"><?php esc_html_e('Abgefangene Angriffe:', 'vgt-sentinel'); ?></span>
                    <strong class="vgt-route-hits-val text-mono"><?php echo esc_html(number_format_i18n($r_hits)); ?></strong>
                </div>
                <div class="vgt-route-bar-track">
                    <div class="vgt-route-bar-fill <?php echo esc_attr($r_badge); ?>" style="width: <?php echo esc_attr((string)max(12, $pct_of_total)); ?>%;"></div>
                </div>
                <div class="vgt-route-card-footer">
                    <span class="vgt-route-status-pill">
                        <span class="pulse-dot" style="width:5px; height:5px;"></span> <?php esc_html_e('AUTOMATISCH ISOLIERT', 'vgt-sentinel'); ?>
                    </span>
                    <span class="vgt-route-time text-mono"><?php echo esc_html($r_time); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- GRAPHICAL THREAT VECTOR DISTRIBUTION BAR -->
    <div class="vector-panel">
        <div class="panel-title-text"><?php esc_html_e('INTERLOCK THREAT VECTOR DISTRIBUTION', 'vgt-sentinel'); ?></div>
        
        <div class="vector-progress-bar">
            <div class="segment segment-aegis" style="width: <?php echo esc_attr((string)$aegis_pct); ?>%;" title="AEGIS DPI: <?php echo esc_attr((string)$aegis_count); ?> intercepts"></div>
            <div class="segment segment-prom" style="width: <?php echo esc_attr((string)$prom_pct); ?>%;" title="PROMETHEUS Swarm: <?php echo esc_attr((string)$prom_count); ?> strikes"></div>
            <div class="segment segment-nemesis" style="width: <?php echo esc_attr((string)$nemesis_pct); ?>%;" title="NEMESIS Tarpit: <?php echo esc_attr((string)$nemesis_count); ?> traps"></div>
            <div class="segment segment-cerberus" style="width: <?php echo esc_attr((string)$cerberus_pct); ?>%;" title="CERBERUS Gate: <?php echo esc_attr((string)$cerberus_count); ?> lockouts"></div>
        </div>

        <div class="vector-legend">
            <div class="legend-item">
                <span class="dot dot-aegis"></span>
                <span class="label">AEGIS (DPI/AI)</span>
                <span class="val"><?php echo esc_html((string)$aegis_pct); ?>% (<?php echo esc_html((string)$aegis_count); ?>)</span>
            </div>
            <div class="legend-item">
                <span class="dot dot-prom"></span>
                <span class="label">PROMETHEUS (Swarm)</span>
                <span class="val"><?php echo esc_html((string)$prom_pct); ?>% (<?php echo esc_html((string)$prom_count); ?>)</span>
            </div>
            <div class="legend-item">
                <span class="dot dot-nemesis"></span>
                <span class="label">NEMESIS (Tarpit)</span>
                <span class="val"><?php echo esc_html((string)$nemesis_pct); ?>% (<?php echo esc_html((string)$nemesis_count); ?>)</span>
            </div>
            <div class="legend-item">
                <span class="dot dot-cerberus"></span>
                <span class="label">CERBERUS (Pre-PHP)</span>
                <span class="val"><?php echo esc_html((string)$cerberus_pct); ?>% (<?php echo esc_html((string)$cerberus_count); ?>)</span>
            </div>
        </div>
    </div>

    <!-- LIVE TRINITY INTERCEPT FEED STREAM -->
    <div class="intercept-panel">
        <div class="panel-header-flex">
            <div class="panel-title-text"><?php esc_html_e('LIVE TRINITY INTERCEPT STREAM', 'vgt-sentinel'); ?></div>
            <div class="stream-badge"><span class="pulse-dot"></span> <?php esc_html_e('REALTIME AUDIT FEED', 'vgt-sentinel'); ?></div>
        </div>

        <?php if (!empty($recent_intercepts)): ?>
        <div class="intercept-table-wrapper">
            <table class="intercept-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Zeitpunkt', 'vgt-sentinel'); ?></th>
                        <th><?php esc_html_e('Ziel IP', 'vgt-sentinel'); ?></th>
                        <th><?php esc_html_e('Interlock Modul', 'vgt-sentinel'); ?></th>
                        <th><?php esc_html_e('Vektor / VGT Grund', 'vgt-sentinel'); ?></th>
                        <th><?php esc_html_e('Aktion', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_intercepts as $feed): ?>
                    <tr>
                        <td class="time-cell"><?php echo esc_html((string)$feed['time']); ?></td>
                        <td><code class="ip-code"><?php echo esc_html((string)$feed['ip']); ?></code></td>
                        <td>
                            <span class="tag-badge tag-<?php echo strtolower($feed['module']); ?>">
                                <?php echo esc_html((string)$feed['module']); ?>
                            </span>
                        </td>
                        <td class="reason-cell">
                            <span class="reason-text" title="<?php echo esc_attr($feed['reason']); ?>">
                                <?php echo esc_html(substr($feed['reason'], 0, 75)); ?>
                            </span>
                            <span class="uri-sub"><?php echo esc_html(substr($feed['uri'], 0, 50)); ?></span>
                        </td>
                        <td><span class="status-action"><?php echo esc_html($feed['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-stream">
            <div class="empty-icon">🛡️</div>
            <div class="empty-text"><?php esc_html_e('Keine aktiven Trinity Lockouts verzeichnet. System befindet sich im Ruhezustand (0 Infiltrationen).', 'vgt-sentinel'); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- SETTINGS PANEL -->
    <div class="control-panel">
        <div class="panel-title"><?php esc_html_e('Trinity Matrix Tuning & Configurations', 'vgt-sentinel'); ?></div>
        
        <!-- Toggle interlock -->
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label"><?php esc_html_e('omega Interlock-Verbindung aktivieren', 'vgt-sentinel'); ?></div>
                <div class="setting-desc"><?php esc_html_e('Schaltet die intelligente Vernetzung zwischen WAF (Aegis), Verhaltenskontrolle (Prometheus), Klebefalle (Nemesis) und Perimeter-Sperre (Cerberus) scharf.', 'vgt-sentinel'); ?></div>
            </div>
            <div class="setting-input-wrap">
                <label class="vgt-toggle">
                    <input type="checkbox" name="vis_trinity_config[interlock_enabled]" id="interlock_enabled" value="1" <?php checked($interlock_enabled, true); ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <!-- WAF Penalty Score -->
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label"><?php esc_html_e('AEGIS WAF-Strike Penalty', 'vgt-sentinel'); ?></div>
                <div class="setting-desc"><?php esc_html_e('Zusätzlicher Anstieg des Bedrohungsscores in Prometheus, wenn die WAF einen Request blockiert.', 'vgt-sentinel'); ?></div>
            </div>
            <div class="setting-input">
                <input type="number" step="0.5" min="0" max="100" name="vis_trinity_config[prom_waf_penalty]" value="<?php echo esc_attr((string)$prom_waf_penalty); ?>" class="vgt-input" style="width:100%; border: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.4); color:#fff; border-radius:4px; padding:6px 12px; font-family:inherit;">
            </div>
        </div>

        <!-- Pre-lock telemetry score -->
        <div class="setting-row">
            <div class="setting-info">
                <div class="setting-label"><?php esc_html_e('Pre-Lock Telemetrieschwelle', 'vgt-sentinel'); ?></div>
                <div class="setting-desc"><?php esc_html_e('Verhaltens-Score, ab dem Trinity eine erhöhte Eskalationsstufe protokolliert, ohne PHP-Worker zu blockieren.', 'vgt-sentinel'); ?></div>
            </div>
            <div class="setting-input">
                <input type="number" step="0.5" min="10" max="200" name="vis_trinity_config[micro_tarpit_score]" value="<?php echo esc_attr((string)$micro_tarpit_score); ?>" class="vgt-input" style="width:100%; border: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.4); color:#fff; border-radius:4px; padding:6px 12px; font-family:inherit;">
            </div>
        </div>

        <div style="margin-top:20px; display:flex; justify-content:flex-end;">
            <?php wp_nonce_field('vis_save_config'); ?>
            <input type="hidden" name="vis_context" value="trinity">
            <input type="hidden" name="xdr_section" value="grid">
            <button type="submit" name="vis_save_config" value="1" class="vis-btn vis-btn-primary"><?php esc_html_e('Grid-Konfiguration speichern', 'vgt-sentinel'); ?></button>
        </div>
    </div>

</div>
