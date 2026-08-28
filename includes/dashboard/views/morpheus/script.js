    const vgt_nonce = "<?php echo esc_js($vgt_nonce); ?>";
    const vgt_isolation_token = "<?php echo esc_js($isolation_token); ?>";

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
        const strict = cb.checked;
        const app = document.getElementById('vgt-app');
        const header = document.getElementById('vgt-header');
        const pill = document.getElementById('vgt-pill');
        const pillText = document.getElementById('vgt-pill-text');
        
        document.querySelectorAll('.vgt-mode-label').forEach(el => el.classList.remove('active'));

        if (strict) {
            app.classList.add('strict-theme');
            header.classList.replace('audit-active', 'strict-active');
            pill.classList.replace('status-audit', 'status-strict');
            pillText.innerText = 'ENFORCEMENT ACTIVE';
            document.querySelector('.label-strict').classList.add('active');
        } else {
            app.classList.remove('strict-theme');
            header.classList.replace('strict-active', 'audit-active');
            pill.classList.replace('status-strict', 'status-audit');
            pillText.innerText = 'LEARNING MODE';
            document.querySelector('.label-audit').classList.add('active');
        }
        
        const formData = new FormData();
        formData.append('action', 'vgt_morpheus_toggle_strict');
        formData.append('strict_mode', strict);
        formData.append('nonce', vgt_nonce);
        formData.append('isolation_token', vgt_isolation_token);

        fetch(ajaxurl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(!data.success) alert('VGT Error: Konnte Modus nicht speichern.');
        });
    }

    function vgtPreviewJson(data, slug) {
        const modal = document.getElementById('vgt-json-modal');
        document.getElementById('vgt-modal-plugin-title').innerText = `morpheus@vgt-core:~/proposed/${slug}.json`;
        document.getElementById('vgt-json-content').innerText = JSON.stringify(data, null, 4);
        
        const approveBtn = document.getElementById('vgt-modal-approve-btn');
        approveBtn.onclick = () => {
            modal.style.display = 'none';
            vgtApprove(slug, approveBtn);
        };
        
        modal.style.display = 'flex';
    }

    function vgtApprove(slug, btnElement) {
        btnElement.innerHTML = `<svg class="vgt-icon spin" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Compiling...`;
        
        const formData = new FormData();
        formData.append('action', 'vgt_morpheus_approve_ai');
        formData.append('plugin_slug', slug);
        formData.append('nonce', vgt_nonce); 
        formData.append('isolation_token', vgt_isolation_token);

        fetch(ajaxurl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                window.location.reload();
            } else {
                alert('VGT Error: ' + String(data.data?.message ?? 'Request failed.'));
                btnElement.innerHTML = 'Error';
            }
        });
    }

    function vgtReject(slug, btnElement) {
        if(confirm(`Vorschlag für [${slug}] verwerfen? Die Datei wird gelöscht und das Audit beginnt von vorn.`)) {
            btnElement.innerHTML = `<svg class="vgt-icon spin" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
            
            const formData = new FormData();
            formData.append('action', 'vgt_morpheus_reject_ai');
            formData.append('plugin_slug', slug);
            formData.append('nonce', vgt_nonce); 
            formData.append('isolation_token', vgt_isolation_token);

            fetch(ajaxurl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) window.location.reload();
            });
        }
    }
    
    function vgtForceDelete(slug, btnElement) {
        if(confirm(`WARNUNG: [${slug}] aus der aktiven Matrix entfernen? Im Strict-Mode wird das Plugin danach blockiert!`)) {
            btnElement.innerHTML = `<svg class="vgt-icon spin" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
            
            const formData = new FormData();
            formData.append('action', 'vgt_morpheus_delete_matrix');
            formData.append('plugin_slug', slug);
            formData.append('nonce', vgt_nonce);
            formData.append('isolation_token', vgt_isolation_token);

            fetch(ajaxurl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) window.location.reload();
            });
        }
    }

    function vgtTriggerAI(slug, btnElement) {
        const originalHtml = btnElement.innerHTML;
        btnElement.innerHTML = `<svg class="vgt-icon spin" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Backend Sync...`;
        btnElement.disabled = true;
        btnElement.classList.remove('pulse');
        
        const term = document.getElementById('vgt-terminal-stream');
        const time = new Date().toLocaleTimeString();
        vgtAppendTerminal(
            `[${time}] [UI] Triggering backend sync for ${slug}...`,
            '#00e5ff'
        );

        const formData = new FormData();
        formData.append('action', 'vgt_morpheus_trigger_ai');
        formData.append('plugin_slug', slug);
        formData.append('nonce', vgt_nonce);
        formData.append('isolation_token', vgt_isolation_token);

        fetch(ajaxurl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                vgtAppendTerminal(
                    `[${time}] [UI] API call completed successfully.`,
                    '#27c93f'
                );
                setTimeout(() => window.location.reload(), 1500);
            } else {
                const message = String(data.data?.message ?? 'Server request failed.');
                const incident = String(data.data?.incident_id ?? '');
                vgtAppendTerminal(
                    `[${time}] [SERVER ERROR] ${message}${incident ? ` [${incident}]` : ''}`,
                    '#ff4d4d',
                    'rgba(255,0,0,0.1)'
                );
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;
            }
            if (term) term.scrollTop = term.scrollHeight;
        })
        .catch(err => {
            vgtAppendTerminal(`[${time}] [AJAX ERROR] Request failed.`, '#ff4d4d');
            btnElement.innerHTML = originalHtml;
            btnElement.disabled = false;
        });
    }
