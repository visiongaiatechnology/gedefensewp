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
        spl_autoload_register(static function (string $class): void {
            if (strpos($class, 'VIS_') !== 0 && strpos($class, 'VGT\\') !== 0) return;
            
            static $map = [
                'VIS_Scanner_Engine'        => 'includes/scanner/class-vis-scanner-engine.php',
                'VIS_Security'              => 'includes/core/class-vis-security.php',
                'VIS_Event_Bus'             => 'includes/core/class-vis-event-bus.php',
                'VIS_Module_Registry'        => 'includes/core/class-vis-module-registry.php',
                'VIS_Integration_Bus'        => 'includes/core/class-vis-integration-bus.php',
                'VIS_Trinity_Grid'           => 'includes/core/class-vis-trinity-grid.php',
                'VIS_AI_Gateway'             => 'includes/core/class-vis-ai-gateway.php',
                'VIS_Module_Integrity'        => 'includes/core/class-vis-module-integrity.php',
                'VIS_Security_Health'       => 'includes/core/class-vis-security-health.php',
                'VIS_Aegis'                 => 'includes/modules/aegis/class-vis-aegis.php',
                'VIS_Aegis_Oracle'          => 'includes/modules/aegis/class-vis-aegis-oracle.php',
                'VIS_Titan'                 => 'includes/modules/titan/class-vis-titan.php',
                'VIS_Hades'                 => 'includes/modules/hades/class-vis-hades.php',
                'VIS_Oracle'                => 'includes/modules/oracle/class-vis-oracle.php',
                'VIS_Chronos'               => 'includes/modules/chronos/class-vis-chronos.php',
                'VIS_Ghost_Trap'            => 'includes/modules/trap/class-vis-ghost-trap.php',
                'VIS_Airlock'               => 'includes/modules/airlock/class-vis-airlock.php',
                'VIS_Styx'                  => 'includes/modules/styx/class-vis-styx.php',
                'VIS_Key_Vault'             => 'includes/modules/vault/class-vis-key-vault.php',
                'VIS_Cerberus'              => 'includes/modules/cerberus/class-vis-cerberus.php',
                'VIS_Filesystem_Guard'      => 'includes/modules/filesystem/class-vis-filesystem-guard.php',
                'VIS_Kernel_Sentinel'       => 'includes/modules/kernel/class-vis-kernel-sentinel.php',
                'VIS_Zeus'                  => 'includes/modules/zeus/class-vis-zeus.php',
                'VIS_I18n'                  => 'includes/core/class-vis-i18n.php',
                'VIS_Dashboard_Core'        => 'includes/dashboard/class-vis-dashboard-core.php',
                'VIS_Dashboard_View'        => 'includes/dashboard/class-vis-dashboard-view.php',
                'VIS_Compatibility_Manager' => 'includes/compatibility/class-vis-compatibility-manager.php',
            ];

            if (isset($map[$class])) {
                $file = VIS_PATH . $map[$class];
                if (is_readable($file)) {
                    require_once $file;
                }
            }
        });
    }

    private static function trigger_fail_close(string $module, string $reason = ''): void {
        http_response_code(503);
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 300');
        die("<h1>VGT SYSTEM HALT</h1><p>INTEGRITY COMPROMISED. Critical module [{$module}] failed to load. Fail-Close sequence initiated to protect host environment. " . ($reason ? "($reason)" : "") . "</p>");
    }

    public static function engage_phase_1(array $config): void {
        
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
            'prometheus' => ['path' => 'includes/modules/prometheus/class-vis-prometheus.php', 'class' => '\VisionGaia\Integrity\Modules\Prometheus\VIS_Prometheus', 'default' => false, 'critical' => false],
            'nemesis'    => ['path' => 'includes/modules/nemesis/class-vis-nemesis.php', 'class' => '\VisionGaia\Integrity\Modules\Nemesis\VIS_Nemesis', 'default' => false, 'critical' => false],
            'morpheus'   => ['path' => 'includes/modules/morpheus/class-vis-morpheus.php', 'class' => '\VGT\Sentinel\Modules\Morpheus\Vis_Morpheus', 'default' => true, 'critical' => true],
            'gorgon'     => ['path' => 'includes/modules/gorgon/class-vis-gorgon.php', 'class' => '\VGT\Sentinel\Modules\Gorgon\Vis_Gorgon', 'default' => true, 'critical' => false],
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
        if (class_exists('VIS_Styx')) VIS_Styx::get_instance();
        
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
