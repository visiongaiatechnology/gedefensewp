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

    const statusLabel = { pass: 'PASS', warn: 'WARN', fail: 'FAIL' };

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
            row.append(indexNode, flow, element('span', `vsc-state is-${boundary.state}`, boundary.state));
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
            header.append(identity, element('span', 'vsc-module-state', state));
            const rights = element('div', 'vsc-rights');
            module.rights.forEach((right) => rights.appendChild(element('span', '', right)));
            const footer = element('div', 'vsc-module-footer');
            footer.append(element('span', '', module.integrity ? `sha256:${module.integrity}` : 'source unavailable'));
            card.append(header, rights, footer);
            fragment.appendChild(card);
        });
        target.replaceChildren(fragment);
    }

    function render(snapshot) {
        const score = Number(snapshot.score) || 0;
        text('vsc-score', score);
        text('vsc-posture', String(snapshot.status || 'attention').toUpperCase());
        text('vsc-pass', snapshot.summary?.passed ?? 0);
        text('vsc-warn', snapshot.summary?.warnings ?? 0);
        text('vsc-fail', snapshot.summary?.failed ?? 0);
        text('vsc-modules', snapshot.summary?.modules ?? 0);
        const ring = byId('vsc-score-ring');
        if (ring) {
            ring.style.setProperty('--vsc-score', `${score * 3.6}deg`);
            ring.dataset.state = snapshot.status || 'attention';
            ring.setAttribute('aria-label', `Security score ${score} of 100`);
        }
        const date = new Date(snapshot.generatedAt);
        text('vsc-last-run', `Last run ${Number.isNaN(date.getTime()) ? 'now' : date.toLocaleTimeString()} · ${snapshot.durationMs ?? 0} ms`);
        renderChecks(Array.isArray(snapshot.checks) ? snapshot.checks : []);
        renderBoundaries(Array.isArray(snapshot.boundaries) ? snapshot.boundaries : []);
        renderModules(Array.isArray(snapshot.modules) ? snapshot.modules : []);
    }

    const snapshotNode = byId('vsc-snapshot');
    try { render(JSON.parse(snapshotNode?.textContent || '{}')); } catch { text('vsc-terminal-text', 'Initial snapshot rejected.'); }

    byId('vsc-run-test')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        if (!(button instanceof HTMLButtonElement) || button.disabled) return;
        button.disabled = true;
        button.classList.add('is-running');
        text('vsc-terminal-text', 'Executing deep architecture verification…');
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
            text('vsc-terminal-text', `Deep verification complete: ${payload.data.summary.passed} passed, ${payload.data.summary.failed} failed.`);
        } catch {
            text('vsc-terminal-text', 'Self-test failed safely. No security state was modified.');
        } finally {
            button.disabled = false;
            button.classList.remove('is-running');
        }
    });
})();
