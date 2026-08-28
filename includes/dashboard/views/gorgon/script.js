// VGT SUPREME: Gehärteter AJAX Wrapper - Fallback Mechanismen verhindern ReferenceErrors
    const vgtAjax = (action, data = {}) => {
        const targetUrl = (typeof window.visConfig !== 'undefined' && window.visConfig.ajaxUrl) 
                          ? window.visConfig.ajaxUrl 
                          : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
                          
        return jQuery.post(targetUrl, {
            action: 'vgt_gorgon_' + action,
            security: '<?php echo wp_create_nonce("vgt_gorgon_nonce"); ?>',
            ...data
        });
    };

    function vgtEnableGorgon() {
        const btn = document.getElementById('btn-activate-gorgon');
        btn.innerHTML = 'Linking...';
        
        vgtAjax('toggle', { enabled: true }).done(res => {
            if(res.success) {
                document.getElementById('vgt-overlay').style.display = 'none';
                document.getElementById('vgt-gorgon-app').dataset.enabled = '1';
                
                // Synchronisiere Hidden-Input, damit Global-Save Gorgon nicht sofort wieder killt
                const hiddenInput = document.getElementById('vgt-gorgon-enabled-input');
                if (hiddenInput) hiddenInput.value = '1';
                
                checkNexusHealth();
            } else {
                alert('Aktivierung fehlgeschlagen. WP AJAX Fehler.');
                btn.innerHTML = 'Activate Gorgon';
            }
        }).fail(() => {
            alert('Netzwerkfehler zum lokalen WordPress-Backend.');
            btn.innerHTML = 'Activate Gorgon';
        });
    }

    // --- REAL-TIME NEXUS HEALTH CHECK (SERVER-SIDE PING & LIVE DEBUGGING) ---
    function checkNexusHealth(fromButton = false) {
        const app = document.getElementById('vgt-gorgon-app');
        const pill = document.getElementById('realtime-status-pill');
        const text = document.getElementById('realtime-status-text');
        const glow = document.getElementById('nexus-bridge-glow');
        const card = document.getElementById('nexus-bridge-card');
        const pingBtnText = document.getElementById('btn-test-link-text');
        
        const isEnabled = app.dataset.enabled === '1';
        const syncUrl = document.getElementById('vgt-nexus-endpoint').value;
        const syncKey = document.getElementById('vgt-nexus-key').value;

        if (!isEnabled) {
            updatePill('GRID OFFLINE', 'offline');
            return;
        }
        
        if (!syncUrl || !syncKey) {
            updatePill('AUTH REQUIRED', 'pending');
            return;
        }

        // CHIRURGISCHER FIX: Live-Save erzwingen, wenn manuell getriggert wird.
        if (fromButton) {
            if(pingBtnText) pingBtnText.innerText = 'Syncing...';
            updatePill('UPDATING BRIDGE...', 'pending');
            
            vgtAjax('update_config', { url: syncUrl, key: syncKey }).done(res => {
                if(res.success) {
                    executePing();
                } else {
                    if(pingBtnText) pingBtnText.innerText = 'Ping Nexus';
                    updatePill('SYNC REJECTED', 'offline');
                }
            }).fail(() => {
                if(pingBtnText) pingBtnText.innerText = 'Ping Nexus';
                updatePill('NETWORK ERROR', 'offline');
            });
            return;
        }

        executePing();

        function executePing() {
            if(pingBtnText) pingBtnText.innerText = 'Pinging...';
            updatePill('VERIFYING LINK...', 'pending');

            vgtAjax('ping_nexus', { url: syncUrl }).done(res => {
                if(pingBtnText) pingBtnText.innerText = 'Ping Nexus';
                if (res.success) {
                    updatePill('LINK SECURED', 'online');
                    glow.style.background = 'radial-gradient(circle at 100% 50%, rgba(0, 255, 136, 0.05), transparent 70%)';
                    card.style.borderColor = 'rgba(0, 255, 136, 0.3)';
                } else {
                    updatePill('NEXUS UNRECOGNIZED', 'offline');
                    setOfflineTheme(glow, card);
                    
                    if(res.data && res.data.debug_status) {
                        console.error(`VGT NEXUS PING FEHLGESCHLAGEN!\n\nHTTP Status Code: ${res.data.debug_status}\n\nAntwort vom Server:\n${res.data.debug_body}`);
                    }
                }
            }).fail(() => {
                if(pingBtnText) pingBtnText.innerText = 'Ping Nexus';
                updatePill('NEXUS TIMEOUT', 'offline');
                setOfflineTheme(glow, card);
            });
        }

        function updatePill(msg, state) {
            text.innerText = msg;
            pill.className = `vgt-pill ${state}`;
        }
        
        function setOfflineTheme(glow, card) {
            glow.style.background = 'radial-gradient(circle at 100% 50%, rgba(255, 0, 60, 0.05), transparent 70%)';
            card.style.borderColor = 'rgba(255, 0, 60, 0.3)';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(checkNexusHealth, 500);
    });

    function vgtSaveConfig() {
        const url = document.getElementById('vgt-nexus-endpoint').value;
        const key = document.getElementById('vgt-nexus-key').value;
        if(!url || !key) return alert('URL und Key werden benötigt.');

        const btn = document.getElementById('btn-save-config');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Updating...';

        vgtAjax('update_config', { url, key }).done(res => {
            if(res.success) {
                document.getElementById('vgt-gorgon-app').dataset.key = '1';
                btn.innerHTML = originalText;
                checkNexusHealth(); // Validierung nach erfolgreichem Update
            } else {
                alert('Speichern fehlgeschlagen: ' + (res.data ? res.data.message : 'Unknown'));
                btn.innerHTML = originalText;
            }
        }).fail(() => {
            alert('Speichern fehlgeschlagen (AJAX Netz-Fehler)');
            btn.innerHTML = originalText;
        });
    }

    function vgtSyncNow() {
        const ico = document.getElementById('vgt-sync-ico');
        ico.classList.add('vgt-spin');
        vgtAjax('sync').done(res => {
            if(res.success) {
                location.reload();
            } else {
                alert('Sync fehlgeschlagen: ' + (res.data ? res.data.message : ''));
                ico.classList.remove('vgt-spin');
            }
        });
    }

    function vgtIntegrateNode() { document.getElementById('vgt-node-modal').style.display = 'flex'; }
    function vgtCloseModal() { document.getElementById('vgt-node-modal').style.display = 'none'; }

    function vgtSaveNode() {
        const data = {
            id: document.getElementById('wiz-id').value,
            table: document.getElementById('wiz-table').value,
            ip_col: document.getElementById('wiz-ip').value,
            type_col: document.getElementById('wiz-type').value,
            time_col: document.getElementById('wiz-time').value
        };
        vgtAjax('add_node', data).done(() => location.reload());
    }

    function vgtDropNode(id) {
        if(confirm(`Node [${id}] dauerhaft vom Grid trennen?`)) {
            vgtAjax('remove_node', { node_id: id }).done(() => location.reload());
        }
    }
