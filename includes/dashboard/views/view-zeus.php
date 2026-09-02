<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$zeus_src_dir = dirname(__DIR__, 2) . '/modules/zeus/src/';
require_once $zeus_src_dir . 'class-zeus-vault-resolver.php';
require_once $zeus_src_dir . 'class-zeus-policy-manager.php';
require_once $zeus_src_dir . 'class-zeus-config-repository.php';
require_once $zeus_src_dir . 'class-zeus-contracts.php';
require_once $zeus_src_dir . 'class-zeus-xdr-bridge.php';
require_once $zeus_src_dir . 'class-zeus-admission.php';
require_once $zeus_src_dir . 'class-zeus-blackbox.php';
require_once $zeus_src_dir . 'class-zeus-budget.php';
require_once $zeus_src_dir . 'class-zeus-benchmark.php';
require_once $zeus_src_dir . 'class-zeus-edge.php';
require_once $zeus_src_dir . 'class-zeus-learning.php';
require_once $zeus_src_dir . 'class-zeus-env.php';
require_once $zeus_src_dir . 'class-zeus-compiler.php';
require_once $zeus_src_dir . 'class-zeus-shield.php';
require_once dirname(__DIR__, 2) . '/modules/zeus/class-vis-zeus.php';

use VisionGaia\GeDefense\Modules\Zeus\Zeus_Vault_Resolver;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Config_Repository;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Blackbox;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Benchmark;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Contracts;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Edge;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Xdr_Bridge;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Learning;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Policy_Manager;

$zeus_config = Zeus_Config_Repository::get();
$vault_dir = Zeus_Vault_Resolver::getVaultDir();
$waf_file = Zeus_Vault_Resolver::getWafFile();
$waf_active = file_exists($waf_file) && is_readable($waf_file);

$metrics = Zeus_Blackbox::getMetrics($vault_dir);
$fingerprint = Zeus_Benchmark::getHardwareFingerprint();
$history = Zeus_Benchmark::getRunHistory();
$contracts = Zeus_Contracts::getDefaultContracts();
$custom_contracts = get_option('vis_zeus_custom_contracts', []);
if (is_array($custom_contracts)) {
    $contracts = array_merge($contracts, $custom_contracts);
}
$mtls_status = Zeus_Edge::getMtlsStatus();
$active_contained_routes = Zeus_Xdr_Bridge::getActiveRouteContainments();
$learned_candidates = Zeus_Learning::getCandidates();

$policy_digest = $zeus_config['policy_digest'] ?? Zeus_Policy_Manager::computeDigest($zeus_config);

$zeusToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Zeus Next Generation Pre-Boot Security Kernel">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('L0 PRE-BOOT ADMISSION CONTROL & EDGE SECURITY KERNEL', 'vgt-sentinel'); ?></p>
            <h2>ZEUS NEXT GEN</h2>
            <p><?php esc_html_e('Pre-Boot Admission Control, Request Envelope Firewall, Tamper-Evident Blackbox & Virtual Emergency Containment.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Zeus Status">
            <span><small><?php esc_html_e('L0 KERNEL', 'vgt-sentinel'); ?></small><strong><?php echo $waf_active ? esc_html__('ACTIVE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('POLICY DIGEST', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html(substr($policy_digest, 0, 10)); ?>...</strong></span>
            <span><small><?php esc_html_e('EVALUATIONS', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(number_format_i18n($metrics['total_requests'] ?? 0)); ?></strong></span>
            <span><small><?php esc_html_e('CONTAINED ROUTES', 'vgt-sentinel'); ?></small><strong><?php echo count($active_contained_routes); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('PRE-BOOT STATE', 'vgt-sentinel'); ?></small><strong><?php echo $waf_active ? esc_html__('ACTIVE', 'vgt-sentinel') : esc_html__('UNCOMPILED', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('TOTAL EVALUATIONS', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(number_format_i18n($metrics['total_requests'] ?? 0)); ?></strong></article>
        <article><small><?php esc_html_e('DROPPED THREATS', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php echo esc_html(number_format_i18n($metrics['blocked_requests'] ?? 0)); ?></strong></article>
        <article><small><?php esc_html_e('ACTIVE ROUTE CONTRACTS', 'vgt-sentinel'); ?></small><strong><?php echo count($contracts); ?></strong></article>
        <article><small><?php esc_html_e('BLACKBOX INTEGRITY', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo !empty($metrics['tamper_evident']) ? esc_html__('SEALED', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('LATENCY OVERHEAD', 'vgt-sentinel'); ?></small><strong>< 0.05ms</strong></article>
    </div>

    <!-- TABS NAVIGATION -->
    <nav class="vgt-titan-nav vgt-mode-nav" aria-label="Zeus Bereiche">
        <button type="button" class="vgt-titan-download vgt-mode-btn active" data-tab="tab-essential">
            <?php esc_html_e('Essential Controls', 'vgt-sentinel'); ?>
        </button>
        <button type="button" class="vgt-titan-download vgt-mode-btn" data-tab="tab-contracts">
            <?php esc_html_e('Route Contracts', 'vgt-sentinel'); ?> (<?php echo count($contracts); ?>)
        </button>
        <button type="button" class="vgt-titan-download vgt-mode-btn" data-tab="tab-telemetry">
            <?php esc_html_e('Blackbox Flight Recorder', 'vgt-sentinel'); ?>
        </button>
        <button type="button" class="vgt-titan-download vgt-mode-btn" data-tab="tab-lab">
            <?php esc_html_e('Zeus Hardening Lab', 'vgt-sentinel'); ?>
        </button>
        <button type="button" class="vgt-titan-download vgt-mode-btn" data-tab="tab-expert">
            <?php esc_html_e('Expert Settings', 'vgt-sentinel'); ?>
        </button>
    </nav>

    <!-- Hidden nonces & flags -->
    <?php wp_nonce_field('vis_save_zeus', 'vis_zeus_nonce'); ?>
    <input type="hidden" name="action" value="vis_save_zeus_config">
    <input type="hidden" name="vgt_zeus_form_submit" value="1">

    <!-- ===================================================================
         TAB 1: ESSENTIAL CONTROLS (FULL CONFIGURATION MATRIX)
         =================================================================== -->
    <div id="tab-essential" class="vgt-tab-pane active">
        <div style="display: grid; gap: 16px;">
            
            <!-- 1. PRIMARY ADMISSION CONTROLS & SECURITY PROFILE -->
            <section class="vgt-titan-panel">
                <div class="vgt-titan-panel-head">
                    <div>
                        <small><?php esc_html_e('01 / ADMISSION & PROFILE', 'vgt-sentinel'); ?></small>
                        <h3><?php esc_html_e('Pre-Boot Admission & Threat Profile', 'vgt-sentinel'); ?></h3>
                    </div>
                    <?php $zeusToggle('zeus_enabled', !empty($zeus_config['zeus_enabled']), 'ZEUS Master Admission Gate'); ?>
                </div>
                
                <div class="vgt-titan-fields">
                    <label>
                        <span><?php esc_html_e('Security Profile Posture', 'vgt-sentinel'); ?></span>
                        <select name="security_profile" class="vgt-titan-select" id="cfg-security-profile">
                            <option value="BALANCED" <?php selected($zeus_config['security_profile'] ?? 'BALANCED', 'BALANCED'); ?>><?php esc_html_e('BALANCED (Recommended)', 'vgt-sentinel'); ?></option>
                            <option value="STRICT" <?php selected($zeus_config['security_profile'] ?? 'BALANCED', 'STRICT'); ?>><?php esc_html_e('STRICT (High Protection)', 'vgt-sentinel'); ?></option>
                            <option value="PARANOID" <?php selected($zeus_config['security_profile'] ?? 'BALANCED', 'PARANOID'); ?>><?php esc_html_e('PARANOID (Maximum Containment)', 'vgt-sentinel'); ?></option>
                            <option value="CUSTOM" <?php selected($zeus_config['security_profile'] ?? 'BALANCED', 'CUSTOM'); ?>><?php esc_html_e('CUSTOM (User Tuned)', 'vgt-sentinel'); ?></option>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Emergency Lockdown State', 'vgt-sentinel'); ?></span>
                        <select name="lockdown_state" class="vgt-titan-select" id="cfg-lockdown-state">
                            <option value="NORMAL" <?php selected($zeus_config['lockdown_state'] ?? 'NORMAL', 'NORMAL'); ?>><?php esc_html_e('NORMAL (Default)', 'vgt-sentinel'); ?></option>
                            <option value="HARDENED" <?php selected($zeus_config['lockdown_state'] ?? 'NORMAL', 'HARDENED'); ?>><?php esc_html_e('HARDENED', 'vgt-sentinel'); ?></option>
                            <option value="FORTRESS" <?php selected($zeus_config['lockdown_state'] ?? 'NORMAL', 'FORTRESS'); ?>><?php esc_html_e('FORTRESS', 'vgt-sentinel'); ?></option>
                            <option value="INCIDENT_LOCKDOWN" <?php selected($zeus_config['lockdown_state'] ?? 'NORMAL', 'INCIDENT_LOCKDOWN'); ?>><?php esc_html_e('INCIDENT LOCKDOWN (Emergency)', 'vgt-sentinel'); ?></option>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Pre-Boot Login Admission Mode', 'vgt-sentinel'); ?></span>
                        <select name="login_admission_mode" class="vgt-titan-select">
                            <option value="DISABLED" <?php selected($zeus_config['login_admission_mode'] ?? 'DISABLED', 'DISABLED'); ?>><?php esc_html_e('DISABLED (Standard)', 'vgt-sentinel'); ?></option>
                            <option value="ADMISSION_TOKEN" <?php selected($zeus_config['login_admission_mode'] ?? 'DISABLED', 'ADMISSION_TOKEN'); ?>><?php esc_html_e('ADMISSION TOKEN (Strict HMAC)', 'vgt-sentinel'); ?></option>
                        </select>
                    </label>
                </div>

                <div class="vgt-titan-toggle-grid">
                    <?php $zeusToggle('learning_mode_enabled', !empty($zeus_config['learning_mode_enabled']), 'Learning Mode (Log & Observe Only)'); ?>
                    <?php $zeusToggle('fw_basic', !empty($zeus_config['fw_basic']), 'Basic WAF Filter Pipeline'); ?>
                    <?php $zeusToggle('fw_6g_blacklist', !empty($zeus_config['fw_6g_blacklist']), 'Canonicalization Guard (6G Firewall)'); ?>
                    <?php $zeusToggle('fw_fake_googlebot', !empty($zeus_config['fw_fake_googlebot']), 'Block Fake Crawlers & Fake Googlebots'); ?>
                    <?php $zeusToggle('fw_block_xmlrpc', !empty($zeus_config['fw_block_xmlrpc']), 'Terminate XML-RPC Interface'); ?>
                </div>
            </section>

            <!-- 2. HOST LOCK & CANONICAL DOMAINS -->
            <section class="vgt-titan-panel">
                <div class="vgt-titan-panel-head">
                    <div>
                        <small><?php esc_html_e('02 / HOST LOCK', 'vgt-sentinel'); ?></small>
                        <h3><?php esc_html_e('Host Lock & Canonical Domain Routing', 'vgt-sentinel'); ?></h3>
                    </div>
                </div>

                <div class="vgt-titan-fields" style="grid-template-columns: 1fr 2fr;">
                    <label>
                        <span><?php esc_html_e('Host Lock Mode', 'vgt-sentinel'); ?></span>
                        <select name="host_lock_mode" class="vgt-titan-select">
                            <option value="DISABLED" <?php selected($zeus_config['host_lock_mode'] ?? 'DISABLED', 'DISABLED'); ?>><?php esc_html_e('DISABLED', 'vgt-sentinel'); ?></option>
                            <option value="AUDIT" <?php selected($zeus_config['host_lock_mode'] ?? 'DISABLED', 'AUDIT'); ?>><?php esc_html_e('AUDIT (Log Only)', 'vgt-sentinel'); ?></option>
                            <option value="REJECT" <?php selected($zeus_config['host_lock_mode'] ?? 'DISABLED', 'REJECT'); ?>><?php esc_html_e('REJECT (421 Misdirected)', 'vgt-sentinel'); ?></option>
                        </select>
                    </label>
                    <label class="vgt-titan-wide-field">
                        <span><?php esc_html_e('Canonical Hosts (Eine pro Zeile)', 'vgt-sentinel'); ?></span>
                        <textarea name="canonical_hosts" rows="2" placeholder="example.com&#10;www.example.com"><?php 
                            echo esc_textarea(implode("
", (array)($zeus_config['canonical_hosts'] ?? [parse_url(home_url(), PHP_URL_HOST)]))); 
                        ?></textarea>
                    </label>
                </div>
            </section>

            <!-- 3. REQUEST RATE BUDGET & DDOS DEFENSE -->
            <section class="vgt-titan-panel">
                <div class="vgt-titan-panel-head">
                    <div>
                        <small><?php esc_html_e('03 / RATE BUDGETING', 'vgt-sentinel'); ?></small>
                        <h3><?php esc_html_e('Request Budget Engine & IP/Subnet Throttling', 'vgt-sentinel'); ?></h3>
                    </div>
                    <?php $zeusToggle('budget_enabled', !empty($zeus_config['budget_enabled']), 'Rate Budget Engine'); ?>
                </div>

                <div class="vgt-titan-fields">
                    <label>
                        <span><?php esc_html_e('Single-IP Request Limit (per 60s)', 'vgt-sentinel'); ?></span>
                        <input type="number" name="budget_ip_limit" min="1" max="5000" value="<?php echo esc_attr((string)($zeus_config['budget_ip_limit'] ?? 180)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Subnet Cluster Limit (per 60s)', 'vgt-sentinel'); ?></span>
                        <input type="number" name="budget_subnet_limit" min="2" max="10000" value="<?php echo esc_attr((string)($zeus_config['budget_subnet_limit'] ?? 450)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Rate Budget Action Mode', 'vgt-sentinel'); ?></span>
                        <select name="budget_action_mode" class="vgt-titan-select">
                            <option value="THROTTLE" <?php selected($zeus_config['budget_action_mode'] ?? 'THROTTLE', 'THROTTLE'); ?>><?php esc_html_e('THROTTLE (Sleep Delay)', 'vgt-sentinel'); ?></option>
                            <option value="TEMPORARY_REJECT" <?php selected($zeus_config['budget_action_mode'] ?? 'THROTTLE', 'TEMPORARY_REJECT'); ?>><?php esc_html_e('TEMPORARY REJECT (503 Service Unavailable)', 'vgt-sentinel'); ?></option>
                            <option value="XDR_SIGNAL" <?php selected($zeus_config['budget_action_mode'] ?? 'THROTTLE', 'XDR_SIGNAL'); ?>><?php esc_html_e('XDR SIGNAL (Trigger Incident Isolation)', 'vgt-sentinel'); ?></option>
                        </select>
                    </label>
                </div>
            </section>

            <!-- 4. BRUTE FORCE, AUTH & APPLICATION HARDENING -->
            <section class="vgt-titan-panel">
                <div class="vgt-titan-panel-head">
                    <div>
                        <small><?php esc_html_e('04 / AUTH & HARDENING', 'vgt-sentinel'); ?></small>
                        <h3><?php esc_html_e('Brute Force Defense & Application Hardening', 'vgt-sentinel'); ?></h3>
                    </div>
                </div>

                <div class="vgt-titan-fields">
                    <label>
                        <span><?php esc_html_e('Custom Login URL Slug (Rename wp-login)', 'vgt-sentinel'); ?></span>
                        <input type="text" name="brute_rename_login" placeholder="secure-entry" value="<?php echo esc_attr((string)($zeus_config['brute_rename_login'] ?? '')); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Secret Magic Gateway Cookie', 'vgt-sentinel'); ?></span>
                        <input type="text" name="brute_magic_cookie" placeholder="vgt_pass_token" value="<?php echo esc_attr((string)($zeus_config['brute_magic_cookie'] ?? '')); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('404 Sweep Lockout Threshold', 'vgt-sentinel'); ?></span>
                        <input type="number" name="brute_404_lockout" min="5" max="100" value="<?php echo esc_attr((string)($zeus_config['brute_404_lockout'] ?? 20)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Failed Login Attempts Lockout', 'vgt-sentinel'); ?></span>
                        <input type="number" name="user_login_lockdown" min="1" max="20" value="<?php echo esc_attr((string)($zeus_config['user_login_lockdown'] ?? 5)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Force Admin Session Timeout (Seconds)', 'vgt-sentinel'); ?></span>
                        <input type="number" name="user_force_logout" min="60" max="86400" value="<?php echo esc_attr((string)($zeus_config['user_force_logout'] ?? 3600)); ?>">
                    </label>
                </div>

                <div class="vgt-titan-toggle-grid">
                    <?php $zeusToggle('fs_disable_edit', !empty($zeus_config['fs_disable_edit']), 'DISALLOW_FILE_EDIT (Disable Plugin/Theme Editor)'); ?>
                    <?php $zeusToggle('fs_prevent_hotlink', !empty($zeus_config['fs_prevent_hotlink']), 'Prevent Direct Media Hotlinking'); ?>
                    <?php $zeusToggle('spam_comment_block', !empty($zeus_config['spam_comment_block']), 'Automated Spam Comment Blocker'); ?>
                </div>
            </section>

            <div class="vgt-titan-actions">
                <button type="submit"><?php esc_html_e('ZEUS EINSTELLUNGEN SPEICHERN & KERNEL KOMPILIEREN', 'vgt-sentinel'); ?></button>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         TAB 5: EXPERT SETTINGS (REQUEST ENVELOPE LIMITS & METHODS)
         =================================================================== -->
    <div id="tab-expert" class="vgt-tab-pane" style="display:none;">
        <div style="display: grid; gap: 16px;">
            <section class="vgt-titan-panel">
                <div class="vgt-titan-panel-head">
                    <div>
                        <small><?php esc_html_e('05 / ENVELOPE LIMITS', 'vgt-sentinel'); ?></small>
                        <h3><?php esc_html_e('Request Envelope Firewalls & HTTP Structural Limits', 'vgt-sentinel'); ?></h3>
                    </div>
                </div>

                <div class="vgt-titan-fields">
                    <label>
                        <span><?php esc_html_e('Max Query Length (Bytes)', 'vgt-sentinel'); ?></span>
                        <input type="number" name="max_query_length" min="64" max="16384" value="<?php echo esc_attr((string)($zeus_config['max_query_length'] ?? 2048)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Max Query Parameter Count', 'vgt-sentinel'); ?></span>
                        <input type="number" name="max_query_params" min="1" max="500" value="<?php echo esc_attr((string)($zeus_config['max_query_params'] ?? 100)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Max Header Count', 'vgt-sentinel'); ?></span>
                        <input type="number" name="max_header_count" min="5" max="200" value="<?php echo esc_attr((string)($zeus_config['max_header_count'] ?? 50)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Max Header Total Size (Bytes)', 'vgt-sentinel'); ?></span>
                        <input type="number" name="max_header_size" min="1024" max="65536" value="<?php echo esc_attr((string)($zeus_config['max_header_size'] ?? 16384)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Max Cookie Total Size (Bytes)', 'vgt-sentinel'); ?></span>
                        <input type="number" name="max_cookie_size" min="512" max="32768" value="<?php echo esc_attr((string)($zeus_config['max_cookie_size'] ?? 8192)); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Default Max POST Body (Bytes)', 'vgt-sentinel'); ?></span>
                        <input type="number" name="max_body_default" min="1024" value="<?php echo esc_attr((string)($zeus_config['max_body_default'] ?? 67108864)); ?>">
                    </label>
                </div>

                <div class="vgt-titan-panel-head" style="margin-top: 20px;">
                    <div>
                        <small><?php esc_html_e('ALLOWED HTTP METHODS', 'vgt-sentinel'); ?></small>
                        <h3><?php esc_html_e('Permitted Global Verbs', 'vgt-sentinel'); ?></h3>
                    </div>
                </div>
                <div class="vgt-titan-toggle-grid">
                    <?php 
                        $currMethods = (array)($zeus_config['allowed_methods'] ?? ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE']);
                        foreach (['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'PATCH', 'DELETE', 'TRACE', 'CONNECT'] as $m): 
                    ?>
                    <label class="vgt-titan-toggle">
                        <input type="checkbox" name="allowed_methods[]" value="<?php echo esc_attr($m); ?>" <?php checked(in_array($m, $currMethods, true)); ?>>
                        <span aria-hidden="true"></span>
                        <b><?php echo esc_html($m); ?></b>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="vgt-titan-actions" style="margin-top:20px;">
                    <button type="submit"><?php esc_html_e('EXPERT LIMITS SPEICHERN', 'vgt-sentinel'); ?></button>
                    <button type="button" id="btn-rollback-lkg" style="border-color:#f59e0b; color:#fde68a; background:rgba(245,158,11,0.1);">
                        <?php esc_html_e('ROLLBACK TO LAST KNOWN GOOD (LKG)', 'vgt-sentinel'); ?>
                    </button>
                </div>
            </section>
        </div>
    </div>

    <!-- ===================================================================
         TAB 2: ROUTE CONTRACT MATRIX
         =================================================================== -->
    <div id="tab-contracts" class="vgt-tab-pane" style="display:none;">
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('02 / ROUTE CONTRACTS', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Active Route Contract Enforcement Matrix', 'vgt-sentinel'); ?></h3>
                </div>
            </div>

            <?php if (!empty($active_contained_routes)): ?>
            <div class="vgt-titan-findings">
                <p class="is-critical">
                    <strong><?php esc_html_e('ACTIVE XDR VIRTUAL ROUTE CONTAINMENTS:', 'vgt-sentinel'); ?></strong><br>
                    <?php foreach ($active_contained_routes as $prefix => $info): ?>
                        <code><?php echo esc_html($prefix); ?></code> — <?php esc_html_e('Expires:', 'vgt-sentinel'); ?> <?php echo esc_html($info['expires_at'] ?? ''); ?><br>
                    <?php endforeach; ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="vgt-titan-table-wrap">
                <table class="vgt-titan-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ROUTE SURFACE', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('MATCH', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('PERMITTED METHODS', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('MAX BODY', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('RATE BUDGET', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('ADMISSION', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('STATUS', 'vgt-sentinel'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('ACTIONS', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contracts as $cId => $c): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($c['name'] ?? $cId); ?></strong>
                                <div style="font-family:monospace; font-size:10px; color:#7dd3fc;"><?php echo esc_html($c['path'] ?? ''); ?></div>
                            </td>
                            <td><code><?php echo esc_html($c['match_type'] ?? 'EXACT'); ?></code></td>
                            <td>
                                <?php foreach ((array)($c['methods'] ?? ['GET']) as $m): ?>
                                <span class="vgt-titan-badge" style="padding: 2px 6px; font-size: 9px;"><?php echo esc_html($m); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><code><?php echo esc_html((string)round(($c['max_body_bytes'] ?? 65536) / 1024)); ?> KB</code></td>
                            <td><code><?php echo esc_html((string)($c['rate_budget']['limit'] ?? 60)); ?> / <?php echo esc_html((string)($c['rate_budget']['window'] ?? 60)); ?>s</code></td>
                            <td>
                                <?php echo !empty($c['admission_required']) ? '<strong style="color:#fb7185;">REQUIRED</strong>' : '<span style="color:#64748b;">PUBLIC</span>'; ?>
                            </td>
                            <td>
                                <strong style="color: <?php echo ($c['status'] ?? 'ACTIVE') === 'ACTIVE' ? '#5eead4' : '#64748b'; ?>;">
                                    <?php echo esc_html($c['status'] ?? 'ACTIVE'); ?>
                                </strong>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="btn-delete-contract" data-id="<?php echo esc_attr($cId); ?>" style="background:rgba(251,113,133,0.1); border:1px solid #fb7185; color:#fecdd3; border-radius:6px; padding:4px 8px; cursor:pointer; font:700 10px monospace;">
                                    <?php esc_html_e('DELETE', 'vgt-sentinel'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- ===================================================================
         TAB 3: BLACKBOX FLIGHT RECORDER
         =================================================================== -->
    <div id="tab-telemetry" class="vgt-tab-pane" style="display:none;">
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('03 / TAMPER-EVIDENT BLACKBOX', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Blackbox Flight Recorder & Telemetry Chain', 'vgt-sentinel'); ?></h3>
                </div>
                <div class="vgt-titan-actions" style="margin-top:0;">
                    <button type="button" id="btn-drain-blackbox"><?php esc_html_e('DRAIN TO TRINITY XDR', 'vgt-sentinel'); ?></button>
                </div>
            </div>

            <div class="vgt-titan-validation-grid" style="margin-bottom:18px;">
                <div>
                    <span><?php esc_html_e('Total Evaluated Requests', 'vgt-sentinel'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($metrics['total_requests'] ?? 0)); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Blocked Attack Ingress', 'vgt-sentinel'); ?></span>
                    <strong style="color:#fb7185;"><?php echo esc_html(number_format_i18n($metrics['blocked_requests'] ?? 0)); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Clean Requests Admitted', 'vgt-sentinel'); ?></span>
                    <strong style="color:#5eead4;"><?php echo esc_html(number_format_i18n($metrics['allowed_requests'] ?? 0)); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Tamper-Evident Hash Chain', 'vgt-sentinel'); ?></span>
                    <strong style="color:#7dd3fc;"><?php echo !empty($metrics['tamper_evident']) ? esc_html__('VERIFIED (SHA-256)', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong>
                </div>
            </div>
        </section>
    </div>

    <!-- ===================================================================
         TAB 4: HARDENING LAB
         =================================================================== -->
    <div id="tab-lab" class="vgt-tab-pane" style="display:none;">
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('04 / PERFORMANCE LAB', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Zeus Hardening Lab & Microbenchmark Suite', 'vgt-sentinel'); ?></h3>
                </div>
            </div>

            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('Test Profile', 'vgt-sentinel'); ?></span>
                    <select id="bmk-profile" class="vgt-titan-select">
                        <option value="BALANCED"><?php esc_html_e('BALANCED Policy', 'vgt-sentinel'); ?></option>
                        <option value="STRICT"><?php esc_html_e('STRICT Policy', 'vgt-sentinel'); ?></option>
                        <option value="PARANOID"><?php esc_html_e('PARANOID Policy', 'vgt-sentinel'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Synthetic Request Iterations', 'vgt-sentinel'); ?></span>
                    <select id="bmk-iterations" class="vgt-titan-select">
                        <option value="1000">1.000 Iterationen</option>
                        <option value="5000" selected>5.000 Iterationen</option>
                        <option value="20000">20.000 Iterationen (Stress-Test)</option>
                    </select>
                </label>
            </div>

            <div class="vgt-titan-actions">
                <button type="button" id="btn-run-benchmark"><?php esc_html_e('START ENGINE BENCHMARK', 'vgt-sentinel'); ?></button>
                <button type="button" id="btn-run-selftest"><?php esc_html_e('RUN SECURITY SELF-TEST', 'vgt-sentinel'); ?></button>
            </div>

            <!-- BENCHMARK RESULTS -->
            <div id="bmk-results-card" style="display:none; margin-top:20px; background:rgba(3,8,16,0.7); padding:18px; border-radius:12px; border:1px solid rgba(94,234,212,0.2);">
                <div class="vgt-titan-validation-grid">
                    <div>
                        <span><?php esc_html_e('Evaluations / Second', 'vgt-sentinel'); ?></span>
                        <strong id="bmk-res-evals" style="color:#5eead4; font-size:18px;">--</strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('p50 Latency', 'vgt-sentinel'); ?></span>
                        <strong id="bmk-res-p50">--</strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('p95 Latency', 'vgt-sentinel'); ?></span>
                        <strong id="bmk-res-p95">--</strong>
                    </div>
                </div>
                <p id="bmk-res-detail" style="margin-top:10px; font-size:11px; color:#94a3b8; font-family:ui-monospace, monospace;"></p>
            </div>

            <!-- SELF TEST RESULTS -->
            <div id="selftest-results-grid" style="display:none; margin-top:20px;">
                <h4 style="color:#fff; margin-bottom:10px;"><?php esc_html_e('Self-Test Results Matrix', 'vgt-sentinel'); ?></h4>
                <div id="selftest-items" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px;"></div>
            </div>
        </section>
    </div>
</section>

