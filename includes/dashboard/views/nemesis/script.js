// STATUS: DIAMANT VGT SUPREME
(() => {
    'use strict';

    const appendLog = (terminal, className, level, message) => {
        if (!(terminal instanceof HTMLElement)) return;
        const code = document.createElement('code');
        code.className = className;
        code.textContent = `[${new Date().toLocaleTimeString()}] [${level}] ${message}`;
        terminal.appendChild(code);
    };

    const initialize = () => {
        const trigger = document.getElementById('vgt-exp-trigger');
        const content = document.getElementById('vgt-exp-content');
        if (trigger instanceof HTMLButtonElement && content instanceof HTMLElement) {
            trigger.addEventListener('click', () => {
                const active = trigger.classList.toggle('active');
                content.style.maxHeight = active ? `${content.scrollHeight}px` : '0px';
                content.style.opacity = active ? '1' : '0';
                content.style.marginTop = active ? '20px' : '0';
            });
        }

        const toggle = document.getElementById('nemesis_enabled');
        if (!(toggle instanceof HTMLInputElement)) return;
        toggle.addEventListener('change', () => {
            const enabled = toggle.checked;
            const dynamicContent = document.getElementById('nemesis-dynamic-content');
            const badgeText = document.getElementById('badge-text-nemesis');
            const badge = document.getElementById('nemesis-main-badge');
            const label = document.getElementById('toggle-label-nemesis');
            const terminal = document.getElementById('nemesis-terminal');

            dynamicContent?.classList.toggle('vgt-disabled', !enabled);
            if (badgeText) badgeText.textContent = enabled ? 'DECEPTION MATRIX: ENGAGED (SAVE REQUIRED)' : 'SYSTEM OFFLINE';
            if (badge) badge.className = `vgt-status-badge ${enabled ? 'active' : 'offline'}`;
            if (label) label.textContent = enabled ? 'ENGAGED (SAVE REQUIRED)' : 'STANDBY';
            document.querySelectorAll('.node-status').forEach((node) => node.classList.toggle('online', enabled));

            if (terminal instanceof HTMLElement) {
                terminal.replaceChildren();
                appendLog(terminal, enabled ? 'log-info' : 'log-critical', enabled ? 'SYSTEM' : 'ERROR', enabled
                    ? 'Bounded deception configuration changed. Save required.'
                    : 'Deception Matrix halted.');
            }
        });
    };

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', initialize, {once: true})
        : initialize();
})();
