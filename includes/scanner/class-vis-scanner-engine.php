<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('ACCESS DENIED: VISIONGAIATECHNOLOGY OMEGA PROTOCOL');

/**
 * VISIONGAIATECHNOLOGY ENGINE: SCANNER CORE (PLATINUM ARCHITECTURE)
 * Status: OMEGA HARDENED V6.2 (STRICT MODE + FAULT TOLERANCE + PATH NORMALIZATION)
 * Optimization: JSON Storage Engine for High-Performance I/O
 * * LOGIC RETENTION PROTOCOL: ACTIVE
 * - Scannen & Reindex Logik unverändert.
 * - Dashboard Kompatibilität: 100%.
 */
class VIS_Scanner_Engine_Omega {

    // CONFIGURATION
    private array $exclude_dirs = [
        'node_modules', '.git', 'cache', 'upgrade', 'languages', 'vis-vault-omega', 'tmp_vis_states', 'updraft', 'backups', 'wprocket'
    ];
    
    // Performance: O(1) Lookup Map für Extensions
    private array $monitored_extensions = [
        'php' => true, 'php5' => true, 'phtml' => true, 'html' => true, 
        'htm' => true, 'js' => true, 'htaccess' => true, 'py' => true, 'pl' => true
    ];
    
    private int $time_limit = 5; 
    private int $batch_size = 500; 
    private float $start_time;

    // PATHS
    private string $vault_dir;
    private string $manifest_file;
    private string $queue_file;
    private string $result_file;

    public function __construct() {
        // High-Priority Execution
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M'); // Temporärer Boost für große Dateisysteme
            @ini_set('max_execution_time', '30');
        }

        $this->start_time = microtime(true);
        $upload_dir = wp_upload_dir();
        
        // Hardening: Pfade normalisieren & Trailing Slash Konsistenz
        $this->vault_dir = wp_normalize_path($upload_dir['basedir'] . '/vis-vault-omega');
        
        // UPGRADE: JSON statt PHP-Array für Performance & Sicherheit
        $this->manifest_file = $this->vault_dir . '/integrity_matrix.json';
        
        $temp_dir = $this->vault_dir . '/tmp_vis_states';
        if (!is_dir($temp_dir)) {
            @mkdir($temp_dir, 0755, true);
            // Security: Temp Dir absichern
            @file_put_contents($temp_dir . '/index.php', '<?php // Silence is golden');
            @file_put_contents($temp_dir . '/.htaccess', 'Deny from all');
        }
        
