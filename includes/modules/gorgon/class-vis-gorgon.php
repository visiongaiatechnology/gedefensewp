<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Gorgon;

if ( ! defined( 'ABSPATH' ) ) exit( 'VGT Protocol: Direct access denied.' );

/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * MODULE: GORGON - NEURAL INTELLIGENCE GRID V4.1
 * STATUS: DIAMANT VGT SUPREME STATUS
 */
final class Gorgon {

    private static ?self $instance = null;
    private Gorgon_Sync_Engine $sync_engine;
    private Gorgon_Uplink $uplink;

    private function __construct() {
        $dir = dirname(__FILE__) . '/src/';
        require_once $dir . 'class-gorgon-config.php';
        require_once $dir . 'class-gorgon-harvester.php';
        require_once $dir . 'class-gorgon-uplink.php';
        require_once $dir . 'class-gorgon-sync-engine.php';
        
        // VGT SUPREME: Lade den AJAX Controller
        require_once $dir . 'class-vis-gorgon-ajax.php';

        $config = new Gorgon_Config($this);
        $harvester = new Gorgon_Harvester($config);
        $this->uplink = new Gorgon_Uplink($config, $this);
        $this->sync_engine = new Gorgon_Sync_Engine($config, $harvester, $this->uplink, $this);

        // Mount AJAX Endpoints
        if ( wp_doing_ajax() ) {
            Gorgon_Ajax::mount_endpoints();
        }

        add_action( 'vgt_gorgon_neural_sync', [ $this, 'execute_sync_cycle' ] );
        if ( ! wp_next_scheduled( 'vgt_gorgon_neural_sync' ) ) {
            wp_schedule_event( time(), 'hourly', 'vgt_gorgon_neural_sync' );
        }
    }

    private function __clone(): void {}
    
    public function __wakeup(): void { 
        $this->execute_kill('SYSTEM', 'MEMORY_CORRUPTION', 'Singleton hijacking blocked.'); 
    }
    
    public function __unserialize(array $data): void { 
        $this->execute_kill('SYSTEM', 'MEMORY_CORRUPTION', 'Singleton hijacking blocked.'); 
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) { 
            self::$instance = new self(); 
        }
        return self::$instance;
    }

    /**
     * @VGT_HOT_PATH: Global Reputation Check
     * Exponierter Contract für das Aegis L1-Shield.
     */
    public function query_global_reputation( string $ip ): int {
        return $this->uplink->query_global_reputation($ip);
    }

    public function execute_sync_cycle( $force = false ): void {
        $this->sync_engine->run($force);
    }

    public function execute_kill( string $module, string $violation, string $details ): void {
        error_log( "VGT GORGON FATAL: [$module] $violation - $details" );
        
        if ( wp_is_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            throw new \RuntimeException( "VGT OMEGA PROTOCOL: KERNEL PROTECTION ACTIVE." );
        }

        if ( ! headers_sent() ) {
            http_response_code( 403 );
        }
        
        die( "VGT OMEGA PROTOCOL: KERNEL PROTECTION ACTIVE." );
    }
}
