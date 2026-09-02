<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

if (!class_exists('\VisionGaia\GeDefense\Modules\Morpheus\Morpheus')) {
    $core_file = wp_normalize_path(VIS_PATH . 'includes/modules/morpheus/class-vis-morpheus.php');
    if (is_readable($core_file)) {
        require_once $core_file;
    }
}

if (!class_exists('\VisionGaia\GeDefense\Modules\Morpheus\Morpheus')) {
    echo '<section class="vgt-titan"><div class="vgt-titan-panel" style="border-color:#fb7185; color:#fb7185;">' . esc_html__('VGT KERNEL PANIC: Morpheus Engine Boot Failure.', 'vgt-sentinel') . '</div></section>';
    return;
}

$morpheus = \VisionGaia\GeDefense\Modules\Morpheus\Morpheus::get_instance();
$vis_config = get_option('vis_config', []);
$is_strict_mode = !empty($vis_config['morpheus_strict_mode']);
$active_matrix = $morpheus->get_full_matrix();

$vault_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/vgt-vault/morpheus/' : dirname(ABSPATH) . '/wp-content/vgt-vault/morpheus/';
$audit_dir = $vault_dir . 'audit/';

$learning_plugins = [];
$active_plugins_db = (array)get_option('active_plugins', []);
foreach ($active_plugins_db as $p) {
    $slug = dirname((string)$p);
    if ($slug !== '.' && $slug !== '/') {
        $learning_plugins[$slug] = 0; 
    }
}

if (is_dir($audit_dir)) {
    $log_files = glob($audit_dir . '*.log');
    if (is_array($log_files)) {
        foreach ($log_files as $file) {
            if (str_contains($file, '.submitted')) continue; 
            if (!is_readable($file)) continue;
            $slug = basename($file, '.log');
            $file_lines = file($file, FILE_SKIP_EMPTY_LINES);
            $lines = is_array($file_lines) ? count($file_lines) : 0;
            $learning_plugins[$slug] = min($lines, 200); 
        }
    }
}
arsort($learning_plugins);
?>

<section class="vgt-titan" aria-label="Morpheus RASP Hypervisor">
    <header class="vgt-titan-hero">
        <div>
            <p class="vgt-titan-kicker"><?php esc_html_e('RUNTIME APPLICATION SELF-PROTECTION (RASP) & HYPERVISOR', 'vgt-sentinel'); ?></p>
            <h2>MORPHEUS</h2>
            <p><?php esc_html_e('Plugin-Verhaltensvirtualisierung, Laufzeit-Sandboxing und RASP-Funktionsabfang zur Verhinderung von Supply-Chain-Angriffen und Zero-Days.', 'vgt-sentinel'); ?></p>
        </div>
        <div class="vgt-titan-state-stack" aria-label="Morpheus Status">
            <span><small><?php esc_html_e('HYPERVISOR', 'vgt-sentinel'); ?></small><strong><?php esc_html_e('ACTIVE', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('ISOLATION MODE', 'vgt-sentinel'); ?></small><strong><?php echo $is_strict_mode ? esc_html__('STRICT', 'vgt-sentinel') : esc_html__('ADAPTIVE', 'vgt-sentinel'); ?></strong></span>
            <span><small><?php esc_html_e('MONITORED PLUGINS', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo count($learning_plugins); ?></strong></span>
            <span><small><?php esc_html_e('SANDBOX OVERLAYS', 'vgt-sentinel'); ?></small><strong><?php echo count($active_matrix); ?></strong></span>
        </div>
    </header>

    <div class="vgt-titan-status-grid">
        <article><small><?php esc_html_e('MONITORED EXTENSIONS', 'vgt-sentinel'); ?></small><strong><?php echo count($learning_plugins); ?></strong></article>
        <article><small><?php esc_html_e('ISOLATION POLICY', 'vgt-sentinel'); ?></small><strong><?php echo $is_strict_mode ? esc_html__('ENFORCED', 'vgt-sentinel') : esc_html__('ADAPTIVE', 'vgt-sentinel'); ?></strong></article>
        <article><small><?php esc_html_e('ACTIVE OVERLAYS', 'vgt-sentinel'); ?></small><strong style="color: #5eead4;"><?php echo count($active_matrix); ?></strong></article>
        <article><small><?php esc_html_e('INTERCEPT ENGINE', 'vgt-sentinel'); ?></small><strong>PHP RUNTIME</strong></article>
        <article><small><?php esc_html_e('TELEMETRY QUOTA', 'vgt-sentinel'); ?></small><strong>200 SAMPLES</strong></article>
        <article><small><?php esc_html_e('CONTAINMENT', 'vgt-sentinel'); ?></small><strong>LOCAL VIRTUAL</strong></article>
    </div>

    <!-- ISOLATION MATRIX PANEL -->
    <section class="vgt-titan-panel">
        <div class="vgt-titan-panel-head">
            <div>
                <small><?php esc_html_e('01 / ISOLATION MATRIX', 'vgt-sentinel'); ?></small>
                <h3><?php esc_html_e('Active Plugin Isolation & Profiling Ledger', 'vgt-sentinel'); ?></h3>
            </div>
            <span class="vgt-titan-badge"><?php echo count($learning_plugins); ?> <?php esc_html_e('PLUGINS', 'vgt-sentinel'); ?></span>
        </div>

        <div class="vgt-titan-table-wrap">
            <table class="vgt-titan-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Plugin Identifier', 'vgt-sentinel'); ?></th>
                        <th><?php esc_html_e('Audit Telemetry Progress', 'vgt-sentinel'); ?></th>
                        <th><?php esc_html_e('Hypervisor State', 'vgt-sentinel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($learning_plugins as $slug => $count): 
                        $pct = min(100, round(($count / 200) * 100));
                    ?>
                    <tr>
                        <td><code><?php echo esc_html((string)$slug); ?></code></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="flex:1; background:rgba(255,255,255,0.06); height:6px; border-radius:3px; overflow:hidden;">
                                    <div style="width:<?php echo esc_attr((string)$pct); ?>%; background:linear-gradient(90deg, #5eead4, #60a5fa); height:100%;"></div>
                                </div>
                                <code style="font-size:10px; color:#7dd3fc;"><?php echo esc_html((string)$count); ?>/200</code>
                            </div>
                        </td>
                        <td>
                            <strong style="color: <?php echo $pct >= 100 ? '#5eead4' : '#60a5fa'; ?>;">
                                <?php echo $pct >= 100 ? esc_html__('PROFILED', 'vgt-sentinel') : esc_html__('LEARNING', 'vgt-sentinel'); ?>
                            </strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
