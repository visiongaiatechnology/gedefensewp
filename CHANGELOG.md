# Changelog — GeDefense WP Open Core

All notable changes to **GeDefense WP — Open Core** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [8.4.1] — 2026-10-21

### Security / Reliability Hotfix
- **Oracle distillate in AEGIS:** policy categories learned from the Oracle's production verdicts
  (Reconnaissance, SQLi, XSS, RCE, Obfuscation, Prompt Injection, Remote Asset Hijacking) are now
  deterministic, auditable local rules. 78.6% of historically AI-classified blocks are resolved
  before a model call.
- **AI gate:** the Oracle is consulted only for sensitive routes and genuinely ambiguous input,
  with per-IP/global budgets; the API-outage fallback no longer fails closed unconditionally.
- **CERBERUS loopback guard:** proxy hops (`127.0.0.0/8`, `0.0.0.0`, `::1`, `::`) can no longer be
  banned, preventing a single tarpit event from locking down an entire reverse-proxied site.
- **XDR deadlock retry:** incident and response updates retry transient MySQL deadlocks.
- **Morpheus network shield:** null-URL hardening and self-host allowlist.
- **Event bus:** request dedupe, hourly flood notice, internal Morpheus context, CLI attribution and
  30-day retention for human-readable logs.
- **AEGIS telemetry:** route, user-agent, engine and AI-call count are persisted with each event.
- **NOC dashboard:** new AI-efficiency panel (local decisions, AI consultations, AI ratio).

### Fixed
- `VIS_Cerberus` namespace fatal in Prometheus mitigation.
- `started_at` vs `created_at` SQL error in XDR response escalation.
- Missing `vis_cerberus_strikes` schema on legacy installations.
- Prometheus/Nemesis dashboard `->ip`/`->message` warnings.
- Float-modulo deprecation, scanner read warnings, VLP null-buffer and builder CSP variable
  (edition-specific fixes ship in the internal MU build).

---

## [8.4.0] — 2026-10-21

### Security
- **PHP upload-progress hardening (`session.upload_progress`)**:
  - The Zeus environment manager enforces `session.upload_progress.enabled = Off` in the site `.user.ini`
    (idempotent, preserves foreign directives, independent of the WAF switch; immutable host files are
    reported as `blocked` instead of throwing on every request).
  - The security health dashboard verifies the **effective web-SAPI value** plus the `.user.ini` state;
    `blocked`/`pending` are reported as failures instead of a silent configuration claim.
  - `scripts/php-upload-progress-hardening-regression.php` covers rewrite, idempotency, WAF-off,
    state classification and health integration.
  - Preventive defense-in-depth for the announced PHP upload-progress RCE class; it does not replace
    the official PHP security update.
- **Login surface hardening**: the LoginPager logo mode hides the fallback title text
  (`text-indent`) so assistive technology and the visible surface only announce one brand name.

### Changed
- **ThroneGuard lock surface redesigned**:
  - Branded auth surface reusing LoginPager tokens (accent, background, logo, title).
  - Viewport-fixed centering, `min(460px, calc(100vw - 32px))` width, safe-area and
    `prefers-reduced-motion` support.
  - Clear session status, accessible error (`role="alert"`), password reveal, logout link,
    stronger focus states and 16px mobile inputs.
- **LoginPager centering fixed**:
  - `#login` is centered with fixed viewport positioning and `translate(-50%, -50%)`, so later
    stylesheets overriding `body.login` flex rules can no longer push the form to the top edge.
  - Logo mode hides the fallback title text to prevent double rendering.
  - Mobile: viewport-relative width, viewport-height cap and internal scrolling on short screens.

### Version
- Version bumped to `8.4.0` in the plugin entrypoint, `VIS_VERSION`, the WAF compiler
  (`COMPILER_VERSION`) and distribution documentation; module manifest and trust anchor regenerated.

---

## [8.3.0] — 2026-10-17

