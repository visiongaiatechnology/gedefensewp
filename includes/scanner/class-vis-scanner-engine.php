<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

require_once __DIR__ . '/class-vis-malware-engine.php';
require_once __DIR__ . '/storage/class-vis-quarantine-store.php';

final class VIS_Scanner_Engine_Omega {
    private const INDEX_DIRECTORY_BUDGET = 250;
    private const INDEX_TIME_BUDGET_SECONDS = 3.0;
    private const PROCESS_BATCH_SIZE = 150;
    private const PROCESS_TIME_BUDGET_SECONDS = 4.0;
    private const MAX_STATE_BYTES = 67108864;

    /** @var array<string, true> */
    private array $excludedDirectories = [
        'node_modules' => true, '.git' => true, 'cache' => true, 'upgrade' => true,
        'languages' => true, 'vis-vault-omega' => true, 'tmp_vis_states' => true,
        'updraft' => true, 'backups' => true, 'wprocket' => true, 'scripts' => true,
    ];

    /** @var array<string, true> */
    private array $monitoredExtensions = [
        'php' => true, 'php3' => true, 'php4' => true, 'php5' => true, 'php7' => true,
        'php8' => true, 'phtml' => true, 'phar' => true, 'html' => true, 'htm' => true,
        'js' => true, 'htaccess' => true, 'ini' => true, 'svg' => true, 'zip' => true,
        'py' => true, 'pl' => true,
    ];

    private string $siteRoot;
    private string $vaultDirectory;
    private string $manifestFile;
    private string $stateDirectory;
    private string $queueFile;
    private string $resultFile;
    private string $findingFile;
    private string $indexStateFile;
    private string $cursorFile;
    private VIS_Malware_Engine $malwareEngine;

