<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Airlock_Config {

    private array $config;

    // Fallback Immutable Baseline
    private const BASE_ALLOWED = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip'
    ];

    public function __construct() {
        $this->config = get_option('vis_config', []);
    }

    public function is_active(): bool {
        return !isset($this->config['airlock_enabled']) || !empty($this->config['airlock_enabled']);
    }

    public function should_obfuscate(): bool {
        return !isset($this->config['airlock_obfuscate']) || !empty($this->config['airlock_obfuscate']);
    }

    public function get_max_size_bytes(): int {
        $mb = isset($this->config['airlock_max_mb']) ? (int)$this->config['airlock_max_mb'] : 5;
        if ($mb <= 0) $mb = 5;
        return $mb * 1048576; // Convert MB to Bytes
    }

    public function get_allowed_map(): array {
        if (empty($this->config['airlock_extensions'])) {
            return self::BASE_ALLOWED;
        }

        $raw_exts = explode(',', $this->config['airlock_extensions']);
        $map = [];
        
        foreach ($raw_exts as $ext) {
            $ext = strtolower(trim($ext));
            if (empty($ext)) continue;
            
            // Map known Mimes or fallback to generic octet-stream for custom types
            $map[$ext] = self::BASE_ALLOWED[$ext] ?? $this->guess_mime($ext);
        }

        return !empty($map) ? $map : self::BASE_ALLOWED;
    }

    private function guess_mime(string $ext): string {
        $mimes = wp_get_mime_types();
        foreach ($mimes as $regex => $mime) {
            if (preg_match('!\.(' . $regex . ')$!i', '.' . $ext)) {
                return $mime;
            }
        }
        return 'application/octet-stream';
    }
}
