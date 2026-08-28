<?php 
declare(strict_types=1);
/**
 * VISIONGAIA AEGIS UPLINK | KERNEL TO WEB BRIDGE (V2.1)
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 * ARCHITECTURE: MODULAR STATE REACTIVE (SVG APEX UI)
 */

if (!defined('ABSPATH')) exit; 

// --- 1. CORE SYSTEM PATHS & LOGIC (STRICT 1:1) ---
$upload_dir = wp_upload_dir();
$vault_dir_absolute = wp_normalize_path($upload_dir['basedir'] . '/vis-vault');
$aegis_file = $vault_dir_absolute . '/aegis-signal.json';

// --- 2. STATE DEFINITION ---
$aegis_status = 'OFFLINE'; 
$aegis_data = [];
$is_critical = false;
$last_signal = __('UNREACHABLE', 'vgt-sentinel');

// --- 3. TELEMETRY READOUT ---
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

// --- 4. DYNAMIC BASH PAYLOAD GENERATION (NOWDOC) ---
$bash_template = <<<'BASH'
#!/bin/bash
# ==========================================================
# VISIONGAIA AEGIS UPLINK | KERNEL TO WEB BRIDGE (V2.1)
# Status: DIAMANT VGT SUPREME
# ==========================================================
set -euo pipefail

VAULT_DIR="{{VAULT_DIR}}"
VAULT_FILE="$VAULT_DIR/aegis-signal.json"

# Sicherstellen, dass das Verzeichnis existiert
mkdir -p "$VAULT_DIR"
WEB_USER=$(stat -c '%U:%G' "$VAULT_DIR")

# 1. Prüfe Audit Logs
EVENT=$(ausearch -ts recent -k modules -k identity_alert -k net_alert -i 2>/dev/null | tail -n 20 || true)
DATE=$(date "+%Y-%m-%d %H:%M:%S")
INCIDENT_ID=$(date +%s)

if [ -n "$EVENT" ]; then
    # --- ALARM ---
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
    # --- HEARTBEAT ---
    if grep -q '"status": "CRITICAL"' "$VAULT_FILE" 2>/dev/null; then
        : # Alarm bleibt im "Latched"-Modus
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

$final_bash_script = str_replace('{{VAULT_DIR}}', $vault_dir_absolute, $bash_template);

$auditd_rules = <<<'RULES'
# Überwachung von Kernel-Modulen (Rootkit-Schutz)
-w /sbin/insmod -p x -k modules
-w /sbin/rmmod -p x -k modules

# Überwachung kritischer Systemdateien
-w /etc/shadow -p wa -k identity_alert
-w /etc/passwd -p wa -k identity_alert

# Überwachung der Netzwerk-Konfiguration
-w /etc/hosts -p wa -k net_alert
RULES;

// --- 5. UI/UX STATE MACHINE ---
$card_style = 'border-top: 3px solid var(--vis-border);';
$dot_class = 'vis-dot-gray'; 
$icon_color = 'var(--vis-text-secondary)';
$title_color = 'var(--vis-text-primary)';

if ($aegis_status === 'SECURE') {
    $card_style = 'border-top: 3px solid var(--vis-success); box-shadow: 0 4px 20px rgba(16, 185, 129, 0.05);'; 
    $dot_class = 'vis-dot-green vis-pulse-anim';
    $icon_color = 'var(--vis-success)';
} elseif ($is_critical) {
    $card_style = 'border-color:var(--vis-danger); background:rgba(239,68,68,0.05); box-shadow: 0 4px 20px rgba(239, 68, 68, 0.1);';
    $dot_class = 'vis-dot-red vis-blink-anim';
    $icon_color = 'var(--vis-danger)';
    $title_color = 'var(--vis-danger)';
} elseif ($aegis_status === 'OFFLINE') {
    $card_style = 'border-top: 3px solid var(--vis-warning); background:rgba(245, 158, 11, 0.02);';
    $dot_class = 'vis-dot-yellow vis-blink-anim';
    $icon_color = 'var(--vis-warning)';
}
?>

<!-- =========================================================================================
     ASSET INJECTION (CSS)
     ========================================================================================= -->
<style>
    <?php 
    $kernel_css_path = __DIR__ . '/kernel/style.css';
    if (is_readable($kernel_css_path)) {
        echo file_get_contents($kernel_css_path);
    }
    ?>
</style>

