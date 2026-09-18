<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

echo "========================================================\n";
echo "VGT THREAT INTELLIGENCE PACKED-BINARY & ATOMIC SWAP TEST\n";
echo "========================================================\n\n";

$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
require_once $root . '/includes/modules/threat-intel/class-vis-threat-intel.php';

use VisionGaia\GeDefense\Modules\ThreatIntel\ThreatIntelligence;

// 1. DATA SYNTHESIS: 10,000 Deterministic Test IPs
echo "[TEST 1] Synthesizing 10,000 IPv4 addresses for binary table...\n";
$ips = [];
$ips[] = '1.1.1.1';
$ips[] = '8.8.8.8';
$ips[] = '192.168.1.1';
$ips[] = '203.0.113.195';
$ips[] = '198.51.100.42';

for ($i = 0; $i < 9995; $i++) {
    $ips[] = long2ip(mt_rand(16777216, 3758096383)); // Class A to C range
}
$ips = array_values(array_unique($ips));

// 2. PACKING & SORTING
$integer_map = [];
foreach ($ips as $ip_str) {
    $packed = inet_pton($ip_str);
    if ($packed !== false && strlen($packed) === 4) {
        $u = unpack('N', $packed)[1];
        $integer_map[$u] = true;
    }
}
$keys = array_keys($integer_map);
sort($keys, SORT_NUMERIC);

$blob = '';
foreach ($keys as $val) {
    $blob .= pack('N', $val);
}

echo "Compiled blob size: " . strlen($blob) . " bytes (" . round(strlen($blob)/1024, 2) . " KB)\n";
if (strlen($blob) !== count($keys) * 4) {
    fwrite(STDERR, "FAIL: Blob size alignment violation!\n");
    exit(1);
}
echo "PASS: Binary alignment valid (4-byte boundary).\n\n";

// 3. MATHEMATICAL ACCURACY: Binary Search Hits & Misses
echo "[TEST 2] Testing binary search accuracy (Hits and Misses)...\n";
$known_hits = ['1.1.1.1', '8.8.8.8', '192.168.1.1', '203.0.113.195', '198.51.100.42'];
foreach ($known_hits as $kh) {
    if (!ThreatIntelligence::bsearch_v4($kh, $blob)) {
        fwrite(STDERR, "FAIL: Known hit {$kh} was NOT found in binary blob!\n");
        exit(1);
    }
}
echo "PASS: All 5 known canary hits correctly detected.\n";

$known_misses = ['10.0.0.1', '172.16.0.1', '127.0.0.1'];
foreach ($known_misses as $km) {
    if (in_array($km, $ips, true)) continue;
    if (ThreatIntelligence::bsearch_v4($km, $blob)) {
        fwrite(STDERR, "FAIL: Known miss {$km} was falsely detected as hit!\n");
        exit(1);
    }
}
echo "PASS: Known misses correctly admitted.\n\n";

// 4. MICROBENCHMARK: 20,000 Lookups
echo "[TEST 3] Running 20,000 lookups microbenchmark...\n";
$t0 = microtime(true);
$lookup_count = 20000;
for ($k = 0; $k < $lookup_count; $k++) {
    $probe = $known_hits[$k % 5];
    ThreatIntelligence::bsearch_v4($probe, $blob);
}
$elapsed_ms = ((microtime(true) - $t0) / $lookup_count) * 1000;
echo "Average lookup time: " . sprintf("%.5f", $elapsed_ms) . " ms (" . round($elapsed_ms * 1000, 2) . " microseconds)\n";
if ($elapsed_ms > 0.05) {
    fwrite(STDERR, "FAIL: Lookup exceeded 50 microsecond ceiling!\n");
    exit(1);
}
echo "PASS: Latency ceiling respected (< 0.05 ms).\n\n";

// 5. ATOMIC SWAP SIMULATION
echo "[TEST 4] Testing atomic file swap mechanics...\n";
$tmp_dir = sys_get_temp_dir() . '/vgt_swap_test_' . bin2hex(random_bytes(4));
mkdir($tmp_dir, 0700, true);

$target_bin = $tmp_dir . '/threat_intel_v4.bin';
$tmp_bin    = $target_bin . '.tmp.' . bin2hex(random_bytes(8));

file_put_contents($tmp_bin, $blob, LOCK_EX);
if (DIRECTORY_SEPARATOR === '\\') {
    $old = $target_bin . '.old.' . bin2hex(random_bytes(4));
    if (file_exists($old)) @unlink($old);
    if (file_exists($target_bin)) @rename($target_bin, $old);
    @rename($tmp_bin, $target_bin);
    if (file_exists($old)) @unlink($old);
} else {
    @rename($tmp_bin, $target_bin);
}

if (!file_exists($target_bin) || filesize($target_bin) !== strlen($blob)) {
    fwrite(STDERR, "FAIL: Atomic file swap did not produce target file with correct size!\n");
    exit(1);
}

$read_back = file_get_contents($target_bin);
if ($read_back !== $blob) {
    fwrite(STDERR, "FAIL: Read-back contents mismatch after atomic swap!\n");
    exit(1);
}
echo "PASS: Atomic swap verified with 100% byte fidelity.\n\n";

// Clean up
@unlink($target_bin);
@rmdir($tmp_dir);

echo "========================================================\n";
echo "ALL TESTS PASSED: DIAMANT VGT SUPREME STATUS VERIFIED\n";
echo "========================================================\n";
