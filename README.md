# GeDefense WP - Open Core

[![License: AGPL v3](https://img.shields.io/badge/License-AGPLv3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.0-21759b.svg)](https://wordpress.org)

**GeDefense WP - Open Core** is a modular, zero-dependency, enterprise-grade WordPress security suite and active defense matrix. It combines high-performance Web Application Firewall (WAF) filtering, Runtime Application Self-Protection (RASP), active cyber deception (honeypots & canary tokens), real-time malware scanning, and cryptographic vault-backed secrets.

---

## 🛡️ Core Defense Architecture

GeDefense WP operates with a **Multi-Phase Ignition Protocol** designed like an OS security kernel:

```
[Incoming Request]
       │
       ▼
 1. Filesystem Guard       (Static Invariant Check)
 2. Cerberus Ban Matrix    (Instant IP-Drop before PHP execution)
 3. Aegis Firewall (WAF)   (Payload Inspection: SQLi, XSS, LFI, RCE & AI-Oracle Heuristics)
 4. Zeus Defender          (Brute-Force Protection, Dynamic Login-Renaming, XML-RPC Lock)
 5. Titan Hardening        (File-Edit Lockdown, User-Enumeration Block, Header Hardening)
 6. Hades Stealth Engine   (Admin-Path Obfuscation & Fingerprint Masking)
 7. Prometheus Scanner     (Real-time Signature Malware Scanner for Web Shells)
 8. Nemesis Deception Grid (Honeypots, Canary Tokens & Active Decoy Injections)
 9. Morpheus Sandbox       (RASP: SQL DML AST Protection, Network SSRF & State Guard)
10. Airlock / Ghost Trap   (Sandbox Isolation & Bot Traps)
11. Styx / Chronos         (Automated Rollups & Integrity Monitors)
12. Gorgon / Nexus         (Encrypted Telemetry & Sync Uplink)
```

---

## ⚡ Zero-Dependency Philosophy

- **No Composer Bloat**: 0 external PHP libraries or vendor dependencies.
- **Pure PHP 8.1+**: Strict types (`declare(strict_types=1)`), optimized PSR-4 autoloader, and native database drivers.
- **Cryptographic Security**: Hardware-accelerated Libsodium encryption (`sodium_crypto_secretbox`) with automatic secure fallback.
- **Ultra-Fast UI**: SVG inline rendering without external font or CDN dependencies (100% GDPR compliant).

---

## 🧩 Extensible Add-On Hub (Open Core)

GeDefense WP Core is fully functional and self-contained out of the box. Additional business and application modules can be installed as Add-On `.zip` packages via the **GeDefense Dashboard (`Modul-Verwaltung`)**:

- **Vision Legal Pro (VLP)**: GDPR compliance manager, local asset mirroring (Google Fonts/Analytics proxy), and privacy gatekeeper.
- **Lightweight Builder**: High-performance visual layout and component engine.
- **GEO Architect (SEO)**: Generative Engine Optimization (GEO) & entity injection for AI-powered search engines.

---

## 🧪 Assurance & Regression Testing

The repository includes a comprehensive regression testing gate:

```bash
php scripts/security-regression.php
php scripts/aegis-regression.php
php scripts/morpheus-regression.php
php scripts/integrity-baseline-regression.php
php scripts/sentinel-threat-benchmark.php
```

---

## 📄 License

GeDefense WP Core is open-source software licensed under the [GNU Affero General Public License v3.0 (AGPLv3)](LICENSE).
