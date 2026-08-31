<div align="center">

# 🛡️ GeDefense WP — Open Core

### Суверенная система безопасности WordPress

[![Version](https://img.shields.io/badge/version-8.0.0_Open_Core-D4AF37?style=for-the-badge)](#)
[![License](https://img.shields.io/badge/license-AGPL--3.0--or--later-0B5FFF?style=for-the-badge)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1--8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=for-the-badge&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![Dependencies](https://img.shields.io/badge/external_PHP_dependencies-0-2EA44F?style=for-the-badge)](#zero-dependency-philosophy)
[![Modules](https://img.shields.io/badge/core_modules-19-111111?style=for-the-badge)](#core-module-matrix)
[![Architecture](https://img.shields.io/badge/architecture-multi--tier_security_kernel-111111?style=for-the-badge)](#architecture)
[![Strict Types](https://img.shields.io/badge/PHP-strict_types-5C2D91?style=for-the-badge)](#zero-dependency-philosophy)
[![Local First](https://img.shields.io/badge/design-local--first-2EA44F?style=for-the-badge)](#security-design-principles)

**WAF · RASP · ПОВЕДЕНЧЕСКАЯ ЗАЩИТА · DECEPTION · ЦЕЛОСТНОСТЬ ФАЙЛОВ · КОНТРОЛЬ ИСХОДЯЩЕГО ТРАФИКА · HARDENING · КРИПТОГРАФИЧЕСКОЕ ХРАНИЛИЩЕ · OPEN CORE**

</div>

---

<img width="2332" height="1183" alt="image" src="https://github.com/user-attachments/assets/b97ed4da-f7a1-4343-a46d-ab8bb1e3feb9" />

## Обзор

**GeDefense WP — Open Core** — это модульная платформа безопасности WordPress без внешних зависимостей, разработанная как **многоуровневое ядро безопасности и матрица активной защиты** для PHP 8.1–8.4 и WordPress 6.0+.

Вместо того чтобы рассматривать безопасность WordPress как единый набор правил межсетевого экрана, GeDefense WP объединяет несколько защитных уровней в единый согласованный конвейер обработки запросов и защиты во время выполнения:

- отклонение запросов до основной загрузки;
- инспекция Web Application Firewall;
- поведенческая оценка угроз;
- согласованная оркестрация ответных действий;
- криптографическая проверка собственной целостности;
- Runtime Application Self-Protection;
- проверка загрузок и файлов;
- hardening WordPress;
- сокрытие административных путей;
- honeypot-ловушки и deception;
- zero-trust контроль исходящего трафика;
- фоновое сканирование целостности;
- криптографическое хранение секретов;
- аудит состояния безопасности.

Ядро спроектировано так, чтобы оставаться **полностью функциональной независимой open-source платформой безопасности**. Дополнительные бизнес- и прикладные модули могут подключаться через реестр модулей Open Core.

> GeDefense WP строится вокруг одного принципа: **чем глубже запрос продвигается внутрь стека приложения, тем больше доверия он должен заслужить.**

---

## Содержание

- [Архитектура](#архитектура)
- [Конвейер безопасности](#многофазный-протокол-запуска)
- [Матрица основных модулей](#матрица-основных-модулей)
- [AEGIS — Deep Packet Inspection WAF](#1-aegis--deep-packet-inspection-waf)
- [PROMETHEUS — поведенческий горизонт угроз](#2-prometheus--поведенческий-горизонт-угроз)
- [TRINITY GRID — матрица координации защиты](#3-trinity-grid--матрица-координации-защиты)
- [Self-Integrity Engine](#4-self-integrity-engine)
- [MORPHEUS — RASP](#5-morpheus--runtime-application-self-protection)
- [NEMESIS — Deception Grid](#6-nemesis--deception-grid)
- [TITAN — Hardening WordPress](#7-titan--hardening-wordpress)
- [HADES — сокрытие административной области](#8-hades--динамическое-сокрытие-административной-области)
- [CERBERUS — периметровый межсетевой экран](#9-cerberus--периметровый-межсетевой-экран)
- [ZEUS — фильтр до загрузки WordPress](#10-zeus--фильтр-запросов-до-загрузки)
- [AIRLOCK — инспекция загрузок](#11-airlock--инспекция-входящих-файлов)
- [GHOST TRAP — honeypot-уровень](#12-ghost-trap--honeypot-уровень)
- [STYX — защита исходящего трафика](#13-styx--защита-исходящего-трафика)
- [CHRONOS — автономный сканер](#14-chronos--автономный-сканер)
- [KEY VAULT](#15-key-vault--криптографическое-хранение-секретов)
- [ORACLE — аудит безопасности](#16-oracle--движок-аудита-безопасности)
- [Реестр модулей / Open Core](#17-реестр-модулей--расширение-open-core)
- [Философия Zero Dependency](#философия-zero-dependency)
- [Режимы безопасности](#режимы-безопасности)
- [Производительность](#производительность)
- [Тестирование](#assurance--регрессионное-тестирование)
- [Принципы проектирования безопасности](#принципы-проектирования-безопасности)
- [Модель Open Core](#модель-open-core)
- [Ответственное раскрытие уязвимостей](#ответственное-раскрытие-уязвимостей)
- [Лицензия](#лицензия)
- [Журнал изменений](#журнал-изменений)

---

# Архитектура

GeDefense WP организован как многоуровневая защитная среда, работающая до, во время и после обычного выполнения WordPress.

All GeDefense-owned runtime APIs use the canonical `VisionGaia\GeDefense` namespace. Core, dashboard, scanner and module symbols are separated below that root. Pre-8.0 `VisionGaia\Integrity` and `VGT\Sentinel` names are isolated inside the central compatibility boundary. Existing global `VIS_` implementation symbols remain ABI-compatible for WordPress hooks and third-party add-ons, but new integrations must use the canonical API.

```mermaid
flowchart TD
    A[Входящий HTTP / HTTPS запрос]
    A --> L0[Уровень 0<br/>ZEUS + CERBERUS]
    L0 --> L1[Уровень 1<br/>Self-Integrity Engine]
    L1 --> L23[Уровни 2-3<br/>AEGIS + PROMETHEUS]
    L23 --> L45[Уровни 4-5<br/>TITAN + HADES + NEMESIS + GHOST TRAP]
    L45 --> L67[Уровни 6-7<br/>MORPHEUS + AIRLOCK + STYX]
    L67 --> WP[WordPress Core / Темы / Плагины]
    PROM[TRINITY GRID] -. координирует .-> L0
    PROM -. координирует .-> L23
    PROM -. координирует .-> L45
    CHR[CHRONOS] -. плановая проверка целостности .-> WP
    ORA[ORACLE] -. состояние безопасности .-> WP
    VLT[KEY VAULT] -. защищённые секреты .-> L23
```

Архитектура намеренно разделяет:

```text
Фильтрация входящего трафика
       ↓
Поведенческий анализ
       ↓
Проверка целостности
       ↓
Hardening WordPress
       ↓
Deception- и trap-уровни
       ↓
Защита во время выполнения
       ↓
Контроль исходящего трафика
       ↓
Фоновая проверка целостности и аудит
```

---

# Многофазный протокол запуска

Входящие запросы проходят через детерминированный многоэтапный конвейер:

```text
[ ВХОДЯЩИЙ HTTP / HTTPS ЗАПРОС ]
                 │
                 ▼

[ УРОВЕНЬ 0 ]
  ├─ Pre-Boot Invariant Guard
  ├─ CERBERUS L1 Ban Matrix
  └─ ZEUS 6G / Static Fast-Kill Filters
                 │
                 ▼

[ УРОВЕНЬ 1 ]
  └─ Проверка собственной целостности
     └─ Проверка Manifest / Merkle Root
                 │
                 ▼

[ УРОВНИ 2–3 ]
  ├─ AEGIS Deep Packet Inspection
  │   ├─ GET
  │   ├─ POST
  │   ├─ Headers
  │   ├─ JSON
  │   └─ Multipart Metadata
  └─ PROMETHEUS Behavioral Scoring
                 │
                 ▼

[ УРОВНИ 4–5 ]
  ├─ TITAN Hardening
  ├─ HADES Admin Stealth
  ├─ NEMESIS Deception Grid
  └─ GHOST TRAP
                 │
                 ▼

[ УРОВНИ 6–7 ]
  ├─ MORPHEUS RASP
  ├─ AIRLOCK File Inspector
  └─ STYX Outbound Egress Shield
                 │
                 ▼

[ WORDPRESS CORE / ТЕМЫ / ПЛАГИНЫ ]
                 │
                 ▼

[ CHRONOS / ORACLE / TELEMETRY / INTEGRITY ]
```

---

# Матрица основных модулей

| # | Модуль | Роль в безопасности | Уровень / домен |
|---:|---|---|---|
| 1 | **AEGIS** | Deep Packet Inspection WAF | Ingress / L3-L7 |
| 2 | **PROMETHEUS** | Поведенческая оценка угроз | Behavioral / L7 |
| 3 | **TRINITY GRID** | Координация ответных действий | Cross-Layer |
| 4 | **SELF-INTEGRITY ENGINE** | Merkle-проверка ядра | Integrity / L1 |
| 5 | **MORPHEUS** | Runtime Application Self-Protection | Runtime / L7 |
| 6 | **NEMESIS** | Cyber Deception & Canary Grid | Deception / L7 |
| 7 | **TITAN** | WordPress Hardening & Anti-Enumeration | Hardening / L4 |
| 8 | **HADES** | Dynamic Admin Path Cloaking | Identity / L7 |
| 9 | **CERBERUS** | Fast Perimeter Ban Matrix | Pre-Boot / L0-L1 |
| 10 | **ZEUS** | Ultra-Lightweight Pre-Boot Filtering | Pre-Boot / L0 |
| 11 | **AIRLOCK** | File Upload & Entropy Inspection | Ingress / L7 |
| 12 | **GHOST TRAP** | Honeypot & Decoy Route Engine | Deception / L7 |
| 13 | **STYX** | Outbound HTTP / Exfiltration Control | Egress / L7 |
| 14 | **CHRONOS** | Autonomous Integrity Scheduler | Background |
| 15 | **KEY VAULT** | Encrypted Secret Storage | Cryptography |
| 16 | **ORACLE** | Security Configuration Audit | Audit |
| 17 | **MODULE REGISTRY** | Open-Core Expansion Hub | Extensibility |
| 18 | **THRONEGUARD** | Master/Admin Privilege Separation & Superkey Session | Identity / Privilege |
| 19 | **LOGINPAGER** | Local Login Surface Hardening & Branding | Identity / UI |

---

# 1. AEGIS — Deep Packet Inspection WAF

**Classification:** Layer 3/7 Ingress Firewall & Protocol Analyzer  
**Core class:** `VisionGaia\GeDefense\Modules\Aegis\Aegis`
**Path:** `includes/modules/aegis/class-vis-aegis.php`

AEGIS — основной движок инспекции запросов на уровне приложения.

### Основные механизмы

- двухфазный конвейер инспекции;
- быстрое сопоставление сигнатур до глубокой нормализации;
- сворачивание SQL-комментариев;
- нормализация null-byte;
- нормализация разрезанных кавычек и слешей;
- нормализация Unicode homoglyph;
- рекурсивная инспекция payload;
- анализ вложенного JSON;
- проверка multipart-метаданных загрузок;
- инспекция HTTP-заголовков;
- настраиваемые лимиты тела запроса;
- кэширование скомпилированных шаблонов в памяти.

### Покрытие обнаружения

AEGIS предназначен для обнаружения паттернов запросов, связанных со следующими классами атак:

```text
SQL Injection
├─ Blind
├─ Time-Based
├─ Error-Based
├─ UNION SELECT
└─ Stacked Queries

Cross-Site Scripting
├─ Reflected
├─ Stored
├─ DOM-Oriented Payloads
├─ Tag Smuggling
└─ SVG Event Handlers

Remote Code Execution
├─ eval
├─ assert
├─ system
├─ passthru
├─ preg_replace /e
└─ shell-style execution patterns

File Inclusion
├─ php://filter
├─ data://
├─ expect://
├─ LFI
├─ RFI
└─ directory traversal

Дополнительные классы
├─ PHP object injection
├─ PHAR-oriented payloads
├─ SSRF
├─ host-header poisoning
└─ ingress header smuggling
```

### Границы инспекции

Целевые значения реализации по умолчанию:

```text
Объём инспекции body: 1 MiB
Лимит инспекции заголовков: 64 KiB
Глубина рекурсивного payload: до 15 уровней
```

### Режимы работы

**Learning Mode**

- пассивная инспекция;
- телеметрия безопасности;
- без автоматической блокировки IP;
- подходит для staging-среды и построения baseline.

**Strict Mode**

- завершение запроса;
- ответ HTTP 403;
- событие угрозы передаётся в Prometheus;
- дальнейшая эскалация в Cerberus через координированную матрицу реагирования.

### Опциональный AI Oracle Bridge

AEGIS может опционально передавать ранее неизвестные payload во внешний AI-мост анализа, настроенный через защищённое хранилище ключей.

AI-путь является **дополнительным аналитическим уровнем**, а не заменой детерминированных локальных механизмов безопасности.

---

# 2. PROMETHEUS — поведенческий горизонт угроз

**Classification:** Layer 7 Behavioral Analysis & Threat Horizon Engine  
**Namespace:** `VisionGaia\GeDefense\Modules\Prometheus\Prometheus`

Prometheus оценивает поведение отдельных клиентов и сетевых диапазонов, а не рассматривает каждый запрос как полностью изолированное событие.

### Оценка угрозы

Каждый клиент может накапливать динамический score на основании наблюдаемого поведения.

Пример матрицы штрафов:

| Сигнал | Примерный вес |
|---|---:|
| Запрещённый HTTP-метод | `+30.0` |
| Null-byte / traversal anomalies | `+50.0` |
| Повторные запросы к чувствительным путям | `+25.0` |
| Burst-частота запросов | `+20.0` |
| AEGIS WAF strike | `+50.0` |

### Затухание угрозы

Затухание score по умолчанию:

```text
0.2 points / second
```

Механизм затухания позволяет временно подозрительным, но легитимным клиентам со временем вернуться к нормальному уровню риска.

### Event horizons

```text
Score 75
   ↓
Pre-Lock Telemetry Threshold

Score 100
   ↓
Cerberus Escalation Threshold
```

### Корреляция подсетей

Prometheus может коррелировать поведение в пределах `/24` сетевого горизонта для выявления паттернов, соответствующих:

- rotating proxies;
- распределённым сканерам;
- координированной bot-активности;
- burst-разведке.

### Конкурентный доступ

Атомарные стратегии блокировок и fallback через блокировки БД применяются для уменьшения race condition при высокой параллельности запросов.

---

# 3. TRINITY GRID — матрица координации защиты

**Классификация:** Cross-Layer Orchestration & Coordinated Response Grid

TRINITY GRID объединяет четыре ключевые защитные системы:

```text
           ┌─────────┐
           │  AEGIS  │
           └────┬────┘
                │
                ▼
        ┌──────────────┐
        │  PROMETHEUS  │
        └──────┬───────┘
               │
        ┌──────┴────────┐
        ▼               ▼
┌──────────┐       ┌──────────┐
│ NEMESIS  │       │ CERBERUS │
└──────────┘       └──────────┘
```

### Модель эскалации

1. AEGIS обнаруживает вредоносный синтаксис.
2. Prometheus увеличивает поведенческий threat score.
3. Повторная активность пересекает порог deception.
4. Nemesis может перевести клиента в tarpit- или decoy-процесс.
5. Устойчивая враждебная активность пересекает event horizon.
6. Cerberus выполняет периметровое отклонение.

Такой подход позволяет не превращать каждый подозрительный запрос в немедленную постоянную блокировку, но при этом быстро эскалировать защиту против устойчиво враждебного поведения.

---

# 4. Self-Integrity Engine

**Classification:** Layer 1 Cryptographic Invariant Guard  
**Class:** `VisionGaia\GeDefense\Core\ModuleIntegrity`
**Path:** `includes/core/class-vis-module-integrity.php`

Уровень self-integrity проверяет критические компоненты GeDefense WP относительно криптографического trust anchor.

### Основные механизмы

- SHA-256 digest манифеста;
- trust anchor `VIS_MANIFEST_DIGEST`;
- Merkle-style проверка файлов;
- проверка ключевых компонентов безопасности;
- сравнение в постоянном времени через `hash_equals`;
- немедленное обнаружение несоответствия целостности.

### Модель целостности

```text
Trusted Manifest Digest
          │
          ▼
Component Hashes
          │
          ▼
Merkle / Aggregate Verification
          │
      ┌───┴────┐
      │        │
    MATCH   MISMATCH
      │        │
      ▼        ▼
  Continue  Security Event
```

Механизм проверяет **целостность кода**, а не семантическую корректность внешних данных или сторонних систем.

---

# 5. MORPHEUS — Runtime Application Self-Protection

**Classification:** Layer 7 In-Memory Execution Protection  
**Namespace:** `VisionGaia\GeDefense\Modules\Morpheus\Morpheus`

Morpheus защищает критическое состояние WordPress во время выполнения.

### Видимость runtime-контекста

Morpheus может анализировать контекст call stack, чтобы определить, какой плагин или компонент инициировал чувствительную операцию.

### Защита SQL DML

Чувствительные таблицы WordPress могут быть защищены от несанкционированных паттернов изменения, включая:

```text
wp_users
wp_usermeta
```

Цель — обнаруживать или блокировать неожиданные операции, связанные с:

- изменением паролей;
- повышением привилегий;
- несанкционированными изменениями административного состояния.

### SSRF / Network Guard

Morpheus может блокировать несанкционированные исходящие запросы к локальным или чувствительным metadata-адресам, например:

```text
127.0.0.1
localhost
169.254.169.254
```

### Защищённые настройки WordPress

Критические опции могут контролироваться или защищаться, включая:

```text
siteurl
home
active_plugins
```

### Режимы

**Audit Mode**

- наблюдение за внутренним поведением плагинов;
- построение модели разрешённых операций;
- генерация телеметрии.

**Enforcement Mode**

- блокировка операций вне утверждённой матрицы безопасности.

---

# 6. NEMESIS — Deception Grid

**Classification:** Layer 7 Counterintelligence & Deception Matrix  
**Namespace:** `VisionGaia\GeDefense\Modules\Nemesis\Nemesis`

Nemesis — deception-уровень GeDefense WP.

### Decoy-цели

Примеры имитируемых ценных путей:

```text
.env
wp-config.php.bak
phpmyadmin
```

### Ограниченные deception-ответы

Подозрительные автоматизированные клиенты получают небольшие, конечные decoy-ответы без раскрытия реального состояния приложения и без удержания PHP workers в открытом состоянии.

### Криптографические canary

Canary-значения на базе HMAC-SHA256 могут внедряться в контролируемые области для последующей корреляции утечек.

### Полиморфное искажение данных

При соответствующей настройке данные, видимые scraper-клиентам, уже классифицированным как враждебная автоматизация, могут намеренно изменяться.

### Граница защитного реагирования

Nemesis ограничен защитной телеметрией, canary-механизмами, ограниченными decoy-ответами и content deception. Runtime-пути не генерируют response bombs, cookie bombs, terminal-control payload и не удерживают workers длительными задержками.

---

# 7. TITAN — Hardening WordPress

**Classification:** Layer 4 System Hardening & Anti-Enumeration Shield  
**Class:** `VisionGaia\GeDefense\Modules\Titan\Titan`
**Path:** `includes/modules/titan/class-vis-titan.php`

Titan уменьшает ненужную поверхность атаки WordPress.

### Hardening-контроли

- блокировка author enumeration;
- защита от REST user enumeration;
- блокировка XML-RPC;
- блокировка редактора файлов;
- `DISALLOW_FILE_EDIT`;
- скрытие version tag WordPress;
- удаление `X-Powered-By`;
- уменьшение server fingerprint.

Примеры защищённых маршрутов:

```text
/?author=1
/wp-json/wp/v2/users
/xmlrpc.php
```

---

# 8. HADES — динамическое сокрытие административной области

**Classification:** Layer 7 Identity & Route Cloaking  
**Class:** `VisionGaia\GeDefense\Modules\Hades\Hades`
**Path:** `includes/modules/hades/class-vis-hades.php`

Hades уменьшает прямую публичную экспозицию административной поверхности входа.

### Модель безопасности

```text
Public Request
      │
      ▼
Standard wp-login.php / wp-admin
      │
      ├─ Authorized Handshake → Admin Session
      │
      └─ No Handshake → 404-style response
```

### Возможности

- динамический admin access handshake;
- подавление прямого login-route;
- имитация 404 для неавторизованных запросов;
- привязка ephemeral session-cookie;
- уменьшение публичной экспозиции административных точек входа.

---

# 9. CERBERUS — периметровый межсетевой экран

**Classification:** Layer 0/1 Instant Drop Barrier  
**Class:** `VisionGaia\GeDefense\Modules\Cerberus\Cerberus`
**Path:** `includes/modules/cerberus/class-vis-cerberus.php`

Cerberus предназначен для максимально раннего отклонения уже известных враждебных клиентов.

### Характеристики

- очень ранний приоритет загрузки;
- in-memory проверка IP там, где она доступна;
- поддержка APCu/shared-cache;
- поддержка IPv4 и IPv6 CIDR;
- Cloudflare-aware проверка client-IP;
- минимальный путь формирования ответа при отклонении.

### Цель

```text
Known Hostile Client
       │
       ▼
Memory Lookup
       │
       ▼
Immediate Reject
       │
       └── No Theme Rendering
           No Normal WordPress Page Flow
```

---

# 10. ZEUS — фильтр запросов до загрузки

**Classification:** Layer 0 Ultra-Lightweight Request Filter  
**Class:** `VisionGaia\GeDefense\Modules\Zeus\Zeus`
**Path:** `includes/modules/zeus/class-vis-zeus.php`

Zeus выполняет низкозатратную фильтрацию до более глубокой инспекции приложения.

### Возможности

- blacklist-правила в стиле 6G;
- фильтрация вредоносных query string;
- фильтрация плохих user-agent;
- фильтрация аномалий referrer;
- отклонение malformed URI;
- emergency recovery / bypass-механизм при ошибочной конфигурации.

---

# 11. AIRLOCK — инспекция входящих файлов

**Classification:** Layer 7 Ingress Data Sandbox  
**Class:** `VisionGaia\GeDefense\Modules\Airlock\Airlock`
**Path:** `includes/modules/airlock/class-vis-airlock.php`

Airlock оценивает загруженные файлы на основе содержимого, а не доверяет только расширению имени файла.

### Механизмы инспекции

- проверка magic bytes;
- MIME validation;
- санитизация SVG XML;
- удаление встроенных script/event;
- обнаружение `javascript:` payload;
- защита от XML entity expansion;
- анализ энтропии;
- обнаружение polyglot-файлов;
- выявление скрытых PHP-сигнатур в метаданных изображений.

### Примеры SVG-защиты

Airlock предназначен для отклонения или санитизации конструкций вида:

```html
<script>
onload=
javascript:
```

а также небезопасных паттернов расширения XML entity.

---

# 12. GHOST TRAP — honeypot-уровень

**Classification:** Layer 7 Active Lure Engine  
**Class:** `VisionGaia\GeDefense\Modules\Trap\GhostTrap`
**Path:** `includes/modules/trap/class-vis-ghost-trap.php`

Ghost Trap создаёт ложные ресурсы, к которым легитимный пользователь обращаться не должен.

Примеры:

```text
backup.sql
config.php.bak
database.dump
.aws/credentials
```

Обращение к decoy-маршруту может рассматриваться как высококонфидентный признак автоматизированной разведки и эскалироваться в общую защитную матрицу.

---

# 13. STYX — защита исходящего трафика

**Classification:** Layer 7 Egress Control & Supply-Chain Guard  
**Class:** `VisionGaia\GeDefense\Modules\Styx\Styx`
**Path:** `includes/modules/styx/class-vis-styx.php`

Styx контролирует исходящий HTTP-трафик WordPress.

### Основной механизм

Точка интеграции:

```text
pre_http_request
```

### Цели

- контроль исходящих адресатов;
- egress allowlisting;
- устойчивость к эксфильтрации;
- containment скомпрометированного плагина;
- опциональное ограничение WordPress core telemetry;
- сокращение неконтролируемых соединений со сторонними сервисами.

Styx особенно актуален там, где WordPress должен работать в рамках **local-first или restricted-egress policy**.

---

# 14. CHRONOS — автономный сканер

**Classification:** Asynchronous Background Integrity Daemon  
**Class:** `VisionGaia\GeDefense\Modules\Chronos\Chronos`
**Path:** `includes/modules/chronos/class-vis-chronos.php`

Chronos выполняет плановую проверку целостности и мониторинг файловой системы.

### Настраиваемые интервалы

Примеры интервалов:

```text
15 минут
   ↓
ежечасно
   ↓
каждые несколько часов
   ↓
ежедневно
```

### Контролируемые области

- GeDefense Core;
- WordPress Core;
- плагины;
- темы;
- выбранные прикладные пути.

### Оповещения

Шаблоны уведомлений могут включать переменные:

```text
{site_url}
{timestamp}
{details}
```

---

# 15. KEY VAULT — криптографическое хранение секретов

**Classification:** Cryptographic Key Management  
**Class:** `VisionGaia\GeDefense\Modules\Vault\KeyVault`

Key Vault защищает чувствительные значения конфигурации, такие как API credentials и токены модулей.

### Криптографические механизмы

Поддерживаемый дизайн включает:

- Libsodium Secretbox;
- AES-256-GCM;
- authenticated encryption;
- AAD-style привязку идентификаторов;
- защищённое хранение API-ключей.

Примеры классов секретов:

```text
AI Provider Keys
Nexus Tokens
Private Integration Secrets
Module Credentials
```

Чувствительные значения никогда не должны попадать в репозиторий.

---

# 16. ORACLE — движок аудита безопасности

**Classification:** Static Security & Configuration Auditing  
**Class:** `VisionGaia\GeDefense\Modules\Oracle\Oracle`
**Path:** `includes/modules/oracle/class-vis-oracle.php`

Oracle оценивает состояние безопасности WordPress и PHP по двенадцати основным направлениям.

| # | Проверка |
|---:|---|
| 1 | защита `wp-config.php` |
| 2 | недоступность `debug.log` извне |
| 3 | блокировка редактора файлов |
| 4 | энтропия WordPress salts |
| 5 | hardening префикса БД |
| 6 | проверка учётной записи `admin` по умолчанию |
| 7 | защита от enumeration пользовательских ID |
| 8 | принудительное использование HTTPS / TLS |
| 9 | экспозиция server signature |
| 10 | состояние PHP display-errors |
| 11 | защита от directory browsing |
| 12 | корректная передача authentication headers |

---

# 17. РЕЕСТР МОДУЛЕЙ — расширение Open Core

**Classification:** Extensible Module Architecture  
**Class:** `VisionGaia\GeDefense\Core\ModuleRegistry`
**Path:** `includes/core/class-vis-module-registry.php`

Реестр модулей предоставляет слой расширения для GeDefense WP Open Core.

Ядро остаётся полностью работоспособным само по себе, а дополнительные модули могут распространяться как подписанные или отдельно упакованные расширения.

### Планируемые / доступные модули экосистемы

#### Vision Legal Pro — VLP

Функциональность, ориентированная на приватность и compliance, включая:

- privacy controls;
- локальное зеркалирование assets;
- consent-oriented workflows;
- local-first обработку данных.

#### Lightweight Builder

Высокопроизводительная визуальная система компоновки и компонентов, предназначенная для отказа от overhead тяжёлых page builder.

#### GEO Architect

Generative Engine Optimization и инструменты семантических сущностей для AI-oriented поиска и discovery-систем.

---

---

# 18. THRONEGUARD — Sovereign Privilege Sentinel

**Classification:** Layer 7 Privilege Boundary & Identity Hardening  
**Core class:** `VisionGaia\GeDefense\Modules\ThroneGuard\ThroneGuard` (`VIS_Throne_Guard`)  
**Path:** `includes/modules/throneguard/class-vis-throne-guard.php`

ThroneGuard enforces a strict cryptographic privilege boundary between sovereign **GeDefense Master** accounts and standard WordPress **Administrator** accounts.

### Core mechanisms

- **Sovereign Master Role (`master`)**: Introduces a dedicated, immutable role tier above standard WordPress administrators.
- **Granular Admin Capability Matrix**: Empowers Masters to selectively strip toxic capabilities from standard administrators across 4 critical attack vectors:
  - *Plugins:* `activate_plugins`, `install_plugins`, `update_plugins`, `delete_plugins`, `edit_plugins`
  - *Themes:* `switch_themes`, `install_themes`, `update_themes`, `delete_themes`, `edit_themes`
  - *Users & Privilege Escalation:* `create_users`, `promote_users`, `delete_users`, `edit_users`
  - *System & Filesystem:* `update_core`, `unfiltered_html`, `edit_files`
- **Dynamic Capability Reconciliation**: Automatically reconciles role permissions on login, user mutations, and option updates to prevent rogue privilege elevation.
- **Zero-Trust Superkey Lockdown**: Protects `wp-admin` dashboard access and REST API write actions with a cryptographic Superkey (PBKDF2/SHA-256 with CSPRNG salt). Privileged sessions require periodic verification (2-hour sliding window token) and bind to client browser fingerprints.
- **Event Horizon Audit Stream**: Circular buffer security event logger (up to 80 events) tracking Master claims, Superkey changes, role reconciliations, and unauthorized REST manipulation attempts with real-time severity filtering (Critical, Warning, Success, Info) and AJAX log purging.
- **Apex Cyberpunk Cockpit**: High-tech dashboard featuring live telemetry vitals (Master Sovereignty, Superkey Vault, Admin Privilege Filter, Zero-Trust Lockdown) and inline privilege matrix toggles.

---

# 19. LOGINPAGER — Sovereign Login Surface

**Classification:** Authentication Gateway & Visual Hardening  
**Core class:** `VisionGaia\GeDefense\Modules\LoginPager\LoginPager` (`VIS_LoginPager`)  
**Path:** `includes/modules/loginpager/class-vis-loginpager.php`

LoginPager transforms the native WordPress authentication endpoint (`wp-login.php`) into a local-first, zero-dependency, cyberpunk-styled security gateway.

### Core mechanisms

- **Zero External Dependencies:** 100% self-contained inline CSS and SVGs without any Google Fonts, external CDNs, or third-party trackers.
- **Cyberpunk Glassmorphism Surface:** Translucent dark form card (`backdrop-filter: blur()`), glowing accent edge lighting, and geometric background mesh.
- **Adaptive Logo & Branding Fallback:** Intelligently centers custom logos or renders a clean, glowing Portal Title if no image asset is supplied.
- **Real-Time Interactive Cockpit (`view-loginpager.php`):** 2-column cockpit with 5 instant color presets (*Cyber Cyan, Emerald Matrix, Purple Haze, Apex Gold, Crimson Core*), dual color pickers with HEX inputs, custom background/logo URLs, and a 1:1 live preview browser mockup simulator.

# Zero-Dependency Philosophy

GeDefense WP намеренно минимизирует количество сторонних runtime-зависимостей.

### PHP

```php
<?php

declare(strict_types=1);
```

### Цели проектирования

- PHP 8.1–8.4;
- WordPress 6.0+;
- отсутствие внешних PHP vendor-библиотек в ядре;
- отсутствие обязательной runtime-зависимости от Composer;
- оптимизированная локальная автозагрузка проекта;
- нативные API WordPress / PHP;
- Libsodium при наличии;
- отсутствие обязательного внешнего frontend CDN;
- UI assets, которые могут полностью отрисовываться локально.

### Почему это важно

Каждая сторонняя runtime-зависимость потенциально создаёт:

```text
Supply Chain Risk
Update Risk
Abandonware Risk
Transitive Dependencies
Version Conflicts
Additional Audit Surface
```

Подход zero-dependency не устраняет программные риски полностью, но намеренно сокращает количество внешне контролируемых runtime-компонентов внутри trusted computing base.

---

# Режимы безопасности

Несколько модулей GeDefense предоставляют явные режимы работы вместо принудительного использования одного универсального enforcement-профиля.

## Learning / Audit

Предназначен для первоначального развёртывания и наблюдения.

```text
Inspect
Log
Score
Baseline
Do Not Aggressively Block
```

## Enforcement / Strict

Предназначен для hardened-развёртываний.

```text
Inspect
Normalize
Detect
Score
Enforce
Escalate
```

Перед включением агрессивных policy в production администраторы должны сначала построить baseline легитимного поведения приложения.

---

# Производительность

The current GeDefense WP 8.0.0 technical profile defines the following internal benchmark targets/results:

| Метрика | GeDefense WP |
|---|---:|
| **L0 rejection latency** | `0.08 ms` |
| **WAF inspection time** | `0.35 ms` |
| **Standby RAM footprint** | `< 1.8 MB` |
| **External PHP dependencies** | `0` |
| **Primary architecture** | Memory-cache first |
| **Control model** | Local / on-premise |

> Показатели производительности зависят от PHP runtime, доступности cache, стека WordPress, hosting environment, формы запроса и включённых модулей безопасности. Перед использованием этих цифр для capacity planning воспроизводите benchmark в собственной среде.

---

# Assurance & регрессионное тестирование

Репозиторий включает отдельные regression- и benchmark-gates.

```bash
php scripts/security-regression.php
php scripts/malware-scanner-regression.php
php scripts/scanner-resumption-regression.php
php scripts/throneguard-loginpager-regression.php
php scripts/aegis-regression.php
php scripts/trinity-regression.php
php scripts/morpheus-regression.php
php scripts/integrity-baseline-regression.php
php scripts/sentinel-threat-benchmark.php
```

Эти тесты предназначены для проверки:

- security invariants;
- регрессионных сценариев WAF;
- runtime-контролей Morpheus;
- integrity baselines;
- поведения обнаружения угроз;
- чувствительных к производительности путей безопасности.

Security-релиз не должен считаться проверенным только потому, что плагин успешно активируется в WordPress.

---

# Принципы проектирования безопасности

## 1. Defense in Depth

Ни один отдельный модуль не рассматривается как единственная граница безопасности.

```text
Pre-Boot
   ↓
WAF
   ↓
Behavior
   ↓
Hardening
   ↓
Runtime Protection
   ↓
Egress Control
   ↓
Integrity
```

## 2. Local First

Состояние безопасности и решения по безопасности по возможности должны оставаться локальными.

## 3. Детерминированные контроли до AI

Детерминированная логика безопасности остаётся основным enforcement-уровнем.

Опциональный AI-анализ является дополнительным.

## 4. Минимальная Trusted Computing Base

Ядро избегает ненужных runtime-зависимостей.

## 5. Явное Enforcement

Агрессивные enforcement-режимы должны включаться осознанно и предварительно проверяться.

## 6. Наблюдаемая безопасность

Защитные действия должны создавать пригодную для анализа телеметрию, а не превращаться в невидимое фоновое поведение.

## 7. Deception без зависимости от него

Honeypot и canary-механизмы дополняют традиционную защиту, а не заменяют её.

---

# Модель Open Core

GeDefense WP выпускается как платформа **Open Core**.

### Open Core означает:

- security-core доступен в исходном коде под AGPLv3;
- ядро полностью функционально само по себе;
- ядро содержит основную архитектуру безопасности;
- опциональные ecosystem-модули могут расширять бизнес- или прикладную функциональность;
- развёртывания могут оставаться локальными и полностью контролироваться оператором.

Цель — не публиковать намеренно урезанную демоверсию.

Цель — создать аудитируемую основу безопасности, которую можно расширять, не превращая базовый защитный слой в обязательный облачный сервис.

---

# Ответственное раскрытие уязвимостей

Security software следует тестировать агрессивно, однако уязвимости, затрагивающие реальных пользователей, должны раскрываться ответственно.

Пожалуйста, **не публикуйте немедленно эксплуатируемые уязвимости, действующие учётные данные, приватные ключи или чувствительные production-данные в публичном issue**.

Полезный отчёт об уязвимости должен включать:

```text
Affected Version
Affected Module
Environment
Reproduction Steps
Expected Behavior
Observed Behavior
Security Impact
Relevant Logs
Proof of Concept, where appropriate
```

Если в репозитории существует `SECURITY.md`, следуйте описанному в нём процессу disclosure.

---

# Уведомление о безопасности

GeDefense WP — защитное программное обеспечение.

Некоторые модули включают deception, honeypot и ограниченные decoy-ответы. Эти механизмы остаются локальными, конечными и защитными.

GeDefense WP не гарантирует, что установка WordPress станет неуязвимой.

Безопасность также зависит от:

- WordPress Core;
- сторонних тем и плагинов;
- PHP;
- веб-сервера;
- базы данных;
- операционной системы;
- конфигурации хостинга;
- безопасности администраторов;
- учётных данных;
- резервных копий;
- дисциплины обновлений;
- окружающей сетевой архитектуры.

---

# Требования

| Компонент | Требование |
|---|---|
| **PHP** | 8.1–8.4 |
| **WordPress** | 6.0+ |
| **PHP mode** | Strict Types |
| **External PHP libraries** | Не требуются ядром |
| **Libsodium** | Рекомендуется для нативных криптографических операций |
| **Object cache** | Опционально; APCu / совместимые cache-механизмы могут улучшить fast-path поведение |

---

# Лицензия

GeDefense WP Open Core распространяется под:

## GNU Affero General Public License v3.0

SPDX identifier:

```text
AGPL-3.0-or-later
```

Полные условия лицензии см. в:

[LICENSE](LICENSE)

Если GeDefense WP модифицируется и эксплуатируется через сеть, ознакомьтесь с требованиями AGPLv3 по доступности исходного кода, применимыми к вашему развёртыванию.

Товарные знаки третьих сторон, марки WordPress, брендинг VisionGaia Technology и отдельно распространяемые add-on-модули могут подпадать под собственные уведомления или политики.

---

# Журнал изменений

## 8.0.0 — Apex Sovereign Cyber Defense & Privilege Boundary Architecture

### 👑 ThroneGuard Master Engine & Privilege Boundary
- **Sovereign Master Role (`master`)**: Implemented immutable Master node provisioning, separating high-trust site owners from standard WordPress administrators.
- **Granular Capability Matrix**: Built interactive per-capability permission filters across 16 core capabilities (Plugins, Themes, User Elevation, and Filesystem/Kernel Updates) with automatic real-time role reconciliation.
- **Zero-Trust Superkey Vault**: Engineered cryptographic Superkey authentication (PBKDF2/SHA-256 with CSPRNG salt) and anti-hijack session locking for `wp-admin` and REST endpoints with 2-hour token lifetimes.
- **Event Horizon Audit Stream**: Built circular buffer telemetry logger with real-time severity filtering (`ALL`, `CRITICAL`, `WARNING`, `SUCCESS`, `INFO`), instant keyword search, and nonce-verified AJAX log clearing.
- **Cyberpunk Lockscreen**: Integrated standalone zero-trust lockscreen overlay with glowing biometric shield and reveal toggle.

### 🎨 LoginPager Sovereign Login Surface & Live Cockpit
- **Cyberpunk Login Surface (`wp-login.php`)**: Re-engineered native WordPress login with deep glassmorphism (`backdrop-filter`), glowing neon focus states, animated checkmarks, and elevated action buttons.
- **Dynamic Branding Fallback**: Automatic SVG status badges and portal typography when no external logo is provided.
- **Interactive 2-Column Cockpit**: Built live preview browser mockup with instant bidirectional synchronization (`vis-loginpager-admin.js`) and 5 instant color presets (*Cyber Cyan, Emerald Matrix, Purple Haze, Apex Gold, Crimson Core*).

### 📊 Multi-Tier Security Scoring & NOC Integration
- **Command Center (Overview)**: Rebalanced Cyber Defense Matrix to include ThroneGuard Master as a core 15% pillar (Zeus 20%, Aegis 15%, ThroneGuard 15%, Prometheus 15%, Titan 15%, Hades 10%, Cerberus 5%, Airlock 5% = 100%).
- **System Status (NOC)**: Added ThroneGuard and LoginPager to the NOC module diagnostic matrix and live vitals score.
- **Security Center**: Registered formal Trust Boundary (`Admin Role -> ThroneGuard Master`) and assurance health invariants in `VIS_Security_Health` and `VIS_Security_Center`.

### 🌐 Full 3-Language Localization (DE 🇩🇪, EN 🇬🇧, RU 🇷🇺)
- Completed 100% dictionary translation coverage in `de.php`, `en.php`, and `ru.php` for all ThroneGuard, LoginPager, capability matrix, and Setup Wizard elements.

### 🛡️ Core Stability & Zero-Dependency Invariants
- Canonical `VisionGaia\GeDefense` namespace consolidation with full `VIS_` ABI backward compatibility.
- 100% Zero-Dependency compliance: Zero Composer packages, zero external CDNs, zero cloud dependencies.

## 7.6.1 — Scanner State Finalization

- fixed the accepted-baseline completion path so the Integrity Monitor retains its live secure state instead of forcing a stale results reload;
- serialized the completion timer to prevent duplicate terminal UI actions;
- added a regression invariant for accepted baseline state handling; and
- added a persistent admin-IP protection gate until the current session is whitelisted in both AEGIS and Prometheus; and
- synchronized the Scanner, Airlock and Trinity integration release across the Open Core and MU-plugin distributions.

## 7.6.0 — Trinity Deterministic Interlock

- introduced a dedicated Trinity orchestration core for deterministic AEGIS, Prometheus, Cerberus and Nemesis routing;
- primed Trinity dependencies before the synchronous AEGIS request guard;
- centralized trusted-proxy and Cloudflare client identity resolution;
- enforced CIDR bans inside the PHP perimeter and deferred OS firewall export through a single scheduled synchronization;
- rejected unlocked Prometheus state mutations and expanded bounded lock acquisition;
- scoped botanical swarm correlation to a common network before subnet mitigation;
- replaced blocking PHP tarpits, artificial response bombs and five-second sleeps with bounded deception responses and telemetry;
- added server-side bounds and capability enforcement for Trinity and Prometheus configuration;
- added an executable Trinity interlock regression suite; and
- aligned release metadata and licensing identifiers;
- replaced the monolithic integrity loop with resumable path-jailed indexing and append-only NDJSON scan state;
- introduced a zero-dependency malware kernel shared by Airlock and Integrity/Chronos through bounded upload and deep-filesystem profiles;
- added PHP lexical-flow, MIME/polyglot, SVG/XML, archive and path-context detectors;
- refused compromised first-run or reindex baselines and added an atomic private quarantine vault; and
- routed structured upload and filesystem malware findings into Trinity without misattributing asynchronous findings to visitor IPs.

## 7.5.2 — Initial Open-Core Release

- опубликовано первое полное Open Core ядро безопасности, матрица модулей, assurance-suite и архитектурная документация.

---

# Состояние проекта

```text
Product:       GeDefense WP
Edition:       Open Core
Version:       8.0.0
Architecture:  Multi-Tier Security Kernel
Runtime:       PHP 8.1–8.4
Platform:      WordPress 6.0+
License:       AGPL-3.0-or-later
Core Modules:  19
Dependencies:  Zero external PHP vendor libraries
```

---

<div align="center">

## GeDefense WP 8.0.0 — Open Core

**СУВЕРЕННАЯ БЕЗОПАСНОСТЬ WORDPRESS**

**AEGIS · PROMETHEUS · TRINITY GRID · MORPHEUS · NEMESIS · TITAN · HADES · CERBERUS · ZEUS · AIRLOCK · GHOST TRAP · STYX · CHRONOS · KEY VAULT · ORACLE**

**VisionGaia Technology**

</div>
