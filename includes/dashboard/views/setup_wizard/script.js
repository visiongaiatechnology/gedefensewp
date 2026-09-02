/* STATUS: DIAMANT VGT SUPREME */
'use strict';
document.addEventListener('DOMContentLoaded', () => {
    let currentStep = 1;
    const maxSteps = 7;
    const wizardRoot = document.getElementById('vgt-setup-wizard');
    const siteUrl = wizardRoot ? wizardRoot.dataset.siteUrl || '' : '';

    const DOM = {
        steps: document.querySelectorAll('.vgt-wizard-step'),
        dots: document.querySelectorAll('.vgt-step-dot'),
        btnNext: document.getElementById('btn-next'),
        btnPrev: document.getElementById('btn-prev'),
        hadesToggle: document.getElementById('hades-toggle'),
        hadesInputs: document.getElementById('hades-inputs'),
        hadesParam: document.getElementById('hades-param-input'),
        hadesSecret: document.getElementById('hades-secret-input'),
        hadesReview: document.getElementById('hades-review-block'),
        hadesPreview: document.getElementById('hades-url-preview'),
        standardReview: document.getElementById('standard-review-block'),
        // Module check elements
        cfgAegis: document.getElementById('cfg-aegis'),
        cfgZeus: document.getElementById('cfg-zeus'),
        cfgCerberus: document.getElementById('cfg-cerberus'),
        cfgPrometheus: document.getElementById('cfg-prometheus'),
        cfgMorpheus: document.getElementById('cfg-morpheus'),
        cfgNemesis: document.getElementById('cfg-nemesis'),
        cfgTrap: document.getElementById('cfg-trap'),
        cfgTitan: document.getElementById('cfg-titan'),
        cfgChronos: document.getElementById('cfg-chronos'),
        // Summary elements
        sumAegis: document.getElementById('sum-aegis'),
        sumZeus: document.getElementById('sum-zeus'),
        sumCerberus: document.getElementById('sum-cerberus'),
        sumPrometheus: document.getElementById('sum-prometheus'),
        sumMorpheus: document.getElementById('sum-morpheus'),
        sumNemesis: document.getElementById('sum-nemesis'),
        sumTrap: document.getElementById('sum-trap'),
        sumTitan: document.getElementById('sum-titan'),
        sumHades: document.getElementById('sum-hades'),
        sumChronos: document.getElementById('sum-chronos')
    };

    const updateWizard = () => {
        // Build URL Previews & Summary for step 7
        if (currentStep === 7) {
            const hasHades = DOM.hadesToggle && DOM.hadesToggle.checked;
            const hadesK = (DOM.hadesParam && DOM.hadesParam.value.trim()) || 'vgt_access';
            const hadesV = (DOM.hadesSecret && DOM.hadesSecret.value.trim()) || 'omega';

            if (hasHades) {
                DOM.hadesPreview.textContent = `${siteUrl}/wp-admin?${hadesK}=${hadesV}`;
                DOM.hadesReview.style.display = 'block';
                DOM.standardReview.style.display = 'none';
            } else {
                DOM.hadesReview.style.display = 'none';
                DOM.standardReview.style.display = 'block';
            }

            // Update summary badges
            const setSum = (el, chk) => {
                if (!el) return;
                const active = chk && chk.checked;
                el.textContent = active ? 'AKTIV' : 'INAKTIV';
                el.style.color = active ? '#10b981' : '#94a3b8';
            };

            setSum(DOM.sumAegis, DOM.cfgAegis);
            setSum(DOM.sumZeus, DOM.cfgZeus);
            setSum(DOM.sumCerberus, DOM.cfgCerberus);
            setSum(DOM.sumPrometheus, DOM.cfgPrometheus);
            setSum(DOM.sumMorpheus, DOM.cfgMorpheus);
            setSum(DOM.sumNemesis, DOM.cfgNemesis);
            setSum(DOM.sumTrap, DOM.cfgTrap);
            setSum(DOM.sumTitan, DOM.cfgTitan);
            setSum(DOM.sumHades, DOM.hadesToggle);
            setSum(DOM.sumChronos, DOM.cfgChronos);
        }

        // Toggle step visibility
        DOM.steps.forEach((step, idx) => {
            step.classList.toggle('active', (idx + 1) === currentStep);
        });

        // Toggle step indicators
        DOM.dots.forEach((dot, idx) => {
            const stepNum = idx + 1;
            dot.classList.toggle('active', stepNum === currentStep);
            dot.classList.toggle('completed', stepNum < currentStep);
        });

        // Toggle action buttons
        DOM.btnPrev.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        
        if (currentStep === maxSteps) {
            DOM.btnNext.textContent = wizardRoot?.dataset.activateLabel || 'ACTIVATE';
            DOM.btnNext.type = 'submit';
            DOM.btnNext.name = 'vis_save_config';
            DOM.btnNext.value = '1';
        } else {
            DOM.btnNext.textContent = wizardRoot?.dataset.nextLabel || 'NEXT';
            DOM.btnNext.type = 'button';
            DOM.btnNext.removeAttribute('name');
            DOM.btnNext.removeAttribute('value');
        }
    };

    DOM.btnNext.addEventListener('click', (e) => {
        if (currentStep < maxSteps) {
            e.preventDefault();
            currentStep++;
            updateWizard();
        }
    });

    DOM.btnPrev.addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep--;
            updateWizard();
        }
    });

    if (DOM.hadesToggle && DOM.hadesInputs) {
        DOM.hadesToggle.addEventListener('change', () => {
            DOM.hadesInputs.style.display = DOM.hadesToggle.checked ? 'grid' : 'none';
        });
    }
});
