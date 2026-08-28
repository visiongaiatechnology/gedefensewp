<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit('VGT_ACCESS_DENIED');

/**
 * MODULE: ZEUS (Perimeter Firewall & WAF Controller) - OMEGA V7.0
 * STATUS: PLATIN VGT SUPREME (MODULAR & HARDENED)
 */
class VIS_Zeus {

    private array $config;
    private string $client_ip;
    private string $swarm_ip;
    private string $vault_dir;
    private string $waf_file;

    // Sub-Module
    private $env_manager;
    private $compiler;
    private $shield;

    public function __construct() {
        $this->vault_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/vgt-vault/zeus/' : dirname( ABSPATH ) . '/wp-content/vgt-vault/zeus/';
        $this->waf_file  = wp_normalize_path( $this->vault_dir . 'zeus-waf.php' );

        $this->config = get_option( 'vis_zeus_config', [
            'fw_basic'             => true,
            'fw_6g_blacklist'      => true,
            'fw_block_xmlrpc'      => true,
            'fw_fake_googlebot'    => true,
            'brute_rename_login'   => '',     
            'brute_magic_cookie'   => '',     
            'brute_404_lockout'    => 20,     
            'user_login_lockdown'  => 5,       
            'user_force_logout'    => 3600,  
            'fs_disable_edit'      => true,
            'fs_prevent_hotlink'   => false,
            'spam_comment_block'   => true
        ] );

        $this->client_ip = $this->extract_deterministic_ip();
        $this->swarm_ip  = $this->get_swarm_ip( $this->client_ip );

        $this->load_dependencies();
        $this->init_modules();
    }

    private function load_dependencies(): void {
        $dir = dirname(__FILE__) . '/src/';
        require_once $dir . 'class-zeus-env.php';
        require_once $dir . 'class-zeus-compiler.php';
        require_once $dir . 'class-zeus-shield.php';
    }

    private function init_modules(): void {
        $this->env_manager = new VIS_Zeus_Env( $this->vault_dir, $this->waf_file, $this->config );
        $this->compiler    = new VIS_Zeus_Compiler( $this->vault_dir, $this->waf_file, $this->config, $this->swarm_ip );
        $this->shield      = new VIS_Zeus_Shield( $this->config, $this->client_ip, $this->swarm_ip, $this->vault_dir );

        $this->env_manager->ensure_master_key_isolated();
        $this->shield->init_hooks();

        add_action( 'vgt_zeus_daily_cleanup', [ $this, 'prometheus_cache_cleanup' ] );
    }

    private function get_swarm_ip( string $ip ): string {
        if ( strpos( $ip, ':' ) !== false ) {
            $parts = explode( ':', $ip );
            return implode( ':', array_slice( $parts, 0, 4 ) ) . '::';
        }
        return preg_replace( '/\.\d+$/', '.0', $ip ) ?? $ip;
    }

    /** @return array{waf:bool,environment:array{user_ini:bool,htaccess:bool,wp_config:bool}} */
    public function deploy_perimeter_shield(): array {
        $this->compiler->deploy_waf();
        $environment = $this->env_manager->sync_all();
        if (!in_array(true, $environment, true)) {
            error_log('[ZEUS STORAGE] WAF compiled, but no host bootstrap configuration was writable.');
        }
        return ['waf' => true, 'environment' => $environment];
    }

    public function prometheus_cache_cleanup(): void {
        $cache_dir = $this->vault_dir . 'cache/';
        if ( ! is_dir( $cache_dir ) ) return;
        
        try {
            $iterator = new DirectoryIterator( $cache_dir );
            $now = time();
            foreach ( $iterator as $fileinfo ) {
                if ( $fileinfo->isFile() && ( $now - $fileinfo->getMTime() > 86400 ) ) {
                    @unlink( $fileinfo->getPathname() );
                }
            }
        } catch ( Exception $e ) {
            // VGT Silent Fail
        }
    }

    private function extract_deterministic_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $cf_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';

        if ( $cf_ip !== '' && filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
            if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                return $cf_ip; 
            }
        }

        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
            return $ip;
        }
        
        return '127.0.0.1';
    }
}
