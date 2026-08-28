<?php
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: CERBERUS
 * MODULE: LAYER 1 PERIMETER DEFENSE & IP BAN MANAGEMENT
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_bans = defined('VIS_TABLE_BANS') ? $wpdb->prefix . VIS_TABLE_BANS : $wpdb->prefix . 'vis_bans';

// --- PAGINATION LOGIK ---
$bans_per_page = 20; 
$current_page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$offset = ($current_page - 1) * $bans_per_page;

// --- ZERO-COST REAL DATA AGGREGATION ---
$total_bans = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bans}");
$total_pages = ceil($total_bans / $bans_per_page);
$recent_bans = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bans} WHERE banned_at >= NOW() - INTERVAL 24 HOUR");

$bans = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_bans} ORDER BY banned_at DESC LIMIT %d OFFSET %d",
    $bans_per_page, $offset
));
?>

<!-- =========================================================================================
     ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $cerberus_css_path = __DIR__ . '/cerberus/style.css';
    if (is_readable($cerberus_css_path)) {
        echo file_get_contents($cerberus_css_path);
    }
    ?>
</style>

<div class="vgt-module-container cerberus-core">
    
    <!-- HEADER SECTION -->
    <div class="vgt-header">
        <div class="vgt-title-group">
            <h1 class="vgt-glitch-text cerberus-glitch" data-text="<?php echo esc_attr__('CERBERUS ENGINE', 'vgt-sentinel'); ?>">
                <?php esc_html_e('CERBERUS ENGINE', 'vgt-sentinel'); ?>
            </h1>
            <p class="vgt-subtitle"><?php esc_html_e('Layer 1 Perimeter Defense & IP Ban Management', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-status-badge active" id="cerberus-main-badge">
            <span class="pulse-dot"></span> 
            <span id="badge-text-cerberus"><?php esc_html_e('PERIMETER: LOCKED DOWN', 'vgt-sentinel'); ?></span>
        </div>
    </div>

    <!-- HIGH LEVEL KPI METRICS -->
    <div class="vgt-kpi-matrix">
        <div class="vgt-kpi-box">
            <div class="kpi-icon"><svg class="vgt-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
            <div class="kpi-data">
                <span class="kpi-value" id="kpi-total-bans"><?php echo esc_html(number_format_i18n($total_bans)); ?></span>
                <span class="kpi-label"><?php esc_html_e('Active Opcache Bans', 'vgt-sentinel'); ?></span>
            </div>
            <div class="kpi-sparkline pulse-slow"></div>
        </div>
        <div class="vgt-kpi-box">
            <div class="kpi-icon"><svg class="vgt-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>
            <div class="kpi-data">
                <span class="kpi-value" id="kpi-recent-bans"><?php echo esc_html(number_format_i18n($recent_bans)); ?></span>
                <span class="kpi-label"><?php esc_html_e('Threats Eliminated (24h)', 'vgt-sentinel'); ?></span>
            </div>
            <div class="kpi-sparkline pulse-medium"></div>
        </div>
        <div class="vgt-kpi-box">
            <div class="kpi-icon"><svg class="vgt-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg></div>
            <div class="kpi-data">
                <span class="kpi-value" id="kpi-pages">
                    <?php printf(esc_html__('%d / %d', 'vgt-sentinel'), (int)$current_page, (int)max(1, $total_pages)); ?>
                </span>
                <span class="kpi-label"><?php esc_html_e('Pagination Engine', 'vgt-sentinel'); ?></span>
            </div>
            <div class="kpi-sparkline pulse-fast" style="width: <?php echo $total_pages > 0 ? esc_attr((string)min(100, ($current_page / $total_pages) * 100)) : '0'; ?>%; background: linear-gradient(90deg, transparent, var(--vgt-cerberus)); opacity: 0.8; transform: none;"></div>
        </div>
    </div>

    <!-- BAN LIST MATRIX -->
    <div class="vgt-grid">
        <div class="vgt-card vgt-glass-card span-full">
            <div class="card-header">
                <div class="icon-wrapper"><svg class="vgt-icon" viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg></div>
                <h3><?php esc_html_e('Active Threat Roster', 'vgt-sentinel'); ?></h3>
            </div>
            
            <div class="tuning-body" style="overflow-x: auto;">
                <table class="vgt-cerberus-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?php esc_html_e('IP-Adresse', 'vgt-sentinel'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Timestamp', 'vgt-sentinel'); ?></th>
                            <th style="width: 55%;"><?php esc_html_e('Terminated Payload (XSS-Isolated)', 'vgt-sentinel'); ?></th>
                            <th style="width: 15%; text-align: right;"><?php esc_html_e('Command', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bans)): ?>
                            <tr>
                                <td colspan="4" style="padding: 40px; text-align: center; color: #64748b;">
                                    <span class="dashicons dashicons-shield" style="font-size: 40px; width: 40px; height: 40px; opacity: 0.3; margin-bottom: 15px;"></span><br>
                                    <?php esc_html_e('Perimeter clear. No active blockades.', 'vgt-sentinel'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bans as $ban): ?>
                                <tr>
                                    <td>
                                        <span class="vgt-cerberus-ip"><?php echo esc_html($ban->ip); ?></span>
                                    </td>
                                    <td>
                                        <span class="vgt-cerberus-time">
                                             <?php echo esc_html(wp_date(get_option('date_format') . ' H:i:s', strtotime($ban->banned_at))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <pre class="vgt-cerberus-payload"><?php 
                                            echo esc_html($ban->reason); 
                                            if (!empty($ban->request_uri)) {
                                                echo "\n\n<span style='color:#64748b;'>" . esc_html__('[TARGET_URI]:', 'vgt-sentinel') . "</span> " . esc_html($ban->request_uri);
                                            }
                                        ?></pre>
                                    </td>
                                    <td style="text-align: right; vertical-align: middle;">
                                        <button type="button" class="vgt-btn-unban" onclick="vgt_trigger_unban_modal('<?php echo esc_js($ban->ip); ?>')">
                                            <?php esc_html_e('UNBAN', 'vgt-sentinel'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php 
            if ($total_pages > 1): 
                $pagination_links = paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo; ' . esc_html__('PREV', 'vgt-sentinel'),
                    'next_text' => esc_html__('NEXT', 'vgt-sentinel') . ' &raquo;',
                    'total'     => $total_pages,
                    'current'   => $current_page,
                    'type'      => 'plain'
                ]);
                
                if ($pagination_links) {
                    echo '<div class="vgt-pagination">' . wp_kses_post($pagination_links) . '</div>';
                }
            endif; 
            ?>
        </div>
    </div>
