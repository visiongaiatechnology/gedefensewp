<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Path_Context_Detector implements VIS_File_Detector {
    public function detect(string $path, VIS_Scan_Context $context, VIS_Scan_Budget $budget): array {
        $relative = strtolower(str_replace('\\', '/', $context->relativePath));
        $findings = [];

        if ($context->isExecutableExtension() && preg_match('~(?:^|/)wp-content/uploads(?:/|$)~', $relative) === 1) {
            $findings[] = new VIS_Scan_Finding('EXECUTABLE_IN_UPLOADS', 92, 96, 'Executable server-side code exists inside the media upload tree.');
        }
        if ($context->isExecutableExtension() && preg_match('~(?:^|/)(?:cache|tmp|backup|backups)(?:/|$)~', $relative) === 1) {
            $findings[] = new VIS_Scan_Finding('EXECUTABLE_IN_TRANSIENT_PATH', 72, 78, 'Executable code exists inside a transient or backup path.');
        }
        if (preg_match('~(?:^|/)(?:\.user\.ini|\.htaccess)$~', $relative) === 1) {
            $findings[] = new VIS_Scan_Finding('SENSITIVE_SERVER_POLICY_FILE', 35, 100, 'Sensitive server policy file requires content inspection.');
        }
        return $findings;
    }
}
