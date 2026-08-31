<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
define('ABSPATH', $root . DIRECTORY_SEPARATOR);
define('VIS_PATH', str_replace('\\', '/', $root) . '/');

require_once $root . '/class-vis-bootstrapper.php';
VIS_Bootstrapper::register_autoloader();

$failures = [];
$canonical = [
    'VisionGaia\\GeDefense\\Core\\Bootstrapper' => 'VIS_Bootstrapper',
    'VisionGaia\\GeDefense\\Core\\Security' => 'VIS_Security',
    'VisionGaia\\GeDefense\\Modules\\ThroneGuard\\ThroneGuard' => 'VIS_Throne_Guard',
    'VisionGaia\\GeDefense\\Modules\\LoginPager\\LoginPager' => 'VIS_LoginPager',
    'VisionGaia\\GeDefense\\Modules\\Morpheus\\Morpheus' => 'VGT\\Sentinel\\Modules\\Morpheus\\Vis_Morpheus',
    'VisionGaia\\GeDefense\\Modules\\Gorgon\\Gorgon' => 'VGT\\Sentinel\\Modules\\Gorgon\\Vis_Gorgon',
];
foreach ($canonical as $class => $legacy) {
    if (!class_exists($class)) {
        $failures[] = 'Canonical symbol unavailable: ' . $class;
        continue;
    }
    if (!class_exists($legacy, false) || !is_a($class, $legacy, true)) {
        $failures[] = 'Legacy compatibility alias unavailable: ' . $legacy;
    }
}

$domainRoots = ['core', 'dashboard', 'scanner', 'modules', 'compatibility'];
foreach ($domainRoots as $domainRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/includes/' . $domainRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) {
            $failures[] = 'Namespace source unavailable.';
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if ($relative === 'includes/core/class-namespace-compatibility.php') {
            continue;
        }
        if (preg_match('/^\s*namespace\s+(?!VisionGaia\\\\GeDefense(?:\\\\|;))/m', $source) === 1) {
            $failures[] = 'Foreign namespace declaration: ' . $relative;
        }
        if (str_contains($source, 'VisionGaia\\Integrity\\') || str_contains($source, 'VGT\\Sentinel\\')) {
            $failures[] = 'Legacy namespace reference escaped boundary: ' . $relative;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "VGT NAMESPACE REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT NAMESPACE REGRESSION: PASS\n");
