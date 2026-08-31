<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Module_Registry {
    private const MODULES = [
        'vlp' => [
            'id' => 'vlp',
            'label' => 'Vision Legal Pro (VLP)',
            'short_label' => 'Privacy & Shadow Net',
            'desc' => 'GDPR & Datenschutz-Manager. Blockiert CDN-Outbounds, spiegelt Assets lokal auf dem Server und bietet einen revisionssicheren Telemetry-Vault.',
            'zone' => 'Application',
            'builtin_path' => 'includes/VLP/vision-legal-pro.php',
            'addon_folder' => 'vlp',
            'addon_file' => 'vision-legal-pro.php',
            'class' => 'VisionLegalPro_Core',
            'config_key' => 'module_vlp_enabled',
            'version' => '5.0.0',
            'schema' => 1,
            'provides' => ['telemetry:ingest','asset:mirror','privacy:enforce','translation:compile'],
            'requires' => ['vault:read','event:emit'],
        ],
        'builder' => [
            'id' => 'builder',
            'label' => 'Lightweight Builder',
            'short_label' => 'VGT Builder',
            'desc' => 'Hochleistungs-HTML/CSS Injektions-Engine. Dient als schlanker, extrem schneller Ersatz für Elementor, ohne den Server-Arbeitsspeicher zu überlasten.',
            'zone' => 'Application',
            'builtin_path' => 'includes/builder/builder.php',
            'addon_folder' => 'builder',
            'addon_file' => 'builder.php',
            'class' => 'VGT_Builder',
            'config_key' => 'module_builder_enabled',
            'version' => '3.0.0',
            'schema' => 1,
            'provides' => ['content:read','content:write','preview:sandbox','asset:manifest'],
            'requires' => ['event:emit','privacy:classify'],
        ],
        'seo' => [
            'id' => 'seo',
            'label' => 'GEO Architect (SEO)',
            'short_label' => 'VisionGaiaSEO',
            'desc' => 'Generative Engine Optimization (GEO) & Entity Injection. Optimiert die Webseiten-Struktur semantisch für KI-Suchmaschinen.',
            'zone' => 'Application',
            'builtin_path' => 'includes/VisionGaiaSEO/visiongaia-seo-architect.php',
            'addon_folder' => 'VisionGaiaSEO',
            'addon_file' => 'visiongaia-seo-architect.php',
            'class' => 'VG_SEO_Bootstrapper',
            'config_key' => 'module_seo_enabled',
            'version' => '4.6.0',
            'schema' => 2,
            'provides' => ['content:analyze','metadata:write','schema:emit','redirect:write'],
            'requires' => ['vault:read','event:emit'],
        ],
    ];

    public static function all(): array { return self::MODULES; }
    public static function get(string $id): ?array { return self::MODULES[$id] ?? null; }

    public static function get_addons_dir(): string {
        if (defined('VIS_ADDONS_DIR')) {
            return wp_normalize_path(VIS_ADDONS_DIR);
        }
        $upload_dir = wp_upload_dir(null, false);
        $addons_dir = wp_normalize_path($upload_dir['basedir'] . '/gedefense-addons');
        return $addons_dir;
    }

    public static function path(string $id): string {
        $module = self::get($id);
        if ($module === null) return '';

        // 1. Check if bundled in plugin
        if (!empty($module['builtin_path'])) {
            $builtin = VIS_PATH . $module['builtin_path'];
            if (file_exists($builtin)) {
                return $builtin;
            }
        }

        // 2. Check dynamic addons directory and variations
        $addons_dir = self::get_addons_dir();
        $folder = $module['addon_folder'];
        $file = $module['addon_file'];

        $candidates = [
            $addons_dir . '/' . $folder . '/' . $file,
            $addons_dir . '/' . strtolower($folder) . '/' . $file,
            $addons_dir . '/' . strtoupper($folder) . '/' . $file,
            $addons_dir . '/' . $id . '/' . $file,
            $addons_dir . '/' . strtolower($id) . '/' . $file,
            $addons_dir . '/' . strtoupper($id) . '/' . $file,
            $addons_dir . '/' . $file,
            VIS_PATH . 'addons/' . $folder . '/' . $file,
            VIS_PATH . 'addons/' . strtolower($folder) . '/' . $file,
            VIS_PATH . 'addons/' . $id . '/' . $file,
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }

        return '';
    }

    public static function is_installed(string $id): bool {
        $path = self::path($id);
        return !empty($path) && is_readable($path);
    }

    public static function enabled(string $id, ?array $config = null): bool {
        if (!self::is_installed($id)) {
            return false;
        }
        $module = self::get($id);
        if ($module === null) return false;
        $config ??= get_option('vis_config', []);
        if (!is_array($config)) $config = [];
        $key = $module['config_key'];
        return !array_key_exists($key, $config) || !empty($config[$key]);
    }

    public static function uninstall_addon(string $id): bool {
        $module = self::get($id);
        if ($module === null) return false;

        $addons_dir = self::get_addons_dir();
        $folder = $module['addon_folder'];
        $candidates = [
            $addons_dir . '/' . $folder,
            $addons_dir . '/' . strtolower($folder),
            $addons_dir . '/' . strtoupper($folder),
            $addons_dir . '/' . $id,
            $addons_dir . '/' . strtolower($id),
            $addons_dir . '/' . strtoupper($id),
            VIS_PATH . 'addons/' . $folder,
            VIS_PATH . 'addons/' . $id
        ];

        $deleted = false;
        foreach (array_unique($candidates) as $dir) {
            if (is_dir($dir)) {
                self::recursive_rmdir($dir);
                $deleted = true;
            }
        }
        return $deleted;
    }

    public static function recursive_rmdir(string $dir): bool {
        if (!is_dir($dir)) return false;
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::recursive_rmdir($path) : @unlink($path);
        }
        return @rmdir($dir);
    }
}