</div>

<!-- VGT SUPREME CUSTOM UI MODAL FÜR UNBAN -->
<div id="vgt-unban-modal" class="vgt-modal-overlay" style="display: none;">
    <div class="vgt-modal-box">
        <div class="vgt-modal-header">
            <svg class="vgt-icon" viewBox="0 0 24 24" style="color: var(--vgt-cerberus);"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <h2><?php esc_html_e('SECURITY OVERRIDE REQUIRED', 'vgt-sentinel'); ?></h2>
        </div>
        <div class="vgt-modal-body">
            <p><?php esc_html_e('Sie sind im Begriff, eine Perimeter-Sperre manuell aufzuheben. Dadurch erhält die betroffene Einheit sofortigen Zugriff auf die Systemressourcen.', 'vgt-sentinel'); ?></p>
            <div class="vgt-modal-target-ip">
                <span><?php esc_html_e('TARGET IP:', 'vgt-sentinel'); ?></span>
                <code id="vgt-modal-ip-display">0.0.0.0</code>
            </div>
        </div>
        <div class="vgt-modal-footer">
            <button type="button" class="vgt-btn-cancel" onclick="vgt_close_unban_modal()"><?php esc_html_e('ABORT', 'vgt-sentinel'); ?></button>
            <button type="button" class="vgt-btn-execute" id="vgt-execute-unban-btn"><?php esc_html_e('EXECUTE UNBAN', 'vgt-sentinel'); ?></button>
        </div>
    </div>
</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT VIA INCLUDE FÜR PHP PARSING)
     ========================================================================================= -->
<script>
    <?php 
    $cerberus_js_path = __DIR__ . '/cerberus/script.js';
    if (is_readable($cerberus_js_path)) {
        include $cerberus_js_path;
    }
    ?>
</script>
