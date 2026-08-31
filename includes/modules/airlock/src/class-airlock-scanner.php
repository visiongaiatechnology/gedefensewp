<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

require_once VIS_PATH . 'includes/scanner/class-vis-malware-engine.php';

final class VIS_Airlock_Scanner {

    private const CHUNK_SIZE = 8192;
    private VIS_Airlock_Config $config;

    public function __construct(VIS_Airlock_Config $config) {
        $this->config = $config;
    }

    public function execute_omega_scan(array $file): array {
        $path = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        $raw_name = isset($file['name']) && is_string($file['name']) ? basename($file['name']) : '';

        if ($path === '' || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return $file;
        if (!is_uploaded_file($path)) {
            $file['error'] = 'VGT_AIRLOCK_DENIED: Upload origin rejected.';
            return $file;
        }

        $max_size = $this->config->get_max_size_bytes();
        $realSize = filesize($path);
        if ($realSize === false || $realSize === 0 || $realSize > $max_size) {
            $mb_limit = $max_size / 1048576;
            $file['error'] = "VGT_AIRLOCK_DENIED: Payload exceeds hard limit of {$mb_limit}MB.";
            return $file;
        }

        $ext_parts = explode('.', $raw_name);
        $ext = strtolower(end($ext_parts));
        $allowed_map = $this->config->get_allowed_map();

        if (!array_key_exists($ext, $allowed_map)) {
            $file['error'] = 'VGT_AIRLOCK_DENIED: File extension not explicitly whitelisted.';
            return $file;
        }

        $real_mime = $this->resolve_mime_type($path, $ext);
        $expected_mime = $allowed_map[$ext];

        if (!$this->mime_matches($expected_mime, $real_mime)) {
            $file['error'] = "VGT_AIRLOCK_DENIED: MIME mismatch. Claimed: {$ext}, Real: {$real_mime}";
            return $file;
        }

        if (!$this->validate_image_type_constant($path, $ext, $real_mime)) {
            $file['error'] = 'VGT_AIRLOCK_DENIED: MIME/type mismatch. Polyglot vector blocked.';
            return $file;
        }

        if ($this->has_embedded_executable_payload($path)) {
            $file['error'] = 'VGT_AIRLOCK_DENIED: Embedded script execution payload detected.';
            return $file;
        }

        if ($ext === 'svg' && !$this->scan_svg($path)) {
            $file['error'] = 'VGT_AIRLOCK_DENIED: SVG contains active XSS/XML payloads.';
            return $file;
        }

        try {
            $context = new VIS_Scan_Context(
                VIS_Scan_Context::PROFILE_FAST_UPLOAD,
                'AIRLOCK',
                'wp-content/uploads/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $raw_name),
                $raw_name,
                $ext,
                $real_mime,
                $expected_mime,
                'UPLOAD'
            );
            $verdict = (new VIS_Malware_Engine())->scan($path, $context, VIS_Scan_Budget::fastUpload($max_size));
            if ($verdict->findings !== []) {
                $this->emitFinding($raw_name, $verdict);
            }
            if ($verdict->shouldBlock()) {
                $file['error'] = 'VGT_AIRLOCK_DENIED: Malware policy rejected the upload.';
                return $file;
            }
        } catch (ValidationException $e) {
            $file['error'] = $e->getMessage();
            return $file;
        } catch (SecurityException $e) {
            error_log('[SEC] ' . $e->getMessage());
            $file['error'] = 'VGT_AIRLOCK_DENIED: Request rejected for security reasons.';
            return $file;
        } catch (StorageException $e) {
            error_log('[STORAGE] ' . $e->getMessage());
            $file['error'] = 'VGT_AIRLOCK_DENIED: Scanner unavailable.';
            return $file;
        } catch (Throwable $e) {
            error_log('[FATAL] ' . $e->getMessage());
            $file['error'] = 'VGT_AIRLOCK_DENIED: Critical scanner fault.';
            return $file;
        }

        return $file;
    }

    private function emitFinding(string $name, VIS_Scan_Verdict $verdict): void {
        $safeName = substr(preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'upload', 0, 128);
        if (class_exists('VIS_Event_Bus')) {
            VIS_Event_Bus::emit('AIRLOCK', 'MALWARE_FINDING', 'Upload malware analysis produced findings.', [
                'file' => $safeName,
                'risk' => $verdict->risk,
                'confidence' => $verdict->confidence,
                'sha256' => $verdict->sha256,
            ], max(1, min(10, (int)ceil($verdict->risk / 10))));
        }
        if (class_exists('VIS_Trinity_Grid')) {
            $ip = class_exists('VIS_Security') ? VIS_Security::client_ip() : null;
            VIS_Trinity_Grid::onMalwareFinding('AIRLOCK', $safeName, $verdict->toArray(), $ip);
        }
    }

    private function has_embedded_executable_payload(string $path): bool {
        $handle = @fopen($path, 'rb');
        if (!$handle) return false;

        $overlap = 64;
        $prev_tail = '';
        $patterns = ['<?php', '<?=', '<script', 'eval(', 'assert(', '__halt_compiler()', 'passthru(', 'shell_exec('];

        while (!feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false || $chunk === '') break;

            $to_check = strtolower($prev_tail . $chunk);
            foreach ($patterns as $pat) {
                if (str_contains($to_check, $pat)) {
                    fclose($handle);
                    return true;
                }
            }
            $prev_tail = substr($chunk, -$overlap);
        }

        fclose($handle);
        return false;
    }

