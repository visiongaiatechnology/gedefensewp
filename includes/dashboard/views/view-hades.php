<?php 
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$opt = get_option('vis_config', []);
$hades_active = !empty($opt['hades_enabled']);

// Hole Parameter (mit Fallbacks für frische Installationen)
$admin_param  = !empty($opt['hades_admin_param']) ? $opt['hades_admin_param'] : 'vgt_access';
$admin_secret = !empty($opt['hades_admin_secret']) ? $opt['hades_admin_secret'] : 'omega';

// MAPPING DATA (CLOAKING VECTORS) - DYNAMIC CONFIG
$cloaking_rules = [
    'wp-content/themes'  => ['key' => 'hades_map_themes',  'default' => 'content/ui'],
    'wp-content/plugins' => ['key' => 'hades_map_plugins', 'default' => 'content/lib'],
    'wp-content/uploads' => ['key' => 'hades_map_uploads', 'default' => 'storage'],
    'wp-content'         => ['key' => 'hades_map_content', 'default' => 'content'],
    'wp-includes'        => ['key' => 'hades_map_includes', 'default' => 'core'],
    'wp-json (REST API)' => ['key' => 'hades_map_rest',     'default' => 'vgt-api'],
];

// OMEGA FILE MAP: Exakte Endpunkt-Verschleierung - DYNAMIC CONFIG
$endpoint_rules = [
    'wp-admin/admin-ajax.php' => ['key' => 'hades_map_ajax', 'default' => 'vgt-api/nexus'],
    'wp-admin/admin-post.php' => ['key' => 'hades_map_post', 'default' => 'vgt-api/post'],
];

// VGT SUPREME: NGINX DETECTION
$is_nginx = strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx') !== false;
$nginx_rules = '';
if ($is_nginx && class_exists('VIS_Hades')) {
    $nginx_rules = VIS_Hades::get_nginx_rules($opt);
}

$hadesToggle = static function(string $name, bool $enabled, string $label): void {
    echo '<label class="vgt-titan-toggle"><input type="checkbox" name="vis_config[' . esc_attr($name) . ']" value="1" ' . checked($enabled, true, false) . '><span aria-hidden="true"></span><b>' . esc_html($label) . '</b></label>';
};
?>

