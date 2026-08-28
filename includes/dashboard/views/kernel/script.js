/**
 * VGT Clipboard Engine
 * Status: ULTRA-DIAMANT
 */
async function visCopyCode(btn, elementId) {
    const textToCopy = document.getElementById(elementId).innerText;
    try {
        await navigator.clipboard.writeText(textToCopy);
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<svg class="vgt-icon" style="width:14px;height:14px" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Kopiert';
        btn.classList.add('copied');
        
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('copied');
        }, 2000);
    } catch (err) {
        console.error('VGT Clipboard Error:', err);
        btn.innerText = 'Fehler';
    }
}