        $this->queue_file = $temp_dir . '/scan_queue.json';
        $this->result_file = $temp_dir . '/current_scan.json';
    }

    /**
     * MASTER CONTROLLER (PLATINUM PROTOCOL)
     */
    public function run_scan_cycle(string $phase = 'init', int $offset = 0, string $mode = 'scan'): array {
        if (!$this->ensure_vault_exists()) {
            return ['status' => 'error', 'message' => 'Vault Directory critical permission error.'];
        }

        // Logic Override Check (Sichert Fallback falls Mode POST Parameter abweicht)
        // Härtung: Nur erlaubte Modi zulassen
        $mode = ($mode === 'reindex') ? 'reindex' : 'scan';
        if ($mode === 'scan' && isset($_POST['mode']) && $_POST['mode'] === 'reindex') {
            $mode = 'reindex';
        }

        try {
            return match ($phase) {
                'init'      => $this->phase_indexing($mode),
                'process'   => $this->phase_processing($offset, $mode),
                'finalize'  => $this->phase_finalization($mode),
                default     => throw new ValidationException('Invalid scanner phase.'),
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

    /**
     * PHASE 1: INDEXING
     * Rekursiver Scan des Dateisystems.
     */
    private function phase_indexing(string $mode): array {
        // Cleanup old states
        if (file_exists($this->queue_file)) @unlink($this->queue_file);
        if (file_exists($this->result_file)) @unlink($this->result_file);
        
        // Initialize empty result set (Reset State)
        $this->write_json($this->result_file, []);

        $root = wp_normalize_path(ABSPATH);
        // Härtung: Trailing Slash erzwingen für konsistente substr Berechnung
        $root = rtrim($root, '/') . '/'; 
        
        // Validierung des Roots
        if (!is_dir($root) || !is_readable($root)) {
            throw new Exception("System Root unreachable: $root");
        }

        $file_list = [];
        $count = 0;
        
        // Performance: Pfad-Längen vorab berechnen
        $root_len = strlen($root);

        try {
            // HARDENING: Exception Handling für Directory Access
            $directory = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
            $iterator = new RecursiveIteratorIterator($directory);

            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                // Hard Time Limit Check (etwas strikter im Loop)
                if ((microtime(true) - $this->start_time) > 10) break; 
                
                if (!$file->isFile()) continue;

                $path = wp_normalize_path($file->getPathname());
                
                // Optimized Exclusion Logic
                foreach ($this->exclude_dirs as $ex) {
                    // Härtung: Striker Check mit Slashes, um "cache_me" vs "cache" zu unterscheiden
                    if (strpos($path, '/' . $ex . '/') !== false) continue 2;
                }

                $ext = strtolower($file->getExtension());
                if (!isset($this->monitored_extensions[$ext])) continue;

                // Relativer Pfad: Sicherstellen, dass er nicht mit / beginnt, wenn root Slash hat
                // Durch $root = rtrim... . '/' ist root_len inkl. Slash. Pfad wird relativ ohne Slash.
                $rel_path = substr($path, $root_len);
                
                if ($rel_path !== false && $rel_path !== '') {
                    $file_list[] = $rel_path;
                    $count++;
                }
            }
        } catch (UnexpectedValueException $e) {
            // Fängt Permissions Fehler ab, die den Scan sonst töten würden
            error_log("VIS SCANNER WARNING: Directory Access Denied during Indexing - " . $e->getMessage());
        } catch (Exception $e) {
            throw $e;
        }

        // Speichern der Queue
        $this->write_json($this->queue_file, $file_list);

        return [
            'status'  => 'next_phase',
            'phase'   => 'process',
            'offset'  => 0,
            'mode'    => $mode, 
            'total'   => $count,
            'message' => ($mode === 'reindex') ? "Re-Indexing System Core..." : "Initializing Integrity Scan..."
        ];
    }

    /**
     * PHASE 2: PROCESSING
     * Hashing und Analyse in Batches.
     */
    private function phase_processing(int $offset, string $mode): array {
        if (!file_exists($this->queue_file)) {
            return ['status' => 'error', 'message' => 'Logic Error: Queue lost. Restart required.'];
        }

        // HARDENING: Strict Type Check für file_get_contents
        $queue_data = @file_get_contents($this->queue_file);
        if ($queue_data === false) {
             return ['status' => 'error', 'message' => 'I/O Error: Cannot read queue.'];
        }

        $queue = json_decode($queue_data, true);
        
        if (!is_array($queue)) {
             return ['status' => 'error', 'message' => 'Queue Corruption Detected.'];
        }

        $total = count($queue);

        if ($offset >= $total) {
            return [
                'status'  => 'next_phase', 
                'phase'   => 'finalize', 
                'offset'  => 0,
                'mode'    => $mode,
                'message' => 'Analysis complete. Calculating Logic Delta...'
            ];
        }

        $batch = array_slice($queue, $offset, $this->batch_size);
        $root = wp_normalize_path(ABSPATH);
        $root = rtrim($root, '/') . '/'; // Konsistenz mit Indexing Phase
        
        // Load partial states
        $current_results = $this->load_json($this->result_file);
        $processed_in_batch = 0;

        foreach ($batch as $rel_path) {
            if ((microtime(true) - $this->start_time) > $this->time_limit) break;

            $abs_path = $root . $rel_path;
            
            // Stat Cache clearen für präzise Werte
            clearstatcache(true, $abs_path);
            
            if (file_exists($abs_path)) {
                $mtime = filemtime($abs_path);
                $size = filesize($abs_path);
                
                // Integrity is content-derived. mtime and size are attacker-
                // controllable metadata and therefore never authorize hash reuse.
                $hash_calc = @hash_file('sha256', $abs_path);
                $hash = ($hash_calc === false) ? 'UNREADABLE_FILE_LOCK' : $hash_calc;

                $current_results[$rel_path] = [
                    'hash'  => $hash,
                    'mtime' => $mtime,
                    'size'  => $size
                ];
            }
            $processed_in_batch++;
        }

        // Speichern der Zwischenergebnisse
        $this->write_json($this->result_file, $current_results);
        
        // Memory cleanup
        unset($queue, $current_results, $batch);
        gc_collect_cycles(); // Zwingende Garbage Collection

        $new_offset = $offset + $processed_in_batch;
        $percent = ($total > 0) ? round(($new_offset / $total) * 100) : 100;

        return [
            'status'   => 'processing',
            'phase'    => 'process',
            'offset'   => $new_offset,
            'mode'     => $mode,
            'progress' => $percent,
            'message'  => ($mode === 'reindex') ? "Updating Neural Baseline... {$percent}%" : "Deep Scanning... {$percent}%"
        ];
    }

    /**
     * PHASE 3: FINALIZATION
     * Vergleich und Reporting.
     */
    private function phase_finalization(string $mode): array {
        $baseline = $this->load_json($this->manifest_file);
        $new_state = $this->load_json($this->result_file);
        
        $report = [];

        if ($mode === 'reindex') {
            // MODE: REINDEX -> verified overwrite. Findings are invalidated
            // only after the fresh baseline survived a read-back check.
            $this->write_json($this->manifest_file, $new_state);
            $this->assert_baseline_commit($new_state);
            $report = [
                'status'    => 'clean',
                'message'   => 'Baseline successfully re-calibrated.',
                'changes'   => [],
                'timestamp' => current_time('mysql'),
                'baseline'  => hash('sha256', wp_json_encode($new_state, JSON_THROW_ON_ERROR)),
            ];
        } else {
            // MODE: SCAN -> COMPARE
            if (empty($baseline)) {
                // First Run
                $this->write_json($this->manifest_file, $new_state);
                $report = [
                    'status'    => 'init', 
                    'message'   => 'Initial System Baseline established.',
                    'changes'   => [],
                    'timestamp' => current_time('mysql')
                ];
            } else {
                $this->assert_manifest_identity($baseline, $new_state);
                $diff = $this->compare_manifests($baseline, $new_state);
                
                if (empty($diff)) {
                    // Update mtimes in background to prevent drift on next scan
                    if (!empty($new_state)) {
                         $this->write_json($this->manifest_file, $new_state); 
                    }
                    
                    $report = [
                        'status'    => 'clean',
                        'message'   => 'System Integrity Verified. Status: GREEN.',
                        'changes'   => [],
                        'timestamp' => current_time('mysql')
                    ];
                } else {
                    $report = [
                        'status'    => 'warning',
                        'message'   => 'SECURITY ALERT: Integrity Mismatch Detected.',
                        'changes'   => $diff,
                        'timestamp' => current_time('mysql')
                    ];
                }
            }
        }

        wp_cache_delete('vis_scan_report', 'options');
        $updated = update_option('vis_scan_report', $report, false);
        if (!$updated && get_option('vis_scan_report', null) !== $report) {
            throw new StorageException('Integrity report persistence failed.');
        }
        wp_cache_delete('vis_scan_report', 'options');

        // Secure Cleanup
        if (file_exists($this->queue_file)) @unlink($this->queue_file);
        if (file_exists($this->result_file)) @unlink($this->result_file);

        return $report;
    }

    // --- HELPER KERNEL ---

    private function compare_manifests(array $old, array $new): array {
        $changes = [];
        // Check Modified & New
        foreach ($new as $path => $data) {
            if (!isset($old[$path])) {
                $changes[] = ['type' => 'NEW', 'file' => $path, 'desc' => 'New File Detected'];
            } elseif (($old[$path]['hash'] ?? '') !== $data['hash']) {
                $changes[] = ['type' => 'MODIFIED', 'file' => $path, 'desc' => 'Content Hash Mismatch'];
            }
        }
        // Check Deleted
        foreach ($old as $path => $data) {
            if (!isset($new[$path])) {
                $changes[] = ['type' => 'DELETED', 'file' => $path, 'desc' => 'File Removed'];
            }
        }
        return $changes;
    }

    /** Blocks the characteristic global delete/new false-positive before reporting or committing. */
    private function assert_manifest_identity(array $old, array $new): void {
        $old_count = count($old);
        $new_count = count($new);
        if ($old_count < 20 || $new_count < 20) return;

        $shared = count(array_intersect_key($old, $new));
        $deleted_ratio = ($old_count - $shared) / $old_count;
        $created_ratio = ($new_count - $shared) / $new_count;
        if ($deleted_ratio < 0.75 || $created_ratio < 0.75) return;

        $old_hashes = array_column($old, 'hash');
        $new_hashes = array_column($new, 'hash');
        $hash_overlap = count(array_intersect($old_hashes, $new_hashes));
        $overlap_ratio = $hash_overlap / max(1, min($old_count, $new_count));
        $reason = $overlap_ratio >= 0.50 ? 'Root/path remap detected.' : 'Incomplete or foreign filesystem snapshot detected.';
        throw new RuntimeException('Integrity baseline identity guard: ' . $reason);
    }

    private function assert_baseline_commit(array $expected): void {
        $persisted = $this->load_json($this->manifest_file);
        $expected_json = wp_json_encode($expected, JSON_THROW_ON_ERROR);
        $persisted_json = wp_json_encode($persisted, JSON_THROW_ON_ERROR);

        if (!hash_equals(hash('sha256', $expected_json), hash('sha256', $persisted_json))) {
            throw new StorageException('Integrity baseline read-back verification failed.');
        }
    }

    /**
     * ATOMIC JSON WRITER
     * Verhindert Dateikorruption bei Concurrent Requests.
     */
    private function write_json(string $file, array $data): void {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        
        $temp = $file . '.tmp.' . bin2hex(random_bytes(16));
        
        // Locking Mechanism: EX + Sync
        if (@file_put_contents($temp, $json, LOCK_EX) !== false) {
            // Atomic Switch
            if (@rename($temp, $file)) {
                // Ensure Permissions
                if (!@chmod($file, 0600)) {
                    throw new StorageException('Integrity artifact permission enforcement failed.');
                }
                return;
            }
        }
        @unlink($temp); // Cleanup on fail
        throw new StorageException('Integrity artifact persistence failed.');
    }

    private function load_json(string $file): array {
        if (file_exists($file)) {
            $content = @file_get_contents($file); // @ to suppress warnings
            if ($content) {
                $data = json_decode($content, true);
                return is_array($data) ? $data : [];
            }
        }
        return [];
    }

    private function ensure_vault_exists(): bool {
        if (!is_dir($this->vault_dir)) {
            @mkdir($this->vault_dir, 0755, true);
            @file_put_contents($this->vault_dir . '/.htaccess', "Order Deny,Allow\nDeny from all");
            @file_put_contents($this->vault_dir . '/index.html', ''); // Silence
        }
        return is_writable($this->vault_dir);
    }
}

if (!class_exists('VIS_Scanner_Engine', false)) {
    class_alias(VIS_Scanner_Engine_Omega::class, 'VIS_Scanner_Engine');
}
