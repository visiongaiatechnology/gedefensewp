<?php
// STATUS: PLATIN
declare(strict_types=1);

$root = dirname(__DIR__);
$components = [
    'root' => [
        'files' => [
            'class-vis-bootstrapper.php',
            'class-vis-schema.php',
            'class-vis-vault.php',
        ],
    ],
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
    'trap' => ['directory' => 'includes/modules/trap'],
    'vault' => ['directory' => 'includes/modules/vault'],
    'zeus' => ['directory' => 'includes/modules/zeus'],
];

/** @return array<string, string> */
$hashDirectory = static function(string $base): array {
    $resolved = realpath($base);
    if ($resolved === false || !is_dir($resolved)) {
        throw new RuntimeException('Component directory unavailable.');
    }

    $normalizedRoot = rtrim(str_replace('\\', '/', $resolved), '/');
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo
            || !$file->isFile()
            || $file->isLink()
            || in_array(strtolower($file->getExtension()), ['zip', 'tar', 'gz', '7z'], true)) {
            continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        $relative = substr($path, strlen($normalizedRoot) + 1);
        if ($relative === '' || str_starts_with($relative, '.')) {
            continue;
        }
        $hash = hash_file('sha256', $file->getPathname());
        if (!is_string($hash)) {
            throw new RuntimeException('Component file hashing failed.');
        }
        $files[$relative] = $hash;
    }
    ksort($files, SORT_STRING);
    return $files;
};

/** @param array<string, string> $files */
$rootHash = static function(array $files): string {
    $stream = '';
    foreach ($files as $path => $hash) {
        $stream .= $path . "\0" . $hash . "\n";
    }
    return hash('sha256', $stream);
};

$output = [
    'schema' => 2,
    'algorithm' => 'sha256',
    'trust' => 'entrypoint-pinned',
    'components' => [],
];

foreach ($components as $id => $definition) {
    $files = [];
    if (isset($definition['directory'])) {
        $files = $hashDirectory($root . '/' . $definition['directory']);
    } else {
        foreach ($definition['files'] as $relative) {
            $path = $root . '/' . $relative;
            if (!is_file($path) || is_link($path)) {
                throw new RuntimeException('Root artifact unavailable.');
            }
            $hash = hash_file('sha256', $path);
            if (!is_string($hash)) {
                throw new RuntimeException('Root artifact hashing failed.');
            }
            $files[$relative] = $hash;
        }
        ksort($files, SORT_STRING);
    }

    $output['components'][$id] = [
        'files' => count($files),
        'root_hash' => $rootHash($files),
    ];
}

$directory = $root . '/integrity';
if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
    throw new RuntimeException('Integrity directory creation failed.');
}
$json = json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
) . "\n";
$temporary = $directory . '/module-manifest.' . bin2hex(random_bytes(16)) . '.tmp';
if (file_put_contents($temporary, $json, LOCK_EX) === false) {
    throw new RuntimeException('Manifest staging write failed.');
}
@chmod($temporary, 0600);
if (!rename($temporary, $directory . '/module-manifest.json')) {
    @unlink($temporary);
    throw new RuntimeException('Manifest atomic commit failed.');
}
@chmod($directory . '/module-manifest.json', 0600);

echo 'VGT MODULE MANIFEST: GENERATED digest=' . hash('sha256', $json) . PHP_EOL;
