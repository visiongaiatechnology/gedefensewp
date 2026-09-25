<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);
if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$status = class_exists('VIS_Throne_Guard') ? VIS_Throne_Guard::status() : [];
$is_master = !empty($status['is_master']);
$master_count = (int)($status['master_count'] ?? 0);
$superkey_set = !empty($status['superkey_set']);
$harden_admin = !empty($status['harden_admin']);
$lock_enabled = !empty($status['lock_enabled']);
$restricted_caps = (array)($status['restricted_caps'] ?? []);
$available_caps = (array)($status['available_caps'] ?? []);
$logs = (array)($status['logs'] ?? []);
$current_ip = (string)($status['current_ip'] ?? '0.0.0.0');
$ip_whitelisted = !empty($status['ip_whitelisted']);
$ip_lease = $status['ip_lease'] ?? null;
$ip_lease_remaining = (int)($status['ip_lease_remaining'] ?? 0);
$active_ip_leases = (array)($status['active_ip_leases'] ?? []);
$rem_hours = intdiv($ip_lease_remaining, 3600);
$rem_mins = intdiv($ip_lease_remaining % 3600, 60);

$total_available_count = 0;
foreach ($available_caps as $grp) {
    $total_available_count += count($grp['caps'] ?? []);
}
$active_restricted_count = count($restricted_caps);

$claimed = isset($_GET['claimed']);
$updated = isset($_GET['updated']);
$whitelisted_notice = isset($_GET['whitelisted']);
$lease_revoked = isset($_GET['lease_revoked']);
$throne_error = isset($_GET['throne_error']) ? sanitize_key($_GET['throne_error']) : '';
?>

<!-- =========================================================================================
     THRONEGUARD CYBERPUNK APEX STYLES (Zero Dependencies)
     ========================================================================================= -->
