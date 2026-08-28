document.addEventListener('DOMContentLoaded', function() {
    const styxToggle = document.getElementById('styx_enabled');
    const auditToggle = document.getElementById('styx_audit_mode');
    const wpToggle = document.getElementById('styx_block_wp_telemetry');
    if (!styxToggle) return;

    function updateUI() {
        const isEnabled = styxToggle.checked;
        const isAudit = auditToggle.checked;
        
        const dynContent = document.getElementById('styx-dynamic-content');
        const badgeText = document.getElementById('badge-text-styx');
        const badgeContainer = document.getElementById('styx-main-badge');
        const styxLabel = document.getElementById('toggle-label-styx');
        const auditLabel = document.getElementById('toggle-label-audit');
        const wpLabel = document.getElementById('toggle-label-wp');
        const pulseDot = badgeContainer.querySelector('.pulse-dot');
        
        if (isEnabled) {
            dynContent.classList.remove('vgt-disabled');
            styxLabel.innerText = 'ONLINE';
            styxLabel.style.color = 'var(--vgt-styx)';
            
            if (isAudit) {
                badgeText.innerText = 'AUDIT MODE: AWAITING SAVE';
                badgeContainer.className = 'vgt-status-badge pending';
                auditLabel.style.color = '#ffbd2e';
            } else {
                badgeText.innerText = 'STRICT MODE: AWAITING SAVE';
                badgeContainer.className = 'vgt-status-badge active';
                auditLabel.style.color = '#666';
            }
        } else {
            dynContent.classList.add('vgt-disabled');
            badgeText.innerText = 'SHIELD OFFLINE';
            badgeContainer.className = 'vgt-status-badge offline';
            styxLabel.innerText = 'STANDBY';
            styxLabel.style.color = '#888';
            auditLabel.style.color = '#666';
        }

        // Handle WP Block UI State
        if (wpToggle && wpLabel) {
            wpLabel.style.color = wpToggle.checked ? '#bc13fe' : '#666';
            if (wpToggle.checked && isEnabled) {
                badgeText.innerText = badgeText.innerText.replace('AWAITING SAVE', '+ WP BLOCKED');
                badgeContainer.style.boxShadow = '0 0 20px rgba(188,19,254,0.3)';
                badgeContainer.style.borderColor = 'rgba(188,19,254,0.5)';
                if(pulseDot) pulseDot.style.color = '#bc13fe';
            } else {
                badgeContainer.style.boxShadow = '';
                badgeContainer.style.borderColor = '';
                if(pulseDot) pulseDot.style.color = '';
            }
        }
    }

    styxToggle.addEventListener('change', updateUI);
    auditToggle.addEventListener('change', updateUI);
    if(wpToggle) wpToggle.addEventListener('change', updateUI);
});
