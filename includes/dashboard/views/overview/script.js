/**
 * VGT ACCORDION LOGIC (Oracle Intelligence Panel - Titan/Security Center Style)
 * Zero-Dependency, Event-Delegated Execution.
 */
document.addEventListener('DOMContentLoaded', function() {
    const oracleList = document.querySelector('.vgt-oracle-list');
    if (!oracleList) return;

    if (oracleList.dataset.accordionInit) return;
    oracleList.dataset.accordionInit = "true";

    oracleList.addEventListener('click', function(e) {
        const trigger = e.target.closest('.vgt-accordion-trigger');
        if (!trigger) return;

        const parentEvent = trigger.closest('.vgt-oracle-event');
        if (!parentEvent) return;

        const bodyWrapper = parentEvent.querySelector('.vgt-oracle-body-wrapper');
        const icon = parentEvent.querySelector('.vgt-accordion-icon');
        const isOpen = parentEvent.classList.contains('is-open');

        if (isOpen) {
            parentEvent.classList.remove('is-open');
            if (bodyWrapper) bodyWrapper.style.display = 'none';
            if (icon) icon.style.transform = 'rotate(0deg)';
        } else {
            parentEvent.classList.add('is-open');
            if (bodyWrapper) bodyWrapper.style.display = 'block';
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
    });
});