### Added & Enhanced
- **VGT Glass 2100 Sovereign Design System**:
  - Full-spectrum Glassmorphic redesign across all 34 dashboard modules, controls, Bento grids, and HUD widgets.
  - Strict hierarchical elevation model (Z0 to Z4) featuring specular 1px top-edge highlights (`rgba(255, 255, 255, 0.16)`), deep sapphire-obsidian radial backdrops, and velvety ambient glow.
  - Zero external third-party/CDN dependencies: 100% native system fonts (`ui-sans-serif`, `ui-monospace`) and pure, framework-free CSS.
  - Full accessibility compliance: dedicated styles for `@media (prefers-reduced-transparency: reduce)`, `@media (prefers-reduced-motion: reduce)`, and high-contrast `@media (forced-colors: active)`.
  - Complete elimination of all legacy, noisy grid lines and sci-fi raster backgrounds in favor of crystalline, readable telemetry surfaces.

- **Full-Width Real-Time Threat Map & Global Vector Tracking (`threat-map.php`)**:
  - Positioned prominently spanning 100% width above the Cockpit Protection Matrix & Cyber Defense Radar.
  - High-precision SVG Mercator world map projection (`900 x 583.08`) with 176 sovereign country vectors, tactical grid lines, and interactive country choropleth fills.
  - Dynamic quadratic Bézier curve trajectory arcs (`M x1 y1 Q cx cy x2 y2`) calculating parabolic attack vectors from origin coordinates directly into the sovereign server hub.
  - Live animated SVG projectile packets (`<animateMotion>`) and pulsating sonar rings color-coded by severity (`#ff3366` Critical/Blocked, `#ffaa00` Scan/Tarpit, `#00f3ff` Tracked/Monitored, `#00ffaa` Server Hub).

- **100% Local Autonomous BGP & GeoIP Binary-Search Engine (`class-vis-geo-resolver.php`)**:
  - Self-contained, sub-0.4ms $O(\log N)$ binary search engine operating over a packed 70.8MB local database (`data/geoip.csv`).
  - Zero cloud leaks: completely autonomous resolution of country code, region, latitude, longitude, ASN carrier, and autonomous system name without external API queries (no MaxMind web services, no IPInfo).
  - High-performance dual-tier cache: in-memory request cache and 7-day persistent WordPress transients.

- **Intelligent Threat Coordinate Clustering & Multi-Vector Aggregation**:
  - Automatically merges multiple threats sharing identical geographical coordinates into a consolidated cluster marker (`.vis-threat-marker.is-cluster`) with a live vector counter badge.
  - Eliminates overlapping SVG element hit-test collisions and hover jitter.
  - Unified multi-vector HUD popup: displays flag, carrier, ASN, and a scrollable list of all targeted URIs, modules, and intervention actions at that coordinate.

- **Universal 4-Way Dynamic Tooltip Positioning Engine**:
  - Mathematical 4-way viewport detection (`Above`, `Below`, `Right`, `Left`):
    - Automatically displays **Above** in lower latitudes when vertical headroom is ample.
    - Automatically flips **Below** in high northern latitudes (e.g., Scandinavia, northern Russia, Arctic).
    - Automatically positions **Sideways (Right or Left)** when vertical clearance is constrained (middle latitudes such as USA, Central Europe, India, China) with strict vertical clamping (`clampedY`) to prevent any clipping.
  - Smooth cursor tracking over large territorial geometries (`mousemove`) and 14px safety boundary edge clamping.

- **100% Genuine Telemetry Engine (Zero Simulation)**:
  - Completely purged all synthetic simulation loops, fake blips, and placeholder generators.
  - Telemetry is harvested exclusively from genuine database events (`vis_bans`, Sentinel, Prometheus, WAF intercept logs).
  - Lightweight, non-blocking AJAX polling (`wp_ajax_vis_get_live_threats`) checks for real attack events every 25 seconds without row duplication.
  - Authoritative "AIRSPACE SECURE · 0 THREATS" nominal state when no active threats are logged.

- **Full Multilingual Localization Matrix (DE / EN / RU)**:
  - 100% localized across all languages: comprehensive dictionary entries added to `de.php`, `en.php`, and `ru.php`.
  - Dynamic JSON i18n payload injection for zero-delay localized frontend rendering in Threat Map tooltips, badges, and streams.

- **Version Bump & Distribution Packaging**:
  - Version updated to `8.3.0` across plugin entrypoint (`vision-integrity-sentinel.php`), WAF compiler (`class-zeus-compiler.php`), and documentation.
  - Full deployment package `visiongaia-integrity.zip` compiled (11.79 MB including complete 70.8MB local GeoIP dataset).

