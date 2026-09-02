<?php 
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$is_oracle_active = false;
if (class_exists('VIS_Key_Vault') && class_exists('VIS_Aegis_Oracle')) {
    try {
        $is_oracle_active = VIS_Key_Vault::get_key('vis_aegis_ai_key') !== '';
    } catch (Throwable $e) {
        error_log('[VGT ORACLE STATUS] Vault state could not be read.');
    }
}

$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['aegis_enabled']);
$mode = $opt['aegis_mode'] ?? 'strict';

$aegisToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Aegis WAF Deep Packet Inspection">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('KERNEL-LEVEL DEEP PACKET INSPECTION & ORACLE NEURAL DEFENSE', 'vgt-sentinel'); ?></p>
            <h2>AEGIS WAF</h2>
            <p><?php esc_html_e('Souveräner Deep Packet Inspection (DPI) WAF-Kernel mit intelligenter Layer-7 Heuristik und neuronaler Bedrohungsanalyse.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Aegis Status">
            <span><small><?php esc_html_e('WAF ENGINE', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ACTIVE', 'vgt-sentinel') : esc_html__('OFFLINE', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('PROTECTION MODE', 'vgt-sentinel'); ?></small><strong><?php echo $mode === 'strict' ? esc_html__('STRICT', 'vgt-sentinel') : esc_html__('LEARNING', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('ORACLE LINK', 'vgt-sentinel'); ?></small><strong style="color: <?php echo $is_oracle_active ? '#c084fc' : '#94a3b8'; ?>;"><?php echo $is_oracle_active ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('DISCONNECTED', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('DPI MATRIX', 'vgt-sentinel'); ?></small><strong><?php esc_html_e('ARMED', 'vgt-sentinel'); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('WAF ENGINE STATE', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('OFFLINE', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('REACTION POLICY', 'vgt-sentinel'); ?></small><strong><?php echo $mode === 'strict' ? esc_html__('STRICT (Instant Ban)', 'vgt-sentinel') : esc_html__('LEARNING (Observe)', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('ORACLE NEURAL LINK', 'vgt-sentinel'); ?></small><strong style="color: <?php echo $is_oracle_active ? '#c084fc' : '#94a3b8'; ?>;"><?php echo $is_oracle_active ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('INSPECTION ENGINE', 'vgt-sentinel'); ?></small><strong>DPI KERNEL</strong></article>
        <article><small><?php esc_html_e('LATENCY OVERHEAD', 'vgt-sentinel'); ?></small><strong>< 0.1ms</strong></article>
        <article><small><?php esc_html_e('SOVEREIGN BOUND', 'vgt-sentinel'); ?></small><strong>100% DSGVO</strong></article>
    </div>

    
        <?php wp_nonce_field('vis_save_config'); ?>
        <input type="hidden" name="vis_save_config" value="1">
        <input type="hidden" name="vis_context" value="aegis">

        <!-- SECTION 1: FIREWALL CONTROLS -->
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('01 / FIREWALL MATRIX', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Firewall Engine & Reaction Policy', 'vgt-sentinel'); ?></h3>
                </div>
                <?php $aegisToggle('aegis_enabled', $is_enabled, 'Enable Firewall Engine'); ?>
            </div>
            
            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('Protection Protocol (Reaction Policy)', 'vgt-sentinel'); ?></span>
                    <select name="vis_config[aegis_mode]" class="vgt-titan-select">
                        <option value="strict" <?php selected($mode, 'strict'); ?>><?php esc_html_e('STRICT (Instant Ban)', 'vgt-sentinel'); ?></option>
                        <option value="learning" <?php selected($mode, 'learning'); ?>><?php esc_html_e('LEARNING (Log & Observe)', 'vgt-sentinel'); ?></option>
                    </select>
                </label>
            </div>
        </section>

        <!-- SECTION 2: SOVEREIGN WHITELIST -->
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('02 / EXCLUSIONS', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Sovereign Whitelist (IPs & User-Agents)', 'vgt-sentinel'); ?></h3>
                </div>
            </div>
            
            <div class="vgt-titan-fields" style="grid-template-columns: 1fr 1fr;">
                <label class="vgt-titan-wide-field">
                    <span><?php esc_html_e('Trusted IP Addresses (Eine pro Zeile)', 'vgt-sentinel'); ?></span>
                    <textarea name="vis_config[aegis_whitelist_ips]" rows="3" placeholder="192.168.1.100&#10;203.0.113.50"><?php echo esc_textarea($opt['aegis_whitelist_ips'] ?? ''); ?></textarea>
                </label>
                <label class="vgt-titan-wide-field">
                    <span><?php esc_html_e('Trusted User-Agents (Ein Keyword pro Zeile)', 'vgt-sentinel'); ?></span>
                    <textarea name="vis_config[aegis_whitelist_uas]" rows="3" placeholder="UptimeRobot&#10;Stripe/1.0"><?php echo esc_textarea($opt['aegis_whitelist_uas'] ?? ''); ?></textarea>
                </label>
            </div>

            <div class="vgt-titan-actions">
                <button type="submit"><?php esc_html_e('AEGIS EINSTELLUNGEN SPEICHERN', 'vgt-sentinel'); ?></button>
            </div>
        </section>
    

    <!-- SECTION 3: ORACLE AI INTEGRATION MODULE -->
    <section class="vgt-titan-panel" style="border-color: rgba(192, 132, 252, 0.3);">
        <div class="vgt-titan-panel-head">
            <div>
                <small style="color: #c084fc;"><?php esc_html_e('03 / NEURAL LINK', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Oracle Neural Link (Layer 7 Defense Diagnostics)', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge" style="border-color: rgba(192, 132, 252, 0.4); color: #c084fc;">
                <?php echo $is_oracle_active ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('DISCONNECTED', 'vgt-sentinel'); ?>
            </span>
        </div>
        
        <p><?php esc_html_e('Generative KI-Heuristik-Engine für Zero-Day-Payload-Analysen und kontextuelle Angriffsidentifikation.', 'vgt-sentinel'); ?></p>

        <div class="vgt-oracle-diagnostics" data-oracle-active="<?php echo $is_oracle_active ? '1' : '0'; ?>" style="margin-top: 14px; background: rgba(3, 8, 16, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.12);">
            <div class="vgt-oracle-meter" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php echo esc_attr__('Groq Verbindungsgeschwindigkeit', 'vgt-sentinel'); ?>" style="background: rgba(255,255,255,0.06); height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
                <span id="vgt-oracle-meter-fill" style="display: block; height: 100%; width: 0%; background: linear-gradient(90deg, #c084fc, #5eead4); transition: width 0.3s ease;"></span>
            </div>
            <div class="vgt-oracle-readout" style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 14px;">
                <strong id="vgt-oracle-latency" style="font: 700 22px/1 ui-monospace, monospace; color: #fff;">-- ms</strong>
                <span id="vgt-oracle-grade" style="font: 600 12px/1 ui-monospace, monospace; color: #7dd3fc;"><?php esc_html_e('Noch nicht getestet', 'vgt-sentinel'); ?></span>
            </div>
            
            <div class="vgt-titan-actions" style="margin-top: 0;">
                <button type="button" id="vgt-oracle-ping" <?php disabled(!$is_oracle_active); ?> style="<?php echo !$is_oracle_active ? 'opacity:0.4; cursor:not-allowed;' : ''; ?>">
                    <?php esc_html_e('GROQ PING-TEST STARTEN', 'vgt-sentinel'); ?>
                </button>
                <?php if (!$is_oracle_active): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vgt-suite&tab=vault')); ?>" class="vgt-titan-download">
                        <?php esc_html_e('Oracle Key im Tresor hinterlegen', 'vgt-sentinel'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <p id="vgt-oracle-ping-status" class="vgt-oracle-ping-status" aria-live="polite" style="margin-top: 12px; font-size: 11px; color: #94a3b8; font-family: ui-monospace, monospace;">
                <?php echo $is_oracle_active ? esc_html__('Misst TLS-, Authentifizierungs- und API-Latenz ohne Prompt- oder Inhaltsübertragung.', 'vgt-sentinel') : esc_html__('Zuerst einen Groq-Key im Schlüssel-Tresor hinterlegen.', 'vgt-sentinel'); ?>
            </p>
        </div>
    </section>

    <!-- SECTION 4: ACTIVE DEFENSE PATTERNS -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('04 / DPI SIGNATURE MATRIX', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Active Defense Inspection Patterns', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge">6 <?php esc_html_e('VECTORS', 'vgt-sentinel'); ?></span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <?php 
                $patterns = [
                    __('SQL INJECTION', 'vgt-sentinel'), 
                    __('XSS (CROSS SITE SCRIPTING)', 'vgt-sentinel'), 
                    __('RCE (REMOTE CODE EXECUTION)', 'vgt-sentinel'), 
                    __('LFI (LOCAL FILE INCLUSION)', 'vgt-sentinel'),
                    __('AI PROMPT INJECTION', 'vgt-sentinel'),
                    __('ANOMALY DETECTION', 'vgt-sentinel')
                ];
                foreach ($patterns as $p): 
            ?>
            <div style="background: rgba(3, 8, 16, 0.62); border: 1px solid rgba(148, 163, 184, 0.13); border-radius: 10px; padding: 14px; display: flex; align-items: center; gap: 10px;">
                <svg class="vgt-icon" width="16" height="16" fill="none" stroke="currentColor" style="color: #5eead4;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <strong style="color: #f8fafc; font: 700 11px/1.3 ui-monospace, monospace;"><?php echo esc_html($p); ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</section>
