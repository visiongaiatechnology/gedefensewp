/* STATUS: DIAMANT VGT SUPREME */
(() => {
    'use strict';

    const applyFilters = () => {
        const searchValue = (document.getElementById('vgt-telemetry-ip-search')?.value || '').toLowerCase().trim();
        const sensorValue = (document.getElementById('vgt-telemetry-sensor-filter')?.value || '').toUpperCase().trim();
        const minimumSeverity = Number.parseInt(document.getElementById('vgt-telemetry-severity-filter')?.value || '0', 10);
        document.querySelectorAll('#vgt-telemetry-table tbody tr.vgt-telemetry-row').forEach((row) => {
            if (!(row instanceof HTMLTableRowElement)) return;
            const rowIp = (row.dataset.ip || '').toLowerCase();
            const rowSensor = (row.dataset.sensor || '').toUpperCase();
            const rowSeverity = Number.parseInt(row.dataset.severity || '1', 10);
            const rowText = (row.textContent || '').toLowerCase();
            const visible = (searchValue === '' || rowIp.includes(searchValue) || rowText.includes(searchValue))
                && (sensorValue === '' || rowSensor === sensorValue)
                && rowSeverity >= minimumSeverity;
            row.hidden = !visible;
        });
    };

    const resetFilters = () => {
        const search = document.getElementById('vgt-telemetry-ip-search');
        const sensor = document.getElementById('vgt-telemetry-sensor-filter');
        const severity = document.getElementById('vgt-telemetry-severity-filter');
        if (search instanceof HTMLInputElement) search.value = '';
        if (sensor instanceof HTMLSelectElement) sensor.value = '';
        if (severity instanceof HTMLSelectElement) severity.value = '0';
        applyFilters();
    };

    window.vgtFilterTableByIp = (ip) => {
        const search = document.getElementById('vgt-telemetry-ip-search');
        if (!(search instanceof HTMLInputElement)) return;
        search.value = String(ip);
        applyFilters();
        search.scrollIntoView({behavior: 'smooth', block: 'center'});
    };
    window.vgtApplyTelemetryFilters = applyFilters;
    window.vgtResetTelemetryFilters = resetFilters;
    document.getElementById('vgt-telemetry-ip-search')?.addEventListener('input', applyFilters);
    document.getElementById('vgt-telemetry-sensor-filter')?.addEventListener('change', applyFilters);
    document.getElementById('vgt-telemetry-severity-filter')?.addEventListener('change', applyFilters);
    document.querySelector('[data-reset-filters]')?.addEventListener('click', resetFilters);
    document.querySelectorAll('[data-filter-ip]').forEach((button) => {
        button.addEventListener('click', () => window.vgtFilterTableByIp(button.getAttribute('data-filter-ip') || ''));
    });
})();