---

## [8.2.6] — 2026-10-16

### Security & Hardening
- **Add-On Uploader Capability & System Policy Barrier (Privilege Escalation Prevention)**:
  - Enforced strict dual-capability authorization in `handle_upload_addon()` and `handle_uninstall_addon()`: requires both WordPress core `install_plugins` / `delete_plugins` capabilities AND ThroneGuard's Sovereign Master role (`VIS_Throne_Guard::MASTER_CAP` / `mcp_master_access`). Subsite administrators in Multisite networks and hardened admin accounts can no longer upload or extract unverified archives.
  - Enforced system file-mod policy: immediately terminates upload/uninstall requests with HTTP 403 `VGT_SECURITY_VIOLATION` whenever `DISALLOW_FILE_MODS` is enabled.
  - Normalized path traversal checks across Windows and Unix path separators (`str_replace('\\', '/', ...)`), blocking dot-dot patterns (`..`), root paths (`/`), and null bytes (`\0`).
  - Restricted archive directory structures to registered add-on namespaces (`['vlp', 'builder', 'visiongaiaseo', 'seo']`).
- **Hades Admin-Cloaking Stealth Verification**:
  - Maintained strict Zero-Trust perimeter cloaking and 404 mimicry on `/wp-admin/` without introducing unverified cookie bypasses.
  - Standardized administrative admission token renewals through defined cryptographic authentication handshakes (`?$admin_param=$admin_secret` and Zeus admission slugs).
- **Cryptographic Key Vault & Backward Compatibility**:
  - Protected legacy ciphertext migration: retained legacy fallback key infrastructure (`get_legacy_key()`) to guarantee transparent, lossless migration of existing customer data before retiring legacy keys.
- **Pre-Boot WAF Compiler Version Bump**:
  - Updated `VIS_Zeus_Compiler::COMPILER_VERSION` to `8.2.6` for self-healing automatic WAF re-compilation on boot.
- **Cryptographic Trust Anchor & Merkle Manifest Re-Sealing**:
  - Regenerated Merkle tree root manifest digests (`module-manifest.json`) and updated `VIS_MANIFEST_DIGEST` across all 27 core components in both Standalone (`gedefense-wp.php`) and MU-Core (`vision-integrity-sentinel.php`) editions with 100% pass across all 28 regression and benchmark suites.

### UI & Architecture
- **Dashboard UI Architecture & HTML5 Form De-Nesting**:
  - Eliminated nested `<form>` structures in `view-styx.php` that violated HTML5 specifications and caused browser form submission drops under shell-managed configuration views.
  - Full CSP compliance: completely removed inline `<style>` and `<script>` blocks from view templates; tab navigation animations (`.vgt-titan-nav a.is-active`, `.vgt-tab-pane.is-active`, `@keyframes vgt-tab-fade-in`) migrated cleanly to `assets/css/vis-dashboard-modern.css`.
  - Consolidated client-side tab switching into the registered external controller `views/styx/script.js` with deep-linking (`?styx_section=threat_intel` / `window.history.replaceState`).
  - Standardized and namespaced threat intelligence nonces to `_vis_threat_intel_sync_nonce` and `_vis_threat_intel_save_nonce`.
  - Dashboard UI regression suite (`dashboard-ui-regression.php`) now passes 100% with exit code 0.

---

## [8.2.5] — 2026-10-15

### Fixed & Enhanced
- **Zeus L0 Pre-Boot Route Contract Content-Type Fix (`CONTRACT_CONTENT_TYPE_REJECT`)**:
  - Fixed a false-positive HTTP `415` (`VGT_ZEUS_ADMISSION_DENIED: CONTRACT_CONTENT_TYPE_REJECT`) in compiled `zeus-waf.php` where routes configured with wildcard `allowed_content_types` (`['*']` or `['*/*']`, such as `/wp-admin/`) compared incoming `POST` `Content-Type` headers literally against `'*'` instead of permitting wildcard MIME types.
  - Expanded default WordPress Route Contracts (`wp_admin`, `admin_ajax`, `admin_post`, `wp_json`, `wp_login`) in `Zeus_Contracts` to explicitly support standard form, multipart, JSON, and text payloads with case-insensitive MIME normalization.
  - Added automatic Pre-Boot WAF compiler version self-healing (`COMPILER_VERSION = 8.2.5`): existing outdated `zeus-waf.php` kernels on disk automatically recompile on the first admin request.
