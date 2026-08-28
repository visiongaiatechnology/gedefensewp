<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * VISIONGAIATECHNOLOGY SYSTEM
 * MODULE: SCHEMA ENFORCEMENT (MU-Core)
 * ARCHITECT: OMEGA PROTOCOL
 */
final class VIS_Schema {

    public static function enforce(): void {
        global $wpdb;
        $installed_ver = get_option('vis_db_version');
        
        // Bail early if the database schema is already up-to-date
        if ($installed_ver === VIS_VERSION) {
            return;
        }

        // Include dbDelta context
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        // 1. VAULT DIRECTORY ENFORCEMENT
        // Stellt sicher, dass das Vault-Verzeichnis immer physisch und sicher existiert
        if (!is_dir(VIS_VAULT_DIR) && !wp_mkdir_p(VIS_VAULT_DIR)) {
            throw new RuntimeException('VGT vault storage unavailable.');
        }

        $vault_files = [
            'index.php' => "<?php\nhttp_response_code(404);\nexit;\n",
            '.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder Deny,Allow\nDeny from all\n</IfModule>\nRemoveHandler .php .phtml .phar\nRemoveType .php .phtml .phar\n",
            'web.config' => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization><directoryBrowse enabled="false" /></system.webServer></configuration>',
        ];

        foreach ($vault_files as $name => $content) {
            $path = VIS_VAULT_DIR . DIRECTORY_SEPARATOR . $name;
            if (file_put_contents($path, $content, LOCK_EX) === false || !chmod($path, 0600)) {
                throw new RuntimeException('VGT vault enforcement failed.');
            }
        }

        if (!chmod(VIS_VAULT_DIR, 0700)) {
            throw new RuntimeException('VGT vault permission enforcement failed.');
        }

        $charset_collate = $wpdb->get_charset_collate();
        
        // 2. DATABASE TABLE DEFINITIONS
        // [ DIAMANT VGT FIX ]: "0000-00-00 00:00:00" entfernt, um Fatal Errors auf Servern 
        // mit STRICT_TRANS_TABLES / NO_ZERO_DATE zu verhindern.
        
        $sql_bans = "CREATE TABLE " . $wpdb->prefix . VIS_TABLE_BANS . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ip varchar(45) NOT NULL,
            reason text NOT NULL,
            banned_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            request_uri varchar(255) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ip (ip),
            KEY banned_at (banned_at)
        ) $charset_collate;";
        
        $sql_logs = "CREATE TABLE " . $wpdb->prefix . VIS_TABLE_LOGS . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            module varchar(32) NOT NULL,
            type varchar(32) NOT NULL,
            message text NOT NULL,
            ip varchar(45) NOT NULL,
            severity tinyint(1) DEFAULT 1,
            PRIMARY KEY  (id),
            KEY module_timestamp (module, timestamp)
        ) $charset_collate;";
        
        $sql_oracle = "CREATE TABLE " . $wpdb->prefix . 'vis_oracle_patterns' . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            ip varchar(45) NOT NULL,
            type varchar(64) NOT NULL,
            message text NOT NULL,
            ai_reason text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql_rate_limits = "CREATE TABLE " . $wpdb->prefix . "vis_rate_limits (
            scope_hash char(64) NOT NULL,
            window_start bigint(20) unsigned NOT NULL,
            hits bigint(20) unsigned NOT NULL DEFAULT 1,
            expires_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (scope_hash),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // 3. EXECUTE SCHEMA UPDATES
        // Supress errors locally to avoid leaking schema data on edge cases
        $wpdb->suppress_errors();
        dbDelta($sql_oracle);
        dbDelta($sql_bans);
        dbDelta($sql_logs);
        dbDelta($sql_rate_limits);
        $wpdb->show_errors();
        
        // 4. FINALIZE UPDATE
        update_option('vis_db_version', VIS_VERSION);
        update_option('vgt_oracle_table_ready', true); // VGT KERNEL FIX: State-Synchronisation für das Oracle
        flush_rewrite_rules();
    }
}
