# Code Cartography: VGT Sentinel V7 (Core Map)

This mapping document outlines the file layout and component associations across the standard plugin (`vgt`) and MU-plugin (`visiongaia-integrity`) configurations. It is designed to save context tokens and accelerate directory traversal.

---

## 1. System Orchestration & Core Entrypoints

These files bootstrap the plugin, hook into WordPress hooks, establish database schemas, and initialize the main configuration registry.

*   **Plugin Bootstraps:**
    *   [0vision-integrity-sentinel.php](file:///c:/Users/Masterboard/Downloads/vgt/0vision-integrity-sentinel.php) — Entry point for the standard plugin version. Registers namespaces and triggers the main bootstrapper.
    *   [visiongaia-integrity.php](file:///C:/Users/Masterboard/Downloads/visiongaia-integrity/visiongaia-integrity.php) — Entry point for the MU-plugin version. Bootstraps in mu-plugins directory.
*   **Orchestration:**
    *   [class-vis-bootstrapper.php](file:///c:/Users/Masterboard/Downloads/vgt/class-vis-bootstrapper.php) (`VIS_Bootstrapper`) — The nervous system. Configures menu routes, enqueues styles/scripts, handles AJAX routes, and loads all active modules.
    *   [class-vis-schema.php](file:///c:/Users/Masterboard/Downloads/vgt/class-vis-schema.php) (`VIS_Schema`) — Sets up and updates custom SQL database tables (e.g. alerts logs, threat levels).
    *   [class-vis-vault.php](file:///c:/Users/Masterboard/Downloads/vgt/class-vis-vault.php) (`VIS_Vault`) — Core cryptography engine. Handles physical storage encryption, salt isolation, and secret binding.
    *   `includes/core/class-vis-security.php` (`VIS_Security`) — Shared safe URL, path jail, rate-limit, and request security primitives.
    *   `includes/core/class-vis-event-bus.php` (`VIS_Event_Bus`) — Normalized security event backbone backed by the Sentinel log table.
    *   `includes/core/class-vis-security-health.php` (`VIS_Security_Health`) — Static invariant scanner for audit-critical hardening rules.
    *   `includes/modules/gorgon/` (`Vis_Gorgon`) — Sole authoritative Gorgon/Nexus AJAX and sync controller.

---

## 2. Active Defense & Deception Enclave (`includes/modules/`)

This directory houses the autonomous protection packages. Each subdirectory represents a specialized security module.

*   **AEGIS Firewall & Oracle:**
    *   [class-vis-aegis.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/modules/aegis/class-vis-aegis.php) — The Web Application Firewall (WAF). Intercepts HTTP headers, GET/POST payloads, queries, and filters out SQLi, XSS, and LFI attempts.
    *   [class-vis-aegis-oracle.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/modules/aegis/class-vis-aegis-oracle.php) — Intelligent analysis framework for suspicious payloads, performing Zero-day heuristic checks.
*   **PROMETHEUS Malware Engine:**
    *   [class-vis-prometheus.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/modules/prometheus/class-vis-prometheus.php) — Real-time backend malware scanner. Matches PHP code hashes against signatures and blocks execution of quarantined files.
*   **NEMESIS Deception Grid:**
    *   [class-vis-nemesis.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/modules/nemesis/class-vis-nemesis.php) — Spoofs system information. Creates virtual honeypots, injects dummy login fields, and displays fake database/PHP errors to confuse scanners.
*   **Other Shielding Modules:**
    *   `cerberus/` — Manages IP ban lists and rate limits.
    *   `airlock/` — Request isolation and sandbox wrappers.
    *   `hades/` — Stealth utilities (hides WordPress headers, meta generators, and indicators).
    *   `morpheus/` — Sandbox environment runner.
    *   `titan/` — WordPress hardening (disables XML-RPC, REST endpoints, and themes/plugins modifications).
    *   `kernel/` — Low-level interceptors and priority hook managers.
    *   `styx/` — Directory security and path restrictions.

---

## 3. Intelligence Matrix & Command Dashboard (`includes/dashboard/`)

Renders the glassmorphic administration panels, controls active switches, handles AJAX states, and displays analytics.

*   **Dashboard Logic & Assets:**
    *   [class-vis-dashboard-view.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/dashboard/class-vis-dashboard-view.php) — Renders the sidebar navigation, layout wrappers, and injects dynamic views.
    *   [class-vis-dashboard-core.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/dashboard/class-vis-dashboard-core.php) — Registers submenus and setups admin pages structure.
    *   [class-vis-dashboard-ajax.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/dashboard/class-vis-dashboard-ajax.php) — Back-end targets for dashboard actions (e.g. toggles, database cleanups, manual scans).
    *   [assets/css/vis-dashboard.css](file:///c:/Users/Masterboard/Downloads/vgt/assets/css/vis-dashboard.css) — Stylesheet containing design variables, layouts, and animations.
    *   [assets/js/vis-dashboard.js](file:///c:/Users/Masterboard/Downloads/vgt/assets/js/vis-dashboard.js) — Interface event listeners (accordion toggles, Ajax hooks).
*   **Views & Panels (`includes/dashboard/views/`):**
    *   `view-overview.php` — Command Center. Threat feeds, system health grids, and Zero-day accordion panels.
    *   `view-systatus.php` — System Status. Displays diagnostic telemetry, memory constraints, and CPU limits.
    *   `view-thread.php` — Threat Matrix visualization dashboard.
    *   `view-oracle.php` — Settings and triggers for the Aegis Oracle scanners.
    *   `view-logs.php` — Visual log parser and log export interface.

---

## 4. VisionGaia SEO & GEO Suite (`includes/VisionGaiaSEO/`)

Implements landing page optimization, location targeting, automated search console indexation, and vanity redirect routing.

*   **Subsystem Bootstrap:**
    *   [visiongaia-seo-architect.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/visiongaia-seo-architect.php) — Loads components.
    *   [class-vg-seo-settings.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/class-vg-seo-settings.php) — Option loader and default settings builder.
*   **SEO & GEO Engines (`includes/VisionGaiaSEO/includes/`):**
    *   [class-vg-geo-injector.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/includes/class-vg-geo-injector.php) — Matches visitor locations to output custom titles, location snippets, coordinates, and regional pages.
    *   [class-vgt-meta.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/includes/class-vgt-meta.php) — Gutenberg integration. Registers post/page meta boxes for titles, keywords, coordinates, and landing page details.
    *   [class-vgt-redirect.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/includes/class-vgt-redirect.php) — Redirect engine, matching regex strings and routing vanity URLs.
    *   [class-vg-vault-bridge.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/includes/class-vg-vault-bridge.php) — Integrates cryptographically locked key states to SEO settings.
    *   [class-vgt-sitemap.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/includes/class-vgt-sitemap.php) — Dynamic XML sitemap generator.
*   **SEO Administration (`includes/VisionGaiaSEO/includes/Dashboard/`):**
    *   [Dashboardui.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/includes/Dashboard/Dashboardui.php) — Frame layout for the SEO dashboard.
    *   [Redirects.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/includes/Dashboard/Redirects.php) — Redirect rules GUI.
    *   [Sitemap.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VisionGaiaSEO/includes/Dashboard/Sitemap.php) — Sitemap generation and search engine ping manager.

---

## 5. Vision Legal Pro (VLP) Subsystem (`includes/VLP/`)

Configures legal declarations, automated cookie consents, privacy-shield blockers, and translation frameworks.

*   [vision-legal-pro.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VLP/vision-legal-pro.php) — Main VLP loader.
*   **Admin Dashboard:**
    *   [class-vlp-admin-dashboard.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VLP/includes/admin/class-vlp-admin-dashboard.php) — Policy builder interface and scans analyzer.
*   **Translation Engine (`includes/VLP/includes/modules/lingua/`):**
    *   [class-vlp-lingua-groq.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VLP/includes/modules/lingua/class-vlp-lingua-groq.php) — Translates legal documents using cloud-hosted LLM endpoints.
    *   [class-vlp-lingua-nexus.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/VLP/includes/modules/lingua/class-vlp-lingua-nexus.php) — Manages translated text nodes and links them back to appropriate languages.

---

## 6. VGT Landing Page Builder (`includes/builder/`)

A drag-and-drop landing page editor providing layout options, templating mechanisms, and automated server migration.

*   [builder.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/builder/builder.php) — Builder loader.
*   **Builder Engines (`includes/builder/inc/`):**
    *   [class-vgt-admin.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/builder/inc/class-vgt-admin.php) — Builder dashboard settings, page registrations, and templates selector.
    *   [class-vgt-ajax.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/builder/inc/class-vgt-ajax.php) — Save, delete, load, and edit routines.
    *   [class-vgt-frontend.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/builder/inc/class-vgt-frontend.php) — Shortcode interpreter and style assembler.
    *   [class-vgt-migration.php](file:///c:/Users/Masterboard/Downloads/vgt/includes/builder/inc/class-vgt-migration.php) — Importer/exporter of complete layout databases.
*   **Builder Views (`includes/builder/views/`):**
    *   `editor-ui.php` — Visual editor canvas interface.
    *   `dashboard.php` — Grid list of available layout blueprints.