- **ThroneGuard 12-Hour Master IP Omni-Whitelist (`AEGIS` + `Prometheus` + `ZEUS`)**:
  - Authenticating as a Sovereign Master (`mcp_master_access`) with the ThroneGuard Superkey (via Lockscreen Unlock, ThroneGuard Settings Save, or the dedicated 1-click **12h Omni-Whitelist** bar) automatically whitelists the Master's current IP for **12 hours (`43,200` seconds)** across **AEGIS**, **Prometheus**, and **ZEUS** (both L0 Pre-Boot `zeus-waf.php` and L7 `VIS_Zeus_Shield`).
  - **Triple-Guarded 12-Hour Automatic Expiration & Pruning**:
    - **Layer 0 Real-Time Lease Guard**: `zeus-waf.php` embeds per-IP lease expiration timestamps (`$tgLeases`) and immediately stops honoring expired leases at the exact second of expiry—even before any cron job executes.
    - **Phase-1 Bootstrapper Reconciliation (`VIS_Throne_Guard::reconcile_ip_leases`)**: Automatically prunes expired 12-hour Master IP leases from AEGIS, Prometheus, and ZEUS options and recompiles `zeus-waf.php` while preserving any static administrator whitelist entries (`preserve_existing`).
    - **Hourly WP-Cron & Instant Manual Revocation**: Added `vis_throneguard_prune_leases` background cleanup and a 1-click revocation button (`vis_throneguard_revoke_lease`) in the ThroneGuard cockpit.
- **Dashboard & Whitelist Synchronization Enhancements**:
  - Added a live **12h Master-Omni-Whitelist** countdown metric card and instant activation/revocation bar in `view-throneguard.php`.
  - Added a dedicated **ZEUS Whitelisted IPs / CIDRs** configuration field in `view-zeus.php` (`03 / Rate Budgeting`) synchronized with `Zeus_Config_Repository`.
  - Upgraded `VIS_Aegis::is_whitelisted()` and `VIS_Prometheus::is_whitelisted()` to support both newline/comma-separated IPs and CIDR ranges.
- **Cryptographic Trust Anchor & Integrity Sync**:
  - Regenerated Merkle tree root manifest digests across all 27 core components with 100% pass across `integrity-regression`, `zeus-nextgen-regression` (10/10), and `zeus-live-waf-regression` (36/36).

---

## [8.2.4] — 2026-10-12

### Added & Optimized
- **Zeus Pre-Boot Threat Intelligence Drop (`auto_prepend_file`)**:
  - Integrated zero-allocation Threat Intelligence screening directly into `zeus-waf.php` at Layer 0, executing **before WordPress application boot and before MySQL initialization**.
  - Incoming requests from documented C2 botnets, compromised hosts, Spamhaus DROP networks, and malicious nodes are terminated in **3.2 microseconds** via $O(\log N)$ binary search, dropping attacks with 0 MB WordPress RAM allocation and 0 database queries.
- **527 KB Packed-Binary Compilation Engine**:
  - Over 135,000 IPv4 threat addresses are compressed and compiled into a tightly aligned 527 KB binary blob (`threat_intel_v4.bin`) using 4-byte network-ordered unsigned integers (`pack('N', ip2long)`).
  - Memory-efficient binary search (`bsearch_v4`) executes in ~17 CPU operations directly in memory, achieving over **300,000 lookups per second per CPU core**.
- **Zero-Lock Atomic File & APCu Memory Swap**:
  - Feed synchronization and database updates run 100% lock-free: Styx writes the compiled binary blob and CIDR JSON to PID-isolated temporary files, completes full disk flush, and performs an **atomic inode pointer swap** (`rename`), eliminating file locks, read contention, and latency spikes during active DDoS attacks.
  - Concurrently updates the APCu shared memory cache (`apcu_store('vgt_threat_blob_v4', ...)`), ensuring zero-downtime, zero-lock pointer replacement across all PHP-FPM worker pools.
