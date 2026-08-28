<?php
declare(strict_types=1);

namespace VGT\Sentinel\Modules\Gorgon;

if (!defined('ABSPATH')) exit('VGT Protocol: Direct access denied.');

final class Gorgon_Config {
    
    public const MAX_BATCH_PER_NODE = 500;
    public const MAX_GLOBAL_VECTORS = 2000;
    public const MEMORY_SAFE_THRESHOLD = 94371840; // 100MB - 10MB Limit
    public const SYNC_COOLDOWN = 21600; // 6 Hours in seconds

    private array $config;
    private bool $vault_active;
    private string $node_id;
    private Vis_Gorgon $core;

    public function __construct(Vis_Gorgon $core) {
        $this->core = $core;
        
        $raw_config = get_option( 'vis_config', [] );
        $this->config = is_array( $raw_config ) ? $raw_config : [];
        $this->vault_active = class_exists( '\VIS_Vault' );

        // Fallback salt logic ensures Node ID persists perfectly
        $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'vgt_omega_fallback_salt' );
        $this->node_id = hash_hmac( 'sha384', get_site_url(), $salt );
    }

    public function is_active(): bool {
        return !empty($this->config['gorgon_enabled']);
    }

    public function get_api_key(): string {
        $api_key = trim($this->config['gorgon_api_key'] ?? '');
        if ( '' !== $api_key && class_exists( '\VIS_Vault' ) ) {
            return trim( \VIS_Vault::decrypt( $api_key ) );
        }
        return $api_key;
    }

    public function get_nexus_url(): string {
        try {
            return \VIS_Security::validate_public_http_url((string)($this->config['gorgon_nexus_url'] ?? ''), true);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function get_preemptive_url(): string {
        try {
            return \VIS_Security::validate_public_http_url((string)($this->config['gorgon_nexus_preemptive_url'] ?? ''), true);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function get_node_id(): string {
        return $this->node_id;
    }

    public function get_raw_config(): array {
        return $this->config;
    }

    public function encrypt_payload( string $payload ): string {
        if ( ! $this->vault_active ) {
            $this->core->execute_kill( 'GORGON_CRYPTO', 'MISSING_VAULT', 'Transmission blockiert.' );
        }
        return \VIS_Vault::encrypt( $payload );
    }

    public function decrypt_payload( string $cipher ): string {
        if ( ! $this->vault_active ) {
            $this->core->execute_kill( 'GORGON_CRYPTO', 'MISSING_VAULT', 'Datenverarbeitung blockiert.' );
        }
        return \VIS_Vault::decrypt( $cipher );
    }

    public function requires_learning_pull(): bool {
        return ( time() - (int)get_option( 'vgt_gorgon_last_pull', 0 ) ) > self::SYNC_COOLDOWN;
    }
}
