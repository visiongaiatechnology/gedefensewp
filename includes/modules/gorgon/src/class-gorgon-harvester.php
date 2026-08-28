<?php
declare(strict_types=1);

namespace VGT\Sentinel\Modules\Gorgon;

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

final class Gorgon_Harvester {
    
    private Gorgon_Config $config;
    private array $table_cache = [];

    public function __construct(Gorgon_Config $config) {
        $this->config = $config;
    }

    /**
     * @return \Generator<array{data: array, ptr: array<string, int>}>
     */
    public function yield_telemetry_nodes(): \Generator {
        global $wpdb;
        $total_count = 0;

        $raw_sync_state = get_option( 'vgt_gorgon_sync_state', [] );
        $sync_state = is_array( $raw_sync_state ) ? $raw_sync_state : [];
        $nodes = $this->get_active_nodes();

        foreach ( $nodes as $node_id => $node_config ) {
            if ( $total_count >= Gorgon_Config::MAX_GLOBAL_VECTORS ) break;

            $last_id = (int) ( $sync_state[ $node_id ] ?? 0 );
            $table   = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) ($node_config['table'] ?? '') );
            
            if ( '' === $table || ! $this->table_exists( $table ) ) continue;

            $id_col = preg_replace( '/[^a-zA-Z0-9_]/', '', $node_config['id_col'] ?? 'id' );

            $query = $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE `{$id_col}` > %d ORDER BY `{$id_col}` ASC LIMIT %d",
                $last_id,
                Gorgon_Config::MAX_BATCH_PER_NODE
            );

            $results = $wpdb->get_results( $query, ARRAY_A );

            if ( ! empty( $results ) && is_array( $results ) ) {
                foreach ( $results as $row ) {
                    if ( $total_count >= Gorgon_Config::MAX_GLOBAL_VECTORS ) break 2;
                    
                    $last_id = max( $last_id, (int) $row[ $id_col ] );
                    
                    yield [
                        'data' => $this->normalize_vector( $row, $node_config, $table ),
                        'ptr'  => [ $node_id => $last_id ]
                    ];
                    
                    $total_count++;
                }
            }
        }
    }

    private function normalize_vector( array $row, array $config, string $table ): array {
        return [
            'node'    => $table,
            'ip'      => $row[ $config['ip_col'] ?? 'ip' ] ?? '0.0.0.0',
            'type'    => $row[ $config['type_col'] ?? 'type' ] ?? 'GENERIC',
            'ts'      => $row[ $config['time_col'] ?? 'timestamp' ] ?? gmdate('Y-m-d H:i:s'),
            'payload' => (string)($row[ $config['payload_col'] ?? 'message' ] ?? '') 
        ];
    }

    private function table_exists( string $table ): bool {
        global $wpdb;
        if ( isset( $this->table_cache[ $table ] ) ) {
            return $this->table_cache[ $table ];
        }
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $table
        );
        $exists = ( (int) $wpdb->get_var( $query ) === 1 );
        
        $this->table_cache[ $table ] = $exists;
        return $exists;
    }

    private function get_active_nodes(): array {
        global $wpdb;
        $defaults = [
            'core' => [
                'table' => $wpdb->prefix . 'vis_omega_logs',
                'id_col' => 'id', 'ip_col' => 'ip', 'type_col' => 'type', 'time_col' => 'timestamp', 'payload_col' => 'message'
            ],
            'oracle' => [
                'table' => $wpdb->prefix . 'vis_oracle_patterns',
                'id_col' => 'id', 'ip_col' => 'ip', 'type_col' => 'type', 'time_col' => 'timestamp', 'payload_col' => 'message'
            ]
        ];
        
        $raw_config = $this->config->get_raw_config();
        $custom_nodes = $raw_config['gorgon_nodes'] ?? [];
        return array_merge( $defaults, is_array( $custom_nodes ) ? $custom_nodes : [] );
    }
}