<div class="vis-aegis-module" style="<?php echo esc_attr($card_style); ?>">
    <!-- HEADER -->
    <div class="vis-header">
        <div style="display:flex; align-items:center; gap:12px;">
            <svg class="vgt-icon" style="color:<?php echo esc_attr($icon_color); ?>; width:22px; height:22px;" viewBox="0 0 24 24">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="M9 12l2 2 4-4"></path>
            </svg>
            <h3 style="margin:0; font-size:16px; font-weight:600; color:<?php echo esc_attr($title_color); ?>;"><?php esc_html_e('AEGIS KERNEL UPLINK', 'vgt-sentinel'); ?></h3>
        </div>
        
        <div class="vis-badge">
            <span style="color:var(--vis-text-secondary);"><?php esc_html_e('STATUS:', 'vgt-sentinel'); ?></span> 
            <span style="color:<?php echo esc_attr($icon_color); ?>;"><?php echo esc_html($aegis_status); ?></span>
            <div class="vis-dot <?php echo esc_attr($dot_class); ?>"></div>
        </div>
    </div>

    <!-- BODY -->
    <div class="vis-body">
        
        <?php if ($aegis_status === 'SECURE'): ?>
            <!-- STATE: SECURE -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom: 20px;">
                <div style="background:rgba(255,255,255,0.03); padding:20px; border-radius:10px; border:1px solid var(--vis-border);">
                    <div style="font-size:11px; color:var(--vis-text-secondary); margin-bottom:8px; letter-spacing:1px;"><?php esc_html_e('AUDITD DAEMON', 'vgt-sentinel'); ?></div>
                    <div style="font-size:18px; font-weight:600; color:#fff;"><?php esc_html_e('ONLINE / SYNCED', 'vgt-sentinel'); ?></div>
                </div>
                <div style="background:rgba(255,255,255,0.03); padding:20px; border-radius:10px; border:1px solid var(--vis-border);">
                    <div style="font-size:11px; color:var(--vis-text-secondary); margin-bottom:8px; letter-spacing:1px;"><?php esc_html_e('KERNEL INTEGRITY', 'vgt-sentinel'); ?></div>
                    <div style="color:var(--vis-success); font-size:18px; font-weight:600;"><?php esc_html_e('SECURE', 'vgt-sentinel'); ?></div>
                </div>
            </div>
            <div style="font-size:13px; color:var(--vis-text-secondary); display:flex; justify-content:space-between; border-top:1px solid var(--vis-border); padding-top:15px;">
                <span><?php esc_html_e('Echtzeit-Telemetrie aktiv. Keine Anomalien detektiert.', 'vgt-sentinel'); ?></span>
                <span style="font-family:monospace; color:#3b82f6;"><?php printf(esc_html__('LAST SYNC: %s', 'vgt-sentinel'), esc_html($last_signal)); ?></span>
            </div>

        <?php elseif ($is_critical): ?>
            <!-- STATE: CRITICAL -->
            <div style="text-align:center; padding:20px 0;">
                <svg class="vgt-icon" style="color:var(--vis-danger); width:48px; height:48px; margin:0 auto 15px auto;" viewBox="0 0 24 24">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <div style="color:var(--vis-danger); font-size:24px; font-weight:800; letter-spacing:1px; margin-bottom:10px;"><?php esc_html_e('KERNEL BREACH DETECTED', 'vgt-sentinel'); ?></div>
            </div>
            
            <div style="background:rgba(239,68,68,0.1); padding:20px; border-radius:8px; border:1px solid rgba(239,68,68,0.2); margin-bottom:20px; font-family:monospace; font-size:13px;">
                <div style="margin-bottom:8px;"><span style="color:#fca5a5;"><?php esc_html_e('TIME:', 'vgt-sentinel'); ?></span> <?php echo esc_html($aegis_data['timestamp']); ?></div>
                <div style="margin-bottom:8px;"><span style="color:#fca5a5;"><?php esc_html_e('TYPE:', 'vgt-sentinel'); ?></span> <?php echo esc_html($aegis_data['alert_type']); ?></div>
                <?php if(isset($aegis_data['details'])): ?>
                    <div style="margin-bottom:8px;"><span style="color:#fca5a5;"><?php esc_html_e('INFO:', 'vgt-sentinel'); ?></span> <?php echo esc_html($aegis_data['details']); ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align:center; font-size:12px; color:var(--vis-text-secondary);">
                <?php esc_html_e('Alarm muss per SSH-Root-Zugang durch Löschen der Datei aegis-signal.json zurückgesetzt werden.', 'vgt-sentinel'); ?>
            </div>

        <?php else: ?>
            <!-- STATE: OFFLINE (DEPLOYMENT TERMINAL) -->
            <div style="background:rgba(245, 158, 11, 0.05); border:1px solid rgba(245, 158, 11, 0.2); border-radius:8px; padding:15px; margin-bottom:25px; display:flex; gap:15px; align-items:flex-start;">
                <svg class="vgt-icon" style="color:var(--vis-warning); width:24px; height:24px; flex-shrink:0;" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <div>
                    <h4 style="margin:0 0 5px 0; color:var(--vis-warning); font-size:14px;"><?php esc_html_e('Uplink nicht initialisiert', 'vgt-sentinel'); ?></h4>
                    <p style="margin:0; font-size:12px; color:var(--vis-text-secondary); line-height:1.5;"><?php esc_html_e('Der Server sendet aktuell keine Telemetrie-Daten. Führe die folgenden Schritte als root auf deinem Server aus. Der Pfad wurde bereits dynamisch angepasst.', 'vgt-sentinel'); ?></p>
                </div>
            </div>

            <!-- STEP 1 -->
            <div class="vis-step-title"><span class="vis-step-num">1</span> <?php esc_html_e('Auditd installieren & konfigurieren', 'vgt-sentinel'); ?></div>
            <div class="vis-code-wrapper">
                <button type="button" class="vis-copy-btn" onclick="visCopyCode(this, 'code-step-1')">
                    <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <?php esc_html_e('Kopieren', 'vgt-sentinel'); ?>
                </button>
                <pre id="code-step-1">apt update && apt install auditd audispd-plugins -y</pre>
            </div>
            
            <p style="font-size:12px; color:var(--vis-text-secondary); margin-bottom:10px;"><?php esc_html_e('Füge diese Regeln ans Ende der Datei /etc/audit/rules.d/audit.rules ein und starte den Service neu:', 'vgt-sentinel'); ?></p>
            <div class="vis-code-wrapper">
                <button type="button" class="vis-copy-btn" onclick="visCopyCode(this, 'code-step-1-rules')">
                    <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <?php esc_html_e('Kopieren', 'vgt-sentinel'); ?>
                </button>
                <pre id="code-step-1-rules"><?php echo esc_html($auditd_rules); ?></pre>
            </div>

            <!-- STEP 2 -->
            <div class="vis-step-title"><span class="vis-step-num">2</span> <?php esc_html_e('Aegis Bridge Script anlegen', 'vgt-sentinel'); ?></div>
            <p style="font-size:12px; color:var(--vis-text-secondary); margin-top:5px; margin-bottom:10px;"><?php esc_html_e('Erstelle die Datei /root/visiongaia_aegis.sh und füge diesen Code ein:', 'vgt-sentinel'); ?></p>
            <div class="vis-code-wrapper">
                <button type="button" class="vis-copy-btn" onclick="visCopyCode(this, 'code-step-2')">
                    <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <?php esc_html_e('Kopieren', 'vgt-sentinel'); ?>
                </button>
                <pre id="code-step-2"><?php echo esc_html($final_bash_script); ?></pre>
            </div>

            <!-- STEP 3 -->
            <div class="vis-step-title"><span class="vis-step-num">3</span> <?php esc_html_e('Ausführbar machen & Cronjob setzen', 'vgt-sentinel'); ?></div>
            <div class="vis-code-wrapper">
                <button type="button" class="vis-copy-btn" onclick="visCopyCode(this, 'code-step-3')">
                    <svg class="vgt-icon" style="width:14px; height:14px;" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <?php esc_html_e('Kopieren', 'vgt-sentinel'); ?>
                </button>
                <pre id="code-step-3">chmod +x /root/visiongaia_aegis.sh
(crontab -l 2>/dev/null; echo "* * * * * /root/visiongaia_aegis.sh") | crontab -</pre>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- =========================================================================================
     ASSET INJECTION (JAVASCRIPT VIA INCLUDE)
     ========================================================================================= -->
<script>
    <?php 
    $kernel_js_path = __DIR__ . '/kernel/script.js';
    if (is_readable($kernel_js_path)) {
        include $kernel_js_path;
    }
    ?>
</script>
