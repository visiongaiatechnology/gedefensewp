jQuery(document).ready(function($) {
    'use strict';

    /**
     * VISIONGAIA CLIENT PILOT V3.0 (AIR-GAPPED APEX EDITION)
     * Protocol: Platinum Asynchronous Phasing & Reactive Live DOM Mutation
     * Backend Sync: VIS_Scanner_Engine Omega
     */
    
    // Globale Config robuster auflösen (Hybrid Support für Matrix & Legacy CFG)
    const cfg = window.visConfig || window.vis_vars || {};
    const matrix = window.vgtScannerMatrix || null;
    
    const endpoint = matrix ? matrix.endpoint : (cfg.ajaxUrl || '');
    if (!endpoint) {
        console.error("[VGT APEX] CRITICAL ERROR: Missing AJAX Configuration/Uplink Matrix.");
        return;
    }

    // --- STYLES INJECTION (VGT GLASSMORPHISM SUPREME) ---
    $('head').append(`
        <style>
            .vis-glass-overlay {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(2, 4, 10, 0.85);
                backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
                z-index: 99999;
                display: flex; align-items: center; justify-content: center;
                opacity: 0; transition: opacity 0.3s ease; pointer-events: none;
            }
            .vis-glass-overlay.active { opacity: 1; pointer-events: all; }
            .vis-glass-card {
                background: linear-gradient(165deg, rgba(15, 20, 30, 0.95) 0%, rgba(2, 4, 10, 0.98) 100%);
                border: 1px solid rgba(212, 175, 55, 0.2);
                border-top: 2px solid #D4AF37;
                box-shadow: 0 0 50px rgba(0,0,0,0.8), inset 0 0 20px rgba(212,175,55,0.05);
                padding: 40px; border-radius: 6px; width: 480px; max-width: 90%;
                text-align: center; transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                color: #fff; font-family: 'Rajdhani', -apple-system, sans-serif;
            }
            .vis-glass-overlay.active .vis-glass-card { transform: scale(1); }
            .vis-spinner-lg { font-size: 50px; width: 50px; height: 50px; margin: 0 auto 20px auto; color: #00ffaa; }
            .vis-status-title { font-family: 'Syncopate', sans-serif; font-size: 18px; font-weight: 700; letter-spacing: 2px; margin: 0 0 10px 0; color: #fff; text-transform: uppercase; }
            .vis-status-desc { font-size: 13px; color: #94a3b8; font-family: 'JetBrains Mono', monospace; min-height: 40px; line-height: 1.5; }
            
            .vis-progress-track {
                width: 100%; height: 4px; background: rgba(0,0,0,0.5);
                border-radius: 2px; margin-top: 25px; overflow: hidden; position: relative;
            }
            .vis-progress-bar {
                height: 100%; width: 0%; background: #00ffaa;
                box-shadow: 0 0 10px #00ffaa; transition: width 0.3s ease;
            }
            
            .vis-modal-actions { display: flex; gap: 10px; justify-content: center; margin-top: 25px; }
            .vis-btn-modal { padding: 10px 20px; border-radius: 3px; border: none; cursor: pointer; font-weight: 600; font-size: 12px; transition: all 0.2s; font-family: 'Orbitron', sans-serif; letter-spacing: 1px; }
            .vis-btn-cancel { background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
            .vis-btn-cancel:hover { background: rgba(255,255,255,0.1); }
            .vis-btn-confirm { background: rgba(212, 175, 55, 0.15); color: #D4AF37; border: 1px solid #D4AF37; }
            .vis-btn-confirm:hover { background: #D4AF37; color: #000; box-shadow: 0 0 20px rgba(212, 175, 55, 0.4); }
            
            @keyframes spin { 100% { transform: rotate(360deg); } }
            .spin { animation: spin 2s linear infinite; }
        </style>
    `);

    // --- DOM ELEMENTS ---
    const UI = {
        scanBtn: $('.vis-btn-scan'),
        approveBtn: $('#vis-btn-approve'),
        overlay: $('<div class="vis-glass-overlay"><div class="vis-glass-card"></div></div>').appendTo('body'),
        card: null 
    };
    UI.card = UI.overlay.find('.vis-glass-card');

    // --- STATE MACHINE ---
    let STATE = {
        phase: 'init',
        offset: 0,
        mode: 'scan', 
        total: 0
    };
    let completionTimer = null;

    // --- EVENT LISTENERS ---
    $(document).on('click', '.vis-btn-scan', function(e) {
        e.preventDefault();
        STATE.mode = $(this).data('mode') || 'scan';
        STATE.phase = 'init';
        STATE.offset = 0;

        showProcessingModal('NATIVE KERNEL SCAN', 'Initializing Air-Gapped Deployment...', STATE.mode);
        executeCycle();
    });

    $(document).on('click', '#vis-btn-approve', function(e) {
        e.preventDefault();
        showConfirmModal();
    });

    // --- MODAL FUNCTIONS ---
    function showConfirmModal() {
        UI.card.html(`
            <div class="dashicons dashicons-warning" style="font-size:40px; width:40px; height:40px; color:#D4AF37; margin-bottom:15px;"></div>
            <div class="vis-status-title">BASELINE OVERRIDE</div>
            <div class="vis-status-desc">
                Möchten Sie den aktuellen Systemzustand wirklich als &quot;Sicher&quot; markieren?<br><br>
                <span style="color:#D4AF37;">Alle aktuellen System-Dateien werden kryptographisch in die Matrix aufgenommen.</span>
            </div>
            <div class="vis-modal-actions">
                <button class="vis-btn-modal vis-btn-cancel" id="vis-modal-cancel">ABBRECHEN</button>
                <button class="vis-btn-modal vis-btn-confirm" id="vis-modal-confirm">MATRIX UPDATE</button>
            </div>
        `);
        UI.overlay.addClass('active');

        $('#vis-modal-cancel').off('click').on('click', function() {
            UI.overlay.removeClass('active');
        });

        $('#vis-modal-confirm').off('click').on('click', function() {
            STATE.mode = 'reindex';
            STATE.phase = 'init';
            STATE.offset = 0;
            showProcessingModal('SECURING MATRIX', 'Authenticating write access...', 'reindex');
            executeCycle();
        });
    }

    function showProcessingModal(title, desc, mode) {
        let iconColor = (mode === 'reindex') ? '#D4AF37' : '#00ffaa';
        
        UI.card.html(`
            <div class="dashicons dashicons-update spin vis-spinner-lg" style="color:${iconColor}"></div>
            <div class="vis-status-title">${title}</div>
            <div class="vis-status-desc" id="vis-dynamic-desc">${desc}</div>
            <div class="vis-progress-track">
                <div id="vis-progress-bar" class="vis-progress-bar" style="width:0%; background:${iconColor}; box-shadow: 0 0 10px ${iconColor};"></div>
            </div>
        `);
        UI.overlay.addClass('active');
    }

    // --- CORE LOGIC (HARDENED WP-AJAX PARSER & CUP BYPASS) ---
    function executeCycle() {
        const payloadAction = matrix ? matrix.action : 'vgt_integrity_uplink';
        const uplinkToken = matrix ? matrix.uplinkToken : '';

        $.ajax({
            url: endpoint,
            type: 'POST',
            headers: {
                'X-VGT-Uplink-Token': uplinkToken
            },
            data: {
                action: payloadAction,
                nonce: cfg.nonce || '',
                phase: STATE.phase,
                offset: STATE.offset,
                mode: STATE.mode
            },
            success: function(response) {
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch(e) {
                        console.error("[VGT UPLINK ERROR] Received Raw String instead of JSON.", response);
                        failSequence("Native Execution Fault: PHP Fatal Error or Memory Exhausted.");
                        return;
                    }
                }

                if (!response.success) {
                    let errorMsg = "Server Action Rejected.";
                    if (response.data) {
                        errorMsg = (typeof response.data === 'string') ? response.data : (response.data.message || "Unknown WP-AJAX Error");
                    }
                    failSequence(errorMsg);
                    return;
                }

                let data = response.data;

                if (data.status === 'error') {
                    failSequence(data.message || "Scanner Engine reported a silent error.");
                    return;
                }

                STATE.phase = data.phase || STATE.phase;
                STATE.offset = data.offset !== undefined ? data.offset : STATE.offset;
                if (data.total) STATE.total = data.total;

                let percent = 10;
                if (STATE.phase === 'process' || STATE.phase === 'scan') {
                    if (STATE.total > 0) {
                        percent = 10 + Math.floor((STATE.offset / STATE.total) * 80);
                    } else {
                        percent = data.progress || 50;
                    }
                } else if (STATE.phase === 'finalize' || STATE.phase === 'verify') {
                    percent = 95;
                }

                updateProgress(data.message, percent);

                if (data.status === 'scanning' || data.status === 'verifying' || data.status === 'processing' || data.status === 'next_phase') {
                    setTimeout(executeCycle, 200); 
                } else {
                    updateProgress(data.message, 100);
                    finalizeUI(data.message, data.status);
                }
            },
            error: function(xhr, status, error) {
                console.error("[VGT UPLINK CRASH] ", xhr.status, error);
                failSequence(`Native Uplink lost (HTTP ${xhr.status}). Scan aborted.`);
            }
        });
    }

    function updateProgress(msg, percent) {
        $('#vis-dynamic-desc').text(msg);
        $('#vis-progress-bar').css('width', percent + '%');
    }

    function updateLiveDOM(status) {
        if (status === 'clean' || status === 'init') {
            // 1. Module Header in Integrity View
            $('.vgt-module-header').css('border-left', '4px solid #10b981');
            $('.vgt-module-header .vgt-icon').first().replaceWith(`
                <svg class="vgt-icon" style="color:#10b981; width:24px; height:24px;" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            `);
            $('.vgt-module-title h2 span.vgt-badge').removeClass('vgt-badge-alert').addClass('vgt-badge-neutral').text('FILE HASHING ENGINE');
            $('.vgt-module-title .vgt-is-alert').removeClass('vgt-is-alert').addClass('vgt-is-active');
            $('.vgt-module-title .vgt-status-pulse').next('span').css('color', '#10b981').text('CLEAN');

            // 2. State Panels: Remove Anomaly Table, Show Clean State
            const cleanPanelHtml = `
                <div class="vgt-glass-panel vgt-state-clean" style="border-top:3px solid #10b981;">
                    <svg class="vgt-icon vgt-state-clean-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <h3>SYSTEM SECURE</h3>
                    <p>Alle überwachten Dateien stimmen exakt mit dem kryptographischen Manifest überein. Es wurden keine nicht-autorisierten Modifikationen (Zero-Day/Malware) im Dateisystem festgestellt.</p>
                </div>
            `;
            $('.vgt-table-container').fadeOut(200, function() {
                $(this).replaceWith(cleanPanelHtml);
            });

            // 3. Overview Cockpit Widget
            const $overviewCard = $('.vis-card-integrity-baseline');
            if ($overviewCard.length) {
                $overviewCard.find('.vis-badge').css({
                    'background': 'rgba(0, 255, 170, 0.12)',
                    'color': '#00ffaa',
                    'border': '1px solid rgba(0, 255, 170, 0.25)'
                }).text('SECURE');
                $overviewCard.find('.vis-integrity-circle-box').css('color', '#00ffaa').html(`
                    <svg class="vgt-icon" style="width:24px; height:24px;" viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                `);
                $overviewCard.find('p').html('Systemintegrität verifiziert.<br><small style="opacity: 0.6; font-family: monospace;">[State: Valid]</small>');
                $overviewCard.find('.vis-btn-sidebar-danger').removeClass('vis-btn-sidebar-danger').text('SCAN MANAGER');
            }
        }
    }

    function finalizeUI(msg, status) {
        let icon = "dashicons-yes";
        let color = "#00ffaa"; 
        const baselineAccepted = STATE.mode === 'reindex' && (status === 'clean' || status === 'init');

        if (status === 'warning') {
            icon = "dashicons-warning";
            color = "#ff4d4d"; 
        }

        if (status === 'init' || status === 'clean') {
            msg = msg || "System Baseline Created. System integrity securely verified.";
            updateLiveDOM(status);
        }

        UI.card.find('.vis-spinner-lg').removeClass('dashicons-update spin').addClass(icon).css('color', color);
        UI.card.find('.vis-status-title').text(status === 'clean' || status === 'init' ? 'SYSTEM SECURE' : 'ANOMALY DETECTED');
        $('#vis-dynamic-desc').text(msg).css('color', color);
        
        // Add completion button for instant close
        if (!UI.card.find('#vis-modal-done').length) {
            UI.card.append(`
                <div class="vis-modal-actions">
                    <button class="vis-btn-modal vis-btn-confirm" id="vis-modal-done" style="background:rgba(16,185,129,0.2); border-color:#10b981; color:#10b981;">OK, VERSTANDEN</button>
                </div>
            `);
            $('#vis-modal-done').off('click').on('click', function() {
                if (completionTimer !== null) {
                    window.clearTimeout(completionTimer);
                    completionTimer = null;
                }
                UI.overlay.removeClass('active');
                if (!baselineAccepted) {
                    reloadPage();
                }
            });
        }

        completionTimer = window.setTimeout(function() {
            completionTimer = null;
            UI.overlay.removeClass('active');
            if (!baselineAccepted) {
                reloadPage();
            }
        }, 1800);
    }

    function reloadPage() {
        const cleanUrl = window.location.href.split('#')[0];
        const sep = cleanUrl.indexOf('?') >= 0 ? '&' : '?';
        const urlWithoutVgt = cleanUrl.replace(/([&?])_vgt_r=\d+/, '');
        const targetUrl = urlWithoutVgt + (urlWithoutVgt.indexOf('?') >= 0 ? '&' : '?') + '_vgt_r=' + Date.now();
        window.location.replace(targetUrl);
    }

    function failSequence(reason) {
        UI.card.find('.vis-spinner-lg').removeClass('dashicons-update spin').addClass('dashicons-no').css('color', '#ff4d4d');
        UI.card.find('.vis-status-title').text('CRITICAL ERROR');
        $('#vis-dynamic-desc').text(reason).css('color', '#ff8082');
        $('#vis-progress-bar').css({'width': '100%', 'background': '#ff4d4d', 'box-shadow': '0 0 10px #ff4d4d'});
        
        if (!UI.card.find('#vis-modal-dismiss').length) {
            UI.card.append(`
                <div class="vis-modal-actions">
                    <button class="vis-btn-modal vis-btn-cancel" id="vis-modal-dismiss">SCHLIESSEN</button>
                </div>
            `);
            $('#vis-modal-dismiss').off('click').on('click', function() {
                UI.overlay.removeClass('active');
            });
        }

        UI.overlay.css('pointer-events', 'all').on('click', function(e) {
            if ($(e.target).hasClass('vis-glass-overlay')) {
                UI.overlay.removeClass('active');
                $(this).off('click');
            }
        });
    }
});
