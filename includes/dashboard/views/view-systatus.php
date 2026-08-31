<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: SYSTEM STATUS (NOC)
 * MODULE: NETWORK OPERATIONS CENTER
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 
global $wpdb;

// =========================================================================================
// 1. DATEN AGGREGATION & LOGIK CORE (VGT SUPREME)
// =========================================================================================
$opt = get_option('vis_config', []);
if (!is_array($opt)) $opt = [];

// --- SENTINEL METRICS ---
$table_bans = $wpdb->prefix . (defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_apex_bans');
$table_logs = $wpdb->prefix . (defined('VIS_TABLE_LOGS') ? VIS_TABLE_LOGS : 'vis_omega_logs');

$sentinel_bans = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_bans}");
$sentinel_logs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_logs} WHERE module IN ('AEGIS', 'CERBERUS', 'AIRLOCK', 'GHOST_TRAP', 'TITAN', 'CHRONOS', 'MORPHEUS', 'GORGON')");
$sentinel_total = $sentinel_bans + $sentinel_logs;

// --- PROMETHEUS & ZEUS METRICS ---
$prometheus_strikes = 0;
// 1. ZEUS WAF Interventions
$prometheus_strikes += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_logs} WHERE module = 'ZEUS'");
// 2. Prometheus Predictive Strikes (Autarke Telemetrie)
$prom_tables = [
    $wpdb->prefix . 'vgt_prometheus_logs',
    $wpdb->prefix . 'vis_prometheus_logs',
    $wpdb->prefix . 'prometheus_logs'
];
foreach ($prom_tables as $ptable) {
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $ptable)) === $ptable) {
        $prometheus_strikes += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$ptable}");
    }
}

// --- DEEP INSIGHTS ---
// Ghost Trap Status
$ghost_manifest = get_option('vis_ghost_trap_manifest', []);
$ghost_nodes = is_array($ghost_manifest) ? count($ghost_manifest) : 0;

// Chronos Status
$chronos_next = wp_next_scheduled('vis_periodic_scan_event');
$chronos_text = $chronos_next ? wp_date('H:i:s', $chronos_next) . ' UTC' : __('Standby', 'vgt-sentinel');

// Airlock Status
$airlock_limit = $opt['airlock_max_mb'] ?? 5;

// VLP Status
$vlp_assets = 0;
if (class_exists('VLP_Asset_Library')) {
    $vlp_assets = count(\VLP_Asset_Library::get_matrix());
}

