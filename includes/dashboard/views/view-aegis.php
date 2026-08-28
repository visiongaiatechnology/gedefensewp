<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: AEGIS
 * MODULE: SECURITY KERNEL & ORACLE INTERFACE
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. LOGIK CORE (STRICT 1:1)
// =========================================================================================
$oracle_key = get_option('vis_aegis_ai_key', '');
$is_oracle_active = !empty($oracle_key) && class_exists('VIS_Aegis_Oracle');

$opt = get_option('vis_config', []);

$oracle_pulse = $is_oracle_active ? 'vgt-oracle-active' : 'vgt-is-standby';
$oracle_color = $is_oracle_active ? '#a855f7' : '#64748b';
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET LOADING
     ========================================================================================= -->
<style>
    <?php 
    $aegis_css_path = __DIR__ . '/aegis/style.css';
    if (is_readable($aegis_css_path)) {
        echo file_get_contents($aegis_css_path);
    }
    ?>
</style>

<div class="vgt-apex-ui">

    <!-- AEGIS FIREWALL MATRIX -->
    <div class="vgt-glass-panel" style="border-top: 3px solid var(--vgt-neon-blue);">
        <div class="vgt-module-header">
            <div class="vgt-module-title">
                <svg class="vgt-icon" style="color:var(--vgt-neon-blue); width:20px; height:20px;" viewBox="0 0 24 24">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <h2><?php esc_html_e('AEGIS FIREWALL MATRIX', 'vgt-sentinel'); ?></h2>
            </div>
        </div>
        
        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('ENABLE FIREWALL ENGINE', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Deep Packet Inspection für SQLi, XSS, RCE, und LFI Vektoren.', 'vgt-sentinel'); ?></p>
            </div>
            <label class="vgt-switch">
                <input type="checkbox" name="vis_config[aegis_enabled]" <?php checked(!empty($opt['aegis_enabled'])); ?>>
                <span class="vgt-slider"></span>
            </label>
        </div>

        <div class="vgt-setting-row">
            <div class="vgt-label-group">
                <strong><?php esc_html_e('PROTECTION PROTOCOL', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Definiert die Reaktions-Policy bei positiven Threat-Signaturen.', 'vgt-sentinel'); ?></p>
            </div>
            <div>
                <select name="vis_config[aegis_mode]" class="vgt-select">
                    <option value="strict" <?php selected(isset($opt['aegis_mode']) ? $opt['aegis_mode'] : '', 'strict'); ?>><?php esc_html_e('STRICT (Instant Ban)', 'vgt-sentinel'); ?></option>
                    <option value="learning" <?php selected(isset($opt['aegis_mode']) ? $opt['aegis_mode'] : '', 'learning'); ?>><?php esc_html_e('LEARNING (Log & Observe)', 'vgt-sentinel'); ?></option>
                </select>
            </div>
        </div>
    </div>

    <!-- SOVEREIGN WHITELIST -->
    <div class="vgt-glass-panel" style="border-top: 3px solid var(--vgt-neon-green);">
        <div class="vgt-module-header">
            <div class="vgt-module-title">
                <svg class="vgt-icon" style="color:var(--vgt-neon-green); width:20px; height:20px;" viewBox="0 0 24 24">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
                </svg>
                <h2><?php esc_html_e('SOVEREIGN WHITELIST', 'vgt-sentinel'); ?></h2>
            </div>
        </div>
        
        <div class="vgt-setting-row" style="flex-direction: column; align-items: flex-start; gap: 12px;">
            <div class="vgt-label-group" style="width: 100%;">
                <strong><?php esc_html_e('TRUSTED IP ADDRESSES', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Eine IP pro Zeile. Diese IPs umgehen den AEGIS-Kernel und das Oracle vollständig.', 'vgt-sentinel'); ?></p>
            </div>
            <textarea name="vis_config[aegis_whitelist_ips]" class="vgt-textarea" placeholder="192.168.1.100&#10;203.0.113.50" rows="3"><?php echo esc_textarea($opt['aegis_whitelist_ips'] ?? ''); ?></textarea>
        </div>

        <div class="vgt-setting-row" style="flex-direction: column; align-items: flex-start; gap: 12px;">
            <div class="vgt-label-group" style="width: 100%;">
                <strong><?php esc_html_e('TRUSTED USER-AGENTS', 'vgt-sentinel'); ?></strong>
                <p><?php esc_html_e('Ein Keyword pro Zeile (z.B. "UptimeRobot"). Warnung: UAs können leicht gespooft werden.', 'vgt-sentinel'); ?></p>
            </div>
            <textarea name="vis_config[aegis_whitelist_uas]" class="vgt-textarea" placeholder="UptimeRobot&#10;Stripe/1.0" rows="3"><?php echo esc_textarea($opt['aegis_whitelist_uas'] ?? ''); ?></textarea>
        </div>
    </div>

    <!-- ORACLE AI INTEGRATION MODULE -->
    <div class="vgt-glass-panel" style="border-left: 4px solid var(--vgt-neon-purple); background: linear-gradient(135deg, rgba(168,85,247,0.05) 0%, rgba(15,23,42,0.8) 100%);">
        <div class="vgt-setting-row" style="border:none;">
            <div>
                <h3 style="margin:0; color:var(--vgt-neon-purple); display:flex; align-items:center; gap:10px; font-size:16px; font-weight:700; letter-spacing:1px;">
                    <div style="background:rgba(168,85,247,0.1); padding:8px; border-radius:6px; border:1px solid rgba(168,85,247,0.2); display:flex;">
                        <svg class="vgt-icon" style="width:20px; height:20px;" viewBox="0 0 24 24">
                            <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.73-2.73 2.5 2.5 0 0 1-.44-4.96 2.5 2.5 0 0 1 .44-4.96 2.5 2.5 0 0 1 2.73-2.73A2.5 2.5 0 0 1 9.5 2Z"></path>
                            <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.73-2.73 2.5 2.5 0 0 0 .44-4.96 2.5 2.5 0 0 0-.44-4.96 2.5 2.5 0 0 0-2.73-2.73A2.5 2.5 0 0 0 14.5 2Z"></path>
                        </svg>
                    </div>
                    <?php esc_html_e('ORACLE NEURAL LINK', 'vgt-sentinel'); ?>
                </h3>
                <p style="margin:6px 0 0 46px; color:var(--vgt-text-dim); font-size:12px; font-family:monospace;">
                    <?php esc_html_e('Generative AI Heuristics Engine (Layer 7 Defense)', 'vgt-sentinel'); ?>
                </p>
            </div>
            
            <div class="<?php echo esc_attr($oracle_pulse); ?>" style="text-align:right;">
                <?php if ($is_oracle_active): ?>
                    <div class="vgt-badge vgt-badge-purple" style="font-size:11px; padding:6px 14px;">
                        <span class="vgt-status-pulse"></span>
                        <?php esc_html_e('SYSTEM ONLINE', 'vgt-sentinel'); ?>
                    </div>
                <?php else: ?>
                    <div class="vgt-badge" style="background:rgba(100,116,139,0.1); border-color:rgba(100,116,139,0.3); color:var(--vgt-text-muted); font-size:11px; padding:6px 14px;">
                        <span class="vgt-status-pulse"></span>
                        <?php esc_html_e('DISCONNECTED', 'vgt-sentinel'); ?>
                    </div>
                    <div style="margin-top:8px; font-size:10px; font-family:monospace;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=vgt-vault')); ?>" style="color:var(--vgt-text-dim); text-decoration:underline;"><?php esc_html_e('Configure Oracle Uplink', 'vgt-sentinel'); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ACTIVE PATTERNS MATRIX -->
    <div class="vgt-glass-panel">
        <div class="vgt-module-header" style="background: rgba(255,255,255,0.02);">
            <div class="vgt-module-title">
                <svg class="vgt-icon" style="color:var(--vgt-text-dim); width:18px; height:18px;" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <h2 style="font-size: 14px; color: var(--vgt-text-dim);"><?php esc_html_e('ACTIVE DEFENSE PATTERNS', 'vgt-sentinel'); ?></h2>
            </div>
        </div>
        
        <div style="padding: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <?php 
                $patterns = [
                    __('SQL INJECTION', 'vgt-sentinel'), 
                    __('XSS (CROSS SITE SCRIPTING)', 'vgt-sentinel'), 
                    __('RCE (REMOTE CODE EXECUTION)', 'vgt-sentinel'), 
                    __('LFI (LOCAL FILE INCLUSION)', 'vgt-sentinel')
                ];
                foreach ($patterns as $p): 
            ?>
            <div class="vgt-badge vgt-badge-active" style="padding:12px; font-size:11px;">
                <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <?php echo esc_html($p); ?>
            </div>
            <?php endforeach; ?>
            
            <div class="vgt-badge vgt-badge-purple" style="padding:12px; font-size:11px;">
                <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <?php esc_html_e('AI PROMPT INJECTION', 'vgt-sentinel'); ?>
            </div>
            <div class="vgt-badge vgt-badge-purple" style="padding:12px; font-size:11px;">
                <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <?php esc_html_e('ANOMALY DETECTION', 'vgt-sentinel'); ?>
            </div>
        </div>
    </div>

</div>
