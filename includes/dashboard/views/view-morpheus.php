<?php
declare(strict_types=1);
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 * MODULE: MORPHEUS HYPERVISOR DASHBOARD VIEW
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'VGT Protocol: Direct access denied.' );
}

if ( ! class_exists( '\VisionGaia\GeDefense\Modules\Morpheus\Morpheus' ) ) {
    $core_file = wp_normalize_path( VIS_PATH . 'includes/modules/morpheus/class-vis-morpheus.php' );
    if ( is_readable( $core_file ) ) {
        require_once $core_file;
    }
    
    if ( ! class_exists( '\VisionGaia\GeDefense\Modules\Morpheus\Morpheus' ) ) {
        echo '<div class="vgt-alert vgt-alert-danger" style="background: rgba(255, 0, 60, 0.1); border: 1px solid #ff003c; color: #ff003c; padding: 16px; border-radius: 8px; font-family: monospace; font-weight: bold; margin-bottom: 20px;">' . esc_html__( 'VGT KERNEL PANIC: Morpheus Engine Boot Failure. System halted.', 'vgt-sentinel' ) . '</div>';
        return;
    }
}

$morpheus = \VisionGaia\GeDefense\Modules\Morpheus\Morpheus::get_instance();

// Sicherheits-Tokens & Config
$vis_config = get_option('vis_config', []);
$is_strict_mode = !empty($vis_config['morpheus_strict_mode']);
$vgt_nonce = wp_create_nonce('vgt_morpheus_action');
$isolation_token = $morpheus->dashboard->generate_isolation_token();

$vault_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/vgt-vault/morpheus/' : dirname( ABSPATH ) . '/wp-content/vgt-vault/morpheus/';
$audit_dir = $vault_dir . 'audit/';
$proposed_dir = $vault_dir . 'proposed/';

$active_matrix = $morpheus->get_full_matrix();

// Aktive Plugins laden (SYSTEM eliminiert)
$learning_plugins = [];
$active_plugins_db = (array) get_option('active_plugins', []);
foreach ($active_plugins_db as $p) {
    $slug = dirname((string)$p);
    if ($slug !== '.' && $slug !== '/') {
        $learning_plugins[$slug] = 0; 
    }
}

// I/O HARDENED: Log Files parsen
if ( is_dir($audit_dir) ) {
    $log_files = glob($audit_dir . '*.log');
    if (is_array($log_files)) {
        foreach ( $log_files as $file ) {
            if (str_contains($file, '.submitted')) continue; 
            if ( ! is_readable( $file ) ) continue;

            $slug = basename($file, '.log');
            $file_lines = file($file, FILE_SKIP_EMPTY_LINES);
            $lines = is_array($file_lines) ? count($file_lines) : 0;
            
            $learning_plugins[$slug] = min($lines, 200); 
        }
    }
}
arsort($learning_plugins); 

// I/O HARDENED: JSON Files parsen
$proposed_plugins = [];
if ( is_dir($proposed_dir) ) {
    $json_files = glob($proposed_dir . '*.json');
    if (is_array($json_files)) {
        foreach ( $json_files as $file ) {
            if ( ! is_readable( $file ) ) continue;

            $slug = basename($file, '.json');
            $raw_data = file_get_contents($file);
            
            if ( $raw_data !== false ) {
                $content = json_decode($raw_data, true);
                if ( is_array($content) ) { 
                    $proposed_plugins[$slug] = $content;
                }
            }
        }
    }
}

