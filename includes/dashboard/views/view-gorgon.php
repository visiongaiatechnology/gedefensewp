<?php
declare(strict_types=1);
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * STATUS: PLATIN VGT STATUS (Neural Grid Hardening)
 * MODULE: GORGON NEURAL GRID DASHBOARD V3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// --- REAL DATA ACQUISITION ---
global $wpdb;
$config = get_option('vis_config', []);
$gorgon_enabled = !empty($config['gorgon_enabled']);
$last_sync = get_option('vgt_gorgon_last_pull', 0);
$nodes = isset($config['gorgon_nodes']) && is_array($config['gorgon_nodes']) ? $config['gorgon_nodes'] : [];

// Real metrics
$total_vectors_sent = (int)get_option('vgt_gorgon_total_vectors', 0);
$total_bans_assimilated = (int)get_option('vgt_gorgon_total_bans', 0);
$nexus_url = $config['gorgon_nexus_url'] ?? 'https://next.visiongaia.de/api/v1/telemetry/sync';
$api_key   = $config['gorgon_api_key'] ?? '';
if ($api_key !== '' && class_exists('VIS_Vault')) {
    $api_key = VIS_Vault::decrypt((string)$api_key);
}
?>

<!-- VGT SUPREME FIX: State-Retention für Global Save -->
<input type="hidden" name="vis_config[gorgon_enabled]" id="vgt-gorgon-enabled-input" value="<?php echo $gorgon_enabled ? '1' : '0'; ?>">

<style>
    <?php 
    $gorgon_css_path = __DIR__ . '/gorgon/style.css';
    if (is_readable($gorgon_css_path)) {
        echo file_get_contents($gorgon_css_path);
    }
    ?>
</style>

