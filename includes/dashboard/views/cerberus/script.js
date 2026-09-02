// STATUS: DIAMANT VGT SUPREME
(() => {
    'use strict';

    let currentUnbanIp = null;

    const modal = document.getElementById('vgt-unban-modal');
    const ipDisplay = document.getElementById('vgt-modal-ip-display');
    const executeBtn = document.getElementById('vgt-execute-unban-btn');

    window.vgt_trigger_unban_modal = (ip) => {
        if (!modal || !ipDisplay) return;
        currentUnbanIp = ip;
        ipDisplay.textContent = ip;
        modal.style.display = 'flex';
        setTimeout(() => { modal.classList.add('active'); }, 10);
    };

    window.vgt_close_unban_modal = () => {
        if (!modal) return;
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            currentUnbanIp = null;
            if (executeBtn) {
                executeBtn.textContent = 'EXECUTE UNBAN';
                executeBtn.classList.remove('processing');
            }
        }, 300);
    };

    if (executeBtn instanceof HTMLButtonElement) {
        executeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!currentUnbanIp) return;

            executeBtn.textContent = 'PROCESSING…';
            executeBtn.classList.add('processing');

            const nonce = (window.visConfig && window.visConfig.nonce) ? window.visConfig.nonce : '';
            const ajaxUrl = (window.visConfig && window.visConfig.ajaxUrl) ? window.visConfig.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

            jQuery.post(ajaxUrl, {
                action: 'vis_dashboard_unban_ip',
                ip: currentUnbanIp,
                nonce: nonce
            }, (response) => {
                if (response && response.success) {
                    executeBtn.textContent = 'SUCCESS';
                    executeBtn.style.background = '#10b981';
                    executeBtn.style.borderColor = '#10b981';
                    setTimeout(() => { location.reload(); }, 600);
                } else {
                    alert('VGT DB ERROR: ' + ((response && response.data) ? response.data : 'Unban failed.'));
                    window.vgt_close_unban_modal();
                }
            }).fail(() => {
                alert('VGT NETWORK ERROR: Server Uplink failed.');
                window.vgt_close_unban_modal();
            });
        });
    }
})();
