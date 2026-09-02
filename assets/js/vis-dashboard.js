/* STATUS: DIAMANT VGT SUPREME */
(() => {
    'use strict';

    const wrapper = document.querySelector('.vis-omega-wrapper');
    const navToggle = document.querySelector('.vis-nav-toggle, #vis-mobile-nav-toggle, .vis-mobile-toggle');
    const sidebar = document.getElementById('vis-dashboard-sidebar');

    const closeNavigation = () => {
        if (!(wrapper instanceof HTMLElement) || !(navToggle instanceof HTMLButtonElement)) return;
        wrapper.classList.remove('vis-nav-open');
        navToggle.setAttribute('aria-expanded', 'false');
    };

    if (wrapper instanceof HTMLElement && navToggle instanceof HTMLButtonElement && sidebar instanceof HTMLElement) {
        navToggle.addEventListener('click', () => {
            const open = wrapper.classList.toggle('vis-nav-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        sidebar.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('a')) closeNavigation();
        });
        wrapper.addEventListener('click', (event) => {
            if (event.target === wrapper && wrapper.classList.contains('vis-nav-open')) closeNavigation();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeNavigation();
        });
    }

    const configForm = document.querySelector('.vis-content > form, .vis-content form[data-vis-config]');
    if (configForm instanceof HTMLFormElement && wrapper instanceof HTMLElement) {
        configForm.addEventListener('change', () => wrapper.classList.add('vis-ui-dirty'));
        configForm.addEventListener('submit', () => wrapper.classList.remove('vis-ui-dirty'));
    }

    document.querySelectorAll('[data-select-on-focus]').forEach((field) => {
        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
            field.addEventListener('focus', () => field.select());
            field.addEventListener('click', () => field.select());
        }
    });
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) return;
        form.addEventListener('submit', (event) => {
            const prompt = form.dataset.confirm || '';
            if (prompt !== '' && !window.confirm(prompt)) event.preventDefault();
        });
    });

    const createElement = (tag, className, text = '') => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    };

    const showModal = (title, message, type = 'info', onConfirm = null) => {
        document.querySelector('.vis-modal-backdrop')?.remove();
        const backdrop = createElement('div', 'vis-modal-backdrop');
        const dialog = createElement('section', 'vis-modal-content');
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'vis-modal-title');

        const header = createElement('header', 'vis-modal-header');
        const titleNode = createElement('h2', 'vis-modal-title', String(title));
        titleNode.id = 'vis-modal-title';
        const close = createElement('button', 'vis-modal-close', '×');
        close.type = 'button';
        close.setAttribute('aria-label', 'Dialog schließen');
        header.append(titleNode, close);

        const body = createElement('div', `vis-modal-body is-${type}`, String(message));
        const footer = createElement('footer', 'vis-modal-footer');
        if (type === 'confirm') {
            const cancel = createElement('button', 'vis-btn vis-btn-ghost vis-modal-cancel', 'ABBRECHEN');
            cancel.type = 'button';
            footer.append(cancel);
        }
        const accept = createElement('button', 'vis-btn vis-btn-neon vis-modal-ok', type === 'confirm' ? 'BESTÄTIGEN' : 'OK');
        accept.type = 'button';
        footer.append(accept);
        dialog.append(header, body, footer);
        backdrop.append(dialog);
        document.body.append(backdrop);

        const dismiss = () => {
            backdrop.classList.remove('vis-show');
            window.setTimeout(() => backdrop.remove(), 180);
        };
        close.addEventListener('click', dismiss);
        backdrop.querySelector('.vis-modal-cancel')?.addEventListener('click', dismiss);
        backdrop.addEventListener('click', (event) => { if (event.target === backdrop) dismiss(); });
        accept.addEventListener('click', () => { dismiss(); if (typeof onConfirm === 'function') onConfirm(); });
        window.requestAnimationFrame(() => { backdrop.classList.add('vis-show'); accept.focus(); });
    };

    window.VISDashboard = Object.freeze({showModal});

    const cfg = window.visConfig;
    if (!cfg || typeof cfg.ajaxUrl !== 'string' || typeof cfg.nonce !== 'string') return;
    const zeusForm = document.getElementById('vis-zeus-settings-form');
    if (!(zeusForm instanceof HTMLFormElement)) return;

    zeusForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = zeusForm.querySelector('button[type="submit"], .vgt-btn-primary');
        if (!(button instanceof HTMLButtonElement)) return;
        const originalText = button.textContent || 'COMPILE';
        button.disabled = true;
        button.textContent = 'COMPILING…';
        try {
            const response = await fetch(cfg.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: new FormData(zeusForm)});
            const rawText = await response.text();
            let payload;
            try {
                payload = JSON.parse(rawText);
            } catch {
                const cleanSnippet = rawText.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(cleanSnippet.substring(0, 240) || `HTTP ${response.status}: Server returned an invalid response.`);
            }
            if (!response.ok || payload?.success !== true) throw new Error(String(payload?.data?.message || `HTTP ${response.status}`));
            showModal('WAF COMPILED & DEPLOYED', String(payload?.data?.message || 'Policy deployed.'), 'success', () => window.location.reload());
        } catch (error) {
            showModal('COMPILATION FAILED', error instanceof Error ? error.message : 'Unbekannter Fehler.', 'error');
            button.disabled = false;
            button.textContent = originalText;
        }
    });
})();