// I/O HARDENED: Terminal Logs laden
$ai_terminal_logs = [];
$terminal_file = $vault_dir . 'ai-terminal.log';
if ( is_readable($terminal_file) ) {
    $term_lines = file($terminal_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ( is_array($term_lines) ) { 
        $ai_terminal_logs = array_slice($term_lines, -25); 
    }
}

delete_transient('morpheus_pending_review');
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $morpheus_css_path = __DIR__ . '/morpheus/style.css';
    if (is_readable($morpheus_css_path)) {
        echo file_get_contents($morpheus_css_path);
    }
    ?>
</style>

<!-- =========================================================================================
     VIEW CONTENT
     ========================================================================================= -->
<div class="vgt-morpheus-container <?php echo $is_strict_mode ? 'strict-theme' : ''; ?>" id="vgt-app">
    
    <div class="vgt-morpheus-header <?php echo $is_strict_mode ? 'strict-active' : 'audit-active'; ?>" id="vgt-header">
        <div class="vgt-header-glow"></div>
        <div class="vgt-morpheus-title">
            <h2>
                <svg class="vgt-icon" style="width:24px; height:24px;" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                <?php esc_html_e('Morpheus AI Builder', 'vgt-sentinel'); ?>
            </h2>
            <p><?php esc_html_e('Zero-Trust Runtime Sandbox. KI-gestützte O(1) Matrix Kompilierung.', 'vgt-sentinel'); ?></p>
        </div>

        <div class="vgt-header-actions">
            <div class="vgt-mode-control">
                <span class="vgt-mode-label label-audit <?php echo !$is_strict_mode ? 'active' : ''; ?>"><?php esc_html_e('Audit', 'vgt-sentinel'); ?></span>
                <label class="vgt-switch">
                    <input type="checkbox" id="vgt-mode-toggle" <?php checked($is_strict_mode); ?> onchange="vgtUpdateTheme(this)">
                    <span class="vgt-slider"></span>
                </label>
                <span class="vgt-mode-label label-strict <?php echo $is_strict_mode ? 'active' : ''; ?>"><?php esc_html_e('Strict', 'vgt-sentinel'); ?></span>
            </div>
            
            <div class="vgt-status-pill <?php echo $is_strict_mode ? 'status-strict' : 'status-audit'; ?>" id="vgt-pill">
                <div class="indicator"></div>
                <span id="vgt-pill-text"><?php echo $is_strict_mode ? esc_html__('ENFORCEMENT ACTIVE', 'vgt-sentinel') : esc_html__('LEARNING MODE', 'vgt-sentinel'); ?></span>
            </div>
        </div>
    </div>

    <!-- SECTION 1: PROPOSED (WAITING FOR APPROVAL) -->
    <div class="vgt-section" style="<?php echo empty($proposed_plugins) ? 'opacity: 0.6;' : 'border-color: rgba(255, 189, 46, 0.4); box-shadow: 0 0 20px rgba(255,189,46,0.1);'; ?>">
        <div class="vgt-section-header">
            <span class="vgt-badge bg-yellow"><?php esc_html_e('ACTION REQUIRED', 'vgt-sentinel'); ?></span>
            <h3><?php esc_html_e('Pending AI Approvals', 'vgt-sentinel'); ?></h3>
        </div>
        
        <?php if (empty($proposed_plugins)): ?>
            <div class="vgt-empty-state"><?php esc_html_e('Keine offenen KI-Vorschläge. Die Matrix ist synchron.', 'vgt-sentinel'); ?></div>
        <?php else: ?>
            <?php foreach ($proposed_plugins as $slug => $data): ?>
                <div class="vgt-row">
                    <div class="vgt-col-main">
                        <div class="vgt-plugin-name">
                            <svg class="vgt-icon" style="width:18px; height:18px; color:#ffbd2e;" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            <?php echo esc_html(strtoupper((string)$slug)); ?>
                        </div>
                        <div class="vgt-plugin-meta"><?php esc_html_e('Groq Llama-3.3-70B hat eine sichere Matrix erstellt.', 'vgt-sentinel'); ?></div>
                    </div>
                    <div class="vgt-actions">
                        <button type="button" class="vgt-btn" onclick='vgtPreviewJson(<?php echo json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, "<?php echo esc_js($slug); ?>")'>
                            <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <?php esc_html_e('Preview JSON', 'vgt-sentinel'); ?>
                        </button>
                        <button type="button" class="vgt-btn vgt-btn-approve" onclick="vgtApprove('<?php echo esc_js($slug); ?>', this)">
                            <svg class="vgt-icon" style="width:14px; height:14px; stroke-width:3px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <?php esc_html_e('Approve', 'vgt-sentinel'); ?>
                        </button>
                        <button type="button" class="vgt-btn vgt-btn-reject" onclick="vgtReject('<?php echo esc_js($slug); ?>', this)">
                            <svg class="vgt-icon" style="width:14px; height:14px; stroke-width:3px;" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- SECTION 2: LEARNING (AUDIT LOGGING) -->
    <div class="vgt-section">
        <div class="vgt-section-header">
            <span class="vgt-badge bg-blue"><?php esc_html_e('LEARNING', 'vgt-sentinel'); ?></span>
            <h3><?php esc_html_e('Active Audit Loggers', 'vgt-sentinel'); ?></h3>
        </div>
        
        <?php if (empty($learning_plugins)): ?>
            <div class="vgt-empty-state"><?php esc_html_e('Keine Plugins gefunden. Warte auf Systemereignisse.', 'vgt-sentinel'); ?></div>
        <?php else: ?>
            <?php foreach ($learning_plugins as $slug => $count): 
                $percent = round(($count / 200) * 100);
                if (isset($active_matrix[$slug]) || isset($proposed_plugins[$slug])) continue;
            ?>
                <div class="vgt-row">
                    <div class="vgt-col-main">
                        <div class="vgt-plugin-name"><?php echo esc_html(strtoupper((string)$slug)); ?></div>
                        <div class="vgt-plugin-meta"><?php esc_html_e('Sammelt Zugriffsvektoren für KI-Analyse...', 'vgt-sentinel'); ?></div>
                    </div>
                    
                    <div class="vgt-progress-container">
                        <div class="vgt-progress-text"><?php printf(esc_html__('%d / 200 Logs (%d%%)', 'vgt-sentinel'), (int)$count, (int)$percent); ?></div>
                        <div class="vgt-progress-bar">
                            <div class="vgt-progress-fill" style="width: <?php echo esc_attr((string)$percent); ?>%;"></div>
                        </div>
                    </div>

                    <div class="vgt-actions" style="margin-left: 30px;">
                        <?php if ($count > 0): ?>
                            <button type="button" class="vgt-btn vgt-btn-ai <?php echo $count >= 200 ? 'pulse' : ''; ?>" onclick="vgtTriggerAI('<?php echo esc_js($slug); ?>', this)" title="<?php echo esc_attr__('Matrix manuell über Groq berechnen lassen', 'vgt-sentinel'); ?>">
                                <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                <?php echo $count >= 200 ? esc_html__('AI Build Ready', 'vgt-sentinel') : esc_html__('Force AI Build', 'vgt-sentinel'); ?>
                            </button>
                        <?php else: ?>
                            <span style="font-size: 0.7rem; color: #555; font-family: monospace;"><?php esc_html_e('AWAITING TRAFFIC', 'vgt-sentinel'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- VGT AI ORACLE TERMINAL -->
        <div class="vgt-ai-terminal-box">
            <div class="vgt-terminal-stream" id="vgt-terminal-stream">
                <?php if (empty($ai_terminal_logs)): ?>
                    <span style="color: #666;"><?php esc_html_e('[SYSTEM] Oracle Terminal verbunden. Warte auf KI-Aktivität...', 'vgt-sentinel'); ?></span>
                <?php else: ?>
                    <?php foreach($ai_terminal_logs as $log): ?>
                        <span><?php echo esc_html(trim((string)$log)); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SECTION 3: COMPILED MATRIX (ACTIVE) -->
    <div class="vgt-section">
        <div class="vgt-section-header">
            <span class="vgt-badge bg-green"><?php esc_html_e('COMPILED', 'vgt-sentinel'); ?></span>
            <h3><?php esc_html_e('Active O(1) Memory Matrix', 'vgt-sentinel'); ?></h3>
        </div>
        
        <?php 
        $has_active = false;
        foreach ($active_matrix as $slug => $data): 
            if ($slug === '_meta' || $slug === '_default') continue;
            $has_active = true;
            
            $net_count = count($data['network'] ?? []);
            $db_count = count($data['db_write'] ?? []);
            $opt_count = count($data['options'] ?? []);
        ?>
            <div class="vgt-row">
                <div class="vgt-col-main">
                    <div class="vgt-plugin-name">
                        <svg class="vgt-icon" style="width:16px; height:16px; color:#27c93f;" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <?php echo esc_html(strtoupper((string)$slug)); ?>
                    </div>
                    <div class="vgt-permissions-preview">
                        <div class="perm-group">
                            <span class="perm-icon"><svg class="vgt-icon" style="width:12px; height:12px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line></svg> <?php esc_html_e('Net:', 'vgt-sentinel'); ?></span>
                            <span class="perm-val <?php echo $net_count ? '' : 'empty'; ?>"><?php echo $net_count ? esc_html(implode(', ', $data['network'])) : esc_html__('BLOCKED', 'vgt-sentinel'); ?></span>
                        </div>
                        <div class="perm-group">
                            <span class="perm-icon"><svg class="vgt-icon" style="width:12px; height:12px;" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path></svg> <?php esc_html_e('DB:', 'vgt-sentinel'); ?></span>
                            <span class="perm-val <?php echo $db_count ? '' : 'empty'; ?>"><?php echo $db_count ? esc_html(implode(', ', $data['db_write'])) : esc_html__('BLOCKED', 'vgt-sentinel'); ?></span>
                        </div>
                        <div class="perm-group">
                            <span class="perm-icon"><svg class="vgt-icon" style="width:12px; height:12px;" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect></svg> <?php esc_html_e('Opt:', 'vgt-sentinel'); ?></span>
                            <span class="perm-val <?php echo $opt_count ? '' : 'empty'; ?>"><?php echo $opt_count ? esc_html(implode(', ', $data['options'])) : esc_html__('BLOCKED', 'vgt-sentinel'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="vgt-actions">
                    <button type="button" class="vgt-btn" onclick="vgtForceDelete('<?php echo esc_js($slug); ?>', this)" title="<?php echo esc_attr__('Remove from Matrix', 'vgt-sentinel'); ?>">
                        <svg class="vgt-icon" style="width:14px; height:14px; color:#ff4d4d;" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (!$has_active): ?>
            <div class="vgt-empty-state"><?php esc_html_e('Die Matrix ist leer. Keine spezifischen Freigaben konfiguriert.', 'vgt-sentinel'); ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- JSON PREVIEW MODAL -->
<div class="vgt-modal-overlay" id="vgt-json-modal">
    <div class="vgt-modal">
        <div class="vgt-modal-header">
            <span class="vgt-modal-title" id="vgt-modal-plugin-title"><?php esc_html_e('morpheus@vgt-core:~/proposed/plugin.json', 'vgt-sentinel'); ?></span>
            <button type="button" class="vgt-close-btn" onclick="document.getElementById('vgt-json-modal').style.display='none'">&times;</button>
        </div>
        <div class="vgt-modal-body">
            <pre class="vgt-json-preview" id="vgt-json-content"></pre>
        </div>
        <div class="vgt-modal-footer">
            <button type="button" class="vgt-btn" onclick="document.getElementById('vgt-json-modal').style.display='none'"><?php esc_html_e('Close', 'vgt-sentinel'); ?></button>
            <button type="button" class="vgt-btn vgt-btn-approve" id="vgt-modal-approve-btn">
                <svg class="vgt-icon" style="width:14px; height:14px; stroke-width:3px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <?php esc_html_e('Approve & Compile', 'vgt-sentinel'); ?>
            </button>
        </div>
    </div>
</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT VIA INCLUDE FÜR PHP PARSING)
     ========================================================================================= -->
<script>
    <?php 
    $morpheus_js_path = __DIR__ . '/morpheus/script.js';
    if (is_readable($morpheus_js_path)) {
        include $morpheus_js_path;
    }
    ?>
</script>
