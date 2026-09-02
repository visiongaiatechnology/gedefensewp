<?php 
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. LOGIK CORE & CONFIG FETCH (STRICT 1:1)
// =========================================================================================

// STATUS-CHECK: Plugin aktiv?
$aios_active = class_exists('AIO_WP_Security');

// Wir laden beide Konfigurations-Arrays von AIOS
$db_configs = get_option('aio_wp_security_configs', []);
$fw_configs = get_option('aiowps_firewall_config', []);

// Zugriff auf Live-Objekte, falls verfügbar
global $aio_wp_security, $aiowps_firewall_config;

/**
 * HELPER: Intelligente Wertermittlung
 * Prüft nacheinander: Globales Objekt -> Firewall-Option -> Main-Option
 */
function vis_get_aios_value($key, $db_configs, $fw_configs) {
    global $aio_wp_security, $aiowps_firewall_config;

    // 1. Check Firewall Object
    if (isset($aiowps_firewall_config) && method_exists($aiowps_firewall_config, 'get_value')) {
        $val = $aiowps_firewall_config->get_value($key);
        if ($val !== null && $val !== '') return $val;
    }

    // 2. Check Firewall Option Array (Backup)
    if (isset($fw_configs[$key])) return $fw_configs[$key];

    // 3. Check Main Security Object
    if (isset($aio_wp_security->configs) && method_exists($aio_wp_security->configs, 'get_value')) {
        $val = $aio_wp_security->configs->get_value($key);
        if ($val !== null && $val !== '') return $val;
    }

    // 4. Check Main Option Array (Last Fallback)
    return isset($db_configs[$key]) ? $db_configs[$key] : '0';
}

/**
 * HELPER: Status-Badge Rendering (Angepasst auf VGT APEX CSS Klassen)
 */
function vis_render_status($key, $db_configs, $fw_configs, $inverted = false) {
    $val = vis_get_aios_value($key, $db_configs, $fw_configs);
    
    // Normalisierung: '1', 'on', true, 'yes' gelten als aktiv
    $is_active = ($val == '1' || $val === 'on' || $val === true || $val === 'yes');
    
    if ($inverted) $is_active = !$is_active;

    if ($is_active) {
        return '<span class="vgt-badge vgt-badge-active"><span class="dashicons dashicons-yes"></span> SECURE</span>';
    } else {
        $debug = (is_bool($val) ? ($val ? 'TRUE' : 'FALSE') : (empty($val) ? '0' : $val));
        return '<span class="vgt-badge vgt-badge-alert" title="Value: '.$debug.'"><span class="dashicons dashicons-warning"></span> ACTION REQUIRED</span>';
    }
}

