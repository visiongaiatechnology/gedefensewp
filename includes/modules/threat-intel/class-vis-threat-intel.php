<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\ThreatIntel;

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

// ============================================================================
// PATTERN 1.5.A — Mandatory Exception Hierarchy
// ============================================================================
class AppException        extends \Exception {}
class ValidationException extends AppException {}  // USER-FACING: Message shown verbatim
class SecurityException   extends AppException {}  // INTERNAL: Generic message to client, full detail to error_log
class StorageException    extends AppException {}  // INTERNAL: Generic message to client, full detail to error_log

/**
 * THREAT INTELLIGENCE ENGINE — ZERO-TRUST REPUTATION MATRIX & SWAP SYNCHRONIZER
 * 
 * 100% Opt-In Threat Intelligence Matrix: Synchronisiert alle 12 Stunden bösartige IP-Adressen
 * und CIDR-Blöcke aus 9 weltweiten Reputations- und C2-Feeds.
 * Vollständiger Differential-Swap: Veraltete oder bereinigte IPs werden nach jedem Sync automatisch gepruned.
 * Styx-Self-Bypass: Interne, fälschungssichere Whitelist für offizielle Feed-Domains verhindert Egress-Blockaden.
 * Gehärtet gegen SQL-Injection, Poisoning und Puffer-Exhaustion via strikter Regex- und Längen-Validierung.
 */
final class ThreatIntelligence {

    private static ?self $instance = null;

    private string $table_threats;
    private array $config = [];
    private bool $schema_checked = false;
    private static array $request_cache = [];
    private static ?array $cached_cidrs = null;
    private static bool $sync_in_progress = false;

    public const CRON_HOOK     = 'vis_threat_intel_cron_sync';
    public const CRON_INTERVAL = 43200; // 12 Hours
    public const LOCK_KEY      = 'vis_threat_intel_sync_lock';
    public const OPTION_CONFIG = 'vis_threat_intel_config';
    public const OPTION_CIDRS  = 'vis_threat_intel_cidrs';

    /**
     * Festprogrammierte, fälschungssichere Whitelist der offiziellen Threat-Intelligence-Domains.
     * Schließt Missbrauch durch beliebige Plugins oder fremde Domains mathematisch aus.
     */
    public const TRUSTED_FEED_HOSTS = [
        'feodotracker.abuse.ch',
        'www.spamhaus.org',
        'spamhaus.org',
        'cinsscore.com',
        'lists.blocklist.de',
        'blocklist.de',
        'rules.emergingthreats.net',
        'emergingthreats.net',
        'raw.githubusercontent.com',
        'iplists.firehol.org',
        'firehol.org',
        'check.torproject.org',
        'torproject.org',
    ];

