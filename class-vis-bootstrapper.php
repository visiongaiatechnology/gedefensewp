<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * VISIONGAIA BOOTSTRAPPER [DIAMOND VGT SUPREME]
 * ARCHITECTURE: Multi-Phase Ignition Protocol
 * KERNEL-FIX: Maximum Striking Power Priority Queue (Cerberus -> Aegis -> Zeus -> Prometheus -> Nemesis)
 */
final class VIS_Bootstrapper {

    public static function register_autoloader(): void {
        $compatibility = VIS_PATH . 'includes/core/class-namespace-compatibility.php';
        if (!is_readable($compatibility)) {
            self::trigger_fail_close('NAMESPACE_KERNEL', 'Compatibility boundary unavailable.');
        }
        require_once $compatibility;
        \VisionGaia\GeDefense\Core\NamespaceCompatibility::register();
    }

    private static function trigger_fail_close(string $module, string $reason = ''): void {
        http_response_code(503);
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 300');
        die("<h1>VGT SYSTEM HALT</h1><p>INTEGRITY COMPROMISED. Critical module [{$module}] failed to load. Fail-Close sequence initiated to protect host environment. " . ($reason ? "($reason)" : "") . "</p>");
    }

    public static function ensure_sovereign_temp_dir(): void {
        $base_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads' : '';
        if (empty($base_dir)) return;

        $temp_dir = wp_normalize_path($base_dir . '/vgt-temp');
        if (!is_dir($temp_dir)) {
            @wp_mkdir_p($temp_dir);
            if (is_dir($temp_dir)) {
                $ht = $temp_dir . '/.htaccess';
                if (!file_exists($ht)) {
                    @file_put_contents($ht, "Order deny,allow\nDeny from all\n");
                }
                $idx = $temp_dir . '/index.php';
                if (!file_exists($idx)) {
                    @file_put_contents($idx, "<?php // Silence is golden\n");
                }
            }
        }

        if (is_dir($temp_dir) && is_writable($temp_dir)) {
            if (!defined('WP_TEMP_DIR')) {
                define('WP_TEMP_DIR', $temp_dir);
            }
            @ini_set('upload_tmp_dir', $temp_dir);
            @ini_set('sys_temp_dir', $temp_dir);

            add_filter('temp_dir', static function(string $dir) use ($temp_dir): string {
                if (!is_dir($dir) || !is_writable($dir)) {
                    return rtrim($temp_dir, '/') . '/';
                }
                return $dir;
            }, 1, 1);
        }
    }