// OMEGA CONFIGURATION MAPPING (AIOS 5.x/6.x Deep-Links & Keys)
$audit_matrix = [
    'firewall' => [
        'title' => 'PERIMETER FIREWALL',
        'icon'  => 'dashicons-shield',
        'items' => [
            [
                'label' => 'Basic Firewall',
                'desc'  => 'Schützt .htaccess und verbietet Zugriff auf sensible Systemdateien.',
                'key'   => 'aiowps_enable_basic_firewall',
                'link'  => 'admin.php?page=aiowpsec_firewall&tab=basic-firewall-settings'
            ],
            [
                'label' => 'XML-RPC Protection',
                'desc'  => 'Blockiert XML-RPC Pingbacks (Haupteinfallstor für DDoS).',
                'key'   => 'aiowps_enable_pingback_firewall', 
                'link'  => 'admin.php?page=aiowpsec_firewall&tab=basic-firewall-settings'
            ],
            [
                'label' => '6G Blacklist Rules',
                'desc'  => 'Erweiterte Firewall-Regeln gegen bekannte Exploits.',
                'key'   => 'aiowps_enable_6g_firewall', 
                'link'  => 'admin.php?page=aiowpsec_firewall&tab=6g-firewall-rules'
            ],
            [
                'label' => 'Fake Googlebots',
                'desc'  => 'Erkennt Bots, die sich fälschlich als Google ausgeben.',
                'key'   => 'aiowps_block_fake_googlebots',
                'link'  => 'admin.php?page=aiowpsec_firewall&tab=php-firewall-rules&subtab=internet-bots'
            ]
        ]
    ],
    'brute' => [
        'title' => 'BRUTE FORCE DEFENSE',
        'icon'  => 'dashicons-lock',
        'items' => [
            [
                'label' => 'Rename Login Page',
                'desc'  => 'Versteckt /wp-admin und /wp-login.php vor Angreifern.',
                'key'   => 'aiowps_enable_rename_login_page',
                'link'  => 'admin.php?page=aiowpsec_brute_force&tab=rename-login'
            ],
            [
                'label' => 'Cookie Prevention',
                'desc'  => 'Erlaubt Login nur, wenn ein magischer Cookie gesetzt ist.',
                'key'   => 'aiowps_enable_brute_force_attack_prevention',
                'link'  => 'admin.php?page=aiowpsec_brute_force&tab=cookie-based-brute-force-prevention'
            ],
            [
                'label' => '404 Lockout',
                'desc'  => 'Sperrt IPs, die zu viele 404-Fehler generieren.',
                'key'   => 'aiowps_enable_404_IP_lockout', 
                'link'  => 'admin.php?page=aiowpsec_brute_force&tab=404-detection'
            ]
        ]
    ],
    'user' => [
        'title' => 'USER & LOGIN SECURITY',
        'icon'  => 'dashicons-admin-users',
        'items' => [
            [
                'label' => 'Login Lockdown',
                'desc'  => 'Sperrt IP nach X Fehlversuchen (Essentiell!).',
                'key'   => 'aiowps_enable_login_lockdown', 
                'link'  => 'admin.php?page=aiowpsec_user_security&tab=login-lockout'
            ],
            [
                'label' => 'Force Logout',
                'desc'  => 'Automatischer Logout nach Inaktivität.',
                'key'   => 'aiowps_enable_forced_logout',
                'link'  => 'admin.php?page=aiowpsec_user_security&tab=force-logout'
            ]
        ]
    ],
    'files' => [
        'title' => 'FILESYSTEM & SPAM',
        'icon'  => 'dashicons-category',
        'items' => [
            [
                'label' => 'Disable File Editing',
                'desc'  => 'Verhindert PHP-Code-Edits über das WP-Dashboard.',
                'key'   => 'aiowps_disable_file_editing',
                'link'  => 'admin.php?page=aiowpsec_filesystem&tab=file-protection&subtab=php-file-editing'
            ],
            [
                'label' => 'Prevent Hotlinking',
                'desc'  => 'Verhindert Traffic-Klau durch direkte Bildeinbindung.',
                'key'   => 'aiowps_prevent_hotlinking',
                'link'  => 'admin.php?page=aiowpsec_filesystem&tab=file-protection&subtab=prevent-hotlinks'
            ],
            [
                'label' => 'Comment Spam Block',
                'desc'  => 'Blockiert Kommentare von bekannten Spam-IPs.',
                'key'   => 'aiowps_enable_spambot_detecting', // KEY FIX: detecting statt blocking
                'link'  => 'admin.php?page=aiowpsec_spam&tab=comment-spam'
            ]
        ]
    ]
];

// SYNERGY CALCULATION
$total_checks = 0;
$passed_checks = 0;
foreach($audit_matrix as $group) {
    foreach($group['items'] as $item) {
        $total_checks++;
        $val = vis_get_aios_value($item['key'], $db_configs, $fw_configs);
        if ($val == '1' || $val === 'on' || $val === true || $val === 'yes') $passed_checks++;
    }
}
$synergy_score = $total_checks > 0 ? round(($passed_checks / $total_checks) * 100) : 0;

// Pulse classes based on connection state
$connection_pulse = $aios_active ? 'vgt-is-active' : 'vgt-is-alert';
$connection_color = $aios_active ? '#10b981' : '#ef4444';
?>

<!-- =========================================================================================
     2. VGT APEX STYLES
     ========================================================================================= -->
