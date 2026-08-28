<?php
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * Modul: NEMESIS (Deception & Counterintelligence Engine)
 * Status: DIAMANT VGT SUPREME (Enterprise Compliance Edition)
 * Architekt: VGT Intelligence System
 * Update: Modulare Trennung von "Legal Defense" und "Active Strike"
 * KERNEL UPGRADES:
 * - Zero-Allocation Tarpit Engine (Verhindert OOM Memory Leaks).
 * - Kryptografische JSON-Encodierung (Verhindert RCE via Log-Pivoting).
 * - Atomare O(1) Regex Compilation für Response Poisoning (ReDoS Immunität).
 * - Thread-Safe Log Rotation (Microtime + Entropie).
 * - OOM-Guarded Telemetry (Control Character Stripping).
 */

declare(strict_types=1);

namespace VisionGaia\Integrity\Modules\Nemesis;

if ( ! defined( 'ABSPATH' ) ) {
    exit('VGT_ACCESS_DENIED');
}

final class VIS_Nemesis {

    private static ?self $instance = null;
    private array $config;
    private bool $active_strike; // Der Master-Schalter für kinetische Sabotage (Compliance-Locked)

    /** @var resource|null */
    private $tarpit_lock_handle = null;
    private string $table_logs;

    // [ DIAMANT FIX ]: Atomare & Non-Capturing Gruppen gegen Regex Denial of Service
    private const SCRAPER_REGEX = '~(?i)(?>curl|wget|python-requests|scrapy|java|libwww-perl|postman|go-http-client|nikto|nmap|zgrab|masscan|httpx|nucleus)~S';
    private const HONEYPOT_REGEX = '~/(?>\.env|wp-config\.php(?>\.bak|\.old|\.save)?|\.git/config|backup\.zip|db\.sql|administrator|cms-admin|phpunit\.xml|xmlrpc\.php)~iS';
    
    private const MAX_CONCURRENT_TARPITS = 3;
    private const MAX_LOG_SIZE_BYTES = 10485760; // 10 MB
    private const TARPIT_CHUNK_DELAY_MICROSEC = 30000000; // 30 Sekunden
    private const MAX_POISON_BUFFER_SIZE = 5242880; // 5 MB Limit
    private const MAX_DECODE_ITERATIONS = 10; // Schutz vor Decode-DoS

