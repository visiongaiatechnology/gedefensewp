// STATUS: DIAMANT VGT SUPREME
(() => {
    'use strict';

    const getNonce = () => {
        if (window.visConfig && window.visConfig.nonce) return window.visConfig.nonce;
        return '';
    };

    const getIsolationToken = () => {
        if (window.visConfig && window.visConfig.isolationToken) return window.visConfig.isolationToken;
        return '';
    };

    const getAjaxUrl = () => {
        if (window.visConfig && window.visConfig.ajaxUrl) return window.visConfig.ajaxUrl;
        if (typeof ajaxurl !== 'undefined') return ajaxurl;
        return '/wp-admin/admin-ajax.php';
    };

    function vgtAppendTerminal(message, color, background = '') {
        const terminal = document.getElementById('vgt-terminal-stream');
        if (!terminal) return;
        const line = document.createElement('span');
        line.style.color = color;
        if (background !== '') {
            line.style.background = background;
            line.style.padding = '4px';
            line.style.borderRadius = '4px';
        }
        line.textContent = String(message);
        terminal.appendChild(line);
        terminal.scrollTop = terminal.scrollHeight;
    }

    function vgtUpdateTheme(cb) {
        if (!cb) return;
        const strict = cb.checked;
        const app = document.getElementById('vgt-app');
        const header = document.getElementById('vgt-header');
        const pill = document.getElementById('vgt-pill');
        const pillText = document.getElementById('vgt-pill-text');

        document.querySelectorAll('.vgt-mode-label').forEach((el) => el.classList.remove('active'));

        if (strict) {
            if (app) app.classList.add('strict-theme');
            if (header) header.classList.replace('audit-active', 'strict-active');
            if (pill) pill.classList.replace('status-audit', 'status-strict');
            if (pillText) pillText.textContent = 'ENFORCEMENT ACTIVE';
            document.querySelector('.label-strict')?.classList.add('active');
        } else {
            if (app) app.classList.remove('strict-theme');
            if (header) header.classList.replace('strict-active', 'audit-active');
            if (pill) pill.classList.replace('status-strict', 'status-audit');
            if (pillText) pillText.textContent = 'LEARNING MODE';
            document.querySelector('.label-audit')?.classList.add('active');
        }

        const formData = new FormData();
        formData.append('action', 'vgt_morpheus_toggle_strict');
        formData.append('strict_mode', strict ? '1' : '0');
        formData.append('nonce', getNonce());
        formData.append('isolation_token', getIsolationToken());

        fetch(getAjaxUrl(), { method: 'POST', body: formData })
            .then((res) => res.json())
            .then((data) => {
                if (!data.success) alert('VGT Error: Konnte Modus nicht speichern.');
            });
    }

    function vgtPreviewJson(data, slug) {
        const modal = document.getElementById('vgt-json-modal');
        const titleEl = document.getElementById('vgt-modal-plugin-title');
        const contentEl = document.getElementById('vgt-json-content');
        if (titleEl) titleEl.textContent = `morpheus@vgt-core:~/proposed/${slug}.json`;
        if (contentEl) contentEl.textContent = JSON.stringify(data, null, 4);

        const approveBtn = document.getElementById('vgt-modal-approve-btn');
        if (approveBtn && modal) {
            approveBtn.onclick = () => {
                modal.style.display = 'none';
                vgtApprove(slug, approveBtn);
            };
        }

        if (modal) modal.style.display = 'flex';
    }

    function vgtApprove(slug, btnElement) {
        if (!btnElement) return;
        btnElement.textContent = 'Compiling…';

        const formData = new FormData();
        formData.append('action', 'vgt_morpheus_approve_ai');
        formData.append('plugin_slug', slug);
        formData.append('nonce', getNonce());
        formData.append('isolation_token', getIsolationToken());

        fetch(getAjaxUrl(), { method: 'POST', body: formData })
            .then((res) => res.json())
            .then((data) => {
                if (data && data.success) {
                    window.location.reload();
                } else {
                    alert('VGT Error: ' + String(data.data?.message ?? 'Request failed.'));
                    btnElement.textContent = 'Error';
                }
            });
    }

    function vgtReject(slug, btnElement) {
        if (confirm(`Vorschlag für [${slug}] verwerfen? Die Datei wird gelöscht und das Audit beginnt von vorn.`)) {
            if (btnElement) btnElement.textContent = 'Working…';

            const formData = new FormData();
            formData.append('action', 'vgt_morpheus_reject_ai');
            formData.append('plugin_slug', slug);
            formData.append('nonce', getNonce());
            formData.append('isolation_token', getIsolationToken());

            fetch(getAjaxUrl(), { method: 'POST', body: formData })
                .then((res) => res.json())
                .then((data) => {
                    if (data && data.success) window.location.reload();
                });
        }
    }

    function vgtForceDelete(slug, btnElement) {
        if (confirm(`WARNUNG: [${slug}] aus der aktiven Matrix entfernen? Im Strict-Mode wird das Plugin danach blockiert!`)) {
            if (btnElement) btnElement.textContent = 'Working…';

            const formData = new FormData();
            formData.append('action', 'vgt_morpheus_delete_matrix');
            formData.append('plugin_slug', slug);
            formData.append('nonce', getNonce());
            formData.append('isolation_token', getIsolationToken());

            fetch(getAjaxUrl(), { method: 'POST', body: formData })
                .then((res) => res.json())
                .then((data) => {
                    if (data && data.success) window.location.reload();
                });
        }
    }

    function vgtTriggerAI(slug, btnElement) {
        if (!btnElement) return;
        const originalText = btnElement.textContent || '';
        btnElement.textContent = 'Backend Sync…';
        btnElement.disabled = true;
        btnElement.classList.remove('pulse');

        const term = document.getElementById('vgt-terminal-stream');
        const time = new Date().toLocaleTimeString();
        vgtAppendTerminal(`[${time}] [UI] Triggering backend sync for ${slug}...`, '#00e5ff');

        const formData = new FormData();
        formData.append('action', 'vgt_morpheus_trigger_ai');
        formData.append('plugin_slug', slug);
        formData.append('nonce', getNonce());
        formData.append('isolation_token', getIsolationToken());

        fetch(getAjaxUrl(), { method: 'POST', body: formData })
            .then((res) => res.json())
            .then((data) => {
                if (data && data.success) {
                    vgtAppendTerminal(`[${time}] [UI] API call completed successfully.`, '#27c93f');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    const message = String(data.data?.message ?? 'Server request failed.');
                    const incident = String(data.data?.incident_id ?? '');
                    vgtAppendTerminal(
                        `[${time}] [SERVER ERROR] ${message}${incident ? ` [${incident}]` : ''}`,
                        '#ff4d4d',
                        'rgba(255,0,0,0.1)'
                    );
                    btnElement.textContent = originalText;
                    btnElement.disabled = false;
                }
                if (term) term.scrollTop = term.scrollHeight;
            })
            .catch(() => {
                vgtAppendTerminal(`[${time}] [AJAX ERROR] Request failed.`, '#ff4d4d');
                btnElement.textContent = originalText;
                btnElement.disabled = false;
            });
    }

    window.vgtUpdateTheme = vgtUpdateTheme;
    window.vgtPreviewJson = vgtPreviewJson;
    window.vgtApprove = vgtApprove;
    window.vgtReject = vgtReject;
    window.vgtForceDelete = vgtForceDelete;
    window.vgtTriggerAI = vgtTriggerAI;
})();
