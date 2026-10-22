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
        const i18n = telemetryData.i18n || {};

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

        // Escape HTML helper
        const escapeHtml = (str) => {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        // --- B. INTERACTIVE MAP TOOLTIP & CROSS-HIGHLIGHTING ---
        let activeFocusedIp = null;

        const setFocus = (ipOrIps, severity) => {
            if (!ipOrIps) return;
            const ipList = Array.isArray(ipOrIps) ? ipOrIps : String(ipOrIps).split(',').map(s => s.trim()).filter(Boolean);
            if (!ipList.length) return;
            const ipSet = new Set(ipList);

            // Highlight Marker
            document.querySelectorAll('.vis-threat-marker').forEach(m => {
                const mIps = (m.dataset.ips || m.dataset.ip || '').split(',').map(s => s.trim());
                const match = mIps.some(ip => ipSet.has(ip));
                m.classList.toggle('is-focused', match);
            });

            // Highlight Arc
            document.querySelectorAll('.vis-arc-item').forEach(a => {
                a.classList.toggle('is-focused', ipSet.has(a.dataset.ip));
            });

            // Highlight Row
            document.querySelectorAll('.vis-stream-row').forEach(r => {
                r.classList.toggle('is-focused', ipSet.has(r.dataset.ip));
            });
        };

        const clearFocus = () => {
            activeFocusedIp = null;
            document.querySelectorAll('.vis-threat-marker.is-focused, .vis-arc-item.is-focused, .vis-stream-row.is-focused').forEach(el => {
                el.classList.remove('is-focused');
            });
        };

        // Universal Smart 4-Way Dynamic Tooltip Positioning Engine (Above, Below, Right, Left)
        const positionTooltip = (posX, posY, isMarker = false) => {
            const stageRect = stage.getBoundingClientRect();

            // Ensure tooltip is visible in DOM to accurately measure height and width
            tooltip.style.display = 'block';

            const ttWidth = tooltip.offsetWidth || 280;
            const ttHeight = tooltip.offsetHeight || 190;
            const halfWidth = Math.ceil(ttWidth / 2);
            const halfHeight = Math.ceil(ttHeight / 2);
            const gap = isMarker ? 18 : 14;

            // Space available in all 4 directions inside stage boundaries
            const spaceAbove = posY - 14;
            const spaceBelow = stageRect.height - posY - 14;
            const spaceRight = stageRect.width - posX - 14;
            const spaceLeft = posX - 14;

            const fitsAbove = spaceAbove >= (ttHeight + gap);
            const fitsBelow = spaceBelow >= (ttHeight + gap);

            // Determine optimal placement:
            // 1. If distinctly in the lower portion and comfortably fits above -> ABOVE
            // 2. If distinctly in the upper portion and comfortably fits below -> BELOW
            // 3. Otherwise (middle latitudes or constrained height) -> SIDEWAYS (RIGHT or LEFT)
            if (fitsAbove && posY >= stageRect.height * 0.52) {
                // Place ABOVE
                const clampedX = Math.max(halfWidth + 14, Math.min(stageRect.width - halfWidth - 14, posX));
                tooltip.style.left = `${clampedX}px`;
                tooltip.style.top = `${posY}px`;
                tooltip.style.transform = `translate(-50%, calc(-100% - ${gap}px))`;
                tooltip.className = 'vis-threat-map-tooltip is-pos-above';
            } else if (fitsBelow && posY <= stageRect.height * 0.32) {
                // Place BELOW
                const clampedX = Math.max(halfWidth + 14, Math.min(stageRect.width - halfWidth - 14, posX));
                tooltip.style.left = `${clampedX}px`;
                tooltip.style.top = `${posY}px`;
                tooltip.style.transform = `translate(-50%, ${gap}px)`;
                tooltip.className = 'vis-threat-map-tooltip is-pos-below';
            } else {
                // Place SIDEWAYS (RIGHT or LEFT):
                // Clamp Y coordinate so the tooltip stays completely within top/bottom boundaries
                const clampedY = Math.max(halfHeight + 14, Math.min(stageRect.height - halfHeight - 14, posY));
                tooltip.style.top = `${clampedY}px`;

                if (spaceRight >= (ttWidth + gap) || spaceRight >= spaceLeft) {
                    // Place to the RIGHT
                    tooltip.style.left = `${posX + gap}px`;
                    tooltip.style.transform = 'translate(0, -50%)';
                    tooltip.className = 'vis-threat-map-tooltip is-pos-right';
                } else {
                    // Place to the LEFT
                    tooltip.style.left = `${posX - gap}px`;
                    tooltip.style.transform = 'translate(-100%, -50%)';
                    tooltip.className = 'vis-threat-map-tooltip is-pos-left';
                }
            }
        };

        // Threat Marker Mouse Events (Supports Single & Clustered Markers)
        const markerGroup = svgMap.querySelector('#visThreatMapMarkers');
        if (markerGroup && tooltip && stage) {
            markerGroup.addEventListener('mouseover', (e) => {
                const marker = e.target.closest('.vis-threat-marker');
                if (!marker) return;

                const isCluster = marker.classList.contains('is-cluster') || parseInt(marker.dataset.count || '1', 10) > 1;
                const count = parseInt(marker.dataset.count || '1', 10);
                const country = marker.dataset.country || 'UN';
                const countryName = marker.dataset.countryName || 'Unknown Region';
                const asn = marker.dataset.asn || 'AS-UNKNOWN';
                const asName = marker.dataset.asName || 'Carrier';
                const severity = marker.dataset.severity || 'blocked';
                const targetIps = marker.dataset.ips || marker.dataset.ip || '';

                // Elements
                const ttFlag = document.getElementById('visTtFlag');
                const ttIp = document.getElementById('visTtIp');
                const ttBadge = document.getElementById('visTtBadge');
                const ttCountry = document.getElementById('visTtCountry');
                const ttAsn = document.getElementById('visTtAsn');
                const ttRowPath = document.getElementById('visTtRowPath');
                const ttRowModule = document.getElementById('visTtRowModule');
                const ttPath = document.getElementById('visTtPath');
                const ttModule = document.getElementById('visTtModule');
                const ttClusterBody = document.getElementById('visTtClusterBody');

                if (ttFlag) ttFlag.textContent = country;
                if (ttCountry) ttCountry.textContent = `${countryName} (${country})`;
                if (ttAsn) ttAsn.textContent = `${asn} · ${asName}`;

                if (isCluster) {
                    // Merged cluster of multiple threats
                    let clusterEvents = [];
                    try {
                        clusterEvents = JSON.parse(marker.dataset.cluster || '[]');
                    } catch (err) {
                        console.warn('GeDefense ThreatMap: cluster JSON parse error', err);
                    }

                    if (ttIp) ttIp.textContent = `${count} ${i18n.threats_bundled || 'BEDROHUNGEN GEBÜNDELT'}`;
                    if (ttBadge) {
                        ttBadge.textContent = `${count} ${i18n.vectors || 'VEKTOREN'}`;
                        ttBadge.style.color = (severity === 'blocked') ? '#ff6482' : ((severity === 'suspicious') ? '#ffaa00' : '#00f3ff');
                        ttBadge.style.borderColor = (severity === 'blocked') ? 'rgba(255, 51, 102, 0.4)' : ((severity === 'suspicious') ? 'rgba(255, 170, 0, 0.4)' : 'rgba(0, 243, 255, 0.4)');
                    }

                    if (ttRowPath) ttRowPath.style.display = 'none';
                    if (ttRowModule) ttRowModule.style.display = 'none';

                    if (ttClusterBody) {
                        ttClusterBody.replaceChildren();
                        const headerDiv = document.createElement('div');
                        headerDiv.className = 'vis-tt-cluster-header';
                        const headerSpan = document.createElement('span');
                        headerSpan.textContent = `${i18n.attacked_paths_modules || 'ANGEGRIFFENE PFADE & MODULE'} (${count})`;
                        headerDiv.appendChild(headerSpan);
                        ttClusterBody.appendChild(headerDiv);

                        const itemsDiv = document.createElement('div');
                        itemsDiv.className = 'vis-tt-cluster-items';

                        clusterEvents.forEach(item => {
                            const isBl = item.severity === 'blocked';
                            const isSu = item.severity === 'suspicious';
                            const badgeColorClass = isBl ? 'is-blocked' : (isSu ? 'is-suspicious' : 'is-tracked');
                            const actText = (item.action || 'INTERCEPT').split(' ')[0];

                            const row = document.createElement('div');
                            row.className = 'vis-tt-cluster-row';

                            const left = document.createElement('div');
                            left.className = 'vis-tt-cr-left';

                            const pathSpan = document.createElement('span');
                            pathSpan.className = 'vis-tt-cr-path';
                            const pText = item.path || '/wp-login.php';
                            pathSpan.title = pText;
                            pathSpan.textContent = pText;

                            const ipSpan = document.createElement('span');
                            ipSpan.style.fontSize = '9px';
                            ipSpan.style.color = '#64748b';
                            ipSpan.style.fontFamily = 'monospace';
                            ipSpan.textContent = `IP: ${item.ip || ''}`;

                            left.appendChild(pathSpan);
                            left.appendChild(ipSpan);

                            const right = document.createElement('div');
                            right.className = 'vis-tt-cr-right';

                            const modChip = document.createElement('span');
                            modChip.className = 'vis-module-chip';
                            modChip.style.fontSize = '8.5px';
                            modChip.style.padding = '2px 5px';
                            modChip.textContent = item.module || 'Kernel';

                            const actBadge = document.createElement('span');
                            actBadge.className = `vis-badge ${badgeColorClass}`;
                            actBadge.style.fontSize = '8px';
                            actBadge.style.padding = '1px 4px';
                            actBadge.textContent = actText;

                            right.appendChild(modChip);
                            right.appendChild(actBadge);

                            row.appendChild(left);
                            row.appendChild(right);
                            itemsDiv.appendChild(row);
                        });
                        ttClusterBody.appendChild(itemsDiv);
                        ttClusterBody.style.display = 'flex';
                    }
                } else {
                    // Single threat node
                    const ip = marker.dataset.ip || '0.0.0.0';
                    const path = marker.dataset.path || '/wp-login.php';
                    const moduleName = marker.dataset.module || 'Kernel Security';
                    const action = marker.dataset.action || 'INTERCEPT';

                    if (ttIp) ttIp.textContent = ip;
                    if (ttBadge) {
                        ttBadge.textContent = action.split(' ')[0] || 'BLOCKED';
                        ttBadge.style.color = (severity === 'blocked') ? '#ff6482' : ((severity === 'suspicious') ? '#ffaa00' : '#00f3ff');
                        ttBadge.style.borderColor = (severity === 'blocked') ? 'rgba(255, 51, 102, 0.4)' : ((severity === 'suspicious') ? 'rgba(255, 170, 0, 0.4)' : 'rgba(0, 243, 255, 0.4)');
                    }

                    if (ttRowPath) {
                        ttRowPath.style.display = 'flex';
                        if (ttPath) ttPath.textContent = path;
                    }
                    if (ttRowModule) {
                        ttRowModule.style.display = 'flex';
                        if (ttModule) ttModule.textContent = `${moduleName} → ${action}`;
                    }
                    if (ttClusterBody) {
                        ttClusterBody.style.display = 'none';
                        ttClusterBody.replaceChildren();
                    }
                }

                // Position Tooltip relative to stage via smart dynamic boundary engine
                const stageRect = stage.getBoundingClientRect();
                const markerRect = marker.getBoundingClientRect();
                const posX = markerRect.left - stageRect.left + (markerRect.width / 2);
                const posY = markerRect.top - stageRect.top + (markerRect.height / 2);

                positionTooltip(posX, posY, true);
                setFocus(targetIps, severity);
            });

            markerGroup.addEventListener('mouseout', (e) => {
                const fromMarker = e.target.closest('.vis-threat-marker');
                const toMarker = e.relatedTarget && e.relatedTarget.closest ? e.relatedTarget.closest('.vis-threat-marker') : null;

                if (fromMarker && toMarker && fromMarker === toMarker) return;
                if (toMarker) return;

                tooltip.style.display = 'none';
                clearFocus();
            });
        }

        // Country Hover Tooltip (Follows mouse & flips dynamically)
        const countryGroup = svgMap.querySelector('.vis-map-countries-group');
        let activeHoveredCountry = null;

        if (countryGroup && tooltip && stage) {
            const handleCountryTooltip = (e, countryPath) => {
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
                const ttRowPath = document.getElementById('visTtRowPath');
                const ttRowModule = document.getElementById('visTtRowModule');
                const ttClusterBody = document.getElementById('visTtClusterBody');

                if (ttRowPath) ttRowPath.style.display = 'flex';
                if (ttRowModule) ttRowModule.style.display = 'flex';
                if (ttClusterBody) {
                    ttClusterBody.style.display = 'none';
                    ttClusterBody.replaceChildren();
                }

                if (ttFlag) ttFlag.textContent = code;
                if (ttIp) ttIp.textContent = name;
                if (ttBadge) {
                    ttBadge.textContent = threats > 0 ? `${threats} ${i18n.threats || 'THREATS'}` : (i18n.secure || 'SECURE');
                    ttBadge.style.color = threats > 0 ? '#ff6482' : '#10b981';
                    ttBadge.style.borderColor = threats > 0 ? 'rgba(255, 51, 102, 0.4)' : 'rgba(16, 185, 129, 0.4)';
                }
                if (ttCountry) ttCountry.textContent = `${i18n.iso_alpha2 || 'ISO Alpha-2:'} ${code}`;
                if (ttAsn) ttAsn.textContent = threats > 0 ? `${threats} ${i18n.vectors_captured || 'Angriffsvektoren erfasst'}` : (i18n.no_active_threats || 'Keine aktiven Drohungen');
                if (ttPath) ttPath.textContent = i18n.global_perimeter || 'Global Perimeter Monitoring';
                if (ttModule) ttModule.textContent = i18n.sovereign_geo_matrix || 'Sovereign Geo Matrix';

                const stageRect = stage.getBoundingClientRect();
                const posX = e.clientX - stageRect.left;
                const posY = e.clientY - stageRect.top;

                positionTooltip(posX, posY, false);
            };

            countryGroup.addEventListener('mouseover', (e) => {
                const countryPath = e.target.closest('.vis-map-country');
                if (!countryPath) return;
                activeHoveredCountry = countryPath;
                handleCountryTooltip(e, countryPath);
            });

            countryGroup.addEventListener('mousemove', (e) => {
                if (!activeHoveredCountry) {
                    const countryPath = e.target.closest('.vis-map-country');
                    if (countryPath) activeHoveredCountry = countryPath;
                    else return;
                }
                handleCountryTooltip(e, activeHoveredCountry);
            });

            countryGroup.addEventListener('mouseout', (e) => {
                if (e.relatedTarget && e.relatedTarget.closest && e.relatedTarget.closest('.vis-map-country')) return;
                activeHoveredCountry = null;
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
                    if (streamStatusLabel) streamStatusLabel.textContent = (activeEvents.length > 0) ? (i18n.stream_active || 'ECHTZEIT-TELEMETRIE AKTIV') : (i18n.airspace_secure || 'LUFTRAUM SICHER');
                } else {
                    this.classList.add('is-paused');
                    if (streamBtnIcon) streamBtnIcon.textContent = '▶';
                    if (streamStatusLabel) streamStatusLabel.textContent = i18n.stream_paused || 'STREAM PAUSIERT';
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
                        const safeSeverity = ['blocked', 'suspicious', 'monitored'].includes(ev.severity) ? ev.severity : 'monitored';
                        tr.className = `vis-stream-row ${safeSeverity}`;
                        tr.dataset.ip = ev.ip || '';
                        tr.dataset.severity = safeSeverity;

                        // col 1: Time
                        const tdTime = document.createElement('td');
                        tdTime.className = 'vis-col-time';
                        const timePulse = document.createElement('span');
                        timePulse.className = 'vis-time-pulse';
                        const timeTxt = document.createElement('span');
                        timeTxt.className = 'vis-time-txt';
                        timeTxt.textContent = ev.time || '';
                        tdTime.appendChild(timePulse);
                        tdTime.appendChild(timeTxt);
                        tr.appendChild(tdTime);

                        // col 2: IP
                        const tdIp = document.createElement('td');
                        tdIp.className = 'vis-col-ip';
                        const ipCell = document.createElement('div');
                        ipCell.className = 'vis-ip-cell';
                        const flagSpan = document.createElement('span');
                        flagSpan.className = 'vis-country-flag';
                        flagSpan.title = ev.country_name || '';
                        flagSpan.textContent = ev.country || '';
                        const ipCode = document.createElement('code');
                        ipCode.className = 'vis-ip-code';
                        ipCode.textContent = ev.ip || '';
                        ipCell.appendChild(flagSpan);
                        ipCell.appendChild(ipCode);
                        tdIp.appendChild(ipCell);
                        tr.appendChild(tdIp);

                        // col 3: Path
                        const tdPath = document.createElement('td');
                        tdPath.className = 'vis-col-path';
                        const pathCode = document.createElement('code');
                        pathCode.className = 'vis-path-code';
                        pathCode.title = escapeHtml(ev.path);
                        pathCode.textContent = escapeHtml(ev.path);
                        tdPath.appendChild(pathCode);
                        tr.appendChild(tdPath);

                        // col 4: Module
                        const tdMod = document.createElement('td');
                        tdMod.className = 'vis-col-module';
                        const modSpan = document.createElement('span');
                        modSpan.className = 'vis-module-chip';
                        modSpan.textContent = ev.module || '';
                        tdMod.appendChild(modSpan);
                        tr.appendChild(tdMod);

                        // col 5: Action
                        const tdAct = document.createElement('td');
                        tdAct.className = 'vis-col-action';
                        const actSpan = document.createElement('span');
                        actSpan.className = `vis-badge ${badgeClass}`;
                        actSpan.textContent = ev.action || '';
                        tdAct.appendChild(actSpan);
                        tr.appendChild(tdAct);

                        // col 6: ASN
                        const tdAsn = document.createElement('td');
                        tdAsn.className = 'vis-col-asn';
                        const asnCell = document.createElement('div');
                        asnCell.className = 'vis-asn-cell';
                        asnCell.title = `${ev.asn || ''} · ${ev.as_name || ''}`;
                        const asnNum = document.createElement('strong');
                        asnNum.className = 'vis-asn-num';
                        asnNum.textContent = ev.asn || '';
                        const asName = document.createElement('span');
                        asName.className = 'vis-as-name';
                        asName.textContent = ev.as_name || '';
                        asnCell.appendChild(asnNum);
                        asnCell.appendChild(asName);
                        tdAsn.appendChild(asnCell);
                        tr.appendChild(tdAsn);
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
                            const svgNS = 'http://www.w3.org/2000/svg';
                            const arcPath = document.createElementNS(svgNS, 'path');
                            arcPath.setAttribute('class', `vis-threat-arc ${isBl ? 'is-blocked' : (isSu ? 'is-suspicious' : 'is-tracked')}`);
                            arcPath.setAttribute('d', arcD);
                            arcPath.setAttribute('stroke', strokeGrad);
                            arcPath.setAttribute('fill', 'none');
                            arcG.appendChild(arcPath);

                            const projCircle = document.createElementNS(svgNS, 'circle');
                            projCircle.setAttribute('class', 'vis-threat-projectile');
                            projCircle.setAttribute('r', '2.2');
                            projCircle.setAttribute('fill', coreColor);
                            projCircle.setAttribute('filter', glowFilter);

                            const animMotion = document.createElementNS(svgNS, 'animateMotion');
                            animMotion.setAttribute('path', arcD);
                            animMotion.setAttribute('dur', '2.4s');
                            animMotion.setAttribute('repeatCount', 'indefinite');
                            projCircle.appendChild(animMotion);

                            arcG.appendChild(projCircle);
                            arcsGroup.appendChild(arcG);

                            // Marker element (supports dynamic clustering)
                            const coordKey = `${x1.toFixed(1)},${y1.toFixed(1)}`;
                            let existingMarker = markersGroup.querySelector(`[data-coords="${coordKey}"]`);

                            if (existingMarker) {
                                let clusterEvents = [];
                                try {
                                    clusterEvents = JSON.parse(existingMarker.dataset.cluster || '[]');
                                } catch (e) {
                                    clusterEvents = [];
                                }
                                clusterEvents.unshift(ev);
                                const newCount = clusterEvents.length;
                                const allIps = clusterEvents.map(it => it.ip).join(',');
                                existingMarker.dataset.count = newCount;
                                existingMarker.dataset.ips = allIps;
                                existingMarker.dataset.cluster = JSON.stringify(clusterEvents);

                                const hasBlocked = clusterEvents.some(it => it.severity === 'blocked');
                                const hasSuspicious = clusterEvents.some(it => it.severity === 'suspicious');
                                const clusterSev = hasBlocked ? 'blocked' : (hasSuspicious ? 'suspicious' : 'tracked');
                                const cColor = (clusterSev === 'blocked') ? '#ff3366' : ((clusterSev === 'suspicious') ? '#ffaa00' : '#00f3ff');
                                const gFilter = (clusterSev === 'blocked') ? 'url(#visGlowRose)' : 'url(#visGlowCyan)';

                                existingMarker.setAttribute('class', `vis-threat-marker is-cluster is-${clusterSev}`);
                                existingMarker.dataset.severity = clusterSev;
                                const svgNS = 'http://www.w3.org/2000/svg';
                                existingMarker.replaceChildren();

                                const pulse = document.createElementNS(svgNS, 'circle');
                                pulse.setAttribute('class', 'vis-sonar-pulse');
                                pulse.setAttribute('r', '13');
                                pulse.setAttribute('fill', cColor);
                                pulse.setAttribute('opacity', '0.3');
                                existingMarker.appendChild(pulse);

                                const ring = document.createElementNS(svgNS, 'circle');
                                ring.setAttribute('class', 'vis-cluster-ring-outer');
                                ring.setAttribute('r', '8.5');
                                ring.setAttribute('fill', 'rgba(6, 14, 26, 0.9)');
                                ring.setAttribute('stroke', cColor);
                                ring.setAttribute('stroke-width', '1.4');
                                existingMarker.appendChild(ring);

                                const core = document.createElementNS(svgNS, 'circle');
                                core.setAttribute('class', 'vis-marker-core');
                                core.setAttribute('r', '4.2');
                                core.setAttribute('fill', cColor);
                                core.setAttribute('filter', gFilter);
                                existingMarker.appendChild(core);

                                const txt = document.createElementNS(svgNS, 'text');
                                txt.setAttribute('class', 'vis-cluster-badge-text');
                                txt.setAttribute('x', '0');
                                txt.setAttribute('y', '2.8');
                                txt.setAttribute('text-anchor', 'middle');
                                txt.textContent = String(newCount);
                                existingMarker.appendChild(txt);
                            } else {
                                const markerG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                                markerG.setAttribute('class', `vis-threat-marker ${isBl ? 'is-blocked' : (isSu ? 'is-suspicious' : 'is-tracked')}`);
                                markerG.dataset.count = '1';
                                markerG.dataset.coords = coordKey;
                                markerG.dataset.ips = ev.ip;
                                markerG.dataset.ip = ev.ip;
                                markerG.dataset.country = ev.country;
                                markerG.dataset.countryName = ev.country_name;
                                markerG.dataset.asn = ev.asn;
                                markerG.dataset.asName = ev.as_name;
                                markerG.dataset.path = ev.path;
                                markerG.dataset.module = ev.module;
                                markerG.dataset.action = ev.action;
                                markerG.dataset.severity = ev.severity;
                                markerG.dataset.cluster = JSON.stringify([ev]);
                                markerG.setAttribute('transform', `translate(${x1.toFixed(2)}, ${y1.toFixed(2)})`);
                                const svgNS = 'http://www.w3.org/2000/svg';
                                const pulse = document.createElementNS(svgNS, 'circle');
                                pulse.setAttribute('class', 'vis-sonar-pulse');
                                pulse.setAttribute('r', '9');
                                pulse.setAttribute('fill', coreColor);
                                pulse.setAttribute('opacity', '0.3');
                                markerG.appendChild(pulse);

                                const ring = document.createElementNS(svgNS, 'circle');
                                ring.setAttribute('class', 'vis-marker-ring');
                                ring.setAttribute('r', '5');
                                ring.setAttribute('fill', 'none');
                                ring.setAttribute('stroke', coreColor);
                                ring.setAttribute('stroke-width', '1.2');
                                markerG.appendChild(ring);

                                const core = document.createElementNS(svgNS, 'circle');
                                core.setAttribute('class', 'vis-marker-core');
                                core.setAttribute('r', '3.2');
                                core.setAttribute('fill', coreColor);
                                core.setAttribute('filter', glowFilter);
                                markerG.appendChild(core);
                                markersGroup.appendChild(markerG);
                            }
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