// =========================================================================================
// 2. MODUL MATRIX (Dynamische NOC Auswertung)
// =========================================================================================
$modules = [
    'aegis' => [
        'name'   => __('AEGIS WAF', 'vgt-sentinel'),
        'desc'   => __('L1 RegEx Payload Filter & Interceptor', 'vgt-sentinel'),
        'active' => !empty($opt['aegis_enabled']),
        'color'  => '#ef4444',
        'meta'   => __('L1 Ingress Defense', 'vgt-sentinel'),
        'icon'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>'
    ],
    'airlock' => [
        'name'   => __('AIRLOCK', 'vgt-sentinel'),
        'desc'   => __('Secure Ingress & Payload Obfuscation', 'vgt-sentinel'),
        'active' => !isset($opt['airlock_enabled']) || !empty($opt['airlock_enabled']),
        'color'  => '#3b82f6',
        'meta'   => sprintf(__('%dMB Hard Limit', 'vgt-sentinel'), (int)$airlock_limit),
        'icon'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line>'
    ],
    'ghost_trap' => [
        'name'   => __('GHOST TRAP', 'vgt-sentinel'),
        'desc'   => __('L7 Deception Honeypot Network', 'vgt-sentinel'),
        'active' => !empty($opt['ghost_trap_enabled']),
        'alert'  => (!empty($opt['ghost_trap_enabled']) && $ghost_nodes === 0),
        'color'  => '#a855f7',
        'meta'   => sprintf(_n('%d Node Deployed', '%d Nodes Deployed', $ghost_nodes, 'vgt-sentinel'), $ghost_nodes),
        'icon'   => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path><path d="M12 12v9"></path><path d="M8 17l4 4 4-4"></path>'
    ],
    'chronos' => [
        'name'   => __('CHRONOS', 'vgt-sentinel'),
        'desc'   => __('Autonomous Scanner Scheduler', 'vgt-sentinel'),
        'active' => !isset($opt['chronos_enabled']) || !empty($opt['chronos_enabled']),
        'alert'  => (!isset($opt['chronos_enabled']) || !empty($opt['chronos_enabled'])) && !$chronos_next,
        'color'  => '#14b8a6',
        'meta'   => sprintf(__('Next: %s', 'vgt-sentinel'), $chronos_text),
        'icon'   => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'
    ],
    'gorgon' => [
        'name'   => __('GORGON', 'vgt-sentinel'),
        'desc'   => __('Neural Intelligence Grid Uplink', 'vgt-sentinel'),
        'active' => !empty($opt['gorgon_enabled']),
        'color'  => '#f59e0b',
        'meta'   => __('Global Swarm Intelligence', 'vgt-sentinel'),
        'icon'   => '<path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>'
    ],
    'morpheus' => [
        'name'   => __('MORPHEUS', 'vgt-sentinel'),
        'desc'   => __('Zero-Trust Hypervisor Sandbox', 'vgt-sentinel'),
        'active' => !empty($opt['morpheus_enabled']),
        'color'  => '#ec4899',
        'meta'   => __('DB/State Isolation', 'vgt-sentinel'),
        'icon'   => '<polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline>'
    ],
    'titan' => [
        'name'   => __('TITAN', 'vgt-sentinel'),
        'desc'   => __('Kernel Hardening & Camouflage', 'vgt-sentinel'),
        'active' => !empty($opt['titan_enabled']),
        'color'  => '#64748b',
        'meta'   => __('System Core Obfuscation', 'vgt-sentinel'),
        'icon'   => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>'
    ],
    'throneguard' => [
        'name'   => __('THRONEGUARD', 'vgt-sentinel'),
        'desc'   => __('Master Role Segregation & Superkey Lockdown', 'vgt-sentinel'),
        'active' => class_exists('VIS_Throne_Guard') && (!empty($opt['throneguard_enabled']) || !empty($opt['throneguard_harden_admin'])),
        'color'  => '#a855f7',
        'meta'   => (!empty($opt['throneguard_harden_admin'])) ? __('Admin Hardened', 'vgt-sentinel') : __('Master Ready', 'vgt-sentinel'),
        'icon'   => '<path d="M3 7l4 4 5-7 5 7 4-4-2 12H5L3 7z"></path><line x1="5" y1="22" x2="19" y2="22"></line>'
    ],
    'loginpager' => [
        'name'   => __('LOGINPAGER', 'vgt-sentinel'),
        'desc'   => __('Custom Auth Gateway & Visual Shield', 'vgt-sentinel'),
        'active' => class_exists('VIS_LoginPager') && !empty($opt['loginpager_enabled']),
        'color'  => '#06b6d4',
        'meta'   => __('Branded Auth UI', 'vgt-sentinel'),
        'icon'   => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>'
    ],
    'vlp' => [
        'name'   => __('SHADOW-NET', 'vgt-sentinel'),
        'desc'   => __('VisionLegalPro Asset Downloader', 'vgt-sentinel'),
        'active' => class_exists('VisionLegalPro_Core'),
        'color'  => '#10b981',
        'meta'   => sprintf(_n('%d Asset Secured', '%d Assets Secured', $vlp_assets, 'vgt-sentinel'), $vlp_assets),
        'icon'   => '<path d="M21 2l-2 2m-7.61 a5.5 5.5 0 1 1 -7.778 7.778 5.5 5.5 0 0 1 7.777 -7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>'
    ]
];

$active_count = 0;
foreach ($modules as $m) {
    if ($m['active']) $active_count++;
}
$health_status = ($active_count >= 4) ? __('OPTIMAL', 'vgt-sentinel') : __('DEGRADED', 'vgt-sentinel');
$health_color  = ($active_count >= 4) ? '#10b981' : '#f59e0b';

