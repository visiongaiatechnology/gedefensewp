<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

use VisionGaia\GeDefense\Modules\Zeus\VIS_Zeus_Compiler;
use VisionGaia\GeDefense\Modules\Zeus\VIS_Zeus_Shield;
use VisionGaia\GeDefense\Modules\Zeus\VIS_Zeus_Env;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Vault_Resolver;
use VisionGaia\GeDefense\Modules\Zeus\Zeus_Config_Repository;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

class VIS_Zeus {

    private string $vault_dir;
    private string $waf_file;
    private array $config;
    private string $client_ip;
    private string $swarm_ip;

    private $env_manager;
    private $compiler;
    private $shield;

    public function __construct() {
        $this->load_dependencies();

        Zeus_Vault_Resolver::ensureDirectories();
        $this->vault_dir = Zeus_Vault_Resolver::getVaultDir();
        $this->waf_file  = Zeus_Vault_Resolver::getWafFile();

        $this->config = Zeus_Config_Repository::get();

        $this->client_ip = $this->extract_deterministic_ip();
        $this->swarm_ip  = $this->get_swarm_ip($this->client_ip);

        $this->init_modules();
    }

    public static function getDefaultConfig(): array {
        require_once __DIR__ . '/src/class-zeus-vault-resolver.php';
        require_once __DIR__ . '/src/class-zeus-policy-manager.php';
        require_once __DIR__ . '/src/class-zeus-config-repository.php';
        return Zeus_Config_Repository::getDefaults();
    }

    private function load_dependencies(): void {
        require_once __DIR__ . '/src/class-zeus-vault-resolver.php';
        require_once __DIR__ . '/src/class-zeus-policy-manager.php';
        require_once __DIR__ . '/src/class-zeus-config-repository.php';
        require_once __DIR__ . '/src/class-zeus-contracts.php';
        require_once __DIR__ . '/src/class-zeus-xdr-bridge.php';
        require_once __DIR__ . '/src/class-zeus-admission.php';
        require_once __DIR__ . '/src/class-zeus-blackbox.php';
        require_once __DIR__ . '/src/class-zeus-budget.php';
        require_once __DIR__ . '/src/class-zeus-env.php';
        require_once __DIR__ . '/src/class-zeus-compiler.php';
        require_once __DIR__ . '/src/class-zeus-shield.php';
    }

    private function init_modules(): void {
        $this->env_manager = new VIS_Zeus_Env($this->vault_dir, $this->waf_file, $this->config);
        $this->compiler    = new VIS_Zeus_Compiler($this->vault_dir, $this->waf_file, $this->config, $this->swarm_ip);
        $this->shield      = new VIS_Zeus_Shield($this->config, $this->client_ip, $this->swarm_ip, $this->vault_dir);
        $this->shield->init_hooks();
    }

    public function deploy_perimeter_shield(): array {
        $this->env_manager->ensure_master_key_isolated();
        $this->compiler->deploy_waf();
        $env_results = $this->env_manager->sync_all();

        return [
            'waf'         => file_exists($this->waf_file) && is_readable($this->waf_file),
            'environment' => $env_results
        ];
    }

    public function run_pre_auth_inspection(): void {
        $this->shield->execute_invariants();
    }

    public function get_master_key(): string {
        $this->env_manager->ensure_master_key_isolated();
        return defined('VGT_MASTER_KEY') ? VGT_MASTER_KEY : '';
    }

    private function extract_deterministic_ip(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '127.0.0.1';
    }

    private function get_swarm_ip(string $ip): string {
        $packed = @inet_pton($ip);
        if ($packed === false) return $ip;

        if (strlen($packed) === 4) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
        }
        if (strlen($packed) === 16) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . '::/64';
        }
        return $ip;
    }
}
