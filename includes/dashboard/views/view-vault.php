<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: VAULT
 * MODULE: KRYPTO KEY MANAGEMENT & ASSET SEALING
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

if (!class_exists('VIS_Key_Vault')) {
    echo '<div class="vgt-apex-ui"><div class="vgt-glass-panel" style="padding:40px; text-align:center; color:red;">' . esc_html__('CRITICAL: VIS_Key_Vault Module not loaded.', 'vgt-sentinel') . '</div></div>';
    return;
}

$registered_keys = VIS_Key_Vault::get_registry();
$status_msg = isset($_GET['vault-status']) ? sanitize_text_field($_GET['vault-status']) : '';
?>

<!-- =========================================================================================
     DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $vault_css_path = __DIR__ . '/vault/style.css';
    if (is_readable($vault_css_path)) {
        echo file_get_contents($vault_css_path);
    }
    ?>
</style>

<div class="vgt-apex-ui">

    <div class="vgt-glass-panel vgt-module-header">
        <div class="vgt-module-title">
            <div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); display: flex;">
                <svg class="vgt-icon" style="color:var(--vgt-neon-purple); width:24px; height:24px;" viewBox="0 0 24 24">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <div>
                <h2><?php esc_html_e('VGT KRYPTO VAULT', 'vgt-sentinel'); ?></h2>
                <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace;">
                    <?php esc_html_e('AES-256-GCM Verschlüsselung | AAD-Binding Active', 'vgt-sentinel'); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($status_msg === 'secured'): ?>
        <div class="vgt-alert-success">
            <svg class="vgt-icon" style="width:18px; height:18px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <?php esc_html_e('Asset erfolgreich im Vault kryptographisch versiegelt.', 'vgt-sentinel'); ?>
        </div>
    <?php elseif ($status_msg === 'terminated'): ?>
        <div class="vgt-alert-success" style="color:var(--vgt-neon-red); border-color:rgba(239,68,68,0.3); background:rgba(239,68,68,0.1);">
            <svg class="vgt-icon" style="width:18px; height:18px;" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            <?php esc_html_e('Asset irreversibel aus der Matrix gelöscht.', 'vgt-sentinel'); ?>
        </div>
    <?php endif; ?>

    <!-- ADD NEW KEY PANEL -->
    <div class="vgt-glass-panel" style="padding: 24px;">
        <h3 style="margin-top:0; color:#fff; font-size:16px; border-bottom:1px solid var(--vgt-border); padding-bottom:12px; margin-bottom:20px; display: flex; align-items: center; gap: 10px;">
            <svg class="vgt-icon" style="color:var(--vgt-neon-purple); width:20px; height:20px;" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <?php esc_html_e('NEUEN KEY VERSCHLÜSSELN - Für AEGIS als vis_aegis_ai_key speichern!', 'vgt-sentinel'); ?> 
        </h3>
        <form method="post" action="">
            <?php wp_nonce_field('vis_vault_save_action'); ?>
            <input type="hidden" name="action" value="vis_vault_save">
            
            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:20px; align-items: start;">
                <div class="vgt-form-group">
                    <label><?php esc_html_e('Key Identifier (Unique ID)', 'vgt-sentinel'); ?></label>
                    <input type="text" name="key_identifier" class="vgt-input" placeholder="<?php echo esc_attr__('z.B. vis_api_key_groq', 'vgt-sentinel'); ?>" required autocomplete="off">
                    <p style="font-size:11px; color:var(--vgt-text-dim); margin-top:6px;"><?php esc_html_e('Wird als System-ID und AAD-Salt verwendet.', 'vgt-sentinel'); ?></p>
                </div>
                
                <div class="vgt-form-group">
                    <label><?php esc_html_e('Raw API Key (Plaintext)', 'vgt-sentinel'); ?></label>
                    <input type="password" name="key_value" class="vgt-input" placeholder="<?php echo esc_attr__('Sk-xxxxxxxxxxxxxxxxxxxxxxxx', 'vgt-sentinel'); ?>" required autocomplete="new-password">
                </div>
            </div>
            
            <div style="text-align:right; margin-top:10px;">
                <button type="submit" class="vgt-btn vgt-btn-neon">
                    <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <?php esc_html_e('IN VAULT VERSIEGELN', 'vgt-sentinel'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- REGISTERED KEYS PANEL -->
    <div class="vgt-glass-panel">
        <h3 style="margin:0; color:#fff; font-size:16px; padding: 20px 24px; border-bottom:1px solid var(--vgt-border); background:rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px;">
            <svg class="vgt-icon" style="color:var(--vgt-neon-green); width:20px; height:20px;" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
            <?php esc_html_e('VERSIEGELTE ASSETS (REGISTRY)', 'vgt-sentinel'); ?>
        </h3>
        
        <?php if (empty($registered_keys)): ?>
            <div style="padding: 40px; text-align:center; color:var(--vgt-text-dim);">
                <svg class="vgt-icon" style="width:30px; height:30px; margin-bottom:10px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <br><?php esc_html_e('Der Vault ist derzeit leer.', 'vgt-sentinel'); ?>
            </div>
        <?php else: ?>
            <div class="vgt-key-grid">
                <?php foreach ($registered_keys as $key_name): ?>
                    <div class="vgt-key-card">
                        <div class="vgt-key-name"><?php echo esc_html((string)$key_name); ?></div>
                        <div class="vgt-key-status">
                            <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <?php esc_html_e('AES-256-GCM SECURED', 'vgt-sentinel'); ?>
                        </div>
                        <p style="font-size:11px; color:var(--vgt-text-dim); margin-bottom:20px; font-family:monospace;">
                            <?php 
                            printf(
                                esc_html__('Usage: VIS_Key_Vault::get_key(\'%s\')', 'vgt-sentinel'),
                                esc_html((string)$key_name)
                            );
                            ?>
                        </p>
                        
                        <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('WARNUNG: Das Löschen des Keys ist irreversibel. Angeschlossene Systeme (Morpheus AI etc.) könnten ausfallen. Fortfahren?', 'vgt-sentinel')); ?>');">
                            <?php wp_nonce_field('vis_vault_delete_action'); ?>
                            <input type="hidden" name="action" value="vis_vault_delete">
                            <input type="hidden" name="key_identifier" value="<?php echo esc_attr((string)$key_name); ?>">
                            <button type="submit" class="vgt-btn vgt-btn-danger" style="width:100%; padding:8px;">
                                <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                <?php esc_html_e('TERMINIEREN', 'vgt-sentinel'); ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
