<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Core;

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

/**
 * Single migration boundary between the canonical GeDefense namespace and
 * pre-8.0 symbols. New code must depend on canonical names only.
 */
final class NamespaceCompatibility {
    private const CLASSES = [
        'VisionGaia\\GeDefense\\Core\\Bootstrapper' => ['class-vis-bootstrapper.php', ['VIS_Bootstrapper']],
        'VisionGaia\\GeDefense\\Core\\Schema' => ['class-vis-schema.php', ['VIS_Schema']],
        'VisionGaia\\GeDefense\\Core\\Vault' => ['class-vis-vault.php', ['VIS_Vault']],
        'VisionGaia\\GeDefense\\Core\\Security' => ['includes/core/class-vis-security.php', ['VIS_Security']],
        'VisionGaia\\GeDefense\\Core\\EventBus' => ['includes/core/class-vis-event-bus.php', ['VIS_Event_Bus']],
        'VisionGaia\\GeDefense\\Core\\ModuleRegistry' => ['includes/core/class-vis-module-registry.php', ['VIS_Module_Registry']],
        'VisionGaia\\GeDefense\\Core\\IntegrationBus' => ['includes/core/class-vis-integration-bus.php', ['VIS_Integration_Bus']],
        'VisionGaia\\GeDefense\\Core\\TrinityGrid' => ['includes/core/class-vis-trinity-grid.php', ['VIS_Trinity_Grid']],
        'VisionGaia\\GeDefense\\Core\\AIGateway' => ['includes/core/class-vis-ai-gateway.php', ['VIS_AI_Gateway']],
        'VisionGaia\\GeDefense\\Core\\ModuleIntegrity' => ['includes/core/class-vis-module-integrity.php', ['VIS_Module_Integrity']],
        'VisionGaia\\GeDefense\\Core\\SecurityHealth' => ['includes/core/class-vis-security-health.php', ['VIS_Security_Health']],
        'VisionGaia\\GeDefense\\Core\\SecurityCenter' => ['includes/core/class-vis-security-center.php', ['VIS_Security_Center']],
        'VisionGaia\\GeDefense\\Core\\I18n' => ['includes/core/class-vis-i18n.php', ['VIS_I18n']],
        'VisionGaia\\GeDefense\\Compatibility\\Manager' => ['includes/compatibility/class-vis-compatibility-manager.php', ['VIS_Compatibility_Manager']],
        'VisionGaia\\GeDefense\\Dashboard\\Dashboard' => ['includes/dashboard/class-vis-dashboard-core.php', ['VIS_Dashboard_Core']],
        'VisionGaia\\GeDefense\\Dashboard\\View' => ['includes/dashboard/class-vis-dashboard-view.php', ['VIS_Dashboard_View']],
        'VisionGaia\\GeDefense\\Dashboard\\Settings' => ['includes/dashboard/class-vis-dashboard-settings.php', ['VIS_Dashboard_Settings']],
        'VisionGaia\\GeDefense\\Dashboard\\Assets' => ['includes/dashboard/class-vis-dashboard-assets.php', ['VIS_Dashboard_Assets']],
        'VisionGaia\\GeDefense\\Dashboard\\Ajax' => ['includes/dashboard/class-vis-dashboard-ajax.php', ['VIS_Dashboard_Ajax']],
        'VisionGaia\\GeDefense\\Dashboard\\SentinelExport' => ['includes/dashboard/class-vis-sentinel-export.php', ['VIS_Sentinel_Export']],
        'VisionGaia\\GeDefense\\Scanner\\ScannerEngine' => ['includes/scanner/class-vis-scanner-engine.php', ['VIS_Scanner_Engine_Omega', 'VIS_Scanner_Engine']],
        'VisionGaia\\GeDefense\\Scanner\\MalwareEngine' => ['includes/scanner/class-vis-malware-engine.php', ['VIS_Malware_Engine']],
        'VisionGaia\\GeDefense\\Xdr\\RequestContext' => ['includes/xdr/class-xdr-request-context.php', []],
        'VisionGaia\\GeDefense\\Xdr\\Redactor' => ['includes/xdr/class-xdr-redactor.php', []],
        'VisionGaia\\GeDefense\\Xdr\\XdrEvent' => ['includes/xdr/class-xdr-event.php', []],
        'VisionGaia\\GeDefense\\Xdr\\EventRepository' => ['includes/xdr/class-xdr-event-repository.php', []],
        'VisionGaia\\GeDefense\\Xdr\\EvidenceStore' => ['includes/xdr/class-xdr-evidence-store.php', []],
        'VisionGaia\\GeDefense\\Xdr\\IncidentEngine' => ['includes/xdr/class-xdr-incident-engine.php', []],
        'VisionGaia\\GeDefense\\Xdr\\PolicyEngine' => ['includes/xdr/class-xdr-policy-engine.php', []],
        'VisionGaia\\GeDefense\\Xdr\\ResponseEngine' => ['includes/xdr/class-xdr-response-engine.php', []],
        'VisionGaia\\GeDefense\\Xdr\\EventFabric' => ['includes/xdr/class-xdr-event-fabric.php', []],
        'VisionGaia\\GeDefense\\Modules\\Aegis\\Aegis' => ['includes/modules/aegis/class-vis-aegis.php', ['VIS_Aegis']],
        'VisionGaia\\GeDefense\\Modules\\Aegis\\Oracle' => ['includes/modules/aegis/class-vis-aegis-oracle.php', ['VIS_Aegis_Oracle']],
        'VisionGaia\\GeDefense\\Modules\\Airlock\\Airlock' => ['includes/modules/airlock/class-vis-airlock.php', ['VIS_Airlock']],
        'VisionGaia\\GeDefense\\Modules\\Cerberus\\Cerberus' => ['includes/modules/cerberus/class-vis-cerberus.php', ['VIS_Cerberus']],
        'VisionGaia\\GeDefense\\Modules\\Chronos\\Chronos' => ['includes/modules/chronos/class-vis-chronos.php', ['VIS_Chronos']],
        'VisionGaia\\GeDefense\\Modules\\Filesystem\\FilesystemGuard' => ['includes/modules/filesystem/class-vis-filesystem-guard.php', ['VIS_Filesystem_Guard']],
        'VisionGaia\\GeDefense\\Modules\\Hades\\Hades' => ['includes/modules/hades/class-vis-hades.php', ['VIS_Hades']],
        'VisionGaia\\GeDefense\\Modules\\Kernel\\KernelSentinel' => ['includes/modules/kernel/class-vis-kernel-sentinel.php', ['VIS_Kernel_Sentinel']],
        'VisionGaia\\GeDefense\\Modules\\LoginPager\\LoginPager' => ['includes/modules/loginpager/class-vis-loginpager.php', ['VIS_LoginPager']],
        'VisionGaia\\GeDefense\\Modules\\SecureDownloads\\DownloadManager' => ['includes/modules/downloads/class-secure-download-manager.php', []],
        'VisionGaia\\GeDefense\\Modules\\Oracle\\Oracle' => ['includes/modules/oracle/class-vis-oracle.php', ['VIS_Oracle']],
        'VisionGaia\\GeDefense\\Modules\\ThroneGuard\\ThroneGuard' => ['includes/modules/throneguard/class-vis-throne-guard.php', ['VIS_Throne_Guard']],
        'VisionGaia\\GeDefense\\Modules\\Titan\\Titan' => ['includes/modules/titan/class-vis-titan.php', ['VIS_Titan']],
        'VisionGaia\\GeDefense\\Modules\\Trap\\GhostTrap' => ['includes/modules/trap/class-vis-ghost-trap.php', ['VIS_Ghost_Trap']],
        'VisionGaia\\GeDefense\\Modules\\Vault\\KeyVault' => ['includes/modules/vault/class-vis-key-vault.php', ['VIS_Key_Vault']],
        'VisionGaia\\GeDefense\\Modules\\Zeus\\Zeus' => ['includes/modules/zeus/class-vis-zeus.php', ['VIS_Zeus']],
        'VisionGaia\\GeDefense\\Modules\\Prometheus\\Prometheus' => ['includes/modules/prometheus/class-vis-prometheus.php', ['VisionGaia\\Integrity\\Modules\\Prometheus\\VIS_Prometheus']],
        'VisionGaia\\GeDefense\\Modules\\Nemesis\\Nemesis' => ['includes/modules/nemesis/class-vis-nemesis.php', ['VisionGaia\\Integrity\\Modules\\Nemesis\\VIS_Nemesis']],
        'VisionGaia\\GeDefense\\Modules\\Styx\\Styx' => ['includes/modules/styx/class-vis-styx.php', ['VIS_Styx', 'VisionGaia\\Integrity\\Modules\\Styx\\VIS_Styx']],
        'VisionGaia\\GeDefense\\Modules\\Morpheus\\Morpheus' => ['includes/modules/morpheus/class-vis-morpheus.php', ['VGT\\Sentinel\\Modules\\Morpheus\\Vis_Morpheus']],
        'VisionGaia\\GeDefense\\Modules\\Gorgon\\Gorgon' => ['includes/modules/gorgon/class-vis-gorgon.php', ['VGT\\Sentinel\\Modules\\Gorgon\\Vis_Gorgon']],
    ];

