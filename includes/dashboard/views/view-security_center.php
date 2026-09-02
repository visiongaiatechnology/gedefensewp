<?php
// STATUS: PLATIN
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$security_section = isset($_GET['security_section']) && is_string($_GET['security_section'])
    ? sanitize_key($_GET['security_section'])
    : 'assurance';
if (!in_array($security_section, ['assurance', 'system', 'logs'], true)) {
    $security_section = 'assurance';
}
$security_sections = [
    'assurance' => __('Sicherheitsanalyse', 'vgt-sentinel'),
    'system' => __('Systemstatus', 'vgt-sentinel'),
    'logs' => __('System-Protokolle', 'vgt-sentinel'),
];
?>
<nav class="vsc-subnav" aria-label="<?php echo esc_attr__('Bereiche der Sicherheitszentrale', 'vgt-sentinel'); ?>">
    <?php foreach ($security_sections as $section_key => $section_label): ?>
        <a class="vsc-subnav-link<?php echo $security_section === $section_key ? ' is-active' : ''; ?>"
           href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=security_center&security_section=' . $section_key)); ?>"
           <?php echo $security_section === $section_key ? 'aria-current="page"' : ''; ?>><?php echo esc_html($section_label); ?></a>
    <?php endforeach; ?>
</nav>
<?php
if ($security_section === 'system') {
    require __DIR__ . '/view-systatus.php';
    return;
}
if ($security_section === 'logs') {
    require __DIR__ . '/view-logs.php';
    return;
}

$engine = VIS_PATH . 'includes/core/class-vis-security-center.php';
if (!class_exists('VIS_Security_Center') && is_readable($engine)) require_once $engine;
$snapshot = class_exists('VIS_Security_Center') ? VIS_Security_Center::snapshot(false) : [
    'score' => 0, 'status' => 'attention', 'summary' => ['passed' => 0, 'warnings' => 0, 'failed' => 1, 'modules' => 0],
    'checks' => [], 'modules' => [], 'boundaries' => [], 'generatedAt' => gmdate('c'), 'durationMs' => 0,
];
$json = wp_json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