    public const FEEDS = [
        'feodo_c2' => [
            'id'       => 'feodo_c2',
            'name'     => 'Feodo Tracker Botnet C2',
            'desc'     => 'Aktive Command-and-Control Server (Abuse.ch)',
            'url'      => 'https://feodotracker.abuse.ch/downloads/ipblocklist.txt',
            'format'   => 'text_ips',
            'default'  => true,
        ],
        'spamhaus_drop_v4' => [
            'id'       => 'spamhaus_drop_v4',
            'name'     => 'Spamhaus DROP IPv4',
            'desc'     => 'Don\'t Route Or Peer - Gekaperte / Bösartige Netze (IPv4)',
            'url'      => 'https://www.spamhaus.org/drop/drop_v4.json',
            'format'   => 'json_lines_cidr',
            'default'  => true,
        ],
        'spamhaus_drop_v6' => [
            'id'       => 'spamhaus_drop_v6',
            'name'     => 'Spamhaus DROP IPv6',
            'desc'     => 'Don\'t Route Or Peer - Gekaperte / Bösartige Netze (IPv6)',
            'url'      => 'https://www.spamhaus.org/drop/drop_v6.json',
            'format'   => 'json_lines_cidr',
            'default'  => true,
        ],
        'cins_badguys' => [
            'id'       => 'cins_badguys',
            'name'     => 'CINS Army Badguys',
            'desc'     => 'Aktive Angreifer & Malicious Scanner (CINS Score)',
            'url'      => 'https://cinsscore.com/list/ci-badguys.txt',
            'format'   => 'text_ips',
            'default'  => true,
        ],
        'blocklist_de' => [
            'id'       => 'blocklist_de',
            'name'     => 'blocklist.de All-Attackers',
            'desc'     => 'Fail2ban Reporting Service (SSH, Mail, Web Attackers)',
            'url'      => 'https://lists.blocklist.de/lists/all.txt',
            'format'   => 'text_ips',
            'default'  => true,
        ],
        'emerging_threats' => [
            'id'       => 'emerging_threats',
            'name'     => 'Emerging Threats Block IPs',
            'desc'     => 'Proofpoint Emerging Threats Compromised IP List',
            'url'      => 'https://rules.emergingthreats.net/fwrules/emerging-Block-IPs.txt',
            'format'   => 'text_ips',
            'default'  => true,
        ],
        'ipsum' => [
            'id'       => 'ipsum',
            'name'     => 'IPsum Threat Intelligence',
            'desc'     => 'Aggregierte Threat-Level Liste (Stamparm)',
            'url'      => 'https://raw.githubusercontent.com/stamparm/ipsum/master/ipsum.txt',
            'format'   => 'tsv_ip_score',
            'default'  => true,
        ],
        'firehol_level1' => [
            'id'       => 'firehol_level1',
            'name'     => 'FireHOL Level 1',
            'desc'     => 'Maximale Bedrohungsstufe (Cybercrime, Abuse, Blacklists)',
            'url'      => 'https://iplists.firehol.org/files/firehol_level1.netset',
            'format'   => 'text_ips_and_cidr',
            'default'  => true,
        ],
        'tor_exit_nodes' => [
            'id'       => 'tor_exit_nodes',
            'name'     => 'Tor Bulk Exit Nodes',
            'desc'     => 'Offizielle Tor-Projekt Exit-Node Liste',
            'url'      => 'https://check.torproject.org/torbulkexitlist',
            'format'   => 'text_ips',
            'default'  => true,
        ],
    ];

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function get_instance(): self {
        return self::instance();
    }

    public static function is_sync_in_progress(): bool {
        return self::$sync_in_progress;
    }

    private function __construct() {
        global $wpdb;
        $prefix = isset($wpdb) && isset($wpdb->prefix) ? (string)$wpdb->prefix : 'wp_';
        $this->table_threats = $prefix . 'vis_threat_intel';

        $this->load_config();
        $this->init_cron();
    }

    public function load_config(): void {
        $saved = get_option(self::OPTION_CONFIG, []);
        $defaults = [
            'enabled'         => false, // OPT-IN BY DEFAULT!
            'block_outbound'  => true,  // Styx Outbound Egress Block
            'block_inbound'   => true,  // Cerberus Inbound Perimeter Block
            'active_feeds'    => array_fill_keys(array_keys(self::FEEDS), true),
            'last_sync'       => null,
            'sync_stats'      => [],
            'total_threats'   => 0,
        ];
        $this->config = is_array($saved) ? array_merge($defaults, $saved) : $defaults;
    }

    public function get_config(): array {
        return $this->config;
    }

    public function is_enabled(): bool {
        return !empty($this->config['enabled']);
    }

    public function is_outbound_enabled(): bool {
        return $this->is_enabled() && !empty($this->config['block_outbound']);
    }

    public function is_inbound_enabled(): bool {
        return $this->is_enabled() && !empty($this->config['block_inbound']);
    }

