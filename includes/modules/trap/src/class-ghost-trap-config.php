<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Ghost_Trap_Config {

    private array $config;

    public function __construct() {
        $this->config = get_option('vis_config', []);
    }

    public function is_active(): bool {
        return !empty($this->config['ghost_trap_enabled']);
    }

    public function get_trap_count(): int {
        $count = isset($this->config['ghost_trap_count']) ? (int)$this->config['ghost_trap_count'] : 5;
        return max(1, min(50, $count)); // Hard limits: Min 1, Max 50
    }

    public function get_extensions(): array {
        $raw = $this->config['ghost_trap_exts'] ?? 'php, sql, bak, old';
        $exts = array_values(array_filter(array_map(
            static fn(string $extension): string => strtolower(ltrim(trim($extension), '.')),
            explode(',', is_string($raw) ? $raw : '')
        ), static fn(string $extension): bool => preg_match('/^[a-z0-9]{1,8}$/D', $extension) === 1));
        return empty($exts) ? ['php'] : $exts;
    }

    public function get_name_style(): string {
        $valid = ['system', 'backup', 'random', 'mixed'];
        $style = $this->config['ghost_trap_style'] ?? 'mixed';
        return in_array($style, $valid, true) ? $style : 'mixed';
    }
}