<section class="vgt-titan" aria-label="Hades Ghost Protocol Cloaking Engine">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('GHOST PROTOCOL ARCHITECTURE & ENDPOINT CLOAKING ENGINE', 'vgt-sentinel'); ?></p>
            <h2>HADES</h2>
            <p><?php esc_html_e('Vollständige Tarnung der WordPress-Architektur. Verschleiert Kernpfade (/wp-content/, /wp-includes/, REST-API) und isoliert den administrativen Zugang über kryptographische URL-Parameter (404 Mimikry).', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Hades Status">
            <span><small><?php esc_html_e('CLOAKING ENGINE', 'vgt-sentinel'); ?></small><strong style="color: <?php echo $hades_active ? '#c084fc' : '#94a3b8'; ?>;"><?php echo $hades_active ? esc_html__('STEALTH ACTIVE', 'vgt-sentinel') : esc_html__('VISIBLE (RAW)', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('404 MIMIKRY', 'vgt-sentinel'); ?></small><strong><?php echo $hades_active ? esc_html__('ARMED', 'vgt-sentinel') : esc_html__('STANDBY', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('PATH VECTORS', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)(count($cloaking_rules) + count($endpoint_rules))); ?> VECTORS</strong></span>
            <span><small><?php esc_html_e('WEB SERVER', 'vgt-sentinel'); ?></small><strong><?php echo $is_nginx ? 'NGINX' : 'APACHE / LITESPEED'; ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('STEALTH STATE', 'vgt-sentinel'); ?></small><strong style="color: <?php echo $hades_active ? '#c084fc' : '#fb7185'; ?>;"><?php echo $hades_active ? esc_html__('STEALTH ACTIVE', 'vgt-sentinel') : esc_html__('EXPOSED', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('ADMIN ACCESS MIMICRY', 'vgt-sentinel'); ?></small><strong><?php echo $hades_active ? esc_html__('404 ISOLATION', 'vgt-sentinel') : esc_html__('STANDARD WP', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('CLOAKED DIRECTORIES', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)count($cloaking_rules)); ?> DIRS</strong></article>
        <article><small><?php esc_html_e('CLOAKED ENDPOINTS', 'vgt-sentinel'); ?></small><strong><?php echo esc_html((string)count($endpoint_rules)); ?> FILES</strong></article>
        <article><small><?php esc_html_e('REWRITE KERNEL', 'vgt-sentinel'); ?></small><strong><?php echo $is_nginx ? 'NGINX RULES' : '.HTACCESS REWRITE'; ?></strong></article>
        <article><small><?php esc_html_e('WAPPALYZER DEFENSE', 'vgt-sentinel'); ?></small><strong>100% DECEPTIVE</strong></article>
    </div>

    <!-- CRITICAL PERMALINK NOTIFICATION -->
    <div style="margin-bottom: 20px; padding: 16px 20px; border-radius: 12px; background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.28); display: flex; align-items: flex-start; gap: 14px;">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#f59e0b" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <div style="font-size: 13px; line-height: 1.6; color: var(--vis-copy);">
            <strong style="color: #f59e0b;"><?php esc_html_e('KRITISCHE AKTION ERFORDERLICH:', 'vgt-sentinel'); ?></strong> 
            <?php esc_html_e('Nach der Aktivierung oder Deaktivierung des Stealth-Modus müssen Sie zwingend die Permalinks neu generieren (WordPress > Einstellungen > Permalinks > Speichern klicken), andernfalls kommt es zu 404-Fehlern, da die Rewrite-Regeln im Webserver neu kompiliert werden müssen.', 'vgt-sentinel'); ?>
        </div>
    </div>

    <?php if ($is_nginx && $hades_active): ?>
    <!-- NGINX SERVER DETECTED -->
    <div style="margin-bottom: 20px; padding: 18px 20px; border-radius: 12px; background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.28);">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; color: #38bdf8; font-weight: 750;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 22h20L12 2z"></path><line x1="12" y1="16" x2="12" y2="16"></line><line x1="12" y1="8" x2="12" y2="12"></line></svg>
            <?php esc_html_e('NGINX SERVER ERKANNT:', 'vgt-sentinel'); ?>
        </div>
        <p style="font-size: 12.5px; color: var(--vis-copy-soft); margin: 0 0 12px 0; line-height: 1.5;">
            <?php esc_html_e('Auf NGINX-Servern greift die automatische .htaccess-Generierung nicht. Kopieren Sie den nachfolgenden Konfigurations-Block in Ihren NGINX server {} Block und laden Sie den Dienst neu (nginx -s reload):', 'vgt-sentinel'); ?>
        </p>
        <textarea readonly style="width: 100%; height: 160px; font-family: ui-monospace, monospace; font-size: 11px; background: rgba(3, 8, 18, 0.85); border: 1px solid var(--vis-line); border-radius: 6px; padding: 12px; color: #5eead4; white-space: pre;"><?php echo esc_textarea($nginx_rules); ?></textarea>
    </div>
    <?php endif; ?>

    <!-- SECTION 1: STEALTH ENGINE & ADMIN MIMICRY -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small style="color: #c084fc;"><?php esc_html_e('01 / STEALTH CORE & ADMIN MIMIKRY', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Admin-Zugangsisolation & Stealth-Aktivierung', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge" style="border-color: rgba(192, 132, 252, 0.4); color: #c084fc;">
                <?php echo $hades_active ? esc_html__('CLOAKED', 'vgt-sentinel') : esc_html__('EXPOSED', 'vgt-sentinel'); ?>
            </span>
        </div>

        <div class="vgt-titan-toggle-grid" style="margin-bottom: 22px;">
            <?php $hadesToggle('hades_enabled', $hades_active, __('Tarnmodus aktivieren (Stealth Engine Master-Switch)', 'vgt-sentinel')); ?>
        </div>

        <div style="background: var(--vis-surface-inset); padding: 20px; border-radius: 10px; border: 1px solid var(--vis-line);">
            <div style="margin-bottom: 12px;">
                <strong style="font-size: 13.5px; color: #ffffff;"><?php esc_html_e('Admin-Zugangsmatrix (404 Mimikry)', 'vgt-sentinel'); ?></strong>
                <p style="font-size: 12.5px; color: var(--vis-copy-soft); margin: 4px 0 0; line-height: 1.5;">
                    <?php esc_html_e('Isoliert /wp-admin und /wp-login.php. Ohne dieses kryptographische Parameter-Paar in der URL wird ein hartes 404 Not Found simuliert. Automatisierte Scans und Brute-Force-Angriffe laufen ins Leere.', 'vgt-sentinel'); ?>
                </p>
            </div>

            <div class="vgt-input-matrix" style="max-width: 600px; margin-top: 14px;">
                <span class="vgt-input-addon">/wp-admin?</span>
                <input type="text" name="vis_config[hades_admin_param]" value="<?php echo esc_attr($admin_param); ?>" placeholder="vgt_access" style="max-width: 160px;">
                <span class="vgt-input-addon">=</span>
                <input type="text" name="vis_config[hades_admin_secret]" value="<?php echo esc_attr($admin_secret); ?>" placeholder="omega" style="flex: 1;">
            </div>

            <div class="vgt-route-preview" style="max-width: 600px; margin-top: 14px;">
                <svg class="vgt-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#34d399" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span style="font-family: ui-monospace, monospace; font-size: 11.5px;">
                    <strong style="color: #94a3b8;"><?php esc_html_e('Verifizierte Zugangsroute:', 'vgt-sentinel'); ?></strong> 
                    <span style="color: #ffffff;"><?php echo esc_html(site_url('/wp-admin')); ?>?<?php echo esc_html($admin_param); ?>=<span style="color: #c084fc; font-weight: 750;"><?php echo esc_html($admin_secret); ?></span></span>
                </span>
            </div>
        </div>
    </section>

    <!-- SECTION 2: DIRECTORY MAPPING -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('02 / DIRECTORY MAPPING', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Aktive Tarnvektoren (Verzeichnis-Verschleierung)', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php esc_html_e('L7 REWRITE', 'vgt-sentinel'); ?></span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--vis-line); text-align: left;">
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 45%;">
                            <?php esc_html_e('ÖFFENTLICHER PFAD (FRONTEND / TARNUNG)', 'vgt-sentinel'); ?>
                        </th>
                        <th style="width: 10%; text-align: center;"></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 45%;">
                            <?php esc_html_e('PHYSISCHES ZIEL (INTERNER BACKEND-PFAD)', 'vgt-sentinel'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($cloaking_rules as $physical => $data): 
                        $val = !empty($opt[$data['key']]) ? $opt[$data['key']] : $data['default'];
                    ?>
                    <tr style="border-bottom: 1px solid rgba(148, 184, 224, 0.08);">
                        <td style="padding: 10px 14px;">
                            <div class="vgt-input-matrix" style="margin: 0;">
                                <span class="vgt-input-addon">/</span>
                                <input type="text" name="vis_config[<?php echo esc_attr($data['key']); ?>]" value="<?php echo esc_attr((string)$val); ?>">
                                <span class="vgt-input-addon">/</span>
                            </div>
                        </td>
                        <td style="text-align: center; color: var(--vis-cyan);">
                            <svg class="vgt-route-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </td>
                        <td style="padding: 10px 14px; font-family: ui-monospace, monospace; color: var(--vis-copy-soft); font-size: 11.5px;">
                            /<?php echo esc_html($physical); ?>/
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- SECTION 3: ENDPOINT STEALTH -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('03 / ENDPOINT STEALTH', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Endpunkt-Tarnung (Dateiebene)', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php esc_html_e('API MASQUERADE', 'vgt-sentinel'); ?></span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--vis-line); text-align: left;">
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 45%;">
                            <?php esc_html_e('ÖFFENTLICHE DATEI (FRONTEND)', 'vgt-sentinel'); ?>
                        </th>
                        <th style="width: 10%; text-align: center;"></th>
                        <th style="padding: 10px 14px; font: 700 10px/1.3 var(--vis-font-data); color: var(--vis-copy-muted); text-transform: uppercase; letter-spacing: 0.1em; width: 45%;">
                            <?php esc_html_e('PHYSISCHES ZIEL (INTERNER ENDPUNKT)', 'vgt-sentinel'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($endpoint_rules as $physical => $data): 
                        $val = !empty($opt[$data['key']]) ? $opt[$data['key']] : $data['default'];
                    ?>
                    <tr style="border-bottom: 1px solid rgba(148, 184, 224, 0.08);">
                        <td style="padding: 10px 14px;">
                            <div class="vgt-input-matrix" style="margin: 0;">
                                <span class="vgt-input-addon">/</span>
                                <input type="text" name="vis_config[<?php echo esc_attr($data['key']); ?>]" value="<?php echo esc_attr((string)$val); ?>">
                            </div>
                        </td>
                        <td style="text-align: center; color: var(--vis-cyan);">
                            <svg class="vgt-route-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </td>
                        <td style="padding: 10px 14px; font-family: ui-monospace, monospace; color: var(--vis-copy-soft); font-size: 11.5px;">
                            /<?php echo esc_html($physical); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="vgt-titan-actions" style="margin-top: 24px; justify-content: center; width: 100%;">
            <button type="submit" style="width: 100%; max-width: 320px; justify-content: center;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                <?php esc_html_e('HADES TARN-EINSTELLUNGEN SPEICHERN', 'vgt-sentinel'); ?>
            </button>
        </div>
    </section>
</section>
