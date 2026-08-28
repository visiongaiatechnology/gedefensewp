<?php
declare(strict_types=1);
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * View: NEMESIS Dashboard (Deception & Counterintelligence)
 * Status: PLATIN VGT STATUS (Hardened UI & i18n)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['nemesis_enabled']);
// VGT COMPLIANCE: Status des offensiven Active Strike Modus
$is_active_strike = !empty($opt['nemesis_active_strike']);

$table_logs = $wpdb->prefix . 'vis_nemesis_logs';

// --- ZERO-COST REAL DATA AGGREGATION ---
// $active_tarpits ist volatil (60 Sekunden TTL). Wir benötigen historische Persistenz.
$active_tarpits = (int) wp_cache_get( 'vgt_active_tarpits' );
$tarpit_total   = 0;
$canaries_count = 0;
$poison_count   = 0;
$real_logs      = [];

// VGT SUPREME FIX: Direkte MySQL Tabellen-Verifikation & Deterministische Aggregation
$suppress = $wpdb->suppress_errors(true);
if ($wpdb->get_var("SHOW TABLES LIKE '{$table_logs}'") === $table_logs) {
    
    // 1. Zähle Tarpits/Honeypots (Historisch)
     $tarpit_total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND (type = 'TARPIT' OR (type = 'CANARY' AND details LIKE %s))", 
        'NEMESIS', '%Honeypot%'
    ));
    
    // 2. Zähle reine Canary-Token Auslösungen (Isoliert von Honeypots)
    $canaries_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND type = 'CANARY' AND details NOT LIKE %s", 
        'NEMESIS', '%Honeypot%'
    ));
    
    // 3. Zähle Poison/Sabotage-Events
    $poison_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table_logs} WHERE module = %s AND type IN ('POISON', 'SABOTAGE', 'STRIKE')", 
        'NEMESIS'
    ));

    // Fallback nur, wenn das System strukturelle Anomalien aufweist
    $total_logs = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$table_logs} WHERE module = %s", 'NEMESIS'));
    if ($total_logs > 0 && $canaries_count === 0 && $poison_count === 0 && $tarpit_total === 0) {
        $poison_count = $total_logs;
    }

    // 4. Terminal Logs laden (Die letzten 25 Events)
    $real_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_logs} WHERE module = %s ORDER BY timestamp DESC LIMIT 25", 
        'NEMESIS'
    ));
}
$wpdb->suppress_errors($suppress);

// UI Status Logik (VGT SUPREME: Dynamic Strike Coupling)
$badge_text = __('SHIELD OFFLINE', 'vgt-sentinel');
$badge_class = 'offline';
if ($is_enabled) {
    if ($is_active_strike) {
        $badge_text = __('ACTIVE STRIKE: ARMED', 'vgt-sentinel');
        $badge_class = 'armed';
    } else {
        $badge_text = __('DECEPTION MATRIX: ENGAGED', 'vgt-sentinel');
        $badge_class = 'active';
    }
}
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION
     ========================================================================================= -->
<style>
    <?php 
    $nemesis_css_path = __DIR__ . '/nemesis/style.css';
    if (is_readable($nemesis_css_path)) {
        echo file_get_contents($nemesis_css_path);
    }
    ?>
</style>

