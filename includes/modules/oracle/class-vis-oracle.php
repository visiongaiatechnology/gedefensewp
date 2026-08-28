<?php
declare(strict_types=1);

// Namespace entfernt. Das alte Modul lief im globalen Namespace.
// Eine plötzliche Namespace-Kapselung führt bei Legacy-Instanziierungen (`new VIS_Oracle()`) sofort zu einem "Class not found" Fatal Error.

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MODULE: ORACLE (The Sight) - OMEGA V2.1.1 (HOTFIX)
 * Führt On-Demand System-Audits durch.
 * Deterministische Logik, Fail-Safe Typisierung, Global Scope Kompatibilität.
 */
class VIS_Oracle {

    public function run_prophecy(): array {
        $r = [];

        // 1. CONFIGURATION & FILESYSTEM
        // Prüft auch, ob wp-config.php aus Sicherheitsgründen ein Verzeichnis höher liegt.
        $config_path = file_exists( ABSPATH . 'wp-config.php' ) ? ABSPATH . 'wp-config.php' : dirname( ABSPATH ) . '/wp-config.php';
        $r[] = $this->evaluate( ! is_writable( $config_path ), __('Config Protection', 'vgt-sentinel'), __('wp-config.php ist read-only (Sicher).', 'vgt-sentinel'), __('CRITICAL: wp-config.php ist beschreibbar (RCE Vektor)!', 'vgt-sentinel') );
        $r[] = $this->evaluate( ! file_exists( WP_CONTENT_DIR . '/debug.log' ), __('Debug Log Secrecy', 'vgt-sentinel'), __('Kein öffentliches debug.log gefunden.', 'vgt-sentinel'), __('CRITICAL: debug.log ist öffentlich zugänglich (Info Leak).', 'vgt-sentinel') );
        $r[] = $this->evaluate( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT, __('File Editor Lockdown', 'vgt-sentinel'), __('Integrierter Datei-Editor ist deaktiviert.', 'vgt-sentinel'), __('Editor aktiv (Erhebliches RCE Risiko).', 'vgt-sentinel') );
        
        // 2. CRYPTOGRAPHY (Salts & Keys)
        $r[] = $this->check_salts();

        // 3. DATABASE & USERS
        global $wpdb;
        // Fallback-Chain für absolute Sicherheit, falls base_prefix in Custom-Setups fehlt.
        $prefix = $wpdb->base_prefix ?? $wpdb->prefix ?? 'wp_';
        $r[] = $this->evaluate( $prefix !== 'wp_', __('DB Prefix Hardening', 'vgt-sentinel'), sprintf( __('Custom Prefix aktiv (%s).', 'vgt-sentinel'), $prefix ), __('Standard "wp_" Prefix gefunden (Brute-Force Risk).', 'vgt-sentinel') );
        
        $admin_exists = get_user_by( 'login', 'admin' );
        $r[] = $this->evaluate( ! $admin_exists, __('Default Admin Blacklist', 'vgt-sentinel'), __('Standard-User "admin" existiert nicht.', 'vgt-sentinel'), __('User "admin" existiert (Primäres Brute-Force Ziel).', 'vgt-sentinel') );
        
        $user_one = get_user_by( 'id', 1 );
        $vis_config = get_option('vis_config', []);
        $is_protected_by_titan = !empty($vis_config['titan_enabled']) && !empty($vis_config['titan_anti_enum']);
        if ($is_protected_by_titan) {
            $r[] = [
                'check'  => __('User ID 1 Ghosting', 'vgt-sentinel'),
                'status' => 'PASS',
                'msg'    => esc_html__( 'User ID 1 Ghosting ist über Titan Hardening (Anti-Enumeration) aktiv und geschützt.', 'vgt-sentinel' )
            ];
        } else {
            $r[] = $this->evaluate( ! $user_one, __('User ID 1 Ghosting', 'vgt-sentinel'), __('User ID 1 ist nicht belegt (Hardened).', 'vgt-sentinel'), __('User ID 1 existiert (Enumeration Risk).', 'vgt-sentinel') );
        }

        // 4. NETWORK & ENVIRONMENT
        $r[] = $this->evaluate( is_ssl(), __('Transport Layer Security', 'vgt-sentinel'), __('SSL/TLS Verschlüsselung aktiv.', 'vgt-sentinel'), __('Verbindung unverschlüsselt (HTTP) - Man-in-the-Middle Gefahr.', 'vgt-sentinel') );
        $r[] = $this->check_server_signature();
        $r[] = $this->check_php_exposure();
        $r[] = $this->check_directory_listing();

        // 5. REST API EXPOSURE
        // Berücksichtigt auch Server (wie Apache mit FastCGI), die den Auth-Header umleiten.
        $has_auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) || isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
        $r[] = $this->evaluate( $has_auth_header, __('Auth Header Propagation', 'vgt-sentinel'), __('Authorization Headers werden korrekt propagiert.', 'vgt-sentinel'), __('Authorization Headers fehlen (Mögliche API-Blockade durch Server-Config).', 'vgt-sentinel') );

