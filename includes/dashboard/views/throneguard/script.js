/* STATUS: DIAMANT VGT SUPREME */
'use strict';
function tgSelectAllCaps(select) {
    document.querySelectorAll('.tg-cap-checkbox').forEach(function(cb) {
        cb.checked = select;
        var parent = cb.closest('.tg-cap-item');
        if (parent) {
            if (select) parent.classList.add('is-restricted');
            else parent.classList.remove('is-restricted');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-cap-selection]').forEach(function(button) {
        button.addEventListener('click', function() {
            tgSelectAllCaps(button.dataset.capSelection === 'restrict');
        });
    });

    // Dynamic cap item highlight
    document.querySelectorAll('.tg-cap-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var parent = this.closest('.tg-cap-item');
            if (parent) {
                if (this.checked) parent.classList.add('is-restricted');
                else parent.classList.remove('is-restricted');
            }
        });
    });

    // Log Filter
    var filterBtns = document.querySelectorAll('.tg-filter-btn');
    var logRows = document.querySelectorAll('.tg-log-row');
    var searchInput = document.getElementById('tg-log-search');

    function applyLogFilters() {
        var activeFilter = document.querySelector('.tg-filter-btn.active')?.getAttribute('data-filter') || 'all';
        var searchTerm = (searchInput?.value || '').toLowerCase().trim();

        logRows.forEach(function(row) {
            var rowSeverity = row.getAttribute('data-severity') || 'info';
            var rowText = row.innerText.toLowerCase();
            
            var matchesSeverity = (activeFilter === 'all' || rowSeverity === activeFilter);
            var matchesSearch = (searchTerm === '' || rowText.indexOf(searchTerm) !== -1);

            if (matchesSeverity && matchesSearch) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });
    }

    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterBtns.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            applyLogFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyLogFilters);
    }

    // Clear Logs AJAX
    var clearBtn = document.getElementById('tg-clear-logs-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            var root = document.getElementById('vgt-throneguard');
            if (!root || !confirm(root.dataset.clearConfirm || '')) return;
            
            clearBtn.disabled = true;
            clearBtn.textContent = root.dataset.clearingLabel || '';

            var data = new FormData();
            data.append('action', 'vis_throneguard_clear_logs');
            data.append('nonce', root.dataset.actionNonce || '');

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.success) {
                    var stream = document.getElementById('tg-log-stream');
                    if (stream) {
                        var emptyState = document.createElement('div');
                        emptyState.className = 'vgt-empty-state';
                        emptyState.textContent = root.dataset.clearedLabel || '';
                        stream.replaceChildren(emptyState);
                    }
                } else {
                    alert(root.dataset.clearError || '');
                }
            })
            .catch(function(err) {
                alert('Netzwerkfehler: ' + err.message);
            })
            .finally(function() {
                clearBtn.disabled = false;
                clearBtn.textContent = root.dataset.clearLabel || '';
            });
        });
    }
});
