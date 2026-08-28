<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: INTEGRITY
 * MODULE: SYSTEM INTEGRITY MONITOR (FILE HASHING ENGINE)
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 

// =========================================================================================
// 1. REPORT DATA FETCH (STRICT 1:1)
// =========================================================================================
$report = get_option('vis_scan_report', false);
$has_report = !empty($report) && is_array($report);
$status = $has_report ? $report['status'] : 'unknown';
$changes = $has_report ? $report['changes'] : [];
$last_scan = $has_report ? $report['timestamp'] : __('Never', 'vgt-sentinel');

// COLORS & SVG PATHS (Adapted for VGT APEX)
$status_color = '#64748b'; 
$status_icon_svg = '<line x1="5" y1="12" x2="19" y2="12"></line>'; // Default: Minus
$status_pulse_class = 'vgt-is-standby';

if ($status === 'clean' || $status === 'init') {
    $status_color = '#10b981'; 
    $status_icon_svg = '<polyline points="20 6 9 17 4 12"></polyline>'; // Check
    $status_pulse_class = 'vgt-is-active';
} elseif ($status === 'warning') {
    $status_color = '#ef4444'; 
    $status_icon_svg = '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>';
    $status_pulse_class = 'vgt-is-alert';
}
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $integrity_css_path = __DIR__ . '/integrity/style.css';
    if (is_readable($integrity_css_path)) {
        echo file_get_contents($integrity_css_path);
    }
    ?>
</style>

<!-- =========================================================================================
     3. VIEW CONTENT
     ========================================================================================= -->
