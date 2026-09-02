<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Morpheus_UI {

    private Morpheus $core;
    private array $audit_trace_cache = [];

    public function __construct( Morpheus $core ) {
        $this->core = $core;
    }

    public function log_audit_trace( string $caller, string $type, string $details ): void {
        if ( $this->core->enforcement_mode ) return; 
        
        $hash = md5( $caller . $type . $details );
        if ( isset( $this->audit_trace_cache[ $hash ] ) ) return;
        
        $this->audit_trace_cache[ $hash ] = true;
        $this->core->ai->log_audit( $caller, 'TELEMETRY_' . $type, $details, '' );
    }

    public function execute_kill( string $caller, string $violation, string $details ): void {
        if ( class_exists( '\VIS_Event_Bus' ) ) {
            \VIS_Event_Bus::emit(
                'MORPHEUS',
                'PLUGIN_CAPABILITY_VIOLATION',
                sprintf( 'Capability violation %s by %s: %s', $violation, $caller, $details ),
                [
                    'component_key' => $caller,
                    'plugin' => $caller,
                    'violation' => $violation,
                    'details' => $details,
                    'attribution_confidence' => 100,
                ],
                8
            );
        }
        $incident_hash = hash( 'sha256', random_bytes( 16 ) );
        
        if ( ! $this->core->enforcement_mode ) {
            $this->core->ai->log_audit( $caller, 'VIOLATION_' . $violation, $details, $incident_hash );
            return;
        }

        $this->hard_kill( $caller, $violation, $details, $incident_hash );
    }

    public function hard_kill( string $caller, string $violation, string $details, string $incident_hash = '' ): void {
        while ( ob_get_level() > 0 ) ob_end_clean();
        
        if ( ! headers_sent() ) {
            http_response_code( 403 );
            header( 'X-VGT-Sentinel-Status: INTERVENTION' );
        }
        
        if ( empty( $incident_hash ) ) $incident_hash = hash( 'sha256', random_bytes( 16 ) );
        error_log( sprintf( '[VGT MORPHEUS KILL] Incident: %s | Caller: %s | Violation: %s | Details: %s', $incident_hash, $caller, $violation, $details ) );

        $html = $this->render_intervention_dashboard( $violation, $caller, $details, $incident_hash );
        
        // VGT FIX: Bricht aus allen unvollständigen WordPress-Tags oder JS-Strings aus.
        echo '"></a></script></style></div></div></div>';
        
        echo $html;
        
        exit;
    }

    private function render_intervention_dashboard( string $violation, string $caller, string $details, string $incident_hash ): string {
        return sprintf(
            '<div id="vgt-nuke-container" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #050507 radial-gradient(circle at 50%% -20%%, rgba(255,0,60,0.15) 0%%, transparent 70%%); z-index: 2147483647; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, sans-serif; box-sizing: border-box; overflow: hidden; visibility: visible !important; opacity: 1 !important; margin: 0; padding: 20px;">
                <style>
                    /* Neutralize WP System */
                    html, body {
                        background: #050507 !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        width: 100vw !important;
                        height: 100vh !important;
                        overflow: hidden !important;
                    }
                    #wpadminbar, #adminmenumain, #adminmenuwrap, #wpfooter {
                        display: none !important;
                    }
                    
                    /* VGT Core Dashboard Styling */
                    .vgt-dashboard { 
                        background: #121216 !important; 
                        border: 1px solid rgba(255, 0, 60, 0.4) !important; 
                        border-radius: 12px !important; 
                        padding: 40px !important; 
                        width: 100%% !important;
                        max-width: 800px !important;
                        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8), inset 0 0 0 1px rgba(255,255,255,0.05) !important; 
                        position: relative !important; 
                        color: #f0f0f5 !important;
                    }
                    .vgt-dashboard::before { 
                        content: "" !important; position: absolute !important; top: 0 !important; left: 0 !important; width: 100%% !important; height: 3px !important; 
                        background: linear-gradient(90deg, transparent, #ff003c, transparent) !important; 
                        box-shadow: 0 0 20px #ff003c !important; 
                    }
                    .vgt-header { display: flex !important; align-items: center !important; margin-bottom: 30px !important; }
                    .vgt-alert-icon { 
                        display: flex !important; align-items: center !important; justify-content: center !important;
                        width: 48px !important; height: 48px !important; background: rgba(255,0,60,0.1) !important;
                        border-radius: 12px !important; border: 1px solid rgba(255,0,60,0.4) !important;
                        color: #ff003c !important; margin-right: 20px !important; 
                        box-shadow: 0 0 20px rgba(255,0,60,0.3) !important;
                    }
                    .vgt-header h1 { color: #fff !important; font-size: 24px !important; margin: 0 !important; font-weight: 800 !important; border: none !important; padding: 0 !important; line-height: 1.2 !important; }
                    .vgt-status-badge { 
                        background: rgba(255, 0, 60, 0.15) !important; color: #ff003c !important; 
                        padding: 6px 16px !important; border-radius: 20px !important; border: 1px solid rgba(255, 0, 60, 0.3) !important; 
                        font-size: 12px !important; font-weight: 700 !important; font-family: monospace !important;
                        margin-left: auto !important; text-transform: uppercase !important;
                    }
                    .vgt-data-grid { display: grid !important; grid-template-columns: 1fr !important; gap: 15px !important; }
                    .vgt-data-row { 
                        background: rgba(0, 0, 0, 0.4) !important; border: 1px solid rgba(255, 255, 255, 0.05) !important; 
                        border-radius: 8px !important; padding: 15px 20px !important;
                        text-align: left !important;
                    }
                    .vgt-data-label { 
                        font-size: 11px !important; color: #8b8b9e !important; text-transform: uppercase !important; 
                        margin-bottom: 8px !important; letter-spacing: 1px !important; font-weight: 700 !important; 
                    }
                    .vgt-data-value { 
                        font-family: monospace !important; font-size: 14px !important; 
                        color: #fff !important; word-break: break-all !important; line-height: 1.5 !important;
                    }
                    .vgt-data-value.critical { color: #ff003c !important; font-weight: 700 !important; }
                    .vgt-footer { 
                        margin-top: 40px !important; text-align: center !important; font-size: 11px !important; 
                        color: #8b8b9e !important; letter-spacing: 2px !important; text-transform: uppercase !important; 
                        opacity: 0.6 !important;
                    }
                </style>
                <div class="vgt-dashboard">
                    <div class="vgt-header">
                        <div class="vgt-alert-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        </div>
                        <h1>Morpheus Hypervisor</h1>
                        <div class="vgt-status-badge">Execution Halted</div>
                    </div>
                    <div class="vgt-data-grid">
                        <div class="vgt-data-row">
                            <div class="vgt-data-label">Violation Directive</div>
                            <div class="vgt-data-value critical">%s</div>
                        </div>
                        <div class="vgt-data-row">
                            <div class="vgt-data-label">Origin Entity (Caller)</div>
                            <div class="vgt-data-value">%s</div>
                        </div>
                        <div class="vgt-data-row">
                            <div class="vgt-data-label">Technical Payload</div>
                            <div class="vgt-data-value">%s</div>
                        </div>
                        <div class="vgt-data-row">
                            <div class="vgt-data-label">Incident Hash (Reference ID)</div>
                            <div class="vgt-data-value" style="color: #8b8b9e;">%s</div>
                        </div>
                    </div>
                    <div class="vgt-footer">
                        VisionGaiaTechnology Omega Protocol &bull; Zero-Trust Sandbox
                    </div>
                </div>
                <script>
                (function() {
                    var executeNuke = function() {
                        try {
                            var container = document.getElementById("vgt-nuke-container");
                            if(container) {
                                // Verschiebe Container auf Body-Level
                                if (document.body && container.parentNode !== document.body) {
                                    document.body.appendChild(container);
                                }
                                // WP-Reste verstecken, ohne unser Styling zu zerstören
                                if (document.body) {
                                    var children = document.body.children;
                                    for (var i = 0; i < children.length; i++) {
                                        if (children[i] !== container && children[i].tagName !== "SCRIPT" && children[i].tagName !== "STYLE") {
                                            children[i].style.display = "none";
                                        }
                                    }
                                    document.documentElement.style.background = "#050507";
                                    document.body.style.background = "#050507";
                                }
                            }
                        } catch(e) {}
                    };
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", executeNuke);
                    } else {
                        executeNuke();
                    }
                })();
                </script>
            </div>',
            htmlspecialchars( $violation, ENT_QUOTES, 'UTF-8' ),
            htmlspecialchars( $caller, ENT_QUOTES, 'UTF-8' ),
            htmlspecialchars( $details, ENT_QUOTES, 'UTF-8' ),
            htmlspecialchars( $incident_hash, ENT_QUOTES, 'UTF-8' )
        );
    }
}