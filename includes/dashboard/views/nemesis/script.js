(function() {
    function initNemesis() {
        const appendLog = (terminal, className, level, message, cursor = false) => {
            if (!terminal) return;
            const code = document.createElement('code');
            code.className = className;
            code.textContent = `[${new Date().toLocaleTimeString()}] [${level}] ${message}`;
            if (cursor) {
                const span = document.createElement('span');
                span.className = 'cursor-blink';
                span.textContent = '_';
                code.appendChild(span);
            }
            terminal.appendChild(code);
        };

        const toggleMain = document.getElementById('nemesis_enabled');
        const toggleStrike = document.getElementById('nemesis_active_strike');
        const strikeBoxes = document.querySelectorAll('.strike-explanation-box');
        const strikePanel = document.getElementById('strike-panel');
        
        // Accordion Logic
        const expTrigger = document.getElementById('vgt-exp-trigger');
        const expContent = document.getElementById('vgt-exp-content');
        if(expTrigger && expContent) {
            expTrigger.addEventListener('click', function(e) {
                e.preventDefault();
                this.classList.toggle('active');
                if(this.classList.contains('active')) {
                    expContent.style.maxHeight = expContent.scrollHeight + "px";
                    expContent.style.opacity = "1";
                    expContent.style.marginTop = "20px";
                } else {
                    expContent.style.maxHeight = "0px";
                    expContent.style.opacity = "0";
                    expContent.style.marginTop = "0px";
                }
            });
        }

        // Modal Logic & Active Strike Interception
        const complianceModal = document.getElementById('vgt-compliance-modal');
        const btnAbort = document.getElementById('vgt-modal-abort');
        const btnAuthorize = document.getElementById('vgt-modal-authorize');
        let pendingStrikeActivation = false;

        function hideComplianceModal() {
            if (complianceModal) {
                complianceModal.classList.remove('active');
                complianceModal.style.display = 'none';
            }
            pendingStrikeActivation = false;
        }

        function showComplianceModal() {
            if (complianceModal) {
                complianceModal.style.display = 'flex';
                setTimeout(() => complianceModal.classList.add('active'), 10);
            }
        }

        if (complianceModal) {
            // Ensure hidden on initial load
            complianceModal.style.display = 'none';
            complianceModal.classList.remove('active');

            // Close when clicking outside the box
            complianceModal.addEventListener('click', function(e) {
                if (e.target === complianceModal) {
                    hideComplianceModal();
                }
            });

            // Close on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && complianceModal.classList.contains('active')) {
                    hideComplianceModal();
                }
            });
        }

        if (toggleStrike) {
            toggleStrike.addEventListener('click', function(e) {
                if (this.checked && !pendingStrikeActivation) {
                    e.preventDefault(); // Block checkbox toggle until confirmed
                    showComplianceModal();
                }
            });

            // Event-Listener für das eigentliche Change-Event
            toggleStrike.addEventListener('change', function() {
                const isChecked = this.checked;
                const toggleLabel = document.getElementById('toggle-label-strike');
                const terminal = document.getElementById('nemesis-terminal');
                const specStrikeModeT = document.getElementById('spec-strike-mode-t');
                
                if (isChecked) {
                    if (toggleLabel) {
                        toggleLabel.innerText = 'ARMED (SAVE REQUIRED)';
                        toggleLabel.style.color = '#ff4d4d';
                    }
                    if(specStrikeModeT) specStrikeModeT.innerHTML = '<svg class="vgt-icon" style="width:16px; height:16px; color:var(--vgt-danger);" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> Mode: Kinetische Sabotage';
                    
                    strikeBoxes.forEach(box => box.classList.add('active'));
                    
                    if(terminal) appendLog(terminal, 'log-critical', 'WARNING', 'ACTIVE STRIKE INITIATED. OFFENSIVE KINETIC COUNTERMEASURES WILL BE ARMED ON SAVE.');
                    
                    if (toggleMain && toggleMain.checked) {
                        const badgeText = document.getElementById('badge-text-nemesis');
                        const badgeContainer = document.getElementById('nemesis-main-badge');
                        if (badgeText) badgeText.innerText = 'ACTIVE STRIKE: ARMED (SAVE REQUIRED)';
                        if (badgeContainer) badgeContainer.className = 'vgt-status-badge armed';
                    }

                } else {
                    if (toggleLabel) {
                        toggleLabel.innerText = 'DISARMED (SAVE REQUIRED)';
                        toggleLabel.style.color = '#888';
                    }
                    if(specStrikeModeT) specStrikeModeT.innerHTML = '<svg class="vgt-icon" style="width:16px; height:16px; color:var(--vgt-nemesis);" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> Mode: Passive Delay';
                    
                    strikeBoxes.forEach(box => box.classList.remove('active'));
                    
                    if(terminal) appendLog(terminal, 'log-info', 'SYSTEM', 'Active Strike deactivated. Returning to safe, passive deception mode.');
                    
                    if (toggleMain && toggleMain.checked) {
                        const badgeText = document.getElementById('badge-text-nemesis');
                        const badgeContainer = document.getElementById('nemesis-main-badge');
                        if (badgeText) badgeText.innerText = 'DECEPTION MATRIX: ENGAGED (SAVE REQUIRED)';
                        if (badgeContainer) badgeContainer.className = 'vgt-status-badge active';
                    }
                }
            });

            // Modal Buttons
            if (btnAbort) {
                btnAbort.addEventListener('click', function(e) {
                    e.preventDefault();
                    hideComplianceModal();
                });
            }

            if (btnAuthorize) {
                btnAuthorize.addEventListener('click', function(e) {
                    e.preventDefault();
                    hideComplianceModal();
                    pendingStrikeActivation = true;
                    toggleStrike.checked = true;
                    toggleStrike.dispatchEvent(new Event('change'));
                    pendingStrikeActivation = false;
                });
            }
        }

        // Main Nemesis Toggle Logic
        if (toggleMain) {
            toggleMain.addEventListener('change', function() {
                const isChecked = this.checked;
                const dynContent = document.getElementById('nemesis-dynamic-content');
                const badgeText = document.getElementById('badge-text-nemesis');
                const badgeContainer = document.getElementById('nemesis-main-badge');
                const toggleLabel = document.getElementById('toggle-label-nemesis');
                const terminal = document.getElementById('nemesis-terminal');
                const statuses = document.querySelectorAll('.node-status');
                const sparklines = document.querySelectorAll('.kpi-sparkline');
                
                if (isChecked) {
                    if (dynContent) dynContent.classList.remove('vgt-disabled');
                    
                    if (toggleStrike && toggleStrike.checked) {
                        if (badgeText) badgeText.innerText = 'ACTIVE STRIKE: ARMED (SAVE REQUIRED)';
                        if (badgeContainer) badgeContainer.className = 'vgt-status-badge armed';
                    } else {
                        if (badgeText) badgeText.innerText = 'DECEPTION MATRIX: ENGAGED (SAVE REQUIRED)';
                        if (badgeContainer) badgeContainer.className = 'vgt-status-badge active';
                    }
                    
                    if (toggleLabel) {
                        toggleLabel.innerText = 'ENGAGED (SAVE REQUIRED)';
                        toggleLabel.style.color = '#bc13fe';
                    }
                    
                    statuses.forEach(s => s.classList.add('online'));
                    sparklines.forEach(s => s.classList.add('pulse-slow'));

                    if(terminal) {
                        terminal.replaceChildren();
                        appendLog(terminal, 'sys-boot', 'INIT', 'Booting Nemesis Counterintelligence Protocol...');
                        appendLog(terminal, 'log-warn', 'WAIT', 'Configuration change detected. Save required to apply matrix.');
                        appendLog(terminal, 'log-info', 'SYSTEM', 'Awaiting save to initialize database stream...', true);
                    }
                    
                    const kpiTarpit = document.getElementById('kpi-tarpit');
                    const kpiCanary = document.getElementById('kpi-canary');
                    const kpiPoison = document.getElementById('kpi-poison');
                    if (kpiTarpit) kpiTarpit.innerHTML = '0 <span style="font-size:0.5em;color:#666;">/ 0 ACT</span>';
                    if (kpiCanary) kpiCanary.innerText = '0';
                    if (kpiPoison) kpiPoison.innerText = '0';
                } else {
                    if (dynContent) dynContent.classList.add('vgt-disabled');
                    
                    if (badgeText) badgeText.innerText = 'SYSTEM OFFLINE: VULNERABLE';
                    if (badgeContainer) badgeContainer.className = 'vgt-status-badge offline';
                    if (toggleLabel) {
                        toggleLabel.innerText = 'STANDBY';
                        toggleLabel.style.color = '#888';
                    }
                    
                    if(toggleStrike && toggleStrike.checked) {
                        toggleStrike.checked = false;
                        toggleStrike.dispatchEvent(new Event('change'));
                    }
                    
                    if(expTrigger && expTrigger.classList.contains('active')) {
                        expTrigger.click();
                    }

                    statuses.forEach(s => s.classList.remove('online'));
                    sparklines.forEach(s => s.className = 'kpi-sparkline');
                    if(terminal) {
                        terminal.replaceChildren();
                        appendLog(terminal, 'log-critical', 'ERROR', 'Deception Matrix halted. Proceed with caution.');
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNemesis);
    } else {
        initNemesis();
    }
})();
