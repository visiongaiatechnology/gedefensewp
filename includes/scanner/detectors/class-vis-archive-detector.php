<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Archive_Detector implements VIS_File_Detector {
    public function detect(string $path, VIS_Scan_Context $context, VIS_Scan_Budget $budget): array {
        if ($context->extension !== 'zip' || !class_exists('ZipArchive')) return [];

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return [new VIS_Scan_Finding('ARCHIVE_PARSE_FAILURE', 75, 90, 'ZIP container could not be parsed.')];
        }

        $findings = [];
        $entries = $zip->numFiles;
        $totalCompressed = 0;
        $totalUncompressed = 0;
        if ($entries > $budget->maxArchiveEntries) {
            $findings[] = new VIS_Scan_Finding('ARCHIVE_ENTRY_LIMIT', 90, 98, 'Archive entry budget exceeded.', true);
        }

        for ($i = 0; $i < min($entries, $budget->maxArchiveEntries + 1); $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
            if (!is_array($stat)) continue;
            $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            $totalCompressed += max(0, (int)($stat['comp_size'] ?? 0));
            $totalUncompressed += max(0, (int)($stat['size'] ?? 0));

            if ($name === '' || str_starts_with($name, '/') || preg_match('~(?:^|/)\.\.(?:/|$)~', $name) === 1 || preg_match('/^[A-Za-z]:\//', $name) === 1) {
                $findings[] = new VIS_Scan_Finding('ARCHIVE_PATH_TRAVERSAL', 100, 99, 'Archive entry escapes the extraction root.', true);
                break;
            }
            if (preg_match('/\.(?:php[0-9]?|phtml|phar)$/i', $name) === 1) {
                $findings[] = new VIS_Scan_Finding('ARCHIVE_EXECUTABLE_PAYLOAD', 55, 100, 'Archive contains server-executable code requiring contextual review.');
            }
        }
        $zip->close();

        if ($totalUncompressed > $budget->maxArchiveUncompressedBytes
            || ($totalCompressed > 0 && ($totalUncompressed / $totalCompressed) > $budget->maxArchiveExpansionRatio)) {
            $findings[] = new VIS_Scan_Finding('ARCHIVE_EXPANSION_BOMB', 98, 99, 'Archive expansion exceeds the bounded extraction policy.', true);
        }
        return $findings;
    }
}
