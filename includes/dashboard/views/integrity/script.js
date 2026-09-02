/* STATUS: DIAMANT VGT SUPREME */
'use strict';
jQuery(document).ready(function($) {
        // Tab Switching Engine
        $(document).on('click', '.vgt-tab-btn', function(e) {
            e.preventDefault();
            var $tab = $(this);
            var filter = $tab.data('filter');

            $('.vgt-tab-btn').removeClass('vgt-tab-active');
            $tab.addClass('vgt-tab-active');

            var visibleCount = 0;
            $('.vgt-anomaly-row').each(function() {
                var rowCat = $(this).data('category');
                if (filter === 'all' || rowCat === filter) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });

            if (visibleCount === 0) {
                $('#vgt-tab-empty-notice').show();
            } else {
                $('#vgt-tab-empty-notice').hide();
            }
        });

        $(document).on('click', '.vis-inspect-file', function(e) {
            e.preventDefault();
            var file = $(this).data('file');
            var $btn = $(this);
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text('…');
            
            $.post(ajaxurl, {
                action: 'vis_inspect_file',
                file: file,
                nonce: document.getElementById('vgt-integrity-view')?.dataset.inspectNonce || ''
            }, function(res) {
                $btn.prop('disabled', false).text(originalText);
                if (res.success) {
                    var titleNode = document.getElementById('vis-source-title');
                    var codeNode = document.getElementById('vis-source-code');
                    if (titleNode) {
                        var icon = document.createElement('span');
                        icon.className = 'dashicons dashicons-editor-code';
                        icon.style.color = '#10b981';
                        titleNode.replaceChildren(icon, document.createTextNode(' ' + res.data.filename + ' (' + res.data.path + ')'));
                    }
                    if (codeNode) {
                        codeNode.textContent = res.data.content;
                    }
                    $('#vis-source-modal').show();
                    setTimeout(function() {
                        $('#vis-source-modal').addClass('vis-show');
                    }, 50);
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Fehler beim Laden der Datei.');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text(originalText);
                alert('Netzwerkfehler: Verbindung zum Server fehlgeschlagen.');
            });
        });
        
        function hideSourceModal() {
            $('#vis-source-modal').removeClass('vis-show');
            setTimeout(function() {
                $('#vis-source-modal').hide();
            }, 300);
        }
        
        $('#vis-source-close, #vis-source-ok').off('click').on('click', function(e) {
            e.preventDefault();
            hideSourceModal();
        });
        
        // Escape modal with ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#vis-source-modal').is(':visible')) {
                hideSourceModal();
            }
        });
    });
