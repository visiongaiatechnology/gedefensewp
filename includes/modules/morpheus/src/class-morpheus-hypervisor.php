<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Morpheus_Hypervisor {

    private Morpheus $core;

    public function __construct( Morpheus $core ) {
        $this->core = $core;
        
        $this->load_dependencies();
        $this->engage_shields();
    }

    private function load_dependencies(): void {
        $dir = dirname(__FILE__);
        require_once $dir . '/class-morpheus-tracer.php';
        require_once $dir . '/class-morpheus-ui.php';
        require_once $dir . '/shields/class-morpheus-shield-network.php';
        require_once $dir . '/shields/class-morpheus-shield-state.php';
        require_once $dir . '/shields/class-morpheus-shield-db.php';
    }

    private function engage_shields(): void {
        $tracer = new Morpheus_Tracer();
        $ui     = new Morpheus_UI( $this->core );

        $network_shield = new Morpheus_Shield_Network( $this->core, $tracer, $ui );
        $state_shield   = new Morpheus_Shield_State( $this->core, $tracer, $ui );
        $db_shield      = new Morpheus_Shield_DB( $this->core, $tracer, $ui );

        add_filter( 'pre_http_request', [ $network_shield, 'intercept_network' ], 9999, 3 );
        add_filter( 'pre_update_option', [ $state_shield, 'intercept_option_update' ], 9999, 3 );
        add_filter( 'pre_add_option', [ $state_shield, 'intercept_option_add' ], 9999, 3 );
        add_filter( 'query', [ $db_shield, 'intercept_database_query' ], 9999 );
    }
}
