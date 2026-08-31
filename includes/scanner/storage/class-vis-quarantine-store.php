<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Quarantine_Store {
    private string $root;
    private string $objects;
    private string $metadata;

    public function __construct() {
        $vault = defined('VIS_VAULT_DIR') ? (string)VIS_VAULT_DIR : '';
        if ($vault === '') throw new StorageException('Quarantine vault unavailable.');
        $this->root = rtrim(wp_normalize_path($vault), '/') . '/quarantine';
        $this->objects = $this->root . '/objects';
        $this->metadata = $this->root . '/metadata';
        $this->ensureDirectory($this->root);
        $this->ensureDirectory($this->objects);
        $this->ensureDirectory($this->metadata);
    }

    /** @return array{incident_id:string,object:string} */
    public function quarantine(string $source, string $sha256, VIS_Scan_Verdict $verdict): array {
        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new ValidationException('Invalid quarantine digest.');
        }
        $siteRoot = realpath(ABSPATH);
        $resolvedSource = realpath($source);
        if ($siteRoot === false || $resolvedSource === false || !is_file($resolvedSource) || is_link($source)) {
            throw new SecurityException('Quarantine source path rejected.');
        }
        $normalizedRoot = rtrim(wp_normalize_path($siteRoot), '/') . '/';
        $normalizedSource = wp_normalize_path($resolvedSource);
        if (!str_starts_with($normalizedSource, $normalizedRoot)) {
            throw new SecurityException('Quarantine source path escaped jail.');
        }

        $incidentId = bin2hex(random_bytes(16));
        $object = $this->objects . '/' . $sha256 . '-' . $incidentId . '.vgtq';
        if (!str_starts_with($object, $this->objects . '/')) {
            throw new SecurityException('Quarantine destination path escaped jail.');
        }
        if (!@rename($resolvedSource, $object)) {
            throw new StorageException('Quarantine atomic move failed.');
        }
        if (!@chmod($object, 0600)) {
            @rename($object, $resolvedSource);
            throw new StorageException('Quarantine permission enforcement failed.');
        }

        $payload = [
            'schema' => 1,
            'incident_id' => $incidentId,
            'sha256' => $sha256,
            'original_path' => substr($normalizedSource, strlen($normalizedRoot)),
            'quarantined_at' => gmdate('c'),
            'verdict' => $verdict->toArray(),
        ];
        $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payload['mac'] = hash_hmac('sha256', $canonical, wp_salt('auth'));
        $this->writeJson($this->metadata . '/' . $incidentId . '.json', $payload);
        return ['incident_id' => $incidentId, 'object' => basename($object)];
    }

    private function ensureDirectory(string $directory): void {
        if (!is_dir($directory) && !wp_mkdir_p($directory)) {
            throw new StorageException('Quarantine directory creation failed.');
        }
        if (!@chmod($directory, 0700)) {
            throw new StorageException('Quarantine directory permission enforcement failed.');
        }
    }

    private function writeJson(string $file, array $payload): void {
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = $file . '.' . bin2hex(random_bytes(16)) . '.tmp';
        if (file_put_contents($temporary, $json, LOCK_EX) === false || !@chmod($temporary, 0600) || !@rename($temporary, $file)) {
            @unlink($temporary);
            throw new StorageException('Quarantine metadata persistence failed.');
        }
    }
}
