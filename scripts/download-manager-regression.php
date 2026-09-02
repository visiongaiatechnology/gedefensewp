<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root = dirname(__DIR__);
$manager = file_get_contents($root . '/includes/modules/downloads/class-secure-download-manager.php');
$aegis = file_get_contents($root . '/includes/modules/aegis/class-vis-aegis.php');
$schema = file_get_contents($root . '/class-vis-schema.php');
$failures = [];
$requirements = [
    'exact query boundary' => 'count($_GET) !== 1',
    'method allowlist' => "['GET','HEAD']",
    'cryptographic public identifier' => 'bin2hex(random_bytes(16))',
    'cryptographic storage identifier' => 'bin2hex(random_bytes(32))',
    'measured file size' => 'filesize($source)',
    'content MIME detection' => 'FILEINFO_MIME_TYPE',
    'upload path jail' => 'str_starts_with($source, $uploadRoot . DIRECTORY_SEPARATOR)',
    'vault path jail' => 'str_starts_with($path, $directory . DIRECTORY_SEPARATOR)',
    'constant-time integrity check' => 'hash_equals((string)$record->file_hash, $digest)',
    'private file mode' => 'chmod($temporary, 0600)',
    'private directory mode' => 'mkdir($directory, 0700, true)',
    'bounded rate limiter' => '$hits >= 30',
];
foreach ($requirements as $label => $needle) if (!is_string($manager) || !str_contains($manager, $needle)) $failures[] = 'Missing ' . $label . '.';
if (!is_string($aegis) || !str_contains($aegis, 'DownloadManager') || !str_contains($aegis, 'isTrustedRequest')) $failures[] = 'AEGIS exact trusted-download bridge missing.';
if (!is_string($schema) || !str_contains($schema, 'vis_secure_downloads')) $failures[] = 'Secure download registry schema missing.';
if (is_string($aegis) && preg_match('/zip[^\n]{0,120}(?:whitelist|allow)/i', $aegis) === 1) $failures[] = 'Global ZIP allow rule detected in AEGIS.';

if ($failures !== []) {
    fwrite(STDERR, "VGT DOWNLOAD MANAGER REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "VGT DOWNLOAD MANAGER REGRESSION: PASS\n");
