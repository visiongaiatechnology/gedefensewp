<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Polyglot_Detector implements VIS_File_Detector {
    private const IMAGE_TYPES = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/gif' => IMAGETYPE_GIF,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    public function detect(string $path, VIS_Scan_Context $context, VIS_Scan_Budget $budget): array {
        $findings = [];
        if ($context->expectedMime !== null && !$this->mimeMatches($context->expectedMime, $context->detectedMime)) {
            $findings[] = new VIS_Scan_Finding('MIME_EXTENSION_MISMATCH', 90, 98, 'Detected MIME type violates the extension policy.', true);
        }

        $expectedType = self::IMAGE_TYPES[$context->detectedMime] ?? null;
        if ($expectedType !== null) {
            $imageInfo = @getimagesize($path);
            if (!is_array($imageInfo) || !isset($imageInfo[2]) || $imageInfo[2] !== $expectedType) {
                $findings[] = new VIS_Scan_Finding('IMAGE_TYPE_POLYGLOT', 96, 98, 'Independent image type validation rejected the payload.', true);
            }
        }

        $head = $this->readHead($path, 4096);
        if ($context->detectedMime === 'application/pdf' && !str_starts_with($head, '%PDF-')) {
            $findings[] = new VIS_Scan_Finding('INVALID_PDF_MAGIC', 88, 98, 'PDF magic bytes are missing.', true);
        }
        if ($context->detectedMime === 'application/zip' && !str_starts_with($head, "PK\x03\x04") && !str_starts_with($head, "PK\x05\x06")) {
            $findings[] = new VIS_Scan_Finding('INVALID_ZIP_MAGIC', 88, 98, 'ZIP magic bytes are missing.', true);
        }
        return $findings;
    }

    private function mimeMatches(string $expected, string $actual): bool {
        return in_array(strtolower($actual), array_map('trim', explode(',', strtolower($expected))), true);
    }

    private function readHead(string $path, int $bytes): string {
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) return '';
        $head = fread($handle, $bytes);
        fclose($handle);
        return is_string($head) ? $head : '';
    }
}
