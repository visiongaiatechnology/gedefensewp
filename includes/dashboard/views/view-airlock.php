<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: AIRLOCK
 * MODULE: SECURE FILE SYSTEM INGRESS
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. LOGIK CORE (STRICT 1:1)
// =========================================================================================
$opt = get_option('vis_config', []);
$is_enabled = !isset($opt['airlock_enabled']) || !empty($opt['airlock_enabled']); // Default ON
$max_mb = $opt['airlock_max_mb'] ?? 5;
$allowed_exts = $opt['airlock_extensions'] ?? 'jpg, jpeg, png, gif, webp, svg, pdf, zip';
$is_obfuscate = !isset($opt['airlock_obfuscate']) || !empty($opt['airlock_obfuscate']); // Default ON
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET LOADING
     ========================================================================================= -->
<!-- =========================================================================================
     3. VIEW CONTENT
     ========================================================================================= -->
<div class="vgt-apex-ui">
    
    <div class="vgt-hero-header">
        <div class="vgt-hero-icon">
            <svg class="vgt-icon" viewBox="0 0 24 24" style="width: 28px; height: 28px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        </div>
        <div>
            <h2 class="vgt-hero-title">
                <?php esc_html_e('AIRLOCK', 'vgt-sentinel'); ?> 
                <span class="vgt-badge vgt-badge-active"><?php esc_html_e('L0 INGRESS', 'vgt-sentinel'); ?></span>
            </h2>
            <p class="vgt-hero-desc"><?php esc_html_e('Strict File-System Defense. Steuert Dateiuploads, blockiert eingebettete Payloads in Bildern und obfuskiert Dateinamen zur Verhinderung von Direct-Execution Angriffen am absoluten Nullpunkt des Stacks.', 'vgt-sentinel'); ?></p>
        </div>
    </div>

    <div class="vgt-grid-2">
            <!-- LEFT COLUMN: SETTINGS -->
            <div class="vgt-panel">
                <div class="vgt-panel-glow"></div>
                <div class="vgt-panel-header">
                    <h3 class="vgt-panel-title">
                        <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        <?php esc_html_e('Ingress Policies', 'vgt-sentinel'); ?>
                    </h3>
                </div>
                
                <div class="vgt-panel-body">
                    
                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <div class="vgt-setting-title"><?php esc_html_e('Airlock Engine aktivieren', 'vgt-sentinel'); ?></div>
                            <div class="vgt-setting-desc"><?php esc_html_e('Master-Switch. Deaktivieren für unlimitierten Raw-Upload (Gefahr!).', 'vgt-sentinel'); ?></div>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="vis_config[airlock_enabled]" value="1" <?php checked($is_enabled, true); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <div class="vgt-setting-title"><?php esc_html_e('Cryptographic Filename Entropy', 'vgt-sentinel'); ?></div>
                            <div class="vgt-setting-desc"><?php esc_html_e('Zerstört originale Dateinamen und ersetzt sie durch CRC32 Hashes. Verhindert Vorhersagbarkeit von Uploads.', 'vgt-sentinel'); ?></div>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="vis_config[airlock_obfuscate]" value="1" <?php checked($is_obfuscate, true); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-form-group" style="margin-top: 24px;">
                        <label class="vgt-label"><?php esc_html_e('Hard Limit: Max Upload Size (MB)', 'vgt-sentinel'); ?></label>
                        <input type="number" name="vis_config[airlock_max_mb]" value="<?php echo esc_attr((string)$max_mb); ?>" class="vgt-input" min="1" max="500">
                        <div class="vgt-help"><?php esc_html_e('Dateien größer als dieser Wert werden auf Kernel-Ebene vom Airlock abgelehnt, bevor WordPress sie verarbeitet. Empfehlung: 5.', 'vgt-sentinel'); ?></div>
                    </div>

                    <div class="vgt-form-group">
                        <label class="vgt-label"><?php esc_html_e('Strict MIME/Extension Whitelist', 'vgt-sentinel'); ?></label>
                        <input type="text" name="vis_config[airlock_extensions]" value="<?php echo esc_attr((string)$allowed_exts); ?>" class="vgt-input" placeholder="<?php echo esc_attr__('jpg, png, pdf...', 'vgt-sentinel'); ?>">
                        <div class="vgt-help"><?php esc_html_e('Komma-separierte Liste an erlaubten Dateiendungen. Alles andere prallt am L0 Shield ab.', 'vgt-sentinel'); ?></div>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;">
                            <?php 
                            $exts = array_filter(array_map('trim', explode(',', (string)$allowed_exts)));
                            foreach($exts as $e) {
                                echo '<span class="vgt-badge vgt-badge-active" style="text-transform:lowercase; font-family:monospace;">.' . esc_html($e) . '</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <button type="submit" class="vgt-btn-primary">
                        <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        <?php esc_html_e('Airlock Policies Speichern', 'vgt-sentinel'); ?>
                    </button>
                    
                </div>
            </div>

            <!-- RIGHT COLUMN: TELEMETRY & INFO -->
            <div class="vgt-panel">
                <div class="vgt-panel-glow" style="background: linear-gradient(90deg, transparent, #94a3b8, transparent);"></div>
                <div class="vgt-panel-header">
                    <h3 class="vgt-panel-title" style="color: #ffffff;">
                        <svg class="vgt-icon" viewBox="0 0 24 24" style="color: var(--vgt-brand);"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        <?php esc_html_e('Scanner Matrix', 'vgt-sentinel'); ?>
                    </h3>
                </div>
                
                <div class="vgt-panel-body">
                    <p style="font-size: 13px; color: var(--vgt-text-muted); line-height: 1.6; margin-top: 0; margin-bottom: 24px;">
                        <?php 
                        printf(
                            esc_html__('Airlock analysiert jeden eingehenden Upload-Stream über das %s Hook. Es vertraut keinen HTTP-Headern und extrahiert Payload-Daten direkt aus dem RAM-Buffer.', 'vgt-sentinel'),
                            '<code style="background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 4px;">wp_handle_upload_prefilter</code>'
                        ); 
                        ?>
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        
                        <!-- Box 1 -->
                        <div style="background: rgba(0,0,0,0.3); border: 1px solid var(--vgt-border); border-left: 3px solid var(--vgt-brand); border-radius: 6px; padding: 16px;">
                            <div style="color: var(--vgt-text); font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?php esc_html_e('Magic Bytes Verification', 'vgt-sentinel'); ?></div>
                            <div style="color: var(--vgt-text-muted); font-size: 12px; line-height: 1.5;"><?php esc_html_e('Scannt die ersten 1024 Bytes des Buffers um gefälschte Dateiendungen (z.B. shell.php.jpg) mathematisch zu entlarven.', 'vgt-sentinel'); ?></div>
                        </div>

                        <!-- Box 2 -->
                        <div style="background: rgba(0,0,0,0.3); border: 1px solid var(--vgt-border); border-left: 3px solid #10b981; border-radius: 6px; padding: 16px;">
                            <div style="color: var(--vgt-text); font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?php esc_html_e('SVG XML-Sanitization', 'vgt-sentinel'); ?></div>
                            <div style="color: var(--vgt-text-muted); font-size: 12px; line-height: 1.5;">
                                <?php 
                                printf(
                                    esc_html__('Extrahiert und blockiert %s, %s und %s Vektoren in hochgeladenen Vektorgrafiken.', 'vgt-sentinel'),
                                    '<code>&lt;script&gt;</code>',
                                    '<code>onload=</code>',
                                    '<code>javascript:</code>'
                                );
                                ?>
                            </div>
                        </div>

                        <!-- Box 3 -->
                        <div style="background: rgba(0,0,0,0.3); border: 1px solid var(--vgt-border); border-left: 3px solid #ef4444; border-radius: 6px; padding: 16px;">
                            <div style="color: var(--vgt-text); font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?php esc_html_e('PHP Payload Detection', 'vgt-sentinel'); ?></div>
                            <div style="color: var(--vgt-text-muted); font-size: 12px; line-height: 1.5;">
                                <?php 
                                printf(
                                    esc_html__('Verhindert, dass Bilder mit injiziertem %s Code das System kompromittieren (Exif-RCE Abwehr).', 'vgt-sentinel'),
                                    '<code>&lt;?php</code>'
                                );
                                ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
    </div>
</div>
