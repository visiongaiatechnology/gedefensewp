<?php
// STATUS: PLATIN
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$engine = VIS_PATH . 'includes/core/class-vis-security-center.php';
if (!class_exists('VIS_Security_Center') && is_readable($engine)) require_once $engine;
$snapshot = class_exists('VIS_Security_Center') ? VIS_Security_Center::snapshot(false) : [
    'score' => 0, 'status' => 'attention', 'summary' => ['passed' => 0, 'warnings' => 0, 'failed' => 1, 'modules' => 0],
    'checks' => [], 'modules' => [], 'boundaries' => [], 'generatedAt' => gmdate('c'), 'durationMs' => 0,
];
$json = wp_json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>
<section class="vsc-shell" id="vis-security-center" aria-labelledby="vsc-title">
    <div class="vsc-hero">
        <div class="vsc-hero-copy">
            <span class="vsc-eyebrow"><span class="vsc-live-dot" aria-hidden="true"></span><?php esc_html_e('SENTINEL ASSURANCE PLANE', 'vgt-sentinel'); ?></span>
            <h2 id="vsc-title"><?php esc_html_e('Architecture Security Center', 'vgt-sentinel'); ?></h2>
            <p><?php esc_html_e('Verifiziert Trust-Boundaries, Laufzeit-Invarianten, Modulrechte und portable Schutzmechanismen direkt innerhalb der Suite.', 'vgt-sentinel'); ?></p>
            <div class="vsc-actions">
                <button type="button" class="vsc-button vsc-button-primary" id="vsc-run-test">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    <span><?php esc_html_e('Deep self-test ausführen', 'vgt-sentinel'); ?></span>
                </button>
                <span class="vsc-last-run" id="vsc-last-run"></span>
            </div>
        </div>
        <div class="vsc-score-panel">
            <div class="vsc-score-ring" id="vsc-score-ring" role="img" aria-label="Security score">
                <div><strong id="vsc-score">0</strong><span>/100</span></div>
            </div>
            <div class="vsc-score-meta">
                <span id="vsc-posture" class="vsc-posture">INITIALIZING</span>
                <small><?php esc_html_e('Weighted assurance score', 'vgt-sentinel'); ?></small>
            </div>
        </div>
    </div>

    <div class="vsc-metrics" aria-label="Security summary">
        <article><span>PASS</span><strong id="vsc-pass">0</strong><small><?php esc_html_e('Invarianten bestätigt', 'vgt-sentinel'); ?></small></article>
        <article><span>WARN</span><strong id="vsc-warn">0</strong><small><?php esc_html_e('Portabilitätsgrenzen', 'vgt-sentinel'); ?></small></article>
        <article><span>FAIL</span><strong id="vsc-fail">0</strong><small><?php esc_html_e('Handlung erforderlich', 'vgt-sentinel'); ?></small></article>
        <article><span>MODULES</span><strong id="vsc-modules">0</strong><small><?php esc_html_e('Rechteprofile erfasst', 'vgt-sentinel'); ?></small></article>
    </div>

    <div class="vsc-grid vsc-grid-main">
        <article class="vsc-panel">
            <header><div><span class="vsc-kicker">01 / ASSURANCE</span><h3><?php esc_html_e('Integrity checks', 'vgt-sentinel'); ?></h3></div><span class="vsc-panel-count" id="vsc-check-count">0</span></header>
            <div class="vsc-check-list" id="vsc-checks" aria-live="polite"></div>
        </article>
        <article class="vsc-panel">
            <header><div><span class="vsc-kicker">02 / BOUNDARIES</span><h3><?php esc_html_e('Trust architecture', 'vgt-sentinel'); ?></h3></div></header>
            <div class="vsc-boundaries" id="vsc-boundaries"></div>
        </article>
    </div>

    <article class="vsc-panel vsc-module-panel">
        <header>
            <div><span class="vsc-kicker">03 / CAPABILITIES</span><h3><?php esc_html_e('Module rights matrix', 'vgt-sentinel'); ?></h3></div>
            <div class="vsc-legend"><span><i class="is-loaded"></i>LOADED</span><span><i class="is-ready"></i>READY</span><span><i class="is-off"></i>OFF</span></div>
        </header>
        <div class="vsc-module-grid" id="vsc-module-grid"></div>
    </article>

    <div class="vsc-terminal" aria-live="polite">
        <span class="vsc-terminal-prompt">sentinel@assurance:~$</span>
        <span id="vsc-terminal-text"><?php esc_html_e('Snapshot loaded. Deep verification ready.', 'vgt-sentinel'); ?></span>
        <span class="vsc-caret" aria-hidden="true"></span>
    </div>
    <script type="application/json" id="vsc-snapshot"><?php echo $json; ?></script>
</section>
