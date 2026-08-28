<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

$opt = get_option('vis_config', []);
$nonce = wp_create_nonce('vis_nonce');

$modules = VIS_Module_Registry::all();
?>
<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $modules_css_path = __DIR__ . '/modules/style.css';
    if (is_readable($modules_css_path)) {
        echo file_get_contents($modules_css_path);
    }
    ?>
    .vgt-addon-uploader {
        border: 2px dashed rgba(59, 130, 246, 0.4);
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        background: rgba(15, 23, 42, 0.4);
        transition: all 0.2s ease;
        margin-bottom: 24px;
        cursor: pointer;
    }
    .vgt-addon-uploader:hover, .vgt-addon-uploader.dragover {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.08);
    }
    .vgt-badge-installed {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .vgt-badge-uninstalled {
        background: rgba(148, 163, 184, 0.1);
        color: #94a3b8;
        border: 1px solid rgba(148, 163, 184, 0.2);
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .vgt-btn-danger-outline {
        background: transparent;
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #ef4444;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .vgt-btn-danger-outline:hover {
        background: rgba(239, 68, 68, 0.15);
        border-color: #ef4444;
    }
</style>

<div class="vgt-apex-ui">
    
    <!-- HEADER -->
    <div class="vgt-glass-panel" style="padding:24px; margin-bottom: 24px;">
        <h3 style="margin-top:0; color:#fff; font-size:18px; border-bottom:1px solid var(--vgt-border); padding-bottom:12px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
            <span><?php esc_html_e('GeDefense WP Add-On Hub & Modul-Verwaltung', 'vgt-sentinel'); ?></span>
            <span class="vgt-badge-installed"><?php esc_html_e('OPEN CORE ARCHITECTURE', 'vgt-sentinel'); ?></span>
        </h3>
        <p style="color:var(--vgt-text-dim); font-size:13px; line-height:1.6; margin-bottom:0;">
            <?php esc_html_e('Der GeDefense Security Core läuft standardmäßig schlank und eigenständig. Erweiterte Business-Module (z. B. Datenschutz/VLP, Lightweight Builder, SEO Architect) können hier als Add-On ZIP-Pakete hochgeladen und sicher verwaltet werden.', 'vgt-sentinel'); ?>
        </p>
    </div>

    <!-- ADD-ON UPLOADER -->
    <div class="vgt-addon-uploader" id="vgt-addon-dropzone">
        <input type="file" id="vgt-addon-file-input" accept=".zip" style="display:none;">
        <div style="display:flex; flex-direction:column; align-items:center; gap:10px;">
            <svg style="width:42px; height:42px; color:#3b82f6;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            <div style="font-size:15px; font-weight:600; color:#fff;">
                <?php esc_html_e('Offizielles GeDefense Add-On Paket (.zip) hochladen', 'vgt-sentinel'); ?>
            </div>
            <div style="font-size:12px; color:var(--vgt-text-dim);">
                <?php esc_html_e('Datei hierher ziehen oder klicken, um eine ZIP-Datei auszuwählen', 'vgt-sentinel'); ?>
            </div>
            <div id="vgt-upload-status" style="margin-top:8px; font-size:12px; font-weight:600; display:none;"></div>
        </div>
    </div>

    <!-- MODULE CARDS -->
    <div class="vgt-grid-3">
        <?php foreach ($modules as $id => $mod): 
            $is_installed = VIS_Module_Registry::is_installed($id);
            $is_enabled = VIS_Module_Registry::enabled($id, $opt);
            $config_key = $mod['config_key'];
        ?>
        <div class="vgt-glass-panel" style="display:flex; flex-direction:column; justify-content:space-between; height:100%; min-height: 290px; padding: 24px;">
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4 style="margin:0; font-size:16px; color:#fff;"><?php echo esc_html(__($mod['label'], 'vgt-sentinel')); ?></h4>
                    <?php if ($is_installed): ?>
                        <label class="vgt-switch">
                            <input type="checkbox" name="vis_config[<?php echo esc_attr($config_key); ?>]" value="1" <?php checked(!isset($opt[$config_key]) || !empty($opt[$config_key])); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    <?php else: ?>
                        <span class="vgt-badge-uninstalled"><?php esc_html_e('NICHT INSTALLIERT', 'vgt-sentinel'); ?></span>
                    <?php endif; ?>
                </div>

                <div style="margin-bottom:12px;">
                    <?php if ($is_installed): ?>
                        <span class="vgt-badge-installed"><?php printf(esc_html__('BEREIT (v%s)', 'vgt-sentinel'), esc_html($mod['version'])); ?></span>
                    <?php else: ?>
                        <span style="font-size:11px; color:#3b82f6; font-weight:600;"><?php esc_html_e('Optionales Add-On', 'vgt-sentinel'); ?></span>
                    <?php endif; ?>
                </div>

                <p style="font-size:12px; color:var(--vgt-text-dim); line-height:1.5; margin-top:0;">
                    <?php echo esc_html(__($mod['desc'], 'vgt-sentinel')); ?>
                </p>
            </div>

            <div style="margin-top:20px; border-top:1px dashed rgba(255,255,255,0.05); padding-top:15px; font-size:11px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:var(--vgt-text-dim);"><?php esc_html_e('Status:', 'vgt-sentinel'); ?></span>
                    <span style="color:<?php echo $is_installed ? '#10b981' : '#94a3b8'; ?>; font-weight:bold; font-family:monospace;">
                        <?php echo $is_installed ? ($is_enabled ? __('AKTIV', 'vgt-sentinel') : __('DEAKTIVIERT', 'vgt-sentinel')) : __('FEHLT', 'vgt-sentinel'); ?>
                    </span>
                </div>
                
                <?php if ($is_installed): ?>
                    <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                        <button type="button" class="vgt-btn-danger-outline vgt-uninstall-addon-btn" data-addon="<?php echo esc_attr($id); ?>">
                            <?php esc_html_e('Add-On löschen', 'vgt-sentinel'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div style="color:var(--vgt-text-dim); margin-top:8px; font-size:11px;">
                        <?php esc_html_e('Laden Sie das ZIP-Paket oben hoch, um dieses Modul zu aktivieren.', 'vgt-sentinel'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dropzone = document.getElementById('vgt-addon-dropzone');
    var fileInput = document.getElementById('vgt-addon-file-input');
    var statusEl = document.getElementById('vgt-upload-status');
    var nonce = '<?php echo esc_js($nonce); ?>';

    if (dropzone && fileInput) {
        dropzone.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT') {
                fileInput.click();
            }
        });

        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', function() {
            dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                uploadAddon(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', function() {
            if (fileInput.files && fileInput.files.length > 0) {
                uploadAddon(fileInput.files[0]);
            }
        });
    }

    function uploadAddon(file) {
        if (!file.name.toLowerCase().endsWith('.zip')) {
            alert('Bitte wählen Sie ein gültiges .zip Archiv aus.');
            return;
        }

        statusEl.style.display = 'block';
        statusEl.style.color = '#3b82f6';
        statusEl.textContent = 'Add-On wird hochgeladen und verifiziert...';

        var formData = new FormData();
        formData.append('action', 'vis_upload_addon');
        formData.append('nonce', nonce);
        formData.append('addon_zip', file);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                statusEl.style.color = '#10b981';
                statusEl.textContent = data.data.message || 'Add-On erfolgreich installiert!';
                setTimeout(function() {
                    window.location.reload();
                }, 1200);
            } else {
                statusEl.style.color = '#ef4444';
                statusEl.textContent = data.data.message || 'Upload fehlgeschlagen.';
            }
        })
        .catch(function(err) {
            statusEl.style.color = '#ef4444';
            statusEl.textContent = 'Netzwerkfehler beim Upload.';
        });
    }

    // Uninstall buttons
    document.querySelectorAll('.vgt-uninstall-addon-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var addonId = this.getAttribute('data-addon');
            if (!confirm('Möchten Sie dieses Add-On wirklich deinstallieren und vom Server entfernen?')) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'vis_uninstall_addon');
            formData.append('nonce', nonce);
            formData.append('addon_id', addonId);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    alert(data.data.message || 'Add-On deinstalliert.');
                    window.location.reload();
                } else {
                    alert(data.data.message || 'Deinstallation fehlgeschlagen.');
                }
            });
        });
    });
});
</script>
