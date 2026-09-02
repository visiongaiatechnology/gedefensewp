<?php 
declare(strict_types=1);
/**
 * VISIONGAIA DASHBOARD VIEW: SIDEBAR
 * MODULE: GLOBAL NAVIGATION & BRANDING
 * STATUS: PLATIN VGT STATUS (Hardened UI & i18n)
 */
if (!defined('ABSPATH')) exit; ?>

<!-- =========================================================================================
     SIDEBAR DOM
     ========================================================================================= -->
<aside class="vgt-sidebar" id="vis-dashboard-sidebar" aria-label="<?php echo esc_attr__('GeDefense Navigation', 'vgt-sentinel'); ?>">
    
    <!-- BRANDING -->
    <div class="vgt-sidebar-brand">
        <div class="vgt-logo-icon">
            <!-- VGT SUPREME: Dynamic Icon Injection -->
            <img src="<?php echo esc_url(defined('VIS_SENTINEL_ICON') ? VIS_SENTINEL_ICON : ''); ?>" 
                 alt="<?php echo esc_attr__('GeDefense WP Icon', 'vgt-sentinel'); ?>" 
                 style="width: 24px; height: 24px; object-fit: contain; filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.4));">
        </div>
        <div>
            <h2 class="vgt-brand-title"><?php esc_html_e('GEDEFENSE', 'vgt-sentinel'); ?><span><?php esc_html_e('WP', 'vgt-sentinel'); ?></span></h2>
            <a href="https://visiongaiatechnology.de" target="_blank" rel="noopener noreferrer" class="vgt-brand-sub"><?php esc_html_e('by VisionGaiaTechnology', 'vgt-sentinel'); ?></a>
        </div>
    </div>

    <!-- NAVIGATION (SCROLLABLE) -->
    <nav class="vgt-sidebar-nav">
        <?php foreach ($this->get_tabs() as $slug => $data): ?>
            
            <?php if (isset($data['type']) && $data['type'] === 'separator'): ?>
                <!-- TIER SEPARATOR (Nicht klickbar) -->
                <div class="vgt-nav-separator">
                    <?php echo esc_html($data['label']); ?>
                </div>
            <?php else: ?>
                <!-- KLICKBARES MODUL -->
                <a href="<?php echo esc_url(add_query_arg(['page' => 'vgt-suite', 'tab' => (string)$slug], admin_url('admin.php'))); ?>" 
                   class="vgt-nav-item <?php echo $active_tab === $slug ? 'active' : ''; ?>">
                    <svg class="vgt-icon" viewBox="0 0 24 24">
                        <?php 
                        // Icons sind interne VGT-Ressourcen, werden aber zur Sicherheit via kses gefiltert
                        echo wp_kses($data['icon'] ?? '', [
                            'path'     => ['d' => true, 'fill' => true, 'stroke' => true],
                            'circle'   => ['cx' => true, 'cy' => true, 'r' => true],
                            'line'     => ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true],
                            'polyline' => ['points' => true],
                            'rect'     => ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true],
                            'polygon'  => ['points' => true],
                            'ellipse'  => ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true],
                        ]); 
                        ?>
                    </svg>
                    <?php echo esc_html($data['label']); ?>
                </a>
            <?php endif; ?>
            
        <?php endforeach; ?>
    </nav>
    
    <!-- FOOTER / SYSTEM STATUS -->
    <div class="vgt-sidebar-footer">
        <div class="vgt-footer-row">
            <span><?php esc_html_e('CORE STATUS', 'vgt-sentinel'); ?></span>
            <div class="vgt-status-indicator">
                <span class="vgt-pulse-dot"></span>
                <?php esc_html_e('ONLINE', 'vgt-sentinel'); ?>
            </div>
        </div>
        <div class="vgt-footer-row">
            <span><?php esc_html_e('EDITION', 'vgt-sentinel'); ?></span>
            <span style="color:#10b981; font-weight:700;"><?php echo esc_html(defined('VIS_VERSION') ? VIS_VERSION : 'OPEN CORE (AGPLv3)'); ?></span>
        </div>
        <div class="vgt-footer-row" style="margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.06); justify-content:space-between;">
            <span><?php esc_html_e('SPRACHE', 'vgt-sentinel'); ?></span>
            <?php if (class_exists('VIS_I18n')) echo VIS_I18n::render_language_switcher(); ?>
        </div>
    </div>
    
</aside>
