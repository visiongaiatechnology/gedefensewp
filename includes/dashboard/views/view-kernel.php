<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

$vault_dir = defined('WP_CONTENT_DIR') ? wp_normalize_path(WP_CONTENT_DIR . '/vgt-vault/zeus') : dirname(ABSPATH) . '/wp-content/vgt-vault/zeus';
$aegis_file = $vault_dir . '/aegis-signal.json';

$aegis_status = 'OFFLINE'; 
$aegis_data = [];
$is_critical = false;
$last_signal = __('UNREACHABLE', 'vgt-sentinel');

if (file_exists($aegis_file)) {
    $json = file_get_contents($aegis_file);
    $data = json_decode($json, true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($data['status'])) {
        $aegis_status = $data['status'];
        $aegis_data = $data;
        $last_signal = isset($data['timestamp']) ? wp_date('H:i:s', strtotime($data['timestamp'])) : __('UNKNOWN_SYNC', 'vgt-sentinel');
        if ($aegis_status === 'CRITICAL') {
            $is_critical = true;
        }
    } else {
        $aegis_status = 'CORRUPTED'; 
    }
}

$bash_template = <<<'BASH'
#!/bin/bash
# ==========================================================
# VISIONGAIA AEGIS UPLINK | KERNEL TO WEB BRIDGE
# STATUS: DIAMANT VGT SUPREME
# ==========================================================
set -euo pipefail

VAULT_DIR="{{VAULT_DIR}}"
VAULT_FILE="$VAULT_DIR/aegis-signal.json"

mkdir -p "$VAULT_DIR"
WEB_USER=$(stat -c '%U:%G' "$VAULT_DIR")

EVENT=$(ausearch -ts recent -k modules -k identity_alert -k net_alert -i 2>/dev/null | tail -n 20 || true)
DATE=$(date "+%Y-%m-%d %H:%M:%S")
INCIDENT_ID=$(date +%s)

if [ -n "$EVENT" ]; then
    SAFE_EVENT=$(echo "$EVENT" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))')
    cat <<EOF > "$VAULT_FILE"
{
    "status": "CRITICAL",
    "incident_id": "$INCIDENT_ID",
    "timestamp": "$DATE",
    "alert_type": "KERNEL_INTEGRITY_EVENT",
    "details": "Auditd hat eine Integritätsverletzung registriert.",
    "payload": $SAFE_EVENT
}
EOF
    chown "$WEB_USER" "$VAULT_FILE"
    chmod 640 "$VAULT_FILE"
else
    if grep -q '"status": "CRITICAL"' "$VAULT_FILE" 2>/dev/null; then
        :
    else
        cat <<EOF > "$VAULT_FILE"
{
    "status": "SECURE",
    "timestamp": "$DATE",
    "alert_type": "HEARTBEAT",
    "details": "VGT Kernel-Watchdog aktiv. Keine Anomalien.",
    "raw": "Kernel-Audit-Status: Nominal"
}
EOF
        chown "$WEB_USER" "$VAULT_FILE"
        chmod 640 "$VAULT_FILE"
    fi
fi
BASH;

$final_bash_script = str_replace('{{VAULT_DIR}}', $vault_dir, $bash_template);
?>

<section class="vgt-titan" aria-label="Kernel Uplink Watchdog">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('HOST-LEVEL AUDITD BRIDGE & KERNEL WATCHDOG', 'vgt-sentinel'); ?></p>
            <h2>KERNEL UPLINK</h2>
            <p><?php esc_html_e('Direkte Brücke zwischen Linux-Kernel-Auditd und WordPress zur Erkennung von Rootkits, Modul-Manipulationen und Host-Eindringlingen.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Kernel Uplink Status">
            <span><small><?php esc_html_e('WATCHDOG SIGNAL', 'vgt-sentinel'); ?></small><strong><?php echo esc_html($aegis_status); ?></strong></span>
            <span><small><?php esc_html_e('HEARTBEAT', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$last_signal); ?></strong></span>
            <span><small><?php esc_html_e('LATCH DEFENSE', 'vgt-sentinel'); ?></small><strong><?php echo $is_critical ? esc_html__('ALERT', 'vgt-sentinel') : esc_html__('NOMINAL', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('BRIDGE VERSION', 'vgt-sentinel'); ?></small><strong>V2.1 SUPREME</strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('WATCHDOG STATE', 'vgt-sentinel'); ?></small><strong><?php echo esc_html($aegis_status); ?></strong></article>
        <article><small><?php esc_html_e('LAST TELEMETRY SYNC', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo esc_html((string)$last_signal); ?></strong></article>
        <article><small><?php esc_html_e('LATCH STATUS', 'vgt-sentinel'); ?></small><strong><?php echo $is_critical ? esc_html__('ALERT', 'vgt-sentinel') : esc_html__('NOMINAL', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('HOST DAEMON', 'vgt-sentinel'); ?></small><strong>SYSTEMD / CRON</strong></article>
        <article><small><?php esc_html_e('STORAGE VAULT', 'vgt-sentinel'); ?></small><strong>JSON SPOOL</strong></article>
        <article><small><?php esc_html_e('ROOTKIT DEFENSE', 'vgt-sentinel'); ?></small><strong>ACTIVE</strong></article>
    </div>

    <!-- BASH DAEMON PANEL -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('01 / HOST BRIDGE DAEMON', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Automated Host Daemon Script (/usr/local/bin/vgt-uplink.sh)', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php esc_html_e('SHELL / AUDITD', 'vgt-sentinel'); ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:12px; flex-wrap:wrap;">
            <p style="margin:0;"><?php esc_html_e('Führe dieses Skript periodisch auf deinem Linux-Host per Systemd Timer oder Cronjob aus, um Kernel-Watchdog-Events direkt an WordPress zu übermitteln.', 'vgt-sentinel'); ?></p>
            <button type="button" id="btn-copy-kernel-script" class="vis-btn vis-btn-primary" style="flex-shrink:0;"><?php esc_html_e('SKRIPT KOPIEREN', 'vgt-sentinel'); ?></button>
        </div>
        <pre><code id="vgt-kernel-bash-code"><?php echo esc_html($final_bash_script); ?></code></pre>
    </section>
</section>
