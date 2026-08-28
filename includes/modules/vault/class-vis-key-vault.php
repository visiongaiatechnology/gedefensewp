<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('ACCESS DENIED: VISIONGAIATECHNOLOGY OMEGA PROTOCOL');
}

/**
 * MODULE: VGT KEY VAULT (DIAMANT SUPREME ARCHITECTURE)
 * Status: OMEGA HARDENED V3.2 (NATIVE SENTINEL INTEGRATION)
 * Logic: AES-256-GCM Encrypted Storage for API Keys with AAD-Binding, Site-Lock, Dynamic Auto-Upgrade & Hash Registry.
 */
final class VIS_Key_Vault {

    private const ENCRYPTION_METHOD = 'aes-256-gcm';
    private const GCM_TAG_LENGTH = 16;
    private const REGISTRY_OPTION = 'vis_vault_registry';

    public function __construct() {
        if (is_admin()) {
            add_action('admin_init', [$this, 'handle_vault_actions']);
        }
    }

    /**
     * KERNEL: Legacy Master Key Generation
     * Dechiffriert Altdaten stabil über das ursprüngliche Ableitungsverfahren.
     */
    private static function get_legacy_master_key(): string {
        $auth_salt = defined('AUTH_SALT') ? AUTH_SALT : '';
        $secure_key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '';
        
        if (empty($auth_salt) || empty($secure_key)) {
            return hash('sha256', $auth_salt . $secure_key, true);
        }

        if (function_exists('hash_hkdf')) {
            return hash_hkdf('sha256', $secure_key, 32, 'vgt_omega_vault_context', $auth_salt);
        }

        return hash('sha256', $auth_salt . $secure_key, true);
    }

    /**
     * KERNEL: Supreme Master Key Generation (Maximum Entropy)
     * Akkumuliert alle verfügbaren System-Salts für die modernisierte, unbrechbare HKDF-Sicherung.
     */
    private static function get_supreme_master_key(): string {
        $salts = '';
        $keys_to_check = [
            'SECURE_AUTH_KEY', 'AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
            'SECURE_AUTH_SALT', 'AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'
        ];

        foreach ($keys_to_check as $const) {
            if (defined($const)) {
                $salts .= constant($const);
            }
        }

        // Ausweich-Schild bei nackten oder fehlenden wp-config salts
        if (empty($salts)) {
            $salts = get_option('vgt_sentinel_vault_system_salt');
            if (empty($salts)) {
                try {
                    $salts = bin2hex(random_bytes(32));
                    update_option('vgt_sentinel_vault_system_salt', $salts, false);
                } catch (\Throwable $e) {
                    $salts = hash('sha256', (string) wp_hash('vgt-sentinel-emergency-salt'));
                }
            }
        }

        return hash_hkdf('sha256', $salts, 32, 'vgt_omega_vault_context_v4', 'vgt_hkdf_sentinel_salt_binding');
    }

    /**
     * KERNEL: Site-Lock Domain Binding
     * Ermittelt die aktuelle Host-Domain zur unlösbaren AAD-Sicherung.
     */
    private static function get_site_binding(): string {
        $domain = 'vgt-sentinel-local';
        if (function_exists('home_url')) {
            $domain = parse_url(home_url(), PHP_URL_HOST) ?: home_url();
        }
        return sanitize_text_field((string)$domain);
    }

    /**
     * KERNEL: Option-Injection / Swapping-Schutz
     * Verhindert die gezielte Manipulation oder das Überschreiben sensibler WP-Systemkonfigurationen.
     */
    private static function is_protected_option(string $option): bool {
        $blocklist = [
            'siteurl', 'home', 'blogname', 'admin_email', 'users_can_register',
            'default_role', 'active_plugins', 'template', 'stylesheet', 'current_theme',
            'vis_vault_registry', 'vgt_sentinel_vault_system_salt', 'vgt_vault_registry_index'
        ];
        return in_array(strtolower(trim($option)), $blocklist, true) || str_starts_with(strtolower($option), 'wp_');
    }