<div class="vgt-module-container nemesis-core">
    
    <!-- HEADER SECTION -->
    <div class="vgt-header">
        <div class="vgt-title-group">
            <h1 class="vgt-glitch-text nemesis-glitch" data-text="<?php echo esc_attr__('NEMESIS ENGINE', 'vgt-sentinel'); ?>"><?php esc_html_e('NEMESIS ENGINE', 'vgt-sentinel'); ?></h1>
            <p class="vgt-subtitle"><?php esc_html_e('Advanced Deception, Tarpitting & Counterintelligence Protocol', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-status-badge <?php echo esc_attr($badge_class); ?>" id="nemesis-main-badge">
            <span class="pulse-dot"></span> 
            <span id="badge-text-nemesis"><?php echo esc_html($badge_text); ?></span>
        </div>
    </div>

    <!-- CONTROL PANELS GRID -->
    <div class="vgt-control-grid">
        
        <!-- ABSOLUTE BULLETPROOF CONFIG TOGGLE (LEGAL DEFAULT) -->
        <div class="vgt-master-switch-panel" style="grid-column: 1 / -1;">
            <div class="panel-info">
                <h3><?php esc_html_e('Nemesis Protocol Authorization', 'vgt-sentinel'); ?></h3>
                <p><?php echo wp_kses_post(__('Aktiviert die asymmetrische <strong>Verteidigungs- und Täuschungsmatrix (100% Legal)</strong>. Das System leitet Angreifer in langsame Endlosschleifen, markiert Content-Diebstahl und liefert Scrapern mutierte Fake-Daten aus, um deren Datenbanken wertlos zu machen.', 'vgt-sentinel')); ?></p>
            </div>
            <div class="panel-action">
                <label class="vgt-pure-switch nemesis-switch" id="toggle-container-nemesis">
                    <input type="checkbox" name="vis_config[nemesis_enabled]" id="nemesis_enabled" value="1" <?php checked($is_enabled, true); ?>>
                    <span class="vgt-pure-slider"></span>
                    <div class="switch-label" id="toggle-label-nemesis">
                        <?php echo $is_enabled ? esc_html__('ENGAGED', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?>
                    </div>
                </label>
            </div>
        </div>
        
    </div>

    <div id="nemesis-dynamic-content" class="<?php echo $is_enabled ? '' : 'vgt-disabled'; ?>">

        <!-- HIGH LEVEL KPI METRICS -->
        <div class="vgt-kpi-matrix">
            <div class="vgt-kpi-box" style="border-color: rgba(188, 19, 254, 0.2);">
                <div class="kpi-icon" style="color:var(--vgt-nemesis);">
                    <svg class="vgt-icon" style="width:28px; height:28px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-tarpit">
                        <?php echo esc_html(number_format_i18n($tarpit_total)); ?> 
                        <span style="font-size:0.5em;color:#666;">/ <?php echo esc_html((string)$active_tarpits); ?> <?php esc_html_e('ACT', 'vgt-sentinel'); ?></span>
                    </span>
                    <span class="kpi-label"><?php esc_html_e('Tarpit Strikes (All-Time)', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-slow' : ''; ?>"></div>
            </div>
            <div class="vgt-kpi-box" style="border-color: rgba(0, 242, 255, 0.2);">
                <div class="kpi-icon" style="background:rgba(0, 242, 255, 0.1); border-color:rgba(0, 242, 255, 0.3); color:#00f2ff;">
                    <svg class="vgt-icon" style="width:28px; height:28px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="2" r="2"></circle></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-canary" style="color:#00f2ff;"><?php echo esc_html(number_format_i18n($canaries_count)); ?></span>
                    <span class="kpi-label"><?php esc_html_e('Canary Traps Triggered', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-fast' : ''; ?>" style="background: linear-gradient(90deg, transparent, #00f2ff, transparent);"></div>
            </div>
            <div class="vgt-kpi-box" style="border-color: rgba(255, 0, 60, 0.2);">
                <div class="kpi-icon" style="background:rgba(255, 0, 60, 0.1); border-color:rgba(255, 0, 60, 0.3); color:#ff003c;">
                    <svg class="vgt-icon" style="width:28px; height:28px;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-value" id="kpi-poison" style="color:#ff003c;"><?php echo esc_html(number_format_i18n($poison_count)); ?></span>
                    <span class="kpi-label"><?php esc_html_e('Scrapers Poisoned / Sabotaged', 'vgt-sentinel'); ?></span>
                </div>
                <div class="kpi-sparkline <?php echo $is_enabled ? 'pulse-medium' : ''; ?>" style="background: linear-gradient(90deg, transparent, #ff003c, transparent);"></div>
            </div>
        </div>

        <!-- TACTICAL COUNTERMEASURES GRID -->
        <div class="vgt-grid">
            
            <!-- TARPIT CARD -->
            <div class="vgt-card vgt-glass-card nemesis-card">
                <div class="card-header">
                    <div class="icon-wrapper">
                        <svg class="vgt-icon" style="width:20px; height:20px;" viewBox="0 0 24 24"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path><path d="M3 22v-6h6"></path><path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path></svg>
                    </div>
                    <h3><?php esc_html_e('Tarpit Mode', 'vgt-sentinel'); ?></h3>
                    <div class="node-status <?php echo $is_enabled ? 'online' : ''; ?>"></div>
                </div>
                <p class="card-desc"><?php esc_html_e('Simuliert kritische Schwachstellen (`.env`, `wp-config`). Legal Defense liefert extrem langsame Fake-Hashes aus, um gegnerische Threads an den Server zu binden (Ressourcen-Erschöpfung durch Zeit).', 'vgt-sentinel'); ?></p>
                <div class="tech-specs">
                    <span><svg class="vgt-icon" style="width:16px; height:16px; color:var(--vgt-nemesis);" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> <?php esc_html_e('Self-DDoS OS-Lock (Max 3 Threads)', 'vgt-sentinel'); ?></span>
                    <span id="spec-strike-mode-t">
                        <?php 
                        if ($is_active_strike) {
                            echo '<svg class="vgt-icon" style="width:16px; height:16px; color:var(--vgt-danger);" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> ' . esc_html__('Mode: Kinetische Sabotage', 'vgt-sentinel'); 
                        } else {
                            echo '<svg class="vgt-icon" style="width:16px; height:16px; color:var(--vgt-nemesis);" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> ' . esc_html__('Mode: Passive Delay', 'vgt-sentinel'); 
                        }
                        ?>
                    </span>
                </div>
                
                <div class="strike-explanation-box <?php echo $is_active_strike ? 'active' : ''; ?>">
                    <h4>
                        <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        <?php esc_html_e('Kinetische Sabotage Aktiv', 'vgt-sentinel'); ?>
                    </h4>
                    <p><?php echo wp_kses_post(__('<strong>GZIP-Bombing</strong> führt zu RAM-Überlauf beim Angreifer. <strong>Terminal-Sabotage</strong> injiziert ANSI-Codes, die das Terminal des Hackers unleserlich machen.', 'vgt-sentinel')); ?></p>
                </div>
            </div>

            <!-- CANARY CARD -->
            <div class="vgt-card vgt-glass-card nemesis-card">
                <div class="card-header">
                    <div class="icon-wrapper" style="color:#00f2ff; background:rgba(0, 242, 255, 0.15);">
                        <svg class="vgt-icon" style="width:20px; height:20px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </div>
                    <h3><?php esc_html_e('Cryptographic Canary', 'vgt-sentinel'); ?></h3>
                    <div class="node-status <?php echo $is_enabled ? 'online' : ''; ?>" style="background:#00f2ff; box-shadow: 0 0 12px #00f2ff;"></div>
                </div>
                <p class="card-desc"><?php esc_html_e('Verdeckte Dom-Injektion kryptographisch signierter Tracking-Tokens (HMAC-SHA256). Ermöglicht präzise Forensik und Data-Leak-Attribution bei unautorisiertem Scraping.', 'vgt-sentinel'); ?></p>
                <div class="tech-specs">
                    <span><svg class="vgt-icon" style="width:16px; height:16px; color:#00f2ff;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> <?php esc_html_e('HMAC-SHA256 Signature', 'vgt-sentinel'); ?></span>
                    <span><svg class="vgt-icon" style="width:16px; height:16px; color:#00f2ff;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> <?php esc_html_e('Invisible DOM Injection', 'vgt-sentinel'); ?></span>
                </div>

                <div class="strike-explanation-box <?php echo $is_active_strike ? 'active' : ''; ?>">
                    <h4>
                        <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        <?php esc_html_e('Cookie Bombing Aktiv', 'vgt-sentinel'); ?>
                    </h4>
                    <p><?php echo wp_kses_post(__('<strong>State Exhaustion:</strong> Flutet den Scraper mit hunderten gigantischen Session-Cookies. Führt bei automatisierten Bots zum sofortigen Out-of-Memory Absturz.', 'vgt-sentinel')); ?></p>
                </div>
            </div>

            <!-- POISON CARD -->
            <div class="vgt-card vgt-glass-card nemesis-card">
                <div class="card-header">
                    <div class="icon-wrapper" style="color:#ff003c; background:rgba(255, 0, 60, 0.15);">
                        <svg class="vgt-icon" style="width:20px; height:20px;" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <h3><?php esc_html_e('Polymorphic Poisoning', 'vgt-sentinel'); ?></h3>
                    <div class="node-status <?php echo $is_enabled ? 'online' : ''; ?>" style="background:#ff003c; box-shadow: 0 0 12px #ff003c;"></div>
                </div>
                <p class="card-desc"><?php esc_html_e('Mutiert echten Content On-The-Fly bei erkannten Bot-Signaturen. Verhindert das Auslesen valider E-Mail-Adressen durch dynamische Injektion von 3-Byte Hex-Entropie.', 'vgt-sentinel'); ?></p>
                <div class="tech-specs">
                    <span><svg class="vgt-icon" style="width:16px; height:16px; color:#ff003c;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> <?php esc_html_e('Entropy Injection (Hex Mutation)', 'vgt-sentinel'); ?></span>
                    <span><svg class="vgt-icon" style="width:16px; height:16px; color:#ff003c;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> <?php esc_html_e('DB-Corruption Routine', 'vgt-sentinel'); ?></span>
                </div>

                <div class="strike-explanation-box <?php echo $is_active_strike ? 'active' : ''; ?>">
                    <h4>
                        <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        <?php esc_html_e('Aggressive DB-Corruption Aktiv', 'vgt-sentinel'); ?>
                    </h4>
                    <p><?php echo wp_kses_post(__('<strong>Database Overloader:</strong> Generiert bei jedem Aufruf on-the-fly 50 hochrealistische Honeypot-Adressen. Dies maximiert die Datenbank-Kosten des Angreifers ins Unermessliche.', 'vgt-sentinel')); ?></p>
                </div>
            </div>

        </div>

        <!-- TACTICAL EVENT STREAM (REAL TERMINAL) -->
        <div class="vgt-terminal">
            <div class="vgt-term-header">
                <div class="vgt-term-buttons">
                    <span class="btn-red"></span><span class="btn-yellow"></span><span class="btn-green"></span>
                </div>
                <div class="vgt-term-title"><?php esc_html_e('nemesis@vgt-core:~/logs$ tail -f /var/log/deception.log', 'vgt-sentinel'); ?></div>
            </div>
            <div class="vgt-term-body" id="nemesis-terminal">
                <?php if ($is_enabled && !empty($real_logs)): ?>
                    <code class="sys-boot">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[SYSTEM] Connection to Nemesis Database established. Streaming logs...', 'vgt-sentinel'); ?></code>
                    <?php if ($is_active_strike): ?>
                        <code class="log-critical">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[WARNING] ACTIVE STRIKE INITIATED. OFFENSIVE KINETIC COUNTERMEASURES ARMED.', 'vgt-sentinel'); ?></code>
                    <?php endif; ?>
                    
                    <?php foreach ($real_logs as $log): 
                        $time = wp_date('H:i:s', strtotime($log->timestamp));
                        $type_str = strtoupper((string)$log->type);
                        $details_str = strtoupper((string)$log->details);
                        
                        // VGT SUPREME FIX: Deterministisches Type-Parsing inkl. historischer Korrektur
                        $class = 'log-info';
                        $type = str_pad("[{$log->type}]", 18, " ", STR_PAD_RIGHT);

                        if (strpos($type_str, 'TARPIT') !== false || (strpos($type_str, 'CANARY') !== false && strpos($details_str, 'HONEYPOT') !== false)) {
                            $class = 'log-tarpit';
                            // Überschreibe historische Daten on-the-fly für reine UI-Konsistenz
                            $type = str_pad("[TARPIT]", 18, " ", STR_PAD_RIGHT); 
                        } elseif (strpos($type_str, 'CANARY') !== false || strpos($type_str, 'DOM') !== false) {
                            $class = 'log-canary';
                        } elseif (strpos($type_str, 'SABOTAGE') !== false || strpos($type_str, 'STRIKE') !== false) {
                            $class = 'log-critical';
                        } elseif (strpos($type_str, 'POISON') !== false) {
                            $class = 'log-poison';
                        }
                    ?>
                        <code class="<?php echo esc_attr($class); ?>">
                            <span class="term-time">[<?php echo esc_html($time); ?>]</span> 
                            <span class="term-type"><?php echo esc_html($type); ?></span> 
                            <span class="term-msg"><?php echo esc_html((string)$log->details); ?></span> 
                            <span class="term-ip">(<?php esc_html_e('IP:', 'vgt-sentinel'); ?> <?php echo esc_html((string)$log->ip_address); ?>)</span>
                        </code>
                    <?php endforeach; ?>
                    <code class="log-info"><span class="term-time">[<?php echo wp_date('H:i:s'); ?>]</span> <?php esc_html_e('[SYSTEM] Waiting for tactical events...', 'vgt-sentinel'); ?><span class="cursor-blink">_</span></code>
                <?php elseif ($is_enabled): ?>
                    <code class="sys-boot">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[SYSTEM] Nemesis Counterintelligence Matrix loaded. Database connected.', 'vgt-sentinel'); ?></code>
                    <?php if ($is_active_strike): ?>
                        <code class="log-critical">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[WARNING] ACTIVE STRIKE INITIATED. OFFENSIVE KINETIC COUNTERMEASURES ARMED.', 'vgt-sentinel'); ?></code>
                    <?php endif; ?>
                    <code class="log-info">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[SYSTEM] Log table is empty. Matrix is active and waiting for targets...', 'vgt-sentinel'); ?><span class="cursor-blink">_</span></code>
                <?php else: ?>
                    <code class="log-critical">[<?php echo wp_date('H:i:s'); ?>] <?php esc_html_e('[ERROR] Deception Matrix is offline. Server responds with standard protocols.', 'vgt-sentinel'); ?></code>
                <?php endif; ?>
            </div>
        </div>

        <!-- EXPERIMENTAL & RESTRICTED PROTOCOLS (ACCORDION) -->
        <div class="vgt-experimental-wrapper">
            <button type="button" class="vgt-accordion-trigger" id="vgt-exp-trigger">
                <span class="trigger-title">
                    <svg class="vgt-icon" style="width:20px; height:20px;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <?php esc_html_e('EXPERIMENTAL PROTOCOLS', 'vgt-sentinel'); ?>
                </span>
                <svg class="vgt-icon toggle-icon" style="width:20px; height:20px;" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            
            <div class="vgt-accordion-content" id="vgt-exp-content">
                <!-- VGT COMPLIANCE: ACTIVE STRIKE (HACK BACK) TOGGLE -->
                <div class="vgt-master-switch-panel vgt-danger-panel" id="strike-panel">
                    <div class="panel-info">
                        <h3 class="danger-text">
                            <svg class="vgt-icon" style="width:24px; height:24px;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            <?php esc_html_e('Active Strike (Hack Back)', 'vgt-sentinel'); ?>
                        </h3>
                        <p><?php echo wp_kses_post(__('Entfesselt die kinetischen Sabotage-Waffen. Das System wechselt von reiner Täuschung zum aktiven <strong>Gegenschlag</strong>. Beinhaltet GZIP-Bombing (OOM-Crash beim Angreifer), Cookie-Bombing und Terminal-Sabotage.', 'vgt-sentinel')); ?></p>
                        <div class="legal-warning-heavy">
                            <div class="warning-header">
                                <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                <?php esc_html_e('STRIKTE RECHTLICHE WARNUNG (DEUTSCHLAND)', 'vgt-sentinel'); ?>
                            </div>
                            <div class="warning-body">
                                <?php echo wp_kses_post(__('Die Ausführung aktiver Denial-of-Service (DoS) oder Sabotage-Maßnahmen gegen fremde IT-Systeme (Hack-Back) ist in der Bundesrepublik Deutschland nach <strong>§ 303a StGB (Datenveränderung)</strong> und <strong>§ 303b StGB (Computersabotage)</strong> strafbar und kann mit Freiheitsstrafen geahndet werden.<br><br><strong>Nutzung ausschließlich in isolierten Sandbox-Umgebungen, im Rahmen autorisierter Penetration Tests oder auf eigene, vollumfängliche rechtliche Verantwortung. VGT übernimmt keinerlei Haftung für den Missbrauch dieser Protokolle.</strong>', 'vgt-sentinel')); ?>
                            </div>
                        </div>
                    </div>
                    <div class="panel-action">
                        <label class="vgt-pure-switch danger-switch" id="toggle-container-strike">
                            <!-- Das Checkbox-Event wird via JS abgefangen -->
                            <input type="checkbox" name="vis_config[nemesis_active_strike]" id="nemesis_active_strike" value="1" <?php checked($is_active_strike, true); ?>>
                            <span class="vgt-pure-slider danger-slider"></span>
                            <div class="switch-label danger-label" id="toggle-label-strike">
                                <?php echo $is_active_strike ? esc_html__('ARMED', 'vgt-sentinel') : esc_html__('DISARMED', 'vgt-sentinel'); ?>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- VGT COMPLIANCE MODAL (HIDDEN BY DEFAULT) -->
<div class="vgt-modal-overlay" id="vgt-compliance-modal" style="display: none;">
    <div class="vgt-modal-box">
        <div class="vgt-modal-header">
            <svg class="vgt-icon" style="width:22px; height:22px;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <?php esc_html_e('KINETIC STRIKE AUTHORIZATION REQUIRED', 'vgt-sentinel'); ?>
        </div>
        <div class="vgt-modal-body">
            <p><?php echo wp_kses_post(__('Sie sind dabei, das <strong>Active Strike (Hack Back) Protokoll</strong> zu initialisieren.', 'vgt-sentinel')); ?></p>
            <p><?php esc_html_e('Diese Aktion verwandelt passive Verteidigung in aktive System-Sabotage (GZIP-Bombs, Memory Exhaustion) gegen die Infrastruktur des Angreifers.', 'vgt-sentinel'); ?></p>
            <div class="modal-legal-box">
                <?php echo wp_kses_post(__('Gemäß <strong>§ 303a / § 303b StGB (Computersabotage)</strong> ist der unautorisierte Einsatz dieser Waffen im deutschen Rechtsraum strikt illegal. Bestätigen Sie, dass Sie dieses System in einer autorisierten Umgebung betreiben und die volle rechtliche Haftung übernehmen.', 'vgt-sentinel')); ?>
            </div>
        </div>
        <div class="vgt-modal-actions">
            <button type="button" class="vgt-btn-cancel" id="vgt-modal-abort"><?php esc_html_e('ABORT SEQUENCE', 'vgt-sentinel'); ?></button>
            <button type="button" class="vgt-btn-confirm" id="vgt-modal-authorize"><?php esc_html_e('AUTHORIZE STRIKE', 'vgt-sentinel'); ?></button>
        </div>
    </div>
</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT)
     ========================================================================================= -->
<script>
    <?php 
    $nemesis_js_path = __DIR__ . '/nemesis/script.js';
    if (is_readable($nemesis_js_path)) {
        echo file_get_contents($nemesis_js_path);
    }
    ?>
</script>
