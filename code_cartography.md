# Code Cartography: GeDefense WP – Open Core (v7.6.1)

This document provides a comprehensive architectural map of the **GeDefense WP - Open Core** codebase. It outlines subsystem boundaries, class responsibilities, execution phases, and data flows to accelerate developer onboarding and code auditing.

---

## 🏛️ 1. Architecture Overview & Ignition Protocol

GeDefense WP operates as an operating-system-style security kernel with strict deterministic phases:

```
[ Incoming Request ]
        │
  [ Phase 1: Pre-Flight Kernel (Early Execution) ]
        ├── Cerberus    (L0 Perimeter Drop, O(1) Memory Lookup vor DB)
        ├── Zeus        (Pre-Boot 6G WAF, Bot Blacklist & Sanitization)
        ├── AEGIS       (Deep Packet Inspection: SQLi, XSS, RCE, LFI, Deserialization)
        ├── Prometheus  (Behavioral Heuristics, IP/Subnet Threat Scoring & Decay)
        ├── Nemesis     (Deception Grid, Micro-Tarpits & Decoy Routing)
        ├── Morpheus    (Runtime Application Self-Protection / RASP Stack Hypervisor)
        └── Gorgon      (Encrypted Telemetry & Node Synchronization)
        │
  [ Hook: plugins_loaded ]
        │
  [ Phase 2: Invariant & Hardening Subsystems ]
        ├── Self-Integrity  (Merkle-Tree Verification via VIS_MANIFEST_DIGEST)
        ├── Titan           (Hardening: User 1 Ghosting, XML-RPC Lock, Editor Disable)
        ├── Hades           (Identity Stealth, Path Cloaking & 404 Mimicry)
        ├── Airlock         (Ingress File Streaming & Polyglot Payload Inspection)
        ├── Ghost Trap      (Dynamic Decoy Honeypots & Perimeter Trigger)
        ├── Styx            (Zero-Trust Egress Whitelist & Outbound Interception)
        ├── Chronos         (Autonomous Background Scheduler & File Integrity Scanner)
        ├── Key Vault       (Hardware/Software Libsodium & AES-256-GCM Cryptographic Vault)
        ├── Integration Bus (Normalized Security Event Backbone & Event Bus)
        ├── Module Registry (Open Core Dynamic Add-On Hub for VLP, Builder & SEO)
        └── Dashboard       (Glassmorphic Admin UI & Real-Time Telemetry Matrix)
```

---

## 📁 2. File & Directory Cartography

### 2.1 Root Bootstrap & Kernel Core
*   **`gedefense-wp.php`** — Primary plugin entrypoint. Declares constants, double-load guards, product metadata (`AGPL-3.0-or-later`), and the cryptographic trust anchor `VIS_MANIFEST_DIGEST`.
*   **`class-vis-bootstrapper.php`** (`VIS_Bootstrapper`) — Master kernel orchestrator. Executes `engage_phase_1()` and `engage_phase_2()`, mounts autoloader, fail-close guards, and UI menus.
*   **`class-vis-schema.php`** (`VIS_Schema`) — Database schema manager. Creates and migrates `vis_apex_bans`, `vis_omega_logs`, and telemetry tables with zero-overhead indexing.
*   **`class-vis-vault.php`** (`VIS_Vault`) — Core Libsodium / AES-256-GCM encryption manager with HKDF key derivation and authenticated data binding.

---

### 2.2 Core Utilities & Shared Foundations (`includes/core/`)
*   **`class-vis-security.php`** (`VIS_Security`) — Zero-trust primitives:
    *   `pinned_https_get()`: DNS-rebinding-resistant HTTPS transport with `CURLOPT_RESOLVE` and post-handshake peer IP verification.
    *   `client_ip()`: Hardened deterministic IP resolver with Cloudflare IPv4/IPv6 CIDR validation.
    *   `validate_hades_gate()`: Timing-safe HMAC-SHA256 cookie validation for stealth gates.
    *   `jailed_path()`: Filesystem sandbox enforcing strict path jail boundaries.
