// STATUS: DIAMANT VGT SUPREME
(() => {
    'use strict';

    const button = document.getElementById('vgt-oracle-ping');
    const meter = document.querySelector('.vgt-oracle-meter');
    const fill = document.getElementById('vgt-oracle-meter-fill');
    const latency = document.getElementById('vgt-oracle-latency');
    const grade = document.getElementById('vgt-oracle-grade');
    const status = document.getElementById('vgt-oracle-ping-status');
    const config = window.visOracleDiagnostics;
    if (!(button instanceof HTMLButtonElement) || !(meter instanceof HTMLElement) || !(fill instanceof HTMLElement)
        || !(latency instanceof HTMLElement) || !(grade instanceof HTMLElement) || !(status instanceof HTMLElement)
        || typeof config !== 'object' || config === null) {
        return;
    }

    button.addEventListener('click', async () => {
        button.disabled = true;
        status.textContent = 'Authentifizierter Groq-Ping läuft…';
        try {
            const body = new FormData();
            body.append('action', 'vis_oracle_ping');
            body.append('nonce', String(config.nonce || ''));
            const response = await fetch(String(config.ajaxUrl || ''), {
                method: 'POST',
                credentials: 'same-origin',
                body,
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            const payload = await response.json();
            if (!response.ok || payload.success !== true || typeof payload.data !== 'object') {
                throw new Error(typeof payload.data?.message === 'string' ? payload.data.message : 'Ping-Test fehlgeschlagen.');
            }
            const score = Math.max(0, Math.min(100, Number(payload.data.score) || 0));
            const milliseconds = Math.max(1, Number(payload.data.latencyMs) || 1);
            fill.style.width = `${score}%`;
            meter.setAttribute('aria-valuenow', String(Math.round(score)));
            latency.textContent = `${Math.round(milliseconds)} ms`;
            grade.textContent = String(payload.data.grade || 'OK');
            status.textContent = String(payload.data.message || 'Groq-Uplink erreichbar.');
        } catch (error) {
            fill.style.width = '0%';
            meter.setAttribute('aria-valuenow', '0');
            latency.textContent = '-- ms';
            grade.textContent = 'Offline';
            status.textContent = error instanceof Error ? error.message : 'Ping-Test fehlgeschlagen.';
        } finally {
            window.setTimeout(() => { button.disabled = false; }, 15000);
        }
    });
})();
