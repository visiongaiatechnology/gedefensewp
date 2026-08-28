<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

/**
 * MODULE: FILESYSTEM GUARD (Datensicherheit)
 * Scanned Datei-Rechte und vergleicht sie mit Soll-Werten.
 */
class VIS_Filesystem_Guard {

    private $critical_paths = [
        ['path' => '/',                     'type' => 'dir',  'rec' => '0755', 'label' => 'Root Directory'],
        ['path' => 'wp-includes/',          'type' => 'dir',  'rec' => '0755', 'label' => 'WP Core Includes'],
        ['path' => '.htaccess',             'type' => 'file', 'rec' => '0644', 'label' => 'Server Config (.htaccess)'],
        ['path' => 'wp-admin/index.php',    'type' => 'file', 'rec' => '0644', 'label' => 'Admin Entry Point'],
        ['path' => 'wp-admin/js/',          'type' => 'dir',  'rec' => '0755', 'label' => 'Admin Assets'],
        ['path' => 'wp-content/themes/',    'type' => 'dir',  'rec' => '0755', 'label' => 'Theme Directory'],
        ['path' => 'wp-content/plugins/',   'type' => 'dir',  'rec' => '0755', 'label' => 'Plugin Directory'],
        ['path' => 'wp-admin/',             'type' => 'dir',  'rec' => '0755', 'label' => 'WP Admin Area'],
        ['path' => 'wp-content/',           'type' => 'dir',  'rec' => '0755', 'label' => 'Content Area'],
        ['path' => 'wp-config.php',         'type' => 'file', 'rec' => '0400', 'label' => 'WP Config (Critical)']
    ];

    public function scan_permissions() {
        $results = [];
        $root = ABSPATH;

        foreach ($this->critical_paths as $item) {
            $full_path = $root . $item['path'];
            $exists = file_exists($full_path);
            
            $perms = $exists ? substr(sprintf('%o', fileperms($full_path)), -4) : 'N/A';
            
            // Status-Logik
            $status = 'secure';
            $msg = 'Keine Aktion erforderlich';

            if (!$exists) {
                $status = 'missing';
                $msg = 'Datei/Ordner nicht gefunden';
            } elseif ($perms !== $item['rec']) {
                // Sonderfall wp-config: 0600 oder 0640 oder 0644 (Host abhängig)
                if ($item['path'] === 'wp-config.php' && in_array($perms, ['0600', '0640', '0644'])) {
                    $status = 'secure';
                } else {
                    $status = 'warning';
                    $msg = 'Rechte korrigieren auf ' . $item['rec'];
                }
            }

            $results[] = [
                'label' => $item['label'],
                'path'  => $full_path, // Zeigt den echten Server-Pfad
                'perms' => $perms,
                'rec'   => $item['rec'],
                'status'=> $status,
                'msg'   => $msg
            ];
        }

        return $results;
    }
}
