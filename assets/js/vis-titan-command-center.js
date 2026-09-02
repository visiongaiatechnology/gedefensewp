/* STATUS: DIAMANT VGT SUPREME */
(() => {
    'use strict';
    const cfg = window.visTitanCommandCenter;
    if (!cfg || typeof cfg.endpoint !== 'string' || typeof cfg.nonce !== 'string') return;
    const status = document.getElementById('vgt-titan-action-status');
    document.querySelectorAll('[data-titan-operation]').forEach((button) => {
        button.addEventListener('click', async () => {
            const operation = button.getAttribute('data-titan-operation') || '';
            if (!['generate_candidate', 'validate', 'activate_report_only', 'activate_enforce', 'rollback'].includes(operation)) return;
            button.disabled = true;
            if (status) status.textContent = 'Operation läuft …';
            const body = new FormData();
            body.append('action', 'vis_titan_policy_action');
            body.append('_wpnonce', cfg.nonce);
            body.append('operation', operation);
            try {
                const response = await fetch(cfg.endpoint, {method: 'POST', body, credentials: 'same-origin', redirect: 'follow'});
                if (!response.ok) throw new Error('HTTP ' + response.status);
                window.location.reload();
            } catch (error) {
                if (status) status.textContent = 'Operation abgelehnt: ' + (error instanceof Error ? error.message : 'UNKNOWN');
                button.disabled = false;
            }
        });
    });
    const gateButton = document.querySelector('[data-titan-gate-link]');
    const gateOutput = document.getElementById('vgt-titan-gate-output');
    if (gateButton instanceof HTMLButtonElement && gateOutput) {
        gateButton.addEventListener('click', async () => {
            gateButton.disabled = true;
            gateOutput.textContent = 'Kurzlebiger Link wird signiert …';
            const body = new FormData();
            body.append('action', 'vis_titan_generate_gate_link');
            body.append('_wpnonce', cfg.gateNonce || '');
            try {
                const response = await fetch(cfg.endpoint, {method: 'POST', body, credentials: 'same-origin'});
                if (!response.ok) throw new Error('HTTP ' + response.status);
                gateOutput.textContent = await response.text();
            } catch (error) {
                gateOutput.textContent = 'Linkerzeugung abgelehnt.';
            } finally {
                gateButton.disabled = false;
            }
        });
    }

    const browserChecks = {
        'vgt-titan-check-trusted-types': typeof window.trustedTypes === 'object' ? 'SUPPORTED' : 'NOT SUPPORTED',
        'vgt-titan-check-coop': window.crossOriginIsolated ? 'OBSERVED' : 'UNKNOWN',
        'vgt-titan-check-permissions': document.permissionsPolicy || document.featurePolicy ? 'SUPPORTED' : 'UNKNOWN',
        'vgt-titan-check-oac': typeof window.originAgentCluster === 'boolean' ? (window.originAgentCluster ? 'OBSERVED' : 'NOT OBSERVED') : 'UNKNOWN'
    };
    Object.entries(browserChecks).forEach(([id, value]) => {
        const node = document.getElementById(id);
        if (node) node.textContent = value;
    });
})();
