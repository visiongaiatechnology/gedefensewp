<div align="center">

# 🛡️ GeDefense WP — Open Core

### Sovereign WordPress Security Fabric & Pre-Boot Admission Kernel

[![Version](https://img.shields.io/badge/version-8.1.0_Open_Core-D4AF37?style=for-the-badge)](#)
[![License](https://img.shields.io/badge/license-AGPL--3.0--or--later-0B5FFF?style=for-the-badge)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1--8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=for-the-badge&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![Dependencies](https://img.shields.io/badge/external_PHP_dependencies-0-2EA44F?style=for-the-badge)](#zero-dependency-philosophy)
[![Modules](https://img.shields.io/badge/core_modules-19-111111?style=for-the-badge)](#core-module-matrix)
[![Architecture](https://img.shields.io/badge/architecture-multi--tier_security_kernel-111111?style=for-the-badge)](#architecture)
[![Strict Types](https://img.shields.io/badge/PHP-strict_types-5C2D91?style=for-the-badge)](#zero-dependency-philosophy)
[![Local First](https://img.shields.io/badge/design-local--first-2EA44F?style=for-the-badge)](#security-design-principles)

**PRE-BOOT ADMISSION · WAF · XDR · RASP · BEHAVIORAL HORIZON · DECEPTION · MERKLE INTEGRITY · EGRESS SHIELD · HARDENING · CRYPTOGRAPHIC VAULT · OPEN CORE**

</div>

---

> ### 📚 Offizielle Dokumentation & Handbücher / Official Documentation & Handbooks
>
> 🏛️ **Vollständige Architekturspezifikation**: [`ARCHITECTURE.md`](ARCHITECTURE.md) *(Detaillierte Systemebenen, Invarianten, Pre-Boot Kernel-Abläufe & Dashboard-Struktur)*  
>
> 📖 **GeDefense WP 8.1.0 Benutzer- & Administrationshandbuch (PDF)** — *Das offizielle Handbuch für Administratoren, Entwickler und Sicherheitsbeauftragte:*
> - 🇩🇪 **Deutsch**: [📘 GeDefense_WP_Handbuch_8.1.0_FINAL.pdf](GeDefense_WP_Handbuch_8.1.0_FINAL.pdf)
> - 🇷🇺 **Русский**: [📕 GeDefense_WP_Handbuch_8.1.0_RU.pdf](GeDefense_WP_Handbuch_8.1.0_RU.pdf)
> - 🇬🇧 **English**: [📗 GeDefense_WP_Handbook_8.1.0_EN.pdf](GeDefense_WP_Handbook_8.1.0_EN.pdf)
>
> *Hinweis: Das Handbuch bietet praxisnahe Anleitungen zur Konfiguration, Bedrohungsanalyse und Systemhärtung für alle Benutzergruppen.*

---

<img width="2338" height="1184" alt="image" src="https://github.com/user-attachments/assets/9fcc11ca-1e91-47e2-a19f-d8c875dd1e15" />


---

# 🚀 What's New in GeDefense WP 8.1.0

> **Changelog Overview**: Version 8.1.0 represents a massive architectural leap, introducing the **ZEUS Next Generation Pre-Boot Admission Kernel**, the **TRINITY Autonomous Closed-Loop XDR Fabric**, formalized **TITAN Assurance Lifecycles**, and comprehensive **Strict-Typing and Localization Refactoring** across the entire platform.

---

<img width="2332" height="1182" alt="image" src="https://github.com/user-attachments/assets/48715b64-eeda-4441-ab60-520f67d2cb9e" />


## ⚡ 1. ZEUS Next Generation — Pre-Boot Admission Control & Edge Defense Kernel
*Transformed ZEUS from a static filter into an ultra-fast, zero-allocation pre-boot admission control and edge defense kernel operating at Layer 0 prior to WordPress application boot.*

- **Deterministic Canonicalization Guard**: Enforces strict URL decoding invariants before routing. Rejects double-percent encoding (`%252f`), null-byte injections (`%00`), encoded directory slashes (`%2f`, `%5c`), unescaped backslashes, dot-segment traversals (`..`), duplicate path separators (`//`), and path depths exceeding 32 levels with immediate HTTP 400 rejection.
- **Host Header Invariant & RFC Host Lock**: Validates Host header structure, lengths, and characters. Includes configurable Host Lock modes (`DISABLED`, `AUDIT`, `REJECT 421`) strictly matching authorized canonical hostnames.
- **Request Envelope Firewall & Structural Ceilings**: Enforces permitted HTTP methods (`GET`, `POST`, `HEAD`, `OPTIONS`, `PUT`, `PATCH`, `DELETE` -> `405`) and hard structural boundaries: Query Length (`2048` chars -> `414`), Parameter Count (`100` -> `400`), Header Count (`50` -> `431`), Aggregate Header Size (`16 KiB` -> `431`), and Cookie Payload Size (`8 KiB` -> `400`).
- **Route Contracts Engine**: Enforces micro-firewall contracts per API route (e.g. `/wp-json/`, `/xmlrpc.php`, `/wp-login.php`) with exact/prefix path matching, allowed method sets, payload body size limits (HTTP `413`), MIME/content-type enforcement (HTTP `415`), query limits, cross-site mutation policy (blocking `Sec-Fetch-Site: cross-site` state changes with `403`), and route-specific rate budgets.
- **Zero-Allocation Request Budget Engine**: Fast token-bucket rate limiter operating in APCu / atomic memory files. Enforces actor IP ceilings (default `180` req/min) and `/24` (IPv4) / `/64` (IPv6) subnet ceilings (default `450` req/min) with selectable action modes: `THROTTLE` (HTTP `429`), `TEMPORARY_REJECT` (HTTP `503`), or `XDR_SIGNAL` (Audit/Telemetry only).
- **Cryptographic Admission Tokens & Clean URL Exchange**: Tamper-proof, short-lived HMAC-SHA256 admission tokens (`vgt1.<payload>.<sig>`) with surface binding (`admin`, `login`, `all`), cryptographic expiry, and single-use nonce replay protection. Seamlessly exchanges query tokens (`?vgt_adm=...`) for Secure, HttpOnly, SameSite=Strict cookies with instant HTTP 302 redirects.
- **Incident Lockdown & Fortress Isolation**: Emergency containment barrier requiring cryptographic admission tokens for all incoming requests, instantly mitigating volumetric DDoS and zero-day exploitation storms.
- **Tamper-Evident Flight Recorder (Blackbox Spool)**: Append-only event spool with rolling HMAC-SHA256 hash chains, 300-second flood coalescing (updating repeat event counters in-place), Windows-safe atomic rotation, and boot-time draining into the WordPress Event Bus.
- **Hardening Lab Microbenchmark & Self-Test**: Integrated sub-microsecond microbenchmark evaluator (achieving `150,000+` evals/sec) and automated 9-point self-test suite.

---

<img width="2329" height="1188" alt="image" src="https://github.com/user-attachments/assets/5fba284e-d2a2-403a-b220-6b7935efc5c6" />


## 🛰️ 2. TRINITY XDR — Autonomous Closed-Loop Extended Detection & Response
*Unified multi-sensor security event fabric correlating signals across AEGIS, Prometheus, Morpheus, Styx, Cerberus, and Zeus with deterministic, automated containment.*

- **Two-Way Virtual Emergency Route Containment**: When an active vulnerability or exploit is identified on a specific API endpoint (e.g. `/wp-json/vulnerable-plugin/v1/`), TRINITY XDR instantly isolates that specific route prefix at Layer 0 in `zeus-waf.php` with HTTP `503`, while keeping the entire rest of the website 100% online and operational.
- **Hard Semantic TTL Engine**: Automated responses (Single IP Bans, Subnet Containment, Zeus Route Containment, Mandatory Admission Enforcement, Morpheus Honey Overlays, Styx Egress Isolation) enforce exact semantic expiration timestamps (`expires_at`), releasing containment automatically without requiring WP-Cron execution.
  

<img width="2329" height="1182" alt="image" src="https://github.com/user-attachments/assets/ee26ac8c-74f7-4343-acde-543944d9bbbc" />


- **Configurable TTL Policy Presets & Fine-Tuning**:
  - `CONSERVATIVE`: 5 min Actor Ban / 10 min Subnet Containment / 5 min Route Isolation.
  - `BALANCED`: 15 min Actor Ban / 30 min Subnet Containment / 15 min Route Isolation.
  - `AGGRESSIVE`: 1 hour Actor Ban / 2 hours Subnet Containment / 1 hour Route Isolation.
  - `CUSTOM`: Granular per-action second-level TTL fine-tuning.
- **Granular Multi-Sensor Response Actuator Controls**: Fine-grained switches in the policy engine allowing administrators to selectively arm or disarm individual containment actuators (Cerberus IP Ban, Zeus Route Quarantine, Zeus Pre-Boot Admission, Morpheus Micro-Tarpit, Styx Virtual Shadowing, Plugin Isolation).
- **Containment Policy Modes & Escalation**:
  - `TEMPORARY_BY_DEFAULT`: Standard automated containment with hard TTL recovery.
  - `TEMPORARY_ONLY`: Strict policy prohibiting permanent edge bans.
  - `ALLOW_PERMANENT_EXPLICIT`: Permits administrative permanent ban escalation.
  - `Escalation Policy`: Repeat offenders within 24 hours receive an automatic 4x TTL multiplier.
- **Deterministic Response Idempotency & Rollback**: Response UUIDs are deterministically derived from `hash("incident|action|target")`. Multi-sensor signals reuse active response IDs, and expired responses are rolled back atomically with full ownership protection.
- **Tamper-Evident Evidence Root**: Correlated attack stories and security events are cryptographically sealed into Merkle evidence digests (`wp_vis_xdr_evidence`).

---

<img width="2331" height="1190" alt="image" src="https://github.com/user-attachments/assets/c4bb424b-0cde-4231-9e74-15dbfc0a48bc" />


## 🖥️ 3. Next-Gen Cyber-Defense Dashboard & HUD Overhaul
*Comprehensive visual, structural, and security refactoring of the entire administration interface into a responsive, high-contrast Cyber-Defense Cockpit.*

- **Unified Cyber-Defense HUD & Cockpit Styling**: High-contrast, SOC/NOC-grade dark theme featuring monospace telemetric typography, pulsing activity nodes, glitch headers, synchronized HUD cards, and unified glassmorphic containers across all 34 module views.
- **Universal Topbar & Form Security Synchronization**: Global action bar featuring unified configuration persistence (`vis-topbar-save`), automatic cryptographic CSRF tokens (`wp_nonce_field('vis_save_config')`) on every configuration view, and robust sub-tab preservation preventing unwanted redirects.

<img width="2326" height="1181" alt="image" src="https://github.com/user-attachments/assets/c8340475-e311-4ed3-b6a7-cc658c8efdeb" />

  
- **Security Center (Assurance Plane Cockpit)**:
  - **21 Deep Invariant & Architecture Checks**: Evaluates strict runtime baselines, storage path jails, privilege boundaries, Merkle root consistency, memory execution boundaries, and HTTP validation contracts in real time.
  - **Interactive Live Audit Runner**: Client-side audit execution engine with dynamic millisecond duration metering, live terminal log streaming, and reactive posture pills (`HARDENED`, `GUARDED`, `ATTENTION`).
  - **Visual Trust Boundaries & Capability Surface Matrix**: Graphical representation of data flows from untrusted ingress to encrypted storage, and permission zones categorized from Trust Core down to Application extensions.

 <img width="2321" height="1179" alt="image" src="https://github.com/user-attachments/assets/eb02fae0-93b3-447d-9728-a486c9d09a63" />

  
- **TRINITY XDR NOC Cockpit**:
  - Graphical real-time NOC topology schematic mapping interlocks between AEGIS, Prometheus, Nemesis, and Cerberus.
  - Threat vector distribution progress bar and live intercept audit feed.
  - **Dynamic Sensor Status Grid**: Real-time operational monitoring across all 6 detection sensors reflecting active vs. standby module states (`ZEUS 6G WAF`, `AEGIS DPI`, `PROMETHEUS AI`, `NEMESIS DECOY`, `AIRLOCK INGRESS`, `MORPHEUS RASP`).
- **Dynamic JavaScript Localization Bridge (`#vsc-i18n`)**: Seamless inline translation dictionary providing real-time internationalization for client-side terminal streams, toast notifications, and badge states without requiring page reloads.
- **System Status & Telemetry Cockpit**: Live audit overview verifying system health, RAM-cache coverage (APCu / Redis), PHP runtime configuration, and module load integrity for all 19 core security components.

---

<img width="2340" height="1184" alt="image" src="https://github.com/user-attachments/assets/1ddad410-6b5c-48bd-a5f4-a97e97bb4a7c" />


## 🏛️ 4. TITAN — Web Security Assurance & System Hardening
*Formalized web assurance lifecycle, advanced browser security policies, and application surface hardening.*

- **5-Stage Assurance Lifecycle**: Formalized states (`IDLE` -> `ANALYZING` -> `STAGING` -> `ENFORCING` -> `RECOVERED`) with atomic policy deployment and rollback.
- **Browser Security Policies**: Hardened HSTS Preload headers, Sandbox Origin Verification, Secure Fetch Metadata validation, and automatic suppression of discovery links, emojis, and asset versions.
- **Application Hardening**: Hardened REST user enumeration defense, author scan blocking, XML-RPC honeytraps, feed suppression, and login gatekeeping.

---

<img width="2327" height="1185" alt="image" src="https://github.com/user-attachments/assets/ce5ffb39-67cd-47f1-b384-b206c40e232d" />


## 🐺 5. CERBERUS & Edge Isolation
*Dynamic edge firewall synchronization and high-performance perimeter security.*

- **Dynamic XDR TTL Synchronization**: Edge firewall exports (Nginx `deny`, Apache `.htaccess`) dynamically verify active XDR response TTLs, ensuring temporary incident bans expire cleanly at the edge without manual rule cleanup.
- **Zero-Allocation CIDR Filtering**: High-speed memory-cache lookups supporting IPv4 `/24` and IPv6 `/64` subnets with Cloudflare trusted proxy CIDR resolution.

---

<img width="2329" height="1180" alt="image" src="https://github.com/user-attachments/assets/3af2406d-08d4-414e-bbfe-eb0aa4dc7258" />


## 🛡️ 6. MORPHEUS RASP & STYX Egress Shield
*In-memory execution self-protection and outbound zero-trust communication control.*

- **Morpheus RASP Memory Defense**: Call-stack inspection protecting sensitive WordPress database tables (`wp_users`, `wp_usermeta`) and core options (`siteurl`, `home`, `active_plugins`) from unauthorized privilege escalation or malicious plugin mutations.
- **Styx Outbound Egress Shield**: Intercepts `pre_http_request` hooks, enforcing outbound domain allowlisting, preventing data exfiltration, and blocking unauthorized WordPress core telemetry.

---

## 🌐 7. Complete 3-Language Localization (DE 🇩🇪, EN 🇬🇧, RU 🇷🇺)
- **100% Dictionary Coverage**: Mapped over 1,100 gettext phrases across all 34 dashboard views, settings panels, audit checks, and live telemetry cockpits into `de.php`, `en.php`, and `ru.php`.
- **Strict-Typing Sanitization & Escaping Audit**: Every view and AJAX endpoint refactored with strict PHP 8.1+ types, explicit string casting on numeric outputs, and comprehensive escaping (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`, `wp_nonce_field`).

---

## Overview

**GeDefense WP — Open Core** is a modular, zero-dependency WordPress security platform designed as a **multi-tier security kernel and active defense matrix** for PHP 8.1–8.4 and WordPress 6.0+.

Instead of treating WordPress security as a single firewall rule set, GeDefense WP combines multiple defensive layers into one coordinated request and runtime pipeline:

- pre-boot admission control and request envelope rejection;
- deep packet inspection Web Application Firewall;
- behavioral threat scoring and event horizon tracking;
- autonomous closed-loop XDR and virtual route containment;
- cryptographic self-integrity verification (Merkle roots);
- Runtime Application Self-Protection (RASP);
- ingress file and upload entropy inspection;
- WordPress hardening and anti-enumeration;
- dynamic admin-path cloaking;
- honeypots and cyber deception;
- zero-trust outbound egress control;
- autonomous background integrity scanning;
- cryptographic secrets storage; and
- security posture auditing.

The core is designed to remain **fully functional as an independent open-source security platform**. Optional modules can be integrated through the Open Core module registry.

> GeDefense WP is built around one principle: **a request must earn trust as it moves deeper into the application stack.**

---

## Table of Contents

- [Documentation & Handbooks](#-offizielle-dokumentation--handbücher--official-documentation--handbooks)
- [What's New in GeDefense WP 8.1.0](#-whats-new-in-gedefense-wp-810)
- [Architecture](#architecture)
- [Security Pipeline](#multi-phase-ignition-protocol)
- [Core Module Matrix](#core-module-matrix)
- [1. ZEUS — Pre-Boot Admission Kernel](#1-zeus--pre-boot-admission-kernel)
- [2. AEGIS — Deep Packet Inspection WAF](#2-aegis--deep-packet-inspection-waf)
- [3. PROMETHEUS — Behavioral Threat Horizon](#3-prometheus--behavioral-threat-horizon)
- [4. TRINITY XDR — Closed-Loop Response Fabric](#4-trinity-xdr--closed-loop-response-fabric)
- [5. Self-Integrity Engine](#5-self-integrity-engine)
- [6. MORPHEUS — Runtime Application Self-Protection](#6-morpheus--runtime-application-self-protection)
- [7. NEMESIS — Deception Grid](#7-nemesis--deception-grid)
- [8. TITAN — WordPress Hardening](#8-titan--wordpress-hardening)
- [9. HADES — Admin Stealth](#9-hades--dynamic-admin-stealth)
- [10. CERBERUS — Perimeter Firewall](#10-cerberus--perimeter-firewall)
- [11. AIRLOCK — Ingress File Inspection](#11-airlock--ingress-file-inspection)
- [12. GHOST TRAP — Honeypot Layer](#12-ghost-trap--honeypot-layer)
- [13. STYX — Outbound Egress Shield](#13-styx--outbound-egress-shield)
- [14. CHRONOS — Autonomous Scanner](#14-chronos--autonomous-scanner)
- [15. KEY VAULT — Cryptographic Secret Storage](#15-key-vault--cryptographic-secret-storage)
- [16. ORACLE — Security Audit Engine](#16-oracle--security-audit-engine)
- [17. THRONEGUARD — Sovereign Privilege Sentinel](#17-throneguard--sovereign-privilege-sentinel)
- [18. LOGINPAGER — Sovereign Login Surface](#18-loginpager--sovereign-login-surface)
- [19. Module Registry / Open Core](#19-module-registry--open-core-expansion)
- [Zero-Dependency Philosophy](#zero-dependency-philosophy)
- [Performance & Benchmarks](#performance)
- [Assurance & Regression Testing](#assurance--regression-testing)
- [Requirements](#requirements)
- [License](#license)
- [Changelog History](#changelog-history)

---

# Architecture

GeDefense WP is organized as a multi-tier security kernel operating before, during, and after normal WordPress execution.

All GeDefense-owned runtime APIs use the canonical `VisionGaia\GeDefense` namespace. Core, dashboard, scanner, and module symbols are cleanly separated. Global `VIS_` symbols remain fully ABI-compatible for WordPress hooks and third-party integrations.

```mermaid
flowchart TD
    A[Incoming HTTP / HTTPS Request]

    A --> L0[Layer 0: Pre-Boot Admission<br/>ZEUS + CERBERUS]
    L0 --> L1[Layer 1: Self-Integrity<br/>Merkle Trust Anchor]
    L1 --> L23[Layers 2-3: Ingress Inspection<br/>AEGIS WAF + PROMETHEUS]
    L23 --> L45[Layers 4-5: Deception & Hardening<br/>TITAN + HADES + NEMESIS + GHOST TRAP]
    L45 --> L67[Layers 6-7: Runtime & Egress<br/>MORPHEUS RASP + AIRLOCK + STYX]
    L67 --> WP[WordPress Core / Themes / Plugins]

    XDR[TRINITY XDR] -. 2-way containment .-> L0
    XDR -. behavioral correlation .-> L23
    XDR -. runtime overlay .-> L67

    CHR[CHRONOS] -. background integrity .-> WP
    ORA[ORACLE] -. posture audit .-> WP
    VLT[KEY VAULT] -. cryptographic secrets .-> L0
```

---

# Multi-Phase Ignition Protocol

Incoming requests traverse a deterministic multi-stage execution pipeline:

```text
[ INCOMING HTTP / HTTPS REQUEST ]
                 │
                 ▼
[ LAYER 0: PRE-BOOT ADMISSION CONTROL ]
  ├─ Host Header Invariant & RFC Host Lock (421)
  ├─ Canonicalization Guard (Traversals, Null-bytes, Double-encoding)
  ├─ Request Envelope Ceilings (Method, Query, Headers, Cookies)
  ├─ Route Contracts Engine (Methods, Body Bytes, Content-Types)
  ├─ Zero-Allocation Request Budgets (IP & Subnet Token Buckets)
  ├─ Cryptographic Admission Tokens (HMAC, Nonce Replay, Surface)
  ├─ TRINITY XDR Virtual Route Isolation (503)
  └─ Blackbox Tamper-Evident Flight Recorder
                 │
                 ▼
[ LAYER 1: SELF-INTEGRITY ENGINE ]
  └─ Merkle Root Verification & SHA-256 Manifest Trust Anchor
                 │
                 ▼
[ LAYERS 2–3: DEEP PACKET & BEHAVIORAL INSPECTION ]
  ├─ AEGIS Deep Packet Inspection (GET, POST, JSON, Headers, Multipart)
  └─ PROMETHEUS Behavioral Threat Scoring & Event Horizon
                 │
                 ▼
[ LAYERS 4–5: HARDENING & CYBER DECEPTION ]
  ├─ TITAN System Hardening & Anti-Enumeration
  ├─ HADES Dynamic Admin Route Cloaking
  ├─ NEMESIS Deception Grid & Cryptographic Canaries
  └─ GHOST TRAP Decoy Routes
                 │
                 ▼
[ LAYERS 6–7: RUNTIME APPLICATION PROTECTION & EGRESS ]
  ├─ MORPHEUS RASP (In-Memory DB Mutation & Option Guard)
  ├─ AIRLOCK Ingress File Sandbox (Entropy, Polyglots, SVG Sanitation)
  └─ STYX Outbound Zero-Trust Egress Shield
                 │
                 ▼
[ WORDPRESS APPLICATION EXECUTION ]
```

---

# Core Module Matrix

| # | Module | Security Role | Layer / Domain |
|---:|---|---|---|
| 1 | **ZEUS** | Pre-Boot Admission Control & Edge Defense Kernel | Pre-Boot / L0 |
| 2 | **AEGIS** | Deep Packet Inspection WAF | Ingress / L3-L7 |
| 3 | **PROMETHEUS** | Behavioral Threat Scoring & Network Horizon | Behavioral / L7 |
| 4 | **TRINITY XDR** | Autonomous Closed-Loop Response Orchestration | Cross-Layer XDR |
| 5 | **SELF-INTEGRITY** | Merkle-Based Cryptographic Core Verification | Integrity / L1 |
| 6 | **MORPHEUS** | Runtime Application Self-Protection (RASP) | Runtime / L7 |
| 7 | **NEMESIS** | Cyber Deception & Canary Grid | Deception / L7 |
| 8 | **TITAN** | WordPress Hardening & Web Assurance Lifecycle | Hardening / L4 |
| 9 | **HADES** | Dynamic Admin Path Cloaking | Identity / L7 |
| 10 | **CERBERUS** | Fast Perimeter Firewall & Dynamic Edge Rules | Pre-Boot / L0-L1 |
| 11 | **AIRLOCK** | File Upload & Entropy Inspection Sandbox | Ingress / L7 |
| 12 | **GHOST TRAP** | Honeypot & Decoy Route Engine | Deception / L7 |
| 13 | **STYX** | Outbound HTTP / Exfiltration Control Shield | Egress / L7 |
| 14 | **CHRONOS** | Autonomous Integrity & Filesystem Scanner | Background |
| 15 | **KEY VAULT** | Cryptographic Secret & Key Storage | Cryptography |
| 16 | **ORACLE** | Static Security & Posture Audit Engine | Audit |
| 17 | **THRONEGUARD** | Master/Admin Privilege Separation & Superkey | Identity / Privilege |
| 18 | **LOGINPAGER** | Local-First Login Surface & Glassmorphism Gateway | Identity / UI |
| 19 | **MODULE REGISTRY** | Open Core Expansion Hub | Extensibility |

---

# 1. ZEUS — Pre-Boot Admission Kernel

**Classification:** Layer 0 Pre-Boot Admission Control & Edge Defense Kernel  
**Core class:** `VisionGaia\GeDefense\Modules\Zeus\VIS_Zeus`  
**Path:** `includes/modules/zeus/class-vis-zeus.php`

ZEUS runs at the absolute edge of PHP execution via `auto_prepend_file` before WordPress boots. It determines if a request deserves to allocate memory and load the WordPress runtime.

### Key Capabilities

- **Deterministic Canonicalization Guard**: Validates request URIs before routing. Neutralizes polyglot traversals, double-encodings, null-bytes, and encoded slashes (`400 Bad Request`).
- **RFC Host Invariant & Host Lock**: Validates host envelopes, enforcing strict matching against canonical domain whitelists (`421 Misdirected Request`).
- **Route Contracts Engine**: Evaluates micro-firewall policies per route: HTTP methods (`405`), body limits (`413`), content types (`415`), query limits (`414`), cross-site mutation policies (`403`), and route-specific rate budgets (`429`).
- **Zero-Allocation Request Budgets**: High-speed rate limiting tracking client IPs and `/24` (IPv4) / `/64` (IPv6) subnets in APCu or atomic files with `THROTTLE`, `TEMPORARY_REJECT`, or `XDR_SIGNAL` actions.
- **Cryptographic Admission Tokens**: Generates and verifies HMAC-SHA256 tokens (`vgt1.<b64>.<sig>`) with surface binding, expiry, and single-use nonce replay protection.
- **Tamper-Evident Blackbox Flight Recorder**: High-speed append-only spool logging with rolling HMAC hash chains, 300s flood coalescing, and boot-time draining into the Event Bus.
- **Hardening Lab**: Microbenchmark suite measuring sub-microsecond evaluation speeds and a 9-point self-test gate.

---

# 2. AEGIS — Deep Packet Inspection WAF

**Classification:** Layer 3/7 Ingress Firewall & Protocol Analyzer  
**Core class:** `VisionGaia\GeDefense\Modules\Aegis\Aegis`  
**Path:** `includes/modules/aegis/class-vis-aegis.php`

AEGIS is the deep application-layer inspection engine, analyzing structured parameters, headers, and request bodies.

### Detection Matrix

- **SQL Injection**: UNION-based, blind, stacked, error-based, and time-based SQLi with comment-collapsing and homoglyph normalization.
- **Cross-Site Scripting (XSS)**: Reflected, stored, tag smuggling, and SVG event handlers.
- **Remote Code Execution (RCE)**: PHP execution constructs (`eval`, `assert`, `system`, `passthru`, `preg_replace /e`).
- **File Inclusion & Traversal**: `php://filter`, `data://`, `expect://`, LFI/RFI, and phar deserialization.
- **Recursive Inspection**: Deep analysis up to 15 levels into nested JSON, multipart boundaries, and URI parameters.

### AEGIS Oracle - KI 
- Anbindung an Groq
- OSS 20B Safeguard für Sicherheitschecks 
- [Datenblatt](https://console.groq.com/docs/model/openai/gpt-oss-safeguard-20b)

---

# 3. PROMETHEUS — Behavioral Threat Horizon

**Classification:** Layer 7 Behavioral Analysis & Threat Horizon Engine  
**Namespace:** `VisionGaia\GeDefense\Modules\Prometheus\Prometheus`

Prometheus aggregates behavioral signals across individual actors and `/24` network horizons over sliding time windows.

### Threat Scoring & Decay

- Dynamic threat scoring with automatic decay (`0.2 points / second`).
- Event horizon thresholds escalate suspicious actors into TRINITY containment or Cerberus perimeter drops.
- Correlates distributed scanners, rotating proxy swarms, and aggressive brute-force bots.

---

# 4. TRINITY XDR — Closed-Loop Response Fabric

**Classification:** Autonomous Multi-Sensor Response & Extended Detection Fabric  
**Namespace:** `VisionGaia\GeDefense\Xdr`

TRINITY XDR unifies telemetry from all defensive modules into an automated, closed-loop mitigation engine.

### Core Capabilities

- **Two-Way Virtual Route Containment**: Isolates compromised plugin routes at Pre-Boot Layer 0 via `Zeus_Xdr_Bridge` without affecting overall site availability.
- **Hard Semantic TTLs**: Enforces exact expiration timestamps for all mitigations, ensuring temporary bans and route containments expire cleanly without WP-Cron.
- **Configurable Presets & Modes**: Conservative, Balanced, Aggressive, and Custom TTL configurations with automatic 4x escalation multipliers for repeat offenders.
- **Merkle Evidence Root**: Cryptographically commits security event evidence chains into tamper-evident digests (`wp_vis_xdr_evidence`).

---

# 5. Self-Integrity Engine

**Classification:** Layer 1 Cryptographic Invariant Guard  
**Class:** `VisionGaia\GeDefense\Core\ModuleIntegrity`  
**Path:** `includes/core/class-vis-module-integrity.php`

The self-integrity engine continuously verifies GeDefense WP core files against an immutable SHA-256 cryptographic trust anchor.

- Constant-time verification (`hash_equals`).
- Immediate detection of tampered or modified core files.
- Fail-close protection preserving the host environment if core integrity is compromised.

---

# 6. MORPHEUS — Runtime Application Self-Protection

**Classification:** Layer 7 In-Memory Execution Protection  
**Namespace:** `VisionGaia\GeDefense\Modules\Morpheus\Morpheus`

Morpheus monitors runtime memory and execution call-stacks during active WordPress execution.

- **DML Database Protection**: Guards sensitive tables (`wp_users`, `wp_usermeta`) against unauthorized password tampering or privilege elevation.
- **Critical Option Sentinel**: Monitors and protects `siteurl`, `home`, and `active_plugins`.
- **SSRF & Network Jail**: Blocks unauthorized outbound requests targeting local or cloud metadata endpoints (`127.0.0.1`, `169.254.169.254`).

---

# 7. NEMESIS — Deception Grid

**Classification:** Layer 7 Cyber Deception & Canary Matrix  
**Namespace:** `VisionGaia\GeDefense\Modules\Nemesis\Nemesis`

Nemesis provides active defensive deception, luring automated scanners away from real application assets.

- Bounded decoy responses for sensitive targets (`.env`, `wp-config.php.bak`, `phpmyadmin`).
- HMAC-backed cryptographic canary tokens for leak tracing.
- Zero worker blocking: no slowloris delays or dangerous terminal payloads.

---

# 8. TITAN — WordPress Hardening

**Classification:** Layer 4 System Hardening & Assurance Shield  
**Class:** `VisionGaia\GeDefense\Modules\Titan\Titan`  
**Path:** `includes/modules/titan/class-vis-titan.php`

Titan hardens the WordPress attack surface and enforces browser security policies.

- 5-stage assurance lifecycle (`IDLE`, `ANALYZING`, `STAGING`, `ENFORCING`, `RECOVERED`).
- Author and REST user enumeration suppression.
- XML-RPC lockdown, file editor restriction (`DISALLOW_FILE_EDIT`), and server signature stripping.
- HSTS Preload, Origin Sandbox verification, and Secure Fetch Metadata validation.

---

# 9. HADES — Dynamic Admin Stealth

**Classification:** Layer 7 Identity & Route Cloaking  
**Class:** `VisionGaia\GeDefense\Modules\Hades\Hades`  
**Path:** `includes/modules/hades/class-vis-hades.php`

Hades cloaks the administrative entry point (`wp-login.php`, `wp-admin`), returning a 404 response to unauthorized visitors without a valid cryptographic handshake.

---

# 10. CERBERUS — Perimeter Firewall

**Classification:** Layer 0/1 Instant Drop Barrier  
**Class:** `VisionGaia\GeDefense\Modules\Cerberus\Cerberus`  
**Path:** `includes/modules/cerberus/class-vis-cerberus.php`

Cerberus provides instant perimeter drops for known hostile actors at the earliest stage of execution.

- High-speed memory-cache lookups.
- IPv4 and IPv6 CIDR subnet matching.
- Cloudflare trusted proxy CIDR verification.
- Dynamic OS firewall rule synchronization (Nginx, Apache) reflecting active XDR TTL states.

---

# 11. AIRLOCK — Ingress File Inspection

**Classification:** Layer 7 Ingress Data Sandbox  
**Class:** `VisionGaia\GeDefense\Modules\Airlock\Airlock`  
**Path:** `includes/modules/airlock/class-vis-airlock.php`

Airlock inspects all uploaded files using content-aware heuristics rather than trusting extensions.

- Magic-byte verification and MIME type validation.
- SVG sanitization (neutralizing embedded scripts, event handlers, and XML entity expansion).
- Polyglot file detection and hidden PHP code extraction in image EXIF metadata.
- Entropy analysis for detecting obfuscated shells.

---

# 12. GHOST TRAP — Honeypot Layer

**Classification:** Layer 7 Active Lure Engine  
**Class:** `VisionGaia\GeDefense\Modules\Trap\GhostTrap`  
**Path:** `includes/modules/trap/class-vis-ghost-trap.php`

Ghost Trap injects realistic, hidden lure links and decoy assets (`database.dump`, `backup.sql`, `.aws/credentials`). Any client accessing these routes is instantly flagged as malicious automation.

---

# 13. STYX — Outbound Egress Shield

**Classification:** Layer 7 Egress Control & Supply-Chain Guard  
**Class:** `VisionGaia\GeDefense\Modules\Styx\Styx`  
**Path:** `includes/modules/styx/class-vis-styx.php`

Styx monitors and restricts outbound HTTP requests initiated by WordPress or installed plugins.

- Outbound domain allowlisting.
- Exfiltration protection against compromised plugins.
- Optional suppression of WordPress core telemetry.

---

# 14. CHRONOS — Autonomous Scanner

**Classification:** Asynchronous Background Integrity Daemon  
**Class:** `VisionGaia\GeDefense\Modules\Chronos\Chronos`  
**Path:** `includes/modules/chronos/class-vis-chronos.php`

Chronos executes scheduled, path-jailed background integrity scans across WordPress core, plugins, themes, and application files.

---

# 15. KEY VAULT — Cryptographic Secret Storage

**Classification:** Cryptographic Key Management  
**Class:** `VisionGaia\GeDefense\Modules\Vault\KeyVault`

The Key Vault provides authenticated, encrypted storage (Libsodium Secretbox / AES-256-GCM) for sensitive configuration values and module API keys.

---

# 16. ORACLE — Security Audit Engine

**Classification:** Static Security & Configuration Auditing  
**Class:** `VisionGaia\GeDefense\Modules\Oracle\Oracle`  
**Path:** `includes/modules/oracle/class-vis-oracle.php`

Oracle continuously audits 12 critical vectors of WordPress and PHP security posture, generating actionable hardening scores and diagnostic reports.

---

# 17. THRONEGUARD — Sovereign Privilege Sentinel

**Classification:** Layer 7 Privilege Boundary & Identity Hardening  
**Core class:** `VisionGaia\GeDefense\Modules\ThroneGuard\ThroneGuard` (`VIS_Throne_Guard`)  
**Path:** `includes/modules/throneguard/class-vis-throne-guard.php`

ThroneGuard establishes an immutable **Master** tier above standard WordPress administrators, allowing site owners to selectively restrict sensitive administrator capabilities (Plugins, Themes, User Elevation, Filesystem updates) and enforce zero-trust Superkey verification on administrative sessions.

---

# 18. LOGINPAGER — Sovereign Login Surface

**Classification:** Authentication Gateway & Visual Hardening  
**Core class:** `VisionGaia\GeDefense\Modules\LoginPager\LoginPager` (`VIS_LoginPager`)  
**Path:** `includes/modules/loginpager/class-vis-loginpager.php`

LoginPager transforms the default WordPress login screen into a zero-dependency, cyberpunk-styled glassmorphism gateway with live interactive preview controls and instant color presets.

---

# 19. MODULE REGISTRY — Open Core Expansion

**Classification:** Extensible Module Architecture  
**Class:** `VisionGaia\GeDefense\Core\ModuleRegistry`  
**Path:** `includes/core/class-vis-module-registry.php`

The module registry allows GeDefense WP to be seamlessly extended with modular applications such as Vision Legal Pro (VLP), Lightweight Builder, and GEO Architect.

---

# Zero-Dependency Philosophy

GeDefense WP strictly adheres to a zero-external-dependency architecture:

- **PHP 8.1–8.4** strict typing (`declare(strict_types=1);`).
- **Zero Composer runtime packages** in the core.
- **Zero external CDN dependencies** (all assets and icons are locally bundled).
- **Native cryptographic APIs** (Libsodium / OpenSSL).

---

# Performance

| Metric | GeDefense WP 8.1.0 |
|---|---:|
| **L0 Pre-Boot Evaluation Latency** | `< 0.05 ms` |
| **L0 Microbenchmark Throughput** | `150,000+ evals/sec` |
| **WAF Deep Inspection Latency** | `0.30 ms` |
| **Standby RAM Footprint** | `< 1.8 MB` |
| **External PHP Dependencies** | `0` |
| **Control Model** | Local / Self-Contained |

---

# Assurance & Regression Testing

The repository contains extensive automated test suites verifying all security invariants:

```bash
# Pre-Boot WAF Subprocess Regression Suite (30 tests)
php scripts/zeus-live-waf-regression.php

# Zeus NextGen Comprehensive Regression Suite (10 suites)
php scripts/zeus-nextgen-regression.php

# Trinity XDR Closed-Loop Release Blockers Suite
php scripts/xdr-release-blockers-regression.php

# Full Core Test Suites
php scripts/security-regression.php
php scripts/malware-scanner-regression.php
php scripts/throneguard-loginpager-regression.php
php scripts/aegis-regression.php
php scripts/trinity-regression.php
php scripts/morpheus-regression.php
php scripts/titan-regression.php
```

---

# Requirements

| Component | Requirement |
|---|---|
| **PHP** | 8.1–8.4 |
| **WordPress** | 6.0+ |
| **PHP Mode** | Strict Types (`declare(strict_types=1);`) |
| **External Libraries** | None (100% Zero-Dependency) |
| **Libsodium** | Recommended for native secretbox encryption |
| **Object Cache** | Optional; APCu enhances high-throughput L0 rate budgeting |

---

# License

GeDefense WP Open Core is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0-or-later)**. See [LICENSE](LICENSE) for full details.

---

# Changelog History

## 8.1.0 — ZEUS NextGen, TRINITY Closed-Loop XDR & Cyber Dashboard Overhaul
- **ZEUS Next Generation**: Layer 0 pre-boot admission kernel with deterministic canonicalization, RFC host lock, route contracts, token-bucket rate limiter, and cryptographic admission tokens.
- **TRINITY Autonomous Closed-Loop XDR**: Two-way virtual route containment, hard semantic TTL engine, multi-sensor response actuators (Cerberus, Zeus Route, Zeus Admission, Morpheus, Styx, Plugin Isolation), and Merkle evidence root.
- **Next-Gen Cyber-Defense Dashboard**: Complete UI/UX refactoring, Security Center Assurance Plane with 21 deep checks, NOC topology visualizer, live sensor telemetry grid, and universal save pipeline.
- **100% 3-Language Localization (DE, RU, EN)**: Over 1,100 translated strings with dynamic client-side `#vsc-i18n` JavaScript bridge.
- **Official Documentation**: Complete `ARCHITECTURE.md` specification and 3 official PDF handbooks (German, Russian, English).
*(See detailed changelog at top of README)*

## 8.0.0 — Apex Sovereign Cyber Defense & Privilege Boundary Architecture
- Sovereign Master Role (`master`) and Granular Admin Capability Matrix in ThroneGuard.
- Zero-Trust Superkey Vault and Lockscreen Overlay.
- Cyberpunk LoginPager with live 2-column cockpit simulator.
- 100% 3-Language Localization Matrix (DE, EN, RU).

## 7.6.1 — Scanner State Finalization
- Resumable indexing and accepted-baseline stability in Integrity Monitor.
- Persistent admin-IP protection gate.

## 7.6.0 — Trinity Deterministic Interlock
- Dedicated Trinity orchestration core for deterministic AEGIS, Prometheus, and Cerberus routing.
- Zero-dependency malware kernel shared by Airlock and Chronos.

---

<div align="center">

## GeDefense WP 8.1.0 — Open Core

**SOVEREIGN WORDPRESS SECURITY**

**ZEUS · AEGIS · PROMETHEUS · TRINITY XDR · MORPHEUS · NEMESIS · TITAN · HADES · CERBERUS · AIRLOCK · GHOST TRAP · STYX · CHRONOS · KEY VAULT · ORACLE · THRONEGUARD · LOGINPAGER**

**VisionGaia Technology**

</div>