<style>
    .vgt-apex-ui {
        --vgt-bg-base: #020617;
        --vgt-bg-panel: rgba(15, 23, 42, 0.5);
        --vgt-border: rgba(255, 255, 255, 0.08);
        --vgt-border-hover: rgba(255, 255, 255, 0.2);
        --vgt-text-main: #f8fafc;
        --vgt-text-dim: #94a3b8;
        --vgt-text-muted: #64748b;
        --vgt-neon-purple: #a855f7;
        --vgt-neon-green: #10b981;
        --vgt-neon-red: #ef4444;
        --vgt-neon-blue: #3b82f6;
        --vgt-neon-orange: #f59e0b;
        
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--vgt-text-main);
        box-sizing: border-box;
        margin-top: 20px;
        margin-right: 20px;
    }

    .vgt-apex-ui * { box-sizing: border-box; }

    /* CORE PANELS */
    .vgt-glass-panel {
        background: var(--vgt-bg-panel);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--vgt-border);
        border-radius: 12px;
        box-shadow: 0 4px 24px -1px rgba(0, 0, 0, 0.2);
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
    }

    /* HEADER */
    .vgt-module-header {
        padding: 20px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(90deg, rgba(15, 23, 42, 0.9) 0%, rgba(2, 6, 23, 0.8) 100%);
        border-left: 4px solid var(--vgt-neon-orange);
    }
    
    .vgt-module-title { display: flex; align-items: center; gap: 15px; }
    .vgt-module-title h2 { margin: 0; font-size: 18px; font-weight: 700; color: #fff; letter-spacing: 0.5px; display:flex; align-items:center; gap:12px;}
    
    .vgt-status-pulse { width: 8px; height: 8px; border-radius: 50%; box-shadow: 0 0 10px currentColor; }
    .vgt-is-active .vgt-status-pulse { animation: vgt-pulse 2s infinite; color: var(--vgt-neon-green); }
    .vgt-is-alert .vgt-status-pulse { animation: vgt-pulse-alert 1s infinite; color: var(--vgt-neon-red); }
    .vgt-is-standby .vgt-status-pulse { color: var(--vgt-text-muted); }

    /* STATE DISPLAYS */
    .vgt-state-clean { text-align: center; padding: 60px 20px; }
    .vgt-state-clean h3 { color: #fff; font-size: 24px; letter-spacing: 1px; margin-bottom: 12px; }
    .vgt-state-clean p { color: var(--vgt-text-dim); max-width: 500px; margin: 0 auto; line-height: 1.6; }

    /* DATA TABLES */
    .vgt-table-header { padding: 16px 20px; border-bottom: 1px solid var(--vgt-border); display: flex; align-items: center; background: rgba(0,0,0,0.2); }
    .vgt-table-header h3 { margin: 0; font-size: 13px; font-weight: 700; letter-spacing: 1px; color: #fff; display: flex; align-items: center; gap: 10px;}
    
    .vgt-data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
    .vgt-data-table td { padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: top; }
    .vgt-data-table tbody tr { transition: background 0.2s ease; }
    .vgt-data-table tbody tr:hover { background: rgba(255,255,255,0.02); }
    .vgt-data-table tbody tr:last-child td { border-bottom: none; }

    /* BUTTONS & BADGES */
    .vgt-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        padding: 6px 12px; border-radius: 6px; font-size: 10px; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase; cursor: pointer; transition: all 0.2s ease; text-decoration: none; border: none;
    }
    .vgt-btn-ghost { background: rgba(255,255,255,0.03); color: var(--vgt-text-main); border: 1px solid var(--vgt-border); }
    .vgt-btn-ghost:hover { background: rgba(255,255,255,0.08); border-color: var(--vgt-border-hover); }
    
    .vgt-btn-danger-ghost { background: transparent; color: var(--vgt-neon-red); border: 1px solid rgba(239, 68, 68, 0.4); }
    .vgt-btn-danger-ghost:hover { background: rgba(239, 68, 68, 0.1); border-color: var(--vgt-neon-red); box-shadow: 0 0 10px rgba(239, 68, 68, 0.2); }

    .vgt-badge {
        padding: 4px 10px; border-radius: 20px; font-size: 9px; font-weight: 800; letter-spacing: 1px;
        text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; white-space: nowrap;
    }
    .vgt-badge-active { background: rgba(16, 185, 129, 0.1); color: var(--vgt-neon-green); border-color: rgba(16, 185, 129, 0.3); }
    .vgt-badge-alert { background: rgba(239, 68, 68, 0.1); color: var(--vgt-neon-red); border-color: rgba(239, 68, 68, 0.3); }
    .vgt-badge-neutral { background: rgba(255, 255, 255, 0.05); color: var(--vgt-text-dim); border-color: var(--vgt-border); }

    /* ANIMATIONS */
    @keyframes vgt-pulse { 0% { box-shadow: 0 0 0 0 currentColor; } 70% { box-shadow: 0 0 0 6px rgba(0,0,0,0); } 100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); } }
    @keyframes vgt-pulse-alert { 0% { box-shadow: 0 0 0 0 currentColor; } 70% { box-shadow: 0 0 0 10px rgba(0,0,0,0); } 100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); } }
</style>

