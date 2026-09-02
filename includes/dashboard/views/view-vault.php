<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

if (!class_exists('VIS_Key_Vault')) {
    echo '<section class="vgt-titan"><div class="vgt-titan-panel" style="border-color:#fb7185; color:#fb7185;">' . esc_html__('CRITICAL: VIS_Key_Vault Module not loaded.', 'vgt-sentinel') . '</div></section>';
    return;
}

$registered_keys = VIS_Key_Vault::get_registry();
$status_msg = isset($_GET['vault-status']) && is_string($_GET['vault-status']) ? sanitize_key(wp_unslash($_GET['vault-status'])) : '';
?>

<section class="vgt-titan" aria-label="Krypto Vault Key Management">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('HARDWARE-BOUND SECRETS STORAGE & AES-256-GCM ENCRYPTION', 'vgt-sentinel'); ?></p>
            <h2>KRYPTO VAULT</h2>
            <p><?php esc_html_e('Kryptographisch versiegelter Schlüsselspeicher mit AES-256-GCM Authenticated Encryption, AAD-Binding und integrierter Master-Secret-Isolation.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Vault Status">
            <span><small><?php esc_html_e('VAULT STATUS', 'vgt-sentinel'); ?></small><strong><?php esc_html_e('SEALED', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('SEALED ASSETS', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo count($registered_keys); ?></strong></span>
            <span><small><?php esc_html_e('CIPHER STANDARD', 'vgt-sentinel'); ?></small><strong>AES-256-GCM</strong></span>
            <span><small><?php esc_html_e('INTEGRITY BINDING', 'vgt-sentinel'); ?></small><strong>AAD ACTIVE</strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('SEALED KEY ASSETS', 'vgt-sentinel'); ?></small><strong><?php echo count($registered_keys); ?></strong></article>
        <article><small><?php esc_html_e('CIPHER MODE', 'vgt-sentinel'); ?></small><strong>AES-256-GCM</strong></article>
        <article><small><?php esc_html_e('AUTHENTICATED DATA', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php esc_html_e('AAD-SALT BINDING', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('KEY DERIVATION', 'vgt-sentinel'); ?></small><strong>HKDF-SHA256</strong></article>
        <article><small><?php esc_html_e('ISOLATION REGION', 'vgt-sentinel'); ?></small><strong>SERVER-BOUND</strong></article>
        <article><small><?php esc_html_e('TAMPER DEFENSE', 'vgt-sentinel'); ?></small><strong>HARDENED</strong></article>
    </div>

    <?php if ($status_msg === 'secured'): ?>
        <div class="vgt-titan-findings"><p><?php esc_html_e('Asset erfolgreich im Vault kryptographisch versiegelt.', 'vgt-sentinel'); ?></p></div>
    <?php elseif ($status_msg === 'terminated'): ?>
        <div class="vgt-titan-findings"><p class="is-critical"><?php esc_html_e('Asset irreversibel aus der Matrix gelöscht.', 'vgt-sentinel'); ?></p></div>
    <?php endif; ?>

    <!-- SECTION 1: SEAL NEW SECRET -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('01 / SECRETS INGRESS', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Neuen API-Key / Secret im Vault versiegeln', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php esc_html_e('AES-256-GCM', 'vgt-sentinel'); ?></span>
        </div>
        <form method="post" action="">
            <?php wp_nonce_field('vis_vault_save_action'); ?>
            <input type="hidden" name="action" value="vis_vault_save">
            
            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('Key Identifier (Unique System-ID)', 'vgt-sentinel'); ?></span>
                    <input type="text" name="key_identifier" placeholder="vis_aegis_ai_key" required autocomplete="off">
                </label>
                <label>
                    <span><?php esc_html_e('Raw API Key (Plaintext)', 'vgt-sentinel'); ?></span>
                    <input type="password" name="key_value" placeholder="sk-xxxxxxxxxxxxxxxxxxxxxxxx" required autocomplete="new-password">
                </label>
            </div>
            
            <div class="vgt-titan-actions">
                <button type="submit"><?php esc_html_e('IN VAULT VERSIEGELN', 'vgt-sentinel'); ?></button>
            </div>
        </form>
    </section>

    <!-- SECTION 2: REGISTERED KEYS -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('02 / SECRETS REGISTRY', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Versiegelte Assets (Registry)', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php echo count($registered_keys); ?> <?php esc_html_e('KEYS', 'vgt-sentinel'); ?></span>
        </div>

        <?php if (empty($registered_keys)): ?>
            <div class="vgt-titan-empty" style="padding: 24px 0; color: #5eead4; text-align: center;">
                <?php esc_html_e('VAULT READY — Noch keine Secrets im Krypto-Tresor gespeichert.', 'vgt-sentinel'); ?>
            </div>
        <?php else: ?>
            <div class="vgt-titan-table-wrap">
                <table class="vgt-titan-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Key Identifier', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Cipher Mode', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('Status', 'vgt-sentinel'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Actions', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registered_keys as $k_id => $k_data): ?>
                        <tr>
                            <td><code><?php echo esc_html((string)$k_id); ?></code></td>
                            <td><code>AES-256-GCM</code></td>
                            <td><strong style="color: #5eead4;"><?php esc_html_e('SEALED', 'vgt-sentinel'); ?></strong></td>
                            <td style="text-align:right;">
                                <form method="post" action="" class="vis-inline-form" data-confirm="<?php echo esc_attr__('Secret wirklich unwiderruflich löschen?', 'vgt-sentinel'); ?>">
                                    <?php wp_nonce_field('vis_vault_delete_action'); ?>
                                    <input type="hidden" name="action" value="vis_vault_delete">
                                    <input type="hidden" name="key_identifier" value="<?php echo esc_attr((string)$k_id); ?>">
                                    <button type="submit" style="background:rgba(251,113,133,0.1); border:1px solid #fb7185; color:#fecdd3; border-radius:6px; padding:6px 10px; cursor:pointer; font:700 10px monospace;">
                                        <?php esc_html_e('LÖSCHEN', 'vgt-sentinel'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
