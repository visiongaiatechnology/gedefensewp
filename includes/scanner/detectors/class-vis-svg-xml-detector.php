<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Svg_Xml_Detector implements VIS_File_Detector {
    public function detect(string $path, VIS_Scan_Context $context, VIS_Scan_Budget $budget): array {
        if ($context->extension !== 'svg' && $context->detectedMime !== 'image/svg+xml') return [];
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > $budget->maxBytes) {
            return [new VIS_Scan_Finding('SVG_SIZE_BOUNDARY', 80, 98, 'SVG payload exceeds the safe parsing boundary.')];
        }
        $content = file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return [new VIS_Scan_Finding('SVG_PARSE_FAILURE', 75, 90, 'SVG payload could not be read.')];
        }
        $patterns = [
            '/<script\b/i', '/\son[a-z]+\s*=/i', '/javascript\s*:/i', '/<iframe\b/i',
            '/<object\b/i', '/<embed\b/i', '/<foreignObject\b/i', '/<!ENTITY/i',
            '/<!DOCTYPE/i', '/xlink:href\s*=\s*[\'\"]\s*(?:javascript:|data:)/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return [new VIS_Scan_Finding('ACTIVE_SVG_PAYLOAD', 96, 98, 'SVG contains active scripting or external entity behavior.', true)];
            }
        }
        if (class_exists('DOMDocument')) {
            $previous = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (!$loaded || strtolower((string)($dom->documentElement?->tagName ?? '')) !== 'svg') {
                return [new VIS_Scan_Finding('SVG_PARSE_FAILURE', 75, 90, 'SVG document structure is invalid.')];
            }
        }
        return [];
    }
}
