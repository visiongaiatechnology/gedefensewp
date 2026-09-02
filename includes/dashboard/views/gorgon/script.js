// STATUS: DIAMANT VGT SUPREME
(() => {
    'use strict';

    const getApp = () => document.getElementById('vgt-gorgon-app');

    const getGorgonNonce = () => {
        const app = getApp();
        if (app && app.dataset.gorgonNonce) return app.dataset.gorgonNonce;
        if (window.visConfig && window.visConfig.nonce) return window.visConfig.nonce;
        return '';
    };

    const getAjaxUrl = () => {
        if (window.visConfig && window.visConfig.ajaxUrl) return window.visConfig.ajaxUrl;
        if (typeof ajaxurl !== 'undefined') return ajaxurl;
        return '/wp-admin/admin-ajax.php';
    };

    const vgtAjax = (action, data = {}) => {
        return jQuery.post(getAjaxUrl(), {
            action: 'vgt_gorgon_' + action,
            security: getGorgonNonce(),
            ...data
        });
    };

    const vgtEnableGorgon = () => {
        const btn = document.getElementById('btn-activate-gorgon');
        if (btn) btn.textContent = 'Linking…';

        vgtAjax('toggle', { enabled: true }).done((res) => {
            if (res && res.success) {
                const overlay = document.getElementById('vgt-overlay');
                const app = getApp();
                if (overlay) overlay.style.display = 'none';
                if (app) app.dataset.enabled = '1';

                const hiddenInput = document.getElementById('vgt-gorgon-enabled-input');
                if (hiddenInput instanceof HTMLInputElement) hiddenInput.value = '1';

                checkNexusHealth();
            } else {
                alert('Aktivierung fehlgeschlagen. WP AJAX Fehler.');
                if (btn) btn.textContent = 'Activate Gorgon';
            }
        }).fail(() => {
            alert('Netzwerkfehler zum lokalen WordPress-Backend.');
            if (btn) btn.textContent = 'Activate Gorgon';
        });
    };

    const checkNexusHealth = (fromButton = false) => {
        const app = getApp();
        if (!app) return;
        const pill = document.getElementById('realtime-status-pill');
        const text = document.getElementById('realtime-status-text');
        const glow = document.getElementById('nexus-bridge-glow');
        const card = document.getElementById('nexus-bridge-card');
        const pingBtnText = document.getElementById('btn-test-link-text');

        const isEnabled = app.dataset.enabled === '1';
        const syncUrlEl = document.getElementById('vgt-nexus-endpoint');
        const syncKeyEl = document.getElementById('vgt-nexus-key');
        const syncUrl = syncUrlEl instanceof HTMLInputElement ? syncUrlEl.value.trim() : '';
        const syncKey = syncKeyEl instanceof HTMLInputElement ? syncKeyEl.value.trim() : '';

        const updatePill = (msg, state) => {
            if (text) text.textContent = msg;
            if (pill) pill.className = `vgt-pill ${state}`;
        };

        const setOfflineTheme = () => {
            if (glow) glow.style.background = 'radial-gradient(circle at 100% 50%, rgba(255, 0, 60, 0.05), transparent 70%)';
            if (card) card.style.borderColor = 'rgba(255, 0, 60, 0.3)';
        };

        if (!isEnabled) {
            updatePill('GRID OFFLINE', 'offline');
            return;
        }

        if (!syncUrl || !syncKey) {
            updatePill('AUTH REQUIRED', 'pending');
            return;
        }

        const executePing = () => {
            if (pingBtnText) pingBtnText.textContent = 'Pinging...';
            updatePill('VERIFYING LINK...', 'pending');

            vgtAjax('ping_nexus', { url: syncUrl }).done((res) => {
                if (pingBtnText) pingBtnText.textContent = 'Ping Nexus';
                if (res && res.success) {
                    updatePill('LINK SECURED', 'online');
                    if (glow) glow.style.background = 'radial-gradient(circle at 100% 50%, rgba(0, 255, 136, 0.05), transparent 70%)';
                    if (card) card.style.borderColor = 'rgba(0, 255, 136, 0.3)';
                } else {
                    updatePill('NEXUS UNRECOGNIZED', 'offline');
                    setOfflineTheme();
                }
            }).fail(() => {
                if (pingBtnText) pingBtnText.textContent = 'Ping Nexus';
                updatePill('NEXUS TIMEOUT', 'offline');
                setOfflineTheme();
            });
        };

        if (fromButton) {
            if (pingBtnText) pingBtnText.textContent = 'Syncing...';
            updatePill('UPDATING BRIDGE...', 'pending');

            vgtAjax('update_config', { url: syncUrl, key: syncKey }).done((res) => {
                if (res && res.success) {
                    executePing();
                } else {
                    if (pingBtnText) pingBtnText.textContent = 'Ping Nexus';
                    updatePill('SYNC REJECTED', 'offline');
                }
            }).fail(() => {
                if (pingBtnText) pingBtnText.textContent = 'Ping Nexus';
                updatePill('NETWORK ERROR', 'offline');
            });
            return;
        }

        executePing();
    };

    const vgtSaveConfig = () => {
        const urlEl = document.getElementById('vgt-nexus-endpoint');
        const keyEl = document.getElementById('vgt-nexus-key');
        const url = urlEl instanceof HTMLInputElement ? urlEl.value.trim() : '';
        const key = keyEl instanceof HTMLInputElement ? keyEl.value.trim() : '';
        if (!url || !key) {
            alert('URL und Key werden benötigt.');
            return;
        }

        const btn = document.getElementById('btn-save-config');
        const originalText = btn ? (btn.textContent || 'Update Config') : '';
        if (btn) btn.textContent = 'Updating…';

        vgtAjax('update_config', { url, key }).done((res) => {
            if (res && res.success) {
                const app = getApp();
                if (app) app.dataset.key = '1';
                if (btn) btn.textContent = originalText;
                checkNexusHealth();
            } else {
                alert('Speichern fehlgeschlagen: ' + (res && res.data ? res.data.message : 'Unknown'));
                if (btn) btn.textContent = originalText;
            }
        }).fail(() => {
            alert('Speichern fehlgeschlagen (AJAX Netz-Fehler)');
            if (btn) btn.textContent = originalText;
        });
    };

    const vgtSyncNow = () => {
        const ico = document.getElementById('vgt-sync-ico');
        if (ico) ico.classList.add('vgt-spin');
        vgtAjax('sync').done((res) => {
            if (res && res.success) {
                location.reload();
            } else {
                alert('Sync fehlgeschlagen: ' + (res && res.data ? res.data.message : ''));
                if (ico) ico.classList.remove('vgt-spin');
            }
        }).fail(() => {
            alert('Netzwerkfehler beim Synchronisieren.');
            if (ico) ico.classList.remove('vgt-spin');
        });
    };

    const vgtIntegrateNode = () => {
        const modal = document.getElementById('vgt-node-modal');
        if (modal) modal.style.display = 'flex';
    };

    const vgtCloseModal = () => {
        const modal = document.getElementById('vgt-node-modal');
        if (modal) modal.style.display = 'none';
    };

    const vgtSaveNode = () => {
        const id = (document.getElementById('wiz-id') || {}).value || '';
        const table = (document.getElementById('wiz-table') || {}).value || '';
        const ip_col = (document.getElementById('wiz-ip') || {}).value || '';
        const type_col = (document.getElementById('wiz-type') || {}).value || '';
        const time_col = (document.getElementById('wiz-time') || {}).value || '';

        vgtAjax('add_node', { id, table, ip_col, type_col, time_col }).done(() => location.reload());
    };

    const vgtDropNode = (id) => {
        if (!id) return;
        if (confirm(`Node [${id}] dauerhaft vom Grid trennen?`)) {
            vgtAjax('remove_node', { node_id: id }).done(() => location.reload());
        }
    };

    const initGorgon = () => {
        if (!getApp()) return;

        document.getElementById('btn-sync-now')?.addEventListener('click', vgtSyncNow);
        document.getElementById('btn-save-config')?.addEventListener('click', vgtSaveConfig);
        document.getElementById('btn-test-link')?.addEventListener('click', () => checkNexusHealth(true));
        document.getElementById('btn-activate-gorgon')?.addEventListener('click', vgtEnableGorgon);
        document.getElementById('btn-integrate-node')?.addEventListener('click', vgtIntegrateNode);
        document.getElementById('btn-close-node-modal')?.addEventListener('click', vgtCloseModal);
        document.getElementById('btn-save-node')?.addEventListener('click', vgtSaveNode);

        document.querySelectorAll('.vgt-drop-node-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const nodeId = btn.getAttribute('data-node-id') || '';
                vgtDropNode(nodeId);
            });
        });

        setTimeout(checkNexusHealth, 400);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGorgon);
    } else {
        initGorgon();
    }
})();
