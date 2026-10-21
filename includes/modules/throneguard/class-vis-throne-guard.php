<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Throne_Guard {
    public const MASTER_ROLE = 'master';
    public const MASTER_CAP = 'mcp_master_access';
    private const SESSION_TTL = 7200;
    public const WHITELIST_LEASE_TTL = 43200; // 12 Hours (43,200 seconds)
    public const OPTION_IP_LEASES = 'vis_throneguard_ip_leases';
    public const CRON_PRUNE_HOOK = 'vis_throneguard_prune_leases';
    
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
        self::reconcile_ip_leases();

        $config = get_option('vis_config', []);
        $this->config = is_array($config) ? $config : [];
        $this->ensure_master_role();

        add_action('admin_post_vis_throneguard_claim', [$this, 'handle_claim_master']);
        add_action('admin_post_vis_throneguard_save', [$this, 'handle_save']);
        add_action('admin_post_vis_throneguard_unlock', [$this, 'handle_unlock']);
        add_action('admin_post_vis_throneguard_revoke_lease', [$this, 'handle_revoke_lease']);
        add_action(self::CRON_PRUNE_HOOK, [self::class, 'reconcile_ip_leases']);
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
        self::reconcile_ip_leases();
        $config = get_option('vis_config', []);
        $config = is_array($config) ? $config : [];
        $instance = self::get_instance();
        $current_ip = self::resolve_client_ip();
        $active_leases = self::get_active_ip_leases();
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
            'current_ip'      => $current_ip,
            'active_leases'   => $active_leases,
            'current_ip_lease'=> $active_leases[$current_ip] ?? null,
        ];
    }

    private static function resolve_client_ip(): string {
        $ip = class_exists('VIS_Security') ? VIS_Security::client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ip = trim($ip);
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '127.0.0.1';
    }

    /** @return string[] */
    private static function parse_ip_lines(string $raw): array {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && !in_array($trimmed, $clean, true)) {
                $clean[] = $trimmed;
            }
        }
        return $clean;
    }

    private static function compute_swarm_subnet(string $ip): string {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return '';
        }
        if (strlen($packed) === 4) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
        }
        if (strlen($packed) === 16) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . '::/64';
        }
        return '';
    }

    private static function sync_zeus_waf(): void {
        if (!class_exists('VIS_Zeus')) {
            $zeus_file = defined('VIS_PATH')
                ? VIS_PATH . 'includes/modules/zeus/class-vis-zeus.php'
                : dirname(__DIR__) . '/zeus/class-vis-zeus.php';
            if (is_readable($zeus_file)) {
                require_once $zeus_file;
            }
        }
        if (class_exists('VIS_Zeus') && method_exists('VIS_Zeus', 'recompile_waf')) {
            \VIS_Zeus::recompile_waf();
        }
    }

    /**
     * Grants a 12-hour (43,200s) Master IP whitelist across AEGIS, Prometheus, and ZEUS.
     *
     * @param string|null $ip
     * @param int $ttl
     * @return array<string, mixed>
     */
    public static function grant_master_ip_whitelist(?string $ip = null, int $ttl = self::WHITELIST_LEASE_TTL): array {
        self::reconcile_ip_leases();

        $target_ip = ($ip !== null && trim($ip) !== '') ? trim($ip) : self::resolve_client_ip();
        if (filter_var($target_ip, FILTER_VALIDATE_IP) === false) {
            $target_ip = '127.0.0.1';
        }

        $ttl = max(60, $ttl);
        $now = time();
        $expires_at = $now + $ttl;

        // 1. Update AEGIS & Prometheus Whitelists in vis_config
        $config = get_option('vis_config', []);
        $config = is_array($config) ? $config : [];

        $aegis_ips = self::parse_ip_lines((string)($config['aegis_whitelist_ips'] ?? ''));
        if (!in_array($target_ip, $aegis_ips, true)) {
            $aegis_ips[] = $target_ip;
        }
        $config['aegis_whitelist_ips'] = implode("\n", $aegis_ips);

        $prom_ips = self::parse_ip_lines((string)($config['prometheus_whitelist_ips'] ?? ''));
        if (!in_array($target_ip, $prom_ips, true)) {
            $prom_ips[] = $target_ip;
        }
        $config['prometheus_whitelist_ips'] = implode("\n", $prom_ips);

        update_option('vis_config', $config, false);

        // 2. Update ZEUS Whitelist (vgt_zeus_whitelist_ips + vis_zeus_config['ip_whitelist'])
        $zeus_wl = get_option('vgt_zeus_whitelist_ips', []);
        $zeus_wl = is_array($zeus_wl) ? $zeus_wl : [];

        $zeus_cfg = get_option('vis_zeus_config', []);
        $zeus_cfg = is_array($zeus_cfg) ? $zeus_cfg : [];
        $cfg_wl = is_array($zeus_cfg['ip_whitelist'] ?? null) ? $zeus_cfg['ip_whitelist'] : [];

        $legacy_swarm = self::compute_swarm_subnet($target_ip);
        $merged_zeus = array_values(array_unique(array_filter(
            array_map('trim', array_map('strval', array_merge(['127.0.0.1', '::1'], $zeus_wl, $cfg_wl))),
            static fn(string $v): bool => $v !== '' && ($legacy_swarm === '' || $v !== $legacy_swarm || in_array($legacy_swarm, $aegis_ips, true))
        )));

        if (!in_array($target_ip, $merged_zeus, true)) {
            $merged_zeus[] = $target_ip;
        }

        update_option('vgt_zeus_whitelist_ips', $merged_zeus);
        $zeus_cfg['ip_whitelist'] = $merged_zeus;
        update_option('vis_zeus_config', $zeus_cfg);

        // 3. Record 12-Hour Lease in vis_throneguard_ip_leases
        $leases = get_option(self::OPTION_IP_LEASES, []);
        $leases = is_array($leases) ? $leases : [];

        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $lease_record = [
            'ip'         => $target_ip,
            'user_id'    => ($user && $user->exists()) ? (int)$user->ID : 0,
            'user_login' => ($user && $user->exists()) ? (string)$user->user_login : 'MASTER',
            'granted_at' => $now,
            'expires_at' => $expires_at,
            'ttl'        => $ttl,
            'modules'    => ['AEGIS', 'PROMETHEUS', 'ZEUS'],
        ];
        $leases[$target_ip] = $lease_record;
        update_option(self::OPTION_IP_LEASES, $leases, false);

        // 4. Schedule WP-Cron Prune Event at expiration
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event($expires_at + 1, self::CRON_PRUNE_HOOK);
        }

        // 5. Clear any existing Prometheus anomaly state or Cerberus ban for this IP
        if (function_exists('delete_transient')) {
            delete_transient('vis_prom_state_' . md5($target_ip));
        }
        if (function_exists('apcu_delete')) {
            @apcu_delete('vis_prom_state_' . md5($target_ip));
            if ($legacy_swarm !== '') {
                @apcu_delete('vis_prom_infra_' . md5($legacy_swarm));
            }
        }
        if (class_exists('VIS_Cerberus') && method_exists('VIS_Cerberus', 'unban_ip')) {
            try {
                \VIS_Cerberus::unban_ip($target_ip);
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        // 6. Recompile Zeus Pre-Boot WAF so whitelist.json and zeus-waf.php reflect the lease immediately
        self::sync_zeus_waf();

        $hours = round($ttl / 3600, 1);
        self::log_event(
            'MASTER_WHITELIST_GRANTED',
            sprintf(
                'Master-IP [%s] nach Superkey-Verifikation für %s Stunden in AEGIS, PROMETHEUS und ZEUS gewhitelistet (bis %s UTC).',
                $target_ip,
                (string)$hours,
                gmdate('Y-m-d H:i:s', $expires_at)
            ),
            'success',
            $lease_record
        );

        return $lease_record;
    }

    /**
     * Reconciles and removes expired 12-hour Master IP leases from AEGIS, Prometheus, and ZEUS.
     * Fast O(1) no-op when no leases exist or none are expired.
     */
    public static function reconcile_ip_leases(): bool {
        $leases = get_option(self::OPTION_IP_LEASES, []);
        if (!is_array($leases) || empty($leases)) {
            return false;
        }

        $now = time();
        $expired = [];
        $active = [];

        foreach ($leases as $ip_key => $info) {
            $ip_str = is_string($ip_key) && $ip_key !== '' ? $ip_key : (string)($info['ip'] ?? '');
            if ($ip_str === '') {
                continue;
            }
            $exp = (int)($info['expires_at'] ?? 0);
            if ($exp <= $now) {
                $expired[$ip_str] = $info;
            } else {
                $active[$ip_str] = $info;
            }
        }

        if (empty($expired)) {
            return false;
        }

        // Update active leases first to prevent re-entrancy
        update_option(self::OPTION_IP_LEASES, $active, false);

        $expired_targets = [];
        foreach (array_keys($expired) as $exp_ip) {
            $expired_targets[$exp_ip] = true;
            $swarm = self::compute_swarm_subnet($exp_ip);
            if ($swarm !== '') {
                $expired_targets[$swarm] = true;
            }
        }

        // 1. Remove expired IP(s) from AEGIS & Prometheus in vis_config
        $config = get_option('vis_config', []);
        $config = is_array($config) ? $config : [];

        $aegis_ips = self::parse_ip_lines((string)($config['aegis_whitelist_ips'] ?? ''));
        $aegis_ips = array_values(array_filter($aegis_ips, static fn(string $entry): bool => !isset($expired_targets[$entry])));
        $config['aegis_whitelist_ips'] = implode("\n", $aegis_ips);

        $prom_ips = self::parse_ip_lines((string)($config['prometheus_whitelist_ips'] ?? ''));
        $prom_ips = array_values(array_filter($prom_ips, static fn(string $entry): bool => !isset($expired_targets[$entry])));
        $config['prometheus_whitelist_ips'] = implode("\n", $prom_ips);

        update_option('vis_config', $config, false);

        // 2. Remove expired IP(s) from ZEUS (vgt_zeus_whitelist_ips + vis_zeus_config['ip_whitelist'])
        $zeus_wl = get_option('vgt_zeus_whitelist_ips', []);
        $zeus_wl = is_array($zeus_wl) ? $zeus_wl : [];
        $zeus_cfg = get_option('vis_zeus_config', []);
        $zeus_cfg = is_array($zeus_cfg) ? $zeus_cfg : [];
        $cfg_wl = is_array($zeus_cfg['ip_whitelist'] ?? null) ? $zeus_cfg['ip_whitelist'] : [];

        $merged_zeus = array_values(array_unique(array_filter(
            array_map('trim', array_map('strval', array_merge($zeus_wl, $cfg_wl))),
            static fn(string $entry): bool => $entry !== '' && !isset($expired_targets[$entry])
        )));
        foreach (['127.0.0.1', '::1'] as $loopback) {
            if (!isset($expired_targets[$loopback]) && !in_array($loopback, $merged_zeus, true)) {
                array_unshift($merged_zeus, $loopback);
            }
        }
        $merged_zeus = array_values(array_unique($merged_zeus));

        update_option('vgt_zeus_whitelist_ips', $merged_zeus);
        $zeus_cfg['ip_whitelist'] = $merged_zeus;
        update_option('vis_zeus_config', $zeus_cfg);

        // 3. Recompile Zeus Pre-Boot WAF so whitelist.json and zeus-waf.php drop the expired IP
        self::sync_zeus_waf();

        foreach ($expired as $exp_ip => $info) {
            self::log_event(
                'MASTER_WHITELIST_EXPIRED',
                sprintf(
                    '12-Stunden Master-Whitelist für IP [%s] abgelaufen. IP wurde automatisch aus AEGIS, PROMETHEUS und ZEUS entfernt.',
                    $exp_ip
                ),
                'info',
                is_array($info) ? $info : ['ip' => $exp_ip]
            );
        }

        return true;
    }

    /**
     * Immediately revokes a Master IP whitelist lease and removes the IP from AEGIS, Prometheus, and ZEUS.
     */
    public static function revoke_master_ip_whitelist(?string $ip = null): bool {
        $target_ip = ($ip !== null && trim($ip) !== '') ? trim($ip) : self::resolve_client_ip();
        $leases = get_option(self::OPTION_IP_LEASES, []);
        $leases = is_array($leases) ? $leases : [];

        if (!isset($leases[$target_ip])) {
            $leases[$target_ip] = [
                'ip'         => $target_ip,
                'granted_at' => time() - self::WHITELIST_LEASE_TTL,
                'expires_at' => time() - 1,
            ];
        } else {
            $leases[$target_ip]['expires_at'] = time() - 1;
        }

        update_option(self::OPTION_IP_LEASES, $leases, false);
        return self::reconcile_ip_leases();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get_active_ip_leases(): array {
        self::reconcile_ip_leases();
        $leases = get_option(self::OPTION_IP_LEASES, []);
        if (!is_array($leases)) {
            return [];
        }
        $now = time();
        $active = [];
        foreach ($leases as $ip_key => $info) {
            if (!is_array($info)) {
                continue;
            }
            $exp = (int)($info['expires_at'] ?? 0);
            if ($exp > $now) {
                $info['remaining_seconds'] = $exp - $now;
                $active[(string)$ip_key] = $info;
            }
        }
        return $active;
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

        if (in_array($severity, ['warning', 'critical'], true) && class_exists('VIS_Event_Bus')) {
            \VIS_Event_Bus::emit(
                'THRONEGUARD',
                $action,
                $message,
                [
                    'category' => 'IDENTITY',
                    'role' => 'DETECTION',
                    'entity_type' => 'USER',
                    'entity_id' => $user->exists() ? (string)$user->ID : '0',
                    'user_id' => $user->exists() ? (int)$user->ID : 0,
                    'actor_ip' => $ip,
                    'attribution_confidence' => 95,
                    'severity' => $severity === 'critical' ? 8 : 6,
                ],
                $severity === 'critical' ? 8 : 6
            );
        }
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

    private function establish_session(): void {
        $token = bin2hex(random_bytes(32));
        $expires = time() + self::SESSION_TTL;
        update_user_meta(get_current_user_id(), '_vis_throneguard_session', [
            'expires' => $expires,
            'token_hash' => hash('sha256', $token),
            'fingerprint' => $this->fingerprint(),
        ]);
        if (!headers_sent()) {
            setcookie($this->cookie_name(), $token, [
                'expires' => $expires,
                'path' => defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/',
                'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        $_COOKIE[$this->cookie_name()] = $token;
    }

    public function handle_save(): never {
        if (!current_user_can(self::MASTER_CAP)) wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        check_admin_referer('vis_throneguard_save');

        $existingHash = (string)get_option('vis_throneguard_superkey_hash', '');
        $currentKey = isset($_POST['current_superkey']) && is_string($_POST['current_superkey']) ? wp_unslash($_POST['current_superkey']) : '';
        $newKey = isset($_POST['new_superkey']) && is_string($_POST['new_superkey']) ? wp_unslash($_POST['new_superkey']) : '';
        $superkeyVerified = false;

        if ($existingHash !== '' && ($currentKey !== '' || $newKey !== '')) {
            if (!password_verify($currentKey, $existingHash)) {
                self::log_event('KEY_UPDATE_FAIL', 'Fehlgeschlagene Superkey-Verifikation: Eingegebener Superkey war falsch.', 'warning');
                wp_safe_redirect($this->dashboard_url(['throne_error' => 'verification']));
                exit;
            }
            $superkeyVerified = true;
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
                $superkeyVerified = true;
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

        $redirectParams = ['updated' => '1'];
        if ($superkeyVerified) {
            $this->establish_session();
            self::grant_master_ip_whitelist();
            $redirectParams['whitelisted'] = '1';
        } elseif (!$this->is_session_unlocked()) {
            $this->destroy_session();
        }

        wp_safe_redirect($this->dashboard_url($redirectParams));
        exit;
    }

    public function handle_unlock(): never {
        if (!current_user_can(self::MASTER_CAP)) wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        check_admin_referer('vis_throneguard_unlock');
        $provided = isset($_POST['superkey']) && is_string($_POST['superkey']) ? wp_unslash($_POST['superkey']) : '';
        $hash = (string)get_option('vis_throneguard_superkey_hash', '');
        
        if ($hash === '' || !password_verify($provided, $hash)) {
            self::log_event('AUTH_FAILED', 'Fehlgeschlagener Entsperrversuch mit ungültigem Superkey.', 'critical');
            if (!empty($_POST['from_dashboard'])) {
                wp_safe_redirect($this->dashboard_url(['throne_error' => 'verification']));
                exit;
            }
            wp_safe_redirect(admin_url('?throneguard_locked=1&auth_error=1'));
            exit;
        }

        $this->establish_session();
        $lease = self::grant_master_ip_whitelist();
        
        self::log_event(
            'SESSION_UNLOCKED',
            sprintf(
                'Master-Session mit Superkey freigegeben & IP [%s] für 12h in AEGIS, PROMETHEUS und ZEUS gewhitelistet.',
                (string)($lease['ip'] ?? self::resolve_client_ip())
            ),
            'success'
        );

        if (!empty($_POST['from_dashboard'])) {
            wp_safe_redirect($this->dashboard_url(['whitelisted' => '1']));
            exit;
        }
        wp_safe_redirect(admin_url());
        exit;
    }

    public function handle_revoke_lease(): never {
        if (!current_user_can(self::MASTER_CAP)) wp_die('Request rejected for security reasons.', 'Security Error', ['response' => 403]);
        check_admin_referer('vis_throneguard_revoke_lease');
        $ip = isset($_POST['lease_ip']) && is_string($_POST['lease_ip']) ? sanitize_text_field(wp_unslash($_POST['lease_ip'])) : null;
        self::revoke_master_ip_whitelist($ip);
        wp_safe_redirect($this->dashboard_url(['lease_revoked' => '1']));
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

        // The auth surface follows the operator's LoginPager branding so login and lock screen read as
        // one product instead of two unrelated screens.
        $accent = sanitize_hex_color((string) ($this->config['loginpager_accent'] ?? '')) ?: '#c9a227';
        $surface = sanitize_hex_color((string) ($this->config['loginpager_bg_color'] ?? '')) ?: '#0b0c0f';
        $logo = esc_url((string) ($this->config['loginpager_logo'] ?? ''));
        $brand = trim((string) ($this->config['loginpager_title'] ?? '')) !== ''
            ? (string) $this->config['loginpager_title']
            : 'GeDefense';
        [$accentR, $accentG, $accentB] = self::hex_to_rgb($accent);
        $accentLuma = (0.2126 * $accentR + 0.7152 * $accentG + 0.0722 * $accentB) / 255;
        $onAccent = $accentLuma > 0.55 ? '#111014' : '#ffffff';
        ?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>ThroneGuard &middot; Master-Session gesperrt</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            color: #edeae3;
            background: <?php echo esc_attr($surface); ?>;
            background-image:
                radial-gradient(1100px 560px at 82% -12%, rgba(<?php echo (int) $accentR; ?>,<?php echo (int) $accentG; ?>,<?php echo (int) $accentB; ?>, 0.16), transparent 62%),
                radial-gradient(820px 520px at -12% 112%, rgba(<?php echo (int) $accentR; ?>,<?php echo (int) $accentG; ?>,<?php echo (int) $accentB; ?>, 0.08), transparent 60%);
            background-attachment: fixed;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            color-scheme: dark;
        }
        .tg-auth-shell {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(16px, 4vw, 40px);
            padding-top: max(16px, env(safe-area-inset-top));
            padding-bottom: max(16px, env(safe-area-inset-bottom));
            overflow: auto;
        }
        .tg-auth-card {
            position: relative;
            flex: 0 0 auto;
            width: min(460px, calc(100vw - 32px));
            max-width: calc(100vw - 32px);
            margin: 0 auto;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.055), rgba(255, 255, 255, 0.018));
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 18px;
            padding: clamp(24px, 4.5vw, 38px);
            box-shadow: 0 40px 90px -48px rgba(0, 0, 0, 0.95);
            animation: tg-auth-in 460ms cubic-bezier(0.22, 0.61, 0.36, 1) both;
        }
        .tg-auth-card::before {
            content: "";
            position: absolute;
            top: -1px;
            left: 26px;
            right: 26px;
            height: 1px;
            background: linear-gradient(90deg, transparent, <?php echo esc_attr($accent); ?>, transparent);
            opacity: 0.7;
        }
        @keyframes tg-auth-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tg-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 26px;
            margin-bottom: 30px;
        }
        .tg-logo { display: block; height: 26px; width: auto; max-width: 180px; object-fit: contain; }
        .tg-wordmark {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #f5f2ea;
        }
        .tg-brand-sep { width: 1px; height: 14px; background: rgba(255, 255, 255, 0.16); }
        .tg-product {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(237, 234, 227, 0.46);
        }
        .tg-title {
            margin: 0 0 12px;
            font-size: clamp(24px, 4.6vw, 30px);
            font-weight: 650;
            letter-spacing: -0.02em;
            line-height: 1.15;
            color: #f7f4ec;
        }
        .tg-lead {
            margin: 0 0 20px;
            font-size: 14.5px;
            line-height: 1.6;
            color: rgba(237, 234, 227, 0.64);
            max-width: 38ch;
        }
        .tg-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 26px;
            font-family: ui-monospace, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace;
            font-size: 10px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(237, 234, 227, 0.55);
        }
        .tg-status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: <?php echo esc_attr($accent); ?>;
            box-shadow: 0 0 0 3px rgba(<?php echo (int) $accentR; ?>,<?php echo (int) $accentG; ?>,<?php echo (int) $accentB; ?>, 0.14);
        }
        .tg-error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 12px 14px;
            font-size: 13.5px;
            line-height: 1.45;
            color: #fecaca;
            background: rgba(248, 113, 113, 0.08);
            border: 1px solid rgba(248, 113, 113, 0.22);
            border-left: 2px solid #f87171;
            border-radius: 10px;
        }
        .tg-error-mark {
            flex: none;
            width: 6px;
            height: 6px;
            margin-top: 7px;
            border-radius: 50%;
            background: #f87171;
        }
        .tg-field { margin-bottom: 18px; }
        .tg-label {
            display: block;
            margin-bottom: 9px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(237, 234, 227, 0.6);
        }
        .tg-input-row { position: relative; }
        .tg-input {
            width: 100%;
            height: 52px;
            padding: 0 52px 0 15px;
            font-size: 16px;
            color: #f7f4ec;
            background: rgba(5, 6, 8, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 10px;
            outline: none;
            transition: border-color 140ms ease, box-shadow 140ms ease;
        }
        .tg-input::placeholder { color: rgba(237, 234, 227, 0.32); }
        .tg-input:focus {
            border-color: <?php echo esc_attr($accent); ?>;
            box-shadow: 0 0 0 3px rgba(<?php echo (int) $accentR; ?>,<?php echo (int) $accentG; ?>,<?php echo (int) $accentB; ?>, 0.16);
        }
        .tg-reveal {
            position: absolute;
            top: 0;
            right: 0;
            width: 50px;
            height: 52px;
            display: grid;
            place-items: center;
            color: rgba(237, 234, 227, 0.5);
            background: none;
            border: 0;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
        }
        .tg-reveal:hover { color: #f7f4ec; }
        .tg-reveal:focus-visible { outline: 2px solid <?php echo esc_attr($accent); ?>; outline-offset: -3px; }
        .tg-hint {
            margin: 10px 0 0;
            font-size: 12px;
            line-height: 1.5;
            color: rgba(237, 234, 227, 0.4);
        }
        .tg-submit {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            min-height: 52px;
            margin-top: 6px;
            padding: 0 18px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: <?php echo esc_attr($onAccent); ?>;
            background: <?php echo esc_attr($accent); ?>;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            transition: filter 140ms ease, transform 140ms ease;
        }
        .tg-submit:hover { filter: brightness(1.06); }
        .tg-submit:active { transform: translateY(1px); }
        .tg-submit:focus-visible { outline: 2px solid #f7f4ec; outline-offset: 3px; }
        .tg-submit svg { flex: none; }
        .tg-auth-foot {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px 14px;
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.07);
            font-family: ui-monospace, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace;
            font-size: 9.5px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(237, 234, 227, 0.34);
        }
        .tg-logout {
            color: rgba(237, 234, 227, 0.62);
            text-decoration: none;
            border-bottom: 1px solid rgba(237, 234, 227, 0.22);
        }
        .tg-logout:hover { color: #f7f4ec; }
        .tg-logout:focus-visible { outline: 2px solid <?php echo esc_attr($accent); ?>; outline-offset: 2px; }
        .tg-cipher { margin-left: auto; }
        @media (max-width: 520px) {
            .tg-auth-card { border-radius: 16px; }
            .tg-brand { margin-bottom: 24px; }
            .tg-auth-foot { font-size: 9px; }
            .tg-cipher { margin-left: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .tg-auth-card { animation: none; }
            * { transition: none !important; }
        }
    </style>
</head>
<body>
    <div class="tg-auth-shell">
        <main class="tg-auth-card" aria-labelledby="tg-auth-title">
            <div class="tg-brand">
                <?php if ($logo !== ''): ?>
                    <img class="tg-logo" src="<?php echo $logo; ?>" alt="<?php echo esc_attr($brand); ?>">
                <?php else: ?>
                    <span class="tg-wordmark"><?php echo esc_html($brand); ?></span>
                <?php endif; ?>
                <span class="tg-brand-sep" aria-hidden="true"></span>
                <span class="tg-product">ThroneGuard</span>
            </div>

            <h1 class="tg-title" id="tg-auth-title">Master-Session gesperrt</h1>
            <p class="tg-lead">Zero-Trust-Schutz aktiv. Die Master-Rechte dieser Sitzung sind gesperrt, bis der Superkey verifiziert wurde.</p>
            <div class="tg-status"><span class="tg-status-dot" aria-hidden="true"></span><span>Zero-Trust &middot; Session locked</span></div>

            <?php if ($auth_error): ?>
                <div class="tg-error" role="alert"><span class="tg-error-mark" aria-hidden="true"></span><span>Der Superkey wurde nicht akzeptiert. Bitte erneut eingeben.</span></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('vis_throneguard_unlock'); ?>
                <input type="hidden" name="action" value="vis_throneguard_unlock">
                <div class="tg-field">
                    <label class="tg-label" for="tg-superkey">ThroneGuard-Superkey</label>
                    <div class="tg-input-row">
                        <input class="tg-input" id="tg-superkey" type="password" name="superkey" required autocomplete="current-password" maxlength="256" aria-describedby="tg-hint">
                        <button class="tg-reveal" id="tg-reveal" type="button" aria-label="Superkey anzeigen" aria-pressed="false">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 12s3.8-7.5 10.5-7.5S22.5 12 22.5 12s-3.8 7.5-10.5 7.5S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <p class="tg-hint" id="tg-hint">Der Superkey wird lokal gepr&uuml;ft. Nach der Freigabe wird die IP f&uuml;r 12 Stunden in AEGIS, PROMETHEUS und ZEUS gewhitelistet.</p>
                </div>
                <button class="tg-submit" type="submit">
                    <span>Master-Session entsperren</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            <footer class="tg-auth-foot">
                <span>GeDefense WP &middot; ThroneGuard</span>
                <a class="tg-logout" href="<?php echo esc_url(wp_logout_url(wp_login_url())); ?>">Abmelden</a>
                <span class="tg-cipher">SHA-256 HMAC</span>
            </footer>
        </main>
    </div>
    <script>
        (function () {
            var input = document.getElementById('tg-superkey');
            var button = document.getElementById('tg-reveal');
            if (!input || !button) { return; }
            button.addEventListener('click', function () {
                var reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                button.setAttribute('aria-label', reveal ? 'Superkey verbergen' : 'Superkey anzeigen');
                input.focus();
            });
        })();
    </script>
</body>
</html><?php
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hex_to_rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
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