    public static function engage_phase_1(array $config): void {
        self::ensure_sovereign_temp_dir();

        $xdr = '\\VisionGaia\\GeDefense\\Xdr\\EventFabric';
        if (class_exists($xdr)) {
            $xdr::boot();
        }

        $downloads = '\\VisionGaia\\GeDefense\\Modules\\SecureDownloads\\DownloadManager';
        if (class_exists($downloads)) {
            $downloads::boot();
        }
        
        // Initialize Multilanguage Matrix (I18n)
        if (class_exists('VIS_I18n')) {
            VIS_I18n::init();
        }
        
        // VGT KERNEL FIX: WAKE UP THE DOG FIRST. (PRIORITY 0)
        // Cerberus MUSS vor AEGIS und allen anderen Scannern laden.
        // Wenn die IP gebannt ist, terminieren wir den Request hier, und sparen 100% CPU.
        if (class_exists('VIS_Cerberus')) {
            try {
                VIS_Cerberus::instance();
            } catch (\Throwable $e) {
                self::trigger_fail_close('CERBERUS_KERNEL', 'Perimeter guard panic.');
            }
        }

        // Trinity dependencies must exist before the synchronous AEGIS guard can emit a strike.
        if (class_exists('VIS_Trinity_Grid')) {
            VIS_Trinity_Grid::prime($config);
        }

        // VGT KERNEL: AEGIS INITIATION (PRIORITY 1)
        if (!defined('VIS_AEGIS_ACTIVE') && class_exists('VIS_Aegis')) {
            try {
                // VGT KERNEL FIX: LIFECYCLE DELEGATION (LAZY-STRIKE)
                // Keine manuelle Ausführung mehr. Das Aegis-Objekt steuert sein Timing selbst.
                $vis_aegis_engine = new VIS_Aegis($config);
                define('VIS_AEGIS_ACTIVE', true);
            } catch (\Throwable $e) {
                self::trigger_fail_close('AEGIS_KERNEL', 'Initialization panic.');
            }
        }

        // VGT SUPREME KERNEL DIRECTIVE: MAXIMUM STRIKING POWER QUEUE (PRIORITY 2-N)
        // Array-Order diktiert die strikte PHP-Ausführungsreihenfolge in der Foreach-Schleife.
        $core_modules = [
            'oracle'     => ['path' => 'includes/modules/oracle/class-vis-oracle.php', 'class' => 'VIS_Oracle', 'default' => true, 'critical' => false],
            'zeus'       => ['path' => 'includes/modules/zeus/class-vis-zeus.php', 'class' => 'VIS_Zeus', 'default' => true, 'critical' => true],
            'prometheus' => ['path' => 'includes/modules/prometheus/class-vis-prometheus.php', 'class' => '\VisionGaia\GeDefense\Modules\Prometheus\Prometheus', 'default' => false, 'critical' => false],
            'nemesis'    => ['path' => 'includes/modules/nemesis/class-vis-nemesis.php', 'class' => '\VisionGaia\GeDefense\Modules\Nemesis\Nemesis', 'default' => false, 'critical' => false],
            'morpheus'   => ['path' => 'includes/modules/morpheus/class-vis-morpheus.php', 'class' => '\VisionGaia\GeDefense\Modules\Morpheus\Morpheus', 'default' => true, 'critical' => true],
            'gorgon'     => ['path' => 'includes/modules/gorgon/class-vis-gorgon.php', 'class' => '\VisionGaia\GeDefense\Modules\Gorgon\Gorgon', 'default' => true, 'critical' => false],
        ];

        foreach ($core_modules as $mod_key => $mod_data) {
            $is_enabled = isset($config[$mod_key . '_enabled']) ? !empty($config[$mod_key . '_enabled']) : $mod_data['default'];
            $is_gorgon_ajax = $mod_key === 'gorgon'
                && wp_doing_ajax()
                && isset($_REQUEST['action'])
                && is_string($_REQUEST['action'])
                && str_starts_with($_REQUEST['action'], 'vgt_gorgon_');
            
            if ( ! empty( $mod_data['critical'] ) ) {
                $is_enabled = true;
            }

            if ($is_enabled || $is_gorgon_ajax) {
                $mod_file = VIS_PATH . $mod_data['path'];
                if (is_readable($mod_file)) {
                    require_once $mod_file;
                    if (class_exists($mod_data['class'])) {
                        try {
                            $target_class = $mod_data['class'];
                            if (method_exists($target_class, 'get_instance')) {
                                $target_class::get_instance();
                            } else {
                                new $target_class();
                            }
                        } catch (\Throwable $e) {
                            if ($mod_data['critical']) self::trigger_fail_close(strtoupper($mod_key), 'Subsystem panic.');
                        }
                    }
                } elseif ($mod_data['critical']) {
                    self::trigger_fail_close(strtoupper($mod_key), 'Missing critical subsystem file.');
                }
            }
        }
    }

    public static function engage_phase_2(array $config): void {
        if (defined('VIS_BOOTSTRAP_COMPLETE')) return; 
        
        if (class_exists('VIS_Compatibility_Manager')) new VIS_Compatibility_Manager();
        if (class_exists('VIS_Titan')) new VIS_Titan($config);
        if (class_exists('VIS_Hades')) new VIS_Hades($config);
        if (class_exists('VIS_Airlock')) new VIS_Airlock();
        if (class_exists('VIS_Ghost_Trap')) new VIS_Ghost_Trap();
        if (class_exists('VIS_Chronos')) VIS_Chronos::instance();
        if (class_exists('VIS_Kernel_Sentinel')) new VIS_Kernel_Sentinel();
        if (class_exists('\VisionGaia\GeDefense\Modules\Styx\Styx')) {
            \VisionGaia\GeDefense\Modules\Styx\Styx::get_instance();
        }
        // ThroneGuard is a fixed core component: its activation and recovery
        // endpoints must remain reachable before enforcement is enabled.
        if (class_exists('VIS_Throne_Guard')) VIS_Throne_Guard::get_instance();
        if (!empty($config['loginpager_enabled']) && class_exists('VIS_LoginPager')) VIS_LoginPager::get_instance();
        
        $vault_path = VIS_PATH . 'includes/modules/vault/class-vis-key-vault.php';
        if (is_readable($vault_path)) {
            require_once $vault_path;
            if (class_exists('VIS_Key_Vault')) new VIS_Key_Vault();
        }

        if (class_exists('VIS_Integration_Bus')) VIS_Integration_Bus::mount();
        if (class_exists('VIS_Module_Registry')) {
            foreach (VIS_Module_Registry::all() as $id => $module) {
                if (!VIS_Module_Registry::enabled($id, $config)) continue;
                $path = VIS_Module_Registry::path($id);
                if (is_readable($path)) {
                    require_once $path;
                    if (!empty($module['class']) && class_exists($module['class'])) {
                        $target_class = $module['class'];
                        if (method_exists($target_class, 'get_instance')) {
                            $target_class::get_instance();
                        } elseif (method_exists($target_class, 'engage')) {
                            $target_class::engage();
                        } else {
                            new $target_class();
                        }
                    }
                }
            }
        }
        
        if (is_admin() && class_exists('VIS_Dashboard_Core')) {
            new VIS_Dashboard_Core();
        }
        
        define('VIS_BOOTSTRAP_COMPLETE', true);
    }
}
