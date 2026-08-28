/**
 * VGT ACCORDION LOGIC (Oracle Intelligence Panel)
 * Zero-Dependency, Event-Delegated Execution.
 */
(function() {
    const oracleList = document.querySelector('.vgt-oracle-list');
    if (!oracleList) return;

    if (oracleList.dataset.accordionInit) return;
    oracleList.dataset.accordionInit = "true";

    oracleList.addEventListener('click', function(e) {
        const trigger = e.target.closest('.vgt-accordion-trigger');
        if (!trigger) return;

        const parentEvent = trigger.closest('.vgt-oracle-event');
        if (!parentEvent) return;

        parentEvent.classList.toggle('is-open');
    });
})();
