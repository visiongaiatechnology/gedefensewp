<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

/**
 * VIEW CLASS: REFACTORED V3.3
 * Status: ULTRA-DIAMANT VGT SUPREME (LFI/Path-Traversal Defense In Depth & SVG Apex Rendering)
 */
class VIS_Dashboard_View {
    // VGT APEX SVGs: Direktes Path-Mapping eliminiert externe HTTP-Requests für Icon-Fonts.
    public function get_tabs(): array {
        $tabs = [
            'section_intelligence' => ['label' => __('I. ANALYSE & MONITORING', 'vgt-sentinel'), 'type' => 'separator'],
            
            'overview'   => ['icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>', 'label' => __('KONTROLLZENTRUM', 'vgt-sentinel')],
            'systatus'   => ['icon' => '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>', 'label' => __('SYSTEMSTATUS', 'vgt-sentinel')],
            'thread'     => ['icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', 'label' => __('BEDROHUNGSMATRIX', 'vgt-sentinel')],
            'oracle'     => ['icon' => '<circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48 0a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/>', 'label' => __('ORAKEL SCANNER', 'vgt-sentinel')],
            'integrity'  => ['icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>', 'label' => __('INTEGRITÄTS-MONITOR', 'vgt-sentinel')],
            'security_center' => ['icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>', 'label' => __('SICHERHEITSZENTRALE', 'vgt-sentinel')],
            'logs'       => ['icon' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>', 'label' => __('SYSTEM-PROTOKOLLE', 'vgt-sentinel')],

            'section_defense' => ['label' => __('II. AKTIVE ABWEHR-MODULE', 'vgt-sentinel'), 'type' => 'separator'],

            'trinity'    => ['icon' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>', 'label' => __('TRINITY GRID', 'vgt-sentinel')],
            'zeus'       => ['icon' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>', 'label' => __('ZEUS DEFENDER', 'vgt-sentinel')],
            'aegis'      => ['icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'label' => __('AEGIS FIREWALL', 'vgt-sentinel')],
            'prometheus' => ['icon' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>', 'label' => __('PROMETHEUS ENGINE', 'vgt-sentinel')], 
            'cerberus'   => ['icon' => '<circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/>', 'label' => __('CERBERUS IP-SPERRE', 'vgt-sentinel')],
            'airlock'    => ['icon' => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>', 'label' => __('AIRLOCK SCHLEUSE', 'vgt-sentinel')],
            
            'section_deception' => ['label' => __('III. TÄUSCHUNG & HONEYPOTS', 'vgt-sentinel'), 'type' => 'separator'],
            
            'nemesis'    => ['icon' => '<circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/>', 'label' => __('NEMESIS TÄUSCHUNG', 'vgt-sentinel')],
            'ghost_trap' => ['icon' => '<circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/>', 'label' => __('GHOST HONIGTOPF', 'vgt-sentinel')],
            'hades'      => ['icon' => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>', 'label' => __('HADES STEALTH', 'vgt-sentinel')],
            'morpheus'   => ['icon' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>', 'label' => __('MORPHEUS SANDBOX', 'vgt-sentinel')],
            
            'section_core' => ['label' => __('IV. SYSTEMHÄRTUNG & KERNEL', 'vgt-sentinel'), 'type' => 'separator'],
            
            'titan'      => ['icon' => '<line x1="22" y1="12" x2="2" y2="12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><line x1="6" y1="16" x2="6.01" y2="16"/><line x1="10" y1="16" x2="10.01" y2="16"/>', 'label' => __('TITAN HÄRTUNG', 'vgt-sentinel')],
            'kernel'     => ['icon' => '<rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/>', 'label' => __('KERNEL UPLINK', 'vgt-sentinel')],
            'styx'       => ['icon' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>', 'label' => __('STYX CONTROLLER', 'vgt-sentinel')],
            'chronos'    => ['icon' => '<rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/>', 'label' => __('CHRONOS AUTOPILOT', 'vgt-sentinel')],
            
            'section_privacy' => ['label' => __('V. DATENSCHUTZ & COMPLIANCE', 'vgt-sentinel'), 'type' => 'separator'],
            
            'vlp'        => ['icon' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>', 'label' => __('DATENSCHUTZ & SHADOW-NET', 'vgt-sentinel')],
            'filesystem' => ['icon' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><rect x="9" y="11" width="6" height="4" rx="1"/><path d="M10 11V9a2 2 0 0 1 4 0v2"/>', 'label' => __('DATENSICHERHEIT', 'vgt-sentinel')],
            // 'gorgon'     => ['icon' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>', 'label' => __('NEXUS NETZWERK', 'vgt-sentinel')],
            'vault'      => ['icon' => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>', 'label' => __('SCHLÜSSEL-TRESOR', 'vgt-sentinel')],

            'section_system' => ['label' => __('VI. SYSTEM & ASSISTENT', 'vgt-sentinel'), 'type' => 'separator'],
            'modules'        => ['icon' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>', 'label' => __('ADD-ON VERWALTUNG', 'vgt-sentinel')],
            'setup_wizard'   => ['icon' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>', 'label' => __('EINRICHTUNGSASSISTENT', 'vgt-sentinel')],
        ];

        // Hide onboarding wizard tab if already completed
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        if (get_option('vgt_setup_wizard_completed') && $active_tab !== 'setup_wizard') {
            unset($tabs['setup_wizard']);
        }

        return $tabs;
    }

    public function render() {
        $tabs = $this->get_tabs();
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';

        // [ DIAMANT VGT FIX: ROUTING GUARD ]
        // Verhindert LFI auf ungültige Tabs UND wehrt ab, falls jemand manuell einen "separator" Tab ansteuert.
        if (!array_key_exists($active_tab, $tabs) || (isset($tabs[$active_tab]['type']) && $tabs[$active_tab]['type'] === 'separator')) {
            $active_tab = 'overview';
        }
        
        // [ DIAMANT VGT FIX: MISSING CONFIG TABS ]
        $config_tabs = ['trinity', 'aegis', 'titan', 'hades', 'zeus', 'prometheus', 'vault', 'nemesis', 'styx', 'morpheus', 'gorgon', 'vlp', 'ghost_trap', 'filesystem', 'airlock', 'cerberus', 'modules', 'setup_wizard'];
        $is_config_tab = in_array($active_tab, $config_tabs); 
        
        // DATEN LADEN
        $opt = get_option('vis_config', []); 
        
        echo '<div class="vis-omega-wrapper" data-vis-tab="' . esc_attr($active_tab) . '">';
        
        require VIS_PATH . 'includes/dashboard/views/view-sidebar.php'; 
        echo '<main class="vis-content">';
        
        if ($is_config_tab) {
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="vis_context" value="' . esc_attr($active_tab) . '">';
        }

        $this->render_header($active_tab);
        
        if ($is_config_tab) {
            wp_nonce_field('vis_save_config');
        }
        
        $file = VIS_PATH . 'includes/dashboard/views/view-' . $active_tab . '.php';
        
        echo '<div class="vis-view-animate">';
        if (file_exists($file)) {
            require $file;
        } else {
            echo '<div class="vis-card" style="padding: 40px; text-align: center; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);">';
            echo '<h3 style="color: #ef4444; font-family: \'Orbitron\', sans-serif; letter-spacing: 2px;">404 - SECTOR NOT FOUND</h3>';
            echo '<p style="color: #9ca3af; font-family: monospace;">The requested uplink module does not exist at:<br><code>' . esc_html($file) . '</code></p>';
            echo '</div>';
        }
        echo '</div>';
        
        if ($is_config_tab) {
            echo '</form>';
        }
        
        echo '</main></div>';
    }

    private function render_header($tab) {
        $tabs = $this->get_tabs();
        $label = $tabs[$tab]['label'] ?? __('MODUL', 'vgt-sentinel');
        $icon_path = $tabs[$tab]['icon'] ?? '<circle cx="12" cy="12" r="10"/>'; // Fallback Path
        
        // VGT SVG Render Engine (Sanitized with wp_kses for maximum XSS protection)
        $svg = sprintf(
            '<svg class="vis-header-icon" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:12px;">%s</svg>',
            wp_kses($icon_path, [
                'path'     => ['d' => true, 'fill' => true, 'stroke' => true],
                'circle'   => ['cx' => true, 'cy' => true, 'r' => true],
                'line'     => ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true],
                'polyline' => ['points' => true],
                'rect'     => ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true],
                'polygon'  => ['points' => true],
                'ellipse'  => ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true],
            ])
        );
        
        $lang_switcher = class_exists('VIS_I18n') ? VIS_I18n::render_language_switcher('vis-topbar-lang') : '';

        echo '<header class="vis-topbar" data-module="' . esc_attr(strtoupper((string)$tab)) . '">
                <div class="vis-header-title" style="display:flex; align-items:center;">
                    ' . $svg . '
                    <h1 style="margin:0;">'.esc_html($label).'</h1>
                </div>
                <div style="display:flex; align-items:center; gap:16px;">
                    ' . $lang_switcher;
        
        // Alle Module, die Configs speichern, bekommen den Button. Wir lesen das direkt aus der oben berechneten Variable.
        $config_tabs = ['aegis', 'titan', 'hades', 'zeus', 'prometheus', 'vault', 'nemesis', 'styx', 'morpheus', 'gorgon', 'vlp', 'ghost_trap', 'filesystem', 'airlock', 'cerberus', 'modules'];
        if (in_array($tab, $config_tabs)) {
            // Native Save Icon
            $save_icon = '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
            
            echo '<button type="submit" name="vis_save_config" value="1" class="vis-btn vis-btn-primary" style="display:inline-flex; align-items:center; background: rgba(212, 175, 55, 0.1); color: #D4AF37; border: 1px solid #D4AF37; padding: 8px 16px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; transition: all 0.3s;">
                    ' . $save_icon . ' ' . esc_html__('CONFIG SAVE', 'vgt-sentinel') . '
                  </button>';
        }
        echo '  </div>
              </header>';
    }
}
