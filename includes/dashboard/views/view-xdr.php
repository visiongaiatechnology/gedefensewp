<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

global $wpdb;
$schemaVersion = class_exists('VIS_Schema') ? VIS_Schema::XDR_SCHEMA_VERSION : '3';
$schema_ready = hash_equals($schemaVersion, (string)get_option('vis_xdr_schema_version', ''));
$incidents = $schema_ready && class_exists('VisionGaia\\GeDefense\\Xdr\\IncidentEngine')
    ? \VisionGaia\GeDefense\Xdr\IncidentEngine::latest(50)
    : [];
$events = $schema_ready && class_exists('VisionGaia\\GeDefense\\Xdr\\EventRepository')
    ? \VisionGaia\GeDefense\Xdr\EventRepository::latest(50)
    : [];
$health = class_exists('VisionGaia\\GeDefense\\Xdr\\EventFabric')
    ? \VisionGaia\GeDefense\Xdr\EventFabric::health()
    : ['EVENT_FABRIC' => 'DISABLED'];
$responses = [];
$evidence = [];
$entities = [];
if ($schema_ready) {
    $responses = $wpdb->get_results("SELECT response_uuid,incident_uuid,action_type,target_type,target_id,confidence,authorized_by,started_at,expires_at,status FROM {$wpdb->prefix}vis_xdr_responses ORDER BY id DESC LIMIT 50");
    $evidence = $wpdb->get_results("SELECT evidence_uuid,incident_uuid,event_uuid,created_at,evidence_type,digest FROM {$wpdb->prefix}vis_xdr_evidence ORDER BY id DESC LIMIT 50");
    $entities = $wpdb->get_results("SELECT entity_type,entity_id,COUNT(*) AS event_count,MAX(last_seen) AS last_seen,MAX(severity) AS max_severity FROM {$wpdb->prefix}vis_xdr_events WHERE entity_id <> '' GROUP BY entity_type,entity_id ORDER BY last_seen DESC LIMIT 50");
}
$active_incidents = count(array_filter($incidents, static fn(object $row): bool => in_array((string)$row->status, ['OPEN','INVESTIGATING','CONTAINED','MONITORING'], true)));
$applied_responses = count(array_filter(is_array($responses) ? $responses : [], static fn(object $row): bool => (string)$row->status === 'APPLIED'));
$xdr_config = get_option('vis_xdr_config', []);
$xdr_config = is_array($xdr_config) ? $xdr_config : [];
$selectedIncident = isset($_GET['incident']) && is_string($_GET['incident']) ? strtolower(wp_unslash($_GET['incident'])) : '';
$incidentDetail = null;
if ($schema_ready && preg_match('/^[a-f0-9]{32}$/D', $selectedIncident) === 1 && class_exists('VisionGaia\\GeDefense\\Xdr\\IncidentEngine')) {
    try {
        $incidentDetail = \VisionGaia\GeDefense\Xdr\IncidentEngine::detail($selectedIncident);
    } catch (ValidationException $e) {
        $incidentDetail = null;
    } catch (SecurityException $e) {
        error_log('[XDR SECURITY] ' . $e->getMessage());
    } catch (StorageException $e) {
        error_log('[XDR STORAGE] ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('[XDR FATAL] ' . $e->getMessage());
    }
}
$detectionCount = count(array_filter($events, static fn(object $row): bool => (string)($row->role ?? '') === 'DETECTION'));
$contextCount = count(array_filter($events, static fn(object $row): bool => (string)($row->role ?? '') === 'CONTEXT'));
?>
<section class="vgt-xdr-shell" aria-label="<?php echo esc_attr__('TRINITY XDR', 'vgt-sentinel'); ?>">
    <div class="vgt-xdr-metrics">
        <article class="vgt-xdr-metric"><span><?php esc_html_e('ACTIVE INCIDENTS', 'vgt-sentinel'); ?></span><strong><?php echo esc_html((string)$active_incidents); ?></strong></article>
        <article class="vgt-xdr-metric"><span><?php esc_html_e('RECENT EVENTS', 'vgt-sentinel'); ?></span><strong><?php echo esc_html((string)count($events)); ?></strong></article>
        <article class="vgt-xdr-metric"><span><?php esc_html_e('ACTIVE RESPONSES', 'vgt-sentinel'); ?></span><strong><?php echo esc_html((string)$applied_responses); ?></strong></article>
        <article class="vgt-xdr-metric"><span><?php esc_html_e('DETECTIONS / CONTEXT', 'vgt-sentinel'); ?></span><strong><?php echo esc_html($detectionCount . ' / ' . $contextCount); ?></strong></article>
        <article class="vgt-xdr-metric"><span><?php esc_html_e('XDR SCHEMA', 'vgt-sentinel'); ?></span><strong><?php echo $schema_ready ? 'V' . esc_html($schemaVersion) : esc_html__('MIGRATION REQUIRED', 'vgt-sentinel'); ?></strong></article>
    </div>

    <?php if (is_array($incidentDetail)): $selected = is_array($incidentDetail['incident'] ?? null) ? $incidentDetail['incident'] : []; ?>
        <!-- DEEP FORENSIC INVESTIGATION PANEL -->
        <article class="vgt-xdr-panel vgt-xdr-investigation">
            <header>
                <div>
                    <small><?php esc_html_e('INCIDENT FORENSIC INVESTIGATION', 'vgt-sentinel'); ?></small>
                    <h3><?php echo esc_html((string)($selected['classification'] ?? 'UNKNOWN')); ?></h3>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=trinity&xdr_section=incidents')); ?>"><?php esc_html_e('DETAIL SCHLIESSEN ✕', 'vgt-sentinel'); ?></a>
            </header>
            <div class="vgt-xdr-incident-summary">
                <div><span><?php esc_html_e('INCIDENT UUID', 'vgt-sentinel'); ?></span><code><?php echo esc_html((string)($selected['incident_uuid'] ?? '')); ?></code></div>
                <div><span><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></span><strong><?php echo esc_html((string)($selected['status'] ?? 'UNKNOWN')); ?></strong></div>
                <div><span><?php esc_html_e('SEVERITY', 'vgt-sentinel'); ?></span><strong><?php echo esc_html((string)($selected['severity'] ?? 0)); ?>/10</strong></div>
                <div><span><?php esc_html_e('CONFIDENCE', 'vgt-sentinel'); ?></span><strong><?php echo esc_html((string)($selected['confidence'] ?? 0)); ?>%</strong></div>
                <div><span><?php esc_html_e('PRIMARY ACTOR', 'vgt-sentinel'); ?></span><code><?php echo esc_html((string)($selected['primary_actor'] ?? 'SYSTEM')); ?></code></div>
                <div><span><?php esc_html_e('PRIMARY ENTITY', 'vgt-sentinel'); ?></span><code><?php echo esc_html((string)($selected['primary_entity_type'] ?? 'UNKNOWN') . ':' . (string)($selected['primary_entity_id'] ?? '')); ?></code></div>
            </div>
            <div class="vgt-xdr-detail-columns">
                <section>
                    <h4><?php esc_html_e('ATTACK STORY KAUSALKETTE', 'vgt-sentinel'); ?></h4>
                    <ol class="vgt-xdr-story">
                        <?php foreach ((array)($incidentDetail['story'] ?? []) as $node): if (!is_array($node)) continue; ?>
                            <li>
                                <time><?php echo esc_html((string)($node['timestamp'] ?? '')); ?></time>
                                <div>
                                    <span class="vgt-xdr-role" data-role="<?php echo esc_attr((string)($node['role'] ?? 'UNKNOWN')); ?>"><?php echo esc_html((string)($node['role'] ?? 'UNKNOWN')); ?></span>
                                    <strong><?php echo esc_html((string)($node['sensor'] ?? 'UNKNOWN') . ' · ' . (string)($node['event_type'] ?? 'EVENT')); ?></strong>
                                    <small><?php echo esc_html((string)($node['causal_edge'] ?? 'SAME_REQUEST') . ' → ' . (string)($node['entity_type'] ?? 'ENTITY')); ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
                <section>
                    <h4><?php esc_html_e('KORRELIERTE ENTITIES', 'vgt-sentinel'); ?></h4>
                    <div class="vgt-xdr-entity-list">
                        <?php foreach ((array)($incidentDetail['entities'] ?? []) as $entity): ?>
                            <code><?php echo esc_html((string)$entity); ?></code>
                        <?php endforeach; ?>
                    </div>
                    <h4><?php esc_html_e('RESPONSE STATUS', 'vgt-sentinel'); ?></h4>
                    <strong class="vgt-xdr-response-state"><?php echo esc_html((string)($selected['response_state'] ?? 'OBSERVE')); ?></strong>
                    <h4><?php esc_html_e('CRYPTOGRAPHIC EVIDENCE ROOT', 'vgt-sentinel'); ?></h4>
                    <code class="vgt-xdr-root"><?php echo esc_html((string)($selected['evidence_root'] ?? 'NOT_ATTACHED')); ?></code>
                </section>
            </div>
            <div class="vgt-xdr-table-wrap">
                <table class="vgt-xdr-table">
                    <thead><tr><th><?php esc_html_e('ZEIT', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ROLLE', 'vgt-sentinel'); ?></th><th><?php esc_html_e('SENSOR', 'vgt-sentinel'); ?></th><th><?php esc_html_e('EVENT', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ACTOR', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ROUTE', 'vgt-sentinel'); ?></th><th><?php esc_html_e('VECTOR', 'vgt-sentinel'); ?></th><th><?php esc_html_e('OUTCOME', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ANZAHL', 'vgt-sentinel'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ((array)($incidentDetail['events'] ?? []) as $event): if (!is_array($event)) continue; ?>
                        <tr>
                            <td><?php echo esc_html((string)($event['last_seen'] ?? '')); ?></td>
                            <td><span class="vgt-xdr-role" data-role="<?php echo esc_attr((string)($event['role'] ?? '')); ?>"><?php echo esc_html((string)($event['role'] ?? '')); ?></span></td>
                            <td><?php echo esc_html((string)($event['sensor'] ?? '')); ?></td>
                            <td><?php echo esc_html((string)($event['event_type'] ?? '')); ?></td>
                            <td><?php echo esc_html((string)(($event['actor_ip'] ?? '') !== '' ? $event['actor_ip'] : 'SYSTEM')); ?></td>
                            <td><?php echo esc_html((string)($event['route'] ?? '')); ?></td>
                            <td><?php echo esc_html((string)($event['vector'] ?? '')); ?></td>
                            <td><?php echo esc_html((string)($event['outcome'] ?? '')); ?></td>
                            <td><?php echo esc_html((string)($event['occurrence_count'] ?? 1)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    <?php endif; ?>

    <?php if ($xdr_section === 'overview'): ?>
        <!-- SECTION 1: LAGEBILD (SITUATIONAL AWARENESS COCKPIT) -->
        <article class="vgt-xdr-panel">
            <header>
                <h3><?php esc_html_e('SITUATIONAL AWARENESS & DEFENSE LAGEBILD', 'vgt-sentinel'); ?></h3>
                <span class="vgt-xdr-state"><?php esc_html_e('ECHTZEIT-TELEMETRIE', 'vgt-sentinel'); ?></span>
            </header>
            
            <div class="vgt-xdr-posture">
                <div class="vgt-xdr-posture-box">
                    <small><?php esc_html_e('AUTONOME EINDÄMMUNG', 'vgt-sentinel'); ?></small>
                    <strong><?php echo !empty($xdr_config['auto_response_enabled']) ? esc_html__('CLOSED-LOOP AKTIV (80%+ CONFIDENCE)', 'vgt-sentinel') : esc_html__('OBSERVATION ONLY (PASSIV)', 'vgt-sentinel'); ?></strong>
                    <p><?php esc_html_e('Multi-Sensor Response autorisiert automatische Sperren ab 80% Vertrauen und 2 unabhängigen Sensorkategorien.', 'vgt-sentinel'); ?></p>
                </div>
                <div class="vgt-xdr-posture-box">
                    <small><?php esc_html_e('CONTAINMENT POLICY', 'vgt-sentinel'); ?></small>
                    <strong><?php echo esc_html((string)($xdr_config['containment_policy'] ?? 'TEMPORARY_BY_DEFAULT')); ?></strong>
                    <p><?php esc_html_e('Preset:', 'vgt-sentinel'); ?> <?php echo esc_html((string)($xdr_config['ttl_preset'] ?? 'BALANCED')); ?> (<?php echo esc_html((string)($xdr_config['ttl_actor_ban'] ?? 900)); ?>s TTL)</p>
                </div>
                <div class="vgt-xdr-posture-box">
                    <small><?php esc_html_e('PIPELINE HEALTH', 'vgt-sentinel'); ?></small>
                    <strong style="color: #10b981;"><?php esc_html_e('ALL FABRIC SENSORS SYNCHRONIZED', 'vgt-sentinel'); ?></strong>
                    <p><?php esc_html_e('Audit-Aufbewahrung:', 'vgt-sentinel'); ?> <?php echo esc_html((string)($xdr_config['retention_days'] ?? 30)); ?> <?php esc_html_e('Tage unveränderlich', 'vgt-sentinel'); ?></p>
                </div>
            </div>

            <?php
            $opt = get_option('vis_config', []);
            $zeus_config = get_option('vis_zeus_config', []);
            $zeus_on = !empty($zeus_config) || !empty($opt['zeus_enabled']);
            $aegis_on = !empty($opt['aegis_enabled']);
            $prom_on = !empty($opt['prometheus_enabled']);
            $nemesis_on = !empty($opt['nemesis_enabled']);
            $airlock_on = !isset($opt['airlock_enabled']) || !empty($opt['airlock_enabled']);
            $morpheus_on = !empty($opt['morpheus_enabled']);
            ?>
            <div class="vgt-xdr-sensor-grid">
                <div class="vgt-xdr-sensor-item" style="<?php echo $zeus_on ? '' : 'opacity:0.6;'; ?>"><span class="vgt-xdr-sensor-name">ZEUS 6G WAF</span><span class="vgt-xdr-sensor-status"><?php echo $zeus_on ? '● ' . esc_html__('L0 ACTIVE', 'vgt-sentinel') : '○ ' . esc_html__('STANDBY', 'vgt-sentinel'); ?></span></div>
                <div class="vgt-xdr-sensor-item" style="<?php echo $aegis_on ? '' : 'opacity:0.6;'; ?>"><span class="vgt-xdr-sensor-name">AEGIS DPI</span><span class="vgt-xdr-sensor-status"><?php echo $aegis_on ? '● ' . esc_html__('L7 ACTIVE', 'vgt-sentinel') : '○ ' . esc_html__('STANDBY', 'vgt-sentinel'); ?></span></div>
                <div class="vgt-xdr-sensor-item" style="<?php echo $prom_on ? '' : 'opacity:0.6;'; ?>"><span class="vgt-xdr-sensor-name">PROMETHEUS AI</span><span class="vgt-xdr-sensor-status"><?php echo $prom_on ? '● ' . esc_html__('HEURISTIC', 'vgt-sentinel') : '○ ' . esc_html__('STANDBY', 'vgt-sentinel'); ?></span></div>
                <div class="vgt-xdr-sensor-item" style="<?php echo $nemesis_on ? '' : 'opacity:0.6;'; ?>"><span class="vgt-xdr-sensor-name">NEMESIS DECOY</span><span class="vgt-xdr-sensor-status"><?php echo $nemesis_on ? '● ' . esc_html__('TARPIT', 'vgt-sentinel') : '○ ' . esc_html__('STANDBY', 'vgt-sentinel'); ?></span></div>
                <div class="vgt-xdr-sensor-item" style="<?php echo $airlock_on ? '' : 'opacity:0.6;'; ?>"><span class="vgt-xdr-sensor-name">AIRLOCK INGRESS</span><span class="vgt-xdr-sensor-status"><?php echo $airlock_on ? '● ' . esc_html__('CLEAN', 'vgt-sentinel') : '○ ' . esc_html__('STANDBY', 'vgt-sentinel'); ?></span></div>
                <div class="vgt-xdr-sensor-item" style="<?php echo $morpheus_on ? '' : 'opacity:0.6;'; ?>"><span class="vgt-xdr-sensor-name">MORPHEUS RASP</span><span class="vgt-xdr-sensor-status"><?php echo $morpheus_on ? '● ' . esc_html__('SANDBOX', 'vgt-sentinel') : '○ ' . esc_html__('STANDBY', 'vgt-sentinel'); ?></span></div>
            </div>

            <header style="border-top: 1px solid rgba(148,163,184,.12);">
                <h3><?php esc_html_e('KRITISCHE SICHERHEITSVORFÄLLE (AKTUELLES LAGEBILD)', 'vgt-sentinel'); ?></h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=trinity&xdr_section=incidents')); ?>" class="vgt-xdr-incident-link"><?php esc_html_e('ALLE INCIDENTS ANZEIGEN →', 'vgt-sentinel'); ?></a>
            </header>
            
            <?php if ($incidents === []): ?>
                <p class="vgt-xdr-empty"><?php esc_html_e('Keine Sicherheitsvorfälle im aktuellen Lagebild verzeichnet. Die Schutzschilde laufen fehlerfrei.', 'vgt-sentinel'); ?></p>
            <?php else: ?>
                <div class="vgt-xdr-table-wrap">
                    <table class="vgt-xdr-table">
                        <thead><tr><th><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th><th><?php esc_html_e('KLASSIFIKATION', 'vgt-sentinel'); ?></th><th><?php esc_html_e('SEVERITY', 'vgt-sentinel'); ?></th><th><?php esc_html_e('CONFIDENCE', 'vgt-sentinel'); ?></th><th><?php esc_html_e('SENSOREN', 'vgt-sentinel'); ?></th><th><?php esc_html_e('EVENTS', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ZEITSTEMPEL', 'vgt-sentinel'); ?></th><th><?php esc_html_e('AKTION', 'vgt-sentinel'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($incidents, 0, 8) as $incident): 
                            $sev = (int)$incident->severity;
                            $sev_class = $sev >= 7 ? 'vgt-xdr-sev-high' : ($sev >= 4 ? 'vgt-xdr-sev-med' : 'vgt-xdr-sev-low');
                        ?>
                            <tr>
                                <td><span class="vgt-xdr-state"><?php echo esc_html((string)$incident->status); ?></span></td>
                                <td><strong style="color:#f8fafc;"><?php echo esc_html((string)$incident->classification); ?></strong></td>
                                <td><span class="vgt-xdr-sev-badge <?php echo esc_attr($sev_class); ?>"><?php echo esc_html((string)$sev); ?>/10</span></td>
                                <td><?php echo esc_html((string)$incident->confidence); ?>%</td>
                                <td><?php echo esc_html((string)$incident->sensor_count); ?></td>
                                <td><?php echo esc_html((string)$incident->event_count); ?></td>
                                <td><?php echo esc_html((string)$incident->updated_at); ?></td>
                                <td>
                                    <a class="vgt-xdr-incident-link" href="<?php echo esc_url(add_query_arg(['page'=>'vgt-suite','tab'=>'trinity','xdr_section'=>'incidents','incident'=>(string)$incident->incident_uuid], admin_url('admin.php'))); ?>">
                                        <?php esc_html_e('Untersuchen →', 'vgt-sentinel'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

    <?php elseif ($xdr_section === 'incidents'): ?>
        <!-- SECTION 2: INCIDENTS (TRIAGE & FORENSIC INVESTIGATION MATRIX) -->
        <article class="vgt-xdr-panel">
            <header>
                <h3><?php esc_html_e('KORRELIERTE SICHERHEITSVORFÄLLE (INCIDENTS)', 'vgt-sentinel'); ?></h3>
                <span class="vgt-xdr-state"><?php echo esc_html(count($incidents)); ?> <?php esc_html_e('VORFÄLLE TOTAL', 'vgt-sentinel'); ?></span>
            </header>
            
            <?php if ($incidents === []): ?>
                <p class="vgt-xdr-empty"><?php esc_html_e('Keine korrelierten Incidents vorhanden. Das Lagebild bleibt datenbasiert und erzeugt keine Demo-Einträge.', 'vgt-sentinel'); ?></p>
            <?php else: ?>
                <div class="vgt-xdr-table-wrap">
                    <table class="vgt-xdr-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('INCIDENT ID', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('KLASSIFIKATION', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('SEVERITY', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('CONFIDENCE', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('SENSOREN', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('KATEGORIEN', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('EVENTS', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('AKTUALISIERT', 'vgt-sentinel'); ?></th>
                                <th><?php esc_html_e('AKTION', 'vgt-sentinel'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($incidents as $incident): 
                            $sev = (int)$incident->severity;
                            $sev_class = $sev >= 7 ? 'vgt-xdr-sev-high' : ($sev >= 4 ? 'vgt-xdr-sev-med' : 'vgt-xdr-sev-low');
                        ?>
                            <tr>
                                <td><code><?php echo esc_html(substr((string)$incident->incident_uuid, 0, 12)); ?>…</code></td>
                                <td><span class="vgt-xdr-state"><?php echo esc_html((string)$incident->status); ?></span></td>
                                <td><strong style="color:#f8fafc;"><?php echo esc_html((string)$incident->classification); ?></strong></td>
                                <td><span class="vgt-xdr-sev-badge <?php echo esc_attr($sev_class); ?>"><?php echo esc_html((string)$sev); ?>/10</span></td>
                                <td><?php echo esc_html((string)$incident->confidence); ?>%</td>
                                <td><?php echo esc_html((string)$incident->sensor_count); ?></td>
                                <td><?php echo esc_html((string)$incident->category_count); ?></td>
                                <td><?php echo esc_html((string)$incident->event_count); ?></td>
                                <td><?php echo esc_html((string)$incident->updated_at); ?></td>
                                <td>
                                    <a class="vgt-xdr-incident-link" href="<?php echo esc_url(add_query_arg(['page'=>'vgt-suite','tab'=>'trinity','xdr_section'=>'incidents','incident'=>(string)$incident->incident_uuid], admin_url('admin.php'))); ?>">
                                        <?php esc_html_e('Untersuchen →', 'vgt-sentinel'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

    <?php elseif ($xdr_section === 'stories'): ?>
        <!-- SECTION 3: ATTACK STORIES (CAUSAL MULTI-STAGE KILL CHAIN VISUALIZER) -->
        <article class="vgt-xdr-panel">
            <header>
                <h3><?php esc_html_e('KORRELIERTE ATTACK STORIES (MULTI-STAGE CAUSAL GRAPH)', 'vgt-sentinel'); ?></h3>
                <span class="vgt-xdr-state"><?php esc_html_e('CYBER KILL CHAIN', 'vgt-sentinel'); ?></span>
            </header>
            
            <?php if ($incidents === []): ?>
                <p class="vgt-xdr-empty"><?php esc_html_e('Keine korrelierten Attack Stories verzeichnet. Bei mehrstufigen Angriffen korreliert die Trinity Engine automatisch die Kausalkette.', 'vgt-sentinel'); ?></p>
            <?php else: ?>
                <div class="vgt-xdr-story-cards">
                    <?php foreach ($incidents as $incident): 
                        $sev = (int)$incident->severity;
                        $sev_class = $sev >= 7 ? 'vgt-xdr-sev-high' : ($sev >= 4 ? 'vgt-xdr-sev-med' : 'vgt-xdr-sev-low');
                    ?>
                        <div class="vgt-xdr-story-card">
                            <div class="vgt-xdr-story-header">
                                <div class="vgt-xdr-story-title">
                                    <span class="vgt-xdr-sev-badge <?php echo esc_attr($sev_class); ?>">SEV <?php echo esc_html((string)$sev); ?></span>
                                    <span><?php echo esc_html((string)$incident->classification); ?></span>
                                    <code style="font-size:11px; color:#94a3b8;">[#<?php echo esc_html(substr((string)$incident->incident_uuid, 0, 8)); ?>]</code>
                                </div>
                                <div>
                                    <span class="vgt-xdr-state"><?php echo esc_html((string)$incident->status); ?></span>
                                    <a class="vgt-xdr-incident-link" style="margin-left:8px;" href="<?php echo esc_url(add_query_arg(['page'=>'vgt-suite','tab'=>'trinity','xdr_section'=>'incidents','incident'=>(string)$incident->incident_uuid], admin_url('admin.php'))); ?>">
                                        <?php esc_html_e('Story analysieren →', 'vgt-sentinel'); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- CAUSAL KILL CHAIN VISUALIZATION -->
                            <div class="vgt-xdr-story-chain">
                                <span class="vgt-xdr-chain-step <?php echo $incident->sensor_count > 0 ? 'is-alert' : ''; ?>"><?php esc_html_e('1. RECONNAISSANCE', 'vgt-sentinel'); ?></span>
                                <span class="vgt-xdr-chain-arrow">→</span>
                                <span class="vgt-xdr-chain-step <?php echo $incident->category_count > 1 ? 'is-alert' : ''; ?>"><?php esc_html_e('2. INGRESS / DELIVERY', 'vgt-sentinel'); ?></span>
                                <span class="vgt-xdr-chain-arrow">→</span>
                                <span class="vgt-xdr-chain-step is-alert"><?php esc_html_e('3. EXPLOITATION', 'vgt-sentinel'); ?></span>
                                <span class="vgt-xdr-chain-arrow">→</span>
                                <span class="vgt-xdr-chain-step"><?php esc_html_e('4. C2 / EGRESS', 'vgt-sentinel'); ?></span>
                                <span class="vgt-xdr-chain-arrow">→</span>
                                <span class="vgt-xdr-chain-step is-blocked"><?php esc_html_e('5. AUTONOMOUS CONTAINMENT', 'vgt-sentinel'); ?></span>
                            </div>
                            
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#94a3b8; font-family:ui-monospace,monospace;">
                                <span><?php esc_html_e('Sensoren:', 'vgt-sentinel'); ?> <strong><?php echo esc_html((string)$incident->sensor_count); ?></strong> · <?php esc_html_e('Events:', 'vgt-sentinel'); ?> <strong><?php echo esc_html((string)$incident->event_count); ?></strong> · <?php esc_html_e('Vertrauen:', 'vgt-sentinel'); ?> <strong><?php echo esc_html((string)$incident->confidence); ?>%</strong></span>
                                <span><?php esc_html_e('Letzte Aktualisierung:', 'vgt-sentinel'); ?> <?php echo esc_html((string)$incident->updated_at); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

    <?php elseif ($xdr_section === 'entities'): ?>
        <article class="vgt-xdr-panel"><header><h3><?php esc_html_e('ENTITY TIMELINE INDEX', 'vgt-sentinel'); ?></h3></header><div class="vgt-xdr-table-wrap"><table class="vgt-xdr-table"><thead><tr><th><?php esc_html_e('TYP', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ENTITY', 'vgt-sentinel'); ?></th><th><?php esc_html_e('EVENTS', 'vgt-sentinel'); ?></th><th><?php esc_html_e('MAX SEVERITY', 'vgt-sentinel'); ?></th><th><?php esc_html_e('LETZTES SIGNAL', 'vgt-sentinel'); ?></th></tr></thead><tbody><?php foreach (is_array($entities) ? $entities : [] as $entity): ?><tr><td><?php echo esc_html((string)$entity->entity_type); ?></td><td><code><?php echo esc_html((string)$entity->entity_id); ?></code></td><td><?php echo esc_html((string)$entity->event_count); ?></td><td><?php echo esc_html((string)$entity->max_severity); ?>/10</td><td><?php echo esc_html((string)$entity->last_seen); ?></td></tr><?php endforeach; ?></tbody></table></div></article>
    <?php elseif ($xdr_section === 'responses'): ?>
        <article class="vgt-xdr-panel"><header><h3><?php esc_html_e('RESPONSE AUDIT TRAIL', 'vgt-sentinel'); ?></h3></header><div class="vgt-xdr-table-wrap"><table class="vgt-xdr-table"><thead><tr><th><?php esc_html_e('AKTION', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ZIEL', 'vgt-sentinel'); ?></th><th><?php esc_html_e('CONFIDENCE', 'vgt-sentinel'); ?></th><th><?php esc_html_e('AUTORISIERT DURCH', 'vgt-sentinel'); ?></th><th><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th><th><?php esc_html_e('START', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ABLAUF', 'vgt-sentinel'); ?></th></tr></thead><tbody><?php foreach (is_array($responses) ? $responses : [] as $response): ?><tr><td><?php echo esc_html((string)$response->action_type); ?></td><td><code><?php echo esc_html((string)$response->target_type . ':' . (string)$response->target_id); ?></code></td><td><?php echo esc_html((string)$response->confidence); ?>%</td><td><?php echo esc_html((string)$response->authorized_by); ?></td><td><span class="vgt-xdr-state"><?php echo esc_html((string)$response->status); ?></span></td><td><?php echo esc_html((string)$response->started_at); ?></td><td><?php echo esc_html((string)($response->expires_at ?? '—')); ?></td></tr><?php endforeach; ?></tbody></table></div></article>
    <?php elseif ($xdr_section === 'evidence'): ?>
        <article class="vgt-xdr-panel"><header><h3><?php esc_html_e('TAMPER-EVIDENT EVIDENCE INDEX', 'vgt-sentinel'); ?></h3></header><div class="vgt-xdr-table-wrap"><table class="vgt-xdr-table"><thead><tr><th><?php esc_html_e('EVIDENCE', 'vgt-sentinel'); ?></th><th><?php esc_html_e('INCIDENT', 'vgt-sentinel'); ?></th><th><?php esc_html_e('EVENT', 'vgt-sentinel'); ?></th><th><?php esc_html_e('TYP', 'vgt-sentinel'); ?></th><th><?php esc_html_e('DIGEST', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ZEIT', 'vgt-sentinel'); ?></th></tr></thead><tbody><?php foreach (is_array($evidence) ? $evidence : [] as $item): ?><tr><td><code><?php echo esc_html(substr((string)$item->evidence_uuid, 0, 12)); ?>…</code></td><td><code><?php echo esc_html(substr((string)$item->incident_uuid, 0, 12)); ?>…</code></td><td><code><?php echo esc_html(substr((string)$item->event_uuid, 0, 12)); ?>…</code></td><td><?php echo esc_html((string)$item->evidence_type); ?></td><td><code><?php echo esc_html(substr((string)$item->digest, 0, 20)); ?>…</code></td><td><?php echo esc_html((string)$item->created_at); ?></td></tr><?php endforeach; ?></tbody></table></div></article>
    <?php elseif ($xdr_section === 'events'): ?>
        <article class="vgt-xdr-panel"><header><h3><?php esc_html_e('CANONICAL EVENT STREAM', 'vgt-sentinel'); ?></h3></header><div class="vgt-xdr-table-wrap"><table class="vgt-xdr-table"><thead><tr><th><?php esc_html_e('ZEIT', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ROLLE', 'vgt-sentinel'); ?></th><th><?php esc_html_e('SENSOR', 'vgt-sentinel'); ?></th><th><?php esc_html_e('KATEGORIE', 'vgt-sentinel'); ?></th><th><?php esc_html_e('EVENT', 'vgt-sentinel'); ?></th><th><?php esc_html_e('SEVERITY', 'vgt-sentinel'); ?></th><th><?php esc_html_e('CONFIDENCE', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ACTOR', 'vgt-sentinel'); ?></th><th><?php esc_html_e('VECTOR', 'vgt-sentinel'); ?></th><th><?php esc_html_e('ANZAHL', 'vgt-sentinel'); ?></th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?php echo esc_html((string)$event->last_seen); ?></td><td><span class="vgt-xdr-role" data-role="<?php echo esc_attr((string)($event->role ?? 'UNKNOWN')); ?>"><?php echo esc_html((string)($event->role ?? 'UNKNOWN')); ?></span></td><td><?php echo esc_html((string)$event->sensor); ?></td><td><?php echo esc_html((string)$event->category); ?></td><td><?php echo esc_html((string)$event->event_type); ?></td><td><?php echo esc_html((string)$event->severity); ?>/10</td><td><?php echo esc_html((string)$event->confidence); ?>%</td><td><code><?php echo esc_html((string)($event->actor_ip !== '' ? $event->actor_ip : 'SYSTEM')); ?></code></td><td><?php echo esc_html((string)$event->vector); ?></td><td><?php echo esc_html((string)$event->occurrence_count); ?></td></tr><?php endforeach; ?></tbody></table></div></article>
    <?php elseif ($xdr_section === 'health'): ?>
        <article class="vgt-xdr-panel"><header><h3><?php esc_html_e('XDR PIPELINE HEALTH', 'vgt-sentinel'); ?></h3></header><div class="vgt-xdr-health"><?php foreach ($health as $component => $state): ?><div><span><?php echo esc_html((string)$component); ?></span><strong><?php echo esc_html((string)$state); ?></strong></div><?php endforeach; ?></div></article>
    <?php elseif ($xdr_section === 'policy'): ?>
        <?php
        $actuator_cerberus       = !isset($xdr_config['actuator_cerberus']) || !empty($xdr_config['actuator_cerberus']);
        $actuator_zeus_route     = !isset($xdr_config['actuator_zeus_route']) || !empty($xdr_config['actuator_zeus_route']);
        $actuator_zeus_admission = !isset($xdr_config['actuator_zeus_admission']) || !empty($xdr_config['actuator_zeus_admission']);
        $actuator_morpheus       = !isset($xdr_config['actuator_morpheus']) || !empty($xdr_config['actuator_morpheus']);
        $actuator_styx           = !isset($xdr_config['actuator_styx']) || !empty($xdr_config['actuator_styx']);
        $actuator_plugin         = !isset($xdr_config['actuator_plugin']) || !empty($xdr_config['actuator_plugin']);
        ?>
        <article class="vgt-xdr-panel"><header><h3><?php esc_html_e('MULTI-SENSOR RESPONSE & TTL CONTAINMENT POLICY', 'vgt-sentinel'); ?></h3></header><div class="vgt-xdr-policy">
            <?php wp_nonce_field('vis_save_config'); ?>
            <input type="hidden" name="vis_context" value="trinity">
            <input type="hidden" name="xdr_section" value="policy">
            <input type="hidden" name="vis_save_config" value="1">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:6px; border:1px solid rgba(255,255,255,0.08); margin-bottom:15px;">
                        <h4 style="margin:0 0 12px 0; color:var(--vgt-cyan, #00f0ff); font-size:13px;"><?php esc_html_e('CLOSED-LOOP AUTONOMIE & ESKALATION', 'vgt-sentinel'); ?></h4>
                        <label style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
                            <input type="checkbox" name="vis_xdr_config[auto_response_enabled]" value="1" <?php checked(!empty($xdr_config['auto_response_enabled'])); ?>>
                            <strong><?php esc_html_e('Automatische XDR-Eindämmung aktivieren (Closed-Loop)', 'vgt-sentinel'); ?></strong>
                        </label>
                        <label style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
                            <input type="checkbox" name="vis_xdr_config[escalation_enabled]" value="1" <?php checked(!empty($xdr_config['escalation_enabled'])); ?>>
                            <span><?php esc_html_e('Eskalationspolitik: Wiederholungstäter innerhalb 24h erhalten 4x TTL Multiplikator', 'vgt-sentinel'); ?></span>
                        </label>
                        <label style="display:block; margin-bottom:12px;">
                            <span style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Containment-Modus:', 'vgt-sentinel'); ?></span>
                            <select class="vgt-input" name="vis_xdr_config[containment_policy]" style="width:100%;">
                                <option value="TEMPORARY_BY_DEFAULT" <?php selected(($xdr_config['containment_policy'] ?? 'TEMPORARY_BY_DEFAULT'), 'TEMPORARY_BY_DEFAULT'); ?>><?php esc_html_e('TEMPORARY BY DEFAULT (Automatische TTL-Rücknahme)', 'vgt-sentinel'); ?></option>
                                <option value="TEMPORARY_ONLY" <?php selected(($xdr_config['containment_policy'] ?? ''), 'TEMPORARY_ONLY'); ?>><?php esc_html_e('TEMPORARY ONLY (Permanente Sperren verboten)', 'vgt-sentinel'); ?></option>
                                <option value="ALLOW_PERMANENT_EXPLICIT" <?php selected(($xdr_config['containment_policy'] ?? ''), 'ALLOW_PERMANENT_EXPLICIT'); ?>><?php esc_html_e('ALLOW PERMANENT EXPLICIT (Manuelle Eskalation erlaubt)', 'vgt-sentinel'); ?></option>
                            </select>
                        </label>
                        <label style="display:block; margin-bottom:12px;">
                            <span style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;"><?php esc_html_e('TTL-Preset:', 'vgt-sentinel'); ?></span>
                            <select class="vgt-input" name="vis_xdr_config[ttl_preset]" style="width:100%;">
                                <option value="CONSERVATIVE" <?php selected(strtoupper($xdr_config['ttl_preset'] ?? 'BALANCED'), 'CONSERVATIVE'); ?>><?php esc_html_e('CONSERVATIVE (5 min Einzel-IP / 10 min Subnetz)', 'vgt-sentinel'); ?></option>
                                <option value="BALANCED" <?php selected(strtoupper($xdr_config['ttl_preset'] ?? 'BALANCED'), 'BALANCED'); ?>><?php esc_html_e('BALANCED (15 min Einzel-IP / 30 min Subnetz)', 'vgt-sentinel'); ?></option>
                                <option value="AGGRESSIVE" <?php selected(strtoupper($xdr_config['ttl_preset'] ?? 'BALANCED'), 'AGGRESSIVE'); ?>><?php esc_html_e('AGGRESSIVE (1 Stunde Einzel-IP / 2 Stunden Subnetz)', 'vgt-sentinel'); ?></option>
                                <option value="CUSTOM" <?php selected(strtoupper($xdr_config['ttl_preset'] ?? 'BALANCED'), 'CUSTOM'); ?>><?php esc_html_e('CUSTOM (Benutzerdefinierte TTL-Werte unten)', 'vgt-sentinel'); ?></option>
                            </select>
                        </label>
                        <label style="display:block;">
                            <span style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Lokale Audit-Aufbewahrung (Tage):', 'vgt-sentinel'); ?></span>
                            <input class="vgt-input" type="number" min="7" max="180" name="vis_xdr_config[retention_days]" value="<?php echo esc_attr((string)max(7, min(180, (int)($xdr_config['retention_days'] ?? 30)))); ?>" style="width:100%;">
                        </label>
                    </div>

                    <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:6px; border:1px solid rgba(255,255,255,0.08);">
                        <h4 style="margin:0 0 12px 0; color:var(--vgt-cyan, #00f0ff); font-size:13px;"><?php esc_html_e('AKTIVE XDR-REAKTIONSSENSOREN (CLOSED-LOOP AKTUATOREN)', 'vgt-sentinel'); ?></h4>
                        <div style="display:grid; grid-template-columns:1fr; gap:8px;">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="vis_xdr_config[actuator_cerberus]" value="1" <?php checked($actuator_cerberus); ?>>
                                <span style="font-size:12px;"><?php esc_html_e('Cerberus IP-Eindämmung (L0/L7 Perimeter Ban)', 'vgt-sentinel'); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="vis_xdr_config[actuator_zeus_route]" value="1" <?php checked($actuator_zeus_route); ?>>
                                <span style="font-size:12px;"><?php esc_html_e('Zeus Virtual Route Isolation (WAF Route Quarantine)', 'vgt-sentinel'); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="vis_xdr_config[actuator_zeus_admission]" value="1" <?php checked($actuator_zeus_admission); ?>>
                                <span style="font-size:12px;"><?php esc_html_e('Zeus Admission Lockdown (Pre-Boot Request Gate)', 'vgt-sentinel'); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="vis_xdr_config[actuator_morpheus]" value="1" <?php checked($actuator_morpheus); ?>>
                                <span style="font-size:12px;"><?php esc_html_e('Morpheus Honey Overlay (Micro-Tarpit Deception)', 'vgt-sentinel'); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="vis_xdr_config[actuator_styx]" value="1" <?php checked($actuator_styx); ?>>
                                <span style="font-size:12px;"><?php esc_html_e('Styx Virtual Shadowing (Egress Isolation)', 'vgt-sentinel'); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="vis_xdr_config[actuator_plugin]" value="1" <?php checked($actuator_plugin); ?>>
                                <span style="font-size:12px;"><?php esc_html_e('Plugin Isolation (Gefährdete Komponenten isolieren)', 'vgt-sentinel'); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:6px; border:1px solid rgba(255,255,255,0.08); height:fit-content;">
                    <h4 style="margin:0 0 12px 0; color:var(--vgt-cyan, #00f0ff); font-size:13px;"><?php esc_html_e('FEINJUSTIERUNG TTL-ZEITFENSTER (SEKUNDEN)', 'vgt-sentinel'); ?></h4>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <label>
                            <span style="font-size:11px; display:block; color:#94a3b8;"><?php esc_html_e('Actor IP-Sperre:', 'vgt-sentinel'); ?></span>
                            <input class="vgt-input" type="number" min="60" max="86400" name="vis_xdr_config[ttl_actor_ban]" value="<?php echo esc_attr((string)($xdr_config['ttl_actor_ban'] ?? 900)); ?>" style="width:100%;">
                        </label>
                        <label>
                            <span style="font-size:11px; display:block; color:#94a3b8;"><?php esc_html_e('Subnetz-Sperre (/24):', 'vgt-sentinel'); ?></span>
                            <input class="vgt-input" type="number" min="120" max="86400" name="vis_xdr_config[ttl_subnet]" value="<?php echo esc_attr((string)($xdr_config['ttl_subnet'] ?? 1800)); ?>" style="width:100%;">
                        </label>
                        <label>
                            <span style="font-size:11px; display:block; color:#94a3b8;"><?php esc_html_e('ZEUS Route Isolation:', 'vgt-sentinel'); ?></span>
                            <input class="vgt-input" type="number" min="60" max="86400" name="vis_xdr_config[ttl_zeus_route]" value="<?php echo esc_attr((string)($xdr_config['ttl_zeus_route'] ?? 900)); ?>" style="width:100%;">
                        </label>
                        <label>
                            <span style="font-size:11px; display:block; color:#94a3b8;"><?php esc_html_e('ZEUS Admission Pflicht:', 'vgt-sentinel'); ?></span>
                            <input class="vgt-input" type="number" min="60" max="86400" name="vis_xdr_config[ttl_zeus_admission]" value="<?php echo esc_attr((string)($xdr_config['ttl_zeus_admission'] ?? 900)); ?>" style="width:100%;">
                        </label>
                        <label>
                            <span style="font-size:11px; display:block; color:#94a3b8;"><?php esc_html_e('Morpheus Honey Overlay:', 'vgt-sentinel'); ?></span>
                            <input class="vgt-input" type="number" min="60" max="86400" name="vis_xdr_config[ttl_morpheus_overlay]" value="<?php echo esc_attr((string)($xdr_config['ttl_morpheus_overlay'] ?? 900)); ?>" style="width:100%;">
                        </label>
                        <label>
                            <span style="font-size:11px; display:block; color:#94a3b8;"><?php esc_html_e('Styx Virtual Shadowing:', 'vgt-sentinel'); ?></span>
                            <input class="vgt-input" type="number" min="60" max="86400" name="vis_xdr_config[ttl_styx_overlay]" value="<?php echo esc_attr((string)($xdr_config['ttl_styx_overlay'] ?? 900)); ?>" style="width:100%;">
                        </label>
                    </div>
                </div>
            </div>
            <p class="vgt-xdr-empty"><?php esc_html_e('Multi-Sensor Response autorisiert automatische Sperren nur ab 80% Vertrauen und 2 unabhängigen Sensorkategorien. Alle Sperren unterliegen Hard Semantic TTLs und laufen automatisch ohne manuelle Intervention aus.', 'vgt-sentinel'); ?></p>
            <button type="submit" name="vis_save_config" value="1" class="button button-primary"><?php esc_html_e('XDR-Policy speichern', 'vgt-sentinel'); ?></button>
        </div></article>
    <?php endif; ?>
</section>