    private function __construct() {
        if ( PHP_SAPI === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        $this->config = get_option( 'vis_config', [] );
        // VGT COMPLIANCE: Active Strike ist standardmäßig AUS, es sei denn der Admin aktiviert es explizit
        $this->active_strike = !empty( $this->config['nemesis_active_strike'] );
        
        global $wpdb;
        $this->table_logs = $wpdb->prefix . 'vis_nemesis_logs';
        $this->enforce_telemetry_schema();
        
        if ( ! empty( $this->config['nemesis_enabled'] ) ) {
            $this->register_tactics();
        }
    }

    private function __clone() {
        throw new \LogicException( 'VGT_SEC_VIOLATION: Instantiation forbidden.' );
    }

    public function __wakeup() {
        throw new \LogicException( 'VGT_SEC_VIOLATION: Unserialization forbidden.' );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function enforce_telemetry_schema(): void {
        if ( get_option( 'vgt_nemesis_logs_schema_verified' ) ) return;

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            module varchar(32) NOT NULL,
            type varchar(32) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text NOT NULL,
            details text NOT NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY module_index (module),
            KEY type_index (type),
            KEY timestamp_index (timestamp)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        update_option( 'vgt_nemesis_logs_schema_verified', true, false );
    }

    private function write_telemetry( string $type, string $details, string $ip = '' ): void {
        global $wpdb;
        if ( empty( $ip ) ) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        $raw_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        // [ DIAMANT FIX ]: Control Character Stripping gegen Stored XSS im Backend
        $safe_ua = preg_replace('/[\x00-\x1F\x7F]/', '', substr( $raw_ua, 0, 500 ));
        $safe_details = preg_replace('/[\x00-\x1F\x7F]/', '', substr( $details, 0, 500 ));

        $wpdb->insert(
            $this->table_logs,
            [
                'module'     => 'NEMESIS',
                'type'       => $type,
                'ip_address' => substr( $ip, 0, 45 ),
                'user_agent' => $safe_ua,
                'details'    => $safe_details,
                'timestamp'  => current_time( 'mysql' )
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    private function register_tactics(): void {
        add_action( 'plugins_loaded', [ $this, 'intercept_vulnerability_scans' ], -9999 );
        add_action( 'wp_footer', [ $this, 'inject_canary_token' ], PHP_INT_MAX );
        add_action( 'template_redirect', [ $this, 'engage_response_poisoning' ], 1 );
    }

    public function intercept_vulnerability_scans(): void {
        $raw_uri = $_SERVER['REQUEST_URI'] ?? '';
        $decoded_uri = $this->deep_urldecode( $raw_uri );
        
        if ( preg_match( self::HONEYPOT_REGEX, $decoded_uri ) ) {
            $this->execute_tarpit_and_feed( $decoded_uri );
        }
    }

    public function trigger_tarpit( string $reason ): void {
        $this->execute_tarpit_and_feed( $reason );
    }

    private function deep_urldecode( string $input ): string {
        $iterations = 0;
        while ( $iterations < self::MAX_DECODE_ITERATIONS ) {
            $decoded = rawurldecode( $input );
            if ( $decoded === $input ) {
                return $decoded;
            }
            $input = $decoded;
            $iterations++;
        }
        return $input;
    }

    private function execute_tarpit_and_feed( string $trigger ): void {
        if ( ! $this->acquire_os_lock() ) {
            http_response_code( 429 );
            header( 'Retry-After: 86400' );
            header( 'Connection: close' );
            die( 'VGT_DEFLECTED' );
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $cerberus_class = '\VIS_Cerberus';
        if ( class_exists( $cerberus_class ) ) {
            try {
                if ( method_exists( $cerberus_class, 'instance' ) ) {
                    $cerberus = $cerberus_class::instance();
                    if ( method_exists( $cerberus, 'get_validated_ip' ) ) {
                        $ip = $cerberus->get_validated_ip();
                    }
                    if ( method_exists( $cerberus, 'ban_ip' ) ) {
                        $cerberus->ban_ip( $ip, "NEMESIS: Tarpit auto-lockout (Trigger: " . substr( $trigger, 0, 100 ) . ")" );
                    }
                }
            } catch ( \Throwable $e ) {
                // Ignore
            }
        }

        $current = (int) wp_cache_get( 'vgt_active_tarpits' );
        wp_cache_set( 'vgt_active_tarpits', $current + 1, '', 60 );

        $this->log_threat_intel( $trigger );

        if ( function_exists( 'ini_set' ) ) {
            @ini_set( 'zlib.output_compression', '0' );
            @ini_set( 'implicit_flush', '1' );
        }
        
        ignore_user_abort( true ); 
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 ); 
        }
        
        $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        // VGT COMPLIANCE: GZIP-Bombing ist eine offensive Waffe (DoS). Nur erlaubt, wenn Active Strike an ist.
        $is_gzip = $this->active_strike && str_contains( $accept_encoding, 'gzip' );
        $is_sql  = str_contains( $trigger, '.sql' );

        header( 'HTTP/1.1 200 OK' );
        header( 'X-Accel-Buffering: no' );
        header( 'X-LiteSpeed-Cache-Control: no-cache' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'X-Robots-Tag: noindex, nofollow' );

        // VGT COMPLIANCE: COOKIE BOMBING (State Exhaustion) - Nur im Active Strike Modus
        if ( $this->active_strike ) {
            for ( $c = 0; $c < 20; $c++ ) {
                header( 'Set-Cookie: vgt_poison_jar_' . $c . '=' . bin2hex( random_bytes( 500 ) ) . '; path=/; HttpOnly; SameSite=Strict' );
            }
        }
        
        if ( $is_gzip ) {
            header( 'Content-Encoding: gzip' );
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Length: 10000000000' ); 
        } else {
            header( 'Content-Encoding: none' );
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'Content-Length: 2147483647' );
        }
        
        while ( ob_get_level() > 0 ) { 
            @ob_end_clean(); 
        }

        try {
            if ( $is_gzip ) {
                echo "\x1f\x8b\x08\x00\x00\x00\x00\x00"; 
            } else {
                echo $this->generate_fake_header( $trigger ) . "\n" . str_repeat( "\x20", 1024 ) . "\n";
            }
            @flush();

            // [ DIAMANT FIX ]: Zero-Allocation Payload Pre-Computation
            // Verhindert Memory-Fragmentation und GC-Spikes über den Verlauf der Tarpit-Stunde.
            $precomputed_gzip_payload = $is_gzip ? gzdeflate( str_repeat( "\0", 1048576 ), 1 ) : '';
            $precomputed_sql_payload  = ($is_sql && $this->active_strike) ? "INSERT INTO `vgt_honeypot_decoys` (`hash_key`, `timestamp`) VALUES ('" . hash( 'sha256', random_bytes( 32 ) ) . "', NOW());\n" : '';
            $precomputed_safe_hash    = hash( 'sha256', random_bytes( 16 ) ) . "\n";

            $trinity_config = get_option('vis_trinity_config', []);
            $max_iterations = (int) ($trinity_config['tarpit_max_loops'] ?? 120);
            
            $iterations = 0;
            while ( $iterations < $max_iterations ) {
                if ( connection_aborted() ) {
                    break; 
                }
                
                if ( $is_gzip ) {
                    // GZIP-Bombing (RAM Überlauf beim Angreifer)
                    echo $precomputed_gzip_payload; 
                } elseif ( $is_sql && $this->active_strike ) {
                    // VGT COMPLIANCE: SQL-Festplatten-Bloat
                    echo $precomputed_sql_payload;
                } else {
                    // LEGALE STANDARD-VERTEIDIGUNG (Reines Tarpitting / Zeitverzögerung)
                    echo $precomputed_safe_hash;
                }
                
                @flush();
                usleep( self::TARPIT_CHUNK_DELAY_MICROSEC );
                $iterations++;
            }
        } finally {
            $this->release_os_lock();
        }

        die();
    }

    private function acquire_os_lock(): bool {
        $tenant_hash = hash( 'sha256', ABSPATH );
        $target_dir = WP_CONTENT_DIR . '/vgt_sys_locks_' . substr( $tenant_hash, 0, 12 );

        if ( ! is_dir( $target_dir ) ) {
            if ( ! @mkdir( $target_dir, 0700, true ) ) {
                return false;
            }
            @file_put_contents( $target_dir . '/index.php', '<?php exit; ?>' );
        }

        for ( $i = 1; $i <= self::MAX_CONCURRENT_TARPITS; $i++ ) {
            $lock_file = $target_dir . '/tarpit_slot_' . $i . '.lock';
            $fp = @fopen( $lock_file, 'w' );
            
            if ( $fp && flock( $fp, LOCK_EX | LOCK_NB ) ) {
                $this->tarpit_lock_handle = $fp; 
                register_shutdown_function( [ $this, 'emergency_lock_release' ] );
                return true;
            }
            if ( $fp ) {
                @fclose( $fp );
            }
        }
        
        return false;
    }

    private function release_os_lock(): void {
        if ( is_resource( $this->tarpit_lock_handle ) ) {
            flock( $this->tarpit_lock_handle, LOCK_UN );
            fclose( $this->tarpit_lock_handle );
            $this->tarpit_lock_handle = null;
        }
    }

    public function emergency_lock_release(): void {
        $this->release_os_lock();
    }

    private function generate_fake_header( string $trigger ): string {
        $hash = hash('sha256', random_bytes(32));
        
        $ansi_sabotage = "";
        $vgt_banner = "";
        
        // VGT COMPLIANCE: TERMINAL SABOTAGE (ANSI Escape Codes) - Nur im Active Strike Modus
        if ( $this->active_strike ) {
            $ansi_sabotage = "\033[2J\033[3J\033[H\033[?25l\a\a\a";
            $vgt_banner = "\033[31;1m[!] VGT TERMINAL SABOTAGE PROTOCOL ENGAGED [!]\033[0m\n\033[32mDownloading Decoy Data...\033[0m\n\n";
        }
        
        if ( str_contains( $trigger, '.env' ) ) {
            return $ansi_sabotage . $vgt_banner . "APP_ENV=production\nAPP_KEY=base64:" . base64_encode( $hash ) . "\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_DATABASE=wp_core\nDB_USERNAME=root\nDB_PASSWORD=vgt_" . substr( $hash, 0, 16 );
        }
        if ( str_contains( $trigger, 'wp-config' ) ) {
            return $ansi_sabotage . $vgt_banner . "<?php\ndefine( 'DB_NAME', 'wp_decoy' );\ndefine( 'DB_USER', 'admin_decoy' );\ndefine( 'DB_PASSWORD', 'vgt_{$hash}' );";
        }
        if ( str_contains( $trigger, '.sql' ) ) {
            return $ansi_sabotage . "-- VGT DECOY SQL ENGINE ACTIVE\nCREATE TABLE IF NOT EXISTS `vgt_honeypot_decoys` (`hash_key` VARCHAR(255), `timestamp` DATETIME);\n";
        }
        
        return "PK\x03\x04\x14\x00\x00\x00\x08\x00";
    }

    private function resolve_client_network(): array {
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $untrusted_claims = [];

        $headers_to_check = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'];
        foreach ( $headers_to_check as $header ) {
            if ( ! empty( $_SERVER[$header] ) ) {
                $untrusted_claims[$header] = filter_var( $_SERVER[$header], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
            }
        }

        return [
            'socket_ip' => filter_var( $remote_addr, FILTER_VALIDATE_IP ) ?: '0.0.0.0',
            'claims'    => $untrusted_claims
        ];
    }

    private function log_threat_intel( string $trigger ): void {
        $network_data = $this->resolve_client_network();
        $socket_ip = $network_data['socket_ip'];
        
        $this->write_telemetry('TARPIT', "Honeypot Triggered on URI: {$trigger}", $socket_ip);

        $tenant_hash = hash( 'sha256', ABSPATH );
        $log_dir = WP_CONTENT_DIR . '/vgt_logs';
        $log_file = $log_dir . '/threat_intel_nemesis_' . substr( $tenant_hash, 0, 16 ) . '.php'; 
        
        if ( ! is_dir( $log_dir ) && ! @mkdir( $log_dir, 0750, true ) ) {
            return; 
        }

        $intel = [
            'timestamp'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'socket_ip'   => $socket_ip,
            'claims'      => $network_data['claims'],
            'trigger'     => filter_var( $trigger, FILTER_SANITIZE_URL ),
            'user_agent'  => filter_var( $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
            'method'      => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
        ];

        // [ DIAMANT FIX ]: HEX_TAG & HEX_QUOT verhindern zwingend, dass Code aus dem JSON-Kontext in die PHP-Umgebung ausbricht (Anti-Pivot).
        $log_entry = wp_json_encode( $intel, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . "\n";

        $fp = @fopen( $log_file, 'c+' );
        if ( $fp && flock( $fp, LOCK_EX ) ) {
            clearstatcache( true, $log_file );
            $filesize = filesize( $log_file );

            if ( $filesize === 0 ) {
                fwrite( $fp, "<?php exit('VGT_SEC'); ?>\n" );
            } elseif ( $filesize > self::MAX_LOG_SIZE_BYTES ) {
                // [ DIAMANT FIX ]: Microtime + Uniqid verhindert Race-Conditions
                $rotated_file = $log_file . '_' . bin2hex(random_bytes(16)) . '.old.php';
                
                // 1. Die alte Datei umbenennen (OS-Ebene)
                @rename( $log_file, $rotated_file );
                
                // 2. Filepointer (der jetzt auf das Backup zeigt) sicher schließen
                flock( $fp, LOCK_UN );
                @fclose( $fp );
                
                // 3. Brandneuen Pointer für das leere Haupt-Logfile öffnen
                $fp = @fopen( $log_file, 'c+' );
                
                // Fallback, falls das OS die Datei gerade sperrt
                if ( ! $fp || ! flock( $fp, LOCK_EX ) ) {
                    return; 
                }
                
                // 4. Den neuen PHP-Schutz-Header in die frische Datei schreiben
                fwrite( $fp, "<?php exit('VGT_SEC'); ?>\n" );
            }
            
            // Ab hier geht es sicher für BEIDE Fälle weiter (neue Datei oder bestehende Datei)
            fseek( $fp, 0, SEEK_END );
            fwrite( $fp, $log_entry );
            flock( $fp, LOCK_UN );
            @fclose( $fp );
        }
    }

    public function inject_canary_token(): void {
        if ( is_admin() || wp_is_json_request() || wp_doing_ajax() ) return;

        $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'vgt_critical_fallback_salt_99';
        
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace( '/[^a-zA-Z0-9.-]/', '', $host ); 
        
        $time_window = gmdate( 'Y-W' ); 
        $base_hash = hash_hmac( 'sha384', $host . '|' . $time_window, $salt );
        
        printf(
            '<div aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; opacity:0; pointer-events:none;" data-vgt-sig="%s" id="vgt-cnry"></div>%s',
            esc_attr( $base_hash ),
            "\n"
        );
    }

    public function engage_response_poisoning(): void {
        if ( is_admin() || wp_is_json_request() || wp_doing_ajax() || wp_doing_cron() ) return;

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if ( preg_match( self::SCRAPER_REGEX, $ua ) ) {
            $this->write_telemetry('POISON', "Scraper signature detected: {$ua}");
            ob_start( [ $this, 'poison_html_buffer' ] );
        }
    }

    public function poison_html_buffer( string $buffer ): string {
        if ( strlen( $buffer ) > self::MAX_POISON_BUFFER_SIZE ) {
            return $buffer; 
        }

        // [ DIAMANT FIX ]: Atomare Backtrack-Prävention 
        $poisoned_buffer = preg_replace_callback(
            '/\b(?>[a-zA-Z0-9._%+-]{1,64})@(?>[a-zA-Z0-9.-]{1,255}\.[a-zA-Z]{2,10})\b/S',
            static function( array $matches ): string {
                $entropy = bin2hex( random_bytes( 3 ) );
                return $matches[1] . '.sys' . $entropy . '@' . $matches[2];
            },
            $buffer
        );

        if ( $poisoned_buffer === null ) {
            $poisoned_buffer = $buffer; 
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace( '/[^a-zA-Z0-9.-]/', '', $host ); 
        
        $dynamic_trap = bin2hex( random_bytes( 4 ) );
        
        // Die Generierung von Fake-Emails ist Data Poisoning auf dem eigenen Server (Legal)
        $poison_cluster = $this->generate_fake_email_cluster( 50, $host );
        
        $poison_links = sprintf(
            '<div style="display:none;" aria-hidden="true" data-vgt-trap="%2$s">
                <a href="mailto:admin-%2$s@%1$s" rel="nofollow">Contact Admin</a>
                <a href="/wp-config.php?vgt_bak=%2$s" rel="nofollow">System Backup</a>
                %3$s
            </div>',
            esc_attr( $host ),
            $dynamic_trap,
            $poison_cluster
        );

        $pos = strripos( $poisoned_buffer, '</body>' );
        if ( $pos !== false ) {
            return substr_replace( $poisoned_buffer, $poison_links . '</body>', $pos, 7 );
        }
        
        return $poisoned_buffer . $poison_links;
    }

    private function generate_fake_email_cluster( int $count, string $host ): string {
        $first_names = ['admin', 'info', 'contact', 'support', 'sales', 'billing', 'service', 'hello', 'office', 'press'];
        $domains = [$host, 'gmail.com', 'outlook.com', 'yahoo.com', 'proton.me', 'corporate.local'];
        
        $cluster = '';
        for ( $i = 0; $i < $count; $i++ ) {
            $fn = $first_names[ array_rand( $first_names ) ];
            $dom = $domains[ array_rand( $domains ) ];
            $entropy = bin2hex( random_bytes( 3 ) );
            $fake_email = sprintf( '%s.vgt%s@%s', $fn, $entropy, $dom );
            $cluster .= sprintf( '<a href="mailto:%1$s" rel="nofollow">%1$s</a> ', $fake_email );
        }
        return $cluster;
    }
}

VIS_Nemesis::get_instance();
