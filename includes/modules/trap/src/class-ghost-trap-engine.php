<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Ghost_Trap_Engine {

    private VIS_Ghost_Trap_Config $config;
    private const MANIFEST_KEY = 'vis_ghost_trap_manifest';

    // VGT Naming Dictionaries (Cognitive Lures)
    private const DICT_SYSTEM = ['setup', 'config', 'admin-ajax-test', 'wp-config-sample', 'sys_info', 'debug', 'phpinfo', 'install'];
    private const DICT_BACKUP = ['db_dump', 'backup', 'wp_db', 'site_backup', 'old_config', 'archive', 'dump_2024', 'data'];

    public function __construct(VIS_Ghost_Trap_Config $config) {
        $this->config = $config;
    }

    /**
     * Zerstört alte Fallen und generiert neue basierend auf der aktuellen Config.
     */
    public function redeploy_matrix(): void {
        $this->destroy_all_traps();

        if (!$this->config->is_active()) return;

        $count = $this->config->get_trap_count();
        $extensions = $this->config->get_extensions();
        $style = $this->config->get_name_style();
        
        $new_manifest = [];
        $payload = $this->compile_trap_payload();

        for ($i = 0; $i < $count; $i++) {
            $filename = $this->generate_filename($style, $extensions);
            $filepath = ABSPATH . $filename;

            // Safety-Check: Niemals bestehende Dateien überschreiben
            if (file_exists($filepath) && filesize($filepath) > 0) continue;

            $result = @file_put_contents($filepath, $payload, LOCK_EX);
            if ($result !== false) {
                @chmod($filepath, 0644);
                $new_manifest[] = $filename;
            }
        }

        update_option(self::MANIFEST_KEY, $new_manifest);
    }

    /**
     * Vernichtet alle im Manifest registrierten Fallen restlos.
     */
    public function destroy_all_traps(): void {
        $manifest = get_option(self::MANIFEST_KEY, []);
        if (empty($manifest) || !is_array($manifest)) return;

        foreach ($manifest as $filename) {
            $filepath = ABSPATH . sanitize_file_name($filename);
            
            if (file_exists($filepath)) {
                $content = @file_get_contents($filepath, false, null, 0, 100);
                // Nur löschen, wenn es unsere VGT Signatur trägt
                if ($content !== false && strpos($content, 'VISIONGAIATECHNOLOGY GHOST TRAP') !== false) {
                    @unlink($filepath);
                }
            }
        }
        
        delete_option(self::MANIFEST_KEY);
    }

    private function generate_filename(string $style, array $extensions): string {
        $ext = $extensions[array_rand($extensions)];
        $ext = ltrim($ext, '.');

        $base = '';
        $current_style = ($style === 'mixed') ? (['system', 'backup', 'random'][random_int(0, 2)]) : $style;

        switch ($current_style) {
            case 'system':
                $base = self::DICT_SYSTEM[array_rand(self::DICT_SYSTEM)];
                if (random_int(0, 1)) $base .= '_' . random_int(1, 99);
                break;
            case 'backup':
                $base = self::DICT_BACKUP[array_rand(self::DICT_BACKUP)];
                $base .= '_' . date('Y_m_d');
                break;
            case 'random':
            default:
                $base = bin2hex(random_bytes(4));
                break;
        }

        return $base . '.' . $ext;
    }

    /**
     * Erzeugt den hochverdichteten, isolierten PHP-Payload.
     * Nutzt SHORTINIT um den DB-Driver zu laden, ohne WP komplett zu booten (Massive Performance).
     */
    private function compile_trap_payload(): string {
        $table_name = defined('VIS_TABLE_BANS') ? VIS_TABLE_BANS : 'vis_apex_bans';

        return <<<PHP
<?php
/** VISIONGAIATECHNOLOGY GHOST TRAP - L7 DECEPTION MODULE */
define('SHORTINIT', true);
require_once dirname(__FILE__) . '/wp-load.php';

global \$wpdb;
\$table = \$wpdb->prefix . '{$table_name}';

\$ip = \$_SERVER['REMOTE_ADDR'] ?? '';
if (!empty(\$ip)) {
    \$uri = \$_SERVER['REQUEST_URI'] ?? '';
    \$query = \$wpdb->prepare(
        "INSERT IGNORE INTO \$table (ip, reason, banned_at, request_uri) VALUES (%s, %s, %s, %s)",
        \$ip, "GHOST_TRAP: Hit Deception Node (\$uri)", current_time('mysql'), \$uri
    );
    \$wpdb->query(\$query);
    
    // Immediate L1 Memory Cache Lockout (O(1) Cerberus sync)
    \$md5_ip = md5(\$ip);
    if (function_exists('apcu_store')) {
        @apcu_store('vis_ban_status_' . \$md5_ip, 1, 300);
    }
    if (function_exists('wp_cache_set')) {
        @wp_cache_set('vis_ban_status_' . \$md5_ip, 1, 'visiongaia_cerberus', 300);
    }

    // Connect to Nemesis Deception connection decay (Tarpitting)
    if (!headers_sent()) {
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/html; charset=utf-8');
        header('Transfer-Encoding: chunked');
        header('Connection: keep-alive');
    }

    \$loops = 0;
    while (\$loops < 30) {
        if (connection_aborted()) {
            break;
        }
        // Send a fake chunked output to keep the HTTP socket open and active
        echo sprintf("%x\r\n%s\r\n", 68, "<!-- VGT SECURITY INTERLOCK DECEPTION MATRIX ACTIVE - NO INTRUSION ALLOWED -->\n");
        @flush();
        sleep(2);
        \$loops++;
    }
    echo "0\r\n\r\n";
    exit;
}

http_response_code(404);
header("Status: 404 Not Found");
echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">';
echo '<html><head><title>404 Not Found</title></head><body>';
echo '<h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
exit;
PHP;
    }
}