    /**
     * Registriert den 12-Stunden Cron-Schedule und das Cron-Event.
     */
    private function init_cron(): void {
        add_filter('cron_schedules', [$this, 'filter_cron_schedules']);
        add_action(self::CRON_HOOK, [$this, 'handle_cron_sync']);

        if ($this->is_enabled()) {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + 60, 'every_12_hours', self::CRON_HOOK);
            }
        } else {
            if (wp_next_scheduled(self::CRON_HOOK)) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
            }
        }
    }

    public function filter_cron_schedules(array $schedules): array {
        if (!isset($schedules['every_12_hours'])) {
            $schedules['every_12_hours'] = [
                'interval' => self::CRON_INTERVAL,
                'display'  => 'GeDefense Threat Intel (Alle 12 Stunden)',
            ];
        }
        return $schedules;
    }

    public function handle_cron_sync(): void {
        if (!$this->is_enabled()) {
            return;
        }
        try {
            $this->sync_all_feeds();
        } catch (\Throwable $e) {
            error_log('[VGT THREAT INTEL CRON ERROR] Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Strikte Tabellen-Initialisierung mit Feed-Isolation für atomares Pruning
     */
    public function enforce_schema(): void {
        if ($this->schema_checked) return;

        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Migration Check: Falls legacy Schema ohne feed_key existiert, erneuern
        $suppress = $wpdb->suppress_errors(true);
        $has_feed_key = (bool)$wpdb->get_var("SHOW COLUMNS FROM {$this->table_threats} LIKE 'feed_key'");
        if (!$has_feed_key && $wpdb->get_var("SHOW TABLES LIKE '{$this->table_threats}'") === $this->table_threats) {
            $wpdb->query("DROP TABLE IF EXISTS {$this->table_threats}");
        }
        $wpdb->suppress_errors($suppress);

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_threats} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            feed_key VARCHAR(32) NOT NULL DEFAULT 'general',
            ip_or_cidr VARCHAR(49) NOT NULL,
            ip_type VARCHAR(10) NOT NULL DEFAULT 'v4',
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_feed_ip (feed_key, ip_or_cidr),
            KEY idx_ip (ip_or_cidr),
            KEY idx_type (ip_type),
            KEY idx_feed_seen (feed_key, last_seen)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $this->schema_checked = true;
    }

    /**
     * Synchronisiert alle aktivierten Feeds (mit Concurrency Lock und atomarem Swap).
     * @return array Statusbericht pro Feed
     */
    public function sync_all_feeds(): array {
        $this->enforce_schema();

        // Concurrency Lock setzen (TTL: 15 Minuten)
        $locked = get_transient(self::LOCK_KEY);
        if ($locked) {
            return [
                'status'  => 'locked',
                'message' => 'Synchronisation läuft bereits im Hintergrund.',
            ];
        }
        set_transient(self::LOCK_KEY, time(), 900);

        self::$sync_in_progress = true;

        $results = [];
        $total_imported = 0;

        try {
            foreach (self::FEEDS as $feed_key => $feed_meta) {
                // Prüfen ob Feed in Config aktiviert ist
                if (isset($this->config['active_feeds'][$feed_key]) && !$this->config['active_feeds'][$feed_key]) {
                    $results[$feed_key] = ['status' => 'skipped', 'count' => 0];
                    continue;
                }

                $feed_res = $this->fetch_and_ingest_feed($feed_meta);
                $results[$feed_key] = $feed_res;
                $total_imported += (int)($feed_res['count'] ?? 0);
            }

            // Inaktive Feeds bereinigen
            $this->purge_inactive_feeds();

            // CIDR Cache aus Datenbank aktualisieren
            $this->refresh_cidr_cache();

            // Cache invalidieren
            self::$request_cache = [];

            // Gesamtzahl aus Datenbank abfragen
            global $wpdb;
            $suppress = $wpdb->suppress_errors(true);
            $total_count = (int)$wpdb->get_var("SELECT COUNT(DISTINCT ip_or_cidr) FROM {$this->table_threats}");
            $wpdb->suppress_errors($suppress);

            // Metadaten speichern
            $this->config['last_sync']     = current_time('mysql');
            $this->config['sync_stats']    = $results;
            $this->config['total_threats'] = $total_count;
            update_option(self::OPTION_CONFIG, $this->config);

        } finally {
            self::$sync_in_progress = false;
            delete_transient(self::LOCK_KEY);
        }

        return [
            'status'         => 'success',
            'timestamp'      => current_time('mysql'),
            'total_threats'  => $total_count,
            'feeds'          => $results,
        ];
    }

    /**
     * Lädt einen einzelnen Feed herunter, filtert und swappt ihn via Batch-Upsert + Pruning.
     */
    public function fetch_and_ingest_feed(array $feed): array {
        $feed_id = (string)$feed['id'];
        $url     = (string)$feed['url'];
        $format  = (string)$feed['format'];

        $sync_started_at = current_time('mysql', 1);

        // Flag setzen, damit Styx erkennt, dass Threat Intelligence aktiv Daten bezieht
        $prev_state = self::$sync_in_progress;
        self::$sync_in_progress = true;

        try {
            // 1. Sicheres Herunterladen mit Timeout und Header-Schutz
            $response = wp_safe_remote_get($url, [
                'timeout'     => 25,
                'redirection' => 2,
                'sslverify'   => true,
                'headers'     => [
                    'User-Agent' => 'VisionGaia-Threat-Intel/8.2.2 (Security Engine; Autonomous Node)',
                    'Accept'     => 'text/plain,application/json,*/*',
                ],
            ]);
        } finally {
            self::$sync_in_progress = $prev_state;
        }

        if (is_wp_error($response)) {
            error_log("[VGT THREAT INTEL] Feed {$feed_id} download error: " . $response->get_error_message());
            return ['status' => 'error', 'message' => $response->get_error_message(), 'count' => 0];
        }

        $code = (int)wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log("[VGT THREAT INTEL] Feed {$feed_id} HTTP status: {$code}");
            return ['status' => 'error', 'message' => "HTTP {$code}", 'count' => 0];
        }

        $body = wp_remote_retrieve_body($response);
        $size = strlen($body);

        // Puffer-Schutz: Feeds über 20MB abweisen
        if ($size === 0 || $size > 20 * 1024 * 1024) {
            error_log("[VGT THREAT INTEL] Feed {$feed_id} payload size boundary violation ({$size} bytes)");
            return ['status' => 'error', 'message' => 'Size boundary violation', 'count' => 0];
        }

        // 2. Zerlegung und Format-Normalisierung
        $lines = preg_split("/\r\n|\n|\r/", $body);
        if (!is_array($lines)) {
            return ['status' => 'error', 'message' => 'Line split error', 'count' => 0];
        }

        $sanitized_entries = [];

        foreach ($lines as $raw_line) {
            $candidate = $this->extract_candidate_from_line($raw_line, $format);
            if ($candidate === null) {
                continue;
            }

            $validated = $this->validate_and_sanitize_ip_or_cidr($candidate);
            if ($validated === null) {
                continue;
            }

            $sanitized_entries[$validated['target']] = $validated['type'];
        }

        // 3. Batch-Persistierung in der Datenbank
        $imported_count = $this->batch_upsert_entries($sanitized_entries, $feed_id);

        // 4. VGT KERNEL PRUNING / LIST-SWAP:
        // Alle Einträge für diesen Feed, die im neuen Payload nicht mehr enthalten sind, sauber entfernen!
        $pruned_count = 0;
        if ($imported_count > 0) {
            global $wpdb;
            $suppress = $wpdb->suppress_errors(true);
            $pruned_count = (int)$wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_threats} WHERE feed_key = %s AND last_seen < %s",
                $feed_id,
                $sync_started_at
            ));
            $wpdb->suppress_errors($suppress);
        }

        self::$request_cache = [];

        return [
            'status' => 'success',
            'count'  => $imported_count,
            'pruned' => $pruned_count,
        ];
    }

    /**
     * Bereinigt Einträge von Feeds, die vom Administrator deaktiviert wurden.
     */
    public function purge_inactive_feeds(): void {
        global $wpdb;
        $this->enforce_schema();

        $active = [];
        if (!empty($this->config['active_feeds']) && is_array($this->config['active_feeds'])) {
            foreach ($this->config['active_feeds'] as $fk => $act) {
                if (!empty($act)) $active[] = (string)$fk;
            }
        }

        $suppress = $wpdb->suppress_errors(true);
        if (!empty($active)) {
            $placeholders = implode(', ', array_fill(0, count($active), '%s'));
            $query = $wpdb->prepare("DELETE FROM {$this->table_threats} WHERE feed_key NOT IN ({$placeholders})", $active);
            $wpdb->query($query);
        } else {
            $wpdb->query("TRUNCATE TABLE {$this->table_threats}");
        }
        $wpdb->suppress_errors($suppress);

        $this->refresh_cidr_cache();
        self::$request_cache = [];
    }

    /**
     * Aktualisiert den In-Memory- und Options-Cache für CIDR-Bereiche.
     */
    public function refresh_cidr_cache(): void {
        global $wpdb;
        $suppress = $wpdb->suppress_errors(true);
        $cidrs = $wpdb->get_col("SELECT DISTINCT ip_or_cidr FROM {$this->table_threats} WHERE ip_type LIKE 'cidr_%'");
        $wpdb->suppress_errors($suppress);
        $cidr_list = is_array($cidrs) ? array_values(array_unique($cidrs)) : [];
        update_option(self::OPTION_CIDRS, $cidr_list, false);
        self::$cached_cidrs = $cidr_list;
    }

    /**
     * Extrahiert den Kandidaten-String abhängig vom Feed-Format
     */
    private function extract_candidate_from_line(string $line, string $format): ?string {
        $line = trim($line);
        if ($line === '') return null;

        // Kommentare ignorieren
        if (str_starts_with($line, '#') || str_starts_with($line, '//') || str_starts_with($line, ';')) {
            return null;
        }

        // Maximale Zeilenlänge (DoS-Schutz)
        if (strlen($line) > 512) {
            return null;
        }

        switch ($format) {
            case 'text_ips':
            case 'text_ips_and_cidr':
                $parts = preg_split('/\s+/', $line);
                return !empty($parts[0]) ? trim($parts[0]) : null;

            case 'tsv_ip_score':
                $parts = preg_split('/\s+/', $line);
                return !empty($parts[0]) ? trim($parts[0]) : null;

            case 'json_lines_cidr':
                try {
                    $json = json_decode($line, true, 4, JSON_THROW_ON_ERROR);
                    if (is_array($json) && !empty($json['cidr']) && is_string($json['cidr'])) {
                        return trim($json['cidr']);
                    }
                } catch (\Throwable) {
                    return null;
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * VGT KERNEL: Strikte Whitelist-Validierung & SQL-Injection Immunität
     * Garantiert, dass ausschließlich valide IPv4, IPv6 oder CIDR-Bereiche passieren.
     * 
     * @return array{target: string, type: string}|null
     */
    public function validate_and_sanitize_ip_or_cidr(string $candidate): ?array {
        $candidate = trim($candidate);
        $len = strlen($candidate);

        // 1. Längen-Schranke: Gültige IPs/CIDRs liegen strikt zwischen 3 (z.B. ::1) und 49 Zeichen
        if ($len < 3 || $len > 49) {
            return null;
        }

        // 2. Zeichen-Whitelist: Ausschließlich Ziffern, Hexadezimal (a-f), Punkt, Doppelpunkt, Slash
        if (!preg_match('/^[0-9a-fA-F.:\/]+$/', $candidate)) {
            error_log('[SEC] VGT Threat Intel: Illegal characters in feed token: ' . substr($candidate, 0, 32));
            return null;
        }

        // 3. Format-Validierung
        if (str_contains($candidate, '/')) {
            $parts = explode('/', $candidate);
            if (count($parts) !== 2 || !ctype_digit($parts[1])) {
                return null;
            }

            $base_ip = $parts[0];
            $prefix = (int)$parts[1];

            if (filter_var($base_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                if ($prefix < 0 || $prefix > 32) return null;
                return ['target' => $candidate, 'type' => 'cidr_v4'];
            }

            if (filter_var($base_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                if ($prefix < 0 || $prefix > 128) return null;
                return ['target' => $candidate, 'type' => 'cidr_v6'];
            }

            return null;
        }

        // Reines IPv4
        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return ['target' => $candidate, 'type' => 'v4'];
        }

        // Reines IPv6
        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return ['target' => $candidate, 'type' => 'v6'];
        }

        return null;
    }

    /**
     * Batch-Insert via $wpdb->prepare() in 500er Transaktionen mit feed_key Isolation.
     */
    private function batch_upsert_entries(array $entries, string $feed_key): int {
        if (empty($entries)) return 0;

        global $wpdb;
        $now = current_time('mysql');
        $batch_size = 500;
        $chunks = array_chunk($entries, $batch_size, true);
        $total_affected = 0;

        $suppress = $wpdb->suppress_errors(true);

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];

            foreach ($chunk as $target => $type) {
                $placeholders[] = "(%s, %s, %s, %s, %s)";
                $values[] = (string)$feed_key;
                $values[] = (string)$target;
                $values[] = (string)$type;
                $values[] = $now; // first_seen
                $values[] = $now; // last_seen
            }

            $query = "INSERT INTO {$this->table_threats} 
                (feed_key, ip_or_cidr, ip_type, first_seen, last_seen)
                VALUES " . implode(', ', $placeholders) . "
                ON DUPLICATE KEY UPDATE 
                last_seen = VALUES(last_seen)";

            $prepared = $wpdb->prepare($query, $values);
            if ($prepared) {
                $res = $wpdb->query($prepared);
                if ($res !== false) {
                    $total_affected += count($chunk);
                }
            }
        }

        $wpdb->suppress_errors($suppress);
        return $total_affected;
    }

    /**
     * O(1) Prüfung ob eine gegebene IP in den Threat-Intelligence-Feeds verzeichnet ist.
     * Verwendet L1 In-Memory Request Cache, B-Tree Index Seek in der DB und bitweise CIDR-Checks.
     */
    public function is_ip_threat(string $ip): bool {
        if (!$this->is_enabled()) return false;

        $ip = trim($ip);
        if ($ip === '') return false;

        // L1 Request Cache
        if (isset(self::$request_cache[$ip])) {
            return self::$request_cache[$ip];
        }

        global $wpdb;
        $suppress = $wpdb->suppress_errors(true);
        $found = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$this->table_threats} WHERE ip_or_cidr = %s LIMIT 1",
            $ip
        ));
        $wpdb->suppress_errors($suppress);

        if ($found === 1) {
            self::$request_cache[$ip] = true;
            return true;
        }

        // Check CIDR Ranges
        if ($this->check_cidr_match($ip)) {
            self::$request_cache[$ip] = true;
            return true;
        }

        self::$request_cache[$ip] = false;
        return false;
    }

    /**
     * Prüft ob eine IPv4-Adresse in einem der abonnierten CIDR-Blöcke liegt
     */
    private function check_cidr_match(string $ip): bool {
        if (self::$cached_cidrs === null) {
            $cidrs = get_option(self::OPTION_CIDRS, []);
            self::$cached_cidrs = is_array($cidrs) ? $cidrs : [];
        }

        if (empty(self::$cached_cidrs)) {
            return false;
        }

        $is_v4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        foreach (self::$cached_cidrs as $cidr) {
            if ($is_v4 && str_contains($cidr, '.') && str_contains($cidr, '/')) {
                if (self::ip_in_cidr_v4($ip, $cidr)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Hochperformanter bitweiser IPv4-CIDR-Subnetzabgleich (O(1), Microsekunden)
     */
    public static function ip_in_cidr_v4(string $ip, string $cidr): bool {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) return false;

        $ip_long     = ip2long($ip);
        $subnet_long = ip2long($parts[0]);
        $mask        = (int)$parts[1];

        if ($ip_long === false || $subnet_long === false || $mask < 0 || $mask > 32) {
            return false;
        }

        $netmask = -1 << (32 - $mask);
        return ($ip_long & $netmask) === ($subnet_long & $netmask);
    }

    /**
     * Gibt Metriken für das Dashboard zurück
     */
    public function get_statistics(): array {
        global $wpdb;
        $suppress = $wpdb->suppress_errors(true);
        $total = 0;
        $v4 = 0;
        $v6 = 0;
        $cidr = 0;
        $feed_stats = [];

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_threats}'") === $this->table_threats) {
            $total = (int)$wpdb->get_var("SELECT COUNT(DISTINCT ip_or_cidr) FROM {$this->table_threats}");
            $v4    = (int)$wpdb->get_var("SELECT COUNT(DISTINCT ip_or_cidr) FROM {$this->table_threats} WHERE ip_type = 'v4'");
            $v6    = (int)$wpdb->get_var("SELECT COUNT(DISTINCT ip_or_cidr) FROM {$this->table_threats} WHERE ip_type = 'v6'");
            $cidr  = (int)$wpdb->get_var("SELECT COUNT(DISTINCT ip_or_cidr) FROM {$this->table_threats} WHERE ip_type LIKE 'cidr_%'");

            $f_rows = $wpdb->get_results("SELECT feed_key, COUNT(*) as cnt FROM {$this->table_threats} GROUP BY feed_key");
            if (is_array($f_rows)) {
                foreach ($f_rows as $row) {
                    $feed_stats[(string)$row->feed_key] = ['count' => (int)$row->cnt];
                }
            }
        }
        $wpdb->suppress_errors($suppress);

        $next_cron = wp_next_scheduled(self::CRON_HOOK);

        return [
            'total'          => $total,
            'ipv4'           => $v4,
            'ipv6'           => $v6,
            'cidrs'          => $cidr,
            'last_sync'      => $this->config['last_sync'] ?? null,
            'next_sync'      => $next_cron ? date('Y-m-d H:i:s', $next_cron) : null,
            'is_enabled'     => $this->is_enabled(),
            'block_outbound' => $this->is_outbound_enabled(),
            'block_inbound'  => $this->is_inbound_enabled(),
            'feed_stats'     => $feed_stats,
        ];
    }
}

// Alias für Abwärtskompatibilität und globale Aufrufe
class_alias(ThreatIntelligence::class, 'VIS_Threat_Intelligence');
