<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

require_once __DIR__ . '/class-zeus-vault-resolver.php';

final class Zeus_Admission {

    public const COOKIE_NAME = 'vgt_zeus_adm';
    public const DEFAULT_TOKEN_TTL = 300; // 5 minutes

    /**
     * Resolves the cryptographic master key from constants, master key file, or vault.
     * ZERO fallback hardcoded strings — FAILS CLOSED if key material is unavailable.
     */
    public static function getMasterSecret(string $vaultDir): ?string {
        if (defined('VGT_MASTER_KEY') && is_string(VGT_MASTER_KEY) && VGT_MASTER_KEY !== '') {
            return VGT_MASTER_KEY;
        }

        $keyFile = $vaultDir . 'vgt-master.php';
        if (is_file($keyFile) && is_readable($keyFile)) {
            @include_once $keyFile;
            if (defined('VGT_MASTER_KEY') && is_string(VGT_MASTER_KEY) && VGT_MASTER_KEY !== '') {
                return VGT_MASTER_KEY;
            }
        }

        if (class_exists('\VIS_Key_Vault')) {
            try {
                $vKey = \VIS_Key_Vault::get_key('vgt_zeus_master_key');
                if (is_string($vKey) && $vKey !== '') {
                    return $vKey;
                }
            } catch (\Throwable) {
                // Ignore
            }
        }

        return null;
    }

    /**
     * Generates a tamper-proof Admission Token for high-security surfaces.
     * Type 'entry' is single-use with replay protection.
     * Type 'session' is multi-use for admitted client sessions until expiry.
     */
    public static function generateToken(string $surface, int $ttl = self::DEFAULT_TOKEN_TTL, string $purpose = 'login', string $vaultDir = '', string $type = 'entry'): string {
        if ($vaultDir === '') {
            $vaultDir = Zeus_Vault_Resolver::getVaultDir();
        }

        $key = self::getMasterSecret($vaultDir);
        if ($key === null) {
            throw new \RuntimeException('VGT_ZEUS_KEY_UNAVAILABLE: Cannot generate admission token without master secret.');
        }

        $now = time();
        $payload = [
            'v' => 1,
            't' => $type, // 'entry' or 'session'
            's' => $surface,
            'iat' => $now,
            'exp' => $now + $ttl,
            'nonce' => bin2hex(random_bytes(8)),
            'p' => $purpose
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $b64 = self::base64UrlEncode($jsonPayload);
        $sig = hash_hmac('sha256', 'vgt1.' . $b64, $key);

        return 'vgt1.' . $b64 . '.' . $sig;
    }

    /**
     * Mints a session capability token from a validated entry token.
     */
    public static function mintSessionToken(array $entryPayload, string $vaultDir = ''): string {
        $surface = (string)($entryPayload['s'] ?? 'all');
        $purpose = (string)($entryPayload['p'] ?? 'login');
        $remainingTtl = max(60, (int)(($entryPayload['exp'] ?? 0) - time()));
        return self::generateToken($surface, $remainingTtl, $purpose, $vaultDir, 'session');
    }

    /**
     * Validates an admission token including HMAC signature, expiration, surface, purpose, and replay prevention.
     */
    public static function validateToken(string $token, string $expectedSurface, string $vaultDir, ?string $expectedPurpose = null): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== 'vgt1') {
            return ['valid' => false, 'reason' => 'TOKEN_FORMAT_INVALID', 'payload' => null];
        }

        $b64Payload = $parts[1];
        $providedSig = $parts[2];

        $key = self::getMasterSecret($vaultDir);
        if ($key === null) {
            return ['valid' => false, 'reason' => 'KEY_MATERIAL_UNAVAILABLE', 'payload' => null];
        }

        $expectedSig = hash_hmac('sha256', 'vgt1.' . $b64Payload, $key);

        if (!hash_equals($expectedSig, $providedSig)) {
            return ['valid' => false, 'reason' => 'TOKEN_SIGNATURE_MISMATCH', 'payload' => null];
        }

        $json = self::base64UrlDecode($b64Payload);
        if ($json === null) {
            return ['valid' => false, 'reason' => 'TOKEN_PAYLOAD_CORRUPT', 'payload' => null];
        }

        try {
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['valid' => false, 'reason' => 'TOKEN_JSON_INVALID', 'payload' => null];
        }

        if (!is_array($payload)) {
            return ['valid' => false, 'reason' => 'TOKEN_PAYLOAD_INVALID', 'payload' => null];
        }

        // Version check
        if (($payload['v'] ?? 0) !== 1) {
            return ['valid' => false, 'reason' => 'TOKEN_VERSION_UNSUPPORTED', 'payload' => $payload];
        }

        // Issued-at sanity (allow max 60s future clock skew)
        $now = time();
        $iat = (int)($payload['iat'] ?? 0);
        if ($iat > $now + 60) {
            return ['valid' => false, 'reason' => 'TOKEN_FUTURE_TIMESTAMP', 'payload' => $payload];
        }

        // Expiration check
        if (($payload['exp'] ?? 0) < $now) {
            return ['valid' => false, 'reason' => 'TOKEN_EXPIRED', 'payload' => $payload];
        }

        // Surface check
        if (($payload['s'] ?? '') !== $expectedSurface && ($payload['s'] ?? '') !== 'all') {
            return ['valid' => false, 'reason' => 'TOKEN_SURFACE_MISMATCH', 'payload' => $payload];
        }

        // Purpose check (if specified)
        if ($expectedPurpose !== null && ($payload['p'] ?? '') !== $expectedPurpose && ($payload['p'] ?? '') !== 'all') {
            return ['valid' => false, 'reason' => 'TOKEN_PURPOSE_MISMATCH', 'payload' => $payload];
        }

        // Replay Protection (Single-use tracking for 'entry' tokens)
        $type = (string)($payload['t'] ?? 'entry');
        $nonce = (string)($payload['nonce'] ?? '');
        if ($type === 'entry') {
            if ($nonce === '') {
                return ['valid' => false, 'reason' => 'ENTRY_TOKEN_NONCE_MISSING', 'payload' => $payload];
            }
            $nonceKey = 'vgt_adm_nonce_' . md5($nonce);
            if (self::isNonceUsed($nonceKey, $vaultDir)) {
                return ['valid' => false, 'reason' => 'TOKEN_REPLAY_DETECTED', 'payload' => $payload];
            }
            self::markNonceUsed($nonceKey, max(60, (int)($payload['exp'] - $now)), $vaultDir);
        }

        return ['valid' => true, 'reason' => null, 'payload' => $payload];
    }

    private static function isNonceUsed(string $nonceKey, string $vaultDir): bool {
        if (function_exists('apcu_fetch')) {
            $success = false;
            $val = apcu_fetch($nonceKey, $success);
            if ($success && $val === 1) return true;
        }

        $lockFile = Zeus_Vault_Resolver::getCacheDir() . $nonceKey . '.lock';
        if (file_exists($lockFile)) {
            $mtime = @filemtime($lockFile);
            if ($mtime !== false && (time() - $mtime) < self::DEFAULT_TOKEN_TTL) {
                return true;
            }
            @unlink($lockFile);
        }
        return false;
    }

    private static function markNonceUsed(string $nonceKey, int $ttl, string $vaultDir): void {
        if (function_exists('apcu_store')) {
            @apcu_store($nonceKey, 1, $ttl);
        }

        $lockFile = Zeus_Vault_Resolver::getCacheDir() . $nonceKey . '.lock';
        @file_put_contents($lockFile, (string)time(), LOCK_EX);
        @chmod($lockFile, 0600);
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): ?string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : null;
    }
}
