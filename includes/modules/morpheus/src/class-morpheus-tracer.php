<?php
declare(strict_types=1);

namespace VGT\Sentinel\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Morpheus_Tracer {

    private array $plugin_path_cache = [];
    private string $cached_plugin_dir = '';
    private ?string $cached_wpmu_dir = null;
    private string $cached_theme_dir = '';

    public function __construct() {
        $this->build_path_cache();
    }

    private function build_path_cache(): void {
        if ( ! function_exists( 'wp_normalize_path' ) ) return;

        $plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
        $this->cached_plugin_dir = strtolower( wp_normalize_path( realpath( $plugin_dir ) ?: $plugin_dir ) );

        if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
            $wpmu_dir = wp_normalize_path( WPMU_PLUGIN_DIR );
            $this->cached_wpmu_dir = strtolower( wp_normalize_path( realpath( $wpmu_dir ) ?: $wpmu_dir ) );
        }

        $theme_dir = wp_normalize_path( get_theme_root() );
        $this->cached_theme_dir = strtolower( wp_normalize_path( realpath( $theme_dir ) ?: $theme_dir ) );

        $plugins = (array) get_option( 'active_plugins', [] );

        foreach ( $plugins as $plugin_file ) {
            $slug = dirname( (string) $plugin_file );
            if ( '.' !== $slug && '/' !== $slug ) {
                $absolute_path = $this->cached_plugin_dir . '/' . strtolower( $slug );
                $this->plugin_path_cache[ $slug ] = wp_normalize_path( $absolute_path );
            }
        }

        if ( $this->cached_wpmu_dir && is_dir( $this->cached_wpmu_dir ) ) {
            $wpmu_files = glob( $this->cached_wpmu_dir . '/*.php' ) ?: [];
            foreach ($wpmu_files as $wpmu_file) {
                $slug = basename( $wpmu_file, '.php' );
                $this->plugin_path_cache[ 'wpmu_' . $slug ] = strtolower( wp_normalize_path( $wpmu_file ) );
            }
            
            $wpmu_dirs = glob( $this->cached_wpmu_dir . '/*', GLOB_ONLYDIR ) ?: [];
            foreach ($wpmu_dirs as $wpmu_subdir) {
                $slug = basename( $wpmu_subdir );
                $this->plugin_path_cache[ 'wpmu_' . $slug ] = strtolower( wp_normalize_path( $wpmu_subdir ) );
            }
        }
    }

    public function identify_caller(): string {
        $stack = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 ); 
        
        // Den Pfad des Sentinel Plugins selbst ermitteln, um es zu ignorieren
        $vis_path = defined('VIS_PATH') ? strtolower( wp_normalize_path( VIS_PATH ) ) : '';

        foreach ( $stack as $frame ) {
            if ( ! isset( $frame['file'] ) ) {
                continue;
            }

            $file = strtolower( wp_normalize_path( $frame['file'] ) );

            // 1. Eigene Ausführung ignorieren (VGT Sentinel / Morpheus selbst)
            if ( ! empty( $vis_path ) && str_starts_with( $file, $vis_path ) ) {
                continue;
            }
            if ( str_contains( $file, 'vgt-sentinel-bridge.php' ) ) {
                continue;
            }

            // 2. WordPress Core Hooks & Proxy-Dateien ignorieren
            if ( str_contains( $file, '/wp-includes/class-wp-hook.php' ) || 
                 str_contains( $file, '/wp-includes/plugin.php' ) || 
                 str_contains( $file, '/wp-includes/wp-db.php' ) || 
                 str_contains( $file, '/wp-includes/option.php' ) ||
                 str_contains( $file, '/wp-includes/functions.php' ) ) {
                continue;
            }

            // 3. PLUGIN CHECK
            if ( str_starts_with( $file, $this->cached_plugin_dir ) ) {
                foreach ( $this->plugin_path_cache as $slug => $path ) {
                    if ( str_starts_with( $file, $path ) ) return $slug;
                }
                
                $relative = trim( str_replace( $this->cached_plugin_dir, '', $file ), '/' );
                $folder = explode( '/', $relative )[0];
                if ( ! empty( $folder ) && ! str_ends_with( $folder, '.php' ) ) {
                    return $folder;
                }
            }

            // 4. MU-PLUGIN CHECK
            if ( $this->cached_wpmu_dir && str_starts_with( $file, $this->cached_wpmu_dir ) ) {
                foreach ( $this->plugin_path_cache as $slug => $path ) {
                    if ( str_starts_with( $file, $path ) ) return $slug;
                }
                
                $relative = trim( str_replace( $this->cached_wpmu_dir, '', $file ), '/' );
                $folder = explode( '/', $relative )[0];
                $name = str_replace( '.php', '', $folder );
                return 'wpmu_' . $name;
            }

            // 5. THEME CHECK
            if ( str_starts_with( $file, $this->cached_theme_dir ) ) {
                return 'theme';
            }
        }

        return 'core'; 
    }
}
