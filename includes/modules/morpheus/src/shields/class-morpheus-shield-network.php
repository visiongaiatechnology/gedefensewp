<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Morpheus_Shield_Network {
    
    private Morpheus $core;
    private Morpheus_Tracer $tracer;
    private Morpheus_UI $ui;

    public function __construct( Morpheus $core, Morpheus_Tracer $tracer, Morpheus_UI $ui ) {
        $this->core = $core;
        $this->tracer = $tracer;
        $this->ui = $ui;
    }

    public function intercept_network( mixed $response, array $parsed_args, string $url ): mixed {
        if ( Morpheus::$is_internal_action ) return $response;

        $caller = $this->tracer->identify_caller();
        if ( 'core' === $caller || 'theme' === $caller ) return $response; 

        $safe_url = $url;
        try {
            $safe_url = \VIS_Security::validate_public_http_url($url, true);
        } catch (\Throwable $e) {
            $this->ui->execute_kill( $caller, 'NETWORK_SSRF_GUARD', 'Outbound URL rejected by Sentinel network policy.' );
        }

        $host = parse_url( $safe_url, PHP_URL_HOST );
        if ( empty( $host ) ) {
            $host = parse_url( $url, PHP_URL_HOST );
        }
        if ( empty( $host ) ) return $response;
        $host = strtolower((string)$host);

        $this->ui->log_audit_trace( $caller, 'NETWORK', sprintf( 'Egress TCP/IP attempt to host: %s', $host ) );

        $matrix = $this->core->get_plugin_matrix( $caller );

        if ( ! in_array( $host, $matrix['network'], true ) ) {
            $this->ui->execute_kill( $caller, 'NETWORK_ISOLATION_BREACH', sprintf( 'TCP/IP egress attempt to unauthorized host: %s', $host ) );
        }

        return $response;
    }
}
