<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: CHRONOS
 * MODULE: AUTONOMOUS KERNEL SCHEDULER
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. LOGIK CORE (STRICT 1:1)
// =========================================================================================
$opt = get_option('vis_config', []);
$is_enabled = !isset($opt['chronos_enabled']) || !empty($opt['chronos_enabled']);
$interval = $opt['chronos_interval'] ?? 'vis_hourly';
$email_to = $opt['chronos_email_to'] ?? get_option('admin_email');
$email_subj = $opt['chronos_email_subject'] ?? '[GEDEFENSE WP] Security Alert: System Integrity Breach Detected';
$email_body = $opt['chronos_email_body'] ?? "GEDEFENSE WP OMEGA REPORT\n=========================\nTimestamp: {TIMESTAMP} UTC\nSystem Status: {STATUS}\n\nIdentified Core/File Modifications: {CHANGES}\nAction Required: Access VGT Dashboard -> Scanner Module immediately.\n";

$next_run = wp_next_scheduled('vis_periodic_scan_event');
$next_run_text = $next_run ? gmdate('Y-m-d H:i:s', $next_run) . ' UTC' : __('Offline / Not Scheduled', 'vgt-sentinel');
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $chronos_css_path = __DIR__ . '/chronos/style.css';
    if (is_readable($chronos_css_path)) {
        echo file_get_contents($chronos_css_path);
    }
    ?>
</style>

<div class="vgt-apex-ui">
    
    <div class="vgt-hero-header">
        <div class="vgt-hero-icon">
            <svg class="vgt-icon" viewBox="0 0 24 24" style="width: 28px; height: 28px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <div>
            <h2 class="vgt-hero-title">
                <?php esc_html_e('CHRONOS', 'vgt-sentinel'); ?> 
                <span class="vgt-badge vgt-badge-active"><?php esc_html_e('AUTONOMOUS SCHEDULER', 'vgt-sentinel'); ?></span>
            </h2>
            <p class="vgt-hero-desc"><?php esc_html_e('Orchestriert den OMEGA Scanner-Kernel im Hintergrund. Führt ressourcenschonende, zeitgesteuerte Deep-Scans der Dateisystem-Integrität durch und informiert dich über Modifikationen.', 'vgt-sentinel'); ?></p>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('vis_save_config'); ?>
        <input type="hidden" name="vis_save_config" value="1">
        <input type="hidden" name="vis_context" value="chronos">

        <div class="vgt-grid-2">
            <!-- LEFT COLUMN: SCHEDULING -->
            <div class="vgt-panel">
                <div class="vgt-panel-glow"></div>
                <div class="vgt-panel-header">
                    <h3 class="vgt-panel-title">
                        <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"></path><polyline points="21 3 21 8 16 8"></polyline></svg>
                        <?php esc_html_e('Temporal Engine', 'vgt-sentinel'); ?>
                    </h3>
                </div>
                
                <div class="vgt-panel-body">
                    
                    <div class="vgt-setting-row">
                        <div class="vgt-setting-info">
                            <div class="vgt-setting-title"><?php esc_html_e('Auto-Scan aktivieren', 'vgt-sentinel'); ?></div>
                            <div class="vgt-setting-desc"><?php esc_html_e('Aktiviert den Hintergrund-Daemon.', 'vgt-sentinel'); ?></div>
                        </div>
                        <label class="vgt-switch">
                            <input type="checkbox" name="vis_config[chronos_enabled]" value="1" <?php checked($is_enabled, true); ?>>
                            <span class="vgt-slider"></span>
                        </label>
                    </div>

                    <div class="vgt-form-group" style="margin-top: 24px;">
                        <label class="vgt-label"><?php esc_html_e('Scan Intervall', 'vgt-sentinel'); ?></label>
                        <select name="vis_config[chronos_interval]" class="vgt-select">
                            <option value="vis_15m" <?php selected($interval, 'vis_15m'); ?>><?php esc_html_e('Aggressiv (Alle 15 Minuten)', 'vgt-sentinel'); ?></option>
                            <option value="vis_30m" <?php selected($interval, 'vis_30m'); ?>><?php esc_html_e('Hoch (Alle 30 Minuten)', 'vgt-sentinel'); ?></option>
                            <option value="vis_hourly" <?php selected($interval, 'vis_hourly'); ?>><?php esc_html_e('Standard (Stündlich)', 'vgt-sentinel'); ?></option>
                            <option value="vis_twicedaily" <?php selected($interval, 'vis_twicedaily'); ?>><?php esc_html_e('Ausbalanciert (Alle 12 Stunden)', 'vgt-sentinel'); ?></option>
                            <option value="vis_daily" <?php selected($interval, 'vis_daily'); ?>><?php esc_html_e('Ökonomisch (1x Täglich)', 'vgt-sentinel'); ?></option>
                        </select>
                        <div class="vgt-help"><?php esc_html_e('Legt fest, wie oft das gesamte Dateisystem (Core, Plugins, Themes) verifiziert wird.', 'vgt-sentinel'); ?></div>
                    </div>

                    <div style="background: rgba(20, 184, 166, 0.05); border: 1px solid rgba(20, 184, 166, 0.2); border-radius: 8px; padding: 16px; margin-top: 24px;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--vgt-brand); margin-bottom: 6px; letter-spacing: 1px; text-transform: uppercase;">
                            <?php esc_html_e('Next Scheduled Ignition', 'vgt-sentinel'); ?>
                        </div>
                        <div style="font-family: 'Fira Code', monospace; font-size: 14px; color: #fff;">
                            <?php echo esc_html($next_run_text); ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN: ALERTING -->
            <div class="vgt-panel">
                <div class="vgt-panel-glow"></div>
                <div class="vgt-panel-header">
                    <h3 class="vgt-panel-title">
                        <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <?php esc_html_e('Alerting Matrix', 'vgt-sentinel'); ?>
                    </h3>
                </div>
                
                <div class="vgt-panel-body">
                    
                    <div class="vgt-form-group">
                        <label class="vgt-label"><?php esc_html_e('Empfänger E-Mail', 'vgt-sentinel'); ?></label>
                        <input type="email" name="vis_config[chronos_email_to]" value="<?php echo esc_attr($email_to); ?>" class="vgt-input">
                    </div>

                    <div class="vgt-form-group">
                        <label class="vgt-label"><?php esc_html_e('E-Mail Betreff', 'vgt-sentinel'); ?></label>
                        <input type="text" name="vis_config[chronos_email_subject]" value="<?php echo esc_attr($email_subj); ?>" class="vgt-input">
                    </div>

                    <div class="vgt-form-group">
                        <label class="vgt-label"><?php esc_html_e('E-Mail Template', 'vgt-sentinel'); ?></label>
                        <textarea name="vis_config[chronos_email_body]" class="vgt-textarea"><?php echo esc_textarea($email_body); ?></textarea>
                        <div class="vgt-help">
                            <?php 
                            printf(
                                esc_html__('Verfügbare Variablen: %s, %s, %s', 'vgt-sentinel'),
                                '<code style="color:var(--vgt-brand);">{TIMESTAMP}</code>',
                                '<code style="color:var(--vgt-brand);">{STATUS}</code>',
                                '<code style="color:var(--vgt-brand);">{CHANGES}</code>'
                            );
                            ?>
                        </div>
                    </div>

                </div>
            </div>

        </div>
        
        <button type="submit" class="vgt-btn-primary" style="margin-top: 24px;">
            <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <?php esc_html_e('Chronos Timing & Alerts Speichern', 'vgt-sentinel'); ?>
        </button>

    </form>
</div>
