<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: GHOST TRAP
 * MODULE: L7 DECEPTION & HONEYPOT NETWORK
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. LOGIK CORE (STRICT 1:1)
// =========================================================================================
$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['ghost_trap_enabled']);
$trap_count = $opt['ghost_trap_count'] ?? 5;
$trap_exts = $opt['ghost_trap_exts'] ?? 'php, sql, bak, old, zip';
$trap_style = $opt['ghost_trap_style'] ?? 'mixed';

// Status fetch
$active_manifest = get_option('vis_ghost_trap_manifest', []);
$deployed_count = count($active_manifest);
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<!-- =========================================================================================
     3. VIEW CONTENT
     ========================================================================================= -->
<div class="vgt-apex-ui">
    
    <div class="vgt-hero-header">
        <div class="vgt-hero-icon">
            <svg class="vgt-icon" viewBox="0 0 24 24" style="width: 28px; height: 28px;"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"></path><path d="M12 12v9"></path><path d="M8 17l4 4 4-4"></path></svg>
        </div>
        <div>
            <h2 class="vgt-hero-title">
                <?php esc_html_e('GHOST TRAP', 'vgt-sentinel'); ?> 
                <span class="vgt-badge vgt-badge-active"><?php esc_html_e('L7 DECEPTION', 'vgt-sentinel'); ?></span>
            </h2>
            <p class="vgt-hero-desc"><?php esc_html_e('Proaktives Honeypot-Netzwerk. Generiert unsichtbare Dummy-Dateien im Root-Verzeichnis. Jeder Scanner oder Bot, der diese Dateien abtastet, wird sofort permanent auf Netzwerk-Ebene blockiert (Auto-Ban).', 'vgt-sentinel'); ?></p>
        </div>
    </div>

    <div class="vgt-grid-2">
            <!-- LEFT COLUMN: SETTINGS -->
            <div class="vgt-panel">
                <div class="vgt-panel-glow"></div>
                <div class="vgt-panel-header">
                    <h3 class="vgt-panel-title">
                        <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                        <?php esc_html_e('Generator Config', 'vgt-sentinel'); ?>
                    </h3>
                </div>
                
                <div class="vgt-panel-body">
                    
                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <div class="vgt-setting-title"><?php esc_html_e('Ghost Trap Engine aktivieren', 'vgt-sentinel'); ?></div>
                            <div class="vgt-setting-desc"><?php esc_html_e('Aktiviert die Erstellung und Überwachung der künstlichen Systemfallen.', 'vgt-sentinel'); ?></div>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="vis_config[ghost_trap_enabled]" value="1" <?php checked($is_enabled, true); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-form-group" style="margin-top: 24px;">
                        <label class="vgt-label"><?php esc_html_e('Anzahl der Fallen (Nodes)', 'vgt-sentinel'); ?></label>
                        <input type="number" name="vis_config[ghost_trap_count]" value="<?php echo esc_attr((string)$trap_count); ?>" class="vgt-input" min="1" max="50">
                        <div class="vgt-help"><?php esc_html_e('Legt fest, wie viele Honeypots im Root-Verzeichnis platziert werden (Max: 50).', 'vgt-sentinel'); ?></div>
                    </div>

                    <div class="vgt-form-group">
                        <label class="vgt-label"><?php esc_html_e('Polymorphe Dateiendungen', 'vgt-sentinel'); ?></label>
                        <input type="text" name="vis_config[ghost_trap_exts]" value="<?php echo esc_attr((string)$trap_exts); ?>" class="vgt-input" placeholder="<?php echo esc_attr__('php, sql, bak, old', 'vgt-sentinel'); ?>">
                        <div class="vgt-help"><?php esc_html_e('Komma-separierte Liste. Diese Endungen locken spezialisierte Scanner an (z.B. SQL-Dumper, Backup-Sniffer).', 'vgt-sentinel'); ?></div>
                    </div>

                    <div class="vgt-form-group">
                        <label class="vgt-label"><?php esc_html_e('Namensgenerator-Logik (AI-Style)', 'vgt-sentinel'); ?></label>
                        <select name="vis_config[ghost_trap_style]" class="vgt-select">
                            <option value="mixed" <?php selected($trap_style, 'mixed'); ?>><?php esc_html_e('Mixed Matrix (Empfohlen)', 'vgt-sentinel'); ?></option>
                            <option value="system" <?php selected($trap_style, 'system'); ?>><?php esc_html_e('System Fakes (wp-config-old, admin-test)', 'vgt-sentinel'); ?></option>
                            <option value="backup" <?php selected($trap_style, 'backup'); ?>><?php esc_html_e('Backup Fakes (db_dump_2024, site_backup)', 'vgt-sentinel'); ?></option>
                            <option value="random" <?php selected($trap_style, 'random'); ?>><?php esc_html_e('Random Hashes (a8f9c12a)', 'vgt-sentinel'); ?></option>
                        </select>
                        <div class="vgt-help"><?php esc_html_e('Bestimmt das semantische Profil der Dateinamen.', 'vgt-sentinel'); ?></div>
                    </div>

                    <button type="submit" class="vgt-btn-primary">
                        <svg class="vgt-icon" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                        <?php esc_html_e('Fallen Generieren & Deployen', 'vgt-sentinel'); ?>
                    </button>
                    <p style="font-size: 11px; text-align: center; margin-top: 12px; color: var(--vgt-text-muted);">
                        <?php esc_html_e('Vorsicht: Beim Speichern wird das bestehende Honeypot-Netzwerk restlos vernichtet und komplett neu gewoben.', 'vgt-sentinel'); ?>
                    </p>
                </div>
            </div>

            <!-- RIGHT COLUMN: TELEMETRY & MANIFEST -->
            <div class="vgt-panel">
                <div class="vgt-panel-glow" style="background: linear-gradient(90deg, transparent, var(--vgt-brand), transparent);"></div>
                <div class="vgt-panel-header">
                    <h3 class="vgt-panel-title" style="color: #ffffff;">
                        <svg class="vgt-icon" viewBox="0 0 24 24" style="color: var(--vgt-brand);"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                        <?php esc_html_e('Deployment Manifest', 'vgt-sentinel'); ?>
                    </h3>
                </div>
                
                <div class="vgt-panel-body">
                    <?php if ($is_enabled && $deployed_count > 0): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                            <span style="font-size: 13px; font-weight: 600; color: #10b981;"><?php esc_html_e('SYSTEM ACTIVE', 'vgt-sentinel'); ?></span>
                            <span class="vgt-badge vgt-badge-active">
                                <?php 
                                printf(
                                    esc_html(
                                        _n('%d Node Deployed', '%d Nodes Deployed', $deployed_count, 'vgt-sentinel')
                                    ),
                                    (int)$deployed_count
                                );
                                ?>
                            </span>
                        </div>
                        <p style="font-size: 12px; color: var(--vgt-text-muted); line-height: 1.5; margin: 0;">
                            <?php esc_html_e('Das Netzwerk ist aktiv. Jeder HTTP-Zugriff auf die gelisteten Routen resultiert im sofortigen IP-Ban durch den Aegis-Kernel.', 'vgt-sentinel'); ?>
                        </p>
                        
                        <div class="vgt-trap-list">
                            <?php foreach ($active_manifest as $node): ?>
                                <div class="vgt-trap-item">
                                    <svg class="vgt-icon" style="color: var(--vgt-brand);" viewBox="0 0 24 24" width="14" height="14"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                    /<?php echo esc_html($node); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 0;">
                            <svg class="vgt-icon" style="width: 48px; height: 48px; color: #64748b; margin-bottom: 16px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            <h4 style="margin: 0 0 8px 0; color: #e2e8f0;"><?php esc_html_e('SYSTEM OFFLINE', 'vgt-sentinel'); ?></h4>
                            <p style="font-size: 13px; color: var(--vgt-text-muted); line-height: 1.5; margin: 0;"><?php esc_html_e('Keine Honeypot-Nodes im Dateisystem platziert.', 'vgt-sentinel'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </div>
</div>