<div class="vgt-apex-ui">

    <!-- MODULE HEADER -->
    <div class="vgt-glass-panel vgt-module-header" style="border-left: 4px solid <?php echo esc_attr($status_color); ?>;">
        <div class="vgt-module-title">
            <div style="background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); display: flex;">
                <svg class="vgt-icon" style="color:<?php echo esc_attr($status_color); ?>; width:24px; height:24px;" viewBox="0 0 24 24">
                    <?php echo $status_icon_svg; // Dynamic Icon but internal logic 1:1 ?>
                </svg>
            </div>
            <div>
                <h2>
                    <?php esc_html_e('SYSTEM INTEGRITY MONITOR', 'vgt-sentinel'); ?>
                    <?php if($status === 'warning'): ?>
                        <span class="vgt-badge vgt-badge-alert" style="border-radius:4px;"><?php esc_html_e('BREACH DETECTED', 'vgt-sentinel'); ?></span>
                    <?php else: ?>
                        <span class="vgt-badge vgt-badge-neutral" style="border-radius:4px;"><?php esc_html_e('FILE HASHING ENGINE', 'vgt-sentinel'); ?></span>
                    <?php endif; ?>
                </h2>
                <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace;">
                    <?php esc_html_e('Last Deep Scan:', 'vgt-sentinel'); ?> 
                    <span style="color:#fff;"><?php echo esc_html($last_scan); ?></span>
                    <span style="margin: 0 8px; color:var(--vgt-text-muted);">|</span>
                    <span class="<?php echo esc_attr($status_pulse_class); ?>" style="display:inline-flex; align-items:center; gap:6px;">
                        <span class="vgt-status-pulse"></span>
                        <span style="color:<?php echo esc_attr($status_color); ?>;"><?php echo esc_html(strtoupper($status)); ?></span>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="vgt-integrity-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(VIS_Sentinel_Export::action()); ?>">
                <?php wp_nonce_field(VIS_Sentinel_Export::nonce_action()); ?>
                <button type="submit" class="vgt-btn vgt-btn-ghost">
                    <svg class="vgt-icon" viewBox="0 0 24 24"><path d="M12 3v12"></path><polyline points="7 10 12 15 17 10"></polyline><path d="M5 21h14"></path></svg>
                    <?php esc_html_e('EXPORT ANALYSE-DATEN', 'vgt-sentinel'); ?>
                </button>
            </form>
            <button type="button" id="vis-btn-scan" class="vgt-btn vgt-btn-neon vis-btn-scan" data-mode="scan">
                <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <?php esc_html_e('RUN DEEP SCAN', 'vgt-sentinel'); ?>
            </button>
        </div>
    </div>

    <!-- STATE PANELS -->
    <?php if(!$has_report): ?>
        <!-- EMPTY STATE -->
        <div class="vgt-glass-panel vgt-state-clean" style="border-color:var(--vgt-border);">
            <svg class="vgt-icon" style="width:64px; height:64px; color:var(--vgt-text-muted); margin-bottom:20px; opacity:0.5;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <h3><?php esc_html_e('AWAITING INITIALIZATION', 'vgt-sentinel'); ?></h3>
            <p><?php esc_html_e('Kein Integritäts-Bericht im System verzeichnet. Bitte starten Sie einen manuellen Baseline-Scan, um das Hashing-Netzwerk zu aktivieren.', 'vgt-sentinel'); ?></p>
        </div>

    <?php elseif($status === 'clean' || $status === 'init'): ?>
        <!-- SECURE STATE -->
        <div class="vgt-glass-panel vgt-state-clean" style="border-top:3px solid var(--vgt-neon-green);">
            <svg class="vgt-icon vgt-state-clean-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <h3><?php esc_html_e('SYSTEM SECURE', 'vgt-sentinel'); ?></h3>
            <p><?php esc_html_e('Alle überwachten Dateien stimmen exakt mit dem kryptographischen Manifest überein. Es wurden keine nicht-autorisierten Modifikationen (Zero-Day/Malware) im Dateisystem festgestellt.', 'vgt-sentinel'); ?></p>
        </div>

    <?php else: ?>
        <!-- WARNING / ANOMALY STATE -->
        <div class="vgt-glass-panel vgt-table-container" style="border: 1px solid rgba(239, 68, 68, 0.4); box-shadow: 0 0 30px rgba(239, 68, 68, 0.1);">
            
            <div class="vgt-state-alert-header">
                <div style="display:flex; align-items:center; gap:12px; color:var(--vgt-neon-red);">
                    <svg class="vgt-icon" style="width:24px; height:24px; animation: vgt-pulse-alert 1.5s infinite;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <div>
                        <strong style="font-size:16px; letter-spacing:1px; display:block;"><?php esc_html_e('CRITICAL ANOMALIES DETECTED', 'vgt-sentinel'); ?></strong>
                        <span style="font-size:12px; font-family:monospace; color:var(--vgt-text-dim);">
                            <?php 
                            printf(
                                esc_html(
                                    _n('%d Datei verstößt gegen die System-Baseline.', '%d Dateien verstoßen gegen die System-Baseline.', count($changes), 'vgt-sentinel')
                                ),
                                (int)count($changes)
                            ); 
                            ?>
                        </span>
                    </div>
                </div>
                
                <button type="button" id="vis-btn-approve" class="vgt-btn vgt-btn-danger" data-mode="reindex">
                    <svg class="vgt-icon" style="width:16px; height:16px;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <?php esc_html_e('BASELINE UPDATEN (APPROVE)', 'vgt-sentinel'); ?>
                </button>
            </div>

            <table class="vgt-data-table">
                <thead>
                    <tr>
                        <th width="10%"><?php esc_html_e('TYPE', 'vgt-sentinel'); ?></th>
                        <th width="45%"><?php esc_html_e('DATEIPFAD (TARGET)', 'vgt-sentinel'); ?></th>
                        <th width="30%"><?php esc_html_e('DETAILS', 'vgt-sentinel'); ?></th>
                        <th width="15%" style="text-align:right;"><?php esc_html_e('ACTION', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($changes as $change): 
                    $type = $change['type'];
                    $badge_class = 'vgt-badge-alert'; 
                    
                    if ($type === 'NEW') $badge_class = 'vgt-badge-active';
                    if ($type === 'MODIFIED') $badge_class = 'vgt-badge-warning';
                    if ($type === 'DELETED') $badge_class = 'vgt-badge-alert';
                    
                    $file_rel_path = ltrim((string)$change['file'], '/');
                    $file_url = site_url('/' . $file_rel_path);
                ?>
                    <tr>
                        <td><span class="vgt-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($type); ?></span></td>
                        <td class="vgt-text-mono" style="color:#fff; word-break:break-all;">
                            <?php echo esc_html((string)$change['file']); ?>
                        </td>
                        <td style="color:var(--vgt-text-dim); font-size:12px;">
                            <?php echo esc_html((string)$change['desc']); ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:inline-flex; gap:8px;">
                                <button type="button" class="vgt-btn vgt-btn-ghost vis-inspect-file" data-file="<?php echo esc_attr((string)$change['file']); ?>" style="padding:6px 10px; color:var(--vgt-text-main); border-color:var(--vgt-border);">
                                    <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    <?php esc_html_e('VIEW', 'vgt-sentinel'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- VGT SECURE CODE VIEWER MODAL -->
    <div id="vis-source-modal" class="vis-modal-backdrop" style="display: none;">
        <div class="vis-modal-content" style="max-width: 900px; width: 90%;">
            <div class="vis-modal-header" style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(5,5,5,0.4);">
                <div class="vis-modal-title" id="vis-source-title" style="font-family: 'Orbitron', monospace; font-size: 14px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-editor-code" style="color:var(--vgt-neon-green, #10b981);"></span>
                    <?php esc_html_e('SOURCE VIEWER', 'vgt-sentinel'); ?>
                </div>
                <button type="button" class="vis-modal-close" id="vis-source-close" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 20px;"><span class="dashicons dashicons-no-alt"></span></button>
            </div>
            <div class="vis-modal-body" style="padding: 20px; background: rgba(2, 4, 10, 0.95);">
                <pre id="vis-source-code" style="margin: 0; padding: 15px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; overflow: auto; max-height: 55vh; font-family: 'Fira Code', 'JetBrains Mono', monospace; font-size: 12px; color: #e2e8f0; line-height: 1.6; text-align: left; user-select: text; white-space: pre; word-wrap: normal;"></pre>
            </div>
            <div class="vis-modal-footer" style="padding: 15px 20px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: flex-end; background: rgba(5,5,5,0.4);">
                <button type="button" class="vgt-btn vgt-btn-ghost" id="vis-source-ok" style="padding: 8px 20px; border-color: rgba(255,255,255,0.1); color: #fff; font-family: 'Orbitron', sans-serif; font-size: 11px; letter-spacing: 1px;"><?php esc_html_e('SCHLIESSEN', 'vgt-sentinel'); ?></button>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $(document).on('click', '.vis-inspect-file', function(e) {
            e.preventDefault();
            var file = $(this).data('file');
            var $btn = $(this);
            var originalHtml = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-right: 4px;"></span>...');
            
            $.post(ajaxurl, {
                action: 'vis_inspect_file',
                file: file,
                nonce: '<?php echo esc_js(wp_create_nonce("vis_nonce")); ?>'
            }, function(res) {
                $btn.prop('disabled', false).html(originalHtml);
                if (res.success) {
                    var titleNode = document.getElementById('vis-source-title');
                    var codeNode = document.getElementById('vis-source-code');
                    if (titleNode) {
                        var icon = document.createElement('span');
                        icon.className = 'dashicons dashicons-editor-code';
                        icon.style.color = '#10b981';
                        titleNode.replaceChildren(icon, document.createTextNode(' ' + res.data.filename + ' (' + res.data.path + ')'));
                    }
                    if (codeNode) {
                        codeNode.textContent = res.data.content;
                    }
                    $('#vis-source-modal').show();
                    setTimeout(function() {
                        $('#vis-source-modal').addClass('vis-show');
                    }, 50);
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Fehler beim Laden der Datei.');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(originalHtml);
                alert('Netzwerkfehler: Verbindung zum Server fehlgeschlagen.');
            });
        });
        
        function hideSourceModal() {
            $('#vis-source-modal').removeClass('vis-show');
            setTimeout(function() {
                $('#vis-source-modal').hide();
            }, 300);
        }
        
        $('#vis-source-close, #vis-source-ok').off('click').on('click', function(e) {
            e.preventDefault();
            hideSourceModal();
        });
        
        // Escape modal with ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#vis-source-modal').is(':visible')) {
                hideSourceModal();
            }
        });
    });
    </script>
</div>
