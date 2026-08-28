<?php
declare(strict_types=1);

namespace VGT\Sentinel\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Morpheus_Shield_State {

    private Vis_Morpheus $core;
    private Morpheus_Tracer $tracer;
    private Morpheus_UI $ui;
    private array $critical_options_map = [];

    public function __construct( Vis_Morpheus $core, Morpheus_Tracer $tracer, Morpheus_UI $ui ) {
        $this->core = $core;
        $this->tracer = $tracer;
        $this->ui = $ui;
        $this->build_critical_options_map();

        // VGT PLATINUM: Hooke delete_option um alle State-Mutationen zu blocken
        add_filter( 'pre_delete_option', [ $this, 'intercept_option_delete' ], 9999, 2 );
    }

    private function build_critical_options_map(): void {
        $criticals = [
            'siteurl', 'home', 'users_can_register', 'default_role', 
            'active_plugins', 'core_updater.lock', 'wp_user_roles', 
            'admin_email', 'permalink_structure', 'vgt_matrix_hash'
        ];
        foreach ( $criticals as $opt ) {
            $this->critical_options_map[ $opt ] = true;
        }
    }

    public function intercept_option_update( mixed $value, string $option, mixed $old_value ): mixed {
        if ( Vis_Morpheus::$is_internal_action ) return $value; 
        $this->enforce_option_policy( $option, 'UPDATE' );
        return $value;
    }

    public function intercept_option_add( mixed $value, string $option, mixed $old_value = '' ): mixed {
         if ( Vis_Morpheus::$is_internal_action ) return $value; 
         $this->enforce_option_policy( $option, 'ADD' );
         return $value;
    }

    public function intercept_option_delete( mixed $delete, string $option ): mixed {
        if ( Vis_Morpheus::$is_internal_action ) return $delete; 
        $this->enforce_option_policy( $option, 'DELETE' );
        return $delete;
    }

    private function enforce_option_policy( string $option, string $action_type ): void {
        $caller = $this->tracer->identify_caller();
        if ( 'core' === $caller || 'theme' === $caller ) return;

        $this->ui->log_audit_trace( $caller, "OPTION_{$action_type}", sprintf( 'State mutation detected on option: %s', $option ) );

        if ( isset( $this->critical_options_map[ $option ] ) ) {
             $this->ui->execute_kill( $caller, 'STATE_HIJACK_ATTEMPT', sprintf( 'Direct %s attempt on immutable core option: %s', $action_type, $option ) );
             return;
        }

        $safe_slug = str_replace('-', '_', $caller);
        if ( str_starts_with( $option, $safe_slug ) || str_starts_with( $option, '_transient_' ) || str_starts_with( $option, '_site_transient_' ) ) {
            return;
        }

        $matrix = $this->core->get_plugin_matrix( $caller );
        
        $is_authorized = false;
        if ( ! empty( $matrix['options'] ) ) {
            foreach ( $matrix['options'] as $allowed_prefix ) {
                if ( str_starts_with( $option, $allowed_prefix ) ) {
                    $is_authorized = true;
                    break;
                }
            }
        }

        if ( ! $is_authorized ) {
             $this->ui->execute_kill( $caller, 'STATE_VIOLATION_NAMESPACE', sprintf( 'Cross-namespace memory write blocked (%s): %s', $action_type, $option ) );
        }
    }
}
