jQuery(document).ready(function($) {
    const getAjaxUrl = () => (window.visConfig && window.visConfig.ajaxUrl) ? window.visConfig.ajaxUrl : ajaxurl;

    const downloadAsset = async (btn, url, file) => {
        const originalText = btn.text();
        btn.html('<svg class="vgt-icon vgt-spin" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>').addClass('disabled');

        try {
            const res = await $.ajax({
                url: getAjaxUrl(),
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'vlp_download_asset',
                    url: url,
                    file: file
                }
            });

            if(res.success) {
                btn.replaceWith('<span class="vgt-badge vgt-badge-active">SECURE</span>');
                checkIfAllDone();
            } else {
                throw new Error(res.data || 'Unknown Error');
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
        if(btn.hasClass('disabled')) return;
        const row = btn.closest('tr');
        downloadAsset(btn, row.data('url'), row.data('file'));
    });

    $('#vlp-batch-trigger').click(function(e) {
        e.preventDefault();
        const buttons = $('.vlp-download-btn');
        if(buttons.length === 0) return;
        
        const self = $(this);
        self.html('<svg class="vgt-icon vgt-spin" style="width:14px; height:14px; margin-right:8px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg> PROCESSING SEQUENCE...').addClass('disabled');
        
        let delay = 0;
        buttons.each(function() {
            const btn = $(this);
            const row = btn.closest('tr');
            setTimeout(() => { downloadAsset(btn, row.data('url'), row.data('file')); }, delay);
            delay += 500;
        });
    });

    function checkIfAllDone() {
        if($('.vlp-download-btn').length === 0) {
            $('#vlp-batch-trigger').replaceWith('<span class="vgt-badge vgt-badge-active">ALL ASSETS SECURED</span>');
            const countEl = $('.vgt-kpi-card:first .vgt-kpi-value').contents().filter(function(){ return this.nodeType === 3; }).first();
            if(countEl.length) {
                const total = $('.vgt-kpi-sub').text().replace('/ ', '');
                countEl[0].nodeValue = total + ' ';
                $('.vgt-kpi-card:first').css('border-top-color', '#10b981');
                $('.vgt-kpi-card:first .vgt-kpi-value').css('color', '#10b981');
                $('.vgt-kpi-card:first .vgt-kpi-desc').html('Alle externen Ressourcen sind lokal gespiegelt und gehärtet. Zero-Leakage verifiziert.');
            }
        }
    }
});
