<?php
// STATUS: PLATIN
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

final class VIS_Module_Integrity {
    private const COMPONENTS = [
        'root' => ['files' => ['class-vis-bootstrapper.php', 'class-vis-schema.php', 'class-vis-vault.php']],
        'assets' => ['directory' => 'assets'],
        'compatibility' => ['directory' => 'includes/compatibility'],
        'core' => ['directory' => 'includes/core'],
        'dashboard' => ['directory' => 'includes/dashboard'],
        'scanner' => ['directory' => 'includes/scanner'],
        'aegis' => ['directory' => 'includes/modules/aegis'],
        'airlock' => ['directory' => 'includes/modules/airlock'],
        'cerberus' => ['directory' => 'includes/modules/cerberus'],
        'chronos' => ['directory' => 'includes/modules/chronos'],
        'filesystem' => ['directory' => 'includes/modules/filesystem'],
        'gorgon' => ['directory' => 'includes/modules/gorgon'],
        'hades' => ['directory' => 'includes/modules/hades'],
        'kernel' => ['directory' => 'includes/modules/kernel'],
        'morpheus' => ['directory' => 'includes/modules/morpheus'],
        'nemesis' => ['directory' => 'includes/modules/nemesis'],
        'oracle' => ['directory' => 'includes/modules/oracle'],
        'prometheus' => ['directory' => 'includes/modules/prometheus'],
        'styx' => ['directory' => 'includes/modules/styx'],
        'titan' => ['directory' => 'includes/modules/titan'],
        'throneguard' => ['directory' => 'includes/modules/throneguard'],
        'loginpager' => ['directory' => 'includes/modules/loginpager'],
        'trap' => ['directory' => 'includes/modules/trap'],
        'vault' => ['directory' => 'includes/modules/vault'],
        'zeus' => ['directory' => 'includes/modules/zeus'],
    ];

    /** @return array{status:string,color:string,msg:string} */
    public static function verify(string $id): array {
        if (!isset(self::COMPONENTS[$id])) {
            return self::result('UNENROLLED', 'var(--vgt-neon-yellow)', 'Unknown component.');
        }

        $manifest = self::trusted_manifest();
        if ($manifest === null) {
            return self::result('UNTRUSTED', 'var(--vgt-neon-red)', 'Manifest trust anchor rejected.');
        }
        $expected = $manifest['components'][$id] ?? null;
        if (!is_array($expected)
            || !isset($expected['files'], $expected['root_hash'])
            || !is_int($expected['files'])
            || !is_string($expected['root_hash'])) {
            return self::result('UNTRUSTED', 'var(--vgt-neon-red)', 'Manifest component schema rejected.');
        }

        try {
            $actual = self::component_tree($id);
        } catch (Throwable $e) {
            error_log('[VIS INTEGRITY] ' . $e->getMessage());
            return self::result('UNAVAILABLE', 'var(--vgt-neon-red)', 'Component verification unavailable.');
        }

        if (count($actual) !== $expected['files']
            || !hash_equals($expected['root_hash'], self::root_hash($actual))) {
            return self::result('MODIFIED', 'var(--vgt-neon-red)', 'Component differs from pinned artifact.');
        }

        return self::result(
            'VERIFIED',
            'var(--vgt-neon-green)',
            count($actual) . ' files verified against the pinned trust anchor.'
        );
    }

    /** @return array<string, array{status:string,color:string,msg:string}> */
    public static function verify_all(): array {
        $results = [];
        foreach (array_keys(self::COMPONENTS) as $id) {
            $results[$id] = self::verify($id);
        }
        return $results;
    }

    /** @return array<string, string> */
    private static function component_tree(string $id): array {
        $definition = self::COMPONENTS[$id];
        if (isset($definition['directory'])) {
            return self::tree(VIS_PATH . $definition['directory']);
        }

        $files = [];
        foreach ($definition['files'] as $relative) {
            $path = VIS_PATH . $relative;
            if (!is_file($path) || is_link($path)) {
                throw new StorageException('Root integrity artifact unavailable.');
            }
            $hash = hash_file('sha256', $path);
            if (!is_string($hash)) {
                throw new StorageException('Root integrity hashing failed.');
            }
            $files[$relative] = $hash;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @return array<string, string> */
    public static function tree(string $base): array {
        $root = realpath($base);
        if ($root === false || !is_dir($root)) {
            throw new StorageException('Integrity component directory unavailable.');
        }
        $normalizedRoot = rtrim(wp_normalize_path($root), '/');
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo
                || !$file->isFile()
                || $file->isLink()
                || in_array(strtolower($file->getExtension()), ['zip', 'tar', 'gz', '7z'], true)) {
                continue;
            }
            $path = wp_normalize_path($file->getPathname());
            $relative = substr($path, strlen($normalizedRoot) + 1);
            if ($relative === '' || str_starts_with($relative, '.')) {
                continue;
            }
            $hash = hash_file('sha256', $file->getPathname());
            if (!is_string($hash)) {
                throw new StorageException('Integrity component hashing failed.');
            }
            $files[$relative] = $hash;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @param array<string, string> $files */
    public static function root_hash(array $files): string {
        $stream = '';
        foreach ($files as $path => $hash) {
            $stream .= $path . "\0" . $hash . "\n";
        }
        return hash('sha256', $stream);
    }

    private static function trusted_manifest(): ?array {
        if (!defined('VIS_MANIFEST_DIGEST')
            || preg_match('/^[a-f0-9]{64}$/D', (string)VIS_MANIFEST_DIGEST) !== 1) {
            return null;
        }
        $file = VIS_PATH . 'integrity/module-manifest.json';
        $json = is_file($file) ? file_get_contents($file) : false;
        if (!is_string($json)
            || !hash_equals((string)VIS_MANIFEST_DIGEST, hash('sha256', $json))) {
            return null;
        }

        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return null;
        }
        if (!is_array($data)
            || ($data['schema'] ?? null) !== 2
            || ($data['algorithm'] ?? null) !== 'sha256'
            || ($data['trust'] ?? null) !== 'entrypoint-pinned'
            || !is_array($data['components'] ?? null)) {
            return null;
        }

        return $data;
    }

    /** @return array{status:string,color:string,msg:string} */
    private static function result(string $status, string $color, string $message): array {
        return ['status' => $status, 'color' => $color, 'msg' => $message];
    }
}
