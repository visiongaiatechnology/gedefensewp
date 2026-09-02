<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: LOGS
 * MODULE: OMNI-CHANNEL TELEMETRY AGGREGATION
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; 
global $wpdb;

// =========================================================================================
// 1. LOGIK CORE (OMNI-CHANNEL TELEMETRY MERGE) - STRICT 1:1
// =========================================================================================

$suppress = $wpdb->suppress_errors(true);

// Alle 4 VGT Telemetrie-Silos definieren
$table_omega      = $wpdb->prefix . 'vis_omega_logs';
$table_aegis      = $wpdb->prefix . 'vis_aegis_logs';
$table_prometheus = $wpdb->prefix . 'vis_prometheus_logs';
$table_nemesis    = $wpdb->prefix . 'vis_nemesis_logs';

$formatted_logs = [];

// 1. OMEGA CORE LOGS LADEN (Zeus, Styx, Hades, Titan)
if ($wpdb->get_var("SHOW TABLES LIKE '$table_omega'") === $table_omega) {
    $raw_omega = $wpdb->get_results("SELECT * FROM $table_omega ORDER BY timestamp DESC LIMIT 50");
    if ($raw_omega) {
        foreach ($raw_omega as $row) {
            $formatted_logs[] = (object) [
                'timestamp' => $row->timestamp,
                'module'    => $row->module,
                'ip'        => $row->ip ?? ($row->ip_address ?? 'UNKNOWN'),
                'message'   => $row->message ?? ($row->details ?? $row->type),
                'source'    => 'sentinel'
            ];
        }
    }
}

// 2. AEGIS NATIVE LOGS LADEN
if ($wpdb->get_var("SHOW TABLES LIKE '$table_aegis'") === $table_aegis) {
    $raw_aegis = $wpdb->get_results("SELECT * FROM $table_aegis ORDER BY timestamp DESC LIMIT 50");
    if ($raw_aegis) {
        foreach ($raw_aegis as $row) {
            $formatted_logs[] = (object) [
                'timestamp' => $row->timestamp,
                'module'    => $row->module ?? 'AEGIS',
                'ip'        => $row->ip ?? ($row->ip_address ?? 'UNKNOWN'),
                'message'   => $row->message ?? ($row->details ?? $row->type),
                'source'    => 'sentinel'
            ];
        }
    }
}

// 3. PROMETHEUS LOGS LADEN (Behavioral AI)
if ($wpdb->get_var("SHOW TABLES LIKE '$table_prometheus'") === $table_prometheus) {
    $raw_prom = $wpdb->get_results("SELECT * FROM $table_prometheus ORDER BY timestamp DESC LIMIT 50");
    if ($raw_prom) {
        foreach ($raw_prom as $row) {
            $formatted_logs[] = (object) [
                'timestamp' => $row->timestamp,
                'module'    => 'PROMETHEUS',
                'ip'        => $row->ip_address,
                'message'   => '[' . $row->type . '] ' . $row->details,
                'source'    => 'prometheus'
            ];
        }
    }
}

// 4. NEMESIS LOGS LADEN (Deception Engine)
if ($wpdb->get_var("SHOW TABLES LIKE '$table_nemesis'") === $table_nemesis) {
    $raw_nem = $wpdb->get_results("SELECT * FROM $table_nemesis ORDER BY timestamp DESC LIMIT 50");
    if ($raw_nem) {
        foreach ($raw_nem as $row) {
            $formatted_logs[] = (object) [
                'timestamp' => $row->timestamp,
                'module'    => 'NEMESIS',
                'ip'        => $row->ip_address,
                'message'   => '[' . $row->type . '] ' . $row->details,
                'source'    => 'nemesis'
            ];
        }
    }
}

$wpdb->suppress_errors($suppress);

// 5. MERGE, SORT & LIMIT
usort($formatted_logs, function($a, $b) {
    return strtotime($b->timestamp) - strtotime($a->timestamp);
});

$final_logs = array_slice($formatted_logs, 0, 50);
?>

<!-- =========================================================================================
     2. DECENTRALIZED ASSET INJECTION (CSS)
     ========================================================================================= -->
<!-- =========================================================================================
     3. VIEW CONTENT
     ========================================================================================= -->
