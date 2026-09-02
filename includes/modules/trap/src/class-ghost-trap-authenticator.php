<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Ghost_Trap_Authenticator {
    public const MANIFEST_KEY = 'vis_ghost_trap_manifest_v2';
    public const LEGACY_PAYLOAD_HASH = '63e4993d6eb890ceb1177374dd2052748fd1401d5f1ed95cc52a39239c64cff2';
    private const SECRET_OPTION = 'vis_ghost_trap_signing_material';
    private const MAX_PAYLOAD_BYTES = 16384;
    private const HEADER_PATTERN = '~^<\?php\n/\*\* VGT-GHOST-TRAP v2 id=([a-f0-9]{32}) body=([a-f0-9]{64}) mac=([a-f0-9]{64}) \*/\n~D';

    /** @return array{payload:string,record:array{id:string,body_hash:string,mac:string,file_hash:string}} */
    public function issue(string $filename, string $body): array {
        $filename = $this->validateFilename($filename);
        $id = bin2hex(random_bytes(16));
        $bodyHash = hash('sha256', $body);
        $mac = hash_hmac('sha256', $this->message($filename, $id, $bodyHash), $this->secret());
        $payload = "<?php\n/** VGT-GHOST-TRAP v2 id={$id} body={$bodyHash} mac={$mac} */\n" . $body;

        return [
            'payload' => $payload,
            'record' => [
                'id' => $id,
                'body_hash' => $bodyHash,
                'mac' => $mac,
                'file_hash' => hash('sha256', $payload),
            ],
        ];
    }

    public function isRegisteredTrap(string $absolutePath): bool {
        $resolvedRoot = realpath(ABSPATH);
        $resolvedPath = realpath($absolutePath);
        if ($resolvedRoot === false || $resolvedPath === false || !is_file($resolvedPath) || is_link($absolutePath)) return false;

        $root = rtrim(wp_normalize_path($resolvedRoot), '/') . '/';
        $path = wp_normalize_path($resolvedPath);
        if (!str_starts_with($path, $root)) return false;
        $filename = substr($path, strlen($root));
        if ($filename === '' || str_contains($filename, '/') || basename($filename) !== $filename) return false;

        $manifest = $this->manifest();
        $record = $manifest[$filename] ?? null;
        if (!is_array($record)) return false;

        $size = filesize($resolvedPath);
        if ($size === false || $size === 0 || $size > self::MAX_PAYLOAD_BYTES) return false;
        $payload = file_get_contents($resolvedPath);
        if (!is_string($payload) || preg_match(self::HEADER_PATTERN, $payload, $matches) !== 1) return false;

        $headerLength = strlen($matches[0]);
        $bodyHash = hash('sha256', substr($payload, $headerLength));
        $fileHash = hash('sha256', $payload);
        $expectedMac = hash_hmac('sha256', $this->message($filename, $matches[1], $bodyHash), $this->secret());

        return $this->equalsRecordValue($record, 'id', $matches[1], 32)
            && $this->equalsRecordValue($record, 'body_hash', $bodyHash, 64)
            && $this->equalsRecordValue($record, 'mac', $matches[3], 64)
            && $this->equalsRecordValue($record, 'file_hash', $fileHash, 64)
            && hash_equals($matches[2], $bodyHash)
            && hash_equals($matches[3], $expectedMac);
    }

    /** @return array<string, true> */
    public function registeredFileHashes(): array {
        $hashes = [self::LEGACY_PAYLOAD_HASH => true];
        foreach ($this->manifest() as $record) {
            $hash = is_array($record) ? (string)($record['file_hash'] ?? '') : '';
            if (preg_match('/^[a-f0-9]{64}$/D', $hash) === 1) $hashes[$hash] = true;
        }
        return $hashes;
    }

    /** @return array<string, array<string, string>> */
    public function manifest(): array {
        $raw = get_option(self::MANIFEST_KEY, []);
        if (!is_array($raw)) return [];
        $manifest = [];
        foreach ($raw as $filename => $record) {
            if (!is_string($filename) || !is_array($record)) continue;
            if (basename($filename) !== $filename || preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $filename) !== 1) continue;
            $manifest[$filename] = array_map('strval', $record);
        }
        return $manifest;
    }

    private function validateFilename(string $filename): string {
        if (basename($filename) !== $filename || preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $filename) !== 1) {
            throw new SecurityException('Ghost trap path validation failed.');
        }
        return $filename;
    }

    private function equalsRecordValue(array $record, string $key, string $actual, int $length): bool {
        $expected = (string)($record[$key] ?? '');
        return strlen($expected) === $length && hash_equals($expected, $actual);
    }

    private function message(string $filename, string $id, string $bodyHash): string {
        return "v2\0{$filename}\0{$id}\0{$bodyHash}";
    }

    private function secret(): string {
        $material = [];
        foreach (['VIS_VAULT_KEY', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY'] as $constant) {
            if (!defined($constant)) continue;
            $value = (string)constant($constant);
            if ($value !== '' && !str_contains($value, 'put your unique phrase here')) $material[] = $value;
        }
        if ($material === []) {
            $stored = get_option(self::SECRET_OPTION, '');
            if (!is_string($stored) || preg_match('/^[a-f0-9]{64}$/D', $stored) !== 1) {
                $stored = bin2hex(random_bytes(32));
                if (!add_option(self::SECRET_OPTION, $stored, '', false)) {
                    $persisted = get_option(self::SECRET_OPTION, '');
                    if (!is_string($persisted) || preg_match('/^[a-f0-9]{64}$/D', $persisted) !== 1) {
                        throw new StorageException('Ghost trap signing material persistence failed.');
                    }
                    $stored = $persisted;
                }
            }
            $material[] = $stored;
        }
        return hash('sha256', implode("\0", $material) . "\0VGT_GHOST_TRAP_V2", true);
    }
}