    public static function register(): void {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;
        spl_autoload_register([self::class, 'autoload'], true, true);
        self::link('VisionGaia\\GeDefense\\Core\\Bootstrapper');
    }

    public static function autoload(string $requested): void {
        $canonical = self::canonical($requested);
        if ($canonical === null) {
            return;
        }

        $definition = self::CLASSES[$canonical];
        $file = VIS_PATH . $definition[0];
        if (!is_readable($file)) {
            return;
        }

        require_once $file;
        self::link($canonical);
    }

    private static function canonical(string $requested): ?string {
        if (isset(self::CLASSES[$requested])) {
            return $requested;
        }

        static $aliases = null;
        if ($aliases === null) {
            $aliases = [];
            foreach (self::CLASSES as $canonical => $definition) {
                foreach ($definition[1] as $alias) {
                    $aliases[$alias] = $canonical;
                }
            }
        }

        return $aliases[$requested] ?? null;
    }

    private static function link(string $canonical): void {
        $definition = self::CLASSES[$canonical] ?? null;
        if ($definition === null) {
            return;
        }

        if (!self::exists($canonical)) {
            foreach ($definition[1] as $alias) {
                if (self::exists($alias)) {
                    class_alias($alias, $canonical);
                    break;
                }
            }
        }

        if (!self::exists($canonical)) {
            return;
        }

        foreach ($definition[1] as $alias) {
            if (!self::exists($alias)) {
                class_alias($canonical, $alias);
            }
        }
    }

    private static function exists(string $symbol): bool {
        return class_exists($symbol, false) || interface_exists($symbol, false) || trait_exists($symbol, false);
    }
}