<div class="vgt-apex-ui">

    <!-- =========================================================================================
         3. DEFENSE SYNERGY ANALYZER (KPI PANEL)
         ========================================================================================= -->
    <div class="vgt-glass-panel" style="padding: 24px; border-left: 4px solid var(--vgt-neon-blue); background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(15, 23, 42, 0.5) 100%);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px;">
            <div>
                <h3 style="color:var(--vgt-neon-blue); margin:0; font-size:16px; font-weight:700; letter-spacing:1px; display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-analytics" style="font-size:20px; width:20px; height:20px;"></span>
                    DEFENSE SYNERGY ANALYZER
                </h3>
                <p style="font-size:12px; color:var(--vgt-text-dim); margin:4px 0 0 30px;">Kombinierte Konfigurations-Analyse von GeDefense WP Core & AIOS Perimeter.</p>
            </div>
            <div style="text-align:right;">
                <div style="font-size:36px; font-weight:900; color:#fff; line-height:1; font-family:monospace;"><?php echo (int)$synergy_score; ?>%</div>
                <div style="font-size:10px; font-weight:800; color:var(--vgt-neon-green); letter-spacing:2px; margin-top:4px;">OMEGA STATUS</div>
            </div>
        </div>
        <div style="background:rgba(255,255,255,0.05); height:6px; border-radius:3px; overflow:hidden; position:relative;">
            <div style="background:var(--vgt-neon-blue); width:<?php echo (int)$synergy_score; ?>%; height:100%; box-shadow: 0 0 15px var(--vgt-neon-blue); transition: width 1s ease-in-out;"></div>
        </div>
    </div>

    <!-- =========================================================================================
         4. MODULE HEADER
         ========================================================================================= -->
    <div class="vgt-glass-panel vgt-module-header">
        <div class="vgt-module-title">
            <div style="background:rgba(245, 158, 11, 0.1); padding:10px; border-radius:8px; border:1px solid rgba(245, 158, 11, 0.3);">
                <span class="dashicons dashicons-networking" style="color:var(--vgt-neon-orange); font-size:24px; width:24px; height:24px;"></span>
            </div>
            <div>
                <h2>
                    AIOS PERIMETER AUDIT
                    <span class="vgt-badge vgt-badge-neutral" style="border-radius:4px;">BRIDGE UPLINK</span>
                </h2>
                <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace; display:flex; align-items:center; gap:8px;">
                    Connection Status:
                    <span class="<?php echo esc_attr((string)$connection_pulse); ?>" style="display:inline-flex; align-items:center; gap:6px;">
                        <span class="vgt-status-pulse"></span>
                        <strong style="color:<?php echo esc_attr((string)$connection_color); ?>; letter-spacing:0.5px;">
                            <?php echo $aios_active ? 'ACTIVE & LINKED' : 'DISCONNECTED'; ?>
                        </strong>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if($aios_active): ?>
        <a href="admin.php?page=aiowpsec" target="_blank" class="vgt-btn vgt-btn-ghost">
            <span class="dashicons dashicons-external"></span> FULL AIOS DASHBOARD
        </a>
        <?php endif; ?>
    </div>

    <!-- =========================================================================================
         5. STATE PANELS & BENTO GRID
         ========================================================================================= -->
    <?php if (!$aios_active): ?>
        <!-- DISCONNECTED STATE -->
        <div class="vgt-glass-panel vgt-state-clean" style="border-color:var(--vgt-border);">
            <span class="dashicons dashicons-warning" style="font-size:64px; width:64px; height:64px; color:var(--vgt-text-muted); margin-bottom:20px; display:inline-block; opacity:0.5;"></span>
            <h3>UPLINK FEHLGESCHLAGEN</h3>
            <p>Das Modul "All In One WP Security & Firewall" wurde nicht im System gefunden. Bitte installieren und aktivieren Sie das Plugin, um die Perimeter-Überwachung zu initialisieren.</p>
        </div>
    <?php else: ?>
        
        <!-- AUDIT MATRIX GRID -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap:24px; margin-bottom: 24px;">
            <?php foreach($audit_matrix as $group): ?>
            
            <div class="vgt-glass-panel" style="margin-bottom:0;">
                <div class="vgt-table-header">
                    <h3>
                        <span class="dashicons <?php echo esc_attr((string)$group['icon']); ?>" style="color:var(--vgt-neon-orange); margin-right:6px;"></span> 
                        <?php echo esc_html((string)$group['title']); ?>
                    </h3>
                </div>
                
                <table class="vgt-data-table">
                    <tbody>
                        <?php foreach($group['items'] as $item): ?>
                        <tr>
                            <td>
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                                    <strong style="color:#e2e8f0; font-size:13px; letter-spacing:0.5px;"><?php echo esc_html((string)$item['label']); ?></strong>
                                    <?php echo wp_kses_post(vis_render_status($item['key'], $db_configs, $fw_configs)); ?>
                                </div>
                                
                                <div style="font-size:11px; color:var(--vgt-text-dim); line-height:1.5; margin-bottom:12px;">
                                    <?php echo esc_html((string)$item['desc']); ?>
                                </div>
                                
                                <?php 
                                    $val = vis_get_aios_value($item['key'], $db_configs, $fw_configs);
                                    $is_active = ($val == '1' || $val === 'on' || $val === true || $val === 'yes');
                                    
                                    if(!$is_active): 
                                ?>
                                    <a href="<?php echo esc_url(admin_url((string)$item['link'])); ?>" target="_blank" rel="noopener noreferrer" class="vgt-btn vgt-btn-danger-ghost">
                                        <span class="dashicons dashicons-admin-tools" style="font-size:12px; margin-right:4px;"></span> FIX IT
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
