<?php
// STATUS: PLATIN VGT SUPREME
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$manager = '\\VisionGaia\\GeDefense\\Modules\\SecureDownloads\\DownloadManager';
$records = class_exists($manager) ? $manager::all() : [];
$attachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => 100,
    'orderby' => 'date',
    'order' => 'DESC',
    'fields' => 'ids',
]);
$status = isset($_GET['download-status']) && is_string($_GET['download-status']) ? sanitize_key(wp_unslash($_GET['download-status'])) : '';
$messages = [
    'created' => __('Die geschützte Download-Kopie wurde erstellt.', 'vgt-sentinel'),
    'updated' => __('Der Downloadstatus wurde aktualisiert.', 'vgt-sentinel'),
    'deleted' => __('Die geschützte Kopie wurde gelöscht. Das Original in der Mediathek bleibt erhalten.', 'vgt-sentinel'),
    'validation' => __('Die ausgewählte Datei verletzt eine Größen- oder Eingabegrenze.', 'vgt-sentinel'),
    'rejected' => __('Die Datei wurde durch die Sicherheitsprüfung abgelehnt.', 'vgt-sentinel'),
    'storage' => __('Der geschützte Downloadspeicher ist momentan nicht verfügbar.', 'vgt-sentinel'),
    'fatal' => __('Der Vorgang konnte nicht abgeschlossen werden.', 'vgt-sentinel'),
];
?>