        return $r;
    }

    private function check_directory_listing(): array {
        $has_protection = file_exists( WP_CONTENT_DIR . '/index.php' ) && file_exists( WP_CONTENT_DIR . '/uploads/index.php' );
        return $this->evaluate( $has_protection, __('Directory Browsing', 'vgt-sentinel'), __('Basisschutz (index.php) in Uploads/Content aktiv.', 'vgt-sentinel'), __('Mögliches Directory Listing (Schutzdateien fehlen).', 'vgt-sentinel') );
    }

    private function check_salts(): array {
        $keys = [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ];
        $default_string = 'put your unique phrase here';
        
        foreach ( $keys as $key ) {
            if ( ! defined( $key ) ) {
                return $this->evaluate( false, __('Security Keys (Salts)', 'vgt-sentinel'), '', sprintf( __('CRITICAL: Der Key "%s" fehlt komplett.', 'vgt-sentinel'), $key ) );
            }
            // TYPE SAFETY: Cast zu (string) für strlen() im strict_types=1 Modus. Verhindert Fatal Error, wenn Konstante z.B. boolean ist.
            if ( constant( $key ) === $default_string || strlen( (string) constant( $key ) ) < 40 ) {
                return $this->evaluate( false, __('Security Keys (Salts)', 'vgt-sentinel'), '', sprintf( __('CRITICAL: Der Key "%s" nutzt Standardwerte oder ist zu kurz.', 'vgt-sentinel'), $key ) );
            }
        }
        
        return $this->evaluate( true, __('Security Keys (Salts)', 'vgt-sentinel'), __('Alle kryptographischen Keys sind individuell konfiguriert.', 'vgt-sentinel'), '' );
    }

    private function check_server_signature(): array {
        $exposed = false;
        
        if ( function_exists( 'headers_list' ) ) {
            $headers = headers_list();
            foreach ( $headers as $header ) {
                if ( stripos( $header, 'X-Powered-By:' ) !== false ) {
                    $exposed = true;
                    break;
                }
            }
        }
        
        if ( ! $exposed && isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
            // TYPE SAFETY: Cast zu (string) für preg_match(), verhindert Absturz bei unerwarteten Array/Null-Werten in globalen Server-Variablen.
            if ( preg_match( '/\d+\.\d+/', (string) $_SERVER['SERVER_SOFTWARE'] ) ) {
                $exposed = true;
            }
        }
        
        return $this->evaluate( ! $exposed, __('Server Signature Extraction', 'vgt-sentinel'), __('Server-Signatur und Versionen sind unterdrückt.', 'vgt-sentinel'), __('Server gibt via Header oder Environment exakte Versionen preis (Targeting Risk).', 'vgt-sentinel') );
    }

    private function check_php_exposure(): array {
        $display_errors = ini_get( 'display_errors' );
        $is_exposed = ( $display_errors === '1' || strtolower( (string) $display_errors ) === 'on' );
        
        return $this->evaluate( ! $is_exposed, __('PHP Display Errors', 'vgt-sentinel'), __('Fehlermeldungen sind im Frontend verborgen.', 'vgt-sentinel'), __('CRITICAL: Display Errors aktiv (Full Path Disclosure möglich).', 'vgt-sentinel') );
    }

    /**
     * VGT Logic-Kernel für Validierung.
     * Deterministische Bool-Logik: True = Sicher, False = Unsicher.
     */
    private function evaluate( bool $is_secure, string $name, string $msg_secure, string $msg_insecure ): array {
        return [
            'check'  => $name,
            'status' => $is_secure ? 'PASS' : 'FAIL',
            'msg'    => $is_secure ? $msg_secure : $msg_insecure
        ];
    }
}
