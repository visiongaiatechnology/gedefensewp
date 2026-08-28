<?php
/**
 * VISIONGAIATECHNOLOGY OMEGA PROTOCOL
 * STATUS: DIAMANT VGT SUPREME (MATHEMATICALLY HARDENED, ZERO-TRUST)
 * MODULE: MORPHEUS - CORE ORCHESTRATOR
 * DESCRIPTION: Main Entry Point. Verwaltet State, Matrix IO & Dependency Injection.
 */

declare(strict_types=1);

namespace VGT\Sentinel\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'VGT Protocol: Direct access denied.' );
}

require_once __DIR__ . '/src/class-morpheus-hypervisor.php';
require_once __DIR__ . '/src/class-morpheus-path-jail.php';
require_once __DIR__ . '/src/class-morpheus-ai.php';
require_once __DIR__ . '/src/class-morpheus-dashboard.php';

final class Vis_Morpheus {

    private static ?self $instance = null;
    
    private array $permission_matrix = [];
    private string $matrix_file;
    public bool $enforcement_mode;

    public static bool $is_internal_action = false;

    public Morpheus_Hypervisor $hypervisor;
    public Morpheus_AI $ai;
    public Morpheus_Dashboard $dashboard;

    private function __construct() {
        $config = get_option( 'vis_config', [] );
        $this->enforcement_mode = ! empty( $config['morpheus_strict_mode'] );

        $this->matrix_file = Morpheus_Path_Jail::root_file('compiled-matrix.json');

        $this->load_compiled_matrix();

        $this->hypervisor = new Morpheus_Hypervisor( $this );
        $this->ai         = new Morpheus_AI( $this );
        $this->dashboard  = new Morpheus_Dashboard( $this );
    }

    private function __clone() {}

    public function __wakeup() {
        http_response_code( 403 );
        die( "VGT KERNEL PANIC: MEMORY_CORRUPTION_ATTEMPT. System halted." );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_compiled_matrix(): void {
        if ( ! is_readable( $this->matrix_file ) ) {
            $this->permission_matrix = [
                '_meta' => [
                    'version' => '1.0',
                    'last_updated' => gmdate('Y-m-d\TH:i:s\Z'),
                    'strict_mode' => $this->enforcement_mode
                ],
                '_default' => [
                    'network'  => [], 
                    'db_write' => [], 
                    'options'  => []  
                ]
            ];
            $this->apply_matrix( $this->permission_matrix );
            return;
        }

        $json = file_get_contents( $this->matrix_file );
        if ( $json === false ) {
            die("VGT KERNEL PANIC: IO_READ_ERROR - compiled-matrix.json nicht lesbar.");
        }
        
        $expected_hash = get_option( 'vgt_matrix_hash' );
        if (!is_string($expected_hash) || strlen($expected_hash) !== 64) {
            throw new \SecurityException('Matrix authentication token missing.');
        }
        if (!defined('AUTH_SALT') || strlen((string)AUTH_SALT) < 32) {
            throw new \SecurityException('Matrix authentication token unavailable.');
        }
        $salt = (string)AUTH_SALT;
        $actual_hash   = hash_hmac( 'sha256', $json, $salt );
        
        if (!hash_equals($expected_hash, $actual_hash)) {
            die("VGT KERNEL PANIC: MATRIX_INTEGRITY_VIOLATION - compiled-matrix.json wurde modifiziert. Lockdown aktiv.");
        }
        
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \SecurityException('Matrix validation failed.', 0, $e);
        }
        if (!is_array($data) || !self::validate_compiled_matrix($data)) {
            throw new \SecurityException('Matrix validation failed.');
        }
        
        $this->permission_matrix = $data;
    }

