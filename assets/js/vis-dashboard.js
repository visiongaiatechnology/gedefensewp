jQuery(document).ready(function($) {
    'use strict';

    // VGT: Scanner Logik restlos entfernt. Wird vom APEX Client (vis-scanner-client.js) übernommen.
    // Diese Datei kümmert sich nur noch um den WAF Compiler und generische Modals.

    const config = window.visConfig || {};
    const nonce = config.nonce;

    if (!nonce) {
        console.error('VisionGaia: Security Nonce missing.');
        return;
    }
    
    // --- UI HELPER: VISIONGAIA MODAL (Wird vom WAF-Compiler benötigt) ---
    function showModal(title, message, type = 'info', onConfirm = null) {
        // Cleanup old modals
        $('.vis-modal-backdrop').remove();

        let icon = 'dashicons-info';
        let color = '#3b82f6';
        if (type === 'success') { icon = 'dashicons-yes-alt'; color = '#10b981'; }
        if (type === 'error') { icon = 'dashicons-warning'; color = '#ef4444'; }
        if (type === 'confirm') { icon = 'dashicons-shield'; color = '#f59e0b'; }

        const modalHtml = `
            <div class="vis-modal-backdrop">
                <div class="vis-modal-content">
                    <div class="vis-modal-header">
                        <div class="vis-modal-title">
                            <span class="dashicons ${icon}" style="color:${color}"></span> ${title}
                        </div>
                        <button class="vis-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
                    </div>
                    <div class="vis-modal-body">
                        ${message}
                    </div>
                    <div class="vis-modal-footer">
                        ${type === 'confirm' ? 
                            '<button class="vis-btn vis-btn-ghost vis-modal-cancel">ABBRECHEN</button>' : ''
                        }
                        <button class="vis-btn vis-btn-neon vis-modal-ok">
                            ${type === 'confirm' ? 'BESTÄTIGEN' : 'OK, VERSTANDEN'}
                        </button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        
        requestAnimationFrame(() => {
            $('.vis-modal-backdrop').addClass('vis-show');
        });

        $('.vis-modal-close, .vis-modal-cancel').click(function() {
            closeModal();
        });

        $('.vis-modal-ok').click(function() {
            closeModal();
            if (onConfirm) onConfirm();
        });
    }

    function closeModal() {
        $('.vis-modal-backdrop').removeClass('vis-show');
        setTimeout(() => { $('.vis-modal-backdrop').remove(); }, 300);
    }

    // =========================================================================
    // VGT ZEUS WAF COMPILER AJAX HANDLER
    // =========================================================================
    $(document).on('submit', '#vis-zeus-settings-form', function(e) {
        e.preventDefault();
        
        let $form = $(this);
        let $btn = $form.find('.vgt-btn-primary');
        let originalText = $btn.html();
        
        // Spin State
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-right: 8px;"></span> COMPILING...');
        
        $.post(config.ajaxUrl, $form.serialize(), function(res) {
            if (res.success) {
                showModal('WAF COMPILED & DEPLOYED', res.data.message, 'success', () => location.reload());
            } else {
                showModal('COMPILATION FAILED', res.data ? res.data.message : 'Unbekannter Fehler bei der WAF-Kompilierung.', 'error');
                $btn.prop('disabled', false).html(originalText);
            }
        }).fail(function() {
            showModal('CONNECTION LOST', 'Verbindung zum GeDefense-WP-Core abgebrochen.', 'error');
            $btn.prop('disabled', false).html(originalText);
        });
    });

});
