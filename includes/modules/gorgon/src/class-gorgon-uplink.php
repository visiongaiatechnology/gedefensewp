<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Gorgon;

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

final class Gorgon_Uplink {
    
    private Gorgon_Config $config;
    private Gorgon $core;

    public function __construct(Gorgon_Config $config, Gorgon $core) {
        $this->config = $config;
        $this->core = $core;
    }

    public function query_global_reputation( string $ip ): int {
        if ( !$this->config->is_active() ) return 0;
        
        $ip_long = ip2long($ip);
        if ( false === $ip_long ) return 0;

        // VGT-Fix: Hex-kodierter MD5 Key für stabile Transients
        $cache_key = 'vgt_r_' . md5($ip);
        $cached_score = get_transient( $cache_key );
        
        if ( false !== $cached_score ) return (int) $cached_score;

        $nexus_url = $this->config->get_preemptive_url();
        $api_key   = $this->config->get_api_key();
        
        if ( '' === $nexus_url || '' === $api_key ) return 0;

        $json_payload = wp_json_encode([
            'site'     => $this->config->get_node_id(), 
            'query_ip' => $ip
        ]);

        if ( false === $json_payload ) return 0;

        $encrypted_payload = wp_json_encode([ 'cipher' => $this->config->encrypt_payload( $json_payload ) ]);
        if ( false === $encrypted_payload ) return 0;

        $response = wp_remote_post( $nexus_url, [
            'body'    => $encrypted_payload,
            'headers' => [ 
                'X-VGT-Nexus-Auth'       => $api_key,
                'X-VGT-Sentinel-Version' => defined('VIS_VERSION') ? VIS_VERSION : '7.4.0',
                'X-VGT-Sentinel-Magic'   => 'vgt-magic-noc-9938-omega-fusion',
                'Content-Type'           => 'application/json',
                'Connection'             => 'close' 
            ],
            'timeout'   => 2,
            'sslverify' => true,
            'redirection' => 0,
        ]);

        if ( is_wp_error( $response ) ) return 0;

        $body = wp_remote_retrieve_body( $response );
        $body_decoded = json_decode( $body, true );
        if ( ! is_array( $body_decoded ) || empty( $body_decoded['cipher'] ) ) return 0;

        $decrypted = $this->config->decrypt_payload( $body_decoded['cipher'] );
        if ( ! $this->is_valid_json( $decrypted ) ) return 0;

        $result = json_decode( $decrypted, true );
        $score = isset($result['reputation']) ? (int) $result['reputation'] : 0;
        
        set_transient( $cache_key, $score, 15 * MINUTE_IN_SECONDS );
        return $score;
    }

    public function transmit_to_nexus( array $vectors ): ?array {
        $nexus_url = $this->config->get_nexus_url();
        $api_key   = $this->config->get_api_key();
        
        if ( '' === $nexus_url || '' === $api_key ) return null;

        // Build plain JSON envelope — Go backend accepts {site, vectors, pull} directly
        $json_payload = wp_json_encode([
            'site'    => $this->config->get_node_id(),
            'vectors' => $vectors,
            'pull'    => true,
        ]);

        if ( false === $json_payload ) {
            error_log('VGT GORGON SYNC ERROR: JSON Encoding fehlgeschlagen.');
            return null;
        }

        $response = wp_remote_post( $nexus_url, [
            'body'    => $json_payload,
            'headers' => [ 
                'X-VGT-Nexus-Auth'       => $api_key,
                'X-VGT-Sentinel-Version' => defined('VIS_VERSION') ? VIS_VERSION : '7.4.0',
                'X-VGT-Sentinel-Magic'   => 'vgt-magic-noc-9938-omega-fusion',
                'Content-Type'           => 'application/json',
                'Connection'             => 'close',
            ],
            'timeout'   => 20,
            'sslverify' => true,
            'redirection' => 0,
        ]);

        if ( is_wp_error( $response ) ) {
            error_log('VGT GORGON SYNC ERROR (NET): ' . $response->get_error_message());
            return null;
        }

        if ( wp_remote_retrieve_response_code($response) !== 200 ) return null;

        $body = wp_remote_retrieve_body( $response );
        $result = json_decode( $body, true );
        return is_array($result) ? $result : null;
    }

    private function is_valid_json( string $string ): bool {
        if ( function_exists( 'json_validate' ) ) {
            return json_validate( $string );
        }
        json_decode( $string );
        return json_last_error() === JSON_ERROR_NONE;
    }
}
