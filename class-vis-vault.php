<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Vault {

    private const PAYLOAD_PREFIX = 'vgt1:';
        
    private static function get_primary_key(): string {
        if (defined('VIS_VAULT_KEY')) {
            return hash('sha256', VIS_VAULT_KEY, true);
        }
        if (defined('AUTH_KEY') && AUTH_KEY !== 'put your unique phrase here') {
            return hash('sha256', AUTH_KEY . 'VGT_OMEGA_SALT_V1', true);
        }

        $material = (string) get_option('vis_vault_material', '');
        if ($material === '') {
            $material = bin2hex(random_bytes(32));
            if (!add_option('vis_vault_material', $material, '', false)) {
                $material = (string)get_option('vis_vault_material', '');
            }
            if (!preg_match('/^[a-f0-9]{64}$/', $material)) {
                throw new StorageException('Vault key material unavailable.');
            }
        }

        return hash('sha256', $material . 'VGT_OMEGA_SALT_V1', true);
    }

    private static function get_legacy_key(): string {
        return hash('sha256', 'VGT_INTERNAL_ENCRYPTION_SECRET', true);
    }

    public static function encrypt(string $plaintext): string {
        $key = self::get_primary_key();
        if (function_exists('sodium_crypto_secretbox')) {
            try {
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
                return self::PAYLOAD_PREFIX . base64_encode($nonce . $ciphertext);
            } catch (Throwable $e) {
                error_log('[VGT VAULT] Sodium encryption failed.');
            }
        }
        return self::PAYLOAD_PREFIX . self::encrypt_openssl($plaintext, $key);
    }

    public static function decrypt(string $encoded_blob): string {
        if ($encoded_blob === '') {
            throw new SecurityException('Vault token format rejected.');
        }

        $is_versioned = str_starts_with($encoded_blob, self::PAYLOAD_PREFIX);
        $payload = $is_versioned ? substr($encoded_blob, strlen(self::PAYLOAD_PREFIX)) : $encoded_blob;

        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $payload)) {
            return $encoded_blob;
        }
        
        $plaintext = self::core_decrypt($payload, self::get_primary_key());
        if ($plaintext !== false) {
            return $plaintext;
        }
        
        if (!$is_versioned) {
            $plaintext = self::core_decrypt($payload, self::get_legacy_key());
            if ($plaintext !== false) {
                return $plaintext;
            }
        }

        error_log('[VGT VAULT] Decrypt rejected invalid vault payload.');
        throw new SecurityException('Vault token authentication failed.');
    }

    private static function core_decrypt(string $encoded_blob, string $key): string|false {
        $decoded = base64_decode($encoded_blob, true);
        if ($decoded === false) return false;

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce_len = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            if (mb_strlen($decoded, '8bit') >= ($nonce_len + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
                $nonce = mb_substr($decoded, 0, $nonce_len, '8bit');
                $ciphertext = mb_substr($decoded, $nonce_len, null, '8bit');
                try {
                    $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
                    if ($plaintext !== false) return $plaintext;
                } catch (Throwable $e) {}
            }
        }
        return self::decrypt_openssl($encoded_blob, $key);
    }

    private static function encrypt_openssl(string $data, string $key): string {
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-gcm'));
        $tag = ""; 
        $ciphertext = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new StorageException('Vault cryptographic storage failed.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt_openssl(string $encoded_blob, string $key): string|false {
        $decoded = base64_decode($encoded_blob, true);
        $iv_len = openssl_cipher_iv_length('aes-256-gcm');
        
        if (strlen($decoded) < $iv_len + 16) return false; 
        
        $iv = substr($decoded, 0, $iv_len);
        $tag = substr($decoded, $iv_len, 16);
        $ciphertext = substr($decoded, $iv_len + 16);
        
        return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    }

    public static function auto_migrate_config(): void {
        $config = get_option('vis_config', []);
        if (is_array($config)) {
            $migrated = false;
            $sensitive_keys = ['gorgon_api_key'];

            foreach ($sensitive_keys as $s_key) {
                if (!empty($config[$s_key])) {
                    $payload = $config[$s_key];
                    
                    $payload_body = str_starts_with((string)$payload, self::PAYLOAD_PREFIX)
                        ? substr((string)$payload, strlen(self::PAYLOAD_PREFIX))
                        : (string)$payload;
                    if (self::core_decrypt($payload_body, self::get_primary_key()) !== false) continue;
                    
                    $plaintext = self::core_decrypt($payload_body, self::get_legacy_key());
                    if ($plaintext !== false) {
                        $config[$s_key] = self::encrypt($plaintext);
                        $migrated = true;
                        continue;
                    }

                    if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $payload) || base64_decode($payload, true) === false) {
                        $config[$s_key] = self::encrypt($payload);
                        $migrated = true;
                    }
                }
            }

            if ($migrated) {
                update_option('vis_config', $config);
            }
        }

        $aegis_key = get_option('vis_aegis_ai_key');
        if (!empty($aegis_key) && is_string($aegis_key)) {
            $aegis_payload = str_starts_with($aegis_key, self::PAYLOAD_PREFIX)
                ? substr($aegis_key, strlen(self::PAYLOAD_PREFIX))
                : $aegis_key;
            if (self::core_decrypt($aegis_payload, self::get_primary_key()) === false) {
                $plaintext = self::core_decrypt($aegis_payload, self::get_legacy_key());
                if ($plaintext !== false) {
                    update_option('vis_aegis_ai_key', self::encrypt($plaintext));
                } elseif (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $aegis_key) || base64_decode($aegis_key, true) === false) {
                    update_option('vis_aegis_ai_key', self::encrypt($aegis_key));
                }
            }
        }
    }

    public static function generate_admin_token(): string {
        $user_id = get_current_user_id();
        if (!$user_id) return '';
        
        $session_token = wp_get_session_token();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $key = self::get_primary_key();
        $token = hash_hmac('sha256', $user_id . '|' . $session_token . '|' . $ip . '|' . $ua, $key);
        
        set_transient('vgt_admin_token_' . hash('sha256', $token), [
            'user_id' => $user_id,
            'session' => hash('sha256', $session_token),
            'ip'      => $ip,
            'ua'      => $ua
        ], 2 * HOUR_IN_SECONDS);
        
        return $token;
    }

    public static function verify_admin_token(string $token): bool {
        if (empty($token) || strlen($token) !== 64) return false;
        
        if (!is_user_logged_in() || !current_user_can('manage_options')) return false;

        $data = get_transient('vgt_admin_token_' . hash('sha256', $token));
        if (!is_array($data)) return false;
        
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $current_user = get_current_user_id();
        $current_session = hash('sha256', wp_get_session_token());
        if ((int)($data['user_id'] ?? 0) !== $current_user
            || !hash_equals((string)($data['session'] ?? ''), $current_session)
            || !hash_equals((string)($data['ip'] ?? ''), $current_ip)
            || !hash_equals((string)($data['ua'] ?? ''), $current_ua)) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $current_user . '|' . wp_get_session_token() . '|' . $current_ip . '|' . $current_ua,
            self::get_primary_key()
        );
        return hash_equals($expected, $token);
    }
}