- **Cerberus & Styx Shared In-Memory Acceleration**:
  - Cerberus and Styx consume the shared packed-binary blob and APCu cache within the WordPress lifecycle, bypassing SQL lookups entirely for all IPv4 queries.
- **Cryptographic Trust Anchor & Integrity Sync**:
  - Regenerated Merkle tree root manifest digests across all 27 core components with 100% pass across integrity, security, Trinity, and integration regression suites.

---

## [8.2.3] — 2026-10-09

### Added & Enhanced
- **Differential Feed Ingestion & Stale Threat Pruning**:
  - Upgraded `vis_threat_intel` database schema with `feed_key VARCHAR(32)` and unique composite index `(feed_key, ip_or_cidr)` with automatic in-place migration for legacy tables.
  - Automated list swapping and stale threat pruning: At the end of each feed ingestion run, all records where `last_seen < $sync_start_time` are purged atomically (`DELETE ... WHERE feed_key = %s AND last_seen < %s`), ensuring delisted or remediated IPs are immediately expunged from the local blocklist.
  - Dynamic feed deactivation cleanup (`purge_inactive_feeds`): Disabling a feed in dashboard settings immediately purges all associated IPs and CIDR ranges from database and in-memory caches.
- **Cerberus Dynamic Threat-Cache Invalidation**:
  - Ingress bans for threat intelligence records are cached in Cerberus with a short 60s TTL (`_type = THREAT_INTEL`). On cache hit, Cerberus verifies database presence; if an IP was pruned or removed, Cerberus invalidates the cache instantly and unblocks legitimate visitors without delay.
- **Styx Egress Self-Sync (Fail-Closed Egress Bypass)**:
  - Styx analyzes call-stacks via `detect_origin()`, reliably identifying `ThreatIntelligence` internal fetch requests (`THREAT_INTEL` / `is_sync_in_progress()`).
  - Allows outbound HTTP connections strictly to 8 hardcoded, cryptographically verified feed domains (`feodotracker.abuse.ch`, `www.spamhaus.org`, `cinsscore.com`, `lists.blocklist.de`, `rules.emergingthreats.net`, `raw.githubusercontent.com`, `iplists.firehol.org`, `check.torproject.org`) even under strict fail-closed outbound policies, while rigorously blocking third-party plugins.

---

## [8.2.2] — 2026-10-05

### Added & Refactored
- **Dedicated Sub-Tab Navigation Architecture**:
  - Deconstructed the monolithic Styx settings page into two specialized sub-cockpits:
    - `🛡️ Outbound Shield & Traffic-Ledger`: Outbound HTTP security controls, domain whitelist, and outbound traffic inspection ledger.
    - `🌐 Threat Intelligence Feeds & C2-Abwehrnetzwerk (Opt-In)`: Full-width reputation matrix, 9 autonomous threat feeds, real-time node metrics, instant sync triggers, and egress/ingress enforcement toggles.
  - Zero-latency client-side tab switching via Vanilla JavaScript with smooth CSS transition effects.
  - Deep-linking and URL history synchronization (`?styx_section=threat_intel` / `window.history.replaceState`).

---

## [8.2.1] — 2026-10-01

### Added
- **Autonomous Multi-Feed Threat Intelligence**:
  - Implemented 100% Opt-In Threat Intelligence Engine synchronizing verified malicious nodes, botnet C2s, and attacker ranges every 12 hours.
  - Aggregates 9 authoritative global threat feeds: Feodo Tracker Botnet C2, Spamhaus DROP IPv4 & IPv6, CINS Army Badguys, blocklist.de All-Attackers, Emerging Threats Compromised IPs, IPsum Threat Intelligence, FireHOL Level 1 Netset, and Tor Bulk Exit Nodes.
- **SQLi-Immune Regex & Length Boundary Guard**:
  - All incoming feed data is strictly filtered through a multi-stage validation pipeline: length boundaries (3–49 chars), strict character whitelist regex (`^[0-9a-fA-F.:\/]+$`), and formal `filter_var(..., FILTER_VALIDATE_IP)` / subnet checks. Rejects all SQL tokens, control characters, null bytes, and script tags.
  - All database upserts executed using chunked batches (500 records/batch) via `$wpdb->prepare()`.