<div class="vgt-gorgon-wrapper" id="vgt-gorgon-app" data-enabled="<?php echo $gorgon_enabled ? '1' : '0'; ?>" data-key="<?php echo !empty($api_key) ? '1' : '0'; ?>">

    <!-- DIAGNOSTIC HUD TOP BAR -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--vgt-cyan)" stroke-width="2" style="filter: drop-shadow(0 0 5px var(--vgt-cyan-glow))"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            <div>
                <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: 800; letter-spacing: -0.5px;"><?php esc_html_e('Swarm Intelligence Grid', 'vgt-sentinel'); ?></h3>
                <span style="font-size: 0.65rem; color: #8b8b9e; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;"><?php esc_html_e('Decentralized Threat Sharing Matrix', 'vgt-sentinel'); ?></span>
            </div>
        </div>
        <button type="button" class="vgt-btn vgt-btn-cyan" style="padding: 8px 16px; font-size: 0.75rem;" onclick="vgtSyncNow()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" id="vgt-sync-ico" style="margin-right: 4px;"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
            <?php esc_html_e('Sync Network Now', 'vgt-sentinel'); ?>
        </button>
    </div>

    <!-- TWO-COLUMN DASHBOARD GRID -->
    <div class="vgt-nexus-bridge" id="nexus-bridge-card">
        <div class="vgt-bridge-glow" id="nexus-bridge-glow"></div>
        
        <!-- Left: Node Configurations -->
        <div class="vgt-bridge-meta">
            <span style="color: var(--vgt-cyan); font-weight: 900; font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px; display: block;"><?php esc_html_e('SECURE CYBER BRIDGE', 'vgt-sentinel'); ?></span>
            <h2><?php esc_html_e('Gorgon Neural Link', 'vgt-sentinel'); ?></h2>
            <p style="margin-bottom: 20px; font-size: 0.85rem; color: #8b8b9e; line-height: 1.5;"><?php esc_html_e('Trage die Zugangsdaten deines privaten VGT Nexus Command Centers ein. Das System verwendet Post-Quantum-Handshakes und AES-256-GCM Verschlüsselung zur Übertragung der Ereignis-Vektoren.', 'vgt-sentinel'); ?></p>
            
            <div class="vgt-config-grid">
                <div class="vgt-field-wrap">
                    <label><?php esc_html_e('Nexus Sync Endpoint', 'vgt-sentinel'); ?></label>
                    <input type="text" id="vgt-nexus-endpoint" name="vis_config[gorgon_nexus_url]" value="<?php echo esc_attr($nexus_url); ?>" class="vgt-field-input" placeholder="https://next.visiongaia.de/api/v1/telemetry/sync">
                </div>
                <div class="vgt-field-wrap">
                    <label><?php esc_html_e('Neural Access Key', 'vgt-sentinel'); ?></label>
                    <input type="password" id="vgt-nexus-key" name="vis_config[gorgon_api_key]" value="<?php echo esc_attr($api_key); ?>" class="vgt-field-input" placeholder="vgt-nx-...">
                </div>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="button" class="vgt-btn vgt-btn-cyan" onclick="vgtSaveConfig()" id="btn-save-config"><?php esc_html_e('Update Config', 'vgt-sentinel'); ?></button>
                <button type="button" class="vgt-btn vgt-btn-ghost" onclick="checkNexusHealth(true)" id="btn-test-link" style="font-size: 0.75rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span id="btn-test-link-text"><?php esc_html_e('Ping Nexus', 'vgt-sentinel'); ?></span>
                </button>
            </div>
        </div>

        <!-- Right: Status Pill & Last Push -->
        <div class="vgt-status-indicator">
            <div class="vgt-pill pending" id="realtime-status-pill">
                <div class="dot"></div>
                <span id="realtime-status-text"><?php esc_html_e('INITIALIZING...', 'vgt-sentinel'); ?></span>
            </div>
            
            <div style="text-align: right; margin-top: 15px; background: rgba(0,0,0,0.3); padding: 12px 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.02);">
                <div style="font-size: 0.65rem; font-weight: 700; color: #8b8b9e; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;"><?php esc_html_e('Last Telemetry Push', 'vgt-sentinel'); ?></div>
                <div style="font-family: 'Share Tech Mono', monospace; font-size: 0.95rem; color: var(--vgt-cyan); text-shadow: 0 0 5px var(--vgt-cyan-glow);">
                    <?php echo $last_sync ? esc_html(wp_date('Y-m-d H:i:s', (int)$last_sync)) : esc_html__('NEVER_SYNCED', 'vgt-sentinel'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- METRICS HUB -->
    <div class="vgt-metrics-hub">
        <div class="vgt-metric-card" style="border-left: 3px solid var(--vgt-cyan);">
            <span class="vgt-metric-val"><?php echo esc_html(number_format_i18n($total_vectors_sent)); ?></span>
            <span class="vgt-metric-label"><?php esc_html_e('Vectors Transmitted', 'vgt-sentinel'); ?></span>
        </div>
        <div class="vgt-metric-card" style="border-left: 3px solid #3b82f6;">
            <span class="vgt-metric-val"><?php echo esc_html(number_format_i18n(count($nodes) + 2)); ?></span>
            <span class="vgt-metric-label"><?php esc_html_e('Active Local Feeds', 'vgt-sentinel'); ?></span>
        </div>
        <div class="vgt-metric-card" style="border-left: 3px solid var(--vgt-green);">
            <span class="vgt-metric-val status-green"><?php echo esc_html(number_format_i18n($total_bans_assimilated)); ?></span>
            <span class="vgt-metric-label status-green"><?php esc_html_e('Assimilated Bans', 'vgt-sentinel'); ?></span>
        </div>
    </div>

    <!-- TELEMETRY NODE REGISTRY -->
    <div class="vgt-section-header">
        <h4 style="font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 12px; margin-bottom: 18px; color: #fff;"><?php esc_html_e('Telemetry Node Registry', 'vgt-sentinel'); ?></h4>
    </div>

    <div class="vgt-nodes-grid">
        <!-- SYSTEM NODE 1: GEDEFENSE WP CORE -->
        <div class="vgt-node-card" style="border-color: rgba(0, 229, 255, 0.25); background: rgba(0, 229, 255, 0.01);">
            <div class="vgt-node-top">
                <span style="font-weight:700; font-size:0.95rem; color:#fff;"><?php esc_html_e('GeDefense WP Core', 'vgt-sentinel'); ?></span>
                <span style="font-size:0.6rem; font-weight:900; padding:4px 8px; border-radius:4px; background:rgba(0,229,255,0.1); color:var(--vgt-cyan);"><?php esc_html_e('KERNEL', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vgt-node-content">
                <code><?php echo esc_html($wpdb->prefix . 'vis_omega_logs'); ?></code>
                <div class="vgt-mapping-table">
                    <div class="vgt-map-cell"><span><?php esc_html_e('IP', 'vgt-sentinel'); ?></span><code>ip</code></div>
                    <div class="vgt-map-cell"><span><?php esc_html_e('TYPE', 'vgt-sentinel'); ?></span><code>type</code></div>
                    <div class="vgt-map-cell"><span><?php esc_html_e('TIME', 'vgt-sentinel'); ?></span><code>timestamp</code></div>
                </div>
            </div>
        </div>

        <!-- SYSTEM NODE 2: ORACLE ANOMALIES -->
        <div class="vgt-node-card" style="border-color: rgba(0, 255, 136, 0.25); background: rgba(0, 255, 136, 0.01);">
            <div class="vgt-node-top">
                <span style="font-weight:700; font-size:0.95rem; color:#fff;"><?php esc_html_e('Oracle Scanner', 'vgt-sentinel'); ?></span>
                <span style="font-size:0.6rem; font-weight:900; padding:4px 8px; border-radius:4px; background:rgba(0,255,136,0.1); color:var(--vgt-green);"><?php esc_html_e('SCANNER', 'vgt-sentinel'); ?></span>
            </div>
            <div class="vgt-node-content">
                <code><?php echo esc_html($wpdb->prefix . 'vis_oracle_patterns'); ?></code>
                <div class="vgt-mapping-table">
                    <div class="vgt-map-cell"><span><?php esc_html_e('IP', 'vgt-sentinel'); ?></span><code>ip</code></div>
                    <div class="vgt-map-cell"><span><?php esc_html_e('TYPE', 'vgt-sentinel'); ?></span><code>type</code></div>
                    <div class="vgt-map-cell"><span><?php esc_html_e('TIME', 'vgt-sentinel'); ?></span><code>timestamp</code></div>
                </div>
            </div>
        </div>

        <!-- DYNAMIC NODES -->
        <?php foreach($nodes as $id => $node): ?>
            <div class="vgt-node-card">
                <div class="vgt-node-top">
                    <span style="font-weight:700; font-size:0.95rem; color:#fff;"><?php echo esc_html($id); ?></span>
                    <button type="button" style="background:none; border:none; color:var(--vgt-red); cursor:pointer; font-size:0.65rem; font-weight:900; letter-spacing: 0.05em; text-transform: uppercase;" onclick="vgtDropNode('<?php echo esc_js($id); ?>')"><?php esc_html_e('DROP SOURCE', 'vgt-sentinel'); ?></button>
                </div>
                <div class="vgt-node-content">
                    <code><?php echo esc_html($node['table']); ?></code>
                    <div class="vgt-mapping-table">
                        <div class="vgt-map-cell"><span><?php esc_html_e('IP', 'vgt-sentinel'); ?></span><code><?php echo esc_html($node['ip_col']); ?></code></div>
                        <div class="vgt-map-cell"><span><?php esc_html_e('TYPE', 'vgt-sentinel'); ?></span><code><?php echo esc_html($node['type_col']); ?></code></div>
                        <div class="vgt-map-cell"><span><?php esc_html_e('TIME', 'vgt-sentinel'); ?></span><code><?php echo esc_html($node['time_col']); ?></code></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- ADD NODE PLACEHOLDER -->
        <div class="vgt-add-placeholder" onclick="vgtIntegrateNode()">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom: 5px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            <span style="font-size: 0.7rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;"><?php esc_html_e('Inject Telemetry Source', 'vgt-sentinel'); ?></span>
        </div>
    </div>

    <!-- RESTRICTED COVERS OVERLAY (Inactive link warning) -->
    <div class="vgt-restricted-overlay" id="vgt-overlay" style="display: <?php echo $gorgon_enabled ? 'none' : 'flex'; ?>;">
        <div class="vgt-restricted-msg">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="margin: 0 auto 15px; color: var(--vgt-red); filter: drop-shadow(0 0 5px var(--vgt-red-glow))"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <h3 style="margin: 0 0 10px 0; font-family: 'Outfit', sans-serif; font-weight: 800; color: #fff; font-size: 1.25rem;"><?php esc_html_e('Neural Link Offline', 'vgt-sentinel'); ?></h3>
            <p style="font-size: 0.85rem; color: #8b8b9e; line-height: 1.5; margin-bottom: 25px;"><?php esc_html_e('Gorgon ist inaktiv. Das globale Immunsystem benötigt die aktive Bridge zum VGT Nexus Backend, um Erkennungen und Banns zu teilen.', 'vgt-sentinel'); ?></p>
            <button type="button" id="btn-activate-gorgon" class="vgt-btn vgt-btn-cyan" style="width: 100%; justify-content: center;" onclick="vgtEnableGorgon()"><?php esc_html_e('Activate Gorgon Grid', 'vgt-sentinel'); ?></button>
        </div>
    </div>

    <!-- WIZARD DISCOVERY MODAL -->
    <div id="vgt-node-modal">
        <div class="vgt-modal-content">
            <h3 style="margin: 0 0 25px 0; font-family: 'Outfit', sans-serif; font-weight: 900; color: var(--vgt-cyan); letter-spacing: -0.5px; font-size: 1.2rem;"><?php esc_html_e('Source Discovery Wizard', 'vgt-sentinel'); ?></h3>
            
            <div class="vgt-field-wrap" style="margin-bottom:15px;">
                <label><?php esc_html_e('Unique Node ID', 'vgt-sentinel'); ?></label>
                <input type="text" id="wiz-id" class="vgt-field-input" placeholder="<?php echo esc_attr__('e.g. WOO_COMMERCE_LOGS', 'vgt-sentinel'); ?>">
            </div>
            
            <div class="vgt-field-wrap" style="margin-bottom:15px;">
                <label><?php esc_html_e('Database Table Target', 'vgt-sentinel'); ?></label>
                <input type="text" id="wiz-table" class="vgt-field-input" placeholder="wp_custom_plugin_logs">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                <div class="vgt-field-wrap">
                    <label><?php esc_html_e('IP Col', 'vgt-sentinel'); ?></label>
                    <input type="text" id="wiz-ip" class="vgt-field-input" placeholder="ip_address">
                </div>
                <div class="vgt-field-wrap">
                    <label><?php esc_html_e('Type Col', 'vgt-sentinel'); ?></label>
                    <input type="text" id="wiz-type" class="vgt-field-input" placeholder="event_type">
                </div>
                <div class="vgt-field-wrap">
                    <label><?php esc_html_e('Time Col', 'vgt-sentinel'); ?></label>
                    <input type="text" id="wiz-time" class="vgt-field-input" placeholder="created_at">
                </div>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="button" class="vgt-btn vgt-btn-ghost" style="flex: 1; justify-content: center;" onclick="vgtCloseModal()"><?php esc_html_e('Cancel', 'vgt-sentinel'); ?></button>
                <button type="button" class="vgt-btn vgt-btn-cyan" style="flex: 2; justify-content: center;" onclick="vgtSaveNode()"><?php esc_html_e('Inject Source', 'vgt-sentinel'); ?></button>
            </div>
        </div>
    </div>

</div>

<script>
    <?php 
    $gorgon_js_path = __DIR__ . '/gorgon/script.js';
    if (is_readable($gorgon_js_path)) {
        include $gorgon_js_path;
    }
    ?>
</script>