$security_checks = class_exists('VIS_Security_Health') ? VIS_Security_Health::run() : [];
$security_score = class_exists('VIS_Security_Health') ? VIS_Security_Health::score() : 0;
$security_failures = array_values(array_filter($security_checks, static fn(array $check): bool => $check['status'] !== 'pass'));
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $systatus_css_path = __DIR__ . '/systatus/style.css';
    if (is_readable($systatus_css_path)) {
        echo file_get_contents($systatus_css_path);
    }
    ?>
</style>

<div class="vgt-noc-ui">

    <!-- HERO HEADER -->
    <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="color: #fff; font-size: 28px; font-weight: 800; margin: 0 0 8px 0; letter-spacing: -0.5px;"><?php esc_html_e('SYSTEM STATUS', 'vgt-sentinel'); ?></h1>
            <p style="color: var(--vgt-text-muted); margin: 0; font-size: 14px;"><?php esc_html_e('Omega Protocol Network Operations Center. Live Telemetry & Module Diagnostics.', 'vgt-sentinel'); ?></p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 11px; color: var(--vgt-text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;"><?php esc_html_e('Core Health', 'vgt-sentinel'); ?></div>
            <div style="display: inline-flex; align-items: center; gap: 8px; font-family: 'Fira Code', monospace; font-size: 16px; font-weight: 700; color: <?php echo esc_attr($health_color); ?>;">
                <svg style="width:16px; height:16px; fill:currentColor;" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm-1 11.59l-3.3-3.3a1 1 0 111.42-1.42l1.88 1.88 4.3-4.3a1 1 0 011.42 1.42l-5 5a1 1 0 01-1.42 0z"/></svg>
                <?php printf(esc_html__('%1$s (%2$d/%3$d)', 'vgt-sentinel'), esc_html($health_status), (int)$active_count, count($modules)); ?>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 30px; background: rgba(15,23,42,0.58); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 22px; border-top: 3px solid <?php echo $security_failures === [] ? '#10b981' : '#f59e0b'; ?>;">
        <div style="display:flex; justify-content:space-between; gap:20px; align-items:flex-start; margin-bottom:16px;">
            <div>
                <div style="font-size: 13px; font-weight: 800; letter-spacing: 1px; color:#fff; text-transform: uppercase;"><?php esc_html_e('Security Audit Harness', 'vgt-sentinel'); ?></div>
                <div style="font-size: 12px; color: var(--vgt-text-muted); margin-top: 5px;"><?php esc_html_e('Static invariant scan for Sentinel hardening rules.', 'vgt-sentinel'); ?></div>
            </div>
            <div style="font-family:monospace; font-size: 18px; font-weight: 800; color: <?php echo $security_failures === [] ? '#10b981' : '#f59e0b'; ?>;"><?php echo esc_html((string)$security_score); ?>%</div>
        </div>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
            <?php foreach ($security_checks as $check): ?>
                <div style="border:1px solid rgba(255,255,255,0.08); border-radius:8px; padding:10px 12px; background:rgba(2,6,23,0.45);">
                    <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                        <span style="font-size:12px; color:#e2e8f0; font-weight:700;"><?php echo esc_html($check['label']); ?></span>
                        <span style="font-size:10px; font-family:monospace; color:<?php echo $check['status'] === 'pass' ? '#10b981' : '#ef4444'; ?>;"><?php echo esc_html(strtoupper($check['status'])); ?></span>
                    </div>
                    <?php if ($check['status'] !== 'pass'): ?>
                        <div style="font-size:10px; color:#94a3b8; margin-top:5px; word-break:break-word;"><?php echo esc_html($check['detail']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SECURITY SCORING MATRIX -->
    <?php
    $score = 0;
    $score_details = [];

    // Zeus (20%)
    $zeus_config = get_option('vis_zeus_config', []);
    $zeus_active = !empty($zeus_config);
    if ($zeus_active) {
        $score += 20;
        $score_details[] = ['name' => 'Zeus Defender', 'active' => true, 'weight' => 20];
    } else {
        $score_details[] = ['name' => 'Zeus Defender', 'active' => false, 'weight' => 20];
    }

    // Aegis (15%)
    if (!empty($opt['aegis_enabled'])) {
        $score += 15;
        $score_details[] = ['name' => 'Aegis WAF', 'active' => true, 'weight' => 15];
    } else {
        $score_details[] = ['name' => 'Aegis WAF', 'active' => false, 'weight' => 15];
    }

    // ThroneGuard (15%)
    $throne_status = class_exists('VIS_Throne_Guard') ? VIS_Throne_Guard::status() : [];
    $throne_active = !empty($throne_status['harden_admin']) || !empty($throne_status['is_master']) || !empty($opt['throneguard_enabled']);
    if ($throne_active) {
        $score += 15;
        $score_details[] = ['name' => 'ThroneGuard Master', 'active' => true, 'weight' => 15];
    } else {
        $score_details[] = ['name' => 'ThroneGuard Master', 'active' => false, 'weight' => 15];
    }

    // Prometheus (15%)
    if (!empty($opt['prometheus_enabled'])) {
        $score += 15;
        $score_details[] = ['name' => 'Prometheus AI', 'active' => true, 'weight' => 15];
    } else {
        $score_details[] = ['name' => 'Prometheus AI', 'active' => false, 'weight' => 15];
    }

    // Titan (15%)
    if (!empty($opt['titan_enabled'])) {
        $score += 15;
        $score_details[] = ['name' => 'Titan Hardening', 'active' => true, 'weight' => 15];
    } else {
        $score_details[] = ['name' => 'Titan Hardening', 'active' => false, 'weight' => 15];
    }

    // Hades (10%)
    if (!empty($opt['hades_enabled'])) {
        $score += 10;
        $score_details[] = ['name' => 'Hades Stealth', 'active' => true, 'weight' => 10];
    } else {
        $score_details[] = ['name' => 'Hades Stealth', 'active' => false, 'weight' => 10];
    }

    // Cerberus (5%)
    if (class_exists('VIS_Cerberus')) {
        $score += 5;
        $score_details[] = ['name' => 'Cerberus Perimeter', 'active' => true, 'weight' => 5];
    } else {
        $score_details[] = ['name' => 'Cerberus Perimeter', 'active' => false, 'weight' => 5];
    }

    // Airlock (5%)
    if (!isset($opt['airlock_enabled']) || !empty($opt['airlock_enabled'])) {
        $score += 5;
        $score_details[] = ['name' => 'Airlock Ingress', 'active' => true, 'weight' => 5];
    } else {
        $score_details[] = ['name' => 'Airlock Ingress', 'active' => false, 'weight' => 5];
    }

    // Scoring Meta
    if ($score >= 90) {
        $score_status = __('MAXIMALER SCHUTZ', 'vgt-sentinel');
        $score_desc = __('Herausragende Abwehrbereitschaft. Alle kritischen Sicherheitsmodule und Perimeter-Schilde sind aktiv. Die Seite läuft unter maximalem Schutz.', 'vgt-sentinel');
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

    <div class="vgt-score-card" style="border-top-color: <?php echo esc_attr($score_color); ?>;">
        <div class="vgt-score-left">
            <div class="vgt-score-circle-container">
                <svg viewBox="0 0 160 160" style="width: 150px; height: 150px; display: block;">
                    <circle class="vgt-score-circle-bg" cx="80" cy="80" r="70"></circle>
                    <circle class="vgt-score-circle-val" cx="80" cy="80" r="70" 
                            style="stroke: <?php echo esc_attr($score_color); ?>; stroke-dasharray: 439; stroke-dashoffset: <?php echo esc_attr((string)$dashoffset); ?>;"></circle>
                </svg>
                <div class="vgt-score-number" style="text-shadow: 0 0 15px <?php echo esc_attr($score_color); ?>50;"><?php echo esc_html((string)$score); ?>%</div>
            </div>
        </div>
        <div class="vgt-score-right">
            <div class="vgt-score-title" style="color: <?php echo esc_attr($score_color); ?>;"><?php echo esc_html($score_status); ?></div>
            <div class="vgt-score-desc"><?php echo esc_html($score_desc); ?></div>
            <div class="vgt-score-grid">
                <?php foreach ($score_details as $detail): ?>
                    <div class="vgt-score-item <?php echo $detail['active'] ? 'active' : 'inactive'; ?>">
                        <span class="vgt-status-dot" style="background: <?php echo $detail['active'] ? '#10b981' : '#ef4444'; ?>; box-shadow: 0 0 8px <?php echo $detail['active'] ? '#10b981' : '#ef4444'; ?>;"></span>
                        <span><?php echo esc_html($detail['name']); ?></span>
                        <span style="margin-left: auto; opacity: 0.6; font-size: 10px;"><?php echo esc_html((string)$detail['weight']); ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <h2 style="font-size: 14px; color: var(--vgt-text-muted); text-transform: uppercase; letter-spacing: 2px; margin: 40px 0 20px 0; border-bottom: 1px solid var(--vgt-border); padding-bottom: 10px;"><?php esc_html_e('Module Integrity Matrix', 'vgt-sentinel'); ?></h2>

    <!-- MODULE GRID -->
    <div class="vgt-module-grid">
        <?php foreach ($modules as $id => $m): ?>
            <div class="vgt-module-card">
                <div class="vgt-module-header">
                    <div class="vgt-module-icon-box" style="color: <?php echo esc_attr($m['color']); ?>; border: 1px solid <?php echo esc_attr($m['color']); ?>40;">
                        <svg class="vgt-module-icon" viewBox="0 0 24 24">
                            <?php 
                            echo wp_kses($m['icon'], [
                                'path'     => ['d' => true],
                                'circle'   => ['cx' => true, 'cy' => true, 'r' => true],
                                'line'     => ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true],
                                'polyline' => ['points' => true],
                                'rect'     => ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true],
                                'polygon'  => ['points' => true]
                            ]); 
                            ?>
                        </svg>
                    </div>
                    
                    <?php if ($m['active'] && isset($m['alert']) && $m['alert']): ?>
                        <div class="vgt-status-badge vgt-status-attention">
                            <svg style="width:10px;height:10px;fill:currentColor;" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 12a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm1-5a1 1 0 01-2 0V5a1 1 0 012 0v4z"/></svg>
                            <?php esc_html_e('ATTENTION', 'vgt-sentinel'); ?>
                        </div>
                    <?php elseif ($m['active']): ?>
                        <div class="vgt-status-badge vgt-status-active">
                            <svg style="width:10px;height:10px;fill:currentColor;" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm-1 11.59l-3.3-3.3a1 1 0 111.42-1.42l1.88 1.88 4.3-4.3a1 1 0 011.42 1.42l-5 5a1 1 0 01-1.42 0z"/></svg>
                            <?php esc_html_e('ACTIVE', 'vgt-sentinel'); ?>
                        </div>
                    <?php else: ?>
                        <div class="vgt-status-badge vgt-status-offline">
                            <svg style="width:10px;height:10px;fill:none;stroke:currentColor;stroke-width:2;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <?php esc_html_e('OFFLINE', 'vgt-sentinel'); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 class="vgt-module-title"><?php echo esc_html($m['name']); ?></h3>
                <p class="vgt-module-desc"><?php echo esc_html($m['desc']); ?></p>

                <div class="vgt-module-footer">
                    <span class="vgt-module-meta"><?php echo esc_html($m['meta']); ?></span>
                    <?php if ($m['active']): ?>
                        <span style="width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr($m['color']); ?>;box-shadow: 0 0 8px <?php echo esc_attr($m['color']); ?>;"></span>
                    <?php else: ?>
                        <span style="width:8px;height:8px;border-radius:50%;background:var(--vgt-border);"></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
