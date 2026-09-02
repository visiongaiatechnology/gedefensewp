<?php
/**
 * VISIONGAIATECHNOLOGY SENTINEL
 * COMPONENT: VISUAL INTERFACE (MYRMIDON VAULT)
 * ARCHITECTURE: MVC VIEW LAYER / SECURE ADMIN CONTEXT
 * SECURITY LEVEL: HIGH (Admin-Only)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // SILENCE IS GOLDEN
}

// 1. KERNEL INITIALISIERUNG
// Zugriff auf die Singleton-Instanz der Core-Klasse
$myrmidon = VIS_Myrmidon::get_instance();
$msg = '';
$msg_type = 'success';

// 2. ACTION CONTROLLER (POST HANDLER)
// Verarbeitet Benutzereingaben strikt und atomar
if ( isset( $_POST['vis_action'] ) && check_admin_referer( 'vis_action_nonce' ) ) {
    
    // Capability Check: Nur Admins dürfen den Vault bedienen
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'ZUGRIFF VERWEIGERT: Insufficient Permissions.' );
    }

    $did = sanitize_text_field( $_POST['device_id'] );
    
    switch ( $_POST['vis_action'] ) {
        case 'approve':
            $myrmidon->approve_device( $did );
            $msg = "GERÄT AUTORISIERT. Kryptographischer Handshake freigegeben.";
            break;
        case 'override':
            $myrmidon->override_device( $did );
            $msg = "SECURITY OVERRIDE: Vertrauensstatus manuell erzwungen (Audit Logged).";
            $msg_type = 'warning';
            break;
        case 'delete':
        case 'deny':
            $myrmidon->delete_device( $did );
            $msg = "Gerät permanent aus dem Ledger entfernt. Schlüssel vernichtet.";
            break;
        default:
            $msg = "Unbekannte Operation.";
            $msg_type = 'error';
    }
}

// 3. DATA AGGREGATION
// Abruf der Rohdaten aus dem geschützten Ledger
$devices_all = $myrmidon->get_all_devices();

// Realtime-Statistik Berechnung
$stats = [
    'total' => count( $devices_all ),
    'pending' => 0,
    'compromised' => 0,
    'secure' => 0
];

foreach ( $devices_all as $d ) {
    if ( $d['status'] === 'pending' ) {
        $stats['pending']++;
    } else {
        $score = (int)$d['integrity_score'];
        if ( $score >= 90 || $d['override_trust'] ) $stats['secure']++;
        elseif ( $score < 50 ) $stats['compromised']++;
    }
}

// System Status Check
$keys = get_option( '_vis_myrmidon_server_keys' );
$fingerprint = $keys ? hash( 'sha256', base64_decode( $keys['public'] ) ) : 'SYSTEM_NOT_INITIALIZED';
$fingerprint_fmt = wordwrap( strtoupper( $fingerprint ), 4, ' ', true );
$sodium_state = extension_loaded( 'sodium' );

// 4. HELPER FUNCTIONS (VIEW SCOPE)
if ( ! function_exists( 'vis_render_audit_item' ) ) {
    function vis_render_audit_item( string $label, bool $status, bool $critical = false ): string {
        $icon = $status ? 'dashicons-yes' : 'dashicons-no';
        $color = $status ? '#00e676' : ( $critical ? '#ff1744' : '#ff9100' );
        $text_class = $status ? 'vis-audit-ok' : ( $critical ? 'vis-audit-fail' : 'vis-audit-warn' );
        $status_text = $status ? 'SICHER' : 'FEHLER';
        
        return sprintf(
            '<div class="vis-audit-item"><span class="dashicons %s" style="color:%s"></span><span class="vis-audit-label">%s</span><span class="%s">%s</span></div>',
            esc_attr( $icon ),
            esc_attr( $color ),
            esc_html( $label ),
            esc_attr( $text_class ),
            esc_html( $status_text )
        );
    }
}

if ( ! function_exists( 'vis_get_os_icon' ) ) {
    function vis_get_os_icon( string $os ): string {
        $os = strtolower( $os );
        if ( strpos( $os, 'win' ) !== false ) return 'dashicons-desktop';
        if ( strpos( $os, 'android' ) !== false ) return 'dashicons-smartphone';
        if ( strpos( $os, 'linux' ) !== false ) return 'dashicons-rest-api';
        if ( strpos( $os, 'mac' ) !== false || strpos( $os, 'ios' ) !== false ) return 'dashicons-apple';
        return 'dashicons-laptop';
    }
}
?>

<!-- 5. RENDER UI (VISIONGAIATECHNOLOGY THEME) -->
<div class="wrap vis-dashboard-wrap">
    
    <!-- NOTIFICATIONS -->
    <?php if ( $msg ) : ?>
        <div class="vis-alert <?php echo $msg_type === 'success' ? 'vis-alert-success' : 'vis-alert-warning'; ?>">
            <span class="dashicons dashicons-info"></span> <?php echo esc_html( $msg ); ?>
        </div>
    <?php endif; ?>

    <!-- HEADER DASHBOARD -->
    <div class="vis-header-grid">
        <div class="vis-card vis-card-glow">
            <h3><span class="dashicons dashicons-database"></span> MYRMIDON LEDGER</h3>
            <div class="vis-stat-grid">
                <div class="vis-stat">
                    <span class="vis-stat-val"><?php echo esc_html( $stats['total'] ); ?></span>
                    <span class="vis-stat-label">VAULT ENTRIES</span>
                </div>
                <div class="vis-stat">
                    <span class="vis-stat-val" style="color: <?php echo $sodium_state ? '#00f2ff' : '#ff0033'; ?>">
                        <?php echo $sodium_state ? 'AES-256' : 'CRITICAL'; ?>
                    </span>
                    <span class="vis-stat-label">CRYPTO ENGINE</span>
                </div>
                <?php if ( $stats['pending'] > 0 ) : ?>
                <div class="vis-stat vis-stat-alert">
                    <span class="vis-stat-val blink" style="color: #f59e0b;"><?php echo esc_html( $stats['pending'] ); ?></span>
                    <span class="vis-stat-label">ACTION REQ.</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="vis-card">
            <h3><span class="dashicons dashicons-fingerprint"></span> SERVER IDENTITÄT</h3>
            <p class="vis-desc">SHA-256 Fingerprint (Anti-MITM Verification):</p>
            <div class="vis-fingerprint-box">
                <?php echo esc_html( $fingerprint_fmt ); ?>
            </div>
        </div>
    </div>

    <!-- PENDING APPROVAL QUEUE -->
    <?php if ( $stats['pending'] > 0 ) : ?>
    <div class="vis-card vis-queue-container">
        <div class="vis-card-header vis-header-warn">
            <h3><span class="dashicons dashicons-flag"></span> FREIGABE ERFORDERLICH (<?php echo esc_html( $stats['pending'] ); ?>)</h3>
        </div>
        <table class="vis-table">
            <thead>
                <tr>
                    <th>GERÄT / FINGERPRINT</th>
                    <th>BENUTZER KONTEXT</th>
                    <th>ZEITSTEMPEL</th>
                    <th style="text-align:right;">COMMAND</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $devices_all as $d ) : 
                if ( $d['status'] !== 'pending' ) continue; ?>
                <tr>
                    <td>
                        <strong class="vis-text-highlight"><?php echo esc_html( $d['device_name'] ); ?></strong><br>
                        <span class="vis-meta mono"><?php echo esc_html( $d['os_type'] ); ?> :: <?php echo esc_html( substr( $d['device_id'], 0, 12 ) ); ?>...</span>
                    </td>
                    <td class="vis-text-light">
                        <?php 
                        $user_info = get_userdata( $d['user_id'] );
                        echo $user_info ? esc_html( $user_info->user_login ) : 'Unknown User';
                        ?> <span class="vis-meta">(ID: <?php echo esc_html( $d['user_id'] ); ?>)</span>
                    </td>
                    <td class="vis-meta"><?php echo human_time_diff( strtotime( $d['created_at'] ) ); ?> ago</td>
                    <td style="text-align:right;">
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field( 'vis_action_nonce' ); ?>
                            <input type="hidden" name="vis_action" value="approve">
                            <input type="hidden" name="device_id" value="<?php echo esc_attr( $d['device_id'] ); ?>">
                            <button class="vis-btn vis-btn-success">ZULASSEN</button>
                        </form>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field( 'vis_action_nonce' ); ?>
                            <input type="hidden" name="vis_action" value="deny">
                            <input type="hidden" name="device_id" value="<?php echo esc_attr( $d['device_id'] ); ?>">
                            <button class="vis-btn vis-btn-danger">ABLEHNEN</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- MAIN SECURE LEDGER -->
    <div class="vis-card vis-table-container">
        <div class="vis-card-header">
            <h3><span class="dashicons dashicons-lock"></span> SECURE DEVICE LEDGER</h3>
        </div>
        
        <table class="vis-table">
            <thead>
                <tr>
                    <th width="40"></th>
                    <th width="60" style="text-align:center;">STATUS</th>
                    <th>GERÄT / OS</th>
                    <th>INTEGRITÄT</th>
                    <th>TRUST SCORE</th>
                    <th style="text-align:right;">PROTOKOLLE</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $devices_all ) ) : ?>
                    <tr><td colspan="6" class="vis-empty-row">Ledger ist leer. Keine aktiven Uplinks.</td></tr>
                <?php else : ?>
                    <?php foreach ( $devices_all as $idx => $device ) : 
                        if ( $device['status'] === 'pending' ) continue;

                        $score = (int)$device['integrity_score'];
                        // Status logic based on Score
                        $integrity_label = ( $score >= 90 ) ? 'secure' : ( ( $score < 50 ) ? 'compromised' : 'warning' );
                        if ( $device['override_trust'] ) $integrity_label = 'secure';

                        $integrity_class = 'vis-' . $integrity_label;
                        $icon = ( $integrity_label === 'secure' ) ? 'dashicons-yes' : 'dashicons-warning';
                        $os_icon = vis_get_os_icon( $device['os_type'] );
                        
                        // ON-THE-FLY DECRYPTION FOR VIEW LAYER
                        // Nutzt die Core-Klasse zur Entschlüsselung
                        $details = $myrmidon->get_device_details_decrypted( $device );
                        $threats = $details['threats'] ?? array();
                        
                        $row_id = 'vis_row_' . $idx;
                        $detail_id = 'vis_detail_' . $idx;
                    ?>
                    <tr class="vis-main-row" onclick="document.getElementById('<?php echo esc_attr( $detail_id ); ?>').classList.toggle('vis-hidden');">
                        <td style="text-align:center; cursor:pointer;"><span class="dashicons dashicons-arrow-down-alt2 vis-toggle-icon"></span></td>
                        <td style="text-align:center;"><span class="dashicons <?php echo esc_attr( $icon ); ?> vis-icon-<?php echo esc_attr( $integrity_label ); ?>"></span></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span class="dashicons <?php echo esc_attr( $os_icon ); ?>" style="color:#607d8b;"></span>
                                <div>
                                    <strong class="vis-text-highlight"><?php echo esc_html( $device['device_name'] ); ?></strong>
                                    <?php if ( $device['override_trust'] ) : ?><span class="vis-badge-override">OVERRIDE</span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                           <span class="vis-badge vis-bg-<?php echo esc_attr( $integrity_label ); ?>"><?php echo strtoupper( esc_html( $integrity_label ) ); ?></span>
                        </td>
                        <td>
                            <div class="vis-score vis-text-<?php echo esc_attr( $integrity_label ); ?>">
                                <?php echo esc_html( $score ); ?>/100
                            </div>
                        </td>
                        <td style="text-align:right;">
                            <?php if ( $integrity_label !== 'secure' && ! $device['override_trust'] ) : ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('WARNUNG: Sie überstimmen das Sicherheitsprotokoll. Dies wird im Audit-Log vermerkt.');">
                                <?php wp_nonce_field( 'vis_action_nonce' ); ?>
                                <input type="hidden" name="vis_action" value="override">
                                <input type="hidden" name="device_id" value="<?php echo esc_attr( $device['device_id'] ); ?>">
                                <button class="vis-action-btn vis-btn-override" title="Trust Override" onclick="event.stopPropagation();">OVERRIDE</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Gerät wirklich löschen? Der kryptographische Kontext geht verloren.');">
                                <?php wp_nonce_field( 'vis_action_nonce' ); ?>
                                <input type="hidden" name="vis_action" value="delete">
                                <input type="hidden" name="device_id" value="<?php echo esc_attr( $device['device_id'] ); ?>">
                                <button class="vis-action-btn vis-btn-delete" title="Löschen" onclick="event.stopPropagation();"><span class="dashicons dashicons-trash"></span></button>
                            </form>
                        </td>
                    </tr>

                    <!-- DETAIL VIEW (HIDDEN) -->
                    <tr id="<?php echo esc_attr( $detail_id ); ?>" class="vis-detail-row vis-hidden">
                        <td colspan="6">
                            <div class="vis-detail-container">
                                <div class="vis-detail-col">
                                    <h4><span class="dashicons dashicons-admin-settings"></span> METADATA (PUBLIC)</h4>
                                    <ul class="vis-tech-list">
                                        <li><strong>ID:</strong> <span class="mono"><?php echo esc_html( $device['device_id'] ); ?></span></li>
                                        <li><strong>Last Uplink:</strong> <?php echo esc_html( $device['last_seen'] ); ?></li>
                                        <li><strong>DB Index:</strong> <?php echo esc_html( $device['id'] ); ?></li>
                                        <li><strong>Storage:</strong> <?php echo ! empty( $device['encrypted_telemetry'] ) ? 'Sodium SecretBox (AES-GCM Compatible)' : 'UNENCRYPTED'; ?></li>
                                    </ul>
                                </div>
                                <div class="vis-detail-col">
                                    <h4><span class="dashicons dashicons-shield-alt"></span> INTEGRITY AUDIT (DECRYPTED)</h4>
                                    <div class="vis-audit-grid">
                                        <?php if ( empty( $details ) ) : ?>
                                            <div class="vis-audit-item"><span class="vis-audit-fail">KEINE TELEMETRIE DATEN VORHANDEN</span></div>
                                        <?php else : ?>
                                            <?php 
                                            // 1. Root Check
                                            $root_safe = empty( $details['is_rooted'] );
                                            echo vis_render_audit_item( 'OS Manipulation (Root/Jailbreak)', $root_safe, true );

                                            // 2. Encryption Check
                                            $enc_safe = ! empty( $details['encryption_active'] );
                                            echo vis_render_audit_item( 'Disk Encryption (BitLocker/FileVault)', $enc_safe, false );

                                            // 3. Optional Checks
                                            if ( isset( $details['secure_boot'] ) ) echo vis_render_audit_item( 'Secure Boot Signatur', $details['secure_boot'], false );
                                            if ( isset( $details['firewall_active'] ) ) echo vis_render_audit_item( 'Firewall Status', $details['firewall_active'], false );
                                            if ( isset( $details['adb_enabled'] ) ) {
                                                $adb_safe = empty( $details['adb_enabled'] );
                                                echo vis_render_audit_item( 'ADB Debugging Bridge', $adb_safe, false );
                                            }
                                            
                                            // 4. Threats Listing
                                            if ( ! empty( $threats ) ) {
                                                echo '<div class="vis-threat-box"><strong>DETECTED THREATS:</strong> ' . esc_html( implode( ', ', $threats ) ) . '</div>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* VISIONGAIATECHNOLOGY OMEGA THEME */
