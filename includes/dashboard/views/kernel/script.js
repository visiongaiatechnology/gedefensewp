// STATUS: DIAMANT VGT SUPREME
(() => {
    'use strict';

    async function visCopyCode(btn, elementId) {
        const target = document.getElementById(elementId);
        if (!target || !btn) return;
        const textToCopy = target.textContent || '';
        try {
            await navigator.clipboard.writeText(textToCopy);
            const originalText = btn.textContent || '';
            btn.textContent = 'KOPIERT!';
            btn.classList.add('copied');
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('copied');
            }, 2000);
        } catch (err) {
            console.error('VGT Clipboard Error:', err);
            btn.textContent = 'FEHLER';
        }
    }

    const initKernel = () => {
        const copyBtn = document.getElementById('btn-copy-kernel-script');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                visCopyCode(copyBtn, 'vgt-kernel-bash-code');
            });
        }
    };

    window.visCopyCode = visCopyCode;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initKernel);
    } else {
        initKernel();
    }
})();