<div class="tg-view-wrapper" id="vgt-throneguard" data-action-nonce="<?php echo esc_attr(wp_create_nonce('vis_throneguard_action')); ?>" data-clear-confirm="<?php echo esc_attr__('Möchtest du das gesamte ThroneGuard Audit-Protokoll wirklich leeren?', 'vgt-sentinel'); ?>" data-clearing-label="<?php echo esc_attr__('Leere...', 'vgt-sentinel'); ?>" data-cleared-label="<?php echo esc_attr__('Audit-Protokoll wurde geleert.', 'vgt-sentinel'); ?>" data-clear-error="<?php echo esc_attr__('Fehler beim Leeren der Logs.', 'vgt-sentinel'); ?>" data-clear-label="<?php echo esc_attr__('Logs leeren', 'vgt-sentinel'); ?>">
    
    <!-- NOTICES -->
    <?php if ($claimed): ?>
        <div class="tg-alert tg-alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <strong><?php esc_html_e('MASTER-ROLLE AKTIVIERT:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Dein Benutzerkonto ist nun als GeDefense-Master provisioniert.', 'vgt-sentinel'); ?>
        </div>
    <?php endif; ?>

    <?php if ($updated): ?>
        <div class="tg-alert tg-alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <strong><?php esc_html_e('KONFIGURATION GESPEICHERT:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('ThroneGuard Privilege Boundary & Superkey-Matrix wurden erfolgreich aktualisiert.', 'vgt-sentinel'); ?>
            <?php if ($whitelisted_notice): ?>
                <?php echo ' — '; ?><strong><?php echo esc_html(sprintf(__('Deine IP (%s) wurde für 12 Stunden in AEGIS, Prometheus & ZEUS auf die Whitelist gesetzt.', 'vgt-sentinel'), $current_ip)); ?></strong>
            <?php endif; ?>
        </div>
    <?php elseif ($whitelisted_notice): ?>
        <div class="tg-alert tg-alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <strong><?php esc_html_e('12H MASTER-WHITELIST AKTIV:', 'vgt-sentinel'); ?></strong> <?php echo esc_html(sprintf(__('Deine IP (%s) ist für 12 Stunden in AEGIS, Prometheus & ZEUS freigeschaltet und wird danach automatisch entfernt.', 'vgt-sentinel'), $current_ip)); ?>
        </div>
    <?php endif; ?>

    <?php if ($lease_revoked): ?>
        <div class="tg-alert tg-alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <strong><?php esc_html_e('WHITELIST-LEASE BEENDET:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Die temporäre 12h Master-IP-Freigabe wurde aus AEGIS, Prometheus und ZEUS entfernt.', 'vgt-sentinel'); ?>
        </div>
    <?php endif; ?>

    <?php if ($throne_error === 'verification'): ?>
        <div class="tg-alert tg-alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <strong><?php esc_html_e('VERIFIKATION FEHLGESCHLAGEN:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Der eingegebene aktuelle Superkey war nicht korrekt.', 'vgt-sentinel'); ?>
        </div>
    <?php elseif ($throne_error === 'key_length'): ?>
        <div class="tg-alert tg-alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <strong><?php esc_html_e('UNZUREICHENDE ENTROPIE:', 'vgt-sentinel'); ?></strong> <?php esc_html_e('Der Superkey muss aus Sicherheitsgründen mindestens 12 Zeichen lang sein.', 'vgt-sentinel'); ?>
        </div>
    <?php endif; ?>

    <!-- HERO BANNER -->
    <div class="tg-hero-banner">
        <div class="tg-hero-left">
            <div class="tg-crown-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 7l4 4 5-7 5 7 4-4-2 12H5L3 7z"/>
                    <line x1="5" y1="22" x2="19" y2="22"/>
                </svg>
            </div>
            <div>
                <h2 class="tg-hero-title">THRONE<span>GUARD</span> // <?php esc_html_e('SOVEREIGN PRIVILEGE SENTINEL', 'vgt-sentinel'); ?></h2>
                <p class="tg-hero-desc"><?php esc_html_e('Trennt privilegierte GeDefense-Master von Standard-Administratoren. Schützt Plugins, Themes, User-Elevation und REST-APIs vor unautorisierter Manipulation durch kompromittierte Admin-Konten.', 'vgt-sentinel'); ?></p>
            </div>
        </div>
        <div>
            <?php if (!$is_master): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('vis_throneguard_claim'); ?>
                    <input type="hidden" name="action" value="vis_throneguard_claim">
                    <button class="tg-btn-primary" type="submit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php esc_html_e('MASTER-ROLLE ÜBERNEHMEN', 'vgt-sentinel'); ?>
                    </button>
                </form>
            <?php else: ?>
                <div style="text-align:right">
                    <span style="font-size:11px; font-family:monospace; color:#10b981; font-weight:800; letter-spacing:1px; background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.4); padding:6px 14px; border-radius:99px; display:inline-flex; align-items:center; gap:6px;">
                        <span class="tg-status-dot active"></span>
                        <?php esc_html_e('MASTER-SITZUNG VERIFIZIERT', 'vgt-sentinel'); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- METRICS GRID -->
    <div class="tg-metrics-grid">
        <!-- 1. Master Sovereignty -->
        <div class="tg-metric-card" style="--card-accent: #a855f7;">
            <div class="tg-metric-label">
                <span><?php esc_html_e('Master-Souveränität', 'vgt-sentinel'); ?></span>
                <span class="tg-status-dot <?php echo $is_master ? 'active' : 'warning'; ?>"></span>
            </div>
            <div class="tg-metric-val">
                <?php echo $is_master ? esc_html__('MASTER NODE', 'vgt-sentinel') : esc_html__('STANDARD ADMIN', 'vgt-sentinel'); ?>
            </div>
            <div class="tg-metric-sub">
                <?php esc_html_e('Registrierte Master-Konten:', 'vgt-sentinel'); ?> <strong style="color:#fff"><?php echo (int)$master_count; ?></strong>
            </div>
        </div>

        <!-- 2. Superkey Vault -->
        <div class="tg-metric-card" style="--card-accent: #d4af37;">
            <div class="tg-metric-label">
                <span><?php esc_html_e('Superkey-Tresor', 'vgt-sentinel'); ?></span>
                <span class="tg-status-dot <?php echo $superkey_set ? 'active' : 'critical'; ?>"></span>
            </div>
            <div class="tg-metric-val" style="color: <?php echo $superkey_set ? '#d4af37' : '#ef4444'; ?>">
                <?php echo $superkey_set ? esc_html__('ARMED & ACTIVE', 'vgt-sentinel') : esc_html__('UNSET / VULNERABLE', 'vgt-sentinel'); ?>
            </div>
            <div class="tg-metric-sub">
                <?php esc_html_e('Hashing:', 'vgt-sentinel'); ?> <strong style="color:#fff"><?php echo $superkey_set ? 'PBKDF2 / SHA-256' : esc_html__('Kein Superkey', 'vgt-sentinel'); ?></strong>
            </div>
        </div>

        <!-- 3. Admin Hardening Level -->
        <div class="tg-metric-card" style="--card-accent: #00f0ff;">
            <div class="tg-metric-label">
                <span><?php esc_html_e('Admin-Rechtefilter', 'vgt-sentinel'); ?></span>
                <span class="tg-status-dot <?php echo $harden_admin ? 'active' : 'inactive'; ?>"></span>
            </div>
            <div class="tg-metric-val" style="color: <?php echo $harden_admin ? '#00f0ff' : '#94a3b8'; ?>">
                <?php echo $harden_admin ? ($active_restricted_count . ' / ' . $total_available_count . ' ' . esc_html__('RESTRICTED', 'vgt-sentinel')) : esc_html__('OFFLINE', 'vgt-sentinel'); ?>
            </div>
            <div class="tg-metric-sub">
                <?php esc_html_e('Status:', 'vgt-sentinel'); ?> <strong style="color:#fff"><?php echo $harden_admin ? esc_html__('Administrator-Rolle gehärtet', 'vgt-sentinel') : esc_html__('Volle Admin-Rechte', 'vgt-sentinel'); ?></strong>
            </div>
        </div>

        <!-- 4. 12h Omni-Whitelist (AEGIS + Prometheus + ZEUS) -->
        <div class="tg-metric-card" style="--card-accent: #10b981;">
            <div class="tg-metric-label">
                <span><?php esc_html_e('12h Master-Omni-Whitelist', 'vgt-sentinel'); ?></span>
                <span class="tg-status-dot <?php echo $ip_whitelisted ? 'active' : ($lock_enabled ? 'warning' : 'inactive'); ?>"></span>
            </div>
            <div class="tg-metric-val" style="color: <?php echo $ip_whitelisted ? '#10b981' : '#94a3b8'; ?>">
                <?php if ($ip_whitelisted): ?>
                    <?php echo esc_html(sprintf('%02dh %02dm REST', $rem_hours, $rem_mins)); ?>
                <?php else: ?>
                    <?php echo $lock_enabled ? esc_html__('BEREIT (SUPERKEY)', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?>
                <?php endif; ?>
            </div>
            <div class="tg-metric-sub">
                <?php esc_html_e('AEGIS + Prometheus + ZEUS:', 'vgt-sentinel'); ?> <strong style="color:#fff"><?php echo $ip_whitelisted ? esc_html($current_ip) : esc_html__('Bei Superkey-Login (12h)', 'vgt-sentinel'); ?></strong>
            </div>
        </div>
    </div>

    <!-- MAIN INTERACTIVE WORKSPACE -->
    <?php if ($is_master): ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="throneguard-config-form">
            <?php wp_nonce_field('vis_throneguard_save'); ?>
            <input type="hidden" name="action" value="vis_throneguard_save">

            <div class="tg-layout-grid">
                
                <!-- LEFT PANEL: SUPERKEY & LOCKDOWN CONFIG -->
                <div class="tg-panel">
                    <div class="tg-panel-header">
                        <div class="tg-panel-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tg-gold)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <?php esc_html_e('Superkey Tresor & Zero-Trust Lock', 'vgt-sentinel'); ?>
                        </div>
                    </div>

                    <!-- Lock Toggles -->
                    <div class="tg-switch-row">
                        <div class="tg-switch-info">
                            <h4><?php esc_html_e('Administrator-Rolle beschränken', 'vgt-sentinel'); ?></h4>
                            <p><?php esc_html_e('Entfernt die unten ausgewählten Capabilities permanent aus der Administrator-Rolle.', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="tg-switch">
                            <input type="checkbox" name="harden_admin" value="1" <?php checked($harden_admin); ?>>
                            <span class="tg-slider"></span>
                        </label>
                    </div>

                    <div class="tg-switch-row">
                        <div class="tg-switch-info">
                            <h4><?php esc_html_e('Master-Backend & REST-API sperren', 'vgt-sentinel'); ?></h4>
                            <p><?php esc_html_e('Erzwingt bei jedem neuen Login die Eingabe des Superkeys (2 Stunden Session-Gültigkeit).', 'vgt-sentinel'); ?></p>
                        </div>
                        <label class="tg-switch">
                            <input type="checkbox" name="lock_enabled" value="1" <?php checked($lock_enabled); ?>>
                            <span class="tg-slider"></span>
                        </label>
                    </div>

                    <div style="height:1px; background:var(--tg-border); margin:20px 0;"></div>

                    <!-- Superkey Inputs -->
                    <?php if ($superkey_set): ?>
                        <div class="tg-form-group">
                            <label class="tg-form-label" for="tg_curr_key"><?php esc_html_e('Aktueller Superkey (zur Verifikation)', 'vgt-sentinel'); ?></label>
                            <div class="tg-input-wrap">
                                <input class="tg-input" id="tg_curr_key" type="password" name="current_superkey" autocomplete="current-password" placeholder="<?php esc_attr_e('Aktuellen Superkey eingeben...', 'vgt-sentinel'); ?>">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="tg-form-group">
                        <label class="tg-form-label" for="tg_new_key">
                            <?php echo $superkey_set ? esc_html__('Neuer Superkey (leer lassen = unverändert)', 'vgt-sentinel') : esc_html__('Neuen Superkey setzen (mindestens 12 Zeichen)', 'vgt-sentinel'); ?>
                        </label>
                        <div class="tg-input-wrap">
                            <input class="tg-input" id="tg_new_key" type="password" name="new_superkey" minlength="12" maxlength="256" autocomplete="new-password" placeholder="<?php esc_attr_e('Neuen Superkey eingeben...', 'vgt-sentinel'); ?>" <?php echo !$superkey_set ? 'required' : ''; ?>>
                        </div>
                    </div>

                    <div style="margin-top:24px;">
                        <button type="submit" class="tg-btn-primary" style="width:100%; justify-content:center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <?php esc_html_e('THRONEGUARD SPEICHERN & SCHARFSCHALTEN', 'vgt-sentinel'); ?>
                        </button>
                    </div>
                </div>

                <!-- RIGHT PANEL: GRANULAR CAPABILITY MATRIX -->
                <div class="tg-panel">
                    <div class="tg-panel-header">
                        <div class="tg-panel-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--tg-cyan)" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                            <?php esc_html_e('Admin Privilege Boundary Matrix', 'vgt-sentinel'); ?>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="tg-btn-secondary" data-cap-selection="restrict"><?php esc_html_e('Alle sperren', 'vgt-sentinel'); ?></button>
                            <button type="button" class="tg-btn-secondary" data-cap-selection="allow"><?php esc_html_e('Alle erlauben', 'vgt-sentinel'); ?></button>
                        </div>
                    </div>

                    <p style="font-size:12px; color:#94a3b8; margin-top:0; margin-bottom:16px;">
                        <?php esc_html_e('Wähle aus, welche Rechte normalen Administratoren entzogen werden, sobald die Härtung aktiv ist.', 'vgt-sentinel'); ?>
                    </p>

                    <div class="tg-matrix-container" style="max-height:480px; overflow-y:auto; padding-right:6px;">
                        <?php foreach ($available_caps as $catKey => $catData): ?>
                            <div class="tg-category-block">
                                <div class="tg-cat-header">
                                    <span class="tg-cat-title">
                                        <?php if ($catKey === 'plugins'): ?>🔌<?php elseif ($catKey === 'themes'): ?>🎨<?php elseif ($catKey === 'users'): ?>👥<?php else: ?>🛡️<?php endif; ?>
                                        <?php echo esc_html(__($catData['label'], 'vgt-sentinel')); ?>
                                    </span>
                                </div>
                                
                                <?php foreach ($catData['caps'] as $capKey => $capInfo): 
                                    $is_checked = in_array($capKey, $restricted_caps, true);
                                    $riskClass = strtolower($capInfo['risk'] ?? 'high');
                                ?>
                                    <div class="tg-cap-item <?php echo $is_checked ? 'is-restricted' : ''; ?>">
                                        <div class="tg-cap-meta">
                                            <div class="tg-cap-title-row">
                                                <span class="tg-cap-name"><?php echo esc_html(__($capInfo['label'], 'vgt-sentinel')); ?></span>
                                                <span class="tg-cap-code"><?php echo esc_html($capKey); ?></span>
                                                <span class="tg-risk-badge <?php echo esc_attr($riskClass); ?>"><?php echo esc_html($capInfo['risk']); ?></span>
                                            </div>
                                            <span class="tg-cap-desc"><?php echo esc_html(__($capInfo['desc'], 'vgt-sentinel')); ?></span>
                                        </div>
                                        <label class="tg-switch">
                                            <input type="checkbox" name="restricted_caps[]" value="<?php echo esc_attr($capKey); ?>" class="tg-cap-checkbox" <?php checked($is_checked); ?>>
                                            <span class="tg-slider"></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </form>

        <!-- 12H MASTER OMNI-WHITELIST INSTANT TRIGGER & LEASE CONTROL -->
        <div class="tg-panel" style="margin-bottom:24px; border-color:rgba(16,185,129,0.3);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div>
                    <div style="font-size:12px; font-weight:800; color:#10b981; font-family:monospace; letter-spacing:0.5px; margin-bottom:4px;">
                        <?php esc_html_e('12H MASTER OMNI-WHITELIST (AEGIS • PROMETHEUS • ZEUS)', 'vgt-sentinel'); ?>
                        — <?php echo $ip_whitelisted ? esc_html(sprintf(__('AKTIV (%02dh %02dm verbleibend)', 'vgt-sentinel'), $rem_hours, $rem_mins)) : esc_html__('BEREIT', 'vgt-sentinel'); ?>
                    </div>
                    <p style="margin:0; font-size:12px; color:#94a3b8;">
                        <?php echo esc_html(sprintf(__('Sobald du dein ThroneGuard-Passwort (Superkey) eingibst, wird deine IP (%s) automatisch für 12 Stunden in AEGIS, Prometheus und ZEUS (inkl. Pre-Boot L0 WAF) auf die Whitelist gesetzt und nach 12 Stunden automatisch wieder entfernt.', 'vgt-sentinel'), $current_ip)); ?>
                    </p>
                </div>

                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <?php if ($superkey_set): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex; gap:8px; align-items:center; margin:0;">
                            <?php wp_nonce_field('vis_throneguard_unlock'); ?>
                            <input type="hidden" name="action" value="vis_throneguard_unlock">
                            <input class="tg-input" type="password" name="superkey" required autocomplete="current-password" placeholder="<?php esc_attr_e('Superkey für 12h Whitelist...', 'vgt-sentinel'); ?>" style="width:220px; padding:8px 12px; font-size:12px;">
                            <button type="submit" class="tg-btn-secondary" style="border-color:rgba(16,185,129,0.5); color:#6ee7b7; padding:9px 14px; white-space:nowrap;">
                                <?php echo $ip_whitelisted ? esc_html__('12h erneuern', 'vgt-sentinel') : esc_html__('12h Omni-Whitelist aktivieren', 'vgt-sentinel'); ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (!empty($active_ip_leases)): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                            <?php wp_nonce_field('vis_throneguard_revoke_lease'); ?>
                            <input type="hidden" name="action" value="vis_throneguard_revoke_lease">
                            <button type="submit" class="tg-btn-secondary" style="color:#f87171; border-color:rgba(239,68,68,0.35); padding:9px 14px;">
                                <?php esc_html_e('Aktive 12h-Whitelist sofort entfernen', 'vgt-sentinel'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- AUDIT TELEMETRY STREAM (EVENT HORIZON) -->
    <div class="tg-terminal">
        <div class="tg-terminal-header">
            <div class="tg-terminal-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <?php esc_html_e('THRONEGUARD // AUDIT STREAM & EVENT HORIZON', 'vgt-sentinel'); ?>
            </div>
            
            <div class="tg-filter-bar">
                <button type="button" class="tg-filter-btn active" data-filter="all"><?php esc_html_e('ALLE', 'vgt-sentinel'); ?> (<?php echo count($logs); ?>)</button>
                <button type="button" class="tg-filter-btn" data-filter="critical" style="color:#f87171;"><?php esc_html_e('KRITISCH', 'vgt-sentinel'); ?></button>
                <button type="button" class="tg-filter-btn" data-filter="warning" style="color:#fbbf24;"><?php esc_html_e('WARNUNGEN', 'vgt-sentinel'); ?></button>
                <button type="button" class="tg-filter-btn" data-filter="success" style="color:#6ee7b7;"><?php esc_html_e('ERFOLG', 'vgt-sentinel'); ?></button>
                <button type="button" class="tg-filter-btn" data-filter="info" style="color:#38bdf8;"><?php esc_html_e('INFO', 'vgt-sentinel'); ?></button>
                
                <input type="text" id="tg-log-search" placeholder="<?php esc_attr_e('Suche in Events...', 'vgt-sentinel'); ?>" style="background:rgba(15,23,42,0.9); border:1px solid rgba(148,163,184,0.2); border-radius:6px; color:#fff; padding:4px 10px; font-size:11px; font-family:monospace; outline:none;">
                
                <button type="button" class="tg-btn-secondary" id="tg-clear-logs-btn" style="color:#ef4444; border-color:rgba(239,68,68,0.3);">
                    <?php esc_html_e('Logs leeren', 'vgt-sentinel'); ?>
                </button>
            </div>
        </div>

        <div class="tg-log-stream" id="tg-log-stream">
            <?php if (empty($logs)): ?>
                <div style="text-align:center; padding:32px; color:#64748b;">
                    <?php esc_html_e('Keine Audit-Einträge vorhanden.', 'vgt-sentinel'); ?>
                </div>
            <?php else: ?>
                <?php foreach ($logs as $log): 
                    $severity = sanitize_key($log['severity'] ?? 'info');
                    $action = esc_html($log['action'] ?? 'ACTION');
                    $msg = esc_html($log['message'] ?? '');
                    $ip = esc_html($log['ip'] ?? '127.0.0.1');
                    $user = esc_html($log['user'] ?? 'SYSTEM');
                    $time = esc_html($log['timestamp'] ?? '');
                ?>
                    <div class="tg-log-row severity-<?php echo esc_attr($severity); ?>" data-severity="<?php echo esc_attr($severity); ?>">
                        <div class="tg-log-main">
                            <div class="tg-log-meta">
                                <span class="tg-log-action">[<?php echo esc_html($action); ?>]</span>
                                <span class="tg-log-user">@<?php echo esc_html($user); ?></span>
                                <span class="tg-log-ip"><?php echo esc_html($ip); ?></span>
                            </div>
                            <div class="tg-log-msg"><?php echo esc_html($msg); ?></div>
                        </div>
                        <div class="tg-log-time"><?php echo esc_html($time); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- INTERACTIVE SCRIPTS -->