<div class="vgt-apex-ui">

    <div class="vgt-glass-panel" style="border-top: 3px solid var(--vgt-neon-cyan);">
        
        <!-- MODULE HEADER -->
        <div class="vgt-module-header">
            <div class="vgt-module-title">
                <div style="background:rgba(0, 242, 255, 0.1); padding:12px; border-radius:8px; border:1px solid rgba(0, 242, 255, 0.3); display: flex;">
                    <svg class="vgt-icon" style="color:var(--vgt-neon-cyan); width:24px; height:24px;" viewBox="0 0 24 24">
                        <line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                </div>
                <div>
                    <h2><?php esc_html_e('GLOBAL SECURITY EVENTS', 'vgt-sentinel'); ?></h2>
                    <div style="font-size:12px; color:var(--vgt-text-dim); margin-top:4px; font-family:monospace; display:flex; align-items:center; gap:8px;">
                        <?php esc_html_e('Log Aggregation:', 'vgt-sentinel'); ?>
                        <span class="vgt-is-active" style="display:inline-flex; align-items:center; gap:6px;">
                            <span class="vgt-status-pulse"></span>
                            <strong style="color:var(--vgt-neon-green); letter-spacing:0.5px;"><?php esc_html_e('OMNI-CHANNEL ACTIVE', 'vgt-sentinel'); ?></strong>
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="font-size:10px; font-weight:900; color:var(--vgt-text-muted); letter-spacing:1.5px; background:rgba(0,0,0,0.4); padding:8px 16px; border-radius:4px; border: 1px solid var(--vgt-border);">
                <?php printf(esc_html__('SHOWING TOP %d EVENTS', 'vgt-sentinel'), (int)count($final_logs)); ?>
            </div>
        </div>

        <!-- EVENT LOG DATA TABLE -->
        <?php if(empty($final_logs)): ?>
            <!-- CLEAN STATE -->
            <div class="vgt-state-clean">
                <svg class="vgt-icon vgt-state-clean-icon" viewBox="0 0 24 24">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <h3><?php esc_html_e('SYSTEM CLEAN', 'vgt-sentinel'); ?></h3>
                <p><?php esc_html_e('Keine Sicherheitsvorfälle im Protokoll verzeichnet. Die VGT Intelligence Engine überwacht den Perimeter weiterhin aktiv auf Anomalien.', 'vgt-sentinel'); ?></p>
            </div>
        <?php else: ?>
            <!-- DATA GRID -->
            <div class="vgt-table-container">
                <table class="vgt-data-table">
                    <thead>
                        <tr>
                            <th width="180"><?php esc_html_e('TIMESTAMP (UTC)', 'vgt-sentinel'); ?></th>
                            <th width="180"><?php esc_html_e('SOURCE / MODULE', 'vgt-sentinel'); ?></th>
                            <th width="160"><?php esc_html_e('IP ADDRESS', 'vgt-sentinel'); ?></th>
                            <th><?php esc_html_e('EVENT DETAILS', 'vgt-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($final_logs as $log): 
                        
                        // APEX Styling Logic per Source
                        $badge_class = 'vgt-badge-blue';
                        $ip_color    = 'var(--vgt-neon-blue)';
                        $row_style   = '';

                        if ($log->source === 'prometheus') {
                            $badge_class = 'vgt-badge-cyan';
                            $ip_color    = 'var(--vgt-neon-cyan)';
                            $row_style   = 'border-left: 2px solid var(--vgt-neon-cyan); background: rgba(0, 242, 255, 0.015);';
                        } elseif ($log->source === 'nemesis') {
                            $badge_class = 'vgt-badge-purple';
                            $ip_color    = 'var(--vgt-neon-purple)';
                            $row_style   = 'border-left: 2px solid var(--vgt-neon-purple); background: rgba(188, 19, 254, 0.015);';
                        } elseif ($log->source === 'sentinel') {
                            $badge_class = 'vgt-badge-green';
                            $ip_color    = 'var(--vgt-neon-green)';
                            $row_style   = 'border-left: 2px solid var(--vgt-neon-green); background: rgba(0, 255, 170, 0.015);';
                        }
                    ?>
                        <tr style="<?php echo esc_attr($row_style); ?>">
                            <td class="vgt-text-mono" style="color:var(--vgt-text-dim);">
                                <?php echo esc_html((string)$log->timestamp); ?>
                            </td>
                            <td>
                                <span class="vgt-badge <?php echo esc_attr($badge_class); ?>">
                                    <?php echo esc_html((string)$log->module); ?>
                                </span>
                            </td>
                            <td class="vgt-text-mono" style="color:<?php echo esc_attr($ip_color); ?>; font-weight:800;">
                                <?php echo esc_html((string)$log->ip); ?>
                            </td>
                            <td style="color:#e2e8f0; line-height:1.5; font-size:13px;">
                                <?php echo esc_html((string)$log->message); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>
