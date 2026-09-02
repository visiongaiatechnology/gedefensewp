/**
 * ZEUS NEXT GENERATION INTERACTION ENGINE
 * Zero-Dependency, Self-Executing Core Controller
 */
(function() {
    'use strict';

    function initZeus() {
        // Tab Navigation
        const tabBtns = document.querySelectorAll('.vgt-mode-btn');
        const tabPanes = document.querySelectorAll('.vgt-tab-pane');

        if (tabBtns.length > 0) {
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('data-tab');
                    if (!targetId) return;

                    tabBtns.forEach(b => {
                        b.classList.remove('active');
                        b.style.background = '';
                        b.style.borderColor = '';
                        b.style.color = '';
                        b.style.boxShadow = '';
                    });

                    tabPanes.forEach(p => {
                        p.classList.remove('active');
                        p.style.display = 'none';
                    });

                    this.classList.add('active');
                    this.style.background = 'rgba(94, 234, 212, 0.2)';
                    this.style.borderColor = '#5eead4';
                    this.style.color = '#ffffff';
                    this.style.boxShadow = '0 0 14px rgba(94, 234, 212, 0.25)';

                    const targetPane = document.getElementById(targetId);
                    if (targetPane) {
                        targetPane.classList.add('active');
                        targetPane.style.display = 'block';
                    }
                });
            });
        }

        // Run Engine Benchmark
        const btnBenchmark = document.getElementById('btn-run-benchmark');
        if (btnBenchmark) {
            btnBenchmark.addEventListener('click', function() {
                const profileElem = document.getElementById('bmk-profile');
                const iterElem = document.getElementById('bmk-iterations');
                const profile = profileElem ? profileElem.value : 'BALANCED';
                const iterations = iterElem ? iterElem.value : '5000';
                btnBenchmark.disabled = true;
                btnBenchmark.textContent = 'RUNNING BENCHMARK...';

                const nonceInput = document.querySelector('input[name="vis_zeus_nonce"]');
                const nonce = nonceInput ? nonceInput.value : '';

                const data = new FormData();
                data.append('action', 'vis_zeus_run_benchmark');
                data.append('profile', profile);
                data.append('iterations', iterations);
                data.append('vis_zeus_nonce', nonce);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(res => {
                        btnBenchmark.disabled = false;
                        btnBenchmark.textContent = 'START ENGINE BENCHMARK';
                        if (res.success && res.data) {
                            const card = document.getElementById('bmk-results-card');
                            if (card) card.style.display = 'block';
                            const evalsElem = document.getElementById('bmk-res-evals');
                            if (evalsElem) evalsElem.textContent = res.data.evals_per_sec.toLocaleString();
                            const p50Elem = document.getElementById('bmk-res-p50');
                            if (p50Elem) p50Elem.textContent = res.data.p50_ms + ' ms';
                            const p95Elem = document.getElementById('bmk-res-p95');
                            if (p95Elem) p95Elem.textContent = res.data.p95_ms + ' ms';
                            const detailElem = document.getElementById('bmk-res-detail');
                            if (detailElem) {
                                detailElem.textContent = 
                                    `Evaluated ${res.data.iterations.toLocaleString()} synthetic requests in ${res.data.duration_ms} ms. ` +
                                    `Drop rate: ${res.data.drop_rate_pct}% (${res.data.rejected_count} rejected, ${res.data.allowed_count} admitted).`;
                            }
                        } else {
                            alert('Benchmark failed: ' + (res.data ? res.data.message : 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        btnBenchmark.disabled = false;
                        btnBenchmark.textContent = 'START ENGINE BENCHMARK';
                        alert('Benchmark error: ' + err.message);
                    });
            });
        }

        // Run Security Self-Test
        const btnSelfTest = document.getElementById('btn-run-selftest');
        if (btnSelfTest) {
            btnSelfTest.addEventListener('click', function() {
                btnSelfTest.disabled = true;
                btnSelfTest.textContent = 'EXECUTING SELF-TEST...';

                const nonceInput = document.querySelector('input[name="vis_zeus_nonce"]');
                const nonce = nonceInput ? nonceInput.value : '';

                const data = new FormData();
                data.append('action', 'vis_zeus_run_self_test');
                data.append('vis_zeus_nonce', nonce);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(res => {
                        btnSelfTest.disabled = false;
                        btnSelfTest.textContent = 'RUN SECURITY SELF-TEST';
                        if (res.success && res.data && res.data.tests) {
                            const grid = document.getElementById('selftest-results-grid');
                            const container = document.getElementById('selftest-items');
                            if (container) container.replaceChildren();
                            if (grid) grid.style.display = 'block';

                            Object.keys(res.data.tests).forEach(k => {
                                const t = res.data.tests[k];
                                const item = document.createElement('div');
                                item.className = 'vgt-test-item ' + (t.pass ? 'vgt-test-pass' : 'vgt-test-fail');
                                item.style.background = 'rgba(3,8,16,0.6)';
                                item.style.border = '1px solid rgba(148,163,184,0.15)';
                                item.style.padding = '10px 14px';
                                item.style.borderRadius = '8px';
                                item.style.display = 'flex';
                                item.style.justifyContent = 'space-between';
                                item.style.alignItems = 'center';
                                const title = document.createElement('span');
                                title.textContent = String(t.title || k);
                                const state = document.createElement('strong');
                                state.textContent = t.pass ? 'PASS' : 'FAIL';
                                state.className = t.pass ? 'vgt-test-state-pass' : 'vgt-test-state-fail';
                                item.replaceChildren(title, state);
                                if (container) container.appendChild(item);
                            });
                        } else {
                            alert('Self-test error: ' + (res.data ? res.data.message : 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        btnSelfTest.disabled = false;
                        btnSelfTest.textContent = 'RUN SECURITY SELF-TEST';
                        alert('Self-test error: ' + err.message);
                    });
            });
        }

        // Drain Blackbox to Trinity XDR
        const btnDrain = document.getElementById('btn-drain-blackbox');
        if (btnDrain) {
            btnDrain.addEventListener('click', function() {
                btnDrain.disabled = true;
                const nonceInput = document.querySelector('input[name="vis_zeus_nonce"]');
                const nonce = nonceInput ? nonceInput.value : '';

                const data = new FormData();
                data.append('action', 'vis_zeus_drain_blackbox');
                data.append('vis_zeus_nonce', nonce);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(res => {
                        btnDrain.disabled = false;
                        if (res.success) {
                            alert(res.data.message);
                            location.reload();
                        } else {
                            alert('Drain failed: ' + (res.data ? res.data.message : 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        btnDrain.disabled = false;
                        alert('Drain error: ' + err.message);
                    });
            });
        }

        // Rollback to Last Known Good
        const btnRollback = document.getElementById('btn-rollback-lkg');
        if (btnRollback) {
            btnRollback.addEventListener('click', function() {
                if (!confirm('Rollback Zeus policy to Last Known Good slot?')) return;
                const nonceInput = document.querySelector('input[name="vis_zeus_nonce"]');
                const nonce = nonceInput ? nonceInput.value : '';

                const data = new FormData();
                data.append('action', 'vis_zeus_rollback_policy');
                data.append('vis_zeus_nonce', nonce);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            alert(res.data.message);
                            location.reload();
                        } else {
                            alert('Rollback failed: ' + (res.data ? res.data.message : 'No backup available'));
                        }
                    });
            });
        }

        // Delete Route Contract
        document.querySelectorAll('.btn-delete-contract').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                if (!confirm(`Delete custom contract "${id}"?`)) return;

                const nonceInput = document.querySelector('input[name="vis_zeus_nonce"]');
                const nonce = nonceInput ? nonceInput.value : '';

                const data = new FormData();
                data.append('action', 'vis_zeus_delete_contract');
                data.append('contract_id', id);
                data.append('vis_zeus_nonce', nonce);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert('Delete failed: ' + (res.data ? res.data.message : 'Unknown error'));
                        }
                    });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initZeus);
    } else {
        initZeus();
    }
})();
