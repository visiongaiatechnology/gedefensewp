<?php 
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$opt = get_option('vis_config', []);
$is_enabled = !empty($opt['ghost_trap_enabled']);
$trap_count = (int)($opt['ghost_trap_count'] ?? 5);
$trap_exts = (string)($opt['ghost_trap_exts'] ?? 'php, sql, bak, old, zip');
$trap_style = (string)($opt['ghost_trap_style'] ?? 'mixed');

$active_manifest = get_option('vis_ghost_trap_manifest', []);
if (!is_array($active_manifest)) {
    $active_manifest = [];
}
$deployed_count = count($active_manifest);

$ghostToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Ghost Trap Honeypot Grid">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('PROACTIVE DECEPTION ENGINE & HONEYPOT NETWORK', 'vgt-sentinel'); ?></p>
            <h2>GHOST TRAP</h2>
            <p><?php esc_html_e('Proaktives Honeypot-Netzwerk. Generiert unsichtbare Dummy-Dateien im Root-Verzeichnis. Jeder Scanner oder Bot, der diese Dateien abtastet, wird sofort permanent auf Netzwerk-Ebene blockiert (Auto-Ban).', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Ghost Trap Status">
            <span><small><?php esc_html_e('DECEPTION ENGINE', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ONLINE', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('DEPLOYED NODES', 'vgt-sentinel'); ?></small><strong style="color: #c084fc;"><?php echo esc_html((string)$deployed_count); ?> NODES</strong></span>
            <span><small><?php esc_html_e('PROFILE LOGIC', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(strtoupper($trap_style)); ?></strong></span>
            <span><small><?php esc_html_e('TRIPWIRE BAN', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ARMED', 'vgt-sentinel') : esc_html__('OFFLINE', 'vgt-sentinel'); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('DECEPTION STATE', 'vgt-sentinel'); ?></small><strong><?php echo $is_enabled ? esc_html__('ACTIVE (Monitoring)', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('ACTIVE NODES', 'vgt-sentinel'); ?></small><strong style="color: #c084fc;"><?php echo esc_html((string)$deployed_count); ?> / <?php echo esc_html((string)$trap_count); ?></strong></article>
        <article><small><?php esc_html_e('NAMING LOGIC', 'vgt-sentinel'); ?></small><strong><?php echo esc_html(strtoupper($trap_style)); ?></strong></article>
        <article><small><?php esc_html_e('TRIPWIRE POLICY', 'vgt-sentinel'); ?></small><strong style="color: #fb7185;"><?php esc_html_e('INSTANT BAN', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('DECEPTION LAYER', 'vgt-sentinel'); ?></small><strong>L7 APPLICATION</strong></article>
        <article><small><?php esc_html_e('SYSTEM OVERHEAD', 'vgt-sentinel'); ?></small><strong>ZERO POLLUTION</strong></article>
    </div>

    <div class="vgt-grid-2">
        <!-- LEFT COLUMN: GENERATOR CONFIGURATION -->
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('01 / GENERATOR CONFIG', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Honeypot-Generator & Fallendefinition', 'vgt-sentinel'); ?></h3>
                </div>
                <span class="vgt-titan-badge" style="border-color: rgba(192, 132, 252, 0.4); color: #c084fc;">
                    <?php echo $is_enabled ? esc_html__('TRAPS ARMED', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?>
                </span>
            </div>

            <div class="vgt-titan-toggle-grid" style="margin-bottom: 20px;">
                <?php $ghostToggle('ghost_trap_enabled', $is_enabled, __('Ghost-Trap Engine aktivieren (Master-Switch)', 'vgt-sentinel')); ?>
            </div>

            <div class="vgt-titan-fields">
                <label>
                    <span><?php esc_html_e('Anzahl der Fallen (Nodes, Max: 50)', 'vgt-sentinel'); ?></span>
                    <input type="number" name="vis_config[ghost_trap_count]" value="<?php echo esc_attr((string)$trap_count); ?>" min="1" max="50">
                    <small style="color: var(--vis-copy-muted); margin-top: 6px; display: block; line-height: 1.4;">
                        <?php esc_html_e('Legt fest, wie viele Honeypots im Dateisystem platziert werden.', 'vgt-sentinel'); ?>
                    </small>
                </label>

                <label>
                    <span><?php esc_html_e('Namensgenerator-Logik (AI-Stil)', 'vgt-sentinel'); ?></span>
                    <select name="vis_config[ghost_trap_style]" class="vgt-titan-select">
                        <option value="mixed" <?php selected($trap_style, 'mixed'); ?>><?php esc_html_e('Mixed Matrix (Empfohlen)', 'vgt-sentinel'); ?></option>
                        <option value="system" <?php selected($trap_style, 'system'); ?>><?php esc_html_e('System Fakes (wp-config-old, admin-test)', 'vgt-sentinel'); ?></option>
                        <option value="backup" <?php selected($trap_style, 'backup'); ?>><?php esc_html_e('Backup Fakes (db_dump_2024, site_backup)', 'vgt-sentinel'); ?></option>
                        <option value="random" <?php selected($trap_style, 'random'); ?>><?php esc_html_e('Random Hashes (a8f9c12a)', 'vgt-sentinel'); ?></option>
                    </select>
                    <small style="color: var(--vis-copy-muted); margin-top: 6px; display: block; line-height: 1.4;">
                        <?php esc_html_e('Bestimmt das semantische Köder-Profil der Dateinamen.', 'vgt-sentinel'); ?>
                    </small>
                </label>
            </div>

            <div class="vgt-titan-wide-field" style="margin-top: 18px;">
                <label>
                    <span><?php esc_html_e('Polymorphe Dateiendungen', 'vgt-sentinel'); ?></span>
                    <input type="text" name="vis_config[ghost_trap_exts]" value="<?php echo esc_attr($trap_exts); ?>" placeholder="php, sql, bak, old, zip">
                    <small style="color: var(--vis-copy-muted); margin-top: 6px; display: block; line-height: 1.4;">
                        <?php esc_html_e('Komma-separierte Liste. Lockt automatisierte Schwachstellen-Scanner an (z.B. SQL-Dumper, Backup-Sniffer).', 'vgt-sentinel'); ?>
                    </small>
                </label>
            </div>

            <p style="font-size: 11.5px; color: var(--vis-copy-muted); line-height: 1.5; margin-top: 18px; padding: 10px 14px; background: rgba(3, 8, 18, 0.5); border-radius: 6px; border: 1px solid var(--vis-line);">
                <strong style="color: #fbbf24;"><?php esc_html_e('HINWEIS:', 'vgt-sentinel'); ?></strong> 
                <?php esc_html_e('Beim Speichern wird das bestehende Honeypot-Netzwerk bereinigt und mit frischen Entropie-Namen neu generiert.', 'vgt-sentinel'); ?>
            </p>

            <div class="vgt-titan-actions" style="margin-top: 24px; justify-content: center; width: 100%;">
                <button type="submit" style="width: 100%; max-width: 280px; justify-content: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    <?php esc_html_e('FALLEN GENERIEREN & DEPLOYEN', 'vgt-sentinel'); ?>
                </button>
            </div>
        </section>

        <!-- RIGHT COLUMN: TELEMETRY & MANIFEST -->
        <section class="vgt-titan-panel">
            <div class="vgt-titan-panel-head">
                <div>
                    <small><?php esc_html_e('02 / MANIFEST & TOPOLOGIE', 'vgt-sentinel'); ?></small>
                    <h3><?php esc_html_e('Bereitstellungs-Manifest', 'vgt-sentinel'); ?></h3>
                </div>
                <span class="vgt-titan-badge" style="border-color: rgba(52, 211, 153, 0.35); color: #34d399;">
                    <?php 
                    printf(
                        esc_html(
                            _n('%d Node', '%d Nodes', $deployed_count, 'vgt-sentinel')
                        ),
                        (int)$deployed_count
                    );
                    ?>
                </span>
            </div>

            <?php if ($is_enabled && $deployed_count > 0): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; padding: 12px 16px; background: rgba(52, 211, 153, 0.08); border: 1px solid rgba(52, 211, 153, 0.25); border-radius: 8px;">
                    <span style="font-size: 13px; font-weight: 750; color: #34d399; display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #34d399; box-shadow: 0 0 10px #34d399;"></span>
                        <?php esc_html_e('SYSTEM AKTIV & SCHARF', 'vgt-sentinel'); ?>
                    </span>
                    <span style="font: 700 11px/1 ui-monospace, monospace; color: var(--vis-copy-soft);">
                        <?php echo esc_html(sprintf(__('%d Nodes im Dateisystem', 'vgt-sentinel'), $deployed_count)); ?>
                    </span>
                </div>

                <p style="font-size: 12.5px; color: var(--vis-copy-soft); line-height: 1.6; margin: 0 0 14px 0;">
                    <?php esc_html_e('Jeder unerlaubte HTTP-Zugriff auf die nachfolgend aufgeführten Routen löst einen sofortigen, permanenten IP-Ban durch den Aegis-Kernel aus.', 'vgt-sentinel'); ?>
                </p>

                <div class="vgt-trap-list">
                    <?php foreach ($active_manifest as $node): ?>
                        <div class="vgt-trap-item">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#c084fc" stroke-width="2">
                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                <polyline points="13 2 13 9 20 9"></polyline>
                            </svg>
                            <span style="font-family: ui-monospace, monospace; color: #ffffff;">/<?php echo esc_html((string)$node); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 48px 16px;">
                    <svg class="vgt-icon" style="width: 44px; height: 44px; color: var(--vis-copy-muted); margin-bottom: 14px; opacity: 0.4;" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <h4 style="margin: 0 0 6px 0; color: #ffffff; font-size: 15px; font-weight: 750;"><?php esc_html_e('SYSTEM STANDBY', 'vgt-sentinel'); ?></h4>
                    <p style="font-size: 12.5px; color: var(--vis-copy-muted); line-height: 1.5; max-width: 380px; margin: 0 auto;">
                        <?php esc_html_e('Derzeit sind keine Honeypot-Köder im Dateisystem platziert. Aktiviere die Engine und klicke auf Bereitstellen.', 'vgt-sentinel'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
