<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$titanState = class_exists('VIS_Titan_Policy_Store') ? VIS_Titan_Policy_Store::snapshot() : [];
$titanCandidate = is_array($titanState['candidate'] ?? null) ? $titanState['candidate'] : [];
$titanActive = is_array($titanState['active'] ?? null) ? $titanState['active'] : [];
$titanValidation = is_array($titanCandidate['validation'] ?? null) ? $titanCandidate['validation'] : [];
$titanServer = get_option('vis_titan_server_rule_status', []);
$titanServer = is_array($titanServer) ? $titanServer : [];
$titanRuntime = get_option('vis_titan_runtime_health', []);
$titanRuntime = is_array($titanRuntime) ? $titanRuntime : [];
$titanViolations = get_option('vis_titan_violations', []);
$titanViolations = is_array($titanViolations) ? array_slice(array_values(array_filter($titanViolations, 'is_array')), 0, 12) : [];
$titanLearned = get_option('vis_titan_learned_origins', []);
$titanLearned = is_array($titanLearned) ? array_slice(array_values(array_filter($titanLearned, 'is_array')), 0, 12) : [];
$titanCompiled = is_array($titanActive['compiled'] ?? null) ? $titanActive['compiled'] : (is_array($titanCandidate['compiled'] ?? null) ? $titanCandidate['compiled'] : []);
$titanSurfaces = class_exists('VIS_Titan_Surface_Resolver') ? VIS_Titan_Surface_Resolver::all() : [];
$titanStateLabel = (string)($titanActive['lifecycle'] ?? 'NOT_VALIDATED');
$titanValidationLabel = (string)($titanValidation['state'] ?? 'NOT_VALIDATED');
$titanProfile = (string)($opt['titan_profile'] ?? 'balanced');
$titanWarnings = is_array($titanValidation['warnings'] ?? null) ? $titanValidation['warnings'] : [];
$titanCritical = is_array($titanValidation['critical_failures'] ?? null) ? $titanValidation['critical_failures'] : [];

$titanSelect = static function(string $name, array $choices, string $selectedValue): void {
    echo '<select class="vgt-titan-select" name="vis_config[' . esc_attr($name) . ']">';
    foreach ($choices as $value => $label) {
        echo '<option value="' . esc_attr((string)$value) . '" ' . selected($selectedValue, (string)$value, false) . '>' . esc_html((string)$label) . '</option>';
    }
    echo '</select>';
};

$titanToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="TITAN Application Confinement">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('APPLICATION & BROWSER CONFINEMENT ENGINE', 'vgt-sentinel'); ?></p>
            <h2>TITAN</h2>
            <p><?php esc_html_e('WordPress-Härtung, HTTP-Policy und browsererzwungene Sicherheitsgrenzen – lokal, ohne Telemetrie oder externe Runtime-Abhängigkeiten.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="TITAN Zustände">
            <span><small><?php esc_html_e('KONFIGURIERT', 'vgt-sentinel'); ?></small><strong><?php echo !empty($opt['titan_enabled']) ? esc_html__('JA', 'vgt-sentinel') : esc_html__('NEIN', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('VALIDIERT', 'vgt-sentinel'); ?></small><strong><?php echo esc_html($titanValidationLabel); ?></strong></span>
            <span><small><?php esc_html_e('AKTIVIERT', 'vgt-sentinel'); ?></small><strong><?php echo esc_html($titanStateLabel); ?></strong></span>
            <span><small><?php esc_html_e('GESENDET', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)($titanRuntime['state'] ?? 'UNKNOWN')); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('PROFIL', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(strtoupper($titanProfile)); ?></strong></article>
        <article><small><?php esc_html_e('POLICY VERSION', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)($titanCandidate['version'] ?? 0)); ?></strong></article>
        <article><small><?php esc_html_e('CSP', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(strtoupper((string)($opt['titan_csp_mode'] ?? 'REPORT_ONLY'))); ?></strong></article>
        <article><small><?php esc_html_e('FETCH METADATA', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(strtoupper((string)($opt['titan_fetch_mode'] ?? 'AUDIT'))); ?></strong></article>
        <article><small><?php esc_html_e('SERVER RULES', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)($titanServer['state'] ?? 'NOT_GENERATED')); ?></strong></article>
        <article><small><?php esc_html_e('ENFORCEMENT ELIGIBLE', 'vgt-sentinel'); ?></small><strong><?php echo !empty($titanValidation['enforcement_eligible']) ? esc_html__('YES', 'vgt-sentinel') : esc_html__('NO', 'vgt-sentinel'); ?></strong></article>
    </div>

    <nav class="vgt-titan-nav" aria-label="TITAN Bereiche">
        <?php foreach ([
            'overview'   => __('Overview', 'vgt-sentinel'),
            'policy'     => __('Policy', 'vgt-sentinel'),
            'browser'    => __('Browser', 'vgt-sentinel'),
            'wordpress'  => __('WordPress', 'vgt-sentinel'),
            'sandbox'    => __('Sandbox', 'vgt-sentinel'),
            'validation' => __('Validation', 'vgt-sentinel'),
            'telemetry'  => __('Violations', 'vgt-sentinel'),
            'server'     => __('Server Rules', 'vgt-sentinel')
        ] as $id => $label): ?>
            <a href="#titan-<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <section id="titan-overview" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('CONTROL PLANE', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Policy Profiles', 'vgt-sentinel'); ?></h3>
            </div>
            <?php $titanToggle('titan_enabled', !empty($opt['titan_enabled']), 'TITAN Runtime'); ?>
        </div>
        <div class="vgt-titan-profile-grid">
            <?php foreach ([
                'compatible' => [__('COMPATIBLE', 'vgt-sentinel'), __('LOW BREAKAGE RISK', 'vgt-sentinel')],
                'balanced' => [__('BALANCED', 'vgt-sentinel'), __('MEDIUM BREAKAGE RISK', 'vgt-sentinel')],
                'strict' => [__('STRICT', 'vgt-sentinel'), __('HIGH BREAKAGE RISK', 'vgt-sentinel')],
                'paranoid' => [__('PARANOID', 'vgt-sentinel'), __('VERY HIGH BREAKAGE RISK', 'vgt-sentinel')],
                'custom' => [__('CUSTOM', 'vgt-sentinel'), __('ADMIN DEFINED', 'vgt-sentinel')],
                'experimental_browser_zero_trust' => [__('BROWSER ZERO TRUST', 'vgt-sentinel'), __('EXPERIMENTAL', 'vgt-sentinel')],
            ] as $value => [$label, $risk]): ?>
                <label class="vgt-titan-profile <?php echo $titanProfile === $value ? 'is-selected' : ''; ?>">
                    <input type="radio" name="vis_config[titan_profile]" value="<?php echo esc_attr($value); ?>" <?php checked($titanProfile, $value); ?>>
                    <strong><?php echo esc_html($label); ?></strong><small><?php echo esc_html($risk); ?></small>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="titan-policy" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('EFFECTIVE POLICY', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Surface Matrix', 'vgt-sentinel'); ?></h3>
            </div>
            <code><?php echo esc_html(substr((string)($titanValidation['compiler_hash'] ?? 'NOT_COMPILED'), 0, 20)); ?></code>
        </div>
        <div class="vgt-titan-table-wrap">
            <table class="vgt-titan-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Surface', 'vgt-sentinel'); ?></th>
                        <th>CSP</th>
                        <th>COOP</th>
                        <th>CORP</th>
                        <th>COEP</th>
                        <th>OAC</th>
                        <th>Fetch</th>
                        <th><?php esc_html_e('State', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($titanSurfaces as $surface): 
                    $policy = is_array($titanCompiled[$surface] ?? null) ? $titanCompiled[$surface] : []; 
                    $headers = is_array($policy['headers'] ?? null) ? $policy['headers'] : []; 
                ?>
                    <tr>
                        <th><?php echo esc_html($surface); ?></th>
                        <td><?php echo esc_html(strtoupper((string)($policy['csp_mode'] ?? 'OFF'))); ?></td>
                        <td><?php echo esc_html((string)($headers['Cross-Origin-Opener-Policy'] ?? 'OFF')); ?></td>
                        <td><?php echo esc_html((string)($headers['Cross-Origin-Resource-Policy'] ?? 'OFF')); ?></td>
                        <td><?php echo esc_html((string)($headers['Cross-Origin-Embedder-Policy'] ?? 'OFF')); ?></td>
                        <td><?php echo isset($headers['Origin-Agent-Cluster']) ? 'ON' : 'OFF'; ?></td>
                        <td><?php echo esc_html(strtoupper((string)($policy['fetch_mode'] ?? 'OFF'))); ?></td>
                        <td><?php echo esc_html((string)($policy['validation_state'] ?? 'NOT_VALIDATED')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="titan-browser" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('BROWSER CONFINEMENT', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('HTTP & Browser Policy', 'vgt-sentinel'); ?></h3>
            </div>
        </div>
        <div class="vgt-titan-fields">
            <label><span>CSP Mode</span><?php $titanSelect('titan_csp_mode', ['off'=>'OFF','learning'=>'LEARNING','report_only'=>'REPORT ONLY','enforce'=>'ENFORCE'], (string)($opt['titan_csp_mode'] ?? 'report_only')); ?></label>
            <label><span>Fetch Metadata</span><?php $titanSelect('titan_fetch_mode', ['off'=>'OFF','audit'=>'AUDIT','enforce_sensitive'=>'ENFORCE SENSITIVE','strict'=>'STRICT'], (string)($opt['titan_fetch_mode'] ?? 'audit')); ?></label>
            <label><span>Trusted Types</span><?php $titanSelect('titan_trusted_types_mode', ['off'=>'OFF','report_only'=>'REPORT ONLY','gedefense_admin_only'=>'GEDEFENSE ADMIN','strict_selected_surfaces'=>'SELECTED SURFACES'], (string)($opt['titan_trusted_types_mode'] ?? 'off')); ?></label>
            <label><span>COEP (Experimental)</span><?php $titanSelect('titan_coep_mode', ['off'=>'OFF','require-corp'=>'REQUIRE-CORP','credentialless'=>'CREDENTIALLESS'], (string)($opt['titan_coep_mode'] ?? 'off')); ?></label>
            <label><span>External Header Conflict</span><?php $titanSelect('titan_header_conflict_strategy', ['observe'=>'OBSERVE / PRESERVE EXTERNAL','override_titan_owned'=>'OVERRIDE TITAN-OWNED'], (string)($opt['titan_header_conflict_strategy'] ?? 'observe')); ?></label>
        </div>
        <div class="vgt-titan-toggle-grid">
            <?php $titanToggle('titan_nonce_enabled', !empty($opt['titan_nonce_enabled']), 'Per-response CSP nonce'); ?>
            <?php $titanToggle('titan_learning_enabled', !empty($opt['titan_learning_enabled']), 'Learning Mode'); ?>
            <?php $titanToggle('titan_hsts_enabled', !empty($opt['titan_hsts_enabled']), 'HSTS'); ?>
            <?php $titanToggle('titan_hsts_include_subdomains', !empty($opt['titan_hsts_include_subdomains']), 'HSTS includeSubDomains'); ?>
            <?php $titanToggle('titan_hsts_preload', !empty($opt['titan_hsts_preload']), 'HSTS preload – long-lived'); ?>
        </div>
        <label class="vgt-titan-wide-field"><span><?php esc_html_e('HSTS max-age', 'vgt-sentinel'); ?></span><input type="number" min="300" max="63072000" name="vis_config[titan_hsts_max_age]" value="<?php echo esc_attr((string)($opt['titan_hsts_max_age'] ?? 31536000)); ?>"></label>
        <label class="vgt-titan-wide-field"><span><?php esc_html_e('Permissions allowed for self (comma-separated)', 'vgt-sentinel'); ?></span><input type="text" name="vis_config[titan_permissions_self]" placeholder="fullscreen, payment" value="<?php echo esc_attr((string)($opt['titan_permissions_self'] ?? '')); ?>"></label>
        <div class="vgt-titan-origin-grid">
            <?php foreach (['script'=>'Script origins','style'=>'Style origins','img'=>'Image origins','connect'=>'Connect origins','frame'=>'Frame origins'] as $key => $label): ?>
                <label><span><?php echo esc_html($label); ?></span><textarea name="vis_config[titan_<?php echo esc_attr($key); ?>_origins]" rows="3" placeholder="https://example.com"><?php echo esc_textarea((string)($opt['titan_' . $key . '_origins'] ?? '')); ?></textarea></label>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="titan-wordpress" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('WORDPRESS KERNEL', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Attack Surface', 'vgt-sentinel'); ?></h3>
            </div>
        </div>
        <div class="vgt-titan-toggle-grid">
            <?php $titanToggle('titan_server_spoof', !empty($opt['titan_server_spoof']), 'Server fingerprint mimicry'); ?>
            <?php $titanToggle('titan_anti_enum', !empty($opt['titan_anti_enum']), 'User enumeration guard'); ?>
            <?php $titanToggle('titan_hide_version', !empty($opt['titan_hide_version']), 'Hide WordPress version'); ?>
            <?php $titanToggle('titan_remove_asset_versions', !empty($opt['titan_remove_asset_versions']), 'Remove asset ?ver= (cache impact)'); ?>
            <?php $titanToggle('titan_remove_discovery_links', !empty($opt['titan_remove_discovery_links']), 'Remove discovery links'); ?>
            <?php $titanToggle('titan_application_lockdown', !empty($opt['titan_application_lockdown']), 'DISALLOW_FILE_MODS (blocks updates)'); ?>
            <?php $titanToggle('titan_login_gatekeeper', !empty($opt['titan_login_gatekeeper']), 'Short-lived login gate'); ?>
            <?php $titanToggle('titan_includes_guard', !empty($opt['titan_includes_guard']), 'wp-includes execution guard'); ?>
            <?php $titanToggle('titan_cleanup_emojis', !empty($opt['titan_cleanup_emojis']), 'Disable emoji assets'); ?>
            <?php $titanToggle('titan_cleanup_embeds', !empty($opt['titan_cleanup_embeds']), 'Disable oEmbed discovery'); ?>
        </div>
        <div class="vgt-titan-actions">
            <button type="button" data-titan-gate-link><?php esc_html_e('GENERATE 5-MINUTE LOGIN LINK', 'vgt-sentinel'); ?></button>
            <code id="vgt-titan-gate-output" aria-live="polite"><?php esc_html_e('No token exposed.', 'vgt-sentinel'); ?></code>
        </div>
        <div class="vgt-titan-fields">
            <label><span>XML-RPC Policy</span><?php $titanSelect('titan_xmlrpc_mode', ['disabled'=>'DISABLED','pingback_disabled'=>'PINGBACK DISABLED','auth_only'=>'AUTH ONLY','honeypot'=>'HONEYPOT','custom'=>'CUSTOM'], (string)($opt['titan_xmlrpc_mode'] ?? 'auth_only')); ?></label>
            <label><span>Application Passwords</span><?php $titanSelect('titan_application_passwords_mode', ['allow'=>'ALLOW','audit'=>'AUDIT','disable'=>'DISABLE'], (string)($opt['titan_application_passwords_mode'] ?? 'allow')); ?></label>
            <label><span>CMS Mimicry</span><?php $titanSelect('titan_camouflage_mode', ['none'=>'OFF','laravel'=>'LARAVEL','drupal'=>'DRUPAL','joomla'=>'JOOMLA'], (string)($opt['titan_camouflage_mode'] ?? 'none')); ?></label>
        </div>
    </section>

    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('ADMIN-ONLY LOCAL TEST', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Browser Validation', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php esc_html_e('OBSERVATION', 'vgt-sentinel'); ?></span>
        </div>
        <div class="vgt-titan-validation-grid">
            <div><span>Trusted Types API</span><strong id="vgt-titan-check-trusted-types">UNKNOWN</strong></div>
            <div><span>Cross-Origin Isolation</span><strong id="vgt-titan-check-coop">UNKNOWN</strong></div>
            <div><span>Permissions Policy API</span><strong id="vgt-titan-check-permissions">UNKNOWN</strong></div>
            <div><span>Origin Agent Cluster</span><strong id="vgt-titan-check-oac">UNKNOWN</strong></div>
        </div>
        <p class="vgt-titan-caveat"><?php esc_html_e('SUPPORTED beweist Browser-Unterstützung. OBSERVED beweist nur den in dieser GeDefense-Adminantwort messbaren Zustand – nicht globale Durchsetzung.', 'vgt-sentinel'); ?></p>
    </section>

    <section id="titan-sandbox" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('AIRLOCK COOPERATION', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Active Content Sandbox', 'vgt-sentinel'); ?></h3>
            </div>
            <strong>CSP SANDBOX FALLBACK</strong>
        </div>
        <p><?php esc_html_e('Registrierte HTML-, SVG- und XML-Uploads werden über kurzlebige, signierte Preview-URLs mit Script-freiem CSP-Sandbox ausgeliefert. Eine dedizierte Origin wird nur akzeptiert, wenn sie HTTPS, vom Anwendungshost getrennt und nicht durch eine gemeinsame WordPress-Cookie-Domain privilegiert ist.', 'vgt-sentinel'); ?></p>
        <div class="vgt-titan-fields">
            <label><span><?php esc_html_e('Dedicated Sandbox Origin', 'vgt-sentinel'); ?></span><input type="url" name="vis_config[titan_sandbox_origin]" placeholder="https://sandbox.example.com" value="<?php echo esc_attr((string)($opt['titan_sandbox_origin'] ?? '')); ?>"></label>
            <label><span><?php esc_html_e('Direct Active Content', 'vgt-sentinel'); ?></span><?php $titanSelect('titan_active_content_direct_access', ['attachment'=>'FORCE ATTACHMENT','block'=>'BLOCK','allow'=>'ALLOW (RISK)'], (string)($opt['titan_active_content_direct_access'] ?? 'attachment')); ?></label>
        </div>
        <?php $titanToggle('titan_sandbox_origin_verified', !empty($opt['titan_sandbox_origin_verified']), 'DNS/TLS origin routing verified by administrator'); ?>
        <div class="vgt-titan-records">
            <?php $sandboxRecords = class_exists('VIS_Titan_Sandbox') ? array_slice(VIS_Titan_Sandbox::records(), 0, 8) : []; ?>
            <?php if ($sandboxRecords === []): ?><p class="vgt-titan-empty"><?php esc_html_e('Keine aktiven Inhalte registriert.', 'vgt-sentinel'); ?></p><?php endif; ?>
            <?php foreach ($sandboxRecords as $record): ?><div><code><?php echo esc_html(substr((string)($record['id'] ?? ''), 0, 12)); ?></code><span><?php echo esc_html((string)($record['mime'] ?? 'unknown')); ?></span><small><?php echo esc_html((string)($record['isolation'] ?? 'UNKNOWN')); ?></small></div><?php endforeach; ?>
        </div>
    </section>

    <section id="titan-validation" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('AUTOMATED ASSURANCE', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Policy Validation', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge" data-state="<?php echo esc_attr($titanValidationLabel); ?>"><?php echo esc_html($titanValidationLabel); ?></span>
        </div>
        <div class="vgt-titan-validation-grid">
            <?php $probeState = is_array($titanValidation['surface_probes'] ?? null) ? $titanValidation['surface_probes'] : []; ?>
            <div><span>Static &amp; Schema</span><strong><?php echo in_array('STATIC_SCHEMA', (array)($titanValidation['checks_passed'] ?? []), true) ? 'PASS' : 'NOT VALIDATED'; ?></strong></div>
            <div><span>Compiler</span><strong><?php echo !empty($titanValidation['compiler_hash']) ? 'PASS' : 'NOT VALIDATED'; ?></strong></div>
            <div><span>Header Probe</span><strong><?php echo $probeState === [] ? 'INCOMPLETE' : 'OBSERVED'; ?></strong></div>
            <div><span>Compatibility</span><strong><?php echo $titanWarnings === [] ? 'PASS' : 'PASS WITH WARNINGS'; ?></strong></div>
            <div><span>Report-only Observation</span><strong><?php echo esc_html((string)($titanValidation['report_only_observation']['state'] ?? 'INCOMPLETE')); ?></strong></div>
            <div><span>Server Rules</span><strong><?php echo esc_html((string)($titanValidation['server_rule_validation']['state'] ?? 'INCOMPLETE')); ?></strong></div>
            <div><span>Last Known Good</span><strong><?php echo !empty($titanState['last_known_good']) ? 'AVAILABLE' : 'NONE'; ?></strong></div>
            <div><span>Rollback</span><strong><?php echo esc_html((string)($titanValidation['rollback_result'] ?? 'NOT REQUIRED')); ?></strong></div>
        </div>
        <?php if ($titanCritical !== [] || $titanWarnings !== []): ?><div class="vgt-titan-findings">
            <?php foreach (array_slice($titanCritical, 0, 10) as $finding): ?><p class="is-critical"><strong>CRITICAL</strong> <?php echo esc_html((string)$finding); ?></p><?php endforeach; ?>
            <?php foreach (array_slice($titanWarnings, 0, 10) as $finding): ?><p><strong><?php echo esc_html((string)($finding['level'] ?? 'WARNING')); ?></strong> <?php echo esc_html((string)($finding['message'] ?? 'Compatibility warning')); ?></p><?php endforeach; ?>
        </div><?php endif; ?>
        <div class="vgt-titan-actions">
            <button type="button" data-titan-operation="generate_candidate" <?php disabled($titanLearned === []); ?>><?php esc_html_e('LEARNING → CANDIDATE', 'vgt-sentinel'); ?></button>
            <button type="button" data-titan-operation="validate"><?php esc_html_e('VALIDATE CANDIDATE', 'vgt-sentinel'); ?></button>
            <button type="button" data-titan-operation="activate_report_only"><?php esc_html_e('ENTER REPORT-ONLY', 'vgt-sentinel'); ?></button>
            <button type="button" data-titan-operation="activate_enforce" <?php disabled(empty($titanValidation['enforcement_eligible'])); ?>><?php esc_html_e('ENFORCE', 'vgt-sentinel'); ?></button>
            <button type="button" data-titan-operation="rollback" class="is-danger" <?php disabled(empty($titanState['last_known_good'])); ?>><?php esc_html_e('ROLLBACK', 'vgt-sentinel'); ?></button>
            <span id="vgt-titan-action-status" role="status" aria-live="polite"></span>
        </div>
    </section>

    <section id="titan-telemetry" class="vgt-titan-panel vgt-titan-columns">
        <div>
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('LOCAL TELEMETRY', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Aggregated Violations', 'vgt-sentinel'); ?></h3>
                </div>
                <b><?php echo count($titanViolations); ?></b>
            </div>
            <?php if ($titanViolations === []): ?><p class="vgt-titan-empty"><?php esc_html_e('Keine CSP-Verletzungen gespeichert.', 'vgt-sentinel'); ?></p><?php endif; ?>
            <?php foreach ($titanViolations as $record): ?><div class="vgt-titan-event"><code><?php echo esc_html((string)($record['directive'] ?? 'unknown')); ?></code><span><?php echo esc_html((string)($record['blocked_origin'] ?? 'redacted')); ?></span><b><?php echo esc_html((string)($record['count'] ?? 0)); ?>×</b></div><?php endforeach; ?>
        </div>
        <div>
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('LEARNING MODE', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Observed Origins', 'vgt-sentinel'); ?></h3>
                </div>
                <b><?php echo count($titanLearned); ?></b>
            </div>
            <?php if ($titanLearned === []): ?><p class="vgt-titan-empty"><?php esc_html_e('Noch keine externen Origins beobachtet.', 'vgt-sentinel'); ?></p><?php endif; ?>
            <?php foreach ($titanLearned as $record): ?><div class="vgt-titan-event"><code><?php echo esc_html(strtoupper((string)($record['type'] ?? 'LEGACY')) . ' / ' . (string)($record['surface'] ?? 'UNKNOWN')); ?></code><span><?php echo esc_html((string)($record['origin'] ?? 'redacted')); ?></span><b><?php echo esc_html((string)($record['count'] ?? 0)); ?>×</b></div><?php endforeach; ?>
        </div>
    </section>

    <section id="titan-server" class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('SERVER CONTROL PLANE', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Apache / Nginx Rules', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php echo esc_html((string)($titanServer['state'] ?? 'NOT GENERATED')); ?></span>
        </div>
        <p><?php esc_html_e('Nginx-Regeln werden nur in einen verifizierten privaten Vault außerhalb des Document Root geschrieben. Ist kein solcher Pfad verfügbar, bleiben sie ausschließlich als authentifizierter Export verfügbar. Ein absoluter Include-Pfad wird nicht in der Datei gespeichert.', 'vgt-sentinel'); ?></p>
        <pre><?php echo esc_html(class_exists('VIS_Titan_Server_Rules') ? VIS_Titan_Server_Rules::nginxRules(is_array($opt) ? $opt : []) : 'TITAN runtime unavailable.'); ?></pre>
        <a class="vgt-titan-download" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=vis_titan_download_nginx'), 'vis_titan_download_nginx')); ?>"><?php esc_html_e('VALIDATED NGINX EXPORT', 'vgt-sentinel'); ?></a>
        <p class="vgt-titan-caveat"><?php esc_html_e('GENERATED oder EXPORT ONLY bedeutet nicht VERIFIED ACTIVE. Die Aktivität muss am Webserver separat geprüft werden.', 'vgt-sentinel'); ?></p>
    </section>

    <div class="vgt-titan-actions" style="margin-top: 24px;">
        <button type="submit"><?php esc_html_e('TITAN EINSTELLUNGEN SPEICHERN & POLICY KOMPILIEREN', 'vgt-sentinel'); ?></button>
    </div>
</section>
