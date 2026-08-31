<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Throne_Guard {
    public const MASTER_ROLE = 'master';
    public const MASTER_CAP = 'mcp_master_access';
    private const SESSION_TTL = 7200;
    
    public const TOXIC_CAPABILITIES = [
        'activate_plugins', 'delete_plugins', 'install_plugins', 'edit_plugins', 'update_plugins',
        'switch_themes', 'edit_themes', 'install_themes', 'delete_themes', 'update_themes',
        'edit_users', 'delete_users', 'create_users', 'promote_users',
        'update_core', 'unfiltered_html', 'edit_files'
    ];

    public const AVAILABLE_CAPABILITIES = [
        'plugins' => [
            'label' => 'Plugin-Verwaltung & Code-Ausführung',
            'desc'  => 'Verhindert das Einschleusen und Aktivieren unautorisierter PHP-Skripte durch normale Administratoren.',
            'caps'  => [
                'activate_plugins' => ['label' => 'Plugins aktivieren', 'risk' => 'CRITICAL', 'desc' => 'Ausführung beliebiger PHP-Initialisierungscodes durch Plugin-Aktivierung.'],
                'install_plugins'  => ['label' => 'Plugins installieren', 'risk' => 'CRITICAL', 'desc' => 'Hochladen und Bereitstellen fremder ZIP-Erweiterungen.'],
                'update_plugins'   => ['label' => 'Plugins aktualisieren', 'risk' => 'HIGH', 'desc' => 'Überschreiben bestehender Plugin-Quellcodedateien.'],
                'delete_plugins'   => ['label' => 'Plugins löschen', 'risk' => 'HIGH', 'desc' => 'Entfernen essenzieller Sicherheits- und Kern-Plugins.'],
                'edit_plugins'     => ['label' => 'Plugin-Quellcode bearbeiten', 'risk' => 'CRITICAL', 'desc' => 'Direkte Code-Injektion in PHP-Dateien über den internen Editor.'],
            ]
        ],
        'themes' => [
            'label' => 'Theme- & Layout-Manipulation',
            'desc'  => 'Schützt vor Manipulation an Frontend-Templates, functions.php und CSS-Injektionen.',
            'caps'  => [
                'switch_themes'    => ['label' => 'Themes wechseln', 'risk' => 'HIGH', 'desc' => 'Wechselt das aktive Theme, was Layout und Backend-Hooks verändert.'],
                'install_themes'   => ['label' => 'Themes installieren', 'risk' => 'CRITICAL', 'desc' => 'Hochladen fremder Theme-Dateien und Skripte.'],
                'update_themes'    => ['label' => 'Themes aktualisieren', 'risk' => 'HIGH', 'desc' => 'Überschreiben von Theme-Quellcodedateien.'],
                'delete_themes'    => ['label' => 'Themes löschen', 'risk' => 'MEDIUM', 'desc' => 'Dauerhaftes Entfernen installierter Design-Vorlagen.'],
                'edit_themes'      => ['label' => 'Theme-Quellcode bearbeiten', 'risk' => 'CRITICAL', 'desc' => 'Direktes Editieren von functions.php und Template-Dateien.'],
            ]
        ],
        'users' => [
            'label' => 'Benutzerkonten & Rechte-Eskalation',
            'desc'  => 'Unterbindet Hintertüren, unautorisierte Benutzer-Erstellung und Passwort-Resets.',
            'caps'  => [
                'create_users'     => ['label' => 'Benutzer erstellen', 'risk' => 'HIGH', 'desc' => 'Erstellung neuer Konten (potenzielle Hintertüren).'],
                'promote_users'    => ['label' => 'Benutzerrollen hochstufen', 'risk' => 'CRITICAL', 'desc' => 'Rechte-Eskalation beliebiger Konten auf Administrator-Ebene.'],
                'delete_users'     => ['label' => 'Benutzer löschen', 'risk' => 'HIGH', 'desc' => 'Löschung legitimer Administratoren oder Redakteure.'],
                'edit_users'       => ['label' => 'Benutzer bearbeiten', 'risk' => 'HIGH', 'desc' => 'Änderung von Passwörtern, E-Mails und Rechten anderer Konten.'],
            ]
        ],
        'system' => [
            'label' => 'System-Kern & Dateisystem',
            'desc'  => 'Härtet den WordPress Core gegen gefährliche Dateiänderungen und XSS-Vektoren.',
            'caps'  => [
                'update_core'      => ['label' => 'WordPress Core Updates', 'risk' => 'MEDIUM', 'desc' => 'Ausführung von Hauptversions-Upgrades.'],
                'unfiltered_html'  => ['label' => 'Ungefiltertes HTML', 'risk' => 'CRITICAL', 'desc' => 'Speichern von rohem JavaScript in Beiträgen/Seiten (XSS-Vektor).'],
                'edit_files'       => ['label' => 'Dateieditor nutzen', 'risk' => 'CRITICAL', 'desc' => 'Nativer WordPress-Dateieditor für Systemdateien.'],
            ]
        ]
    ];

    private static ?self $instance = null;
    private array $config;

    public static function get_instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        $config = get_option('vis_config', []);
        $this->config = is_array($config) ? $config : [];
        $this->ensure_master_role();

        add_action('admin_post_vis_throneguard_claim', [$this, 'handle_claim_master']);
        add_action('admin_post_vis_throneguard_save', [$this, 'handle_save']);
        add_action('admin_post_vis_throneguard_unlock', [$this, 'handle_unlock']);
        add_action('wp_ajax_vis_throneguard_clear_logs', [$this, 'handle_clear_logs']);
        add_action('clear_auth_cookie', [$this, 'destroy_session']);
        add_action('delete_user', [$this, 'protect_master_deletion']);
        add_action('remove_user_from_blog', [$this, 'protect_master_deletion']);
        add_action('profile_update', [$this, 'protect_master_profile'], 10, 2);
        add_filter('editable_roles', [$this, 'filter_editable_roles']);

        if (!empty($this->config['throneguard_harden_admin'])) {
            add_action('init', [$this, 'reconcile_administrator'], 1);
        }
        if (!empty($this->config['throneguard_lock_enabled'])) {
            add_action('admin_init', [$this, 'enforce_backend_lock'], 1);
            add_filter('rest_authentication_errors', [$this, 'enforce_rest_lock'], 90);
        }
    }

    public static function provision_current_master(): bool {
        $instance = self::get_instance();
        $user = wp_get_current_user();
        if (!$user->exists() || !$user->has_cap('manage_options')) return false;
        $instance->ensure_master_role();
        $user->set_role(self::MASTER_ROLE);
        $success = $user->has_cap(self::MASTER_CAP);
        if ($success) {
            self::log_event('MASTER_CLAIM', 'Administrator [' . $user->user_login . '] hat die GeDefense Master-Rolle übernommen.', 'success');
        }
        return $success;
    }

    public static function apply_administrator_policy(bool $harden): void {
        $instance = self::get_instance();
        $harden ? $instance->reconcile_administrator() : $instance->restore_administrator();
    }

    public static function status(): array {
        $config = get_option('vis_config', []);
        $config = is_array($config) ? $config : [];
        $instance = self::get_instance();
        return [
            'is_master' => current_user_can(self::MASTER_CAP),
            'master_count' => count(get_users(['role' => self::MASTER_ROLE, 'fields' => 'ids'])),
            'superkey_set' => is_string(get_option('vis_throneguard_superkey_hash', '')) && get_option('vis_throneguard_superkey_hash', '') !== '',
            'harden_admin' => !empty($config['throneguard_harden_admin']),
            'lock_enabled' => !empty($config['throneguard_lock_enabled']),
            'session_unlocked' => $instance->is_session_unlocked(),
            'restricted_caps' => $instance->get_restricted_capabilities(),
            'available_caps'  => self::AVAILABLE_CAPABILITIES,
            'logs'            => self::get_logs(),
        ];
    }

    /** @return string[] */
    public function get_restricted_capabilities(): array {
        $config = get_option('vis_config', []);
        if (isset($config['throneguard_restricted_caps']) && is_array($config['throneguard_restricted_caps'])) {
            return array_values(array_filter($config['throneguard_restricted_caps'], 'is_string'));
        }
        return self::TOXIC_CAPABILITIES;
    }

    public static function get_logs(): array {
        $logs = get_option('vis_throneguard_audit_logs', []);
        if (!is_array($logs) || empty($logs)) {
            return [
                [
                    'id'        => 'init-001',
                    'timestamp' => current_time('mysql'),
                    'action'    => 'SYSTEM_INIT',
                    'severity'  => 'info',
                    'message'   => 'ThroneGuard Master Privilege Sentinel aktiv. Bereit für Zero-Trust Schutz.',
                    'ip'        => class_exists('VIS_Security') ? VIS_Security::client_ip() : '127.0.0.1',
                    'user'      => 'SYSTEM'
                ]
            ];
        }
        return $logs;
    }

    public static function log_event(string $action, string $message, string $severity = 'info', array $meta = []): void {
        $logs = get_option('vis_throneguard_audit_logs', []);
        $logs = is_array($logs) ? $logs : [];
        
        $ip = class_exists('VIS_Security') ? VIS_Security::client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $user = wp_get_current_user();
        
        $entry = [
            'id'        => bin2hex(random_bytes(6)),
            'timestamp' => current_time('mysql'),
            'action'    => $action,
            'severity'  => in_array($severity, ['success', 'info', 'warning', 'critical'], true) ? $severity : 'info',
            'message'   => $message,
            'ip'        => $ip,
            'user'      => $user->exists() ? $user->user_login : 'ANONYMOUS',
            'meta'      => $meta
        ];
        
        array_unshift($logs, $entry);
        if (count($logs) > 80) {
            $logs = array_slice($logs, 0, 80);
        }
        update_option('vis_throneguard_audit_logs', $logs, false);
    }

    public function handle_clear_logs(): void {
        if (!current_user_can(self::MASTER_CAP)) {
            wp_send_json_error('Berechtigung verweigert.', 403);
        }
        check_ajax_referer('vis_throneguard_action', 'nonce');
        update_option('vis_throneguard_audit_logs', [], false);
        self::log_event('LOGS_CLEARED', 'Audit-Protokoll wurde durch den Master geleert.', 'info');
        wp_send_json_success(['message' => 'Logs geleert.']);
    }

    private function ensure_master_role(): void {
        $administrator = get_role('administrator');
        $capabilities = $administrator ? $administrator->capabilities : ['read' => true, 'manage_options' => true];
        $capabilities[self::MASTER_CAP] = true;
        foreach (self::TOXIC_CAPABILITIES as $capability) $capabilities[$capability] = true;

        $master = get_role(self::MASTER_ROLE);
        if ($master === null) {
            add_role(self::MASTER_ROLE, 'GeDefense Master', $capabilities);
            return;
        }
        foreach ($capabilities as $capability => $granted) $master->add_cap((string)$capability, (bool)$granted);
    }

    public function reconcile_administrator(): void {
        if (!$this->has_master()) return;
        $administrator = get_role('administrator');
        if ($administrator === null) return;

        $restricted = $this->get_restricted_capabilities();
        $all_possible = [];
        foreach (self::AVAILABLE_CAPABILITIES as $group) {
            foreach ($group['caps'] as $capKey => $capData) {
                $all_possible[] = $capKey;
            }
        }

        foreach ($all_possible as $capability) {
            if (in_array($capability, $restricted, true)) {
                $administrator->remove_cap($capability);
            } else {
                $administrator->add_cap($capability);
            }
        }
    }

    public function handle_claim_master(): never {
        if (!current_user_can('manage_options')) wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        check_admin_referer('vis_throneguard_claim');
        if (!self::provision_current_master()) wp_die('A server error occurred.', 'Server Error', ['response' => 500]);
        wp_safe_redirect($this->dashboard_url(['claimed' => '1']));
        exit;
    }

    public function handle_save(): never {
        if (!current_user_can(self::MASTER_CAP)) wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        check_admin_referer('vis_throneguard_save');

        $existingHash = (string)get_option('vis_throneguard_superkey_hash', '');
        $currentKey = isset($_POST['current_superkey']) && is_string($_POST['current_superkey']) ? $_POST['current_superkey'] : '';
        $newKey = isset($_POST['new_superkey']) && is_string($_POST['new_superkey']) ? $_POST['new_superkey'] : '';
        
        if ($existingHash !== '' && !empty($newKey) && !password_verify($currentKey, $existingHash)) {
            self::log_event('KEY_UPDATE_FAIL', 'Fehlgeschlagene Superkey-Änderung: Aktueller Superkey war falsch.', 'warning');
            wp_safe_redirect($this->dashboard_url(['throne_error' => 'verification']));
            exit;
        }
        
        if ($existingHash === '' || $newKey !== '') {
            if ($newKey !== '') {
                if (strlen($newKey) < 12 || strlen($newKey) > 256) {
                    wp_safe_redirect($this->dashboard_url(['throne_error' => 'key_length']));
                    exit;
                }
                $newHash = password_hash($newKey, PASSWORD_DEFAULT);
                if (!is_string($newHash) || !update_option('vis_throneguard_superkey_hash', $newHash, false)) {
                    if ((string)get_option('vis_throneguard_superkey_hash', '') !== $newHash) {
                        wp_die('A server error occurred.', 'Server Error', ['response' => 500]);
                    }
                }
                self::log_event('SUPERKEY_UPDATE', 'ThroneGuard Superkey wurde erfolgreich neu gesetzt.', 'success');
            }
        }

        // Parse custom selected capabilities
        $selectedCaps = [];
        if (isset($_POST['restricted_caps']) && is_array($_POST['restricted_caps'])) {
            foreach ($_POST['restricted_caps'] as $cap) {
                if (is_string($cap)) {
                    $cleanCap = sanitize_key($cap);
                    if ($cleanCap !== '') $selectedCaps[] = $cleanCap;
                }
            }
        }

        $config = get_option('vis_config', []);
        $config = is_array($config) ? $config : [];
        $config['throneguard_enabled'] = 1;
        $config['throneguard_harden_admin'] = isset($_POST['harden_admin']) ? 1 : 0;
        $config['throneguard_lock_enabled'] = isset($_POST['lock_enabled']) ? 1 : 0;
        $config['throneguard_restricted_caps'] = $selectedCaps;
        update_option('vis_config', $config, false);

        self::apply_administrator_policy(!empty($config['throneguard_harden_admin']));
        
        self::log_event(
            'POLICY_SAVED', 
            sprintf('ThroneGuard Richtlinien aktualisiert. Härtung: %s (%d Restriktionen aktiv). Lock: %s.',
                !empty($config['throneguard_harden_admin']) ? 'AKTIV' : 'INAKTIV',
                count($selectedCaps),
                !empty($config['throneguard_lock_enabled']) ? 'AKTIV' : 'INAKTIV'
            ), 
            'info'
        );

        $this->destroy_session();
        wp_safe_redirect($this->dashboard_url(['updated' => '1']));
        exit;
    }

    public function handle_unlock(): never {
        if (!current_user_can(self::MASTER_CAP)) wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        check_admin_referer('vis_throneguard_unlock');
        $provided = isset($_POST['superkey']) && is_string($_POST['superkey']) ? $_POST['superkey'] : '';
        $hash = (string)get_option('vis_throneguard_superkey_hash', '');
        
        if ($hash === '' || !password_verify($provided, $hash)) {
            self::log_event('AUTH_FAILED', 'Fehlgeschlagener Entsperrversuch mit ungültigem Superkey.', 'critical');
            wp_safe_redirect(admin_url('?throneguard_locked=1&auth_error=1'));
            exit;
        }

        $token = bin2hex(random_bytes(32));
        $expires = time() + self::SESSION_TTL;
        update_user_meta(get_current_user_id(), '_vis_throneguard_session', [
            'expires' => $expires,
            'token_hash' => hash('sha256', $token),
            'fingerprint' => $this->fingerprint(),
        ]);
        setcookie($this->cookie_name(), $token, [
            'expires' => $expires,
            'path' => defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/',
            'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        
        self::log_event('SESSION_UNLOCKED', 'Master-Session erfolgreich mit Superkey freigegeben (Gültigkeit: 2 Stunden).', 'success');
        wp_safe_redirect(admin_url());
        exit;
    }

    public function enforce_backend_lock(): void {
        if (!current_user_can(self::MASTER_CAP) || (string)get_option('vis_throneguard_superkey_hash', '') === '') return;
        global $pagenow;
        if ($pagenow === 'admin-post.php' && isset($_POST['action']) && $_POST['action'] === 'vis_throneguard_unlock') return;
        if ($this->is_session_unlocked()) return;
        if (wp_doing_ajax()) wp_die('GeDefense ThroneGuard session locked.', '', ['response' => 401]);
        
        self::log_event('LOCK_ENFORCE', 'Zugriff auf Backend blockiert: Master-Session gesperrt.', 'warning');
        $this->render_lock_screen();
        exit;
    }

    public function enforce_rest_lock(mixed $result): mixed {
        if ($result !== null || !current_user_can(self::MASTER_CAP) || (string)get_option('vis_throneguard_superkey_hash', '') === '') return $result;
        if (!$this->is_session_unlocked()) {
            self::log_event('REST_LOCK', 'REST-API Anfrage abgewiesen: Master-Session gesperrt.', 'warning');
            return new WP_Error('vis_throneguard_locked', 'GeDefense ThroneGuard session locked.', ['status' => 401]);
        }
        return $result;
    }

    private function render_lock_screen(): void {
        status_header(401);
        nocache_headers();
        $auth_error = isset($_GET['auth_error']);
        ?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ThroneGuard — Zero-Trust Lockdown | GeDefense</title>
    <style>
        :root {
            --tg-bg: #020617;
            --tg-card: #0b1329;
            --tg-border: rgba(99, 102, 241, 0.3);
            --tg-glow: rgba(99, 102, 241, 0.2);
            --tg-accent: #6366f1;
            --tg-cyan: #00f0ff;
            --tg-emerald: #10b981;
            --tg-crimson: #ef4444;
            --tg-text: #f8fafc;
            --tg-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body {
            background-color: var(--tg-bg);
            background-image: 
                radial-gradient(circle at 50% 20%, rgba(99, 102, 241, 0.15), transparent 60%),
                radial-gradient(circle at 80% 80%, rgba(0, 240, 255, 0.08), transparent 50%),
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 32px 32px, 32px 32px;
            color: var(--tg-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .tg-container {
            width: 100%;
            max-width: 440px;
            background: var(--tg-card);
            border: 1px solid var(--tg-border);
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 0 50px var(--tg-glow), 0 25px 60px rgba(0, 0, 0, 0.8);
            position: relative;
            backdrop-filter: blur(12px);
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .tg-header { text-align: center; margin-bottom: 28px; }
        .tg-icon-shield {
            width: 64px;
            height: 64px;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.4);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--tg-cyan);
            margin-bottom: 16px;
            box-shadow: 0 0 20px rgba(0, 240, 255, 0.2);
            animation: pulseGlow 3s infinite ease-in-out;
        }
        @keyframes pulseGlow { 0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(0, 240, 255, 0.2); } 50% { transform: scale(1.05); box-shadow: 0 0 35px rgba(99, 102, 241, 0.4); } }
        .tg-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #fff;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .tg-title span { color: var(--tg-cyan); }
        .tg-subtitle { font-size: 13px; color: var(--tg-muted); line-height: 1.5; }
        .tg-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 11px;
            font-family: monospace;
            letter-spacing: 1px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #f87171;
            margin-top: 10px;
        }
        .tg-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 8px #ef4444; }
        .tg-form { margin-top: 24px; }
        .tg-label { display: block; font-size: 12px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.8px; }
        .tg-input-wrapper { position: relative; margin-bottom: 20px; }
        .tg-input {
            width: 100%;
            background: rgba(2, 6, 23, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 8px;
            padding: 14px 44px 14px 16px;
            color: #fff;
            font-size: 15px;
            outline: none;
            transition: all 0.2s;
        }
        .tg-input:focus { border-color: var(--tg-cyan); box-shadow: 0 0 15px rgba(0, 240, 255, 0.25); }
        .tg-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--tg-muted);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
        }
        .tg-toggle-btn:hover { color: #fff; }
        .tg-btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.4);
            transition: all 0.2s;
        }
        .tg-btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 25px rgba(6, 182, 212, 0.5); }
        .tg-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tg-footer { margin-top: 24px; text-align: center; font-size: 11px; color: #64748b; font-family: monospace; }
    </style>
</head>
<body>
    <main class="tg-container">
        <div class="tg-header">
            <div class="tg-icon-shield">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="M3 7l4 4 5-7 5 7 4-4-2 12H5L3 7z"/>
                </svg>
            </div>
            <h1 class="tg-title">THRONE<span>GUARD</span></h1>
            <p class="tg-subtitle">Master Privilege Boundary & Sovereign Lockdown aktiv. Superkey erforderlich zur Freigabe.</p>
            <div class="tg-badge">
                <span class="tg-badge-dot"></span>
                <span>ZERO-TRUST SESSION LOCKED</span>
            </div>
        </div>

        <?php if ($auth_error): ?>
            <div class="tg-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Ungültiger Superkey. Zugriff abgewiesen.
            </div>
        <?php endif; ?>

        <form class="tg-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('vis_throneguard_unlock'); ?>
            <input type="hidden" name="action" value="vis_throneguard_unlock">
            
            <label class="tg-label" for="tg-superkey">ThroneGuard Superkey</label>
            <div class="tg-input-wrapper">
                <input class="tg-input" id="tg-superkey" type="password" name="superkey" required autocomplete="current-password" placeholder="Superkey eingeben...">
                <button type="button" class="tg-toggle-btn" onclick="const f=document.getElementById('tg-superkey');f.type=f.type==='password'?'text':'password';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>

            <button type="submit" class="tg-btn-submit">Master-Session Entsperren &rarr;</button>
        </form>

        <div class="tg-footer">
            GEDEFENSE WP // APEX SOVEREIGNTY // CIPHER: SHA-256 HMAC
        </div>
    </main>
</body>
</html><?php
    }

    private function is_session_unlocked(): bool {
        if (!current_user_can(self::MASTER_CAP)) return false;
        $cookie = $_COOKIE[$this->cookie_name()] ?? null;
        $stored = get_user_meta(get_current_user_id(), '_vis_throneguard_session', true);
        if (!is_string($cookie) || preg_match('/^[a-f0-9]{64}$/D', $cookie) !== 1 || !is_array($stored)) return false;
        if ((int)($stored['expires'] ?? 0) < time()) return false;
        $tokenHash = (string)($stored['token_hash'] ?? '');
        $fingerprint = (string)($stored['fingerprint'] ?? '');
        return preg_match('/^[a-f0-9]{64}$/D', $tokenHash) === 1
            && hash_equals($tokenHash, hash('sha256', $cookie))
            && hash_equals($fingerprint, $this->fingerprint());
    }

    private function fingerprint(): string {
        $ip = class_exists('VIS_Security') ? VIS_Security::client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
        return hash_hmac('sha256', $ip . "\0" . $ua, wp_salt('auth'));
    }

    public function destroy_session(): void {
        $name = $this->cookie_name();
        if (isset($_COOKIE[$name])) {
            setcookie($name, '', [
                'expires' => time() - 3600,
                'path' => defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/',
                'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        delete_user_meta(get_current_user_id(), '_vis_throneguard_session');
    }

    public function filter_editable_roles(array $roles): array {
        if (!current_user_can(self::MASTER_CAP)) unset($roles[self::MASTER_ROLE]);
        return $roles;
    }

    public function protect_master_deletion(int $userId): void {
        $target = get_userdata($userId);
        if ($target instanceof WP_User && in_array(self::MASTER_ROLE, (array)$target->roles, true) && !current_user_can(self::MASTER_CAP)) {
            self::log_event('MASTER_PROTECT', 'Versuch blockiert, Master-Konto [ID: ' . $userId . '] zu löschen.', 'critical');
            wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        }
    }

    public function protect_master_profile(int $userId, WP_User $oldUser): void {
        if (in_array(self::MASTER_ROLE, (array)$oldUser->roles, true) && !current_user_can(self::MASTER_CAP)) {
            self::log_event('MASTER_PROTECT', 'Versuch blockiert, Master-Profil [ID: ' . $userId . '] zu manipulieren.', 'critical');
            wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        }
    }

    private function has_master(): bool {
        return get_users(['role' => self::MASTER_ROLE, 'number' => 1, 'fields' => 'ids']) !== [];
    }

    private function restore_administrator(): void {
        $administrator = get_role('administrator');
        if ($administrator === null) return;
        foreach (self::TOXIC_CAPABILITIES as $capability) $administrator->add_cap($capability);
    }

    private function cookie_name(): string {
        return 'vis_throneguard_' . get_current_user_id();
    }

    private function dashboard_url(array $query = []): string {
        return add_query_arg($query, admin_url('admin.php?page=vgt-suite&tab=throneguard'));
    }
}