// STATUS: DIAMANT VGT SUPREME
document.addEventListener('DOMContentLoaded', function() {
    // 1. SUB-TAB SWITCHER (OUTBOUND SHIELD vs. THREAT INTEL MATRIX)
    const styxTabs = document.querySelectorAll('#styx-nav-tabs a[data-tab]');
    const styxPanes = {
        'styx-outbound': document.getElementById('pane-styx-outbound'),
        'styx-threat-intel': document.getElementById('pane-styx-threat-intel')
    };

    if (styxTabs.length > 0) {
        function activateStyxTab(tabId, updateHistory) {
            styxTabs.forEach(function(tab) {
                const isMatch = tab.getAttribute('data-tab') === tabId;
                tab.classList.toggle('is-active', isMatch);
                tab.classList.toggle('active', isMatch);
            });
            Object.keys(styxPanes).forEach(function(id) {
                const pane = styxPanes[id];
                if (pane) {
                    if (id === tabId) {
                        pane.classList.add('is-active');
                        pane.style.display = 'block';
                    } else {
                        pane.classList.remove('is-active');
                        pane.style.display = 'none';
                    }
                }
            });
            if (updateHistory && window.history && window.history.replaceState) {
                const sec = tabId === 'styx-threat-intel' ? 'threat_intel' : 'outbound';
                const url = new URL(window.location.href);
                url.searchParams.set('styx_section', sec);
                window.history.replaceState({}, '', url.toString());
            }
        }

        styxTabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.getAttribute('data-tab');
                activateStyxTab(tabId, true);
            });
        });

        // Check hash or URL params for active tab routing
        const urlParams = new URLSearchParams(window.location.search);
        const sectionParam = urlParams.get('styx_section');
        if (sectionParam === 'threat_intel' || window.location.hash === '#styx-threat-intel') {
            activateStyxTab('styx-threat-intel', false);
        } else if (sectionParam === 'outbound') {
            activateStyxTab('styx-outbound', false);
        }
    }

    // 2. LIVE TOGGLE CONTROLS (IF PRESENT)
    const styxToggle = document.getElementById('styx_enabled');
    const auditToggle = document.getElementById('styx_audit_mode');
    const wpToggle = document.getElementById('styx_block_wp_telemetry');
    if (!styxToggle) return;

    function updateUI() {
        const isEnabled = styxToggle.checked;
        const isAudit = auditToggle ? auditToggle.checked : false;
        
        const dynContent = document.getElementById('styx-dynamic-content');
        const badgeText = document.getElementById('badge-text-styx');
        const badgeContainer = document.getElementById('styx-main-badge');
        const styxLabel = document.getElementById('toggle-label-styx');
        const auditLabel = document.getElementById('toggle-label-audit');
        const wpLabel = document.getElementById('toggle-label-wp');
        const pulseDot = badgeContainer ? badgeContainer.querySelector('.pulse-dot') : null;
        
        if (isEnabled) {
            if (dynContent) dynContent.classList.remove('vgt-disabled');
            if (styxLabel) {
                styxLabel.innerText = 'ONLINE';
                styxLabel.style.color = 'var(--vgt-styx)';
            }
            
            if (isAudit) {
                if (badgeText) badgeText.innerText = 'AUDIT MODE: AWAITING SAVE';
                if (badgeContainer) badgeContainer.className = 'vgt-status-badge pending';
                if (auditLabel) auditLabel.style.color = '#ffbd2e';
            } else {
                if (badgeText) badgeText.innerText = 'STRICT MODE: AWAITING SAVE';
                if (badgeContainer) badgeContainer.className = 'vgt-status-badge active';
                if (auditLabel) auditLabel.style.color = '#666';
            }
        } else {
            if (dynContent) dynContent.classList.add('vgt-disabled');
            if (badgeText) badgeText.innerText = 'SHIELD OFFLINE';
            if (badgeContainer) badgeContainer.className = 'vgt-status-badge offline';
            if (styxLabel) {
                styxLabel.innerText = 'STANDBY';
                styxLabel.style.color = '#888';
            }
            if (auditLabel) auditLabel.style.color = '#666';
        }

        // Handle WP Block UI State
        if (wpToggle && wpLabel) {
            wpLabel.style.color = wpToggle.checked ? '#bc13fe' : '#666';
            if (wpToggle.checked && isEnabled && badgeText && badgeContainer) {
                badgeText.innerText = badgeText.innerText.replace('AWAITING SAVE', '+ WP BLOCKED');
                badgeContainer.style.boxShadow = '0 0 20px rgba(188,19,254,0.3)';
                badgeContainer.style.borderColor = 'rgba(188,19,254,0.5)';
                if (pulseDot) pulseDot.style.color = '#bc13fe';
            } else if (badgeContainer) {
                badgeContainer.style.boxShadow = '';
                badgeContainer.style.borderColor = '';
                if (pulseDot) pulseDot.style.color = '';
            }
        }
    }

    styxToggle.addEventListener('change', updateUI);
    if (auditToggle) auditToggle.addEventListener('change', updateUI);
    if (wpToggle) wpToggle.addEventListener('change', updateUI);
});
