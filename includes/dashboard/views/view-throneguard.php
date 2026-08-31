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

$total_available_count = 0;
foreach ($available_caps as $grp) {
    $total_available_count += count($grp['caps'] ?? []);
}
$active_restricted_count = count($restricted_caps);

$claimed = isset($_GET['claimed']);
$updated = isset($_GET['updated']);
$throne_error = isset($_GET['throne_error']) ? sanitize_key($_GET['throne_error']) : '';
?>

<!-- =========================================================================================
     THRONEGUARD CYBERPUNK APEX STYLES (Zero Dependencies)
     ========================================================================================= -->
<style>
    .tg-view-wrapper {
        --tg-gold: #d4af37;
        --tg-gold-glow: rgba(212, 175, 55, 0.35);
        --tg-cyan: #00f0ff;
        --tg-cyan-glow: rgba(0, 240, 255, 0.3);
        --tg-purple: #a855f7;
        --tg-purple-glow: rgba(168, 85, 247, 0.3);
        --tg-emerald: #10b981;
        --tg-crimson: #ef4444;
        --tg-bg-card: rgba(15, 23, 42, 0.75);
        --tg-border: rgba(148, 163, 184, 0.15);
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .tg-hero-banner {
        background: linear-gradient(135deg, rgba(168, 85, 247, 0.12) 0%, rgba(0, 240, 255, 0.08) 50%, rgba(15, 23, 42, 0.6) 100%);
        border: 1px solid var(--tg-purple);
        border-radius: 14px;
        padding: 24px 28px;
        margin-bottom: 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        box-shadow: 0 0 30px rgba(168, 85, 247, 0.15);
        backdrop-filter: blur(12px);
    }

    .tg-hero-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .tg-crown-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        background: rgba(168, 85, 247, 0.15);
        border: 1px solid var(--tg-purple);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--tg-gold);
        box-shadow: 0 0 20px var(--tg-gold-glow);
        flex-shrink: 0;
    }

    .tg-hero-title {
        margin: 0 0 6px 0;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: 1.5px;
        color: #fff;
        text-transform: uppercase;
    }
    .tg-hero-title span { color: var(--tg-gold); }

    .tg-hero-desc {
        margin: 0;
        font-size: 13px;
        color: #94a3b8;
        line-height: 1.5;
        max-width: 780px;
    }

    /* TELEMETRY METRICS GRID */
    .tg-metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .tg-metric-card {
        background: var(--tg-bg-card);
        border: 1px solid var(--tg-border);
        border-radius: 12px;
        padding: 20px;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(8px);
        transition: all 0.25s ease;
    }
    .tg-metric-card:hover {
        border-color: rgba(0, 240, 255, 0.4);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    }
    .tg-metric-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--card-accent, var(--tg-cyan)), transparent);
    }

    .tg-metric-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #64748b;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .tg-metric-val {
        font-size: 20px;
        font-weight: 800;
        color: #fff;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: monospace;
    }

    .tg-metric-sub {
        font-size: 11px;
        color: #94a3b8;
    }

    .tg-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .tg-status-dot.active { background: #10b981; box-shadow: 0 0 10px #10b981; }
    .tg-status-dot.inactive { background: #64748b; }
    .tg-status-dot.warning { background: #f59e0b; box-shadow: 0 0 10px #f59e0b; }
    .tg-status-dot.critical { background: #ef4444; box-shadow: 0 0 10px #ef4444; }

    /* LAYOUT SPLIT */
    .tg-layout-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 28px;
    }
    @media (max-width: 1100px) {
        .tg-layout-grid { grid-template-columns: 1fr; }
    }

    .tg-panel {
        background: var(--tg-bg-card);
        border: 1px solid var(--tg-border);
        border-radius: 14px;
        padding: 26px;
        backdrop-filter: blur(10px);
        position: relative;
    }

    .tg-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--tg-border);
    }
    .tg-panel-title {
        font-size: 15px;
        font-weight: 800;
        letter-spacing: 1px;
        color: #fff;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* FORM ELEMENTS */
    .tg-form-group {
        margin-bottom: 18px;
    }
    .tg-form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #cbd5e1;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    .tg-input-wrap {
        position: relative;
    }
    .tg-input {
        width: 100%;
        background: rgba(2, 6, 23, 0.7);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 8px;
        padding: 12px 14px;
        color: #fff;
        font-size: 14px;
        outline: none;
        box-sizing: border-box;
        transition: all 0.2s;
    }
    .tg-input:focus {
        border-color: var(--tg-cyan);
        box-shadow: 0 0 15px rgba(0, 240, 255, 0.2);
    }

    .tg-switch-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(2, 6, 23, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 14px;
        transition: border-color 0.2s;
    }
    .tg-switch-row:hover { border-color: rgba(148, 163, 184, 0.2); }
    .tg-switch-info h4 { margin: 0 0 4px 0; font-size: 13px; font-weight: 700; color: #f8fafc; }
    .tg-switch-info p { margin: 0; font-size: 11px; color: #94a3b8; line-height: 1.4; }

    /* NATIVE VGT SWITCH */
    .tg-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }
    .tg-switch input { opacity: 0; width: 0; height: 0; }
    .tg-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: #334155;
        transition: .3s;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .tg-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background: #fff;
        transition: .3s;
        border-radius: 50%;
    }
    .tg-switch input:checked + .tg-slider {
        background: linear-gradient(135deg, #a855f7 0%, #00f0ff 100%);
        box-shadow: 0 0 12px rgba(0, 240, 255, 0.4);
    }
    .tg-switch input:checked + .tg-slider:before { transform: translateX(20px); }

    /* CAPABILITY MATRIX GRID */
    .tg-matrix-container {
        margin-top: 14px;
    }
    .tg-category-block {
        background: rgba(2, 6, 23, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 12px;
        padding: 18px;
        margin-bottom: 16px;
    }
    .tg-cat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .tg-cat-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--tg-cyan);
        letter-spacing: 0.8px;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tg-cap-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border-radius: 8px;
        margin-bottom: 6px;
        background: rgba(15, 23, 42, 0.4);
        border: 1px solid transparent;
        transition: all 0.2s;
    }
    .tg-cap-item:hover {
        background: rgba(15, 23, 42, 0.8);
        border-color: rgba(148, 163, 184, 0.15);
    }
    .tg-cap-item.is-restricted {
        border-left: 3px solid var(--tg-purple);
    }
    .tg-cap-meta {
        display: flex;
        flex-direction: column;
        gap: 2px;
        max-width: 75%;
    }
    .tg-cap-title-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tg-cap-name {
        font-size: 13px;
        font-weight: 700;
        color: #f1f5f9;
    }
    .tg-cap-code {
        font-family: monospace;
        font-size: 10px;
        color: #64748b;
        background: rgba(0, 0, 0, 0.3);
        padding: 2px 6px;
        border-radius: 4px;
    }
    .tg-cap-desc {
        font-size: 11px;
        color: #94a3b8;
        line-height: 1.3;
    }

    .tg-risk-badge {
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: monospace;
    }
    .tg-risk-badge.critical { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
    .tg-risk-badge.high { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
    .tg-risk-badge.medium { background: rgba(6, 182, 212, 0.2); color: #38bdf8; border: 1px solid rgba(6, 182, 212, 0.4); }

    /* ACTION BUTTONS */
    .tg-btn-primary {
        background: linear-gradient(135deg, #a855f7 0%, #4f46e5 100%);
        border: 1px solid rgba(168, 85, 247, 0.5);
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        box-shadow: 0 0 20px rgba(168, 85, 247, 0.3);
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .tg-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 0 30px rgba(168, 85, 247, 0.5);
    }

    .tg-btn-secondary {
        background: rgba(15, 23, 42, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.2);
        color: #cbd5e1;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .tg-btn-secondary:hover {
        background: rgba(30, 41, 59, 1);
        color: #fff;
        border-color: var(--tg-cyan);
    }

    /* TELEMETRY LOG VIEWER (EVENT HORIZON) */
    .tg-terminal {
        background: #020617;
        border: 1px solid rgba(168, 85, 247, 0.3);
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 0 35px rgba(0, 0, 0, 0.8);
        position: relative;
    }
    .tg-terminal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .tg-terminal-title {
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 1.5px;
        color: var(--tg-gold);
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: monospace;
    }
    .tg-filter-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .tg-filter-btn {
        background: rgba(15, 23, 42, 0.9);
        border: 1px solid rgba(148, 163, 184, 0.15);
        color: #94a3b8;
        font-size: 10px;
        font-family: monospace;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .tg-filter-btn.active, .tg-filter-btn:hover {
        background: rgba(168, 85, 247, 0.2);
        color: #fff;
        border-color: var(--tg-purple);
    }

    .tg-log-stream {
        max-height: 420px;
        overflow-y: auto;
        padding-right: 6px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        font-family: monospace;
        font-size: 12px;
    }
    .tg-log-stream::-webkit-scrollbar { width: 4px; }
    .tg-log-stream::-webkit-scrollbar-thumb { background: rgba(168, 85, 247, 0.3); border-radius: 4px; }

    .tg-log-row {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-left: 3px solid #64748b;
        padding: 10px 14px;
        border-radius: 6px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        transition: all 0.15s;
    }
    .tg-log-row:hover {
        background: rgba(15, 23, 42, 0.9);
        border-color: rgba(255, 255, 255, 0.1);
    }
    .tg-log-row.severity-critical { border-left-color: #ef4444; }
    .tg-log-row.severity-warning { border-left-color: #f59e0b; }
    .tg-log-row.severity-success { border-left-color: #10b981; }
    .tg-log-row.severity-info { border-left-color: #00f0ff; }

    .tg-log-main {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex-grow: 1;
    }
    .tg-log-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        color: #64748b;
    }
    .tg-log-action {
        font-weight: 800;
        color: #e2e8f0;
    }
    .tg-log-user {
        color: var(--tg-cyan);
        background: rgba(0, 240, 255, 0.08);
        padding: 1px 6px;
        border-radius: 4px;
    }
    .tg-log-ip {
        color: #94a3b8;
    }
    .tg-log-msg {
        color: #cbd5e1;
        line-height: 1.4;
    }
    .tg-log-time {
        font-size: 10px;
        color: #475569;
        white-space: nowrap;
    }

    .tg-alert {
        padding: 14px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .tg-alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; }
    .tg-alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
</style>

<div class="tg-view-wrapper">
    
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
                <?php esc_html_e('Registrierte Master-Konten:', 'vgt-sentinel'); ?> <strong style="color:#fff"><?php echo $master_count; ?></strong>
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

        <!-- 4. Zero-Trust Lockdown -->
        <div class="tg-metric-card" style="--card-accent: #10b981;">
            <div class="tg-metric-label">
                <span><?php esc_html_e('Zero-Trust Lockdown', 'vgt-sentinel'); ?></span>
                <span class="tg-status-dot <?php echo $lock_enabled ? 'active' : 'inactive'; ?>"></span>
            </div>
            <div class="tg-metric-val" style="color: <?php echo $lock_enabled ? '#10b981' : '#94a3b8'; ?>">
                <?php echo $lock_enabled ? esc_html__('SESSION GUARD (2h)', 'vgt-sentinel') : esc_html__('DEAKTIVIERT', 'vgt-sentinel'); ?>
            </div>
            <div class="tg-metric-sub">
                <?php esc_html_e('Anti-Hijack:', 'vgt-sentinel'); ?> <strong style="color:#fff"><?php echo $lock_enabled ? esc_html__('Fingerprint & Cookie Lock', 'vgt-sentinel') : esc_html__('Standard Session', 'vgt-sentinel'); ?></strong>
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
                            <button type="button" class="tg-btn-secondary" onclick="tgSelectAllCaps(true)"><?php esc_html_e('Alle sperren', 'vgt-sentinel'); ?></button>
                            <button type="button" class="tg-btn-secondary" onclick="tgSelectAllCaps(false)"><?php esc_html_e('Alle erlauben', 'vgt-sentinel'); ?></button>
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
                                <span class="tg-log-action">[<?php echo $action; ?>]</span>
                                <span class="tg-log-user">@<?php echo $user; ?></span>
                                <span class="tg-log-ip"><?php echo $ip; ?></span>
                            </div>
                            <div class="tg-log-msg"><?php echo $msg; ?></div>
                        </div>
                        <div class="tg-log-time"><?php echo $time; ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- INTERACTIVE SCRIPTS -->
<script>
function tgSelectAllCaps(select) {
    document.querySelectorAll('.tg-cap-checkbox').forEach(function(cb) {
        cb.checked = select;
        var parent = cb.closest('.tg-cap-item');
        if (parent) {
            if (select) parent.classList.add('is-restricted');
            else parent.classList.remove('is-restricted');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Dynamic cap item highlight
    document.querySelectorAll('.tg-cap-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var parent = this.closest('.tg-cap-item');
            if (parent) {
                if (this.checked) parent.classList.add('is-restricted');
                else parent.classList.remove('is-restricted');
            }
        });
    });

    // Log Filter
    var filterBtns = document.querySelectorAll('.tg-filter-btn');
    var logRows = document.querySelectorAll('.tg-log-row');
    var searchInput = document.getElementById('tg-log-search');

    function applyLogFilters() {
        var activeFilter = document.querySelector('.tg-filter-btn.active')?.getAttribute('data-filter') || 'all';
        var searchTerm = (searchInput?.value || '').toLowerCase().trim();

        logRows.forEach(function(row) {
            var rowSeverity = row.getAttribute('data-severity') || 'info';
            var rowText = row.innerText.toLowerCase();
            
            var matchesSeverity = (activeFilter === 'all' || rowSeverity === activeFilter);
            var matchesSearch = (searchTerm === '' || rowText.indexOf(searchTerm) !== -1);

            if (matchesSeverity && matchesSearch) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });
    }

    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterBtns.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            applyLogFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyLogFilters);
    }

    // Clear Logs AJAX
    var clearBtn = document.getElementById('tg-clear-logs-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (!confirm('<?php echo esc_js(__("Möchtest du das gesamte ThroneGuard Audit-Protokoll wirklich leeren?", "vgt-sentinel")); ?>')) return;
            
            clearBtn.disabled = true;
            clearBtn.innerText = '<?php echo esc_js(__("Leere...", "vgt-sentinel")); ?>';

            var data = new FormData();
            data.append('action', 'vis_throneguard_clear_logs');
            data.append('nonce', '<?php echo wp_create_nonce("vis_throneguard_action"); ?>');

            fetch(ajaxurl, {
                method: 'POST',
                body: data
            })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.success) {
                    var stream = document.getElementById('tg-log-stream');
                    if (stream) {
                        stream.innerHTML = '<div style="text-align:center; padding:32px; color:#64748b;"><?php echo esc_js(__("Audit-Protokoll wurde geleert.", "vgt-sentinel")); ?></div>';
                    }
                } else {
                    alert('<?php echo esc_js(__("Fehler beim Leeren der Logs.", "vgt-sentinel")); ?>');
                }
            })
            .catch(function(err) {
                alert('Netzwerkfehler: ' + err.message);
            })
            .finally(function() {
                clearBtn.disabled = false;
                clearBtn.innerText = '<?php echo esc_js(__("Logs leeren", "vgt-sentinel")); ?>';
            });
        });
    }
});
</script>
