<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Airlock_Sanitizer {

    private VIS_Airlock_Config $config;

    public function __construct(VIS_Airlock_Config $config) {
        $this->config = $config;
    }

    public function generate_entropy_filename(string $filename): string {
        if (!$this->config->should_obfuscate()) {
            // Nur Basic-Sanitization, keine Obfuskation
            return preg_replace('/[^a-zA-Z0-9_.-]/', '', strtolower($filename));
        }

        $info = pathinfo($filename);
        $ext = strtolower($info['extension'] ?? '');
        $allowed_map = $this->config->get_allowed_map();
        
        if (empty($ext) || !array_key_exists($ext, $allowed_map)) {
            $ext = 'bin'; // Force safe extension if bypassed
        }

        $entropy = bin2hex(random_bytes(16));
        $clean_base = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($info['filename']));
        $clean_base = substr($clean_base, 0, 15); 
        
        if (empty($clean_base)) $clean_base = 'vgt_asset';

        return sprintf('%s_%s.%s', $clean_base, $entropy, $ext);
    }
}