*   **`class-vis-module-integrity.php`** (`VIS_Module_Integrity`) — Cryptographic invariant checker calculating SHA-256 Merkle-style root hashes over all 23 core components.
*   **`class-vis-module-registry.php`** (`VIS_Module_Registry`) — Open-Core extension manager. Resolves, checks, and manages dynamic Add-On modules (VLP, Builder, SEO).
*   **`class-vis-i18n.php`** (`VIS_I18n`) — Native Zero-Overhead Multi-Language Translation Matrix (German / English) with top-bar switcher.
*   **`class-vis-event-bus.php`** (`VIS_Event_Bus`) — High-throughput event emitter recording standardized threat records.
*   **`class-vis-security-health.php`** (`VIS_Security_Health`) — Audit-critical invariant engine evaluating host environment stability.
*   **`class-vis-security-center.php`** (`VIS_Security_Center`) — Central self-test and operational snapshot reporter.
*   **`class-vis-ai-gateway.php`** (`VIS_AI_Gateway`) — Isolated transport adapter for Groq LLM API queries with bounded memory buffers.

---

### 2.3 Defense Enclave & Subsystem Modules (`includes/modules/`)

#### Layer 0 / Pre-Boot Filtering:
*   **`cerberus/`** (`VIS_Cerberus`) — Zero-Cost L0 Perimeter Firewall:
    *   Runs at `plugins_loaded` priority `-9999` before WordPress DB initialization.
    *   L1 Memory Cache lookups deliver **0.08 ms** request drops for banned IPs.
    *   Compiles and exports OS-level firewall rules (`nginx_deny.conf`, `nftables_drop.map`, `htaccess_deny.conf`).
*   **`zeus/`** (`VIS_Zeus`) — Pre-Boot 6G WAF & Request Sanitizer:
    *   Filters malformed query strings, rogue user-agents, and author enumeration before application boot.

#### Layer 2-3 / Ingress Inspection:
*   **`aegis/`** (`VIS_Aegis`, `VIS_Aegis_Oracle`) — Deep Packet Inspection (DPI) WAF:
    *   Two-Phase Pipeline: Fast atomic signature DFA matching followed by recursive normalization.
    *   Inspects GET, POST, JSON, Multi-Part, and HTTP Headers up to 15 levels deep.
    *   Normalizes SQL comment collapsers, Unicode homoglyphs, and quote-slash slicing.
*   **`prometheus/`** (`VIS_Prometheus`) — Cognitive Behavioral Profiler:
    *   Dynamically scores IP and `/24` subnet entropy.
    *   Implements real-time score decay algorithms and atomic MySQL `GET_LOCK` spin-locks.
    *   Real-time malware signature engine intercepting PHP webshell patterns.

#### Layer 4-5 / Hardening & Stealth:
*   **`titan/`** (`VIS_Titan`) — WordPress Kernel Hardening:
    *   Masks User ID 1, blocks REST API user enumeration, enforces `DISALLOW_FILE_EDIT`, strips version headers.
*   **`hades/`** (`VIS_Hades`) — Admin Cloaking & Route Concealment:
    *   Hides `/wp-admin` and `wp-login.php` behind cryptographic handshake parameters.
    *   Answers unauthorized access with authentic Nginx/Apache 404 error responses.

#### Layer 6-7 / RASP & Active Deception:
*   **`morpheus/`** (`Vis_Morpheus`, `Morpheus_Hypervisor`) — Runtime Application Self-Protection (RASP):
    *   Tracks live PHP call-stacks to verify executing plugin context.
    *   Isolates SQL DML operations on `wp_users` and blocks network SSRF on cloud metadata IPs (`169.254.169.254`).
*   **`nemesis/`** (`VIS_Nemesis`) — Asymmetric Cyber Deception Grid:
    *   Returns bounded decoy responses without retaining PHP workers.
    *   Injects cryptographic HMAC-SHA256 canary tracking tokens and polymorphic frontend poisoning.
    *   Enforces a defensive-only response boundary with no response bombs or hack-back payloads.