$vsc_i18n = [
    'pass' => __('BESTANDEN', 'vgt-sentinel'),
    'warn' => __('WARNUNG', 'vgt-sentinel'),
    'fail' => __('FEHLER', 'vgt-sentinel'),
    'hardened' => __('GEHÄRTET', 'vgt-sentinel'),
    'guarded' => __('GESCHÜTZT', 'vgt-sentinel'),
    'attention' => __('AUFMERKSAMKEIT', 'vgt-sentinel'),
    'initializing' => __('INITIALISIERUNG', 'vgt-sentinel'),
    'loaded' => __('GELADEN', 'vgt-sentinel'),
    'ready' => __('BEREIT', 'vgt-sentinel'),
    'off' => __('INAKTIV', 'vgt-sentinel'),
    'enforced' => __('DURCHGESETZT', 'vgt-sentinel'),
    'mapped' => __('ZUGEORDNET', 'vgt-sentinel'),
    'closed' => __('GESCHLOSSEN', 'vgt-sentinel'),
    'healthy' => __('GESUND', 'vgt-sentinel'),
    'failed' => __('FEHLGESCHLAGEN', 'vgt-sentinel'),
    'degraded' => __('BEEINTRÄCHTIGT', 'vgt-sentinel'),
    'incomplete' => __('UNVOLLSTÄNDIG', 'vgt-sentinel'),
    'disabled' => __('DEAKTIVIERT', 'vgt-sentinel'),
    'experimental' => __('EXPERIMENTELL', 'vgt-sentinel'),
    'source_unavailable' => __('Quelle nicht verfügbar', 'vgt-sentinel'),
    'titan_control' => __('TITAN KONTROLLE', 'vgt-sentinel'),
    'last_run' => __('Zuletzt ausgeführt: %s · %s ms', 'vgt-sentinel'),
    'term_initial_rejected' => __('Initialer Snapshot abgelehnt.', 'vgt-sentinel'),
    'term_executing' => __('Tiefen-Architekturprüfung wird ausgeführt…', 'vgt-sentinel'),
    'term_complete' => __('Tiefenprüfung abgeschlossen: %d bestanden, %d fehlgeschlagen.', 'vgt-sentinel'),
    'term_failed_safe' => __('Selbsttest sicher beendet. Es wurde kein Sicherheitsstatus verändert.', 'vgt-sentinel'),
    'score_aria' => __('Sicherheits-Score %d von 100', 'vgt-sentinel'),
];
?>
<section class="vsc-shell" id="vis-security-center" aria-labelledby="vsc-title">
    <div class="vsc-hero">
        <div class="vsc-hero-copy">
            <span class="vsc-eyebrow"><span class="vsc-live-dot" aria-hidden="true"></span><?php esc_html_e('SENTINEL SICHERHEITSEBENE', 'vgt-sentinel'); ?></span>
            <h2 id="vsc-title"><?php esc_html_e('Architektur-Sicherheitszentrale', 'vgt-sentinel'); ?></h2>
            <p><?php esc_html_e('Verifiziert Trust-Boundaries, Laufzeit-Invarianten, Modulrechte und portable Schutzmechanismen direkt innerhalb der Suite.', 'vgt-sentinel'); ?></p>
            <div class="vsc-actions">
                <button type="button" class="vsc-button vsc-button-primary" id="vsc-run-test">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    <span><?php esc_html_e('Deep Self-Test ausführen', 'vgt-sentinel'); ?></span>
                </button>
                <span class="vsc-last-run" id="vsc-last-run"></span>
            </div>
        </div>
        <div class="vsc-score-panel">
            <div class="vsc-score-ring" id="vsc-score-ring" role="img" aria-label="<?php echo esc_attr__('Sicherheits-Score', 'vgt-sentinel'); ?>">
                <div><strong id="vsc-score">0</strong><span>/100</span></div>
            </div>
            <div class="vsc-score-meta">
                <span id="vsc-posture" class="vsc-posture"><?php esc_html_e('INITIALISIERUNG', 'vgt-sentinel'); ?></span>
                <small><?php esc_html_e('Gewichteter Sicherheits-Score', 'vgt-sentinel'); ?></small>
            </div>
        </div>
    </div>

    <div class="vsc-metrics" aria-label="<?php echo esc_attr__('Sicherheits-Zusammenfassung', 'vgt-sentinel'); ?>">
        <article><span><?php esc_html_e('BESTANDEN', 'vgt-sentinel'); ?></span><strong id="vsc-pass">0</strong><small><?php esc_html_e('Invarianten bestätigt', 'vgt-sentinel'); ?></small></article>
        <article><span><?php esc_html_e('WARNUNG', 'vgt-sentinel'); ?></span><strong id="vsc-warn">0</strong><small><?php esc_html_e('Portabilitätsgrenzen', 'vgt-sentinel'); ?></small></article>
        <article><span><?php esc_html_e('FEHLER', 'vgt-sentinel'); ?></span><strong id="vsc-fail">0</strong><small><?php esc_html_e('Handlung erforderlich', 'vgt-sentinel'); ?></small></article>
        <article><span><?php esc_html_e('MODULE', 'vgt-sentinel'); ?></span><strong id="vsc-modules">0</strong><small><?php esc_html_e('Rechteprofile erfasst', 'vgt-sentinel'); ?></small></article>
    </div>

    <div class="vsc-grid vsc-grid-main">
        <article class="vsc-panel">
            <header><div><span class="vsc-kicker"><?php esc_html_e('01 / PRÜFUNG', 'vgt-sentinel'); ?></span><h3><?php esc_html_e('Integritätsprüfungen', 'vgt-sentinel'); ?></h3></div><span class="vsc-panel-count" id="vsc-check-count">0</span></header>
            <div class="vsc-check-list" id="vsc-checks" aria-live="polite"></div>
        </article>
        <article class="vsc-panel">
            <header><div><span class="vsc-kicker"><?php esc_html_e('02 / GRENZEN', 'vgt-sentinel'); ?></span><h3><?php esc_html_e('Vertrauensarchitektur', 'vgt-sentinel'); ?></h3></div></header>
            <div class="vsc-boundaries" id="vsc-boundaries"></div>
        </article>
    </div>

    <article class="vsc-panel vsc-module-panel">
        <header>
            <div><span class="vsc-kicker"><?php esc_html_e('03 / BERECHTIGUNGEN', 'vgt-sentinel'); ?></span><h3><?php esc_html_e('Modulrechte-Matrix', 'vgt-sentinel'); ?></h3></div>
            <div class="vsc-legend"><span><i class="is-loaded"></i><?php esc_html_e('GELADEN', 'vgt-sentinel'); ?></span><span><i class="is-ready"></i><?php esc_html_e('BEREIT', 'vgt-sentinel'); ?></span><span><i class="is-off"></i><?php esc_html_e('INAKTIV', 'vgt-sentinel'); ?></span></div>
        </header>
        <div class="vsc-module-grid" id="vsc-module-grid"></div>
    </article>

    <article class="vsc-panel vsc-module-panel">
        <header><div><span class="vsc-kicker"><?php esc_html_e('04 / TITAN', 'vgt-sentinel'); ?></span><h3><?php esc_html_e('Anwendungs-Isolation & Integrität', 'vgt-sentinel'); ?></h3></div></header>
        <div class="vsc-module-grid" id="vsc-titan-health"></div>
    </article>

    <div class="vsc-terminal" aria-live="polite">
        <span class="vsc-terminal-prompt">sentinel@assurance:~$</span>
        <span id="vsc-terminal-text"><?php esc_html_e('Snapshot geladen. Tiefenüberprüfung bereit.', 'vgt-sentinel'); ?></span>
        <span class="vsc-caret" aria-hidden="true"></span>
    </div>
    <script type="application/json" id="vsc-snapshot"><?php echo esc_html($json); ?></script>
    <script type="application/json" id="vsc-i18n"><?php echo wp_json_encode($vsc_i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
</section>
