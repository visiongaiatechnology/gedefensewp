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
            $validated = \VIS_Security::validate_public_http_url($url, true);
            if ( is_string($validated) && $validated !== '' ) {
                $safe_url = $validated;
            }
        } catch (\Throwable $e) {
            // Reject only the URL, never continue with an empty target.
            $this->ui->execute_kill( $caller, 'NETWORK_SSRF_GUARD', 'Outbound URL rejected by Sentinel network policy.' );
            $safe_url = $url;
        }

        $host = parse_url( $safe_url, PHP_URL_HOST );
        if ( empty( $host ) ) {
            $host = parse_url( $url, PHP_URL_HOST );
        }
        if ( ! is_string( $host ) || $host === '' ) return $response;
        $host = strtolower( rtrim( (string) $host, '.' ) );

        // A site is always allowed to talk to itself: WP-Cron, loopback health
        // probes and plugin-internal callbacks must not be classified as egress.
        if ( $this->is_own_host( $host ) ) return $response;

        $this->ui->log_audit_trace( $caller, 'NETWORK', sprintf( 'Egress TCP/IP attempt to host: %s', $host ) );

        $matrix = $this->core->get_plugin_matrix( $caller );

        if ( ! in_array( $host, $matrix['network'], true ) ) {
            $this->ui->execute_kill( $caller, 'NETWORK_ISOLATION_BREACH', sprintf( 'TCP/IP egress attempt to unauthorized host: %s', $host ) );
        }

        return $response;
    }

    /**
     * Returns true for the site's own hostnames, the server address and loopback.
     * Prevents the network shield from blocking WordPress' own self-calls.
     */
    private function is_own_host( string $host ): bool {
        if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) return true;

        $candidates = array();
        if ( function_exists( 'home_url' ) ) $candidates[] = (string) parse_url( (string) home_url(), PHP_URL_HOST );
        if ( function_exists( 'site_url' ) ) $candidates[] = (string) parse_url( (string) site_url(), PHP_URL_HOST );
        if ( isset( $_SERVER['HTTP_HOST'] ) ) $candidates[] = (string) $_SERVER['HTTP_HOST'];
        if ( isset( $_SERVER['SERVER_NAME'] ) ) $candidates[] = (string) $_SERVER['SERVER_NAME'];
        if ( isset( $_SERVER['SERVER_ADDR'] ) ) $candidates[] = (string) $_SERVER['SERVER_ADDR'];

        foreach ( $candidates as $candidate ) {
            $candidate = strtolower( trim( (string) $candidate ) );
            if ( str_contains( $candidate, ':' ) && ! str_starts_with( $candidate, '[' ) && filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) === false ) {
                $candidate = strtolower( (string) parse_url( 'http://' . $candidate, PHP_URL_HOST ) );
            }
            $candidate = rtrim( $candidate, '.' );
            if ( $candidate !== '' && ( $host === $candidate || str_ends_with( $host, '.' . $candidate ) ) ) {
                return true;
            }
        }

        return false;
    }
}