:root {
    --vis-bg: #121212;
    --vis-panel: #1e1e1e;
    --vis-border: #333;
    --vis-accent: #00e676; /* Neon Green */
    --vis-warn: #ff9100;    /* Amber */
    --vis-crit: #ff1744;    /* Red */
    --vis-text: #eceff1;
    --vis-meta: #607d8b;
    --vis-glow: 0 0 10px rgba(0, 230, 118, 0.1);
}

.vis-dashboard-wrap { max-width: 1400px; margin: 20px auto; font-family: 'Segoe UI', system-ui, sans-serif; color: var(--vis-text); }
.vis-dashboard-wrap * { box-sizing: border-box; }
.mono { font-family: 'Consolas', 'Monaco', monospace; letter-spacing: -0.5px; }

/* LAYOUT */
.vis-header-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 25px; }
.vis-card { background: var(--vis-panel); border: 1px solid var(--vis-border); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); padding: 20px; position: relative; overflow: hidden; }
.vis-card-glow { border-top: 2px solid var(--vis-accent); }

/* TYPOGRAPHY */
.vis-card h3 { margin-top: 0; color: #fff; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--vis-border); padding-bottom: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.vis-desc { font-size: 12px; color: var(--vis-meta); margin-bottom: 10px; }

/* STATS */
.vis-stat-grid { display: flex; gap: 40px; }
.vis-stat { text-align: left; }
.vis-stat-val { display: block; font-size: 32px; font-weight: 700; color: #fff; line-height: 1.1; font-family: 'Segoe UI', sans-serif; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
.vis-stat-label { font-size: 10px; text-transform: uppercase; color: var(--vis-meta); font-weight: 600; letter-spacing: 0.5px; margin-top: 5px; display: block; }
.vis-stat-alert .vis-stat-val { text-shadow: 0 0 10px rgba(245, 158, 11, 0.4); }

.blink { animation: vis-pulse 2s infinite; }
@keyframes vis-pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }

/* FINGERPRINT */
.vis-fingerprint-box { background: #000; color: #00f2ff; font-family: 'Consolas', monospace; padding: 15px; border-radius: 4px; border: 1px solid #004d40; word-break: break-all; font-size: 14px; text-align: center; letter-spacing: 1px; box-shadow: inset 0 0 20px rgba(0, 77, 64, 0.3); }

/* TABLES */
table.vis-table { width: 100%; border-collapse: separate; border-spacing: 0; background: transparent; }
table.vis-table thead th { text-align: left; padding: 15px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--vis-meta); border-bottom: 2px solid var(--vis-border); background: rgba(0,0,0,0.2); }
table.vis-table tbody td { padding: 15px; border-bottom: 1px solid #2c2c2c; vertical-align: middle; color: #cfd8dc; background: transparent; transition: background 0.1s; }

.vis-text-highlight { color: #fff; font-weight: 600; font-size: 14px; }
.vis-text-light { color: #b0bec5; font-size: 13px; }
.vis-meta { font-size: 11px; color: #546e7a; }

/* ICONS & STATUS */
.vis-icon-secure { color: var(--vis-accent); }
.vis-icon-warning { color: var(--vis-warn); }
.vis-icon-compromised { color: var(--vis-crit); }

/* BADGES */
.vis-badge { padding: 4px 8px; border-radius: 2px; font-size: 9px; font-weight: 800; letter-spacing: 0.5px; display: inline-block; min-width: 70px; text-align: center; }
.vis-bg-secure { background: rgba(0, 230, 118, 0.1); color: var(--vis-accent); border: 1px solid var(--vis-accent); }
.vis-bg-compromised { background: rgba(255, 23, 68, 0.1); color: var(--vis-crit); border: 1px solid var(--vis-crit); }
.vis-bg-warning { background: rgba(255, 145, 0, 0.1); color: var(--vis-warn); border: 1px solid var(--vis-warn); }
.vis-badge-override { background: var(--vis-warn); color: #000; font-size: 9px; padding: 2px 4px; border-radius: 2px; margin-left: 8px; font-weight: 800; }

/* BUTTONS */
.vis-btn { border: none; padding: 8px 16px; border-radius: 2px; cursor: pointer; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s; }
.vis-btn-success { background: var(--vis-accent); color: #000; box-shadow: 0 2px 5px rgba(0,230,118,0.2); }
.vis-btn-danger { background: transparent; border: 1px solid var(--vis-crit); color: var(--vis-crit); }
.vis-btn-danger:hover { background: var(--vis-crit); color: #fff; }

.vis-action-btn { background: none; border: none; cursor: pointer; color: var(--vis-meta); font-size: 16px; transition: color 0.2s; padding: 5px; }
.vis-btn-delete:hover { color: var(--vis-crit); }
.vis-btn-override { color: var(--vis-warn); font-size: 10px; font-weight: bold; border: 1px solid var(--vis-warn); border-radius: 2px; padding: 4px 8px; margin-right: 10px; }
.vis-btn-override:hover { background: var(--vis-warn); color: #000; }

/* INTERACTIONS */
.vis-main-row { transition: background 0.1s; }
.vis-main-row:hover td { background: #252525 !important; }
.vis-toggle-icon { transition: transform 0.2s; color: var(--vis-meta); }
.vis-main-row:hover .vis-toggle-icon { color: #fff; transform: translateY(2px); }

/* DETAILS PANE */
.vis-hidden { display: none; }
.vis-detail-row td { background: #181818 !important; border-bottom: 2px solid var(--vis-border) !important; padding: 0 !important; }
.vis-detail-container { padding: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; border-left: 3px solid var(--vis-border); background: #151515; }
.vis-detail-col h4 { margin: 0 0 20px 0; font-size: 11px; color: var(--vis-meta); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #2c2c2c; padding-bottom: 8px; }

.vis-tech-list { list-style: none; margin: 0; padding: 0; }
.vis-tech-list li { font-size: 13px; color: #b0bec5; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #2c2c2c; display: flex; justify-content: space-between; }
.vis-tech-list strong { color: var(--vis-text); font-weight: 600; }

/* AUDIT ITEMS */
.vis-audit-grid { display: flex; flex-direction: column; gap: 10px; }
.vis-audit-item { display: flex; align-items: center; background: #222; padding: 10px 15px; border-radius: 2px; border: 1px solid #333; transition: border-color 0.2s; }
.vis-audit-item:hover { border-color: #444; }
.vis-audit-item .dashicons { margin-right: 15px; font-size: 20px; }
.vis-audit-label { flex-grow: 1; font-size: 13px; font-weight: 500; color: #eceff1; }
.vis-audit-ok { color: var(--vis-accent); font-size: 10px; font-weight: 800; letter-spacing: 1px; }
.vis-audit-fail { color: var(--vis-crit); font-size: 10px; font-weight: 800; letter-spacing: 1px; }

.vis-threat-box { margin-top: 15px; padding: 10px; background: rgba(255, 23, 68, 0.1); border: 1px solid var(--vis-crit); color: var(--vis-crit); font-size: 12px; border-radius: 2px; }

/* ALERTS */
.vis-alert { padding: 15px; margin-bottom: 20px; border-radius: 2px; font-size: 13px; display: flex; align-items: center; gap: 10px; border-left: 4px solid; }
.vis-alert-success { background: rgba(0, 230, 118, 0.1); color: #fff; border-color: var(--vis-accent); }
.vis-alert-warning { background: rgba(255, 145, 0, 0.1); color: #fff; border-color: var(--vis-warn); }
.vis-queue-container { margin-top: 30px; border-color: var(--vis-warn); border-top-width: 2px; }
.vis-header-warn h3 { color: var(--vis-warn); border-bottom-color: rgba(255, 145, 0, 0.3); }
</style>
