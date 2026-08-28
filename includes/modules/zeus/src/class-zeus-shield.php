<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit('VGT_ACCESS_DENIED');

class VIS_Zeus_Shield {
    
    private array $config;
    private string $client_ip;
    private string $swarm_ip;
    private string $vault_dir;

    public function __construct( array $config, string $client_ip, string $swarm_ip, string $vault_dir ) {
        $this->config    = $config;
        $this->client_ip = $client_ip;
        $this->swarm_ip  = $swarm_ip;
        $this->vault_dir = $vault_dir;
    }

    public function init_hooks(): void {
        if ( ! empty( $this->config['brute_rename_login'] ) || ! empty( $this->config['brute_magic_cookie'] ) ) {
            add_action( 'init', [ $this, 'enforce_login_portal_obfuscation' ], -99999 );
        }

        if ( $this->config['brute_404_lockout'] > 0 ) {
            add_action( 'template_redirect', [ $this, 'track_404_anomalies' ], -99999 );
        }

        if ( $this->config['user_login_lockdown'] > 0 ) {
            add_action( 'wp_login_failed', [ $this, 'track_login_failures' ], -99999 );
            add_action( 'wp_authenticate', [ $this, 'verify_login_lockout' ], -99999 );
        }

        if ( $this->config['user_force_logout'] > 0 ) {
            add_action( 'admin_init', [ $this, 'enforce_session_timeout' ], -99999 );
            add_action( 'wp_login', [ $this, 'reset_session_activity' ], -99999, 2 );
        }

        if ( $this->config['fs_disable_edit'] && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
            define( 'DISALLOW_FILE_EDIT', true );
        }

        if ( $this->config['spam_comment_block'] ) {
            add_filter( 'preprocess_comment', [ $this, 'intercept_spam_comments' ], -99999 );
        }
    }

    public function enforce_login_portal_obfuscation(): void {
        if ( is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) return;

        $path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
        $is_login_path = ( strpos( (string) $path, 'wp-login.php' ) !== false || strpos( (string) $path, 'wp-admin' ) !== false );

        if ( ! $is_login_path ) return;

        $hades_config = get_option('vis_config', []);
        $hades_active = !empty($hades_config['hades_enabled']);
        $hades_param  = $hades_config['hades_admin_param'] ?? 'vgt_access';
        $hades_secret = $hades_config['hades_admin_secret'] ?? 'omega';
        
        $hades_cookie_valid = false;
        if (isset($_COOKIE['vgt_hades_gate'])) {
            $expected_hash = hash_hmac('sha256', $hades_secret, wp_salt('auth'));
            if (hash_equals($expected_hash, $_COOKIE['vgt_hades_gate'])) {
                $hades_cookie_valid = true;
            }
        }
        $has_hades_param = isset($_GET[$hades_param]) && $_GET[$hades_param] === $hades_secret;

        $hades_override = $hades_active && ($has_hades_param || $hades_cookie_valid);

        if ( ! empty( $this->config['brute_magic_cookie'] ) && ! $hades_override ) {
            $cookie_name = sanitize_key((string)$this->config['brute_magic_cookie']);
            if (!defined('VGT_MASTER_KEY') || VGT_MASTER_KEY === '') {
                $this->wp_hard_kill( 'AUTH_MAGIC_COOKIE_KEY_MISSING', 'Login portal cryptographic key unavailable.' );
            }
            $auth_key    = VGT_MASTER_KEY;
            $expected    = hash_hmac( 'sha256', $cookie_name . $this->swarm_ip, $auth_key );
            
            if ( ! isset( $_COOKIE[ $cookie_name ] ) || ! hash_equals( $expected, $_COOKIE[ $cookie_name ] ) ) {
                $this->sync_prometheus_score( 100.0 );
                $this->wp_hard_kill( 'AUTH_MAGIC_COOKIE_VIOLATION', 'Login portal accessed without valid cryptographic entry token.' );
            }
        }

        if ( ! empty( $this->config['brute_rename_login'] ) && ! $hades_override ) {
            $slug = sanitize_key((string)$this->config['brute_rename_login']);
            if ( ! isset( $_GET[ $slug ] ) ) {
                $this->sync_prometheus_score( 100.0 );
                $this->wp_hard_kill( 'AUTH_HIDDEN_PORTAL_VIOLATION', 'Direct access to wp-login.php is blocked.' );
            }
        }
    }

    public function track_404_anomalies(): void {
        if ( ! is_404() || is_user_logged_in() ) return;

        $limit     = (int) $this->config['brute_404_lockout'];
        $cache_key = 'vgt_404_' . md5( $this->swarm_ip );
        
        $strikes = $this->get_vault_strike( $cache_key, HOUR_IN_SECONDS );
        $strikes++;

        if ( $strikes >= $limit ) {
            $this->sync_prometheus_score( 100.0 );
            $this->wp_hard_kill( '404_EXCESSIVE_PROBING', sprintf('IP triggered %d 404 errors.', $strikes) );
        } else {
            $this->sync_prometheus_score( 100.0 );
        }

        $this->set_vault_strike( $cache_key, $strikes );
    }

    public function track_login_failures( string $username ): void {
        $cache_key = 'vgt_login_fail_' . md5( $this->swarm_ip );

        $strikes = $this->get_vault_strike( $cache_key, HOUR_IN_SECONDS * 12 );
        $strikes++;
        
        $this->sync_prometheus_score( 25.0 );
        $this->set_vault_strike( $cache_key, $strikes );
        
        if ( $strikes >= (int) $this->config['user_login_lockdown'] ) {
            $this->wp_hard_kill( 'BRUTE_FORCE_LOCKDOWN', 'Maximum login attempts exceeded on current request.' );
        }
    }