    /**
     * KERNEL: Verschlüsselung (AES-256-GCM)
     * Verwendet standardmäßig die Supreme-Engine und kombiniertes Site-Domain-Binding.
     */
    public static function encrypt(string $plaintext, string $identifier): string {
        $key = self::get_supreme_master_key();
        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        if ($iv_length === false) {
            throw new \RuntimeException('VGT Vault Error: Cipher initialization failed.');
        }

        $iv = random_bytes($iv_length);
        $tag = '';
        
        // Neues AAD-Binding: Koppelt den Payload an den Option-Namen UND die aktuelle Host-Domain
        $aad = $identifier . '|' . self::get_site_binding();

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ENCRYPTION_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::GCM_TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('VGT Vault Error: Encryption process failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * KERNEL: Entschlüsselung (AES-256-GCM) mit automatischer Upgrade-Pipeline
     */
    public static function decrypt(string $payload, string $identifier): string {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new \RuntimeException("VGT Vault Error: Base64 decode failed for [{$identifier}].");
        }

        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        if ($iv_length === false || strlen($decoded) < $iv_length + self::GCM_TAG_LENGTH) {
            throw new \RuntimeException("VGT Vault Error: Payload corrupted [{$identifier}].");
        }

        $iv = substr($decoded, 0, $iv_length);
        $tag = substr($decoded, $iv_length, self::GCM_TAG_LENGTH);
        $ciphertext = substr($decoded, $iv_length + self::GCM_TAG_LENGTH);

        // STUFE 1: Versuche Supreme Key und Domain-locked AAD-Verbindung (Aktueller Standard)
        $supreme_key = self::get_supreme_master_key();
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ENCRYPTION_METHOD,
            $supreme_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $identifier . '|' . self::get_site_binding()
        );

        if ($plaintext !== false) {
            return $plaintext;
        }

        // STUFE 2: Versuche Supreme Key ohne Domain-Locking (Nur Option-Identifier AAD)
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ENCRYPTION_METHOD,
            $supreme_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $identifier
        );

        if ($plaintext !== false) {
            // AUTO-UPGRADE: Schlüssel nahtlos auf Domain-Binding-Sicherheitsniveau heben
            try {
                self::save_key($identifier, $plaintext);
            } catch (\Throwable $e) {
                error_log('[VGT_VAULT_UPGRADE] Auto-Upgrade auf Domain-Binding fehlgeschlagen: ' . $e->getMessage());
            }
            return $plaintext;
        }

