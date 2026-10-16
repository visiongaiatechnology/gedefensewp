<?php 
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$opt = get_option('vis_config', []);
$is_enabled = !isset($opt['airlock_enabled']) || !empty($opt['airlock_enabled']); // Default ON
$max_mb = (int)($opt['airlock_max_mb'] ?? 5);
$allowed_exts = (string)($opt['airlock_extensions'] ?? 'jpg, jpeg, png, gif, webp, svg, pdf, zip');
$is_obfuscate = !isset($opt['airlock_obfuscate']) || !empty($opt['airlock_obfuscate']); // Default ON

$airlockToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Airlock Secure Ingress">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('KERNEL-LEVEL FILE INGRESS & ZERO-DAY PAYLOAD SANITIZER', 'vgt-sentinel'); ?></p>
            <h2>AIRLOCK</h2>
            <p><?php esc_html_e('Strict File-System Defense. Steuert Dateiuploads, blockiert eingebettete Payloads in Bildern und obfuskiert Dateinamen zur Verhinderung von Direct-Execution Angriffen am absoluten Nullpunkt des Stacks.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Airlock Status">
            <span><small><?php esc_html_e('INGRESS ENGINE', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ACTIVE', 'vgt-sentinel') : esc_html__('OFFLINE', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('MAX UPLOAD', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$max_mb); ?> MB</strong></span>
            <span><small><?php esc_html_e('NAME ENTROPY', 'vgt-sentinel'); ?></small><strong><?php echo $is_obfuscate ? esc_html__('CRC32 ACTIVE', 'vgt-sentinel') : esc_html__('RAW (OFF)', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('L0 SHIELD', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ARMED', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('INGRESS FILTER STATE', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ONLINE (Strict)', 'vgt-sentinel') : esc_html__('OFFLINE (Raw)', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('HARD UPLOAD LIMIT', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$max_mb); ?> MB</strong></article>
        <article><small><?php esc_html_e('FILENAME OBFUSCATION', 'vgt-sentinel'); ?></small><strong><?php echo $is_obfuscate ? esc_html__('CRC32 HASH', 'vgt-sentinel') : esc_html__('RAW NAMES', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('INSPECTION ENGINE', 'vgt-sentinel'); ?></small><strong>MAGIC-BYTES L0</strong></article>
        <article><small><?php esc_html_e('MALWARE DEFENSE', 'vgt-sentinel'); ?></small><strong>EXIF-RCE SHIELD</strong></article>
        <article><small><?php esc_html_e('INTEGRITY IMPACT', 'vgt-sentinel'); ?></small><strong>ZERO-EXECUTION</strong></article>
    </div>

    <div class="vgt-grid-2">
        <!-- LEFT COLUMN: INGRESS POLICIES -->
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('01 / INGRESS POLICIES', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Dateiupload-Richtlinien & Härtung', 'vgt-sentinel'); ?></h3>
                </div>
                <span class="vgt-titan-badge" style="border-color: rgba(94, 234, 212, 0.4); color: var(--vis-cyan);">
                    <?php echo $is_enabled ? esc_html__('L0 ACTIVE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?>
                </span>
            </div>

            <div class="vgt-titan-toggle-grid" style="margin-bottom: 20px;">
                <?php $airlockToggle('airlock_enabled', $is_enabled, __('Airlock Engine aktivieren (Master-Switch)', 'vgt-sentinel')); ?>
                <?php $airlockToggle('airlock_obfuscate', $is_obfuscate, __('Kryptographische Dateinamen-Entropie (CRC32)', 'vgt-sentinel')); ?>
            </div>

            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('Hard Limit: Max Upload-Größe (MB)', 'vgt-sentinel'); ?></span>
                    <input type="number" name="vis_config[airlock_max_mb]" value="<?php echo esc_attr((string)$max_mb); ?>" min="1" max="500">
                    <small style="color: var(--vis-copy-muted); margin-top: 6px; display: block; line-height: 1.4;">
                        <?php esc_html_e('Dateien größer als dieser Wert werden auf Kernel-Ebene vom Airlock abgelehnt, bevor WordPress sie verarbeitet.', 'vgt-sentinel'); ?>
                    </small>
                </label>
            </div>

            <div class="vgt-titan-wide-field" style="margin-top: 18px;">
                <label>
                    <span><?php esc_html_e('Strikte MIME / Dateiendungs-Whitelist', 'vgt-sentinel'); ?></span>
                    <input type="text" name="vis_config[airlock_extensions]" value="<?php echo esc_attr((string)$allowed_exts); ?>" placeholder="jpg, jpeg, png, gif, webp, svg, pdf, zip">
                    <small style="color: var(--vis-copy-muted); margin-top: 6px; display: block; line-height: 1.4;">
                        <?php esc_html_e('Komma-separierte Liste an erlaubten Dateiendungen. Nicht gelistete Dateitypen prallen am L0 Shield ab.', 'vgt-sentinel'); ?>
                    </small>
                </label>

                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;">
                    <?php 
                    $exts = array_filter(array_map('trim', explode(',', (string)$allowed_exts)));
                    foreach($exts as $e): ?>
                        <span style="display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; background: rgba(94, 234, 212, 0.1); border: 1px solid rgba(94, 234, 212, 0.28); color: var(--vis-cyan); font: 700 11px/1 ui-monospace, monospace; text-transform: lowercase;">
                            .<?php echo esc_html($e); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="vgt-titan-actions" style="margin-top: 24px; justify-content: center; width: 100%;">
                <button type="submit" style="width: 100%; max-width: 280px; justify-content: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <?php esc_html_e('AIRLOCK RICHTLINIEN SPEICHERN', 'vgt-sentinel'); ?>
                </button>
            </div>
        </section>

        <!-- RIGHT COLUMN: TELEMETRY & INFO -->
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('02 / TELEMETRIE & DIAGNOSE', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Scanner-Matrix & Filter-Architektur', 'vgt-sentinel'); ?></h3>
                </div>
                <span class="vgt-titan-badge" style="border-color: rgba(56, 189, 248, 0.35); color: #38bdf8;">
                    <?php esc_html_e('DPI STREAM', 'vgt-sentinel'); ?>
                </span>
            </div>

            <p style="font-size: 13px; color: var(--vis-copy-soft); line-height: 1.6; margin-top: 0; margin-bottom: 20px;">
                <?php 
                printf(
                    esc_html__('Airlock analysiert jeden eingehenden Upload-Stream über den %s Hook. Es vertraut keinen manipulierbaren HTTP-Headern und extrahiert Payload-Daten direkt aus dem Kernel-RAM-Buffer.', 'vgt-sentinel'),
                    '<code style="background: rgba(148, 184, 224, 0.12); padding: 3px 6px; border-radius: 4px; color: var(--vis-cyan); font-family: ui-monospace, monospace;">wp_handle_upload_prefilter</code>'
                ); 
                ?>
            </p>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <!-- Box 1 -->
                <div style="background: var(--vis-surface-inset); border: 1px solid var(--vis-line); border-left: 3px solid var(--vis-cyan); border-radius: 8px; padding: 16px;">
                    <div style="color: #ffffff; font-weight: 750; font-size: 13.5px; margin-bottom: 5px; display: flex; align-items: center; gap: 8px;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--vis-cyan)" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                        <?php esc_html_e('Magic Bytes Verifizierung', 'vgt-sentinel'); ?>
                    </div>
                    <div style="color: var(--vis-copy-soft); font-size: 12px; line-height: 1.55;">
                        <?php esc_html_e('Scannt die ersten 1024 Bytes des Buffers, um gefälschte Dateiendungen (z.B. shell.php.jpg) mathematisch zu entlarven.', 'vgt-sentinel'); ?>
                    </div>
                </div>

                <!-- Box 2 -->
                <div style="background: var(--vis-surface-inset); border: 1px solid var(--vis-line); border-left: 3px solid #34d399; border-radius: 8px; padding: 16px;">
                    <div style="color: #ffffff; font-weight: 750; font-size: 13.5px; margin-bottom: 5px; display: flex; align-items: center; gap: 8px;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#34d399" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?php esc_html_e('SVG XML-Bereinigung & Sanitization', 'vgt-sentinel'); ?>
                    </div>
                    <div style="color: var(--vis-copy-soft); font-size: 12px; line-height: 1.55;">
                        <?php 
                        printf(
                            esc_html__('Extrahiert und blockiert %s, %s und %s Vektoren in hochgeladenen Vektorgrafiken.', 'vgt-sentinel'),
                            '<code style="color:#34d399; background:rgba(52,211,153,0.1); padding:2px 4px; border-radius:3px;">&lt;script&gt;</code>',
                            '<code style="color:#34d399; background:rgba(52,211,153,0.1); padding:2px 4px; border-radius:3px;">onload=</code>',
                            '<code style="color:#34d399; background:rgba(52,211,153,0.1); padding:2px 4px; border-radius:3px;">javascript:</code>'
                        );
                        ?>
                    </div>
                </div>

                <!-- Box 3 -->
                <div style="background: var(--vis-surface-inset); border: 1px solid var(--vis-line); border-left: 3px solid #fb7185; border-radius: 8px; padding: 16px;">
                    <div style="color: #ffffff; font-weight: 750; font-size: 13.5px; margin-bottom: 5px; display: flex; align-items: center; gap: 8px;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#fb7185" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <?php esc_html_e('PHP-Payload & Exif-RCE Abwehr', 'vgt-sentinel'); ?>
                    </div>
                    <div style="color: var(--vis-copy-soft); font-size: 12px; line-height: 1.55;">
                        <?php 
                        printf(
                            esc_html__('Verhindert, dass Bilder mit injiziertem %s Code das System kompromittieren (Exif-Header Exploits & Shell-Injektionen).', 'vgt-sentinel'),
                            '<code style="color:#fb7185; background:rgba(251,113,133,0.1); padding:2px 4px; border-radius:3px;">&lt;?php</code>'
                        );
                        ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</section>
