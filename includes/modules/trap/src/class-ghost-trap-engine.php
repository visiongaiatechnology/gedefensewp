<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Ghost_Trap_Engine {
    private const LEGACY_MANIFEST_KEY = 'vis_ghost_trap_manifest';
    private const DICT_SYSTEM = ['setup', 'config', 'admin-ajax-test', 'wp-config-sample', 'sys_info', 'debug', 'phpinfo', 'install'];
    private const DICT_BACKUP = ['db_dump', 'backup', 'wp_db', 'site_backup', 'old_config', 'archive', 'dump_2024', 'data'];

    public function __construct(
        private VIS_Ghost_Trap_Config $config,
        private ?VIS_Ghost_Trap_Authenticator $authenticator = null
    ) {
        $this->authenticator ??= new VIS_Ghost_Trap_Authenticator();
    }

    public function redeploy_matrix(): void {
        $this->destroy_all_traps();
        if (!$this->config->is_active()) return;

        $manifest = [];
        $legacyManifest = [];
        $attempts = 0;
        $target = $this->config->get_trap_count();
        while (count($manifest) < $target && $attempts < ($target * 5)) {
            $attempts++;
            $filename = $this->generate_filename($this->config->get_name_style(), $this->config->get_extensions());
            $filepath = $this->rootPath($filename);
            if (file_exists($filepath)) continue;

            $issued = $this->authenticator->issue($filename, $this->compile_trap_body());
            $temporary = $filepath . '.' . bin2hex(random_bytes(8)) . '.tmp';
            if (file_put_contents($temporary, $issued['payload'], LOCK_EX) === false) continue;
            chmod($temporary, 0600);
            if (!rename($temporary, $filepath)) {
                unlink($temporary);
                continue;
            }
            chmod($filepath, 0644);
            $manifest[$filename] = $issued['record'];
            $legacyManifest[] = $filename;
        }

        if (count($manifest) !== $target) {
            foreach (array_keys($manifest) as $filename) {
                $filepath = $this->rootPath($filename);
                if (is_file($filepath)) unlink($filepath);
            }
            throw new StorageException('Ghost trap deployment could not reach the configured node count.');
        }

        $v2Updated = update_option(VIS_Ghost_Trap_Authenticator::MANIFEST_KEY, $manifest, false);
        $legacyUpdated = update_option(self::LEGACY_MANIFEST_KEY, $legacyManifest, false);
        if ((!$v2Updated && get_option(VIS_Ghost_Trap_Authenticator::MANIFEST_KEY, null) !== $manifest)
            || (!$legacyUpdated && get_option(self::LEGACY_MANIFEST_KEY, null) !== $legacyManifest)) {
            foreach (array_keys($manifest) as $filename) {
                $filepath = $this->rootPath($filename);
                if (is_file($filepath)) unlink($filepath);
            }
            throw new StorageException('Ghost trap manifest persistence failed.');
        }
    }

    public function requiresMigration(): bool {
        if ($this->authenticator->manifest() === []) return true;
        $legacy = get_option(self::LEGACY_MANIFEST_KEY, []);
        if (!is_array($legacy)) return false;
        foreach ($legacy as $filename) {
            if (!is_string($filename) || basename($filename) !== $filename) continue;
            $filepath = $this->rootPath($filename);
            if (!is_file($filepath) || $this->authenticator->isRegisteredTrap($filepath)) continue;
            $size = filesize($filepath);
            if ($size === false || $size === 0 || $size > 16384) continue;
            $content = file_get_contents($filepath);
            if (is_string($content) && str_contains($content, 'VISIONGAIATECHNOLOGY GHOST TRAP')) return true;
        }
        return false;
    }

    public function destroy_all_traps(): void {
        $v2Manifest = $this->authenticator->manifest();
        foreach (array_keys($v2Manifest) as $filename) {
            $filepath = $this->rootPath($filename);
            if (is_file($filepath) && $this->authenticator->isRegisteredTrap($filepath)) unlink($filepath);
        }

        $legacy = get_option(self::LEGACY_MANIFEST_KEY, []);
        if (is_array($legacy)) {
            foreach ($legacy as $filename) {
                if (!is_string($filename) || basename($filename) !== $filename) continue;
                $filepath = $this->rootPath($filename);
                if (!is_file($filepath) || filesize($filepath) > 16384) continue;
                $content = file_get_contents($filepath);
                if (is_string($content) && str_contains($content, 'VISIONGAIATECHNOLOGY GHOST TRAP')) unlink($filepath);
            }
        }
        delete_option(VIS_Ghost_Trap_Authenticator::MANIFEST_KEY);
        delete_option(self::LEGACY_MANIFEST_KEY);
    }

    private function rootPath(string $filename): string {
        $root = realpath(ABSPATH);
        if ($root === false || !is_dir($root)) throw new StorageException('Ghost trap root unavailable.');
        $resolvedRoot = rtrim(wp_normalize_path($root), '/');
        $destination = $resolvedRoot . '/' . $filename;
        if (!str_starts_with($destination, $resolvedRoot . '/')) throw new SecurityException('Ghost trap path escaped jail.');
        return $destination;
    }

    private function generate_filename(string $style, array $extensions): string {
        $ext = $extensions[random_int(0, count($extensions) - 1)];
        $currentStyle = $style === 'mixed' ? ['system', 'backup', 'random'][random_int(0, 2)] : $style;
        $base = match ($currentStyle) {
            'system' => self::DICT_SYSTEM[random_int(0, count(self::DICT_SYSTEM) - 1)] . (random_int(0, 1) ? '_' . random_int(1, 99) : ''),
            'backup' => self::DICT_BACKUP[random_int(0, count(self::DICT_BACKUP) - 1)] . '_' . gmdate('Y_m_d'),
            default => bin2hex(random_bytes(4)),
        };
        return $base . '.' . $ext;
    }

    private function compile_trap_body(): string {
        $tableName = defined('VIS_TABLE_BANS') ? (string)VIS_TABLE_BANS : 'vis_apex_bans';
        return <<<PHP
define('SHORTINIT', true);
require_once __DIR__ . '/wp-load.php';
global \$wpdb;
\$ip = isset(\$_SERVER['REMOTE_ADDR']) ? (string)\$_SERVER['REMOTE_ADDR'] : '';
if (filter_var(\$ip, FILTER_VALIDATE_IP) !== false) {
    \$uri = isset(\$_SERVER['REQUEST_URI']) ? substr((string)\$_SERVER['REQUEST_URI'], 0, 2048) : '';
    \$table = \$wpdb->prefix . '{$tableName}';
    \$query = \$wpdb->prepare("INSERT IGNORE INTO \$table (ip, reason, banned_at, request_uri) VALUES (%s, %s, %s, %s)", \$ip, 'GHOST_TRAP: Signed deception node hit', current_time('mysql'), \$uri);
    \$wpdb->query(\$query);
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo '404 Not Found';
exit;
PHP;
    }
}
