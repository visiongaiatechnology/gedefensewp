<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Morpheus;

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

final class Morpheus_Path_Jail {
    private const AREAS = ['audit', 'proposed'];
    private const MAX_SLUG_BYTES = 128;

    public static function validate_slug(mixed $candidate): string {
        if (!is_string($candidate)) {
            throw new \ValidationException('Invalid plugin identifier.');
        }

        $slug = trim(wp_unslash($candidate));
        if ($slug === ''
            || strlen($slug) > self::MAX_SLUG_BYTES
            || preg_match('/^[a-z0-9][a-z0-9_-]*$/iD', $slug) !== 1) {
            throw new \SecurityException('Plugin path validation failed.');
        }

        return $slug;
    }

    public static function root(): string {
        $input = defined('WP_CONTENT_DIR')
            ? WP_CONTENT_DIR . '/vgt-vault/morpheus'
            : dirname(__DIR__, 5) . '/wp-content/vgt-vault/morpheus';

        self::ensure_directory($input);
        $resolvedDir = realpath($input);
        if ($resolvedDir === false || !is_dir($resolvedDir)) {
            throw new \StorageException('Morpheus storage directory unresolved.');
        }

        return wp_normalize_path($resolvedDir);
    }

    public static function directory(string $area): string {
        if (!in_array($area, self::AREAS, true)) {
            throw new \SecurityException('Storage path area rejected.');
        }

        $root = self::root();
        $input = $root . DIRECTORY_SEPARATOR . $area;
        self::ensure_directory($input);
        $resolvedDir = realpath($input);
        if ($resolvedDir === false || !is_dir($resolvedDir)) {
            throw new \StorageException('Morpheus storage area unresolved.');
        }
        if (!str_starts_with(
            wp_normalize_path($resolvedDir) . '/',
            rtrim(wp_normalize_path($root), '/') . '/'
        )) {
            throw new \SecurityException('Path escaped jail.');
        }

        return wp_normalize_path($resolvedDir);
    }

    public static function file(string $area, string $slug, string $suffix): string {
        $validated_slug = self::validate_slug($slug);
        if (preg_match('/^\.[a-z0-9.]{1,32}$/iD', $suffix) !== 1) {
            throw new \SecurityException('Storage path suffix rejected.');
        }

        $input = self::directory($area);
        $resolvedDir = realpath($input);
        if ($resolvedDir === false || !is_dir($resolvedDir)) {
            throw new \StorageException('Morpheus storage area unresolved.');
        }

        $filename = $validated_slug . $suffix;
        $destination = $resolvedDir . DIRECTORY_SEPARATOR . $filename;
        if (!str_starts_with($destination, $resolvedDir . DIRECTORY_SEPARATOR)) {
            throw new \SecurityException('Path escaped jail.');
        }
        if (is_link($destination)) {
            throw new \SecurityException('Symbolic path target rejected.');
        }

        return wp_normalize_path($destination);
    }

    public static function root_file(string $filename): string {
        if ($filename !== '.htaccess'
            && preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/iD', $filename) !== 1) {
            throw new \SecurityException('Storage path filename rejected.');
        }

        $input = self::root();
        $resolvedDir = realpath($input);
        if ($resolvedDir === false || !is_dir($resolvedDir)) {
            throw new \StorageException('Morpheus storage directory unresolved.');
        }
        $destination = $resolvedDir . DIRECTORY_SEPARATOR . $filename;
        if (!str_starts_with($destination, $resolvedDir . DIRECTORY_SEPARATOR)) {
            throw new \SecurityException('Path escaped jail.');
        }
        if (is_link($destination)) {
            throw new \SecurityException('Symbolic path target rejected.');
        }

        return wp_normalize_path($destination);
    }

    public static function existing_file(string $area, string $slug, string $suffix): string {
        $candidate = self::file($area, $slug, $suffix);
        $resolved = realpath($candidate);
        $resolved_area = realpath(self::directory($area));
        if ($resolved === false || $resolved_area === false || !is_file($resolved)) {
            throw new \StorageException('Requested Morpheus artifact unavailable.');
        }
        if (!str_starts_with($resolved, $resolved_area . DIRECTORY_SEPARATOR)) {
            throw new \SecurityException('Path escaped jail.');
        }

        return wp_normalize_path($resolved);
    }

    private static function ensure_directory(string $path): void {
        $previous_umask = umask(0077);
        try {
            if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
                throw new \StorageException('Morpheus storage directory unavailable.');
            }
            if (!@chmod($path, 0700) && DIRECTORY_SEPARATOR !== '\\') {
                throw new \StorageException('Morpheus storage permissions unavailable.');
            }
        } finally {
            umask($previous_umask);
        }
    }
}
