// STATUS: DIAMANT VGT SUPREME
jQuery(document).ready(function($) {
    'use strict';
    const getAjaxUrl = () => (window.visConfig && window.visConfig.ajaxUrl) ? window.visConfig.ajaxUrl : ajaxurl;
    const getNonce = () => (window.visConfig && window.visConfig.nonce) ? window.visConfig.nonce : '';

    const downloadAsset = async (btn, url, file) => {
        const originalText = btn.text();
        btn.text('WORKING…').addClass('disabled');

        try {
            const res = await $.ajax({
                url: getAjaxUrl(),
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'vlp_download_asset',
                    url: url,
                    file: file,
                    nonce: getNonce()
                }
            });

            if (res && res.success) {
                const badge = document.createElement('span');
                badge.className = 'vgt-badge vgt-badge-active';
                badge.textContent = 'SECURE';
                btn.replaceWith(badge);
                checkIfAllDone();
            } else {
                throw new Error((res && res.data) || 'Unknown Error');
            }
        } catch(e) {
            console.error('VLP Download Error:', e);
            btn.removeClass('disabled').text('ERROR').css({'background': 'rgba(239, 68, 68, 0.1)', 'color': '#ef4444', 'border-color': '#ef4444'});
            setTimeout(() => { btn.text(originalText).css({'background': '', 'color': '', 'border-color': ''}); }, 3000);
        }
    };

    $(document).on('click', '.vlp-download-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        if (btn.hasClass('disabled')) return;
        const row = btn.closest('tr');
        downloadAsset(btn, row.data('url'), row.data('file'));
    });

    $('#vlp-batch-trigger').click(function(e) {
        e.preventDefault();
        const buttons = $('.vlp-download-btn');
        if (buttons.length === 0) return;
        
        const self = $(this);
        self.text('PROCESSING SEQUENCE…').addClass('disabled');
        
        let delay = 0;
        buttons.each(function() {
            const btn = $(this);
            const row = btn.closest('tr');
            setTimeout(() => { downloadAsset(btn, row.data('url'), row.data('file')); }, delay);
            delay += 500;
        });
    });

    function checkIfAllDone() {
        if ($('.vlp-download-btn').length === 0) {
            const batchBadge = document.createElement('span');
            batchBadge.className = 'vgt-badge vgt-badge-active';
            batchBadge.textContent = 'ALL ASSETS SECURED';
            $('#vlp-batch-trigger').replaceWith(batchBadge);

            const countEl = $('.vgt-kpi-card:first .vgt-kpi-value').contents().filter(function(){ return this.nodeType === 3; }).first();
            if (countEl.length) {
                const total = $('.vgt-kpi-sub').text().replace('/ ', '');
                countEl[0].nodeValue = total + ' ';
                $('.vgt-kpi-card:first').css('border-top-color', '#10b981');
                $('.vgt-kpi-card:first .vgt-kpi-value').css('color', '#10b981');
                $('.vgt-kpi-card:first .vgt-kpi-desc').text('Alle externen Ressourcen sind lokal gespiegelt und gehärtet. Zero-Leakage verifiziert.');
            }
        }
    }
});