        // STUFE 3: Versuche Legacy Key und alten GCM-Kontext
        $legacy_key = self::get_legacy_master_key();
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ENCRYPTION_METHOD,
            $legacy_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $identifier
        );

        if ($plaintext !== false) {
            // AUTO-UPGRADE: Altschlüssel nahtlos auf Supreme Key & Domain-Locking migrieren
            try {
                self::save_key($identifier, $plaintext);
            } catch (\Throwable $e) {
                error_log('[VGT_VAULT_UPGRADE] Auto-Upgrade auf Supreme Master Key fehlgeschlagen: ' . $e->getMessage());
            }
            return $plaintext;
        }

        throw new \RuntimeException("VGT Vault Error: Decryption/Authentication failed for [{$identifier}]. Signatur verletzt oder Payload kompromittiert.");
    }

    /**
     * API: Extrahiert und entschlüsselt einen Key in O(1).
     * Führt bei veralteter Verschlüsselung unbemerkt im Hintergrund ein sicheres Upgrade durch.
     */
    public static function get_key(string $identifier): string {
        $clean_identifier = preg_replace('/[^a-zA-Z0-9_\-]/', '', $identifier);
        if (empty($clean_identifier)) {
            throw new \RuntimeException("VGT Vault Error: Ungültiges Identifier-Format.");
        }

        $payload = get_option($clean_identifier);
        if ($payload === false) {
            throw new \RuntimeException("VGT Vault Error: Key [{$clean_identifier}] existiert nicht in der Matrix.");
        }

        return self::decrypt((string) $payload, $clean_identifier);
    }

    /**
     * API: Registriert einen neuen Key in der Vault.
     */
    public static function save_key(string $identifier, string $plaintext): void {
        $clean_identifier = preg_replace('/[^a-zA-Z0-9_\-]/', '', $identifier);
        
        if (empty($clean_identifier) || self::is_protected_option($clean_identifier)) {
            throw new \RuntimeException("VGT Vault Error: Systemrelevanter oder ungültiger Name [{$identifier}] blockiert.");
        }

        $encrypted = self::encrypt($plaintext, $clean_identifier);
        update_option($clean_identifier, $encrypted, false);
        
        // O(1) Registrierung über assoziative Hash-Map
        $registry = get_option(self::REGISTRY_OPTION, []);
        if (!is_array($registry)) {
            $registry = [];
        }

        // Falls noch das alte Listenformat existiert, on-the-fly konvertieren
        if (isset($registry[0])) {
            $temp = [];
            foreach ($registry as $v) {
                if (is_string($v)) {
                    $temp[$v] = true;
                }
            }
            $registry = $temp;
        }

        if (!isset($registry[$clean_identifier])) {
            $registry[$clean_identifier] = true;
            update_option(self::REGISTRY_OPTION, $registry, false);
        }
    }

    /**
     * API: Löscht einen Key irreversibel.
     */
    public static function delete_key(string $identifier): void {
        $clean_identifier = preg_replace('/[^a-zA-Z0-9_\-]/', '', $identifier);
        if (empty($clean_identifier)) {
            return;
        }

        delete_option($clean_identifier);
        
        $registry = get_option(self::REGISTRY_OPTION, []);
        if (is_array($registry)) {
            if (isset($registry[0])) {
                $registry = array_fill_keys($registry, true);
            }
            if (isset($registry[$clean_identifier])) {
                unset($registry[$clean_identifier]);
                update_option(self::REGISTRY_OPTION, $registry, false);
            }
        }
    }

    /**
     * Liefert alle registrierten Keys für das Dashboard.
     * Repariert ungültige oder tote Verweise vollautomatisch im Hintergrund.
     */
    public static function get_registry(): array {
        $registry = get_option(self::REGISTRY_OPTION, []);
        if (!is_array($registry)) {
            return [];
        }
        
        // Auto-Migration von linearem O(n) Array zu O(1) Hash Map
        if (isset($registry[0])) {
            $migrated = [];
            foreach ($registry as $val) {
                if (is_string($val)) {
                    $migrated[$val] = true;
                }
            }
            update_option(self::REGISTRY_OPTION, $migrated, false);
            $registry = $migrated;
        }
        
        $healed_registry = [];
        $requires_heal = false;
        
        foreach ($registry as $identifier => $exists) {
            if (is_string($identifier) && get_option($identifier) !== false) {
                $healed_registry[$identifier] = true;
            } else {
                $requires_heal = true;
            }
        }
        
        if ($requires_heal) {
            update_option(self::REGISTRY_OPTION, $healed_registry, false);
        }
        
        return array_keys($healed_registry);
    }

    /**
     * EVENT LISTENER: Dashboard Form Actions (Schnittstellensicherung)
     */
    public function handle_vault_actions(): void {
        if (!is_admin() || !current_user_can('manage_options') || empty($_POST['action'])) {
            return;
        }

        try {
            if ($_POST['action'] === 'vis_vault_save') {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'vis_vault_save_action')) {
                    throw new \SecurityException('Kritische CSRF-Sicherheitsverletzung blockiert.');
                }

                $identifier = preg_replace('/[^a-zA-Z0-9_\-]/', '', isset($_POST['key_identifier']) ? (string)$_POST['key_identifier'] : '');
                $value = isset($_POST['key_value']) ? trim((string)wp_unslash($_POST['key_value'])) : '';
                
                if (empty($identifier) || empty($value)) {
                    throw new \Exception('VGT Vault: Identifier oder Value fehlt.');
                }
                
                self::save_key($identifier, $value);
                
                wp_safe_redirect(add_query_arg(['page' => 'vgt-suite', 'tab' => 'vault', 'vault-status' => 'secured'], admin_url('admin.php')));
                exit;
            }

            if ($_POST['action'] === 'vis_vault_delete') {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'vis_vault_delete_action')) {
                    throw new \SecurityException('Kritische CSRF-Sicherheitsverletzung blockiert.');
                }

                $identifier = preg_replace('/[^a-zA-Z0-9_\-]/', '', isset($_POST['key_identifier']) ? (string)$_POST['key_identifier'] : '');
                if (!empty($identifier)) {
                    self::delete_key($identifier);
                }
                
                wp_safe_redirect(add_query_arg(['page' => 'vgt-suite', 'tab' => 'vault', 'vault-status' => 'terminated'], admin_url('admin.php')));
                exit;
            }
        } catch (\Throwable $e) {
            error_log('VIS VAULT ACTION SECURITY ERROR: ' . $e->getMessage());
            wp_die(
                esc_html__('Anfrage aus Sicherheitsgründen blockiert: ', 'vgt-key-vault') . esc_html($e->getMessage()),
                esc_html__('Vault Access Denied', 'vgt-key-vault'),
                ['response' => 403]
            );
        }
    }
}
