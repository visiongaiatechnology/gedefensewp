<?php
/**
 * GEODEFENSE REAL-TIME THREAT MAP & LIVE ANOMALY STREAM
 * STATUS: DIAMANT VGT SUPREME
 * 
 * 100% Local BGP/ASN Cartography Engine. Zero third-party requests. Pure native SVG & Vanilla JS.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

/**
 * Variables expected from parent scope (view-overview.php):
 * @var array $host_origin
 * @var array $host_point
 * @var array $threat_map_events
 * @var array $country_threat_counts
 * @var int   $threat_blocked_count
 * @var int   $threat_suspicious_count
 */
?>
<!-- =========================================================================
     FULL-WIDTH REAL-TIME THREAT MAP & LIVE ANOMALY STREAM (GLASS 2100)
     ========================================================================= -->
<div class="vis-threat-map-section vis-cyber-card" id="visThreatMapCard">

    <!-- Section Header with Live Badges, Telemetry Counters & Navigation Controls -->
    <div class="vis-cyber-card-header vis-threat-map-header">
        <div class="vis-threat-map-header-left">
            <h3>
                <span class="vis-threat-header-icon-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="vgt-icon"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                </span>
                <?php esc_html_e('ECHTZEIT THREAT KARTE & GLOBALE ANGRIFFSVEKTOREN', 'vgt-sentinel'); ?>
            </h3>
            <div class="vis-threat-live-badge" title="<?php esc_attr_e('Lokaler Telemetrie-Stream aktiv', 'vgt-sentinel'); ?>">
                <span class="vis-threat-live-dot"></span>
                <span id="visStreamStatusLabel"><?php echo empty($threat_map_events) ? esc_html__('LUFTRAUM SICHER', 'vgt-sentinel') : esc_html__('ECHTZEIT-TELEMETRIE AKTIV', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vis-threat-geo-badge" title="<?php esc_attr_e('Vollständig autark ohne Cloud-Abfragen', 'vgt-sentinel'); ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <span><?php esc_html_e('100% LOKALE GEOIP & ASN MATRIX · 0 CLOUD LEAKS', 'vgt-sentinel'); ?></span>
            </div>
        </div>

        <div class="vis-threat-map-header-right">
            <!-- Telemetry summary chips -->
            <div class="vis-threat-kpi-chip" title="<?php esc_attr_e('Aktive Angreifer-IPs im Erfassungsbereich', 'vgt-sentinel'); ?>">
                <span class="vis-chip-label"><?php esc_html_e('Quellen:', 'vgt-sentinel'); ?></span>
                <strong class="vis-chip-val" id="visThreatTotalSources"><?php echo (int)count($threat_map_events); ?></strong>
            </div>
            <div class="vis-threat-kpi-chip" title="<?php esc_attr_e('Identifizierte Ursprungsländer', 'vgt-sentinel'); ?>">
                <span class="vis-chip-label"><?php esc_html_e('Länder:', 'vgt-sentinel'); ?></span>
                <strong class="vis-chip-val" id="visThreatCountryCount"><?php echo (int)count($country_threat_counts); ?></strong>
            </div>
            <div class="vis-threat-kpi-chip danger" title="<?php esc_attr_e('Durch L1/L2 Kernel-Interlock verworfene Attacken', 'vgt-sentinel'); ?>">
                <span class="vis-chip-label"><?php esc_html_e('Geblockt:', 'vgt-sentinel'); ?></span>
                <strong class="vis-chip-val" id="visThreatBlockedCount"><?php echo (int)$threat_blocked_count; ?></strong>
            </div>

            <!-- Viewport Zoom & Stream Playback Controls -->
            <div class="vis-map-controls" role="toolbar" aria-label="<?php esc_attr_e('Kartensteuerung', 'vgt-sentinel'); ?>">
                <button type="button" class="vis-map-btn" id="visMapZoomIn" title="<?php esc_attr_e('Kartenansicht vergrößern', 'vgt-sentinel'); ?>" aria-label="<?php esc_attr_e('Zoom In', 'vgt-sentinel'); ?>">+</button>
                <button type="button" class="vis-map-btn" id="visMapZoomOut" title="<?php esc_attr_e('Kartenansicht verkleinern', 'vgt-sentinel'); ?>" aria-label="<?php esc_attr_e('Zoom Out', 'vgt-sentinel'); ?>">−</button>
                <button type="button" class="vis-map-btn" id="visMapZoomReset" title="<?php esc_attr_e('Ansicht zentrieren', 'vgt-sentinel'); ?>" aria-label="<?php esc_attr_e('Reset', 'vgt-sentinel'); ?>">⟲</button>
                <button type="button" class="vis-map-btn stream-toggle" id="visMapStreamToggle" title="<?php esc_attr_e('Echtzeit-Stream anhalten/fortsetzen', 'vgt-sentinel'); ?>" aria-label="<?php esc_attr_e('Stream Toggle', 'vgt-sentinel'); ?>">
                    <span class="vis-pause-icon" id="visStreamBtnIcon">❚❚</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Map Stage Body -->
    <div class="vis-threat-map-stage" id="visThreatMapStage">
        <!-- SVG Canvas Wrapper -->
        <div class="vis-threat-svg-viewport" id="visThreatSvgViewport">
            <?php
            // Buffer SVG map template and inject dynamic arcs, markers and origin hub
            ob_start();
            include VIS_PATH . 'includes/dashboard/views/overview/world-map.svg.php';
            $map_svg = ob_get_clean();

            // 1. Build Trajectory Arcs SVG and Cluster Threat Markers
            $arcs_html = '';
            $markers_html = '';
            $origin_x = $host_point['x'] ?? 471.49;
            $origin_y = $host_point['y'] ?? 269.36;

            $clusters = [];

            foreach ($threat_map_events as $idx => $ev) {
                $x1 = (float)$ev['x'];
                $y1 = (float)$ev['y'];
                $x2 = (float)$origin_x;
                $y2 = (float)$origin_y;

                // Group threats sharing the same point into cluster
                $cluster_key = round($x1, 1) . '_' . round($y1, 1);
                if (!isset($clusters[$cluster_key])) {
                    $clusters[$cluster_key] = [
                        'x'            => $x1,
                        'y'            => $y1,
                        'country'      => $ev['country'],
                        'country_name' => $ev['country_name'],
                        'asn'          => $ev['asn'],
                        'as_name'      => $ev['as_name'],
                        'events'       => [],
                    ];
                }
                $clusters[$cluster_key]['events'][] = $ev;

                $dx = $x2 - $x1;
                $dy = $y2 - $y1;
                $dist = sqrt($dx * $dx + $dy * $dy);
                if ($dist < 1.0) continue;

                $mx = ($x1 + $x2) / 2.0;
                $my = ($y1 + $y2) / 2.0;
                $nx = -$dy / $dist;
                $ny = $dx / $dist;

                // Elevate arc upward (polar direction)
                $curvature = min(75.0, max(25.0, $dist * 0.16));
                if ($ny > 0) {
                    $nx = -$nx;
                    $ny = -$ny;
                }
                $cx = round($mx + ($nx * $curvature), 1);
                $cy = round($my + ($ny * $curvature) - 10.0, 1);
                $arc_d = "M {$x1},{$y1} Q {$cx},{$cy} {$x2},{$y2}";

                $is_blocked = ($ev['severity'] === 'blocked');
                $is_suspicious = ($ev['severity'] === 'suspicious');

                $stroke_grad = $is_blocked ? 'url(#visArcGradBlocked)' : ($is_suspicious ? 'url(#visArcGradTracked)' : 'rgba(0, 243, 255, 0.45)');
                $core_color  = $is_blocked ? '#ff3366' : ($is_suspicious ? '#ffaa00' : '#00f3ff');
                $glow_filter = $is_blocked ? 'url(#visGlowRose)' : 'url(#visGlowCyan)';
                $duration    = max(1.8, min(3.8, 1.4 + ($dist / 220.0)));
                $anim_delay  = round(fmod($idx * 0.28, 2.5), 2);

                // Trajectory Path and animated photon packet
                $arcs_html .= sprintf(
                    '<g class="vis-arc-item" data-ip="%s" data-severity="%s">
                        <path class="vis-threat-arc %s" d="%s" stroke="%s" fill="none" />
                        <circle class="vis-threat-projectile" r="2.2" fill="%s" filter="%s">
                            <animateMotion path="%s" dur="%.2fs" begin="%.2fs" repeatCount="indefinite" />
                        </circle>
                    </g>',
                    esc_attr($ev['ip']),
                    esc_attr($ev['severity']),
                    esc_attr($is_blocked ? 'is-blocked' : ($is_suspicious ? 'is-suspicious' : 'is-tracked')),
                    esc_attr($arc_d),
                    esc_attr($stroke_grad),
                    esc_attr($core_color),
                    esc_attr($glow_filter),
                    esc_attr($arc_d),
                    $duration,
                    $anim_delay
                );
            }

            // Build Cluster Markers: eliminates multiple overlapping nodes and tooltip flicker
            foreach ($clusters as $c) {
                $count = count($c['events']);
                $has_blocked = false;
                $has_suspicious = false;
                foreach ($c['events'] as $c_ev) {
                    if ($c_ev['severity'] === 'blocked') $has_blocked = true;
                    elseif ($c_ev['severity'] === 'suspicious') $has_suspicious = true;
                }
                $severity = $has_blocked ? 'blocked' : ($has_suspicious ? 'suspicious' : 'tracked');
                $core_color  = ($severity === 'blocked') ? '#ff3366' : (($severity === 'suspicious') ? '#ffaa00' : '#00f3ff');
                $glow_filter = ($severity === 'blocked') ? 'url(#visGlowRose)' : 'url(#visGlowCyan)';
                $cluster_json = wp_json_encode($c['events'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                $cluster_ips = implode(',', array_column($c['events'], 'ip'));

                if ($count > 1) {
                    // Merged Cluster Marker: shows threat count badge and eliminates collision
                    $markers_html .= sprintf(
                        '<g class="vis-threat-marker is-cluster %s" data-count="%d" data-coords="%.1f,%.1f" data-ips="%s" data-ip="%s" data-country="%s" data-country-name="%s" data-asn="%s" data-as-name="%s" data-severity="%s" data-cluster="%s" transform="translate(%.2f, %.2f)">
                            <circle class="vis-sonar-pulse" r="13" fill="%s" opacity="0.3"></circle>
                            <circle class="vis-cluster-ring-outer" r="8.5" fill="rgba(6, 14, 26, 0.9)" stroke="%s" stroke-width="1.4"></circle>
                            <circle class="vis-marker-core" r="4.2" fill="%s" filter="%s"></circle>
                            <text class="vis-cluster-badge-text" x="0" y="2.8" text-anchor="middle">%d</text>
                        </g>',
                        esc_attr($severity === 'blocked' ? 'is-blocked' : ($severity === 'suspicious' ? 'is-suspicious' : 'is-tracked')),
                        $count,
                        $c['x'],
                        $c['y'],
                        esc_attr($cluster_ips),
                        esc_attr($c['events'][0]['ip']),
                        esc_attr($c['country']),
                        esc_attr($c['country_name']),
                        esc_attr($c['asn']),
                        esc_attr($c['as_name']),
                        esc_attr($severity),
                        esc_attr($cluster_json),
                        $c['x'],
                        $c['y'],
                        esc_attr($core_color),
                        esc_attr($core_color),
                        esc_attr($core_color),
                        esc_attr($glow_filter),
                        $count
                    );
                } else {
                    $ev = $c['events'][0];
                    $markers_html .= sprintf(
                        '<g class="vis-threat-marker %s" data-count="1" data-coords="%.1f,%.1f" data-ips="%s" data-ip="%s" data-country="%s" data-country-name="%s" data-asn="%s" data-as-name="%s" data-path="%s" data-module="%s" data-action="%s" data-severity="%s" data-cluster="%s" transform="translate(%.2f, %.2f)">
                            <circle class="vis-sonar-pulse" r="9" fill="%s" opacity="0.3"></circle>
                            <circle class="vis-marker-ring" r="5" fill="none" stroke="%s" stroke-width="1.2"></circle>
                            <circle class="vis-marker-core" r="3.2" fill="%s" filter="%s"></circle>
                        </g>',
                        esc_attr($severity === 'blocked' ? 'is-blocked' : ($severity === 'suspicious' ? 'is-suspicious' : 'is-tracked')),
                        $c['x'],
                        $c['y'],
                        esc_attr($ev['ip']),
                        esc_attr($ev['ip']),
                        esc_attr($ev['country']),
                        esc_attr($ev['country_name']),
                        esc_attr($ev['asn']),
                        esc_attr($ev['as_name']),
                        esc_attr($ev['path']),
                        esc_attr($ev['module']),
                        esc_attr($ev['action']),
                        esc_attr($ev['severity']),
                        esc_attr($cluster_json),
                        $c['x'],
                        $c['y'],
                        esc_attr($core_color),
                        esc_attr($core_color),
                        esc_attr($core_color),
                        esc_attr($glow_filter)
                    );
                }
            }

            // 2. Build Sovereign Host Hub Marker
            $origin_html = sprintf(
                '<g class="vis-origin-marker" transform="translate(%.2f, %.2f)">
                    <circle class="vis-origin-glow" r="28" fill="url(#visOriginGlow)"></circle>
                    <circle class="vis-origin-ping-outer" r="14" fill="none" stroke="#00f3ff" stroke-width="1.2" opacity="0.8"></circle>
                    <circle class="vis-origin-ring" r="8" fill="rgba(0, 243, 255, 0.15)" stroke="#00ffaa" stroke-width="1.5"></circle>
                    <circle class="vis-origin-core" r="4.5" fill="#00ffaa" filter="url(#visGlowCyan)"></circle>
                    <text class="vis-origin-label" x="0" y="-16" text-anchor="middle">%s</text>
                </g>',
                $origin_x,
                $origin_y,
                esc_html__('SOVEREIGN HOST HUB', 'vgt-sentinel')
            );

            // Inject into SVG placeholders
            $map_svg = str_replace(
                '<g id="visThreatMapArcs" class="vis-map-arcs-group"></g>',
                '<g id="visThreatMapArcs" class="vis-map-arcs-group">' . $arcs_html . '</g>',
                $map_svg
            );
            $map_svg = str_replace(
                '<g id="visThreatMapMarkers" class="vis-map-markers-group"></g>',
                '<g id="visThreatMapMarkers" class="vis-map-markers-group">' . $markers_html . '</g>',
                $map_svg
            );
            $map_svg = str_replace(
                '<g id="visThreatMapOrigin" class="vis-map-origin-group"></g>',
                '<g id="visThreatMapOrigin" class="vis-map-origin-group">' . $origin_html . '</g>',
                $map_svg
            );

            // Output enhanced SVG
            echo $map_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>

        <?php if (empty($threat_map_events)): ?>
            <!-- Floating Clean State Indicator when 0 threats exist in database -->
            <div class="vis-threat-clean-indicator" id="visThreatCleanIndicator">
                <div class="vis-clean-badge-halo">
                    <span class="vis-radar-clean-pulse"></span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div class="vis-clean-copy">
                    <strong><?php esc_html_e('LUFTRAUM SICHER · 0 BEDROHUNGEN', 'vgt-sentinel'); ?></strong>
                    <span><?php esc_html_e('Keine Angriffsvektoren oder Sperren im Erfassungszeitraum · Host-Hub nominal', 'vgt-sentinel'); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Floating Interactive Tooltip (Supports Single & Cluster Aggregation) -->
        <div class="vis-threat-map-tooltip" id="visThreatMapTooltip" style="display: none;" role="tooltip">
            <div class="vis-tooltip-header">
                <span class="vis-tt-flag" id="visTtFlag">🌐</span>
                <strong class="vis-tt-ip" id="visTtIp">0.0.0.0</strong>
                <span class="vis-tt-status-badge" id="visTtBadge">BLOCKED</span>
            </div>
            <div class="vis-tooltip-body" id="visTtBody">
                <div class="vis-tt-row" id="visTtRowCountry">
                    <span><?php esc_html_e('Land & Region:', 'vgt-sentinel'); ?></span>
                    <strong id="visTtCountry">-</strong>
                </div>
                <div class="vis-tt-row" id="visTtRowAsn">
                    <span><?php esc_html_e('ASN & Carrier:', 'vgt-sentinel'); ?></span>
                    <strong id="visTtAsn">-</strong>
                </div>
                <div class="vis-tt-row" id="visTtRowPath">
                    <span><?php esc_html_e('Zielpfad (URI):', 'vgt-sentinel'); ?></span>
                    <strong class="vis-tt-path" id="visTtPath">-</strong>
                </div>
                <div class="vis-tt-row" id="visTtRowModule">
                    <span><?php esc_html_e('Modul & Intervention:', 'vgt-sentinel'); ?></span>
                    <strong id="visTtModule">-</strong>
                </div>
            </div>
            <div class="vis-tooltip-cluster-body" id="visTtClusterBody" style="display: none;"></div>
        </div>

        <!-- Map Quick Legend -->
        <div class="vis-threat-map-legend">
            <div class="vis-legend-item">
                <span class="vis-legend-dot blocked"></span>
                <span><?php esc_html_e('Kritisch / Geblockt (L1-L3)', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vis-legend-item">
                <span class="vis-legend-dot suspicious"></span>
                <span><?php esc_html_e('Abtastung / Tarpit (Warnung)', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vis-legend-item">
                <span class="vis-legend-dot tracked"></span>
                <span><?php esc_html_e('Erfasst / Monitored', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vis-legend-item">
                <span class="vis-legend-dot origin"></span>
                <span><?php esc_html_e('Souveräner Server-Hub', 'vgt-sentinel'); ?></span>
            </div>
        </div>
    </div>

    <!-- Live Real-Time Intercept Stream Table -->
    <div class="vis-threat-stream-section">
        <div class="vis-threat-stream-header">
            <div class="vis-stream-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="vgt-icon"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                <h4><?php esc_html_e('ECHTZEIT-TELEMETRIE & ANOMALIE-STREAM', 'vgt-sentinel'); ?></h4>
                <span class="vis-stream-counter">(<span id="visStreamCounter"><?php echo (int)count($threat_map_events); ?></span> <?php esc_html_e('Ereignisse geladen', 'vgt-sentinel'); ?>)</span>
            </div>
            <div class="vis-stream-filter-pills" role="tablist">
                <button type="button" class="vis-filter-pill active" data-filter="all"><?php esc_html_e('Alle Vektoren', 'vgt-sentinel'); ?></button>
                <button type="button" class="vis-filter-pill" data-filter="blocked"><?php esc_html_e('Nur Geblockt', 'vgt-sentinel'); ?></button>
                <button type="button" class="vis-filter-pill" data-filter="suspicious"><?php esc_html_e('Nur Abtastungen', 'vgt-sentinel'); ?></button>
            </div>
        </div>

        <div class="vis-threat-table-wrapper">
            <table class="vis-threat-stream-table" id="visThreatStreamTable">
                <thead>
                    <tr>
                        <th style="width: 105px;"><?php esc_html_e('ZEIT', 'vgt-sentinel'); ?></th>
                        <th style="width: 175px;"><?php esc_html_e('QUELL-IP & GEO', 'vgt-sentinel'); ?></th>
                        <th><?php esc_html_e('ZIELPFAD (TARGET URI)', 'vgt-sentinel'); ?></th>
                        <th style="width: 170px;"><?php esc_html_e('ERKENNUNGSMODUL', 'vgt-sentinel'); ?></th>
                        <th style="width: 165px;"><?php esc_html_e('AKTION', 'vgt-sentinel'); ?></th>
                        <th style="width: 230px;"><?php esc_html_e('ASN & ORGANISATION (LOKAL)', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody id="visThreatStreamBody">
                    <?php if (empty($threat_map_events)): ?>
                        <tr class="vis-stream-empty">
                            <td colspan="6" style="text-align: center; padding: 36px 20px; color: #94a3b8;">
                                <div style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                                    <div style="width:42px; height:42px; border-radius:50%; background:rgba(16, 185, 129, 0.12); border:1px solid rgba(16, 185, 129, 0.3); display:flex; align-items:center; justify-content:center;">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="filter: drop-shadow(0 0 6px rgba(16, 185, 129, 0.4));"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                    </div>
                                    <strong style="color:#f1f5f9; font-size:13px; letter-spacing:0.5px;"><?php esc_html_e('KEINE AKTIVEN BEDROHUNGEN PROTOKOLLIERT', 'vgt-sentinel'); ?></strong>
                                    <span style="font-size:12px; color:#64748b; max-width:440px; line-height:1.5;"><?php esc_html_e('Aktuell liegen keine unbewältigten Angriffe oder gesperrten Vektoren in der Datenbank vor. Reale Zugriffsverletzungen werden hier sofort visualisiert.', 'vgt-sentinel'); ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($threat_map_events as $ev): 
                            $is_bl = ($ev['severity'] === 'blocked');
                            $is_su = ($ev['severity'] === 'suspicious');
                            $badge_class = $is_bl ? 'vis-badge-danger' : ($is_su ? 'vis-badge-warning' : 'vis-badge-info');
                        ?>
                            <tr class="vis-stream-row <?php echo esc_attr($ev['severity']); ?>" 
                                data-ip="<?php echo esc_attr($ev['ip']); ?>"
                                data-severity="<?php echo esc_attr($ev['severity']); ?>">
                                <td class="vis-col-time">
                                    <span class="vis-time-pulse"></span>
                                    <span class="vis-time-txt"><?php echo esc_html($ev['time']); ?></span>
                                </td>
                                <td class="vis-col-ip">
                                    <div class="vis-ip-cell">
                                        <span class="vis-country-flag" title="<?php echo esc_attr($ev['country_name']); ?>"><?php echo esc_html($ev['country']); ?></span>
                                        <code class="vis-ip-code"><?php echo esc_html($ev['ip']); ?></code>
                                    </div>
                                </td>
                                <td class="vis-col-path">
                                    <code class="vis-path-code" title="<?php echo esc_attr($ev['path']); ?>"><?php echo esc_html($ev['path']); ?></code>
                                </td>
                                <td class="vis-col-module">
                                    <span class="vis-module-chip"><?php echo esc_html($ev['module']); ?></span>
                                </td>
                                <td class="vis-col-action">
                                    <span class="vis-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($ev['action']); ?></span>
                                </td>
                                <td class="vis-col-asn">
                                    <div class="vis-asn-cell" title="<?php echo esc_attr($ev['asn'] . ' · ' . $ev['as_name']); ?>">
                                        <strong class="vis-asn-num"><?php echo esc_html($ev['asn']); ?></strong>
                                        <span class="vis-as-name"><?php echo esc_html($ev['as_name']); ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Data Provenance & Sovereign Cartography Attestation -->
        <div class="vis-threat-provenance-bar" style="margin-top: 14px; padding: 10px 14px; border-radius: 6px; background: rgba(5, 14, 28, 0.6); border: 1px solid rgba(0, 243, 255, 0.15); display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: #94a3b8; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #00f3ff; box-shadow: 0 0 6px #00f3ff;"></span>
                <span><strong><?php esc_html_e('Karten- & ASN-Herkunft:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Weltkarte: jsVectorMap world-merc (MIT) · ASN-Daten: iplocate/ip-address-databases (CC BY-SA 4.0)', 'vgt-sentinel'); ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; font-family: ui-monospace, monospace; font-size: 10px;">
                <span title="<?php esc_attr_e('Keine externen API-Aufrufe, keine DNS-Leaks, DSGVO-konform', 'vgt-sentinel'); ?>" style="color: #10b981;">● <?php esc_html_e('0 CLOUD LEAKS · ZERO EXTERNAL APIS', 'vgt-sentinel'); ?></span>
                <span style="color: #64748b;">|</span>
                <span style="color: #94a3b8;"><?php esc_html_e('Version: 2025.Q1 BGP Baseline', 'vgt-sentinel'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Safe Telemetry JSON Payload for Vanilla JS Live Engine -->
<script type="application/json" id="visThreatMapJson">
<?php echo wp_json_encode([
    'origin'    => $host_origin,
    'originPt'  => $host_point,
    'events'    => $threat_map_events,
    'countries' => $country_threat_counts,
    'blocked'   => $threat_blocked_count,
    'i18n'      => [
        'threats_bundled'        => esc_html__('BEDROHUNGEN GEBÜNDELT', 'vgt-sentinel'),
        'vectors'                => esc_html__('VEKTOREN', 'vgt-sentinel'),
        'attacked_paths_modules' => esc_html__('ANGEGRIFFENE PFADE & MODULE', 'vgt-sentinel'),
        'no_active_threats'      => esc_html__('Keine aktiven Drohungen', 'vgt-sentinel'),
        'vectors_captured'       => esc_html__('Angriffsvektoren erfasst', 'vgt-sentinel'),
        'global_perimeter'       => esc_html__('Global Perimeter Monitoring', 'vgt-sentinel'),
        'sovereign_geo_matrix'   => esc_html__('Sovereign Geo Matrix', 'vgt-sentinel'),
        'events_loaded'          => esc_html__('Ereignisse geladen', 'vgt-sentinel'),
        'stream_paused'          => esc_html__('Echtzeit-Telemetrie angehalten', 'vgt-sentinel'),
        'stream_active'          => esc_html__('ECHTZEIT-TELEMETRIE AKTIV', 'vgt-sentinel'),
        'airspace_secure'        => esc_html__('LUFTRAUM SICHER', 'vgt-sentinel'),
        'threats'                => esc_html__('THREATS', 'vgt-sentinel'),
        'secure'                 => esc_html__('SECURE', 'vgt-sentinel'),
        'iso_alpha2'             => esc_html__('ISO Alpha-2:', 'vgt-sentinel'),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
</script>
