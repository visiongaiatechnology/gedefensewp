// STATUS: PLATIN
(() => {
    'use strict';
    const root = document.getElementById('vis-security-center');
    if (!root) return;

    const byId = (id) => document.getElementById(id);
    const text = (id, value) => { const node = byId(id); if (node) node.textContent = String(value); };
    const element = (tag, className, value = '') => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (value !== '') node.textContent = String(value);
        return node;
    };

    const i18n = JSON.parse(byId('vsc-i18n')?.textContent || '{}');
    const t = (key, fallback) => (i18n && typeof i18n[key] === 'string' && i18n[key] !== '') ? i18n[key] : fallback;

    const statusLabel = { 
        pass: t('pass', 'PASS'), 
        warn: t('warn', 'WARN'), 
        fail: t('fail', 'FAIL') 
    };

    const postureLabel = {
        hardened: t('hardened', 'HARDENED'),
        guarded: t('guarded', 'GUARDED'),
        attention: t('attention', 'ATTENTION'),
        initializing: t('initializing', 'INITIALIZING')
    };

    const stateLabel = {
        loaded: t('loaded', 'LOADED'),
        ready: t('ready', 'READY'),
        off: t('off', 'OFF'),
        enforced: t('enforced', 'ENFORCED'),
        mapped: t('mapped', 'MAPPED'),
        closed: t('closed', 'CLOSED'),
        healthy: t('healthy', 'HEALTHY'),
        failed: t('failed', 'FAILED'),
        degraded: t('degraded', 'DEGRADED'),
        incomplete: t('incomplete', 'INCOMPLETE'),
        disabled: t('disabled', 'DISABLED'),
        experimental: t('experimental', 'EXPERIMENTAL')
    };

    function renderChecks(checks) {
        const target = byId('vsc-checks');
        if (!target) return;
        const fragment = document.createDocumentFragment();
        checks.forEach((check) => {
            const row = element('div', `vsc-check is-${check.status}`);
            const signal = element('span', 'vsc-check-signal');
            signal.setAttribute('aria-hidden', 'true');
            const copy = element('div', 'vsc-check-copy');
            copy.append(element('strong', '', check.label), element('small', '', check.detail));
            const meta = element('div', 'vsc-check-meta');
            meta.append(element('span', 'vsc-domain', check.domain), element('b', '', statusLabel[check.status] || 'UNKNOWN'));
            row.append(signal, copy, meta);
            fragment.appendChild(row);
        });
        target.replaceChildren(fragment);
        text('vsc-check-count', checks.length);
    }

    function renderBoundaries(boundaries) {
        const target = byId('vsc-boundaries');
        if (!target) return;
        const fragment = document.createDocumentFragment();
        boundaries.forEach((boundary, index) => {
            const row = element('div', 'vsc-boundary');
            const indexNode = element('span', 'vsc-boundary-index', String(index + 1).padStart(2, '0'));
            const flow = element('div', 'vsc-boundary-flow');
            const route = element('div', 'vsc-boundary-route');
            route.append(element('strong', '', boundary.from), element('i', '', '→'), element('strong', '', boundary.to));
            flow.append(route, element('small', '', boundary.policy));
            const stateText = stateLabel[String(boundary.state).toLowerCase()] || boundary.state;
            row.append(indexNode, flow, element('span', `vsc-state is-${boundary.state}`, stateText));
            fragment.appendChild(row);
        });
        target.replaceChildren(fragment);
    }

    function renderModules(modules) {
        const target = byId('vsc-module-grid');
        if (!target) return;
        const fragment = document.createDocumentFragment();
        modules.forEach((module) => {
            const state = !module.present || !module.enabled ? 'off' : (module.loaded ? 'loaded' : 'ready');
            const card = element('article', `vsc-module is-${state}`);
            const header = element('div', 'vsc-module-header');
            const identity = element('div', 'vsc-module-identity');
            identity.append(element('span', 'vsc-module-glyph', module.label.slice(0, 2).toUpperCase()), element('div', ''));
            identity.lastChild.append(element('strong', '', module.label), element('small', '', module.zone));
            header.append(identity, element('span', 'vsc-module-state', stateLabel[state] || state));
            const rights = element('div', 'vsc-rights');
            module.rights.forEach((right) => rights.appendChild(element('span', '', right)));
            const footer = element('div', 'vsc-module-footer');
            footer.append(element('span', '', module.integrity ? `sha256:${module.integrity}` : t('source_unavailable', 'source unavailable')));
            card.append(header, rights, footer);
            fragment.appendChild(card);
        });
        target.replaceChildren(fragment);
    }

    function renderTitan(health) {
        const target = byId('vsc-titan-health');
        if (!target) return;
        const fragment = document.createDocumentFragment();
        Object.entries(health && typeof health === 'object' ? health : {}).forEach(([component, value]) => {
            const state = String(value || 'UNKNOWN').toUpperCase();
            const normalized = ['HEALTHY', 'GENERATED', 'GENERATED_EXPORT_ONLY'].includes(state) ? 'loaded' : (state === 'DISABLED' ? 'off' : 'ready');
            const card = element('article', `vsc-module is-${normalized}`);
            const header = element('div', 'vsc-module-header');
            const identity = element('div', 'vsc-module-identity');
            identity.append(element('span', 'vsc-module-glyph', 'TT'), element('div', ''));
            identity.lastChild.append(element('strong', '', component.replaceAll('_', ' ')), element('small', '', t('titan_control', 'TITAN CONTROL')));
            header.append(identity, element('span', 'vsc-module-state', stateLabel[state.toLowerCase()] || state));
            card.append(header);
            fragment.appendChild(card);
        });
        target.replaceChildren(fragment);
    }

    function render(snapshot) {
        const score = Number(snapshot.score) || 0;
        text('vsc-score', score);
        const postureKey = String(snapshot.status || 'attention').toLowerCase();
        text('vsc-posture', postureLabel[postureKey] || postureKey.toUpperCase());
        text('vsc-pass', snapshot.summary?.passed ?? 0);
        text('vsc-warn', snapshot.summary?.warnings ?? 0);
        text('vsc-fail', snapshot.summary?.failed ?? 0);
        text('vsc-modules', snapshot.summary?.modules ?? 0);
        const ring = byId('vsc-score-ring');
        if (ring) {
            ring.style.setProperty('--vsc-score', `${score * 3.6}deg`);
            ring.dataset.state = snapshot.status || 'attention';
            const scoreAriaTpl = t('score_aria', 'Security score %d of 100');
            ring.setAttribute('aria-label', scoreAriaTpl.replace('%d', String(score)));
        }
        const date = new Date(snapshot.generatedAt);
        const timeStr = Number.isNaN(date.getTime()) ? 'now' : date.toLocaleTimeString();
        const durationStr = String(snapshot.durationMs ?? 0);
        const lastRunTpl = t('last_run', 'Last run %s · %s ms');
        text('vsc-last-run', lastRunTpl.replace('%s', timeStr).replace('%s', durationStr).replace('%d', durationStr));

        renderChecks(Array.isArray(snapshot.checks) ? snapshot.checks : []);
        renderBoundaries(Array.isArray(snapshot.boundaries) ? snapshot.boundaries : []);
        renderModules(Array.isArray(snapshot.modules) ? snapshot.modules : []);
        renderTitan(snapshot.titan);
    }

    const snapshotNode = byId('vsc-snapshot');
    try { render(JSON.parse(snapshotNode?.textContent || '{}')); } catch { text('vsc-terminal-text', t('term_initial_rejected', 'Initial snapshot rejected.')); }

    byId('vsc-run-test')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        if (!(button instanceof HTMLButtonElement) || button.disabled) return;
        button.disabled = true;
        button.classList.add('is-running');
        text('vsc-terminal-text', t('term_executing', 'Executing deep architecture verification…'));
        const body = new URLSearchParams({ action: 'vis_security_center_test', nonce: window.visConfig?.nonce || '' });
        try {
            const response = await fetch(window.visConfig?.ajaxUrl || '', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body
            });
            if (!response.ok) throw new Error('transport');
            const payload = await response.json();
            if (!payload?.success || !payload.data) throw new Error('verification');
            render(payload.data);
            const completeTpl = t('term_complete', 'Deep verification complete: %d passed, %d failed.');
            const passedCount = payload.data.summary?.passed ?? 0;
            const failedCount = payload.data.summary?.failed ?? 0;
            text('vsc-terminal-text', completeTpl.replace('%d', String(passedCount)).replace('%d', String(failedCount)));
        } catch {
            text('vsc-terminal-text', t('term_failed_safe', 'Self-test failed safely. No security state was modified.'));
        } finally {
            button.disabled = false;
            button.classList.remove('is-running');
        }
    });
})();
