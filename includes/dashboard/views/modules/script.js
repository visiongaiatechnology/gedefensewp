/* STATUS: DIAMANT VGT SUPREME */
'use strict';
document.addEventListener('DOMContentLoaded', function() {
    var dropzone = document.getElementById('vgt-addon-dropzone');
    var fileInput = document.getElementById('vgt-addon-file-input');
    var statusEl = document.getElementById('vgt-upload-status');
    var managerRoot = document.getElementById('vgt-addon-manager');
    var nonce = managerRoot ? managerRoot.dataset.nonce || '' : '';

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
