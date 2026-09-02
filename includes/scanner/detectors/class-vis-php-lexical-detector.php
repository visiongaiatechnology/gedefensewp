<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Php_Lexical_Detector implements VIS_File_Detector {
    private const MAX_ANALYSIS_BYTES = 1048576; // 1 MB scan window per file

    public function detect(string $path, VIS_Scan_Context $context, VIS_Scan_Budget $budget): array {
        $content = $this->readBounded($path, min($budget->maxBytes, self::MAX_ANALYSIS_BYTES));
        if ($content === '') return [];

        return $this->analyzeContent($content, $context);
    }

    /** @return list<VIS_Scan_Finding> */
    public function analyzeContent(string $content, VIS_Scan_Context $context): array {
        $relPath = strtolower(str_replace('\\', '/', $context->relativePath));

        // Skip internal scanner pattern definition files to avoid signature self-matching
        if (str_contains($relPath, 'includes/scanner/')) {
            return [];
        }

        $hasPhp = stripos($content, '<?php') !== false || str_contains($content, '<?=');
        $isViewTemplate = preg_match('~(?:^|/)(?:views|templates|tpl)/~', $relPath) === 1;

        if (!$hasPhp && !$context->isExecutableExtension()) {
            return $this->detectKnownMarkers($content);
        }

        $findings = $this->detectKnownMarkers($content);

        // Flag PHP tags in non-executable files (unless it is an internal view template)
        if ($hasPhp && !$context->isExecutableExtension() && !$isViewTemplate) {
            $findings[] = new VIS_Scan_Finding('EMBEDDED_PHP_PAYLOAD', 98, 99, 'PHP execution payload is embedded in a non-PHP file.', true);
        }

        // Memory Pre-Flight Check: Ensure sufficient memory before complex regex execution
        $freeMem = $this->getAvailableMemory();
        if ($freeMem < 8388608) { // Less than 8MB free RAM
            // Safe fallback: Return findings gathered so far to prevent fatal OOM
            return $findings;
        }

        $execPats = '(?:' . 'eval|' . 'assert|' . 'system|' . 'exec|' . 'shell_exec|' . 'passthru|' . 'popen|' . 'proc_open)';
        $decPats  = '(?:' . 'base64_decode|' . 'gzinflate|' . 'gzuncompress|' . 'str_rot13|' . 'hex2bin|' . 'convert_uudecode)';
        $inputPats = '\\$_' . '(?:GET|POST|REQUEST|COOKIE|SERVER)';
        $writePats = '(?:' . 'file_put_contents|' . 'fwrite|' . 'fputs)';

        // 1. Direct Chained Decode-Execution (e.g. eval(base64_decode(...)), assert(gzinflate(...)))
        $chainRegex = '/\\b' . $execPats . '\\s*\\(\\s*' . $decPats . '\\s*\\(/i';
        if (preg_match($chainRegex, $content) === 1) {
            $findings[] = new VIS_Scan_Finding('DECODE_EXECUTION_CHAIN', 98, 96, 'Decoded content flows directly into dynamic execution primitive.', true);
        }

        // 2. Direct Remote Execution Flow (e.g. eval($_POST[...]), system($_GET[...]), eval(base64_decode($_POST[...])), $_POST['cmd'](...))
        $rceRegex1 = '/\\b' . $execPats . '\\s*\\([^;]*?' . $inputPats . '\\b/i';
        $rceRegex2 = '/\\$' . '_(?:GET|POST|REQUEST|COOKIE)\\s*\\[[^\]]+\\]\\s*\\(/i';
        if (preg_match($rceRegex1, $content) === 1 || preg_match($rceRegex2, $content) === 1) {
            $findings[] = new VIS_Scan_Finding('REMOTE_EXECUTION_FLOW', 99, 96, 'External request data flows directly into dynamic execution primitive.', true);
        }

        // 3. Direct Remote File Dropper Flow (e.g. file_put_contents($path, $_POST['data']), file_put_contents($_GET['name'].'.php', $_POST['body']))
        $dropperRegex = '/\\b' . $writePats . '\\s*\\([^;]*?' . $inputPats . '\\b/i';
        $uploadRegex = '/\\b' . 'move_uploaded_file\\s*\\([^,]+,\\s*[^;]*\\.php/i';
        if (preg_match($dropperRegex, $content) === 1
            || (preg_match($uploadRegex, $content) === 1 && !str_contains($relPath, 'airlock'))) {
            $findings[] = new VIS_Scan_Finding('REMOTE_FILE_DROPPER_FLOW', 97, 92, 'External request data flows directly into executable file creation.', true);
        }

        // 4. Obfuscated Dynamic Function Calls
        $dynCallRegex = '/(?:\\$[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*)\\s*=\\s*' . $decPats . '\\s*\\([^;]+\\);\\s*\\$[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*\\s*\\(/i';
        if (preg_match($dynCallRegex, $content) === 1) {
            $findings[] = new VIS_Scan_Finding('OBFUSCATED_DYNAMIC_CALL', 88, 84, 'Decoded data and variable function execution occur together in sequence.', true);
        }

        // 5. High Entropy Executable Payload Check
        $highEntropyExec = '/\\b' . $execPats . '\\b/i';
        if ($this->hasHighEntropyPayload($content) && preg_match($highEntropyExec, $content) === 1) {
            $findings[] = new VIS_Scan_Finding('HIGH_ENTROPY_EXECUTABLE_BLOB', 82, 78, 'High-entropy content is combined with execution primitives.');
        }

        return $findings;
    }

    /** @return list<VIS_Scan_Finding> */
    private function detectKnownMarkers(string $content): array {
        $lower = strtolower($content);
        $markers = [
            'c99' . 'shell',
            'r57' . 'shell',
            'wso' . ' ' . 'shell',
            'files' . 'man',
            'b374' . 'k',
            'indox' . 'ploit',
        ];
        foreach ($markers as $marker) {
            if (str_contains($lower, $marker)) {
                return [new VIS_Scan_Finding('KNOWN_WEBSHELL_MARKER', 100, 99, 'Known webshell family marker detected.', true)];
            }
        }
        return [];
    }

    private function readBounded(string $path, int $maxBytes): string {
        $fileSize = @filesize($path);
        if ($fileSize === false || $fileSize === 0) return '';

        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) return '';

        // If file is within boundary, stream read it up to maxBytes
        if ($fileSize <= $maxBytes) {
            $content = '';
            while (!feof($handle) && strlen($content) < $maxBytes) {
                $chunk = fread($handle, min(65536, $maxBytes - strlen($content)));
                if (!is_string($chunk) || $chunk === '') break;
                $content .= $chunk;
            }
            fclose($handle);
            return $content;
        }

        // Large file optimization: read first 512KB and last 512KB to catch injected webshells/payloads
        $half = (int) floor($maxBytes / 2);
        $head = fread($handle, $half);
        $head = is_string($head) ? $head : '';

        $tail = '';
        if (@fseek($handle, -$half, SEEK_END) === 0) {
            $tail = fread($handle, $half);
            $tail = is_string($tail) ? $tail : '';
        }
        fclose($handle);

        return $head . "\n/* ... VGT_STREAM_GAP ... */\n" . $tail;
    }

    private function hasHighEntropyPayload(string $content): bool {
        if (preg_match_all('/[A-Za-z0-9+\/=]{256,}/', $content, $matches) < 1) return false;
        foreach (array_slice($matches[0], 0, 8) as $candidate) {
            $length = strlen($candidate);
            if ($length === 0) continue;
            $counts = count_chars($candidate, 1);
            $entropy = 0.0;
            foreach ($counts as $count) {
                $probability = $count / $length;
                $entropy -= $probability * log($probability, 2);
            }
            if ($entropy >= 5.8) return true;
        }
        return false;
    }

    private function getAvailableMemory(): int {
        $limitStr = ini_get('memory_limit');
        if (!$limitStr || $limitStr === '-1') return 268435456; // 256MB default if unconstrained

        $last = strtolower(substr($limitStr, -1));
        $val = (int) $limitStr;
        switch ($last) {
            case 'g': $val *= 1024 * 1024 * 1024; break;
            case 'm': $val *= 1024 * 1024; break;
            case 'k': $val *= 1024; break;
        }
        $used = memory_get_usage(true);
        return max(0, $val - $used);
    }
}