    public function verify_login_lockout( string $user_login = '' ): void {
        $limit     = (int) $this->config['user_login_lockdown'];
        $cache_key = 'vgt_login_fail_' . md5( $this->swarm_ip );

        $strikes = $this->get_vault_strike( $cache_key, HOUR_IN_SECONDS * 12 );

        if ( $strikes >= $limit ) {
            $this->sync_prometheus_score( 100.0 );
            $this->wp_hard_kill( 'BRUTE_FORCE_LOCKDOWN', sprintf('Maximum login attempts (%d) exceeded.', $limit) );
        }
    }

    public function enforce_session_timeout(): void {
        if ( wp_doing_ajax() ) return;
        
        $timeout = (int) $this->config['user_force_logout'];
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $last_activity = (int) get_user_meta( $user_id, 'vgt_last_activity', true );
        $current_time = time();

        if ( $last_activity > 0 && ( $current_time - $last_activity ) > $timeout ) {
            wp_logout();
            wp_safe_redirect( wp_login_url() . '?vgt_timeout=1' );
            exit;
        }

        if ( ( $current_time - $last_activity ) > 300 ) {
            update_user_meta( $user_id, 'vgt_last_activity', $current_time );
        }
    }

    public function reset_session_activity( string $user_login, \WP_User $user ): void {
        update_user_meta( $user->ID, 'vgt_last_activity', time() );
    }

    public function intercept_spam_comments( array $commentdata ): array {
        $content = $commentdata['comment_content'] ?? '';
        $link_count = substr_count( $content, 'http://' ) + substr_count( $content, 'https://' );
        
        if ( $link_count > 3 ) {
            $this->sync_prometheus_score( 100.0 );
            $this->wp_hard_kill( 'COMMENT_SPAM_SIGNATURE', 'Payload contains excessive hyperlinks.' );
        }
        return $commentdata;
    }

    private function get_vault_strike( string $key, int $ttl = 3600 ): int {
        if ( function_exists( 'apcu_fetch' ) ) {
            $val = apcu_fetch( $key );
            return $val !== false ? (int)$val : 0;
        }
        
        $file = $this->vault_dir . 'cache/' . $key . '.php';
        if ( file_exists( $file ) && ( time() - filemtime( $file ) ) < $ttl ) {
            return max( 0, filesize( $file ) - 12 ); 
        }
        return 0;
    }

    private function set_vault_strike( string $key, int $value ): void {
        if ( function_exists( 'apcu_store' ) ) {
            apcu_store( $key, $value, 86400 );
            return;
        }

        $file = $this->vault_dir . 'cache/' . $key . '.php';
        $payload = chr(60) . '?php die;' . chr(63) . chr(62) . str_repeat( '.', $value );
        if ( ! is_dir( dirname( $file ) ) ) {
            @mkdir( dirname( $file ), 0700, true );
        }
        
        $fh = @fopen( $file, 'c+' );
        if ( $fh ) {
            if ( flock( $fh, LOCK_EX ) ) {
                ftruncate( $fh, 0 );
                fwrite( $fh, $payload );
                flock( $fh, LOCK_UN );
            }
            fclose( $fh );
            @chmod( $file, 0600 );
        }
    }

    private function sync_prometheus_score( float $points ): void {
        $prom_key = 'vis_prom_infra_' . md5( $this->swarm_ip );
        $now = (float)time();
        
        if ( function_exists( 'apcu_fetch' ) ) {
            $state = apcu_fetch($prom_key);
            if (!is_array($state)) $state = ['score' => 0.0, 'last_time' => $now, 'last_ip' => $this->client_ip, 'cooldown_until' => 0.0];
            
            $decay = max(0, $now - $state['last_time']) * 0.2;
            $state['score'] = max(0, $state['score'] - $decay) + $points;
            $state['last_time'] = $now;
            $state['last_ip'] = $this->client_ip;
            
            apcu_store( $prom_key, $state, 86400 );
            return;
        }

        $prom_file = $this->vault_dir . 'cache/' . $prom_key . '.php';
        $php_die_tag = chr(60) . '?php die;' . chr(63) . chr(62);
        if ( ! is_dir( dirname( $prom_file ) ) ) {
            @mkdir( dirname( $prom_file ), 0700, true );
        }
        
        if ( ! file_exists( $prom_file ) ) {
            @file_put_contents( $prom_file, $php_die_tag . str_repeat('.', (int)$points), LOCK_EX );
        } else {
            $last_time = (float)(@filemtime($prom_file) ?: $now);
            $current_score = max(0, (@filesize($prom_file) ?: 12) - 12);
            $decay = max(0, $now - $last_time) * 0.2;
            $new_score = max(0, $current_score - $decay) + $points;
            
            @file_put_contents( $prom_file, $php_die_tag . str_repeat( '.', (int)$new_score ), LOCK_EX );
        }
        @chmod( $prom_file, 0600 );
    }

    private function wp_hard_kill( string $violation, string $details ): void {
        if ( ob_get_level() > 0 ) ob_end_clean();
        do_action( 'vgt_sentinel_log', 'ZEUS', $violation, $details, $this->client_ip );
        http_response_code( 403 );
        die( '403' );
    }
}
