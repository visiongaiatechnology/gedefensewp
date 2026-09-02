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
<section class="vgt-downloads-panel vgt-downloads-panel-top">
    <h2>
        <svg class="vgt-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #00f2ff;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <?php esc_html_e('GeDefense Secure Download Manager', 'vgt-sentinel'); ?>
    </h2>
    <p class="vgt-downloads-desc"><?php esc_html_e('Erstellt eine unveränderliche, nicht direkt webzugängliche Kopie einer Mediendatei. Nur der erzeugte GeDefense-Link erhält einen eng begrenzten AEGIS-Vertrauenspfad; andere ZIP- oder Dateianfragen bleiben vollständig geschützt.', 'vgt-sentinel'); ?></p>
    
    <?php if (isset($messages[$status])): ?>
        <div class="vgt-notice <?php echo in_array($status, ['created','updated','deleted'], true) ? 'vgt-notice-success' : 'vgt-notice-error'; ?>">
            <svg class="vgt-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span><?php echo esc_html($messages[$status]); ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="vgt-downloads-form">
        <input type="hidden" name="action" value="vis_download_register">
        <?php wp_nonce_field('vis_download_register'); ?>
        <label class="vgt-downloads-label">
            <span><?php esc_html_e('Datei aus der WordPress-Mediathek auswählen', 'vgt-sentinel'); ?></span>
            <select name="attachment_id" required class="vgt-downloads-select">
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
        <button type="submit" class="vgt-downloads-btn-primary">
            <svg class="vgt-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <?php esc_html_e('Geschützten Link erzeugen', 'vgt-sentinel'); ?>
        </button>
    </form>
</section>

<section class="vgt-downloads-panel">
    <h3>
        <svg class="vgt-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #38bdf8;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
        <?php esc_html_e('Registrierte Downloads', 'vgt-sentinel'); ?>
    </h3>
    
    <div class="vgt-downloads-table-wrap">
        <table class="vgt-downloads-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Datei & Metadaten', 'vgt-sentinel'); ?></th>
                    <th><?php esc_html_e('Integritäts-Hash', 'vgt-sentinel'); ?></th>
                    <th><?php esc_html_e('Downloads', 'vgt-sentinel'); ?></th>
                    <th><?php esc_html_e('GeDefense-Link', 'vgt-sentinel'); ?></th>
                    <th><?php esc_html_e('Aktionen', 'vgt-sentinel'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($records === []): ?>
                <tr>
                    <td colspan="5" class="vgt-empty-cell">
                        <?php esc_html_e('Noch keine sicheren Downloads registriert.', 'vgt-sentinel'); ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($records as $record): $url = $manager::url((string)$record->public_id); ?>
                <tr>
                    <td>
                        <span class="vgt-download-title"><?php echo esc_html((string)$record->display_name); ?></span>
                        <div class="vgt-download-meta">
                            <span><?php echo esc_html(size_format((int)$record->file_size)); ?></span>
                            <span>·</span>
                            <span><?php echo esc_html((string)$record->mime_type); ?></span>
                            <span>·</span>
                            <?php if (!empty($record->enabled)): ?>
                                <span class="vgt-badge-active"><?php esc_html_e('AKTIV', 'vgt-sentinel'); ?></span>
                            <?php else: ?>
                                <span class="vgt-badge-paused"><?php esc_html_e('PAUSIERT', 'vgt-sentinel'); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><code class="vgt-hash-code"><?php echo esc_html(substr((string)$record->file_hash, 0, 16)); ?>…</code></td>
                    <td><span class="vgt-count-badge"><?php echo esc_html((string)(int)$record->download_count); ?></span></td>
                    <td>
                        <div class="vgt-url-group">
                            <input type="text" readonly value="<?php echo esc_attr($url); ?>" class="vgt-url-input vis-download-url" data-select-on-focus>
                        </div>
                    </td>
                    <td>
                        <div class="vgt-actions-cell">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="vis_download_toggle">
                                <input type="hidden" name="public_id" value="<?php echo esc_attr((string)$record->public_id); ?>">
                                <?php wp_nonce_field('vis_download_toggle'); ?>
                                <button class="vgt-btn-action" type="submit">
                                    <?php echo !empty($record->enabled) ? esc_html__('Pausieren', 'vgt-sentinel') : esc_html__('Aktivieren', 'vgt-sentinel'); ?>
                                </button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="vis_download_delete">
                                <input type="hidden" name="public_id" value="<?php echo esc_attr((string)$record->public_id); ?>">
                                <?php wp_nonce_field('vis_download_delete'); ?>
                                <button class="vgt-btn-action vgt-btn-danger" type="submit">
                                    <?php esc_html_e('Kopie löschen', 'vgt-sentinel'); ?>
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
