<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

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

$wpdb->suppress_errors($suppress);
?>

<style>
    <?php 
    $css_path = __DIR__ . '/trinity/style.css';
    if (is_readable($css_path)) {
        echo file_get_contents($css_path);
    }
    ?>
</style>

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

    <!-- GRAPHICAL NOC TOPOLOGY SCHEMATIC (INTERLOCK RADAR) -->
    <div class="visual-panel">
        <div class="panel-header-flex">
            <div class="panel-title-text"><?php esc_html_e('TRINITY REAL-TIME INTERLOCK TOPOLOGY', 'vgt-sentinel'); ?></div>
            <div class="panel-badge"><?php echo $trinity_active ? esc_html__('MATRIX ACTIVE (0-LATENCY)', 'vgt-sentinel') : esc_html__('PARTIAL INTERLOCK', 'vgt-sentinel'); ?></div>
        </div>

        <div class="topology-container">
            <svg class="topology-svg" viewBox="0 0 800 320" preserveAspectRatio="xMidYMid meet">
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

                    <path id="pathCoreAegis" d="M 400,160 L 150,70" />
                    <path id="pathCoreProm" d="M 400,160 L 650,70" />
                    <path id="pathCoreNemesis" d="M 400,160 L 150,250" />
                    <path id="pathCoreCerberus" d="M 400,160 L 650,250" />
                </defs>

                <!-- Connection Lines -->
                <line x1="400" y1="160" x2="150" y2="70" class="top-line <?php echo $aegis_on ? 'pulse-line-cyan' : ''; ?>" />
                <line x1="400" y1="160" x2="650" y2="70" class="top-line <?php echo $prom_on ? 'pulse-line-purple' : ''; ?>" />
                <line x1="400" y1="160" x2="150" y2="250" class="top-line <?php echo $nemesis_on ? 'pulse-line-emerald' : ''; ?>" />
                <line x1="400" y1="160" x2="650" y2="250" class="top-line <?php echo $cerberus_on ? 'pulse-line-red' : ''; ?>" />

                <!-- Stream Signal Particles -->
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
                    <circle cx="400" cy="160" r="48" fill="url(#coreGlow)" opacity="0.15" class="core-pulse-bg" />
                    <circle cx="400" cy="160" r="36" fill="rgba(8,16,28,0.95)" stroke="#00ff88" stroke-width="2.5" class="core-circle" />
                    <text x="400" y="156" text-anchor="middle" fill="#00ff88" font-size="11" font-weight="900" letter-spacing="1">TRINITY</text>
                    <text x="400" y="170" text-anchor="middle" fill="#64748b" font-size="8" font-weight="700">CORE MATRIX</text>
                </g>

                <!-- NODE 1: AEGIS (WAF DPI) -->
                <g class="topology-node node-aegis <?php echo $aegis_on ? 'online' : 'offline'; ?>">
                    <circle cx="150" cy="70" r="32" fill="rgba(10,20,35,0.9)" stroke="<?php echo $aegis_on ? '#00bcff' : '#475569'; ?>" stroke-width="2" />
                    <text x="150" y="66" text-anchor="middle" fill="<?php echo $aegis_on ? '#00bcff' : '#94a3b8'; ?>" font-size="10" font-weight="800">AEGIS</text>
                    <text x="150" y="79" text-anchor="middle" fill="#64748b" font-size="8">DPI / AI WAF</text>
                </g>

                <!-- NODE 2: PROMETHEUS (TELEMETRY / SWARM) -->
                <g class="topology-node node-prom <?php echo $prom_on ? 'online' : 'offline'; ?>">
                    <circle cx="650" cy="70" r="32" fill="rgba(20,10,35,0.9)" stroke="<?php echo $prom_on ? '#a855f7' : '#475569'; ?>" stroke-width="2" />
                    <text x="650" y="66" text-anchor="middle" fill="<?php echo $prom_on ? '#c084fc' : '#94a3b8'; ?>" font-size="8.5" font-weight="800" letter-spacing="-0.15">PROMETHEUS</text>
                    <text x="650" y="79" text-anchor="middle" fill="#64748b" font-size="8">SWARM / DECAY</text>
                </g>

                <!-- NODE 3: NEMESIS (DECEPTION GRID) -->
                <g class="topology-node node-nemesis <?php echo $nemesis_on ? 'online' : 'offline'; ?>">
                    <circle cx="150" cy="250" r="32" fill="rgba(10,30,20,0.9)" stroke="<?php echo $nemesis_on ? '#10b981' : '#475569'; ?>" stroke-width="2" />
                    <text x="150" y="246" text-anchor="middle" fill="<?php echo $nemesis_on ? '#34d399' : '#94a3b8'; ?>" font-size="10" font-weight="800">NEMESIS</text>
                    <text x="150" y="259" text-anchor="middle" fill="#64748b" font-size="8">TARPIT GRID</text>
                </g>

                <!-- NODE 4: CERBERUS (PERIMETER GATE) -->
                <g class="topology-node node-cerberus <?php echo $cerberus_on ? 'online' : 'offline'; ?>">
                    <circle cx="650" cy="250" r="32" fill="rgba(35,10,20,0.9)" stroke="<?php echo $cerberus_on ? '#ff3366' : '#475569'; ?>" stroke-width="2" />
                    <text x="650" y="246" text-anchor="middle" fill="<?php echo $cerberus_on ? '#ff3366' : '#94a3b8'; ?>" font-size="10" font-weight="800">CERBERUS</text>
                    <text x="650" y="259" text-anchor="middle" fill="#64748b" font-size="8">PRE-PHP GATE</text>
                </g>
            </svg>
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

    </div>

</div>