<section class="vgt-titan" aria-label="GeDefense Secure Download Manager">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('IMMUTABLE FILE DISTRIBUTION & AEGIS TRUST PATH', 'vgt-sentinel'); ?></p>
            <h2>SECURE DOWNLOADS</h2>
            <p><?php esc_html_e('Erstellt eine unveränderliche, nicht direkt webzugängliche Kopie einer Mediendatei. Nur der erzeugte GeDefense-Link erhält einen eng begrenzten AEGIS-Vertrauenspfad; andere ZIP- oder Dateianfragen bleiben vollständig geschützt.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Downloads Status">
            <span><small><?php esc_html_e('SECURE REPOSITORY', 'vgt-sentinel'); ?></small><strong><?php echo class_exists($manager) ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('ACTIVE PACKAGES', 'vgt-sentinel'); ?></small><strong style="color: var(--vis-cyan);"><?php echo esc_html((string)count($records)); ?></strong></span>
            <span><small><?php esc_html_e('INTEGRITY LOCK', 'vgt-sentinel'); ?></small><strong>SHA-256 VAULT</strong></span>
            <span><small><?php esc_html_e('ACCESS PATH', 'vgt-sentinel'); ?></small><strong>AEGIS RESTRICTED</strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('SECURE PACKAGES', 'vgt-sentinel'); ?></small><strong style="color: var(--vis-cyan);"><?php echo esc_html((string)count($records)); ?> FILES</strong></article>
        <article><small><?php esc_html_e('MEDIA LIBRARY SCOPE', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)count($attachments)); ?> MEDIA</strong></article>
        <article><small><?php esc_html_e('TRUST PATH ENGINE', 'vgt-sentinel'); ?></small><strong>AEGIS L7</strong></article>
        <article><small><?php esc_html_e('DIRECT ACCESS', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;">BLOCKED (403)</strong></article>
        <article><small><?php esc_html_e('CRYPTOGRAPHIC SEAL', 'vgt-sentinel'); ?></small><strong>SHA-256 IMMUTABLE</strong></article>
        <article><small><?php esc_html_e('TRACKING ENGINE', 'vgt-sentinel'); ?></small><strong>IN-MEMORY TELEMETRY</strong></article>
    </div>

    <?php if (isset($messages[$status])): ?>
        <div class="vis-dashboard-notice <?php echo in_array($status, ['created','updated','deleted'], true) ? 'is-success' : 'is-error'; ?>" style="margin-bottom: 20px;">
            <span><?php echo esc_html($messages[$status]); ?></span>
        </div>
    <?php endif; ?>

    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('01 / PACKAGE GENERATOR', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Neuen geschützten Download registrieren', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge" style="border-color: rgba(94, 234, 212, 0.4); color: var(--vis-cyan);">
                <?php esc_html_e('L7 TRUST LINK', 'vgt-sentinel'); ?>
            </span>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="vis_download_register">
            <?php wp_nonce_field('vis_download_register'); ?>
            
            <div class="vgt-titan-wide-field">
                <label>
                    <span><?php esc_html_e('Datei aus der WordPress-Mediathek auswählen', 'vgt-sentinel'); ?></span>
                    <select name="attachment_id" required class="vgt-titan-select">
                        <option value=""><?php esc_html_e('Datei auswählen …', 'vgt-sentinel'); ?></option>
                        <?php foreach ($attachments as $attachmentId):
                            $path = get_attached_file((int)$attachmentId, true);
                            if (!is_string($path) || !is_file($path)) continue;
                            $size = filesize($path);
                            $label = get_the_title((int)$attachmentId) . ' — ' . basename($path) . ' (' . size_format(is_int($size) ? $size : 0) . ')';
                        ?>
                            <option value="<?php echo esc_attr((string)$attachmentId); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="vgt-titan-actions" style="margin-top: 20px; justify-content: center; width: 100%;">
                <button type="submit" style="width: 100%; max-width: 280px; justify-content: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <?php esc_html_e('GESCHÜTZTEN LINK ERZEUGEN', 'vgt-sentinel'); ?>
                </button>
            </div>
        </form>
    </section>

    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('02 / DISTRIBUTION MATRIX', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Registrierte geschützte Downloads', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge">
                <?php echo esc_html(sprintf(__('%d Pakete aktiv', 'vgt-sentinel'), count($records))); ?>
            </span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--vis-line); text-align: left;">
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 30%;"><?php esc_html_e('DATEI & METADATEN', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 18%;"><?php esc_html_e('INTEGRITÄTS-HASH', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 10%;"><?php esc_html_e('DOWNLOADS', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 27%;"><?php esc_html_e('GEDEFENSE-LINK', 'vgt-sentinel'); ?></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 15%; text-align: right;"><?php esc_html_e('AKTIONEN', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($records === []): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px 14px; color: var(--vis-copy-muted);">
                            <?php esc_html_e('Noch keine sicheren Downloads registriert.', 'vgt-sentinel'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($records as $record): $url = $manager::url((string)$record->public_id); ?>
                    <tr style="border-bottom: 1px solid rgba(148, 184, 224, 0.08);">
                        <td style="padding: 12px 14px;">
                            <span style="font-weight: 750; color: #ffffff; display: block; margin-bottom: 4px;"><?php echo esc_html((string)$record->display_name); ?></span>
                            <div style="font-size: 11px; color: var(--vis-copy-soft); display: flex; align-items: center; gap: 8px;">
                                <span><?php echo esc_html(size_format((int)$record->file_size)); ?></span>
                                <span>·</span>
                                <span><?php echo esc_html((string)$record->mime_type); ?></span>
                                <span>·</span>
                                <?php if (!empty($record->enabled)): ?>
                                    <span style="color: #34d399; font-weight: 700;"><?php esc_html_e('AKTIV', 'vgt-sentinel'); ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-weight: 700;"><?php esc_html_e('PAUSIERT', 'vgt-sentinel'); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 12px 14px;">
                            <code style="font-family: ui-monospace, monospace; font-size: 11px; background: rgba(4, 11, 24, 0.7); border: 1px solid var(--vis-line); border-radius: 4px; padding: 3px 6px; color: var(--vis-cyan);"><?php echo esc_html(substr((string)$record->file_hash, 0, 16)); ?>…</code>
                        </td>
                        <td style="padding: 12px 14px;">
                            <span style="font-family: ui-monospace, monospace; font-weight: 750; color: #ffffff;"><?php echo esc_html((string)(int)$record->download_count); ?></span>
                        </td>
                        <td style="padding: 12px 14px;">
                            <input type="text" readonly value="<?php echo esc_attr($url); ?>" class="vis-copyable-url" data-select-on-focus style="font-size: 11px; font-family: ui-monospace, monospace;">
                        </td>
                        <td style="padding: 12px 14px; text-align: right;">
                            <div style="display: inline-flex; gap: 8px; align-items: center;">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0;">
                                    <input type="hidden" name="action" value="vis_download_toggle">
                                    <input type="hidden" name="public_id" value="<?php echo esc_attr((string)$record->public_id); ?>">
                                    <?php wp_nonce_field('vis_download_toggle'); ?>
                                    <button type="submit" style="min-height: 28px; padding: 4px 10px; font-size: 10px; font-weight: 700;">
                                        <?php echo !empty($record->enabled) ? esc_html__('Pausieren', 'vgt-sentinel') : esc_html__('Aktivieren', 'vgt-sentinel'); ?>
                                    </button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0;">
                                    <input type="hidden" name="action" value="vis_download_delete">
                                    <input type="hidden" name="public_id" value="<?php echo esc_attr((string)$record->public_id); ?>">
                                    <?php wp_nonce_field('vis_download_delete'); ?>
                                    <button type="submit" class="is-danger" style="min-height: 28px; padding: 4px 10px; font-size: 10px; font-weight: 700;">
                                        <?php esc_html_e('Löschen', 'vgt-sentinel'); ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