- **Styx & Cerberus Interlock**:
  - **Styx (Outbound Egress Shield)**: Intercepts and blocks outbound HTTP requests from plugins/themes targeting documented C2 and malware nodes.
  - **Cerberus (Inbound Perimeter)**: Blocks incoming connections from threat intelligence IPs at the gate with high-tech telemetry.
- **Styx Cockpit Management Panel**:
  - Added "03 / THREAT INTELLIGENCE MATRIX (OPT-IN)" with master toggle, granular feed selection, real-time node counters, and manual 1-click sync action with nonce verification.

---

## [8.2.0] — 2026-09-25

### Added & Overhauled
- **Corporate Landing-Page & Microsite Block Screen**:
  - Transformed both Cerberus IP bans and Prometheus AI predictive strikes into a full-fledged, professional corporate microsite / landing-page.
  - Custom logo, custom background artwork with adjustable darkening overlay (30%–98%), company headline, claim, description ("Who we are & what we do"), and core competence / USP feature cards.
  - Direct customer support hub: one-tap-to-call mobile button (`tel:`), 1-click email button (`mailto:`) with pre-filled subject including deterministic incident reference code (`PROM-XXXX-XXXX` / `CERB-XXXX-XXXX`), physical address, and operating hours.
- **De-escalating Transparent Security Box ("Darunter")**:
  - Positioned discreetly beneath corporate presentation with real-time incident telemetry and 1-click native clipboard copy button.
- **Cerberus Admin Branding Customizer (`view-cerberus.php`)**:
  - 4 dedicated configuration cards under `WP-Admin -> GeDefense / Sentinel -> Cerberus -> Block-Page Personalisierung & Branding`.

---

## [8.1.3] — 2026-09-18

### Fixed
- **Cerberus Static Invocation Patch & Dual-Mode Unban Hardening**:
  - Resolved fatal error `Non-static method VIS_Cerberus::unban_ip() cannot be called statically` with formal static delegation across PHP 8.0–8.4.

---

## [8.1.2] — 2026-09-12

### Added
- **Cerberus Cyberpunk Block Screen & Perimeter Response Hardening**:
  - Zero-dependency Cyberpunk / High-Tech Active Mitigation cockpit with real-time telemetry and deterministic Incident Reference codes.

---

## [8.1.1] — 2026-09-05

### Fixed & Hardened
- Early-bootstrap pluggable decoupling (`wp_parse_auth_cookie`), diagnostic transparency, TITAN admin autosave stability, and VLP ReDoS atomic group protection.

---

## [8.1.0] — 2026-08-20

### Major Release
- **ZEUS Next Generation**: Layer 0 pre-boot admission kernel with deterministic canonicalization, RFC host lock, route contracts, token-bucket rate limiter, and cryptographic admission tokens.
- **TRINITY Autonomous Closed-Loop XDR**: Two-way virtual route containment, hard semantic TTL engine, multi-sensor response actuators (Cerberus, Zeus Route, Zeus Admission, Morpheus, Styx, Plugin Isolation), and Merkle evidence root.
- **Next-Gen Cyber-Defense Dashboard**: Complete UI/UX refactoring, Security Center Assurance Plane with 21 deep checks, NOC topology visualizer, live sensor telemetry grid, and universal save pipeline.
- **100% 3-Language Localization (DE, RU, EN)**: Over 1,100 translated strings with dynamic client-side `#vsc-i18n` JavaScript bridge.
- **Official Documentation**: Complete `ARCHITECTURE.md` specification and 3 official PDF handbooks (German, Russian, English).

---

## [8.0.0] — 2026-07-15
- Sovereign Master Role (`master`) and Granular Admin Capability Matrix in ThroneGuard.
- Zero-Trust Superkey Vault and Lockscreen Overlay.
- Cyberpunk LoginPager with live 2-column cockpit simulator.
- 100% 3-Language Localization Matrix (DE, EN, RU).

---

## [7.6.1] — 2026-06-28
- Resumable indexing and accepted-baseline stability in Integrity Monitor.
- Persistent admin-IP protection gate.

---

## [7.6.0] — 2026-06-10
- Dedicated Trinity orchestration core for deterministic AEGIS, Prometheus, and Cerberus routing.
- Zero-dependency malware kernel shared by Airlock and Chronos.
