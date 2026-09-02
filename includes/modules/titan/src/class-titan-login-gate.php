<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Login_Gate {
    private const KEY_ID = 'vis_titan_gate_signing_key';
    private const TOKEN_TTL = 300;
    private const COOKIE_TTL = 1800;

    public function enforce(): void {
        if ($this->validProbeRequest()) return;
        if ($this->validCookie()) return;
        $token = isset($_GET['vgt_door_token']) && is_string($_GET['vgt_door_token']) ? wp_unslash($_GET['vgt_door_token']) : '';
        if ($token === '') $this->reject('TITAN_LOGIN_GATE_REJECT', 85);
        try {
            $this->consumeToken($token);
            $this->setPassCookie();
            $clean = remove_query_arg('vgt_door_token', (string)($_SERVER['REQUEST_URI'] ?? '/wp-login.php'));
            wp_safe_redirect(home_url($clean));
            exit;
        } catch (ValidationException $e) {
            $this->reject('TITAN_LOGIN_GATE_INVALID', 88);
        } catch (SecurityException $e) {
            error_log('[TITAN GATE SECURITY] ' . $e->getMessage());
            $this->reject('TITAN_LOGIN_GATE_INVALID', 95);
        } catch (StorageException $e) {
            error_log('[TITAN GATE STORAGE] ' . $e->getMessage());
            $this->reject('TITAN_LOGIN_GATE_FAULT', 70);
        } catch (Throwable $e) {
            error_log('[TITAN GATE FATAL] ' . $e->getMessage());
            $this->reject('TITAN_LOGIN_GATE_FAULT', 70);
        }
    }

    public static function handleGenerateLink(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_user_logged_in() || !current_user_can('manage_options')) wp_die('Request rejected for security reasons.', '', ['response' => 403]);
        check_admin_referer('vis_titan_generate_gate_link');
        try {
            $instance = new self();
            $token = $instance->issueToken();
            $url = add_query_arg('vgt_door_token', rawurlencode($token), wp_login_url());
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
                header('Cache-Control: no-store, private');
                header('X-Content-Type-Options: nosniff');
            }
            echo $url;
            exit;
        } catch (ValidationException $e) {
            wp_die(esc_html($e->getMessage()), '', ['response' => 422]);
        } catch (SecurityException $e) {
            error_log('[TITAN GATE SECURITY] ' . $e->getMessage());
            wp_die('Request rejected for security reasons.', '', ['response' => 403]);
        } catch (StorageException $e) {
            error_log('[TITAN GATE STORAGE] ' . $e->getMessage());
            wp_die('A server error occurred.', '', ['response' => 500]);
        } catch (Throwable $e) {
            error_log('[TITAN GATE FATAL] ' . $e->getMessage());
            wp_die('Critical system fault.', '', ['response' => 500]);
        }
    }

    public function issueToken(): string {
        $payload = ['v' => 1, 'iat' => time(), 'exp' => time() + self::TOKEN_TTL, 'nonce' => bin2hex(random_bytes(16)), 'purpose' => 'wordpress-login-gate'];
        $encoded = self::base64UrlEncode(wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $mac = hash_hmac('sha256', "GEDEFENSE:TITAN:GATE:v1\0" . $encoded, $this->secret(), true);
        return $encoded . '.' . self::base64UrlEncode($mac);
    }

    /** @return array{v:int,iat:int,exp:int,nonce:string,purpose:string} */
    public function consumeToken(string $token): array {
        $payload = $this->verifyToken($token);
        $replayKey = 'vis_titan_gate_replay_' . hash('sha256', (string)$payload['nonce']);
        if (get_transient($replayKey) !== false) {
            $this->emit('TITAN_LOGIN_GATE_REPLAY', 98, 'LOGIN_GATE_REPLAY');
            throw new SecurityException('Login gate token replay detected.');
        }
        set_transient($replayKey, '1', self::TOKEN_TTL * 2);
        return $payload;
    }

    /** @return array{v:int,iat:int,exp:int,nonce:string,purpose:string} */
    private function verifyToken(string $token): array {
        if (strlen($token) > 1024 || substr_count($token, '.') !== 1) throw new ValidationException('Login gate token format invalid.');
        [$payloadEncoded, $macEncoded] = explode('.', $token, 2);
        $payloadJson = self::base64UrlDecode($payloadEncoded);
        $mac = self::base64UrlDecode($macEncoded);
        if ($payloadJson === false || $mac === false || strlen($mac) !== 32) throw new ValidationException('Login gate token encoding invalid.');
        $expected = hash_hmac('sha256', "GEDEFENSE:TITAN:GATE:v1\0" . $payloadEncoded, $this->secret(), true);
        if (!hash_equals($expected, $mac)) throw new SecurityException('Login gate token authentication failed.');
        $payload = json_decode($payloadJson, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) throw new ValidationException('Login gate payload invalid.');
        $now = time();
        if ((int)($payload['v'] ?? 0) !== 1 || !hash_equals('wordpress-login-gate', (string)($payload['purpose'] ?? '')) || (int)($payload['iat'] ?? 0) > $now + 30 || (int)($payload['exp'] ?? 0) < $now || (int)($payload['exp'] ?? 0) > $now + self::TOKEN_TTL || preg_match('/^[a-f0-9]{32}$/D', (string)($payload['nonce'] ?? '')) !== 1) {
            throw new SecurityException('Login gate token validation failed.');
        }
        return ['v' => 1, 'iat' => (int)$payload['iat'], 'exp' => (int)$payload['exp'], 'nonce' => (string)$payload['nonce'], 'purpose' => 'wordpress-login-gate'];
    }

    private function validCookie(): bool {
        $cookie = isset($_COOKIE['vgt_gate_pass']) && is_string($_COOKIE['vgt_gate_pass']) ? $_COOKIE['vgt_gate_pass'] : '';
        if ($cookie === '' || substr_count($cookie, '.') !== 1) return false;
        [$payload, $mac] = explode('.', $cookie, 2);
        if (preg_match('/^[0-9]{10}$/D', $payload) !== 1 || (int)$payload < time()) return false;
        $expected = hash_hmac('sha256', $this->clientIp() . "\0" . $payload, $this->secret());
        return strlen($mac) === 64 && hash_equals($expected, $mac);
    }

    private function validProbeRequest(): bool {
        $token = isset($_SERVER['HTTP_X_VGT_TITAN_PROBE']) ? (string)$_SERVER['HTTP_X_VGT_TITAN_PROBE'] : '';
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) return false;
        $expected = get_transient('vis_titan_probe_token_hash');
        return is_string($expected) && strlen($expected) === 64 && hash_equals($expected, hash('sha256', $token));
    }

    private function setPassCookie(): void {
        $expiration = time() + self::COOKIE_TTL;
        $payload = (string)$expiration;
        $value = $payload . '.' . hash_hmac('sha256', $this->clientIp() . "\0" . $payload, $this->secret());
        setcookie('vgt_gate_pass', $value, ['expires' => $expiration, 'path' => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/', 'domain' => defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '', 'secure' => function_exists('is_ssl') && is_ssl(), 'httponly' => true, 'samesite' => 'Strict']);
    }

    private function secret(): string {
        if (!class_exists('VIS_Key_Vault')) throw new StorageException('TITAN key vault unavailable.');
        $stored = VIS_Key_Vault::get_key(self::KEY_ID);
        if (preg_match('/^[a-f0-9]{64}$/D', $stored) !== 1) {
            $stored = bin2hex(random_bytes(32));
            VIS_Key_Vault::save_key(self::KEY_ID, $stored);
        }
        return hash('sha256', "GEDEFENSE:TITAN:GATE:KEY:v1\0" . $stored, true);
    }

    private function clientIp(): string {
        $ip = class_exists('VIS_Security') ? VIS_Security::client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private function reject(string $event, int $confidence): never {
        $this->emit($event, $confidence, $event);
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        exit('Request rejected for security reasons.');
    }

    private function emit(string $event, int $confidence, string $vector): void {
        $fabric = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
        if (!class_exists($fabric)) return;
        $fabric::ingest(['sensor' => 'TITAN', 'category' => 'AUTHENTICATION', 'event_type' => $event, 'role' => 'DETECTION', 'severity' => 9, 'confidence' => $confidence, 'score' => $confidence, 'actor_ip' => $this->clientIp(), 'entity_type' => 'ROUTE', 'entity_id' => 'route:' . hash('sha256', '/wp-login.php'), 'vector' => $vector, 'action_type' => 'BLOCK', 'outcome' => 'INTERCEPTED', 'metadata' => ['surface' => VIS_Titan_Surface_Resolver::LOGIN]]);
    }

    private static function base64UrlEncode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private static function base64UrlDecode(string $value): string|false {
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) return false;
        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
