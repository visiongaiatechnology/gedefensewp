/* STATUS: DIAMANT VGT SUPREME */
jQuery(function ($) {
    'use strict';

    const cfg = window.visConfig || window.vis_vars || {};
    const matrix = window.vgtScannerMatrix || null;
    const endpoint = matrix ? matrix.endpoint : (cfg.ajaxUrl || '');
    if (!endpoint) return;

    const $overlay = $('<div>', {'class': 'vis-scan-overlay', 'aria-hidden': 'true'});
    const $dialog = $('<section>', {'class': 'vis-scan-dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'vis-scan-title'});
    const $content = $('<div>', {'class': 'vis-scan-content'});
    const $orbit = $('<div>', {'class': 'vis-scan-orbit'}).append($('<span>', {'class': 'vis-scan-core'}));
    const $title = $('<h2>', {id: 'vis-scan-title', 'class': 'vis-scan-title'});
    const $description = $('<p>', {'class': 'vis-scan-description'});
    const $percent = $('<span>', {'class': 'vis-scan-percent'}).text('0%');
    const $bar = $('<div>', {'class': 'vis-scan-bar'});
    const $actions = $('<div>', {'class': 'vis-scan-actions'});
    const $copy = $('<div>', {'class': 'vis-scan-copy'}).append(
        $('<p>', {'class': 'vis-scan-kicker'}).text('GEDEFENSE · INTEGRITY UPLINK'), $title
    );
    $content.append(
        $('<div>', {'class': 'vis-scan-head'}).append($orbit, $copy),
        $description,
        $('<div>', {'class': 'vis-scan-progress-meta'}).append($('<span>').text('SCAN FORTSCHRITT'), $percent),
        $('<div>', {'class': 'vis-scan-track'}).append($bar),
        $actions
    );
    $dialog.append($('<div>', {'class': 'vis-scan-grid'}), $content);
    $overlay.append($dialog).appendTo(document.body);

    const state = {phase: 'init', offset: 0, mode: 'scan', total: 0, running: false};
    let cycleTimer = null;
    let request = null;

    function setButtonsDisabled(disabled) {
        $('.vis-btn-scan, #vis-btn-approve').prop('disabled', disabled).attr('aria-disabled', disabled ? 'true' : 'false');
    }

    function openDialog(title, description) {
        $dialog.removeClass('is-error is-success');
        $orbit.removeClass('is-static');
        $title.text(title);
        $description.text(description);
        $actions.empty();
        updateProgress(0);
        $overlay.addClass('is-active').attr('aria-hidden', 'false');
    }

    function closeDialog() {
        if (cycleTimer !== null) window.clearTimeout(cycleTimer);
        cycleTimer = null;
        request = null;
        state.running = false;
        setButtonsDisabled(false);
        $overlay.removeClass('is-active').attr('aria-hidden', 'true');
    }

    function addCloseButton(reloadAfterClose) {
        const $button = $('<button>', {type: 'button', 'class': 'vis-scan-button vis-scan-button--primary'}).text('SCHLIESSEN');
        $button.on('click', function () {
            closeDialog();
            if (reloadAfterClose) reloadPage();
        });
        $actions.empty().append($button);
        window.setTimeout(function () { $button.trigger('focus'); }, 0);
    }

    function start(mode) {
        if (state.running) return;
        state.phase = 'init';
        state.offset = 0;
        state.total = 0;
        state.mode = mode === 'reindex' ? 'reindex' : 'scan';
        state.running = true;
        setButtonsDisabled(true);
        openDialog(state.mode === 'reindex' ? 'Baseline wird versiegelt' : 'Deep Scan aktiv', 'Der isolierte Dateisystem-Index wird vorbereitet …');
        executeCycle();
    }

    $(document).on('click', '.vis-btn-scan', function (event) {
        event.preventDefault();
        start(String($(this).data('mode') || 'scan'));
    });

    $(document).on('click', '#vis-btn-approve', function (event) {
        event.preventDefault();
        if (state.running) return;
        start('reindex');
    });

    function executeCycle() {
        if (!state.running) return;
        request = $.ajax({
            url: endpoint,
            method: 'POST',
            timeout: 20000,
            headers: {'X-VGT-Uplink-Token': matrix ? matrix.uplinkToken : ''},
            data: {
                action: matrix ? matrix.action : 'vgt_integrity_uplink',
                nonce: cfg.nonce || '',
                phase: state.phase,
                offset: state.offset,
                mode: state.mode
            }
        }).done(handleResponse).fail(function (xhr) {
            const response = xhr.responseJSON && xhr.responseJSON.data;
            const message = response && typeof response === 'object' && response.message
                ? response.message
                : 'Native Uplink unterbrochen (HTTP ' + xhr.status + '). Der Scan-Zustand bleibt sicher fortsetzbar.';
            failSequence(message);
        }).always(function () { request = null; });
    }

    function handleResponse(response) {
        if (typeof response === 'string') {
            try { response = JSON.parse(response); } catch (error) {
                failSequence('Die Serverantwort war kein gültiges JSON. Bitte das PHP-Fehlerprotokoll prüfen.');
                return;
            }
        }
        if (!response || response.success !== true || !response.data) {
            const message = response && response.data && response.data.message ? response.data.message : 'Der Scanner-Uplink wurde abgelehnt.';
            failSequence(message);
            return;
        }
        const data = response.data;
        if (data.status === 'error') {
            failSequence(data.message || 'Der Scanner hat den Zyklus sicher abgebrochen.');
            return;
        }
        state.phase = data.phase || state.phase;
        state.offset = Number.isFinite(Number(data.offset)) ? Number(data.offset) : state.offset;
        state.total = Number.isFinite(Number(data.total)) && Number(data.total) > 0 ? Number(data.total) : state.total;
        $description.text(String(data.message || 'Scan läuft …'));
        updateProgress(progressForPhase());

        if (['scanning', 'verifying', 'processing', 'next_phase'].includes(data.status)) {
            cycleTimer = window.setTimeout(executeCycle, 250);
            return;
        }
        finalizeSequence(String(data.message || ''), String(data.status || 'warning'));
    }

    function progressForPhase() {
        if (state.phase === 'index') return 12;
        if (state.phase === 'process') return state.total > 0 ? Math.min(92, 15 + Math.floor((state.offset / state.total) * 77)) : 45;
        if (state.phase === 'finalize') return 96;
        return 5;
    }

    function updateProgress(percent) {
        const bounded = Math.max(0, Math.min(100, Number(percent) || 0));
        $bar.css('width', bounded + '%');
        $percent.text(bounded + '%');
    }

    function finalizeSequence(message, status) {
        state.running = false;
        setButtonsDisabled(false);
        updateProgress(100);
        $orbit.addClass('is-static');
        const clean = status === 'clean' || status === 'init';
        $dialog.addClass(clean ? 'is-success' : 'is-error');
        $title.text(clean ? 'Systemintegrität bestätigt' : 'Prüfung abgeschlossen');
        $description.text(message || (clean ? 'Alle überwachten Dateien wurden kryptografisch verifiziert.' : 'Befunde benötigen eine Prüfung.'));
        if (clean) updateLiveDom();
        addCloseButton(true);
    }

    function failSequence(reason) {
        state.running = false;
        setButtonsDisabled(false);
        $dialog.removeClass('is-success').addClass('is-error');
        $orbit.addClass('is-static');
        $title.text('Scan sicher angehalten');
        $description.text(String(reason));
        updateProgress(100);
        addCloseButton(false);
    }

    function updateLiveDom() {
        $('.vgt-module-title .vgt-is-alert').removeClass('vgt-is-alert').addClass('vgt-is-active');
        $('.vgt-module-title .vgt-status-pulse').next('span').text('CLEAN');
        $('.vis-card-integrity-baseline .vis-badge').text('SECURE');
    }

    function reloadPage() {
        const url = new URL(window.location.href);
        url.searchParams.set('_vgt_r', String(Date.now()));
        window.location.replace(url.toString());
    }
});
