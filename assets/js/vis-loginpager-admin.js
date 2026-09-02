// STATUS: DIAMANT VGT SUPREME
document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const preview = document.getElementById('vis-loginpager-preview');
    if (!(preview instanceof HTMLElement)) return;

    // Color bindings
    const bgPicker = document.getElementById('loginpager-bg');
    const bgHex = document.getElementById('loginpager-bg-hex');
    const accentPicker = document.getElementById('loginpager-accent');
    const accentHex = document.getElementById('loginpager-accent-hex');
    const mockBtn = document.getElementById('lp-mock-btn');
    const mockDot = document.getElementById('lp-mock-dot');

    const safeHttpUrl = (value) => {
        if (value === '') return '';
        try {
            const parsed = new URL(value, window.location.origin);
            return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '';
        } catch (error) {
            return '';
        }
    };

    function syncBg(val) {
        preview.style.setProperty('--login-bg', val);
        preview.style.backgroundColor = val;
    }

    function syncAccent(val) {
        preview.style.setProperty('--login-accent', val);
        if (mockBtn) mockBtn.style.backgroundColor = val;
        if (mockDot) {
            mockDot.style.backgroundColor = val;
            mockDot.style.boxShadow = '0 0 10px ' + val;
        }
    }

    if (bgPicker && bgHex) {
        bgPicker.addEventListener('input', () => {
            bgHex.value = bgPicker.value;
            syncBg(bgPicker.value);
        });
        bgHex.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(bgHex.value)) {
                bgPicker.value = bgHex.value;
                syncBg(bgHex.value);
            }
        });
    }

    if (accentPicker && accentHex) {
        accentPicker.addEventListener('input', () => {
            accentHex.value = accentPicker.value;
            syncAccent(accentPicker.value);
        });
        accentHex.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(accentHex.value)) {
                accentPicker.value = accentHex.value;
                syncAccent(accentHex.value);
            }
        });
    }

    // Image URL binding
    const imgInput = document.getElementById('loginpager-image');
    if (imgInput instanceof HTMLInputElement) {
        imgInput.addEventListener('input', () => {
            const clean = safeHttpUrl(imgInput.value.trim());
            preview.style.setProperty('--login-image', clean === '' ? 'none' : `url('${clean}')`);
            preview.style.backgroundImage = clean === '' ? 'none' : `url('${clean}')`;
        });
    }

    // Logo URL binding
    const logoInput = document.getElementById('loginpager-logo');
    const logoWrap = document.getElementById('lp-mock-logo-wrap');
    const logoImg = document.getElementById('lp-mock-logo-img');
    const titleHeader = document.getElementById('lp-mock-title-text');

    if (logoInput instanceof HTMLInputElement) {
        logoInput.addEventListener('input', () => {
            const clean = safeHttpUrl(logoInput.value.trim());
            if (clean !== '') {
                if (logoImg) logoImg.src = clean;
                if (logoWrap) logoWrap.style.display = 'block';
                if (titleHeader) titleHeader.style.display = 'none';
            } else {
                if (logoWrap) logoWrap.style.display = 'none';
                if (titleHeader) titleHeader.style.display = 'flex';
            }
        });
    }

    // Title text binding
    const titleInput = document.getElementById('loginpager-title');
    const titleVal = document.getElementById('lp-mock-title-val');
    if (titleInput instanceof HTMLInputElement && titleVal) {
        titleInput.addEventListener('input', () => {
            titleVal.textContent = titleInput.value.trim() || 'WordPress';
        });
    }

    // Subtitle text binding
    const subInput = document.getElementById('loginpager-subtitle');
    const subText = document.getElementById('lp-mock-sub-text');
    if (subInput instanceof HTMLInputElement && subText) {
        subInput.addEventListener('input', () => {
            subText.textContent = subInput.value.trim() || 'ZERO-TRUST AUTHENTICATION GATEWAY';
        });
    }

    document.querySelectorAll('.lp-swatch-btn[data-login-bg][data-login-accent]').forEach((button) => {
        button.addEventListener('click', () => {
            const bg = button.getAttribute('data-login-bg') || '';
            const accent = button.getAttribute('data-login-accent') || '';
            if (!/^#[0-9a-f]{6}$/i.test(bg) || !/^#[0-9a-f]{6}$/i.test(accent)) return;
            if (bgPicker instanceof HTMLInputElement) bgPicker.value = bg;
            if (bgHex instanceof HTMLInputElement) bgHex.value = bg;
            if (accentPicker instanceof HTMLInputElement) accentPicker.value = accent;
            if (accentHex instanceof HTMLInputElement) accentHex.value = accent;
            syncBg(bg);
            syncAccent(accent);
        });
    });
});
