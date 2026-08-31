<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

interface VIS_File_Detector {
    /** @return list<VIS_Scan_Finding> */
    public function detect(string $path, VIS_Scan_Context $context, VIS_Scan_Budget $budget): array;
}
