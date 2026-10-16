/**
 * VGT OVERVIEW MISSION CONTROL CONTROLLER
 * STATUS: DIAMANT VGT SUPREME
 * Pure Vanilla JS, Zero External Dependencies.
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // =========================================================================
    // 1. ORACLE INTELLIGENCE ACCORDION (Zero-Dependency Event Delegation)
    // =========================================================================
    const oracleList = document.querySelector('.vgt-oracle-list');
    if (oracleList && !oracleList.dataset.accordionInit) {
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
    }

    // =========================================================================
    // 2. AUTHENTIC CYBER DEFENSE AIRSPACE RADAR CONTROLLER
    // =========================================================================
    const radarCard = document.getElementById('vis-defense-radar-card');
    const radarHud = document.getElementById('vis-radar-target-hud');
    const hudClose = document.getElementById('vis-hud-close');

    if (radarCard && radarHud) {
        const hudSeverity = document.getElementById('vis-hud-severity');
        const hudIp = document.getElementById('vis-hud-ip');
        const hudModule = document.getElementById('vis-hud-module');
        const hudBearing = document.getElementById('vis-hud-bearing');
        const hudRange = document.getElementById('vis-hud-range');
        const hudTime = document.getElementById('vis-hud-time');
        const hudMsg = document.getElementById('vis-hud-msg');

        let activeTarget = null;

        const closeHud = () => {
            radarHud.style.display = 'none';
            if (activeTarget) {
                activeTarget.classList.remove('is-selected');
                activeTarget = null;
            }
        };

        const showTargetHud = (targetEl) => {
            if (!(targetEl instanceof HTMLElement)) return;

            if (activeTarget) {
                activeTarget.classList.remove('is-selected');
            }
            activeTarget = targetEl;
            activeTarget.classList.add('is-selected');

            const ip = targetEl.dataset.ip || targetEl.dataset.name || '0.0.0.0';
            const moduleName = targetEl.dataset.module || 'SENTINEL CORE';
            const severity = (targetEl.dataset.severity || 'monitored').toLowerCase();
            const bearing = targetEl.dataset.bearing || '000°';
            const range = targetEl.dataset.range || '50%';
            const time = targetEl.dataset.time || '-';
            const msg = targetEl.dataset.msg || '-';

            if (hudIp) hudIp.textContent = ip;
            if (hudModule) hudModule.textContent = moduleName;
            if (hudBearing) hudBearing.textContent = bearing;
            if (hudRange) hudRange.textContent = range;
            if (hudTime) hudTime.textContent = time;
            if (hudMsg) hudMsg.textContent = msg;

            if (hudSeverity) {
                let badgeText = 'TARGET';
                let badgeClass = 'vis-badge-info';
                if (severity === 'critical') {
                    badgeText = 'CRITICAL THREAT';
                    badgeClass = 'vis-badge-danger';
                } else if (severity === 'warning') {
                    badgeText = 'ANOMALY / WARN';
                    badgeClass = 'vis-badge-warning';
                } else if (severity === 'friendly') {
                    badgeText = 'FRIENDLY NODE';
                    badgeClass = 'vis-badge-success';
                } else if (severity === 'offline') {
                    badgeText = 'NODE STANDBY';
                    badgeClass = 'vis-badge-muted';
                } else {
                    badgeText = 'MONITORED';
                    badgeClass = 'vis-badge-info';
                }
                hudSeverity.className = 'vis-radar-hud-tag ' + badgeClass;
                hudSeverity.textContent = badgeText;
            }

            radarHud.style.display = 'block';
        };

        // Event delegation on radar container for target blips and nodes
        const radarContainer = radarCard.querySelector('.vis-radar-container');
        if (radarContainer) {
            radarContainer.addEventListener('click', function(e) {
                const target = e.target.closest('.vis-radar-blip, .vis-radar-node');
                if (target) {
                    e.stopPropagation();
                    showTargetHud(target);
                } else {
                    closeHud();
                }
            });

            radarContainer.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    const target = e.target.closest('.vis-radar-blip, .vis-radar-node');
                    if (target) {
                        e.preventDefault();
                        showTargetHud(target);
                    }
                } else if (e.key === 'Escape') {
                    closeHud();
                }
            });
        }

        if (hudClose) {
            hudClose.addEventListener('click', function(e) {
                e.stopPropagation();
                closeHud();
            });
        }

        // Dismiss on outside click
        document.addEventListener('click', function(e) {
            if (!radarCard.contains(e.target)) {
                closeHud();
            }
        });
    }

    // =========================================================================
    // 3. LIVING TELEMETRY COUNT-UP ENGINE (Glass 2100 Eased Acceleration)
    // =========================================================================
    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const countupElements = document.querySelectorAll('.vgt-countup');
    const scoreRing = document.getElementById('vis-overview-score-ring');

    if (countupElements.length > 0 && !prefersReducedMotion) {
        const duration = 1400; // ms
        const startTime = performance.now();
        const numberFormatter = new Intl.NumberFormat();

        // Animate elements and score ring in sync
        const animateTelemetry = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Cubic ease-out: responsive initial ramp, silky smooth settlement
            const ease = 1 - Math.pow(1 - progress, 3);

            countupElements.forEach(el => {
                const target = parseFloat(el.dataset.target || '0');
                const format = el.dataset.format || 'raw';
                const suffix = el.dataset.suffix || '';
                const currentVal = Math.round(target * ease);

                if (format === 'int') {
                    el.textContent = numberFormatter.format(currentVal) + suffix;
                } else {
                    el.textContent = currentVal + suffix;
                }
            });

            // Concurrently fill the conic-gradient angle on the Master Score Ring
            if (scoreRing) {
                const targetScore = parseFloat(scoreRing.querySelector('.vgt-countup')?.dataset.target || '0');
                const currentScoreDeg = (targetScore * ease * 3.6).toFixed(1);
                scoreRing.style.setProperty('--vsc-score', `${currentScoreDeg}deg`);
            }

            if (progress < 1) {
                requestAnimationFrame(animateTelemetry);
            }
        };

        requestAnimationFrame(animateTelemetry);
    }

    // =========================================================================
    // 4. REAL-TIME THREAT MAP & LIVE ANOMALY STREAM CONTROLLER (GLASS 2100)
    // =========================================================================
    const threatCard = document.getElementById('visThreatMapCard');
    const svgViewport = document.getElementById('visThreatSvgViewport');
    const svgMap = document.getElementById('visThreatMapSvg');
    const tooltip = document.getElementById('visThreatMapTooltip');
    const streamBody = document.getElementById('visThreatStreamBody');
    const streamToggleBtn = document.getElementById('visMapStreamToggle');
    const streamStatusLabel = document.getElementById('visStreamStatusLabel');
    const streamBtnIcon = document.getElementById('visStreamBtnIcon');
    const streamCounter = document.getElementById('visStreamCounter');

    if (threatCard && svgViewport && svgMap) {
        // Parse Initial Telemetry Data
        let telemetryData = { events: [], origin: {}, originPt: {} };
        const jsonEl = document.getElementById('visThreatMapJson');
        if (jsonEl && jsonEl.textContent) {
            try {
                telemetryData = JSON.parse(jsonEl.textContent);
            } catch (err) {
                console.warn('GeDefense ThreatMap: Telemetry JSON parse fallback', err);
            }
        }

        // --- A. ZOOM & PAN STATE ENGINE ---
        let scale = 1.0;
        let transX = 0;
        let transY = 0;
        let isDragging = false;
        let startX = 0;
        let startY = 0;

        const updateTransform = () => {
            svgViewport.style.transform = `translate(${transX}px, ${transY}px) scale(${scale})`;
        };

        const zoomInBtn = document.getElementById('visMapZoomIn');
        const zoomOutBtn = document.getElementById('visMapZoomOut');
        const zoomResetBtn = document.getElementById('visMapZoomReset');

        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                scale = Math.min(3.8, scale + 0.35);
                updateTransform();
            });
        }

        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                scale = Math.max(1.0, scale - 0.35);
                if (scale === 1.0) {
                    transX = 0;
                    transY = 0;
                }
                updateTransform();
            });
        }

        if (zoomResetBtn) {
            zoomResetBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                scale = 1.0;
                transX = 0;
                transY = 0;
                updateTransform();
            });
        }

        // Mouse Drag to Pan
        const stage = document.getElementById('visThreatMapStage');
        if (stage) {
            stage.addEventListener('mousedown', (e) => {
                if (scale <= 1.0) return;
                isDragging = true;
                svgViewport.classList.add('is-dragging');
                startX = e.clientX - transX;
                startY = e.clientY - transY;
            });

            window.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                transX = e.clientX - startX;
                transY = e.clientY - startY;
                // Clamp pan bounds
                const maxPan = (scale - 1) * 320;
                transX = Math.max(-maxPan, Math.min(maxPan, transX));
                transY = Math.max(-maxPan, Math.min(maxPan, transY));
                updateTransform();
            });

            window.addEventListener('mouseup', () => {
                if (isDragging) {
                    isDragging = false;
                    svgViewport.classList.remove('is-dragging');
                }
            });
        }

        // --- B. INTERACTIVE MAP TOOLTIP & CROSS-HIGHLIGHTING ---
        let activeFocusedIp = null;

        const setFocus = (ip, severity) => {
            if (activeFocusedIp === ip) return;
            activeFocusedIp = ip;

            // Highlight Marker
            document.querySelectorAll('.vis-threat-marker').forEach(m => {
                m.classList.toggle('is-focused', m.dataset.ip === ip);
            });

            // Highlight Arc
            document.querySelectorAll('.vis-arc-item').forEach(a => {
                a.classList.toggle('is-focused', a.dataset.ip === ip);
            });

            // Highlight Row
            document.querySelectorAll('.vis-stream-row').forEach(r => {
                r.classList.toggle('is-focused', r.dataset.ip === ip);
            });
        };

        const clearFocus = () => {
            activeFocusedIp = null;
            document.querySelectorAll('.vis-threat-marker.is-focused, .vis-arc-item.is-focused, .vis-stream-row.is-focused').forEach(el => {
                el.classList.remove('is-focused');
            });
        };

        // Threat Marker Mouse Events
        const markerGroup = svgMap.querySelector('#visThreatMapMarkers');
        if (markerGroup && tooltip && stage) {
            markerGroup.addEventListener('mouseover', (e) => {
                const marker = e.target.closest('.vis-threat-marker');
                if (!marker) return;

                const ip = marker.dataset.ip || '0.0.0.0';
                const country = marker.dataset.country || 'UN';
                const countryName = marker.dataset.countryName || 'Unknown Region';
                const asn = marker.dataset.asn || 'AS-UNKNOWN';
                const asName = marker.dataset.asName || 'Carrier';
                const path = marker.dataset.path || '/wp-login.php';
                const moduleName = marker.dataset.module || 'Kernel Security';
                const action = marker.dataset.action || 'INTERCEPT';
                const severity = marker.dataset.severity || 'blocked';

                // Populate Tooltip
                const ttFlag = document.getElementById('visTtFlag');
                const ttIp = document.getElementById('visTtIp');
                const ttBadge = document.getElementById('visTtBadge');
                const ttCountry = document.getElementById('visTtCountry');
                const ttAsn = document.getElementById('visTtAsn');
                const ttPath = document.getElementById('visTtPath');
                const ttModule = document.getElementById('visTtModule');

                if (ttFlag) ttFlag.textContent = country;
                if (ttIp) ttIp.textContent = ip;
                if (ttBadge) {
                    ttBadge.textContent = action.split(' ')[0] || 'BLOCKED';
                    ttBadge.style.color = (severity === 'blocked') ? '#ff6482' : ((severity === 'suspicious') ? '#ffaa00' : '#00f3ff');
                    ttBadge.style.borderColor = (severity === 'blocked') ? 'rgba(255, 51, 102, 0.4)' : ((severity === 'suspicious') ? 'rgba(255, 170, 0, 0.4)' : 'rgba(0, 243, 255, 0.4)');
                }
                if (ttCountry) ttCountry.textContent = `${countryName} (${country})`;
                if (ttAsn) ttAsn.textContent = `${asn} · ${asName}`;
                if (ttPath) ttPath.textContent = path;
                if (ttModule) ttModule.textContent = `${moduleName} → ${action}`;

                // Position Tooltip relative to stage
                const stageRect = stage.getBoundingClientRect();
                const markerRect = marker.getBoundingClientRect();
                const posX = markerRect.left - stageRect.left + (markerRect.width / 2);
                const posY = markerRect.top - stageRect.top;

                tooltip.style.left = `${posX}px`;
                tooltip.style.top = `${posY}px`;
                tooltip.style.display = 'block';

                setFocus(ip, severity);
            });

            markerGroup.addEventListener('mouseout', (e) => {
                if (e.relatedTarget && e.relatedTarget.closest && e.relatedTarget.closest('.vis-threat-marker')) return;
                tooltip.style.display = 'none';
                clearFocus();
            });
        }

        // Country Hover Tooltip
        const countryGroup = svgMap.querySelector('.vis-map-countries-group');
        if (countryGroup && tooltip && stage) {
            countryGroup.addEventListener('mouseover', (e) => {
                const countryPath = e.target.closest('.vis-map-country');
                if (!countryPath) return;

                const name = countryPath.dataset.name || 'Region';
                const code = countryPath.dataset.code || '';
                const threats = telemetryData.countries ? (telemetryData.countries[code] || 0) : 0;

                const ttFlag = document.getElementById('visTtFlag');
                const ttIp = document.getElementById('visTtIp');
                const ttBadge = document.getElementById('visTtBadge');
                const ttCountry = document.getElementById('visTtCountry');
                const ttAsn = document.getElementById('visTtAsn');
                const ttPath = document.getElementById('visTtPath');
                const ttModule = document.getElementById('visTtModule');

                if (ttFlag) ttFlag.textContent = code;
                if (ttIp) ttIp.textContent = name;
                if (ttBadge) {
                    ttBadge.textContent = threats > 0 ? `${threats} THREATS` : 'SECURE';
                    ttBadge.style.color = threats > 0 ? '#ff6482' : '#10b981';
                    ttBadge.style.borderColor = threats > 0 ? 'rgba(255, 51, 102, 0.4)' : 'rgba(16, 185, 129, 0.4)';
                }
                if (ttCountry) ttCountry.textContent = `ISO Alpha-2: ${code}`;
                if (ttAsn) ttAsn.textContent = threats > 0 ? `${threats} Angriffsvektoren erfasst` : 'Keine aktiven Drohungen';
                if (ttPath) ttPath.textContent = 'Global Perimeter Monitoring';
                if (ttModule) ttModule.textContent = 'Sovereign Geo Matrix';

                const stageRect = stage.getBoundingClientRect();
                const posX = e.clientX - stageRect.left;
                const posY = e.clientY - stageRect.top - 12;

                tooltip.style.left = `${posX}px`;
                tooltip.style.top = `${posY}px`;
                tooltip.style.display = 'block';
            });

            countryGroup.addEventListener('mouseout', (e) => {
                if (e.relatedTarget && e.relatedTarget.closest && e.relatedTarget.closest('.vis-map-country')) return;
                tooltip.style.display = 'none';
            });
        }

        // Table Row Hover & Click Cross-Highlighting
        if (streamBody) {
            streamBody.addEventListener('mouseover', (e) => {
                const row = e.target.closest('.vis-stream-row');
                if (!row) return;
                const ip = row.dataset.ip;
                const severity = row.dataset.severity;
                if (ip) setFocus(ip, severity);
            });

            streamBody.addEventListener('mouseout', (e) => {
                const row = e.target.closest('.vis-stream-row');
                if (row && (!e.relatedTarget || !e.relatedTarget.closest('.vis-stream-row'))) {
                    clearFocus();
                }
            });

            streamBody.addEventListener('click', (e) => {
                const row = e.target.closest('.vis-stream-row');
                if (!row) return;
                const ip = row.dataset.ip;
                if (!ip) return;
                const marker = svgMap.querySelector(`.vis-threat-marker[data-ip="${ip}"]`);
                if (marker) {
                    marker.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
                }
            });
        }

        // --- C. STREAM FILTER PILLS ---
        const filterPills = threatCard.querySelectorAll('.vis-filter-pill');
        filterPills.forEach(pill => {
            pill.addEventListener('click', function() {
                filterPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter || 'all';

                // Filter Stream Rows
                document.querySelectorAll('.vis-stream-row').forEach(row => {
                    const sev = row.dataset.severity || '';
                    if (filter === 'all' || sev === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Filter Markers and Arcs on Map
                document.querySelectorAll('.vis-threat-marker').forEach(marker => {
                    const sev = marker.dataset.severity || '';
                    marker.style.display = (filter === 'all' || sev === filter) ? '' : 'none';
                });
                document.querySelectorAll('.vis-arc-item').forEach(arc => {
                    const sev = arc.dataset.severity || '';
                    arc.style.display = (filter === 'all' || sev === filter) ? '' : 'none';
                });
            });
        });

        // --- D. 100% GENUINE TELEMETRY ENGINE (ZERO SIMULATION) ---
        let isStreamActive = true;
        let ambientScanTimer = null;
        let autoPollTimer = null;

        if (streamToggleBtn) {
            streamToggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                isStreamActive = !isStreamActive;
                if (isStreamActive) {
                    this.classList.remove('is-paused');
                    if (streamBtnIcon) streamBtnIcon.textContent = '❚❚';
                    if (streamStatusLabel) streamStatusLabel.textContent = (activeEvents.length > 0) ? 'ECHTZEIT-TELEMETRIE AKTIV' : 'LUFTRAUM SICHER';
                } else {
                    this.classList.add('is-paused');
                    if (streamBtnIcon) streamBtnIcon.textContent = '▶';
                    if (streamStatusLabel) streamStatusLabel.textContent = 'STREAM PAUSIERT';
                }
            });
        }

        // 1. Ambient Map Sweep on Genuine Markers (No Fake Rows)
        let eventCycleIndex = 0;
        const ambientMapScan = () => {
            if (!isStreamActive || activeEvents.length === 0) return;

            eventCycleIndex = (eventCycleIndex + 1) % activeEvents.length;
            const ev = activeEvents[eventCycleIndex];

            // Highlight real marker
            const marker = svgMap.querySelector(`.vis-threat-marker[data-ip="${ev.ip}"]`);
            if (marker) {
                marker.classList.add('is-focused');
                setTimeout(() => marker.classList.remove('is-focused'), 1400);
            }

            // Ripple Origin Hub
            const originGlow = svgMap.querySelector('.vis-origin-glow');
            if (originGlow) {
                originGlow.setAttribute('r', '36');
                setTimeout(() => originGlow.setAttribute('r', '28'), 600);
            }
        };

        if (activeEvents.length > 0) {
            ambientScanTimer = setInterval(ambientMapScan, 3800);
        }

        // 2. Genuine Database Telemetry Poller (Queries REAL threats via AJAX)
        const knownIps = new Set(activeEvents.map(e => e.ip));

        const fetchLiveThreatUpdates = async () => {
            if (!isStreamActive) return;
            const ajaxUrl = window.visConfig?.ajaxUrl || (typeof ajaxurl !== 'undefined' ? ajaxurl : null);
            const nonce = window.visConfig?.nonce || null;
            if (!ajaxUrl || !nonce) return;

            try {
                const formData = new FormData();
                formData.append('action', 'vis_get_live_threats');
                formData.append('nonce', nonce);

                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) return;
                const result = await response.json();
                if (!result.success || !result.data || !Array.isArray(result.data.events)) return;

                const incoming = result.data.events;
                let addedCount = 0;

                incoming.forEach(ev => {
                    if (knownIps.has(ev.ip)) return;
                    knownIps.add(ev.ip);
                    activeEvents.unshift(ev);
                    addedCount++;

                    // Prepend real row to table
                    if (streamBody) {
                        const emptyRow = streamBody.querySelector('.vis-stream-empty');
                        if (emptyRow) emptyRow.remove();

                        const isBlocked = (ev.severity === 'blocked');
                        const isSuspicious = (ev.severity === 'suspicious');
                        const badgeClass = isBlocked ? 'vis-badge-danger' : (isSuspicious ? 'vis-badge-warning' : 'vis-badge-info');

                        const tr = document.createElement('tr');
                        tr.className = `vis-stream-row ${ev.severity}`;
                        tr.dataset.ip = ev.ip;
                        tr.dataset.severity = ev.severity;

                        tr.innerHTML = `
                            <td class="vis-col-time">
                                <span class="vis-time-pulse"></span>
                                <span class="vis-time-txt">${ev.time}</span>
                            </td>
                            <td class="vis-col-ip">
                                <div class="vis-ip-cell">
                                    <span class="vis-country-flag" title="${ev.country_name}">${ev.country}</span>
                                    <code class="vis-ip-code">${ev.ip}</code>
                                </div>
                            </td>
                            <td class="vis-col-path">
                                <code class="vis-path-code" title="${ev.path}">${ev.path}</code>
                            </td>
                            <td class="vis-col-module">
                                <span class="vis-module-chip">${ev.module}</span>
                            </td>
                            <td class="vis-col-action">
                                <span class="vis-badge ${badgeClass}">${ev.action}</span>
                            </td>
                            <td class="vis-col-asn">
                                <div class="vis-asn-cell" title="${ev.asn} · ${ev.as_name}">
                                    <strong class="vis-asn-num">${ev.asn}</strong>
                                    <span class="vis-as-name">${ev.as_name}</span>
                                </div>
                            </td>
                        `;
                        streamBody.insertBefore(tr, streamBody.firstChild);
                    }

                    // Dynamically append new Arc and Marker to SVG map
                    const arcsGroup = svgMap.querySelector('#visThreatMapArcs');
                    const markersGroup = svgMap.querySelector('#visThreatMapMarkers');
                    const originPt = telemetryData.originPt || { x: 471.49, y: 269.36 };

                    if (arcsGroup && markersGroup) {
                        const x1 = parseFloat(ev.x);
                        const y1 = parseFloat(ev.y);
                        const x2 = parseFloat(originPt.x);
                        const y2 = parseFloat(originPt.y);
                        const dx = x2 - x1;
                        const dy = y2 - y1;
                        const dist = Math.sqrt(dx * dx + dy * dy);

                        if (dist >= 1.0) {
                            const mx = (x1 + x2) / 2.0;
                            const my = (y1 + y2) / 2.0;
                            let nx = -dy / dist;
                            let ny = dx / dist;
                            const curvature = Math.min(75.0, Math.max(25.0, dist * 0.16));
                            if (ny > 0) { nx = -nx; ny = -ny; }
                            const cx = Math.round(mx + (nx * curvature));
                            const cy = Math.round(my + (ny * curvature) - 10.0);
                            const arcD = `M ${x1},${y1} Q ${cx},${cy} ${x2},${y2}`;

                            const isBl = (ev.severity === 'blocked');
                            const isSu = (ev.severity === 'suspicious');
                            const strokeGrad = isBl ? 'url(#visArcGradBlocked)' : (isSu ? 'url(#visArcGradTracked)' : 'rgba(0, 243, 255, 0.45)');
                            const coreColor = isBl ? '#ff3366' : (isSu ? '#ffaa00' : '#00f3ff');
                            const glowFilter = isBl ? 'url(#visGlowRose)' : 'url(#visGlowCyan)';

                            // Arc element
                            const arcG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                            arcG.setAttribute('class', 'vis-arc-item');
                            arcG.dataset.ip = ev.ip;
                            arcG.dataset.severity = ev.severity;
                            arcG.innerHTML = `
                                <path class="vis-threat-arc ${isBl ? 'is-blocked' : (isSu ? 'is-suspicious' : 'is-tracked')}" d="${arcD}" stroke="${strokeGrad}" fill="none" />
                                <circle class="vis-threat-projectile" r="2.2" fill="${coreColor}" filter="${glowFilter}">
                                    <animateMotion path="${arcD}" dur="2.4s" repeatCount="indefinite" />
                                </circle>
                            `;
                            arcsGroup.appendChild(arcG);

                            // Marker element
                            const markerG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                            markerG.setAttribute('class', `vis-threat-marker ${isBl ? 'is-blocked' : (isSu ? 'is-suspicious' : 'is-tracked')}`);
                            markerG.dataset.ip = ev.ip;
                            markerG.dataset.country = ev.country;
                            markerG.dataset.countryName = ev.country_name;
                            markerG.dataset.asn = ev.asn;
                            markerG.dataset.asName = ev.as_name;
                            markerG.dataset.path = ev.path;
                            markerG.dataset.module = ev.module;
                            markerG.dataset.action = ev.action;
                            markerG.dataset.severity = ev.severity;
                            markerG.setAttribute('transform', `translate(${x1.toFixed(2)}, ${y1.toFixed(2)})`);
                            markerG.innerHTML = `
                                <circle class="vis-sonar-pulse" r="9" fill="${coreColor}" opacity="0.3"></circle>
                                <circle class="vis-marker-ring" r="5" fill="none" stroke="${coreColor}" stroke-width="1.2"></circle>
                                <circle class="vis-marker-core" r="3.2" fill="${coreColor}" filter="${glowFilter}"></circle>
                            `;
                            markersGroup.appendChild(markerG);
                        }
                    }

                    // Remove clean indicator on first real attack
                    const cleanIndicator = document.getElementById('visThreatCleanIndicator');
                    if (cleanIndicator) cleanIndicator.remove();
                });

                if (addedCount > 0) {
                    if (streamCounter) streamCounter.textContent = activeEvents.length;
                    const totalChip = document.getElementById('visThreatTotalSources');
                    if (totalChip) totalChip.textContent = activeEvents.length;
                    const blockedChip = document.getElementById('visThreatBlockedCount');
                    if (blockedChip && result.data.blocked !== undefined) blockedChip.textContent = result.data.blocked;
                    const countryChip = document.getElementById('visThreatCountryCount');
                    if (countryChip && result.data.countries) countryChip.textContent = Object.keys(result.data.countries).length;
                    if (streamStatusLabel) streamStatusLabel.textContent = 'ECHTZEIT-TELEMETRIE AKTIV';

                    if (!ambientScanTimer) {
                        ambientScanTimer = setInterval(ambientMapScan, 3800);
                    }
                }
            } catch (err) {
                // Fail silently on network interruptions
            }
        };

        // Poll for genuine real-time threats every 25 seconds
        autoPollTimer = setInterval(fetchLiveThreatUpdates, 25000);
    }
});