    public function __construct() {
        $resolvedRoot = realpath(ABSPATH);
        if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new StorageException('Integrity scan root unavailable.');
        }
        $this->siteRoot = rtrim(wp_normalize_path($resolvedRoot), '/') . '/';
        if (defined('VIS_VAULT_DIR')) {
            $this->vaultDirectory = rtrim(wp_normalize_path((string)VIS_VAULT_DIR), '/');
        } else {
            $uploadDirectory = wp_upload_dir();
            $this->vaultDirectory = rtrim(wp_normalize_path((string)($uploadDirectory['basedir'] ?? '')), '/') . '/vis-vault-omega';
        }
        if (defined('VIS_MANIFEST_FILE')) {
            $this->manifestFile = wp_normalize_path((string)VIS_MANIFEST_FILE);
        } else {
            $this->manifestFile = $this->vaultDirectory . '/integrity_matrix.json';
        }
        $this->stateDirectory = $this->vaultDirectory . '/tmp_vis_states';
        $this->queueFile = $this->stateDirectory . '/scan_queue.ndjson';
        $this->resultFile = $this->stateDirectory . '/current_scan.ndjson';
        $this->findingFile = $this->stateDirectory . '/malware_findings.ndjson';
        $this->indexStateFile = $this->stateDirectory . '/index_state.json';
        $this->cursorFile = $this->stateDirectory . '/process_cursor.json';
        $this->malwareEngine = new VIS_Malware_Engine();
    }

    /** @return array<string, mixed> */
    public function run_scan_cycle(string $phase = 'init', int $offset = 0, string $mode = 'scan'): array {
        if (!$this->ensureVaultExists()) {
            return ['status' => 'error', 'message' => 'Scanner vault permission enforcement failed.'];
        }
        $mode = $mode === 'reindex' ? 'reindex' : 'scan';
        $offset = max(0, $offset);

        try {
            return match ($phase) {
                'init' => $this->initializeScan($mode),
                'index' => $this->continueIndexing($offset, $mode),
                'process' => $this->processFiles($offset, $mode),
                'finalize' => $this->finalizeScan($mode),
                default => throw new ValidationException('Invalid scanner phase.'),
            };
        } catch (ValidationException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (SecurityException $e) {
            error_log('[SEC] ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Request rejected for security reasons.'];
        } catch (StorageException $e) {
            error_log('[STORAGE] ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'A server error occurred.'];
        } catch (Throwable $e) {
            error_log('[FATAL] ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Critical system fault.'];
        }
    }

    /** @return array<string, mixed> */
    private function initializeScan(string $mode): array {
        foreach ([$this->queueFile, $this->resultFile, $this->findingFile, $this->indexStateFile, $this->cursorFile] as $file) {
            if (is_file($file) && !@unlink($file)) throw new StorageException('Stale scanner state cleanup failed.');
        }
        $this->writeJson($this->indexStateFile, ['pending' => [$this->siteRoot], 'files' => 0, 'directories' => 0]);
        $this->truncateFile($this->queueFile);
        $this->truncateFile($this->resultFile);
        $this->truncateFile($this->findingFile);
        return [
            'status' => 'next_phase', 'phase' => 'index', 'offset' => 0, 'mode' => $mode,
            'total' => 0, 'message' => 'Initializing jailed filesystem inventory...',
        ];
    }

    /** @return array<string, mixed> */
    private function continueIndexing(int $offset, string $mode): array {
        $state = $this->loadJson($this->indexStateFile);
        $pending = is_array($state['pending'] ?? null) ? array_values($state['pending']) : [];
        $files = max(0, (int)($state['files'] ?? 0));
        $directories = max(0, (int)($state['directories'] ?? 0));
        $started = microtime(true);
        $processedDirectories = 0;

        while ($pending !== []
            && $processedDirectories < self::INDEX_DIRECTORY_BUDGET
            && (microtime(true) - $started) < self::INDEX_TIME_BUDGET_SECONDS) {
            $directory = array_pop($pending);
            if (!is_string($directory)) continue;
            $resolvedDirectory = realpath($directory);
            if ($resolvedDirectory === false || !is_dir($resolvedDirectory) || is_link($directory)) continue;
            $normalizedDirectory = rtrim(wp_normalize_path($resolvedDirectory), '/') . '/';
            if (!str_starts_with($normalizedDirectory, $this->siteRoot)) {
                throw new SecurityException('Integrity indexing path escaped jail.');
            }

            try {
                $iterator = new FilesystemIterator($resolvedDirectory, FilesystemIterator::SKIP_DOTS);
                foreach ($iterator as $entry) {
                    if (!$entry instanceof SplFileInfo || $entry->isLink()) continue;
                    $name = strtolower($entry->getFilename());
                    if ($entry->isDir()) {
                        if (!isset($this->excludedDirectories[$name])) $pending[] = $entry->getPathname();
                        continue;
                    }
                    if (!$entry->isFile()) continue;
                    $extension = strtolower($entry->getExtension());
                    if (!isset($this->monitoredExtensions[$extension])) continue;
                    $normalized = wp_normalize_path($entry->getPathname());
                    if (!str_starts_with($normalized, $this->siteRoot)) {
                        throw new SecurityException('Integrity file path escaped jail.');
                    }
                    $relative = substr($normalized, strlen($this->siteRoot));
                    if ($relative === '' || str_contains($relative, "\0")) continue;
                    $this->appendLine($this->queueFile, $relative);
                    $files++;
                }
            } catch (UnexpectedValueException $e) {
                $this->appendFindingRecord([
                    'file' => substr($normalizedDirectory, strlen($this->siteRoot)),
                    'verdict' => ['risk' => 20, 'confidence' => 100, 'truncated' => false, 'findings' => [[
                        'code' => 'DIRECTORY_UNREADABLE', 'risk' => 85, 'confidence' => 100,
                        'message' => 'Directory could not be read; a complete integrity baseline cannot be proven.', 'quarantine_eligible' => false,
                    ]]],
                ]);
            }
            $processedDirectories++;
            $directories++;
        }

        $state = ['pending' => $pending, 'files' => $files, 'directories' => $directories];
        $this->writeJson($this->indexStateFile, $state);
        if ($pending !== []) {
            return [
                'status' => 'processing', 'phase' => 'index', 'offset' => $directories, 'mode' => $mode,
                'total' => $files, 'message' => "Indexing jailed filesystem... {$files} files discovered",
            ];
        }

        $this->writeJson($this->cursorFile, ['index' => 0, 'byte_offset' => 0]);
        return [
            'status' => 'next_phase', 'phase' => 'process', 'offset' => 0, 'mode' => $mode,
            'total' => $files, 'message' => 'Inventory complete. Starting malware and integrity analysis...',
        ];
    }

    /** @return array<string, mixed> */
    private function processFiles(int $requestedOffset, string $mode): array {
        $cursor = $this->loadJson($this->cursorFile);
        $index = max(0, (int)($cursor['index'] ?? 0));
        $byteOffset = max(0, (int)($cursor['byte_offset'] ?? 0));
        if ($requestedOffset > $index) throw new ValidationException('Scanner cursor is ahead of committed state.');
        if ($requestedOffset < $index) {
            return ['status' => 'processing', 'phase' => 'process', 'offset' => $index, 'mode' => $mode, 'message' => 'Resuming committed scan cursor...'];
        }

        $handle = @fopen($this->queueFile, 'rb');
        if (!is_resource($handle) || fseek($handle, $byteOffset) !== 0) {
            if (is_resource($handle)) fclose($handle);
            throw new StorageException('Scanner queue cursor unavailable.');
        }

        $baseline = $this->loadJson($this->manifestFile);
        $processed = 0;
        $started = microtime(true);
        while (!feof($handle)
            && $processed < self::PROCESS_BATCH_SIZE
            && (microtime(true) - $started) < self::PROCESS_TIME_BUDGET_SECONDS) {
            $line = fgets($handle);
            if ($line === false) break;
            $relative = trim($line);
            if ($relative === '') continue;
            $this->processOneFile($relative, $baseline);
            $processed++;
            $index++;
        }
        $nextByteOffset = ftell($handle);
        $finished = feof($handle);
        fclose($handle);
        if (!is_int($nextByteOffset)) throw new StorageException('Scanner queue cursor persistence failed.');
        $this->writeJson($this->cursorFile, ['index' => $index, 'byte_offset' => $nextByteOffset]);

        if ($finished) {
            return [
                'status' => 'next_phase', 'phase' => 'finalize', 'offset' => 0, 'mode' => $mode,
                'message' => 'Analysis complete. Correlating integrity and malware findings...',
            ];
        }
        return [
            'status' => 'processing', 'phase' => 'process', 'offset' => $index, 'mode' => $mode,
            'message' => "Deep malware analysis... {$index} files processed",
        ];
    }

    /** @param array<string, mixed> $baseline */
    private function processOneFile(string $relative, array $baseline): void {
        if (str_contains($relative, "\0") || str_starts_with($relative, '/') || preg_match('~(?:^|/)\.\.(?:/|$)~', $relative) === 1) {
            throw new SecurityException('Scanner queue path traversal rejected.');
        }
        $candidate = $this->siteRoot . str_replace('\\', '/', $relative);
        if (is_link($candidate)) throw new SecurityException('Scanner symlink target rejected.');
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            $unavailableFinding = [
                'code' => 'FILE_UNAVAILABLE',
                'risk' => 85,
                'confidence' => 100,
                'message' => 'Indexed file became unavailable before content verification.',
                'quarantine_eligible' => false,
            ];
            $this->appendFindingRecord([
                'file' => $relative,
                'change_type' => 'UNAVAILABLE',
                'verdict' => [
                    'risk' => 85,
                    'confidence' => 100,
                    'truncated' => false,
                    'findings' => [$unavailableFinding],
                ],
            ]);
            return;
        }
        $normalized = wp_normalize_path($resolved);
        if (!str_starts_with($normalized, $this->siteRoot)) throw new SecurityException('Scanner target path escaped jail.');

        $sha256 = hash_file('sha256', $resolved);
        if (!is_string($sha256)) throw new StorageException('Integrity file hashing failed.');
        $oldHash = is_array($baseline[$relative] ?? null) ? (string)($baseline[$relative]['hash'] ?? '') : '';
        $changeType = $oldHash === '' ? 'NEW' : (hash_equals($oldHash, $sha256) ? 'UNCHANGED' : 'MODIFIED');
        $quarantined = false;

        if ($changeType !== 'UNCHANGED' || $baseline === []) {
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            $context = new VIS_Scan_Context(
                VIS_Scan_Context::PROFILE_DEEP_FILESYSTEM,
                'INTEGRITY',
                $relative,
                basename($relative),
                $extension,
                VIS_Malware_Engine::detectMime($resolved),
                null,
                $changeType
            );
            $verdict = $this->malwareEngine->scan($resolved, $context, VIS_Scan_Budget::deepFilesystem());
            if ($verdict->findings !== []) {
                $record = ['file' => $relative, 'change_type' => $changeType, 'verdict' => $verdict->toArray()];
                if ($verdict->shouldQuarantine() && $this->isUploadExecutable($relative, $extension)) {
                    $store = new VIS_Quarantine_Store();
                    $record['quarantine'] = $store->quarantine($resolved, $sha256, $verdict);
                    $quarantined = true;
                }
                $this->appendFindingRecord($record);
                if (class_exists('VIS_Trinity_Grid')) {
                    VIS_Trinity_Grid::onMalwareFinding('INTEGRITY', $relative, $verdict->toArray(), null);
                }
            }
        }

        if (!$quarantined) {
            $size = filesize($resolved);
            $mtime = filemtime($resolved);
            $this->appendJsonRecord($this->resultFile, [
                'path' => $relative,
                'hash' => $sha256,
                'mtime' => $mtime === false ? 0 : $mtime,
                'size' => $size === false ? 0 : $size,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function finalizeScan(string $mode): array {
        $baseline = $this->loadJson($this->manifestFile);
        $newState = $this->loadResultState();
        $findingRecords = $this->loadNdjson($this->findingFile);
        $malwareChanges = $this->findingChanges($findingRecords);
        $blockingMalware = false;
        foreach ($findingRecords as $record) {
            $verdict = is_array($record['verdict'] ?? null) ? $record['verdict'] : [];
            if ((int)($verdict['risk'] ?? 0) >= 80 && (int)($verdict['confidence'] ?? 0) >= 75) {
                $blockingMalware = true;
                break;
            }
        }

        if ($mode === 'reindex') {
            if ($blockingMalware) {
                $report = [
                    'status' => 'warning',
                    'message' => 'Baseline approval refused because high-confidence malware findings exist.',
                    'changes' => $malwareChanges,
                    'timestamp' => current_time('mysql'),
                ];
            } else {
                $this->commitBaseline($newState);
                $report = [
                    'status' => $malwareChanges === [] ? 'clean' : 'warning',
                    'message' => $malwareChanges === [] ? 'Baseline securely recalibrated after malware analysis.' : 'Baseline recalibrated, but non-blocking malware findings require review.',
                    'changes' => $malwareChanges, 'timestamp' => current_time('mysql'),
                    'baseline' => hash('sha256', wp_json_encode($newState, JSON_THROW_ON_ERROR)),
                ];
            }
        } elseif ($baseline === []) {
            if ($blockingMalware) {
                $report = [
                    'status' => 'warning',
                    'message' => 'Initial baseline refused because high-confidence malware findings exist.',
                    'changes' => $malwareChanges,
                    'timestamp' => current_time('mysql'),
                ];
            } else {
                $this->commitBaseline($newState);
                $report = [
                    'status' => $malwareChanges === [] ? 'init' : 'warning', 'message' => $malwareChanges === [] ? 'Initial malware-screened system baseline established.' : 'Initial baseline established with non-blocking findings requiring review.',
                    'changes' => $malwareChanges, 'timestamp' => current_time('mysql'),
                ];
            }
        } else {
            $this->assertManifestIdentity($baseline, $newState);
            $changes = array_merge($this->compareManifests($baseline, $newState), $malwareChanges);
            if ($changes === []) {
                $this->commitBaseline($newState);
                $report = [
                    'status' => 'clean', 'message' => 'Integrity and malware analysis verified the system.',
                    'changes' => [], 'timestamp' => current_time('mysql'),
                ];
            } else {
                $report = [
                    'status' => 'warning', 'message' => 'Integrity or malware findings require review.',
                    'changes' => $changes, 'timestamp' => current_time('mysql'),
                ];
            }
        }

        wp_cache_delete('vis_scan_report', 'options');
        wp_cache_delete('alloptions', 'options');
        $updated = update_option('vis_scan_report', $report, false);
        if (!$updated && get_option('vis_scan_report', null) !== $report) {
            throw new StorageException('Integrity report persistence failed.');
        }
        wp_cache_delete('vis_scan_report', 'options');
        wp_cache_delete('alloptions', 'options');
        $this->cleanupScanState();
        return $report;
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new @return list<array<string, string>> */
    private function compareManifests(array $old, array $new): array {
        $changes = [];
        foreach ($new as $path => $data) {
            if (!isset($old[$path])) {
                $changes[] = ['type' => 'NEW', 'file' => $path, 'desc' => 'New file detected'];
            } elseif (!hash_equals((string)($old[$path]['hash'] ?? ''), (string)($data['hash'] ?? ''))) {
                $changes[] = ['type' => 'MODIFIED', 'file' => $path, 'desc' => 'Content hash mismatch'];
            }
        }
        foreach ($old as $path => $data) {
            if (!isset($new[$path])) $changes[] = ['type' => 'DELETED', 'file' => $path, 'desc' => 'File removed'];
        }
        return $changes;
    }

    /** @param list<array<string, mixed>> $records @return list<array<string, string|int>> */
    private function findingChanges(array $records): array {
        $changes = [];
        foreach ($records as $record) {
            $verdict = is_array($record['verdict'] ?? null) ? $record['verdict'] : [];
            $findings = is_array($verdict['findings'] ?? null) ? $verdict['findings'] : [];
            foreach ($findings as $finding) {
                if (!is_array($finding) || (int)($finding['risk'] ?? 0) < 50) continue;
                $changes[] = [
                    'type' => isset($record['quarantine']) ? 'QUARANTINED' : 'MALWARE',
                    'file' => (string)($record['file'] ?? 'unknown'),
                    'desc' => (string)($finding['code'] ?? 'MALWARE_FINDING'),
                    'risk' => (int)($finding['risk'] ?? 0),
                    'confidence' => (int)($finding['confidence'] ?? 0),
                ];
            }
        }
        return $changes;
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function assertManifestIdentity(array $old, array $new): void {
        $oldCount = count($old);
        $newCount = count($new);
        if ($oldCount < 20 || $newCount < 20) return;
        $shared = count(array_intersect_key($old, $new));
        $deletedRatio = ($oldCount - $shared) / $oldCount;
        $createdRatio = ($newCount - $shared) / $newCount;
        if ($deletedRatio < 0.75 || $createdRatio < 0.75) return;
        $oldHashes = array_column($old, 'hash');
        $newHashes = array_column($new, 'hash');
        $hashOverlap = count(array_intersect($oldHashes, $newHashes));
        $reason = ($hashOverlap / max(1, min($oldCount, $newCount))) >= 0.50
            ? 'Root remap detected.' : 'Foreign or incomplete filesystem snapshot detected.';
        throw new SecurityException('Integrity baseline path validation failed: ' . $reason);
    }

    /** @param array<string, mixed> $state */
    private function commitBaseline(array $state): void {
        $this->writeJson($this->manifestFile, $state);
        $persisted = $this->loadJson($this->manifestFile);
        $expectedJson = wp_json_encode($state, JSON_THROW_ON_ERROR);
        $persistedJson = wp_json_encode($persisted, JSON_THROW_ON_ERROR);
        if (!hash_equals(hash('sha256', $expectedJson), hash('sha256', $persistedJson))) {
            throw new StorageException('Integrity baseline read-back verification failed.');
        }
    }

    /** @return array<string, array{hash:string,mtime:int,size:int}> */
    private function loadResultState(): array {
        $state = [];
        foreach ($this->loadNdjson($this->resultFile) as $record) {
            $path = (string)($record['path'] ?? '');
            $hash = (string)($record['hash'] ?? '');
            if ($path === '' || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) continue;
            $state[$path] = ['hash' => $hash, 'mtime' => (int)($record['mtime'] ?? 0), 'size' => (int)($record['size'] ?? 0)];
        }
        ksort($state, SORT_STRING);
        return $state;
    }

    /** @return list<array<string, mixed>> */
    private function loadNdjson(string $file): array {
        if (!is_file($file)) return [];
        if ((filesize($file) ?: 0) > self::MAX_STATE_BYTES) throw new StorageException('Scanner state size boundary exceeded.');
        $handle = @fopen($file, 'rb');
        if (!is_resource($handle)) throw new StorageException('Scanner state unavailable.');
        $records = [];
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            try {
                $record = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                fclose($handle);
                throw new StorageException('Scanner state validation failed.');
            }
            if (is_array($record)) $records[] = $record;
        }
        fclose($handle);
        return $records;
    }

    /** @return array<string, mixed> */
    private function loadJson(string $file): array {
        if (!is_file($file)) return [];
        $size = filesize($file);
        if ($size === false || $size > self::MAX_STATE_BYTES) throw new StorageException('Scanner JSON size boundary exceeded.');
        $content = file_get_contents($file);
        if (!is_string($content) || $content === '') return [];
        try {
            $data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new StorageException('Scanner JSON validation failed.');
        }
        return is_array($data) ? $data : [];
    }

    private function writeJson(string $file, array $data): void {
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!is_string($json) || strlen($json) > self::MAX_STATE_BYTES) throw new StorageException('Scanner JSON boundary exceeded.');
        $temporary = $file . '.' . bin2hex(random_bytes(16)) . '.tmp';
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new StorageException('Scanner state atomic write failed.');
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $file)) {
            if (!@copy($temporary, $file)) {
                @unlink($temporary);
                throw new StorageException('Scanner state atomic commit failed.');
            }
            @unlink($temporary);
        }
        @chmod($file, 0600);
    }

    private function appendLine(string $file, string $line): void {
        if (str_contains($line, "\n") || str_contains($line, "\r") || str_contains($line, "\0")) {
            throw new SecurityException('Scanner queue record rejected.');
        }
        $currentSize = is_file($file) ? filesize($file) : 0;
        if ($currentSize === false || $currentSize + strlen($line) + 1 > self::MAX_STATE_BYTES) {
            throw new StorageException('Scanner append state boundary exceeded.');
        }
        if (file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new StorageException('Scanner queue append failed.');
        }
    }

    private function appendJsonRecord(string $file, array $record): void {
        $json = wp_json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!is_string($json) || strlen($json) > 65536) throw new StorageException('Scanner record boundary exceeded.');
        $this->appendLine($file, $json);
    }

    private function appendFindingRecord(array $record): void {
        $this->appendJsonRecord($this->findingFile, $record);
    }

    private function truncateFile(string $file): void {
        if (file_put_contents($file, '', LOCK_EX) === false) {
            throw new StorageException('Scanner state initialization failed.');
        }
        @chmod($file, 0600);
    }

    private function isUploadExecutable(string $relative, string $extension): bool {
        $normalized = strtolower(str_replace('\\', '/', $relative));
        return preg_match('~(?:^|/)wp-content/uploads(?:/|$)~', $normalized) === 1
            && in_array($extension, ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar'], true);
    }

    private function cleanupScanState(): void {
        foreach ([$this->queueFile, $this->resultFile, $this->findingFile, $this->indexStateFile, $this->cursorFile] as $file) {
            if (is_file($file) && !@unlink($file)) error_log('[VIS SCANNER] State cleanup failed.');
        }
    }

    private function ensureVaultExists(): bool {
        foreach ([$this->vaultDirectory, $this->stateDirectory] as $directory) {
            if (!is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
            @chmod($directory, 0700);
        }
        foreach ([$this->vaultDirectory . '/.htaccess' => "Require all denied\nDeny from all\n", $this->vaultDirectory . '/index.php' => "<?php\nexit;\n"] as $file => $content) {
            if (!is_file($file)) {
                @file_put_contents($file, $content, LOCK_EX);
            }
            @chmod($file, 0600);
        }
        return is_dir($this->vaultDirectory) && is_writable($this->vaultDirectory) && is_dir($this->stateDirectory) && is_writable($this->stateDirectory);
    }
}

if (!class_exists('VIS_Scanner_Engine', false)) {
    class_alias(VIS_Scanner_Engine_Omega::class, 'VIS_Scanner_Engine');
}