    private function scan_svg(string $path): bool {
        $svg_content = file_get_contents($path);
        if (empty($svg_content)) return false;
        if (strlen($svg_content) > $this->config->get_max_size_bytes()) return false;

        $blacklist = [
            '/<script\b/i', '/\son[a-z]+\s*=/i', '/javascript\s*:/i',
            '/<iframe\b/i', '/<object\b/i', '/<embed\b/i', '/<foreignObject\b/i',
            '/<!ENTITY/i', '/<!DOCTYPE/i', '/xlink:href\s*=\s*[\'"]\s*(?:javascript:|data:)/i'
        ];

        foreach ($blacklist as $pattern) {
            if (preg_match($pattern, $svg_content)) return false;
        }

        if (class_exists('DOMDocument')) {
            $previous = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loaded = $dom->loadXML($svg_content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (!$loaded || strtolower($dom->documentElement?->tagName ?? '') !== 'svg') {
                return false;
            }
        }

        return true;
    }

    private function mime_matches(string $expected_mime, string $real_mime): bool {
        $expected = array_map('trim', explode(',', strtolower($expected_mime)));
        return in_array(strtolower($real_mime), $expected, true);
    }

    private function validate_image_type_constant(string $path, string $ext, string $detectedMime): bool {
        $expectedType = match($detectedMime) {
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png'  => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
            'image/gif'  => IMAGETYPE_GIF,
            default      => null,
        };

        if ($expectedType === null) {
            return true;
        }

        $imageInfo = @getimagesize($path);
        return is_array($imageInfo) && isset($imageInfo[2]) && $imageInfo[2] === $expectedType;
    }

    private function resolve_mime_type(string $path, string $ext) {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if ($mime !== false && $mime !== 'application/x-empty') return $mime;
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if ($mime !== false) return $mime;
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $img_info = @getimagesize($path);
            if (!empty($img_info['mime'])) return $img_info['mime'];
        }

        $handle = @fopen($path, 'rb');
        if ($handle) {
            $magic = fread($handle, 1024);
            fclose($handle);
            
            if ($ext === 'pdf' && strncmp($magic, '%PDF-', 5) === 0) return 'application/pdf';
            if ($ext === 'zip' && strncmp($magic, "PK\x03\x04", 4) === 0) return 'application/zip';
            if ($ext === 'svg' && stripos($magic, '<svg') !== false) return 'image/svg+xml';
        }

        return 'application/octet-stream';
    }
}