*   **`trap/`** (`VIS_Ghost_Trap`) — Dynamic Decoy Honeypots:
    *   Serves dynamic bait files (`.env`, `.sql`, `.bak`) and triggers instant Cerberus perimeter bans.
*   **`airlock/`** (`VIS_Airlock`, `VIS_Airlock_Scanner`) — Ingress Upload Sanity Guard:
    *   Verifies binary magic bytes, cleans SVG XML against XSS/XXE, and scans entire file streams for polyglot PHP payloads.
*   **`styx/`** (`VIS_Styx`) — Outbound Exfiltration Shield:
    *   Intercepts `pre_http_request` with a strict Zero-Trust destination whitelist.

#### Automation, Vault & Audit:
*   **`chronos/`** (`VIS_Chronos`) — Autonomous Background Integrity Daemon.
*   **`includes/scanner/class-vis-scanner-engine.php`** — Resumable path-jailed integrity orchestration with append-only NDJSON state and guarded baseline commits.
*   **`includes/scanner/class-vis-malware-engine.php`** — Shared zero-dependency detector kernel for Airlock and Integrity/Chronos.
*   **`includes/scanner/detectors/`** — PHP lexical-flow, MIME/polyglot, SVG/XML, archive and path-context analysis.
*   **`includes/scanner/storage/class-vis-quarantine-store.php`** — Atomic private quarantine vault for high-confidence executable findings.
*   **`vault/`** (`VIS_Key_Vault`) — Authenticated Libsodium KMS for API secrets.
*   **`oracle/`** (`VIS_Oracle`) — 12-vector system security audit engine.
*   **`filesystem/`** (`VIS_Filesystem_Guard`) — Static file permission and CHMOD auditor.

---

### 2.4 Command Dashboard & Telemetry Matrix (`includes/dashboard/`)
*   **`class-vis-dashboard-view.php`** — Master view renderer, topbar SVG injector, and tab controller.
*   **`class-vis-dashboard-settings.php`** — Configuration sanitization engine with strict whitelist validation.
*   **`class-vis-dashboard-ajax.php`** — AJAX endpoint hub (scan bridges, IP unbanning, hardened Add-On ZIP uploader).
*   **`views/`** — Modular dashboard templates:
    *   `view-overview.php` (Command Center)
    *   `view-aegis.php` (WAF & DPI Matrix)
    *   `view-prometheus.php` (Cognitive AI & Threat Horizon)
    *   `view-trinity.php` (Defense Interlock Matrix)
    *   `view-morpheus.php` (RASP Sandboxing)
    *   `view-nemesis.php` (Bounded Defensive Deception)
    *   `view-modules.php` (Add-On Hub & ZIP Package Manager)
    *   `view-setup_wizard.php` (7-Step Interactive Setup Wizard)

---

### 2.5 Test Harness & Regression Suite (`scripts/`)
*   **`generate-module-manifest.php`** — Calculates SHA-256 Merkle-tree root hashes over all components to build `integrity/module-manifest.json`.
*   **`integrity-regression.php`** — Verifies cryptographic trust anchor against current codebase.
*   **`security-regression.php`** — Lints all PHP sources and validates strict type compliance.
*   **`aegis-regression.php`** — Validates WAF payload normalization and attack vectors.
*   **`morpheus-regression.php`** — Tests RASP AST parsing and SQL isolation policies.
*   **`sentinel-threat-benchmark.php`** — Runs 87+ real-world attack vectors against the defense matrix.

---

## 🔒 3. Design Invariants & Security Principles

1.  **Zero-Dependency Philosophy:** 0 Composer libraries, 0 external JS CDNs. 100% GDPR compliant and pure PHP 8.1+.
2.  **Fail-Closed Architecture:** Critical subsystems panic with HTTP 503 instead of degrading to an insecure bypass state.
3.  **Memory-First Execution:** IP bans and regular expression DFA lookups resolve from RAM (APCu / Object Cache) with minimal database I/O.
4.  **Constant-Time Cryptography:** All secret and token comparisons use `hash_equals()` to eliminate timing side-channel attacks.