    public function apply_matrix( array $data ): void {
        if (!self::validate_compiled_matrix($data)) {
            throw new \SecurityException('Matrix validation failed.');
        }
        self::$is_internal_action = true; 
        try {
            $json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            if ( $json === false ) {
                 throw new \RuntimeException( "JSON Encoding failed." );
            }
            
            $tmp = Morpheus_Path_Jail::root_file(
                'compiled-matrix.' . bin2hex(random_bytes(16)) . '.tmp'
            );

            if ( file_put_contents( $tmp, $json, LOCK_EX ) === false ) {
                throw new \StorageException('Matrix staging write failed.');
            }
            @chmod($tmp, 0600);
            if ( ! rename( $tmp, $this->matrix_file ) ) {
                @unlink($tmp);
                throw new \StorageException('Matrix atomic commit failed.');
            }
            @chmod($this->matrix_file, 0600);
            
            if (!defined('AUTH_SALT') || strlen((string)AUTH_SALT) < 32) {
                throw new \SecurityException('Matrix authentication token unavailable.');
            }
            $salt = (string)AUTH_SALT;
            update_option( 'vgt_matrix_hash', hash_hmac( 'sha256', $json, $salt ) );
            
            $this->permission_matrix = $data;
        } finally {
            self::$is_internal_action = false;
        }
    }

    public function get_plugin_matrix( string $plugin_slug ): array {
        return $this->permission_matrix[ $plugin_slug ] ?? $this->permission_matrix['_default'];
    }

    public function update_plugin_matrix( string $plugin_slug, array $new_matrix ): void {
        $plugin_slug = Morpheus_Path_Jail::validate_slug($plugin_slug);
        if (!self::validate_matrix($new_matrix)) {
            throw new \SecurityException('Matrix validation failed.');
        }
        $this->permission_matrix[ $plugin_slug ] = $new_matrix;
        $this->apply_matrix( $this->permission_matrix );
    }

    public function delete_plugin_matrix( string $plugin_slug ): void {
        $plugin_slug = Morpheus_Path_Jail::validate_slug($plugin_slug);
        if ( isset( $this->permission_matrix[ $plugin_slug ] ) ) {
            unset( $this->permission_matrix[ $plugin_slug ] );
            $this->apply_matrix( $this->permission_matrix );
        }
    }

    public function get_full_matrix(): array {
        return $this->permission_matrix;
    }

    public static function validate_matrix(array $matrix): bool {
        $required_keys = ['network', 'db_write', 'options'];
        if (count($matrix) !== count($required_keys)
            || array_diff($required_keys, array_keys($matrix)) !== []) {
            return false;
        }

        foreach ($matrix as $values) {
            if (!is_array($values) || count($values) > 256) {
                return false;
            }
        }
        foreach ($matrix['network'] as $host) {
            if (!is_string($host) || strlen($host) > 253
                || filter_var($host, FILTER_VALIDATE_IP) !== false
                || preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/iD', $host) !== 1) {
                return false;
            }
        }
        foreach (['db_write', 'options'] as $key) {
            foreach ($matrix[$key] as $prefix) {
                if (!is_string($prefix) || preg_match('/^[a-z0-9_]{2,128}$/iD', $prefix) !== 1
                    || preg_match('/(?:^|_)(?:users?|usermeta|options|capabilities)(?:_|$)/i', $prefix) === 1) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function validate_compiled_matrix(array $matrix): bool {
        if (!isset($matrix['_meta'], $matrix['_default'])
            || !is_array($matrix['_meta'])
            || !is_array($matrix['_default'])
            || !self::validate_matrix($matrix['_default'])) {
            return false;
        }

        foreach ($matrix as $slug => $permissions) {
            if ($slug === '_meta' || $slug === '_default') {
                continue;
            }
            if (!is_string($slug)
                || preg_match('/^[a-z0-9][a-z0-9_-]{0,127}$/iD', $slug) !== 1
                || !is_array($permissions)
                || !self::validate_matrix($permissions)) {
                return false;
            }
        }

        return true;
    }

    public static function ai_debug(string $msg): void {
        try {
            $log_file = Morpheus_Path_Jail::root_file('ai-terminal.log');
            if (file_put_contents(
                $log_file,
                '[' . gmdate('H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $msg) . "\n",
                FILE_APPEND | LOCK_EX
            ) !== false) {
                @chmod($log_file, 0600);
            }
        } catch (\Throwable $e) {
            error_log('[MORPHEUS DEBUG STORAGE] unavailable');
        }
    }
}
