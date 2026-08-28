jQuery(document).ready(function($) {
    $('#vis-zeus-settings-form').on('submit', function(e) {
        e.preventDefault(); // Stoppt den Standard-Reload
        
        let $form = $(this);
        let $btn = $form.find('.vgt-btn-primary');
        let originalText = $btn.html();
        
        $btn.prop('disabled', true).html('<svg class="vgt-icon spin" style="width:16px; height:16px; margin-right:8px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg> COMPILING KERNEL...');
        
        let ajaxTarget = (typeof window.visConfig !== 'undefined' && window.visConfig.ajaxUrl) ? window.visConfig.ajaxUrl : ajaxurl;

        $.post(ajaxTarget, $form.serialize(), function(res) {
            if (res.success) {
                location.reload(); // Erfolgreich - Seite neu laden um ONLINE Status zu zeigen
            } else {
                alert('WAF COMPILATION FAILED: ' + (res.data ? res.data.message : 'Unknown Error'));
                $btn.prop('disabled', false).html(originalText);
            }
        }).fail(function() {
            // Wenn der AJAX Request geblockt wird, fällt er auf den NATIVEN POST in Core zurück!
            $form.off('submit').submit();
        });
    });
});
