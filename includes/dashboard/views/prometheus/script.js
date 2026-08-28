document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('prometheus_enabled');
    if (!toggle) return;

    const appendLog = (terminal, className, level, message, cursor = false) => {
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

    toggle.addEventListener('change', function() {
        const isChecked = this.checked;
        const dynContent = document.getElementById('prom-dynamic-content');
        const badgeText = document.getElementById('badge-text-prom');
        const badgeContainer = document.getElementById('prom-main-badge');
        const toggleLabel = document.getElementById('toggle-label-prom');
        const terminal = document.getElementById('prom-terminal');
        const statuses = document.querySelectorAll('.node-status');
        const sparklines = document.querySelectorAll('.kpi-sparkline');
        
        if (isChecked) {
            dynContent.classList.remove('vgt-disabled');
            badgeText.innerText = 'AI COGNITION: AWAITING CONFIG SAVE';
            badgeContainer.className = 'vgt-status-badge pending';
            toggleLabel.innerText = 'ONLINE (SAVE REQUIRED)';
            toggleLabel.style.color = 'var(--vgt-prom)';
            
            statuses.forEach(s => s.classList.add('online'));
            sparklines.forEach(s => s.classList.add('pulse-slow'));

            terminal.replaceChildren();
            appendLog(terminal, 'sys-boot', 'INIT', 'Booting Prometheus Cognitive Engine...');
            appendLog(terminal, 'log-warn', 'WAIT', 'Matrix configuration changed. Save required to engage AI.');
            appendLog(terminal, 'log-info', 'SYSTEM', 'Awaiting input...', true);
            
            document.getElementById('kpi-strikes').innerText = '0';
            document.getElementById('kpi-anomalies').innerText = '0';
            document.getElementById('kpi-entropy').innerText = '0%';

        } else {
            dynContent.classList.add('vgt-disabled');
            badgeText.innerText = 'AI SENSORS: BLIND';
            badgeContainer.className = 'vgt-status-badge offline';
            toggleLabel.innerText = 'STANDBY';
            toggleLabel.style.color = '#888';

            statuses.forEach(s => s.classList.remove('online'));
            sparklines.forEach(s => {
                s.className = 'kpi-sparkline';
                s.style.width = '100%';
            });

            terminal.replaceChildren();
            appendLog(terminal, 'log-critical', 'ERROR', 'Prometheus Engine shutdown.');
        }
    });
});
