<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Morpheus_Shield_DB {

    private Morpheus $core;
    private Morpheus_Tracer $tracer;
    private Morpheus_UI $ui;

    public function __construct( Morpheus $core, Morpheus_Tracer $tracer, Morpheus_UI $ui ) {
        $this->core = $core;
        $this->tracer = $tracer;
        $this->ui = $ui;
    }

    public function intercept_database_query( string $query ): string {
        if ( Morpheus::$is_internal_action ) return $query;
        if ( empty( trim( $query ) ) ) return (string) $query;

        // 1. FILE I/O RCE Check (Unabhängig vom Caller)
        if ( preg_match( '/^\s*(SELECT|SHOW|EXPLAIN|DESCRIBE|SET)\b/i', $query ) ) {
            if ( preg_match( '/\bINTO\s+(OUTFILE|DUMPFILE)\b/i', $query ) ) {
                $caller = $this->tracer->identify_caller();
                $this->ui->hard_kill( $caller, 'FILE_IO_RCE_ATTEMPT', 'SELECT INTO OUTFILE/DUMPFILE detected. Severe RCE vector blocked.' );
            }
            return $query;
        }

        $caller = $this->tracer->identify_caller();
        if ( 'core' === $caller || 'theme' === $caller ) return $query;

        // VGT PLATINUM ARCHITECTURE: Semantic Shield Layering (No Double Jeopardy)
        // Wenn der DB-Call über sichere WP Core APIs (Options, Transients, Meta) läuft, 
        // haben die semantischen Schilde (State Shield) dies bereits bewertet.
        // Der DB Shield greift ab sofort ausschließlich bei schmutzigen RAW $wpdb->query() Operationen.
        if ( $this->is_core_api_proxy() ) {
            return $query;
        }

        // 2. DDL Violation Check (Raw Modifikationen)
        if ( preg_match( '/^\s*(DROP|ALTER|TRUNCATE|CREATE|RENAME)\b/i', $query ) ) {
            $this->ui->execute_kill( $caller, 'DDL_VIOLATION', 'Runtime schema modification is strictly forbidden.' );
        }

        // 3. Evasion Check
        if ( preg_match( '/^\s*(PREPARE|EXECUTE|DEALLOCATE)\b/i', $query ) ) {
            $this->ui->execute_kill( $caller, 'DYNAMIC_SQL_EVASION', 'Prepared statement evasion attempt blocked.' );
        }

        $matrix = $this->core->get_plugin_matrix( $caller );
        global $wpdb;

        // 4. Core Protection Boundary
        $core_protected_tables = [ $wpdb->users, $wpdb->usermeta, $wpdb->options ];

        foreach ( $core_protected_tables as $protected_table ) {
            if ( empty( $protected_table ) ) continue;
            $pattern = '/(?:^|[^a-zA-Z0-9_])' . preg_quote( (string) $protected_table, '/' ) . '(?:[^a-zA-Z0-9_]|$)/i';
            if ( preg_match( $pattern, $query ) ) {
                $this->ui->execute_kill( $caller, 'DB_CROSS_BOUNDARY_VIOLATION', sprintf( 'Direct RAW query references strictly protected core table: %s', $protected_table ) );
                return $query; // Block cascade logging
            }
        }

        // 5. Custom DML Target Verification
        $target_tables = $this->extract_ast_dml_targets( $query );

        if ( empty( $target_tables ) ) {
            $this->ui->execute_kill( $caller, 'DB_AST_PARSE_FAILURE', 'Unrecognized DML syntax, Subquery obfuscation, or Hex-Evasion detected.' );
            return $query;
        }

        foreach ( $target_tables as $target_table ) {
            if ($target_table === '_passthrough') continue;

            $this->ui->log_audit_trace( $caller, 'DB_WRITE', sprintf( 'RAW DML AST-Target identified: %s', $target_table ) );

            $is_authorized = false;
            foreach ( $matrix['db_write'] as $allowed_prefix ) {
                if ( str_starts_with( $target_table, $allowed_prefix ) ) {
                    $is_authorized = true;
                    break;
                }
            }
            if ( ! $is_authorized ) {
                $this->ui->execute_kill( $caller, 'DATABASE_WRITE_VIOLATION', sprintf( 'Unauthorized RAW DML operation on unassigned table: %s', $target_table ) );
            }
        }

        return $query;
    }

    /**
     * Identifiziert, ob die Query durch eine offizielle, high-level WordPress API getriggert wurde.
     * Wenn JA -> State-Shield kümmert sich. Wenn NEIN -> Harter DB-Shield Eingriff.
     */
    private function is_core_api_proxy(): bool {
        $stack = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );
        $safe_apis = [
            'add_option', 'update_option', 'delete_option', 'get_option', 
            'add_site_option', 'update_site_option', 'delete_site_option', 'get_site_option',
            'add_metadata', 'update_metadata', 'delete_metadata', 'get_metadata', 
            'wp_insert_user', 'wp_update_user', 'wp_delete_user',
            'set_transient', 'get_transient', 'delete_transient',
            'set_site_transient', 'get_site_transient', 'delete_site_transient'
        ];

        foreach ( $stack as $frame ) {
            if ( ! isset( $frame['function'] ) ) continue;
            if ( in_array( $frame['function'], $safe_apis, true ) ) {
                return true;
            }
        }
        return false;
    }

    private function extract_ast_dml_targets( string $sql ): array {
        $sql = trim($sql);
        if ( empty($sql) ) return ['_passthrough'];
        
        $normalized = preg_replace('/`/', '', $sql);
        $targets = [];

        if ( preg_match( '/^\s*(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?(?:INTO\s+)?([a-zA-Z0-9_]+)/i', $normalized, $matches ) ) {
            $targets[] = $matches[1];
        } elseif ( preg_match( '/^\s*UPDATE\s+(?:IGNORE\s+)?([a-zA-Z0-9_]+)/i', $normalized, $matches ) ) {
            $targets[] = $matches[1];
        } elseif ( preg_match( '/^\s*DELETE\s+(?:IGNORE\s+)?FROM\s+([a-zA-Z0-9_]+)/i', $normalized, $matches ) ) {
            $targets[] = $matches[1];
        }

        if ( !empty($targets) ) {
            return array_unique($targets);
        }

        $tokens = [];
        $pattern = '/(?:' . '\'\'|' . '""|' . '\'(?:[^\'\\\\]|\\\\.)*\'|' . '"(?:[^"\\\\]|\\\\.)*"|' . '`[^`]+`|' . '\/\*.*?\*\/|' . '--[^\n]*|' . '#[^\n]*|' . '\b[a-zA-Z0-9_]+\b' . ')/s';

        if ( ! preg_match_all( $pattern, $sql, $matches ) ) return [];

        foreach ( $matches[0] as $match ) {
            $first_char = $match[0];
            if ( $first_char === '/' || $first_char === '-' || $first_char === '#' ) continue; 
            if ( $first_char === "'" || $first_char === '"' ) continue; 
            
            if ( $first_char === '`' ) {
                $tokens[] = str_replace( '`', '', $match ); 
            } else {
                $tokens[] = $match;
            }
        }

        if ( count( $tokens ) === 0 ) return [];

        $command = strtoupper( $tokens[0] );
        $skip_keywords = ['INTO', 'FROM', 'IGNORE', 'LOW_PRIORITY', 'HIGH_PRIORITY', 'DELAYED', 'QUICK', 'ONLY'];

        if ( 'INSERT' === $command || 'REPLACE' === $command ) {
            for ( $i = 1; $i < count($tokens); $i++ ) {
                $t = strtoupper( $tokens[$i] );
                if ( in_array( $t, $skip_keywords, true ) ) continue;
                $targets[] = $tokens[$i];
                break;
            }
        } elseif ( 'UPDATE' === $command ) {
            for ( $i = 1; $i < count($tokens); $i++ ) {
                $t = strtoupper( $tokens[$i] );
                if ( in_array( $t, $skip_keywords, true ) ) continue;
                if ( 'SET' === $t ) break; 
                if ( in_array( $t, ['JOIN', 'INNER', 'LEFT', 'RIGHT', 'ON', 'AS'], true ) ) continue;
                $targets[] = $tokens[$i];
            }
        } elseif ( 'DELETE' === $command ) {
            $has_from = false;
            for ( $i = 1; $i < count($tokens); $i++ ) {
                $t = strtoupper( $tokens[$i] );
                if ( in_array( $t, $skip_keywords, true ) ) {
                    if ( 'FROM' === $t ) $has_from = true;
                    continue;
                }
                if ( in_array( $t, ['WHERE', 'ORDER', 'LIMIT', 'JOIN', 'USING'], true ) ) break;
                
                $targets[] = $tokens[$i];
                if ( $has_from ) break; 
            }
        }
        
        return array_unique( $targets );
    }
}
