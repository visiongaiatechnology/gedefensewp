<?php
declare(strict_types=1);

namespace VGT\Sentinel\Modules\Gorgon;

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

final class Gorgon_Sync_Engine {
    
    private Gorgon_Config $config;
    private Gorgon_Harvester $harvester;
    private Gorgon_Uplink $uplink;
    private Vis_Gorgon $core;
    private string $lock_name;

    public function __construct(Gorgon_Config $config, Gorgon_Harvester $harvester, Gorgon_Uplink $uplink, Vis_Gorgon $core) {
        $this->config = $config;
        $this->harvester = $harvester;
        $this->uplink = $uplink;
        $this->core = $core;

        global $wpdb;
        $this->lock_name = 'vgt_sync_' . md5( $wpdb->prefix . ABSPATH );
    }

    public function run( $force = false ): void {
        if ( !$this->config->is_active() ) return;

        $force = (bool) $force;
        global $wpdb;

        $lock_acquired = (int) $wpdb->get_var( $wpdb->prepare( "SELECT GET_LOCK(%s, 0)", $this->lock_name ) );
        if ( 1 !== $lock_acquired && ! $force ) return;

        try {
            $generator = $this->harvester->yield_telemetry_nodes();
            $vectors = [];
            
            $raw_pointers = get_option( 'vgt_gorgon_sync_state', [] );
            $pointers = is_array( $raw_pointers ) ? $raw_pointers : [];
            
            foreach ( $generator as $yield_data ) {
                $vectors[] = $yield_data['data'];
                $node_id = (string)key($yield_data['ptr']);
                $pointers[$node_id] = $yield_data['ptr'][$node_id];
                
                if ( memory_get_usage() > Gorgon_Config::MEMORY_SAFE_THRESHOLD ) {
                    error_log('VGT GORGON STATE: Memory threshold reached.');
                    break;
                }
            }
            
            $has_vectors = ! empty( $vectors );
            if ( ! $has_vectors && ! $this->config->requires_learning_pull() && ! $force ) return;

            $learnings = $this->uplink->transmit_to_nexus( $vectors );

            if ( null !== $learnings ) {
                if (class_exists('\VIS_Event_Bus')) {
                    \VIS_Event_Bus::emit('GORGON', 'SYNC_OK', 'Nexus telemetry sync completed.', [
                        'vectors' => count($vectors),
                        'forced' => $force,
                    ], 2);
                }

                if ( ! empty( $learnings['banned_ips'] ) ) {
                    $this->assimilate_global_learnings( $learnings['banned_ips'] );
                }

                if ( ! empty( $learnings['nexus_matrix'] ) ) {
                    $this->persist_threat_matrix( $learnings['nexus_matrix'] );
                }
                
                if ( $has_vectors && ! empty( $pointers ) ) {
                    update_option( 'vgt_gorgon_sync_state', $pointers );
                }
            }
            
        } catch (\Throwable $t) {
            if (class_exists('\VIS_Event_Bus')) {
                \VIS_Event_Bus::emit('GORGON', 'SYNC_ERROR', 'Nexus telemetry sync failed.', [
                    'error' => $t->getMessage(),
                ], 7);
            }
            error_log( 'VGT GORGON FATAL ERROR: ' . $t->getMessage() );
        } finally {
            $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $this->lock_name ) );
        }
    }

    private function assimilate_global_learnings( array $ips ): void {
        global $wpdb;
        $ban_table = $wpdb->prefix . ( defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_apex_bans' );
        
        $ips = array_unique( array_filter( $ips, static fn($ip) => filter_var($ip, FILTER_VALIDATE_IP) ) );
        if ( empty( $ips ) ) return;

        $timestamp = gmdate( 'Y-m-d H:i:s' );
        $reason    = 'GORGON NEURAL NET: Swarm Intelligence Ban.';
        $uri       = 'GLOBAL_SYNC';

        foreach ( array_chunk( $ips, 200 ) as $chunk ) {
            $values = [];
            $placeholders = [];
            foreach ( $chunk as $ip ) {
                $values[] = $ip;
                $values[] = $reason;
                $values[] = $timestamp;
                $values[] = $uri;
                $placeholders[] = "(%s, %s, %s, %s)";
            }
            $sql = "INSERT IGNORE INTO `$ban_table` (ip, reason, banned_at, request_uri) VALUES " . implode( ', ', $placeholders );
            $wpdb->query( $wpdb->prepare( $sql, ...$values ) );
        }

        update_option( 'vgt_gorgon_last_pull', time() );
    }

    private function persist_threat_matrix( array $matrix_data ): void {
        $vault_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/vgt-vault/gorgon/' : ABSPATH . 'wp-content/vgt-vault/gorgon/';
        $matrix_file = wp_normalize_path( $vault_dir . 'nexus_matrix.json' );

        if ( ! is_dir( $vault_dir ) ) {
            if ( ! wp_mkdir_p( $vault_dir ) ) {
                error_log('VGT GORGON IO_ERROR: Matrix-Vault konnte nicht erstellt werden.');
                return;
            }
            chmod( $vault_dir, 0750 );
            
            $htaccess_payload = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder Deny,Allow\nDeny from all\n</IfModule>";
            file_put_contents( wp_normalize_path( $vault_dir . '.htaccess' ), $htaccess_payload, LOCK_EX );
            file_put_contents( wp_normalize_path( $vault_dir . 'index.php' ), "<?php\n// VGT OMEGA PROTOCOL: ACCESS DENIED.\ndie();\n", LOCK_EX );
        }

        $matrix_data['compiled_at'] = time();
        $matrix_data['node_id']     = $this->config->get_node_id();

        $json_payload = wp_json_encode( $matrix_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        
        if ( false !== $json_payload ) {
            $tmp_file = $matrix_file . '.' . bin2hex(random_bytes(8)) . '.tmp';
            $bytes_written = file_put_contents( $tmp_file, $json_payload, LOCK_EX );
            
            if ( false === $bytes_written || ! rename( $tmp_file, $matrix_file ) ) {
                 error_log('VGT GORGON IO_ERROR: Matrix persistierung fehlgeschlagen.');
                 if ( file_exists( $tmp_file ) ) @unlink( $tmp_file );
            } else {
                 chmod( $matrix_file, 0640 );
                 wp_cache_delete('vgt_nexus_matrix_map', 'vgt'); 
            }
        }

        if ( ! empty( $matrix_data['meta_regex']['all'] ) ) {
            $raw_regex = trim( (string) $matrix_data['meta_regex']['all'] );
            $safe_regex = str_replace( '#', '\#', $raw_regex );
            $singularity = '#' . $safe_regex . '#is';
            
            if ( strlen( $singularity ) > 2048 ) return;
            if ( preg_match('/(\([^)]*\+[^)]*\)\+|\([^)]*\*[^)]*\)\*|\([^)]*\{[^}]*\}[^)]*\)\+)/', $raw_regex) ) return;

            $is_valid_regex = false;
            $original_backtrack = ini_get('pcre.backtrack_limit');
            
            try {
                set_error_handler(static function($errno, $errstr) { throw new \RuntimeException($errstr); });
                ini_set('pcre.backtrack_limit', '10000');
                
                $toxic_payload = str_repeat('a', 500) . str_repeat('B', 500) . str_repeat('1', 500) . '!';
                $is_valid_regex = ( false !== preg_match($singularity, $toxic_payload) && preg_last_error() === PREG_NO_ERROR );
                
            } catch (\Throwable $t) {
                $is_valid_regex = false;
            } finally {
                ini_set('pcre.backtrack_limit', (string)$original_backtrack);
                restore_error_handler();
            }

            if ( $is_valid_regex ) {
                update_option( 'vgt_learning_regex', $singularity );
                wp_cache_set( 'vgt_learning_regex', $singularity, 'vgt', 3600 );
            }
        }
    }
}
