<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

/**
 * GeDefense WP Multilanguage & Localization Matrix (I18N)
 * Provides instant German (Default) & English switching without external dependencies.
 */
final class VIS_I18n {

    private static ?string $current_lang = null;

    /**
     * Bidirectional Translation Matrix between German (de) and English (en).
     */
    private static array $dictionary = [
        // =========================================================================
        // 1. TABS & SIDEBAR NAVIGATION
        // =========================================================================
        'KONTROLLZENTRUM'          => ['de' => 'KONTROLLZENTRUM', 'en' => 'COMMAND CENTER'],
        'COMMAND CENTER'           => ['de' => 'KONTROLLZENTRUM', 'en' => 'COMMAND CENTER'],
        'SYSTEMSTATUS'             => ['de' => 'SYSTEMSTATUS', 'en' => 'SYSTEM STATUS'],
        'SYSTEM STATUS'            => ['de' => 'SYSTEMSTATUS', 'en' => 'SYSTEM STATUS'],
        'BEDROHUNGSMATRIX'         => ['de' => 'BEDROHUNGSMATRIX', 'en' => 'THREAT MATRIX'],
        'THREAT MATRIX'            => ['de' => 'BEDROHUNGSMATRIX', 'en' => 'THREAT MATRIX'],
        'ORAKEL SCANNER'           => ['de' => 'ORAKEL SCANNER', 'en' => 'ORACLE SCANNER'],
        'ORACLE SCANNER'           => ['de' => 'ORAKEL SCANNER', 'en' => 'ORACLE SCANNER'],
        'INTEGRITÄTS-MONITOR'      => ['de' => 'INTEGRITÄTS-MONITOR', 'en' => 'INTEGRITY MONITOR'],
        'INTEGRITY MONITOR'        => ['de' => 'INTEGRITÄTS-MONITOR', 'en' => 'INTEGRITY MONITOR'],
        'SICHERHEITSZENTRALE'      => ['de' => 'SICHERHEITSZENTRALE', 'en' => 'SECURITY CENTER'],
        'SECURITY CENTER'          => ['de' => 'SICHERHEITSZENTRALE', 'en' => 'SECURITY CENTER'],
        'SYSTEM-PROTOKOLLE'        => ['de' => 'SYSTEM-PROTOKOLLE', 'en' => 'SYSTEM LOGS'],
        'SYSTEM LOGS'              => ['de' => 'SYSTEM-PROTOKOLLE', 'en' => 'SYSTEM LOGS'],
        'TRINITY GRID'             => ['de' => 'TRINITY GRID', 'en' => 'TRINITY GRID'],
        'ZEUS DEFENDER'            => ['de' => 'ZEUS DEFENDER', 'en' => 'ZEUS DEFENDER'],
        'AEGIS FIREWALL'           => ['de' => 'AEGIS FIREWALL', 'en' => 'AEGIS FIREWALL'],
        'PROMETHEUS ENGINE'        => ['de' => 'PROMETHEUS ENGINE', 'en' => 'PROMETHEUS ENGINE'],
        'CERBERUS IP-SPERRE'       => ['de' => 'CERBERUS IP-SPERRE', 'en' => 'CERBERUS BAN'],
        'CERBERUS BAN'             => ['de' => 'CERBERUS IP-SPERRE', 'en' => 'CERBERUS BAN'],
        'AIRLOCK SCHLEUSE'         => ['de' => 'AIRLOCK SCHLEUSE', 'en' => 'AIRLOCK GATEWAY'],
        'AIRLOCK'                  => ['de' => 'AIRLOCK SCHLEUSE', 'en' => 'AIRLOCK GATEWAY'],
        'NEMESIS TÄUSCHUNG'        => ['de' => 'NEMESIS TÄUSCHUNG', 'en' => 'NEMESIS DECEPTION'],
        'NEMESIS DECEPTION'        => ['de' => 'NEMESIS TÄUSCHUNG', 'en' => 'NEMESIS DECEPTION'],
        'GHOST HONIGTOPF'          => ['de' => 'GHOST HONIGTOPF', 'en' => 'GHOST HONEYPOT'],
        'GHOST HONEYPOT'           => ['de' => 'GHOST HONIGTOPF', 'en' => 'GHOST HONEYPOT'],
        'HADES STEALTH'            => ['de' => 'HADES STEALTH', 'en' => 'HADES STEALTH'],
        'MORPHEUS SANDBOX'         => ['de' => 'MORPHEUS SANDBOX', 'en' => 'MORPHEUS SANDBOX'],
        'TITAN HÄRTUNG'            => ['de' => 'TITAN HÄRTUNG', 'en' => 'TITAN HARDENING'],
        'TITAN HARDENING'          => ['de' => 'TITAN HÄRTUNG', 'en' => 'TITAN HARDENING'],
        'KERNEL UPLINK'            => ['de' => 'KERNEL UPLINK', 'en' => 'KERNEL UPLINK'],
        'STYX CONTROLLER'          => ['de' => 'STYX CONTROLLER', 'en' => 'STYX CONTROLLER'],
        'CHRONOS AUTOPILOT'        => ['de' => 'CHRONOS AUTOPILOT', 'en' => 'CHRONOS AUTOPILOT'],
        'CHRONOS'                  => ['de' => 'CHRONOS AUTOPILOT', 'en' => 'CHRONOS AUTOPILOT'],
        'DATENSCHUTZ & SHADOW-NET' => ['de' => 'DATENSCHUTZ & SHADOW-NET', 'en' => 'PRIVACY & SHADOW NET'],
        'PRIVACY & SHADOW NET'     => ['de' => 'DATENSCHUTZ & SHADOW-NET', 'en' => 'PRIVACY & SHADOW NET'],
        'DATENSICHERHEIT'          => ['de' => 'DATENSICHERHEIT', 'en' => 'DATA INTEGRITY'],
        'NEXUS NETZWERK'           => ['de' => 'NEXUS NETZWERK', 'en' => 'NEXUS NETWORK'],
        'NEXUS NETWORK'            => ['de' => 'NEXUS NETZWERK', 'en' => 'NEXUS NETWORK'],
        'SCHLÜSSEL-TRESOR'         => ['de' => 'SCHLÜSSEL-TRESOR', 'en' => 'KEY VAULT'],
        'KEY VAULT'                => ['de' => 'SCHLÜSSEL-TRESOR', 'en' => 'KEY VAULT'],
        'ADD-ON VERWALTUNG'        => ['de' => 'ADD-ON VERWALTUNG', 'en' => 'ADD-ON MANAGER'],
        'MODUL-VERWALTUNG'         => ['de' => 'ADD-ON VERWALTUNG', 'en' => 'ADD-ON MANAGER'],
        'EINRICHTUNGSASSISTENT'    => ['de' => 'EINRICHTUNGSASSISTENT', 'en' => 'SETUP WIZARD'],
        'EINRICHTUNGS-GUIDE'       => ['de' => 'EINRICHTUNGSASSISTENT', 'en' => 'SETUP WIZARD'],

        // =========================================================================
        // 2. SECTIONS IN SIDEBAR
        // =========================================================================
        'I. ANALYSE & MONITORING'    => ['de' => 'I. ANALYSE & MONITORING', 'en' => 'I. INTELLIGENCE & TELEMETRY'],
        'I. INTELLIGENCE'            => ['de' => 'I. ANALYSE & MONITORING', 'en' => 'I. INTELLIGENCE & TELEMETRY'],
        'II. AKTIVE ABWEHR-MODULE'   => ['de' => 'II. AKTIVE ABWEHR-MODULE', 'en' => 'II. ACTIVE DEFENSE STACK'],
        'II. ACTIVE DEFENSE STACK'   => ['de' => 'II. AKTIVE ABWEHR-MODULE', 'en' => 'II. ACTIVE DEFENSE STACK'],
        'III. TÄUSCHUNG & HONEYPOTS' => ['de' => 'III. TÄUSCHUNG & HONEYPOTS', 'en' => 'III. DECEPTION GRID'],
        'III. DECEPTION GRID'        => ['de' => 'III. TÄUSCHUNG & HONEYPOTS', 'en' => 'III. DECEPTION GRID'],
        'IV. SYSTEMHÄRTUNG & KERNEL' => ['de' => 'IV. SYSTEMHÄRTUNG & KERNEL', 'en' => 'IV. DEEP CORE & HARDENING'],
        'IV. DEEP CORE'              => ['de' => 'IV. SYSTEMHÄRTUNG & KERNEL', 'en' => 'IV. DEEP CORE & HARDENING'],
        'V. DATENSCHUTZ & COMPLIANCE'=> ['de' => 'V. DATENSCHUTZ & COMPLIANCE', 'en' => 'V. PRIVACY & COMPLIANCE'],
        'V. PRIVACY'                 => ['de' => 'V. DATENSCHUTZ & COMPLIANCE', 'en' => 'V. PRIVACY & COMPLIANCE'],
        'VI. SYSTEM & ASSISTENT'     => ['de' => 'VI. SYSTEM & ASSISTENT', 'en' => 'VI. SYSTEM & TOOLS'],
        'VI. SYSTEM'                 => ['de' => 'VI. SYSTEM & ASSISTENT', 'en' => 'VI. SYSTEM & TOOLS'],

        // =========================================================================
        // 3. COMMON CONTROLS, BUTTONS & LABELS
        // =========================================================================
        'CONFIG SAVE'            => ['de' => 'EINSTELLUNGEN SPEICHERN', 'en' => 'SAVE CONFIG'],
        'ONLINE'                 => ['de' => 'ONLINE', 'en' => 'ONLINE'],
        'STANDBY'                => ['de' => 'STANDBY', 'en' => 'STANDBY'],
        'OFFLINE'                => ['de' => 'OFFLINE', 'en' => 'OFFLINE'],
        'AKTIV'                  => ['de' => 'AKTIV', 'en' => 'ACTIVE'],
        'ACTIVE'                 => ['de' => 'AKTIV', 'en' => 'ACTIVE'],
        'DEAKTIVIERT'            => ['de' => 'DEAKTIVIERT', 'en' => 'DISABLED'],
        'DISABLED'               => ['de' => 'DEAKTIVIERT', 'en' => 'DISABLED'],
        'INAKTIV'                => ['de' => 'INAKTIV', 'en' => 'INACTIVE'],
        'INACTIVE'               => ['de' => 'INAKTIV', 'en' => 'INACTIVE'],
        'FEHLT'                  => ['de' => 'FEHLT', 'en' => 'MISSING'],
        'MISSING'                => ['de' => 'FEHLT', 'en' => 'MISSING'],
        'BEREIT'                 => ['de' => 'BEREIT', 'en' => 'READY'],
        'READY'                  => ['de' => 'BEREIT', 'en' => 'READY'],
        'EDITION'                => ['de' => 'EDITION', 'en' => 'EDITION'],
        'SPRACHE'                => ['de' => 'SPRACHE', 'en' => 'LANGUAGE'],
        'CORE STATUS'            => ['de' => 'KERN-STATUS', 'en' => 'CORE STATUS'],
        'CONFIG'                 => ['de' => 'KONFIGURATION', 'en' => 'CONFIG'],
        'MODUL'                  => ['de' => 'MODUL', 'en' => 'MODULE'],
        'MODULE'                 => ['de' => 'MODUL', 'en' => 'MODULE'],
        'Status:'                => ['de' => 'Status:', 'en' => 'Status:'],
        'PASS'                   => ['de' => 'BESTANDEN', 'en' => 'PASS'],
        'FAIL'                   => ['de' => 'FEHLGESCHLAGEN', 'en' => 'FAIL'],
        'WARN'                   => ['de' => 'WARNUNG', 'en' => 'WARN'],
        'SCHLIESSEN'             => ['de' => 'SCHLIESSEN', 'en' => 'CLOSE'],
        'CLOSE'                  => ['de' => 'SCHLIESSEN', 'en' => 'CLOSE'],
        'UNBAN'                  => ['de' => 'ENTSPERREN', 'en' => 'UNBAN'],
        'ABORT'                  => ['de' => 'ABBRECHEN', 'en' => 'ABORT'],
        'EXECUTE UNBAN'          => ['de' => 'ENTSPERRUNG AUSFÜHREN', 'en' => 'EXECUTE UNBAN'],
        'PREV'                   => ['de' => 'ZURÜCK', 'en' => 'PREV'],
        'NEXT'                   => ['de' => 'WEITER', 'en' => 'NEXT'],
        'VIEW'                   => ['de' => 'ANSEHEN', 'en' => 'VIEW'],
        'ACTION'                 => ['de' => 'AKTION', 'en' => 'ACTION'],
        'DETAILS'                => ['de' => 'DETAILS', 'en' => 'DETAILS'],
        'TYPE'                   => ['de' => 'TYP', 'en' => 'TYPE'],
        'DATEIPFAD (TARGET)'     => ['de' => 'DATEIPFAD (ZIEL)', 'en' => 'FILE PATH (TARGET)'],
        'SOURCE VIEWER'          => ['de' => 'QUELLCODE-BETRACHTER', 'en' => 'SOURCE VIEWER'],
        'BLOCK'                  => ['de' => 'BLOCKIERT', 'en' => 'BLOCK'],
        'SAFE'                   => ['de' => 'SICHER', 'en' => 'SAFE'],
        'TARGET IP:'             => ['de' => 'ZIEL-IP:', 'en' => 'TARGET IP:'],
        'VERDICT:'               => ['de' => 'BEWERTUNG:', 'en' => 'VERDICT:'],

        // =========================================================================
        // 4. COMMAND CENTER & SCORING DESCRIPTIONS
        // =========================================================================
        'GEDEFENSE COMMAND CENTER'             => ['de' => 'GEDEFENSE KONTROLLZENTRUM', 'en' => 'GEDEFENSE COMMAND CENTER'],
        'VGT COMMAND CENTER'                   => ['de' => 'VGT KONTROLLZENTRUM', 'en' => 'VGT COMMAND CENTER'],
        'ACTIVE SHIELD LEVEL:'                 => ['de' => 'AKTIVER SCHUTZLEVEL:', 'en' => 'ACTIVE SHIELD LEVEL:'],
        'NOC CORE STATUS:'                     => ['de' => 'NOC KERN-STATUS:', 'en' => 'NOC CORE STATUS:'],
        'SYSTEM EDITION:'                      => ['de' => 'SYSTEM-EDITION:', 'en' => 'SYSTEM EDITION:'],
        'OPEN CORE'                            => ['de' => 'OPEN CORE', 'en' => 'OPEN CORE'],
        'SYSTEM PROTOCOLS:'                    => ['de' => 'SYSTEM-PROTOKOLLE:', 'en' => 'SYSTEM PROTOCOLS:'],
        'COCKPIT PROTECTION MATRIX'            => ['de' => 'COCKPIT SCHUTZ-MATRIX', 'en' => 'COCKPIT PROTECTION MATRIX'],
        'MAXIMALER SCHUTZ'                     => ['de' => 'MAXIMALER SCHUTZ', 'en' => 'MAXIMUM PROTECTION'],
        'OPTIMALER SCHUTZ'                     => ['de' => 'OPTIMALER SCHUTZ', 'en' => 'OPTIMAL PROTECTION'],
        'MINIMALER SCHUTZ'                     => ['de' => 'MINIMALER SCHUTZ', 'en' => 'MINIMAL PROTECTION'],
        'GEFÄHRDET (LOW)'                      => ['de' => 'GEFÄHRDET (NIEDRIG)', 'en' => 'VULNERABLE (LOW)'],
        'Herausragende Abwehrbereitschaft. Alle kritischen Module und Perimeter-Schilde sind aktiv. Die Seite läuft unter maximalem Schutz.' => [
            'de' => 'Herausragende Abwehrbereitschaft. Alle kritischen Module und Perimeter-Schilde sind aktiv. Die Seite läuft unter maximalem Schutz.',
            'en' => 'Outstanding defense readiness. All critical modules and perimeter shields are active. The site operates under maximum protection.'
        ],
        'Herausragende Abwehrbereitschaft. Alle kritischen Sicherheitsmodule und Perimeter-Schilde sind aktiv. Die Seite läuft unter maximalem Schutz.' => [
            'de' => 'Herausragende Abwehrbereitschaft. Alle kritischen Sicherheitsmodule und Perimeter-Schilde sind aktiv. Die Seite läuft unter maximalem Schutz.',
            'en' => 'Outstanding defense readiness. All critical security modules and perimeter shields are active. The site operates under maximum protection.'
        ],
        'Hohe Sicherheitsabdeckung. Die primären Firewalls und Härtungskomponenten sind aktiv. Einige Deception-Schilde oder periphere WAFs könnten noch hinzugeschaltet werden.' => [
            'de' => 'Hohe Sicherheitsabdeckung. Die primären Firewalls und Härtungskomponenten sind aktiv. Einige Deception-Schilde oder periphere WAFs könnten noch hinzugeschaltet werden.',
            'en' => 'High security coverage. Primary firewalls and hardening components are active. Additional deception grids or peripheral WAF layers can be engaged.'
        ],
        'Basisüberwachung ist aktiv. Es wird dringend empfohlen, Aegis WAF, Titan Hardening und Zeus Defender im Setup-Wizard oder Command Center zu konfigurieren.' => [
            'de' => 'Basisüberwachung ist aktiv. Es wird dringend empfohlen, Aegis WAF, Titan Hardening und Zeus Defender im Setup-Wizard oder Command Center zu konfigurieren.',
            'en' => 'Basic surveillance is active. It is strongly recommended to configure Aegis WAF, Titan Hardening, and Zeus Defender in the Setup Wizard or Command Center.'
        ],
        'Kritischer Zustand! Fast alle Sicherheitsmodule sind inaktiv. Das System bietet derzeit keinen ausreichenden Schutz vor Targeted Exploits oder Brute-Force-Angriffen.' => [
            'de' => 'Kritischer Zustand! Fast alle Sicherheitsmodule sind inaktiv. Das System bietet derzeit keinen ausreichenden Schutz vor Targeted Exploits oder Brute-Force-Angriffen.',
            'en' => 'Critical state! Almost all security modules are inactive. The system currently provides insufficient protection against targeted exploits or brute-force attacks.'
        ],
        'Unbegrenzte, server-eigene Cyber-Abwehr ohne externe Cloud-Abhängigkeiten oder Telemetrie-Leaks. 100% DSGVO-souverän und latenzfrei.' => [
            'de' => 'Unbegrenzte, server-eigene Cyber-Abwehr ohne externe Cloud-Abhängigkeiten oder Telemetrie-Leaks. 100% DSGVO-souverän und latenzfrei.',
            'en' => 'Unlimited server-native cyber defense without external cloud dependencies or telemetry leaks. 100% GDPR-compliant and zero-latency.'
        ],
        'GLOBAL COGNITIVE INCIDENT PROTOCOLS'  => ['de' => 'GLOBALE VORFALLS-PROTOKOLLE', 'en' => 'GLOBAL COGNITIVE INCIDENT PROTOCOLS'],
        'COMBINED LOGS'                        => ['de' => 'KOMBINIERTE LOGS', 'en' => 'COMBINED LOGS'],
        'SHIELD CLEAN'                         => ['de' => 'SCHILD SAUBER', 'en' => 'SHIELD CLEAN'],
        'Keine Sicherheitsvorfälle im Protokoll verzeichnet.' => ['de' => 'Keine Sicherheitsvorfälle im Protokoll verzeichnet.', 'en' => 'No security incidents recorded in logs.'],
        'TIMESTAMP'                            => ['de' => 'ZEITSTEMPEL', 'en' => 'TIMESTAMP'],
        'SOURCE'                               => ['de' => 'QUELLE', 'en' => 'SOURCE'],
        'IP ADDRESS'                           => ['de' => 'IP-ADRESSE', 'en' => 'IP ADDRESS'],
        'EVENT DETAILS'                        => ['de' => 'EREIGNIS-DETAILS', 'en' => 'EVENT DETAILS'],
        'ORACLE INTELLIGENCE AUDIT FEED'       => ['de' => 'ORAKEL ANALYSE-FEED', 'en' => 'ORACLE INTELLIGENCE AUDIT FEED'],
        'ORACLE STANDBY'                       => ['de' => 'ORAKEL STANDBY', 'en' => 'ORACLE STANDBY'],
        'REAL-TIME'                            => ['de' => 'ECHTZEIT', 'en' => 'REAL-TIME'],
        'Das AI-Modell ist bereit. Es wartet auf eingehende Request-Analysen.' => ['de' => 'Das AI-Modell ist bereit. Es wartet auf eingehende Request-Analysen.', 'en' => 'The AI model is ready. Awaiting incoming request analyses.'],
        'HARMONY REASONING (AI AUDIT)'         => ['de' => 'KI-BEGRÜNDUNG (AUDIT)', 'en' => 'HARMONY REASONING (AI AUDIT)'],
        'RAW PAYLOAD (INTERCEPTED)'            => ['de' => 'ROH-PAYLOAD (ABGEFANGEN)', 'en' => 'RAW PAYLOAD (INTERCEPTED)'],
        'CORE SHIELD VITALITY'                 => ['de' => 'KERN-SCHUTZ VITALITÄT', 'en' => 'CORE SHIELD VITALITY'],
        'SHIELDED'                             => ['de' => 'GESCHÜTZT', 'en' => 'SHIELDED'],
        'WAF Blocked'                          => ['de' => 'WAF Blockiert', 'en' => 'WAF Blocked'],
        'AI Strikes'                           => ['de' => 'KI Abwehren', 'en' => 'AI Strikes'],
        'INTEGRITY baseline'                   => ['de' => 'Integritäts-Baseline', 'en' => 'Integrity Baseline'],
        'No Scan Data'                         => ['de' => 'Keine Scan-Daten', 'en' => 'No Scan Data'],
        'State: Violated'                      => ['de' => 'Status: Verletzt', 'en' => 'State: Violated'],
        'State: Valid'                         => ['de' => 'Status: Gültig', 'en' => 'State: Valid'],
        'Systemintegrität verifiziert.'        => ['de' => 'Systemintegrität verifiziert.', 'en' => 'System integrity verified.'],
        'Bitte starten Sie einen manuellen Integritäts-Scan.' => ['de' => 'Bitte starten Sie einen manuellen Integritäts-Scan.', 'en' => 'Please start a manual integrity scan.'],
        'ANOMALIEN BEHEBEN'                    => ['de' => 'ANOMALIEN BEHEBEN', 'en' => 'RESOLVE ANOMALIES'],
        'SCAN MANAGER'                         => ['de' => 'SCAN MANAGER', 'en' => 'SCAN MANAGER'],
        'OPEN CORE EDITION'                    => ['de' => 'OPEN CORE EDITION', 'en' => 'OPEN CORE EDITION'],
        'SOVEREIGN (AGPLv3)'                   => ['de' => 'SOUVERÄN (AGPLv3)', 'en' => 'SOVEREIGN (AGPLv3)'],
        'SaaS Equivalent Value'                => ['de' => 'SaaS-Äquivalenzwert', 'en' => 'SaaS Equivalent Value'],
        'Included Free in Open Core'           => ['de' => 'Kostenlos im Open Core enthalten', 'en' => 'Included Free in Open Core'],
        'Zero-Latency Pre-Boot Kernel WAF'     => ['de' => 'Latenzfreie Pre-Boot Kernel-WAF', 'en' => 'Zero-Latency Pre-Boot Kernel WAF'],
        'Prometheus Heuristic AI & Malware Guard' => ['de' => 'Prometheus heuristische KI & Malware-Wächter', 'en' => 'Prometheus Heuristic AI & Malware Guard'],
        'Nemesis Deception Grid & Tarpit Traps'=> ['de' => 'Nemesis Täuschungs-Gitter & Tarpit-Fallen', 'en' => 'Nemesis Deception Grid & Tarpit Traps'],
        'Morpheus RASP Runtime Sandboxing'     => ['de' => 'Morpheus RASP Laufzeit-Sandboxing', 'en' => 'Morpheus RASP Runtime Sandboxing'],
        'Extensible Add-On Hub (VLP, Builder, SEO)' => ['de' => 'Erweiterbarer Add-On Hub (VLP, Builder, SEO)', 'en' => 'Extensible Add-On Hub (VLP, Builder, SEO)'],

        // =========================================================================
        // 5. ORACLE SCANNER & CHECKS
        // =========================================================================
        'ORACLE SYSTEM AUDIT'        => ['de' => 'ORAKEL SYSTEM-AUDIT', 'en' => 'ORACLE SYSTEM AUDIT'],
        'Prophecy Engine:'           => ['de' => 'Prophezeiungs-Engine:', 'en' => 'Prophecy Engine:'],
        'SYSTEM OPTIMAL'             => ['de' => 'SYSTEM OPTIMAL', 'en' => 'SYSTEM OPTIMAL'],
        'ANOMALIES DETECTED'         => ['de' => 'ANOMALIEN ERKANNT', 'en' => 'ANOMALIES DETECTED'],
        'TOTAL CHECKS'               => ['de' => 'GESAMT-PRÜFUNGEN', 'en' => 'TOTAL CHECKS'],
        'PASSED VECTORS'             => ['de' => 'BESTANDENE VEKTOREN', 'en' => 'PASSED VECTORS'],
        'ANOMALIES FOUND'            => ['de' => 'GEFUNDENE ANOMALIEN', 'en' => 'ANOMALIES FOUND'],
        'SECURITY CHECK DEFINITION'  => ['de' => 'SICHERHEITSPRÜFUNG DEFINITION', 'en' => 'SECURITY CHECK DEFINITION'],
        'STATUS'                     => ['de' => 'STATUS', 'en' => 'STATUS'],
        'ANALYSIS RESULT (PROPHECY)' => ['de' => 'ANALYSE-ERGEBNIS (PROPHEZEIUNG)', 'en' => 'ANALYSIS RESULT (PROPHECY)'],
        'RUN COMPLETE SYSTEM AUDIT'  => ['de' => 'VOLLSTÄNDIGEN SYSTEM-AUDIT AUSFÜHREN', 'en' => 'RUN COMPLETE SYSTEM AUDIT'],

        // =========================================================================
        // 6. INTEGRITY MONITOR
        // =========================================================================
        'SYSTEM INTEGRITY MONITOR'    => ['de' => 'SYSTEM INTEGRITÄTS-MONITOR', 'en' => 'SYSTEM INTEGRITY MONITOR'],
        'FILE HASHING ENGINE'        => ['de' => 'DATEI-HASHING ENGINE', 'en' => 'FILE HASHING ENGINE'],
        'BREACH DETECTED'            => ['de' => 'SICHERHEITSVERLETZUNG ERKANNT', 'en' => 'BREACH DETECTED'],
        'Last Deep Scan:'            => ['de' => 'Letzter Deep Scan:', 'en' => 'Last Deep Scan:'],
        'Never'                      => ['de' => 'Nie', 'en' => 'Never'],
        'EXPORT ANALYSE-DATEN'       => ['de' => 'ANALYSEDATEN EXPORTIEREN', 'en' => 'EXPORT ANALYSIS DATA'],
        'RUN DEEP SCAN'              => ['de' => 'DEEP-SCAN AUSFÜHREN', 'en' => 'RUN DEEP SCAN'],
        'AWAITING INITIALIZATION'    => ['de' => 'WARTE AUF INITIALISIERUNG', 'en' => 'AWAITING INITIALIZATION'],
        'Kein Integritäts-Bericht im System verzeichnet. Bitte starten Sie einen manuellen Baseline-Scan, um das Hashing-Netzwerk zu aktivieren.' => [
            'de' => 'Kein Integritäts-Bericht im System verzeichnet. Bitte starten Sie einen manuellen Baseline-Scan, um das Hashing-Netzwerk zu aktivieren.',
            'en' => 'No integrity report recorded in the system. Please run a manual baseline scan to initialize the hashing network.'
        ],
        'SYSTEM SECURE'              => ['de' => 'SYSTEM GESICHERT', 'en' => 'SYSTEM SECURE'],
        'Alle überwachten Dateien stimmen exakt mit dem kryptographischen Manifest überein. Es wurden keine nicht-autorisierten Modifikationen (Zero-Day/Malware) im Dateisystem festgestellt.' => [
            'de' => 'Alle überwachten Dateien stimmen exakt mit dem kryptographischen Manifest überein. Es wurden keine nicht-autorisierten Modifikationen (Zero-Day/Malware) im Dateisystem festgestellt.',
            'en' => 'All monitored files match the cryptographic baseline manifest. No unauthorized modifications (zero-day/malware) were detected in the filesystem.'
        ],
        'CRITICAL ANOMALIES DETECTED'=> ['de' => 'KRITISCHE ANOMALIEN ERKANNT', 'en' => 'CRITICAL ANOMALIES DETECTED'],
        'BASELINE UPDATEN (APPROVE)' => ['de' => 'BASELINE AKTUALISIEREN (GENEHMIGEN)', 'en' => 'UPDATE BASELINE (APPROVE)'],

        // =========================================================================
        // 7. SECURITY CENTER
        // =========================================================================
        'SENTINEL ASSURANCE PLANE'   => ['de' => 'SICHERHEITS-ARCHITEKTUR EBENE', 'en' => 'SENTINEL ASSURANCE PLANE'],
        'Architecture Security Center'=> ['de' => 'Architektur Sicherheitszentrale', 'en' => 'Architecture Security Center'],
        'Verifiziert Trust-Boundaries, Laufzeit-Invarianten, Modulrechte und portable Schutzmechanismen direkt innerhalb der Suite.' => [
            'de' => 'Verifiziert Trust-Boundaries, Laufzeit-Invarianten, Modulrechte und portable Schutzmechanismen direkt innerhalb der Suite.',
            'en' => 'Verifies trust boundaries, runtime invariants, module permissions, and portable defense mechanisms directly within the suite.'
        ],
        'Deep self-test ausführen'   => ['de' => 'Deep Self-Test ausführen', 'en' => 'Run Deep Self-Test'],
        'Weighted assurance score'   => ['de' => 'Gewichteter Sicherheits-Score', 'en' => 'Weighted Assurance Score'],
        'Invarianten bestätigt'      => ['de' => 'Invarianten bestätigt', 'en' => 'Invariants Confirmed'],
        'Portabilitätsgrenzen'       => ['de' => 'Portabilitätsgrenzen', 'en' => 'Portability Boundaries'],
        'Handlung erforderlich'      => ['de' => 'Handlung erforderlich', 'en' => 'Action Required'],
        'Rechteprofile erfasst'      => ['de' => 'Rechteprofile erfasst', 'en' => 'Permission Profiles Captured'],
        'Integrity checks'           => ['de' => 'Integritäts-Prüfungen', 'en' => 'Integrity Checks'],
        'Trust architecture'         => ['de' => 'Vertrauens-Architektur', 'en' => 'Trust Architecture'],
        'Module rights matrix'       => ['de' => 'Modul-Rechte-Matrix', 'en' => 'Module Rights Matrix'],

        // =========================================================================
        // 8. NOC / SYSTEM STATUS & THREAT MATRIX
        // =========================================================================
        'NETWORK OPERATIONS CENTER'  => ['de' => 'NETZWERK-KONTROLLZENTRUM (NOC)', 'en' => 'NETWORK OPERATIONS CENTER'],
        'NOC VITAL MATRIX'           => ['de' => 'NOC VITAL-MATRIX', 'en' => 'NOC VITAL MATRIX'],
        'GLOBAL TELEMETRY NEXUS'     => ['de' => 'GLOBALER TELEMETRIE-NEXUS', 'en' => 'GLOBAL TELEMETRY NEXUS'],
        'GLOBAL TELEMETRY FUSION'    => ['de' => 'GLOBALE TELEMETRIE-FUSION', 'en' => 'GLOBAL TELEMETRY FUSION'],
        'MULTI-NODE UPLINK'          => ['de' => 'MULTI-NODE UPLINK', 'en' => 'MULTI-NODE UPLINK'],
        'DECEPTION MATRIX'           => ['de' => 'TÄUSCHUNGS-MATRIX', 'en' => 'DECEPTION MATRIX'],
        'Aegis Terminations'         => ['de' => 'Aegis Blockierungen', 'en' => 'Aegis Terminations'],
        'Prometheus Anomalies'       => ['de' => 'Prometheus Anomalien', 'en' => 'Prometheus Anomalies'],
        'Nemesis Deceptions'         => ['de' => 'Nemesis Täuschungen', 'en' => 'Nemesis Deceptions'],
        'Oracle AI Defenses'         => ['de' => 'Orakel KI-Abwehrmaßnahmen', 'en' => 'Oracle AI Defenses'],
        'Ghost Trap Kills'           => ['de' => 'Ghost Trap Abfangungen', 'en' => 'Ghost Trap Kills'],
        'Total Interventions'        => ['de' => 'Gesamte Abwehrmaßnahmen', 'en' => 'Total Interventions'],
        'Prometheus Strikes'         => ['de' => 'Prometheus Abwehren', 'en' => 'Prometheus Strikes'],
        'Active Subsystems'          => ['de' => 'Aktive Subsysteme', 'en' => 'Active Subsystems'],
        'Core Health'                => ['de' => 'Kern-Zustand', 'en' => 'Core Health'],
        'CORE HEALTH:'               => ['de' => 'KERN-ZUSTAND:', 'en' => 'CORE HEALTH:'],
        'OPTIMAL'                    => ['de' => 'OPTIMAL', 'en' => 'OPTIMAL'],
        'DEGRADED'                   => ['de' => 'EINGESCHRÄNKT', 'en' => 'DEGRADED'],
        'L1 Ingress Defense'         => ['de' => 'L1 Eingangs-Abwehr', 'en' => 'L1 Ingress Defense'],
        'Secure Ingress & Payload Obfuscation' => ['de' => 'Sicherer Eingang & Payload-Verschleierung', 'en' => 'Secure Ingress & Payload Obfuscation'],
        'L7 Deception Honeypot Network' => ['de' => 'L7 Täuschungs-Honeypot-Netzwerk', 'en' => 'L7 Deception Honeypot Network'],
        'Autonomous Scanner Scheduler'=> ['de' => 'Autonomer Scanner-Zeitplaner', 'en' => 'Autonomous Scanner Scheduler'],
        'Neural Intelligence Grid Uplink' => ['de' => 'Neuronales Intelligence-Netzwerk', 'en' => 'Neural Intelligence Grid Uplink'],
        'Zero-Trust Hypervisor Sandbox' => ['de' => 'Zero-Trust Hypervisor Sandbox', 'en' => 'Zero-Trust Hypervisor Sandbox'],
        'Kernel Hardening & Camouflage' => ['de' => 'Kernel-Härtung & Tarnung', 'en' => 'Kernel Hardening & Camouflage'],
        'VisionLegalPro Asset Downloader' => ['de' => 'VisionLegalPro Asset-Downloader', 'en' => 'VisionLegalPro Asset Downloader'],
        'Omega Protocol Network Operations Center. Live Telemetry & Module Diagnostics.' => [
            'de' => 'Omega Protocol Netzwerk-Operationszentrum. Live-Telemetrie & Modul-Diagnostik.',
            'en' => 'Omega Protocol Network Operations Center. Live Telemetry & Module Diagnostics.'
        ],
        'Security Audit Harness'     => ['de' => 'Sicherheits-Audit-Prüffeld', 'en' => 'Security Audit Harness'],
        'Static invariant scan for Sentinel hardening rules.' => [
            'de' => 'Statischer Invarianten-Scan für Sentinel-Härtungsregeln.',
            'en' => 'Static invariant scan for Sentinel hardening rules.'
        ],
        'Module Integrity Matrix'    => ['de' => 'Modul-Integritäts-Matrix', 'en' => 'Module Integrity Matrix'],

        // =========================================================================
        // 9. LOGS & SYSTEM EVENTS
        // =========================================================================
        'GLOBAL SECURITY EVENTS'     => ['de' => 'GLOBALE SICHERHEITS-EREIGNISSE', 'en' => 'GLOBAL SECURITY EVENTS'],
        'Log Aggregation:'           => ['de' => 'Protokoll-Aggregation:', 'en' => 'Log Aggregation:'],
        'OMNI-CHANNEL ACTIVE'        => ['de' => 'OMNI-CHANNEL AKTIV', 'en' => 'OMNI-CHANNEL ACTIVE'],
        'SHOWING TOP %d EVENTS'      => ['de' => 'ZEIGE TOP %d EREIGNISSE', 'en' => 'SHOWING TOP %d EVENTS'],
        'SYSTEM CLEAN'               => ['de' => 'SYSTEM SAUBER', 'en' => 'SYSTEM CLEAN'],
        'Keine Sicherheitsvorfälle im Protokoll verzeichnet. Die VGT Intelligence Engine überwacht den Perimeter weiterhin aktiv auf Anomalien.' => [
            'de' => 'Keine Sicherheitsvorfälle im Protokoll verzeichnet. Die VGT Intelligence Engine überwacht den Perimeter weiterhin aktiv auf Anomalien.',
            'en' => 'No security incidents recorded in the logs. The VGT Intelligence Engine continues active perimeter anomaly surveillance.'
        ],
        'TIMESTAMP (UTC)'            => ['de' => 'ZEITSTEMPEL (UTC)', 'en' => 'TIMESTAMP (UTC)'],
        'SOURCE / MODULE'            => ['de' => 'QUELLE / MODUL', 'en' => 'SOURCE / MODULE'],

        // =========================================================================
        // 10. ACTIVE DEFENSE MODULES (AEGIS, ZEUS, PROMETHEUS, CERBERUS, AIRLOCK)
        // =========================================================================
        'AEGIS FIREWALL MATRIX'      => ['de' => 'AEGIS FIREWALL MATRIX', 'en' => 'AEGIS FIREWALL MATRIX'],
        'ENABLE FIREWALL ENGINE'     => ['de' => 'FIREWALL-ENGINE AKTIVIEREN', 'en' => 'ENABLE FIREWALL ENGINE'],
        'Deep Packet Inspection für SQLi, XSS, RCE, und LFI Vektoren.' => [
            'de' => 'Deep Packet Inspection für SQLi, XSS, RCE, und LFI Vektoren.',
            'en' => 'Deep Packet Inspection for SQLi, XSS, RCE, and LFI vectors.'
        ],
        'PROTECTION PROTOCOL'        => ['de' => 'SCHUTZ-PROTOKOLL', 'en' => 'PROTECTION PROTOCOL'],
        'Definiert die Reaktions-Policy bei positiven Threat-Signaturen.' => [
            'de' => 'Definiert die Reaktions-Policy bei positiven Threat-Signaturen.',
            'en' => 'Defines response policy upon positive threat signatures.'
        ],
        'STRICT (Instant Ban)'       => ['de' => 'STRIKT (Sofortiger Ban)', 'en' => 'STRICT (Instant Ban)'],
        'LEARNING (Log & Observe)'   => ['de' => 'LERNMODUS (Protokollieren & Beobachten)', 'en' => 'LEARNING (Log & Observe)'],
        'SOVEREIGN WHITELIST'        => ['de' => 'SOUVERÄNE WHITELIST', 'en' => 'SOVEREIGN WHITELIST'],
        'TRUSTED IP ADDRESSES'       => ['de' => 'VERTRAUENSWÜRDIGE IP-ADRESSEN', 'en' => 'TRUSTED IP ADDRESSES'],
        'Eine IP pro Zeile. Diese IPs umgehen den AEGIS-Kernel und das Oracle vollständig.' => [
            'de' => 'Eine IP pro Zeile. Diese IPs umgehen den AEGIS-Kernel und das Oracle vollständig.',
            'en' => 'One IP per line. These IPs bypass the AEGIS kernel and Oracle entirely.'
        ],
        'TRUSTED USER-AGENTS'        => ['de' => 'VERTRAUENSWÜRDIGE USER-AGENTS', 'en' => 'TRUSTED USER-AGENTS'],
        'Ein Keyword pro Zeile (z.B. "UptimeRobot"). Warnung: UAs können leicht gespooft werden.' => [
            'de' => 'Ein Keyword pro Zeile (z.B. "UptimeRobot"). Warnung: UAs können leicht gespooft werden.',
            'en' => 'One keyword per line (e.g. "UptimeRobot"). Warning: UAs can easily be spoofed.'
        ],
        'ORACLE NEURAL LINK'         => ['de' => 'ORAKEL NEURONALER LINK', 'en' => 'ORACLE NEURAL LINK'],
        'Generative AI Heuristics Engine (Layer 7 Defense)' => [
            'de' => 'Generative KI Heuristik-Engine (Layer 7 Abwehr)',
            'en' => 'Generative AI Heuristics Engine (Layer 7 Defense)'
        ],
        'SYSTEM ONLINE'              => ['de' => 'SYSTEM ONLINE', 'en' => 'SYSTEM ONLINE'],
        'DISCONNECTED'               => ['de' => 'GETRENNT', 'en' => 'DISCONNECTED'],
        'Configure Oracle Uplink'    => ['de' => 'Orakel Uplink konfigurieren', 'en' => 'Configure Oracle Uplink'],
        'ACTIVE DEFENSE PATTERNS'    => ['de' => 'AKTIVE ABWEHR-MUSTER', 'en' => 'ACTIVE DEFENSE PATTERNS'],
        'SQL INJECTION'              => ['de' => 'SQL INJECTION', 'en' => 'SQL INJECTION'],
        'XSS (CROSS SITE SCRIPTING)' => ['de' => 'XSS (CROSS SITE SCRIPTING)', 'en' => 'XSS (CROSS SITE SCRIPTING)'],
        'RCE (REMOTE CODE EXECUTION)'=> ['de' => 'RCE (REMOTE CODE EXECUTION)', 'en' => 'RCE (REMOTE CODE EXECUTION)'],
        'LFI (LOCAL FILE INCLUSION)' => ['de' => 'LFI (LOCAL FILE INCLUSION)', 'en' => 'LFI (LOCAL FILE INCLUSION)'],
        'AI PROMPT INJECTION'        => ['de' => 'KI PROMPT INJECTION', 'en' => 'AI PROMPT INJECTION'],
        'ANOMALY DETECTION'          => ['de' => 'ANOMALIE-ERKENNUNG', 'en' => 'ANOMALY DETECTION'],

        // ZEUS VIEW STRINGS
        'ZEUS WAF COMPILER'          => ['de' => 'ZEUS WAF COMPILER', 'en' => 'ZEUS WAF COMPILER'],
        'Supreme Triad Integration: AEGIS (DPI) × PROMETHEUS (AI) × NEMESIS (Tarpit) <br> WICHTIG APCU muss installiert sein' => [
            'de' => 'Supreme Triad Integration: AEGIS (DPI) × PROMETHEUS (AI) × NEMESIS (Tarpit) <br> WICHTIG: APCu muss installiert sein',
            'en' => 'Supreme Triad Integration: AEGIS (DPI) × PROMETHEUS (AI) × NEMESIS (Tarpit) <br> IMPORTANT: APCu must be installed'
        ],
        'WAF KERNEL ONLINE'          => ['de' => 'WAF KERNEL ONLINE', 'en' => 'WAF KERNEL ONLINE'],
        'WAF OFFLINE / STANDBY'      => ['de' => 'WAF OFFLINE / STANDBY', 'en' => 'WAF OFFLINE / STANDBY'],
        'CRITICAL: Emergency Bypass URL' => ['de' => 'KRITISCH: Notfall-Bypass-URL', 'en' => 'CRITICAL: Emergency Bypass URL'],
        'Bewahre diese URL sicher auf. Falls du dich durch fehlerhafte Einstellungen oder das Prometheus Rate-Limiting selbst aus dem System aussperrst, kannst du die WAF mit diesem Link sofort temporär umgehen.' => [
            'de' => 'Bewahre diese URL sicher auf. Falls du dich durch fehlerhafte Einstellungen oder das Prometheus Rate-Limiting selbst aus dem System aussperrst, kannst du die WAF mit diesem Link sofort temporär umgehen.',
            'en' => 'Store this URL safely. If misconfigurations or Prometheus rate limiting ever lock you out, you can instantly bypass the WAF temporarily via this link.'
        ],
        'Pre-Boot WAF & AEGIS (DPI)' => ['de' => 'Pre-Boot WAF & AEGIS (DPI)', 'en' => 'Pre-Boot WAF & AEGIS (DPI)'],
        'Basic Perimeter Hardening'  => ['de' => 'Basis-Perimeter-Härtung', 'en' => 'Basic Perimeter Hardening'],
        'Sperrt Zugriffe auf wp-config.php, .htaccess und deaktiviert das Directory Listing.' => [
            'de' => 'Sperrt Zugriffe auf wp-config.php, .htaccess und deaktiviert das Directory Listing.',
            'en' => 'Blocks access to wp-config.php, .htaccess and disables directory listing.'
        ],
        'AEGIS DPI & 6G Matrix'      => ['de' => 'AEGIS DPI & 6G Matrix', 'en' => 'AEGIS DPI & 6G Matrix'],
        'Pre-Boot Erkennung von SQLi, XSS, LFI und Base64 Obfuscation. Blockiert böswillige Query-Strings in O(1) Laufzeit.' => [
            'de' => 'Pre-Boot Erkennung von SQLi, XSS, LFI und Base64 Obfuscation. Blockiert böswillige Query-Strings in O(1) Laufzeit.',
            'en' => 'Pre-Boot detection of SQLi, XSS, LFI, and Base64 obfuscation. Rejects malicious query strings in O(1) runtime.'
        ],
        'Terminate XML-RPC'          => ['de' => 'XML-RPC Terminieren', 'en' => 'Terminate XML-RPC'],
        'Blockiert alle Zugriffe auf xmlrpc.php präventiv im WAF-Kernel (Verhindert Amplification DDoS).' => [
            'de' => 'Blockiert alle Zugriffe auf xmlrpc.php präventiv im WAF-Kernel (Verhindert Amplification DDoS).',
            'en' => 'Blocks all access to xmlrpc.php proactively in the WAF kernel (prevents amplification DDoS).'
        ],
        'Fake Googlebot Extermination' => ['de' => 'Fake-Googlebot Vernichtung', 'en' => 'Fake Googlebot Extermination'],
        'Nutzt asynchrones Reverse-DNS Lookup Caching, um gefälschte Bots zu enttarnen.' => [
            'de' => 'Nutzt asynchrones Reverse-DNS Lookup Caching, um gefälschte Bots zu enttarnen.',
            'en' => 'Uses asynchronous reverse-DNS lookup caching to unmask spoofed bots.'
        ],
        'Filesystem & Spam Isolation'=> ['de' => 'Dateisystem- & Spam-Isolation', 'en' => 'Filesystem & Spam Isolation'],
        'Disable File Editor'        => ['de' => 'Datei-Editor deaktivieren', 'en' => 'Disable File Editor'],
        'Erzwingt DISALLOW_FILE_EDIT. Blockiert RCE Vektoren durch kompromittierte Admins.' => [
            'de' => 'Erzwingt DISALLOW_FILE_EDIT. Blockiert RCE Vektoren durch kompromittierte Admins.',
            'en' => 'Enforces DISALLOW_FILE_EDIT. Eliminates RCE vectors from compromised administrator accounts.'
        ],
        'Asset Hotlink Protection'   => ['de' => 'Asset-Hotlink Schutz', 'en' => 'Asset Hotlink Protection'],
        'Verhindert Traffic-Diebstahl auf .htaccess Ebene (Blockiert externe Bild/Asset Referrer).' => [
            'de' => 'Verhindert Traffic-Diebstahl auf .htaccess Ebene (Blockiert externe Bild/Asset Referrer).',
            'en' => 'Prevents bandwidth theft on .htaccess layer (blocks external image/asset referrers).'
        ],
        'Automated Spam Isolation'   => ['de' => 'Automatisierte Spam-Isolation', 'en' => 'Automated Spam Isolation'],
        'Blockiert extreme Link-Payloads und speist den Verstoß direkt in PROMETHEUS (+100 Threat Score).' => [
            'de' => 'Blockiert extreme Link-Payloads und speist den Verstoß direkt in PROMETHEUS (+100 Threat Score).',
            'en' => 'Blocks extreme link payloads and feeds the infraction directly into PROMETHEUS (+100 Threat Score).'
        ],
        'Auth Shield & PROMETHEUS Threat Thresholds' => [
            'de' => 'Auth-Schild & PROMETHEUS Schwellenwerte',
            'en' => 'Auth Shield & PROMETHEUS Threat Thresholds'
        ],
        'HADES OMEGA LOCK DETECTED'  => ['de' => 'HADES OMEGA SPERRE ERKANNT', 'en' => 'HADES OMEGA LOCK DETECTED'],
        'Das Hades Stealth Protocol verwaltet aktuell das Routing für <code>/wp-admin</code>. Dies ist deine globale Bypass-Route:' => [
            'de' => 'Das Hades Stealth Protocol verwaltet aktuell das Routing für <code>/wp-admin</code>. Dies ist deine globale Bypass-Route:',
            'en' => 'The Hades Stealth Protocol currently manages routing for <code>/wp-admin</code>. This is your global bypass route:'
        ],
        'Rename Login Portal (Slug)' => ['de' => 'Login-Portal umbenennen (Slug)', 'en' => 'Rename Login Portal (Slug)'],
        'Zugang nur über /wp-login.php?dein_slug' => [
            'de' => 'Zugang nur über /wp-login.php?dein_slug',
            'en' => 'Access strictly via /wp-login.php?your_slug'
        ],
        'Cryptographic Magic Cookie' => ['de' => 'Kryptographisches Magic Cookie', 'en' => 'Cryptographic Magic Cookie'],
        'WAF verlangt HMAC-verifiziertes Cookie.' => [
            'de' => 'WAF verlangt HMAC-verifiziertes Cookie.',
            'en' => 'WAF requires an HMAC-verified cookie.'
        ],
        'Prometheus 404 Event Horizon' => ['de' => 'Prometheus 404 Event-Horizon', 'en' => 'Prometheus 404 Event Horizon'],
        '404s/Stunde vor NEMESIS Tarpit (0 = Off).' => [
            'de' => '404s/Stunde vor NEMESIS Tarpit (0 = Aus).',
            'en' => '404s/hour before NEMESIS Tarpit (0 = Off).'
        ],
        'Max Login Failures'         => ['de' => 'Max. Login-Fehlversuche', 'en' => 'Max Login Failures'],
        'Maximalwert vor PROMETHEUS Lockdown.' => [
            'de' => 'Maximalwert vor PROMETHEUS Lockdown.',
            'en' => 'Maximum attempts prior to PROMETHEUS lockdown.'
        ],
        'Force Logout Timeout'       => ['de' => 'Zwangs-Logout Timeout', 'en' => 'Force Logout Timeout'],
        'Inaktive Sessions beenden (Sekunden).' => [
            'de' => 'Inaktive Sessions beenden (Sekunden).',
            'en' => 'Terminate idle sessions (seconds).'
        ],
        'WAF Kompilierung ist O(1) atomar. (PHP .user.ini Caching durch den Server kann bis zu 5 Min. dauern).' => [
            'de' => 'WAF Kompilierung ist O(1) atomar. (PHP .user.ini Caching durch den Server kann bis zu 5 Min. dauern).',
            'en' => 'WAF compilation is O(1) atomic. (PHP .user.ini caching by the host may take up to 5 min).'
        ],
        'COMPILE & DEPLOY WAF'       => ['de' => 'WAF KOMPILIEREN & DEPLOYEN', 'en' => 'COMPILE & DEPLOY WAF'],

        // AIRLOCK VIEW STRINGS
        'AIRLOCK'                    => ['de' => 'AIRLOCK', 'en' => 'AIRLOCK'],
        'L0 INGRESS'                 => ['de' => 'L0 EINGANGS-FILTER', 'en' => 'L0 INGRESS'],
        'Strict File-System Defense. Steuert Dateiuploads, blockiert eingebettete Payloads in Bildern und obfuskiert Dateinamen zur Verhinderung von Direct-Execution Angriffen am absoluten Nullpunkt des Stacks.' => [
            'de' => 'Strict File-System Defense. Steuert Dateiuploads, blockiert eingebettete Payloads in Bildern und obfuskiert Dateinamen zur Verhinderung von Direct-Execution Angriffen am absoluten Nullpunkt des Stacks.',
            'en' => 'Strict Filesystem Defense. Controls file uploads, sanitizes embedded payloads in media, and obfuscates filenames to eliminate direct execution attacks at stack zero.'
        ],
        'Ingress Policies'           => ['de' => 'Eingangs-Richtlinien', 'en' => 'Ingress Policies'],
        'Airlock Engine aktivieren'  => ['de' => 'Airlock-Engine aktivieren', 'en' => 'Enable Airlock Engine'],
        'Master-Switch. Deaktivieren für unlimitierten Raw-Upload (Gefahr!).' => [
            'de' => 'Master-Switch. Deaktivieren für unlimitierten Raw-Upload (Gefahr!).',
            'en' => 'Master switch. Disable for unrestricted raw uploads (high risk!).'
        ],
        'Cryptographic Filename Entropy' => ['de' => 'Kryptographische Dateinamen-Entropie', 'en' => 'Cryptographic Filename Entropy'],
        'Zerstört originale Dateinamen und ersetzt sie durch CRC32 Hashes. Verhindert Vorhersagbarkeit von Uploads.' => [
            'de' => 'Zerstört originale Dateinamen und ersetzt sie durch CRC32 Hashes. Verhindert Vorhersagbarkeit von Uploads.',
            'en' => 'Destroys original filenames and replaces them with CRC32 hashes. Eliminates predictable target upload paths.'
        ],
        'Hard Limit: Max Upload Size (MB)' => ['de' => 'Hard-Limit: Max. Upload-Größe (MB)', 'en' => 'Hard Limit: Max Upload Size (MB)'],
        'Dateien größer als dieser Wert werden auf Kernel-Ebene vom Airlock abgelehnt, bevor WordPress sie verarbeitet. Empfehlung: 5.' => [
            'de' => 'Dateien größer als dieser Wert werden auf Kernel-Ebene vom Airlock abgelehnt, bevor WordPress sie verarbeitet. Empfehlung: 5.',
            'en' => 'Files larger than this limit are rejected at kernel level by Airlock before WordPress processes them. Recommended: 5.'
        ],
        'Strict MIME/Extension Whitelist' => ['de' => 'Strikte MIME/Dateiendungs-Whitelist', 'en' => 'Strict MIME/Extension Whitelist'],
        'Komma-separierte Liste an erlaubten Dateiendungen. Alles andere prallt am L0 Shield ab.' => [
            'de' => 'Komma-separierte Liste an erlaubten Dateiendungen. Alles andere prallt am L0 Shield ab.',
            'en' => 'Comma-separated list of allowed file extensions. All other formats are bounced off the L0 shield.'
        ],
        'Airlock Policies Speichern' => ['de' => 'Airlock-Richtlinien speichern', 'en' => 'Save Airlock Policies'],
        'Scanner Matrix'             => ['de' => 'Scanner-Matrix', 'en' => 'Scanner Matrix'],
        'Airlock analysiert jeden eingehenden Upload-Stream über das %s Hook. Es vertraut keinen HTTP-Headern und extrahiert Payload-Daten direkt aus dem RAM-Buffer.' => [
            'de' => 'Airlock analysiert jeden eingehenden Upload-Stream über das %s Hook. Es vertraut keinen HTTP-Headern und extrahiert Payload-Daten direkt aus dem RAM-Buffer.',
            'en' => 'Airlock inspects every incoming upload stream via the %s hook. It trusts no HTTP headers and extracts payload data directly from the RAM buffer.'
        ],
        'Magic Bytes Verification'   => ['de' => 'Magic-Bytes Verifizierung', 'en' => 'Magic Bytes Verification'],
        'Scannt die ersten 1024 Bytes des Buffers um gefälschte Dateiendungen (z.B. shell.php.jpg) mathematisch zu entlarven.' => [
            'de' => 'Scannt die ersten 1024 Bytes des Buffers um gefälschte Dateiendungen (z.B. shell.php.jpg) mathematisch zu entlarven.',
            'en' => 'Inspects the first 1024 bytes of the memory buffer to mathematically unmask disguised file extensions (e.g. shell.php.jpg).'
        ],
        'SVG XML-Sanitization'       => ['de' => 'SVG XML-Bereinigung', 'en' => 'SVG XML Sanitization'],
        'Extrahiert und blockiert %s, %s und %s Vektoren in hochgeladenen Vektorgrafiken.' => [
            'de' => 'Extrahiert und blockiert %s, %s und %s Vektoren in hochgeladenen Vektorgrafiken.',
            'en' => 'Extracts and neutralizes %s, %s, and %s vectors in uploaded vector graphics.'
        ],
        'PHP Payload Detection'      => ['de' => 'PHP-Payload Erkennung', 'en' => 'PHP Payload Detection'],
        'Verhindert, dass Bilder mit injiziertem %s Code das System kompromittieren (Exif-RCE Abwehr).' => [
            'de' => 'Verhindert, dass Bilder mit injiziertem %s Code das System kompromittieren (Exif-RCE Abwehr).',
            'en' => 'Prevents media containing injected %s code from compromising the host (Exif RCE defense).'
        ],

        // GHOST TRAP VIEW STRINGS
        'GHOST TRAP'                 => ['de' => 'GHOST TRAP', 'en' => 'GHOST TRAP'],
        'L7 DECEPTION'               => ['de' => 'L7 TÄUSCHUNGS-GITTER', 'en' => 'L7 DECEPTION'],
        'Proaktives Honeypot-Netzwerk. Generiert unsichtbare Dummy-Dateien im Root-Verzeichnis. Jeder Scanner oder Bot, der diese Dateien abtastet, wird sofort permanent auf Netzwerk-Ebene blockiert (Auto-Ban).' => [
            'de' => 'Proaktives Honeypot-Netzwerk. Generiert unsichtbare Dummy-Dateien im Root-Verzeichnis. Jeder Scanner oder Bot, der diese Dateien abtastet, wird sofort permanent auf Netzwerk-Ebene blockiert (Auto-Ban).',
            'en' => 'Proactive honeypot network. Deploys invisible decoy files in the root folder. Any scanner or crawler touching these paths is permanently banned at network level (auto-ban).'
        ],
        'Generator Config'           => ['de' => 'Generator-Konfiguration', 'en' => 'Generator Config'],
        'Ghost Trap Engine aktivieren' => ['de' => 'Ghost-Trap-Engine aktivieren', 'en' => 'Enable Ghost Trap Engine'],
        'Aktiviert die Erstellung und Überwachung der künstlichen Systemfallen.' => [
            'de' => 'Aktiviert die Erstellung und Überwachung der künstlichen Systemfallen.',
            'en' => 'Enables generation and active telemetry surveillance of synthetic decoy files.'
        ],
        'Anzahl der Fallen (Nodes)'  => ['de' => 'Anzahl der Fallen (Nodes)', 'en' => 'Decoy Node Count'],
        'Legt fest, wie viele Honeypots im Root-Verzeichnis platziert werden (Max: 50).' => [
            'de' => 'Legt fest, wie viele Honeypots im Root-Verzeichnis platziert werden (Max: 50).',
            'en' => 'Defines how many honeypot nodes are placed in the root directory (Max: 50).'
        ],
        'Polymorphe Dateiendungen'   => ['de' => 'Polymorphe Dateiendungen', 'en' => 'Polymorphic Extensions'],
        'Komma-separierte Liste. Diese Endungen locken spezialisierte Scanner an (z.B. SQL-Dumper, Backup-Sniffer).' => [
            'de' => 'Komma-separierte Liste. Diese Endungen locken spezialisierte Scanner an (z.B. SQL-Dumper, Backup-Sniffer).',
            'en' => 'Comma-separated list. These extensions attract automated sniffers (e.g. SQL dumpers, backup crawlers).'
        ],
        'Namensgenerator-Logik (AI-Style)' => ['de' => 'Namensgenerator-Logik (AI-Stil)', 'en' => 'Name Generator Strategy (AI-Style)'],
        'Mixed Matrix (Empfohlen)'   => ['de' => 'Gemischte Matrix (Empfohlen)', 'en' => 'Mixed Matrix (Recommended)'],
        'System Fakes (wp-config-old, admin-test)' => ['de' => 'System-Attrappen (wp-config-old, admin-test)', 'en' => 'System Decoys (wp-config-old, admin-test)'],
        'Backup Fakes (db_dump_2024, site_backup)' => ['de' => 'Backup-Attrappen (db_dump_2024, site_backup)', 'en' => 'Backup Decoys (db_dump_2024, site_backup)'],
        'Random Hashes (a8f9c12a)'   => ['de' => 'Zufällige Hashes (a8f9c12a)', 'en' => 'Random Hashes (a8f9c12a)'],
        'Bestimmt das semantische Profil der Dateinamen.' => [
            'de' => 'Bestimmt das semantische Profil der Dateinamen.',
            'en' => 'Determines the semantic naming profile of decoy files.'
        ],
        'Fallen Generieren & Deployen' => ['de' => 'Fallen generieren & deployen', 'en' => 'Generate & Deploy Traps'],
        'Vorsicht: Beim Speichern wird das bestehende Honeypot-Netzwerk restlos vernichtet und komplett neu gewoben.' => [
            'de' => 'Vorsicht: Beim Speichern wird das bestehende Honeypot-Netzwerk restlos vernichtet und komplett neu gewoben.',
            'en' => 'Caution: Saving will completely purge existing honeypots and weave a fresh network.'
        ],
        'Deployment Manifest'        => ['de' => 'Bereitstellungs-Manifest', 'en' => 'Deployment Manifest'],
        'SYSTEM ACTIVE'              => ['de' => 'SYSTEM AKTIV', 'en' => 'SYSTEM ACTIVE'],
        'Das Netzwerk ist aktiv. Jeder HTTP-Zugriff auf die gelisteten Routen resultiert im sofortigen IP-Ban durch den Aegis-Kernel.' => [
            'de' => 'Das Netzwerk ist aktiv. Jeder HTTP-Zugriff auf die gelisteten Routen resultiert im sofortigen IP-Ban durch den Aegis-Kernel.',
            'en' => 'The network is active. Any HTTP probe against these routes triggers an instant IP ban by the Aegis kernel.'
        ],
        'SYSTEM OFFLINE'             => ['de' => 'SYSTEM OFFLINE', 'en' => 'SYSTEM OFFLINE'],
        'Keine Honeypot-Nodes im Dateisystem platziert.' => [
            'de' => 'Keine Honeypot-Nodes im Dateisystem platziert.',
            'en' => 'No honeypot nodes currently deployed in the filesystem.'
        ],

        // TITAN HARDENING VIEW STRINGS
        'TITAN KERNEL HARDENING (OMEGA V6)' => ['de' => 'TITAN KERNEL HÄRTUNG (OMEGA V6)', 'en' => 'TITAN KERNEL HARDENING (OMEGA V6)'],
        'System Core Shield:'        => ['de' => 'System-Kern Schutz:', 'en' => 'System Core Shield:'],
        'LOCKED & SECURED'           => ['de' => 'GESPERRT & GESICHERT', 'en' => 'LOCKED & SECURED'],
        'UNPROTECTED'                => ['de' => 'UNGESCHÜTZT', 'en' => 'UNPROTECTED'],
        'SYSTEM HINWEIS:'            => ['de' => 'SYSTEM-HINWEIS:', 'en' => 'SYSTEM NOTICE:'],
        'OMEGA V6 aktiviert radikale Tarnkappen-Protokolle (VGT_OS). Konfigurationsänderungen erfordern einen Klick auf "CONFIG SAVE". Nginx-User müssen die generierte .conf im Server-Block includen.' => [
            'de' => 'OMEGA V6 aktiviert radikale Tarnkappen-Protokolle (VGT_OS). Konfigurationsänderungen erfordern einen Klick auf "CONFIG SAVE". Nginx-User müssen die generierte .conf im Server-Block includen.',
            'en' => 'OMEGA V6 engages stealth camouflage protocols (VGT_OS). Configuration updates require clicking "CONFIG SAVE". Nginx users must include the generated .conf file in their server block.'
        ],
        'TITAN SHIELD (SECURITY HEADERS & FIREWALL)' => ['de' => 'TITAN SCHILD (SECURITY HEADERS & FIREWALL)', 'en' => 'TITAN SHIELD (SECURITY HEADERS & FIREWALL)'],
        'Aktiviert die physischen Firewalls auf File-System Ebene für Root, Uploads, Content und generiert den Nginx-Shield.' => [
            'de' => 'Aktiviert die physischen Firewalls auf File-System Ebene für Root, Uploads, Content und generiert den Nginx-Shield.',
            'en' => 'Activates filesystem-level physical firewalls for Root, Uploads, Content, and compiles the Nginx shield.'
        ],
        'ROOT FW'                    => ['de' => 'ROOT FW', 'en' => 'ROOT FW'],
        'UPLOAD VAULT'               => ['de' => 'UPLOAD TRESOR', 'en' => 'UPLOAD VAULT'],
        'CONTENT SENTINEL'           => ['de' => 'CONTENT SENTINEL', 'en' => 'CONTENT SENTINEL'],
        'INCLUDES GUARD'             => ['de' => 'INCLUDES GUARD', 'en' => 'INCLUDES GUARD'],
        'NGINX VAULT'                => ['de' => 'NGINX VAULT', 'en' => 'NGINX VAULT'],
        'NGINX SERVER INTEGRATION'   => ['de' => 'NGINX SERVER INTEGRATION', 'en' => 'NGINX SERVER INTEGRATION'],
        'Fügen Sie den folgenden include-Befehl in den server {} Block Ihrer Nginx-Konfiguration (z.B. in AApanel) ein und starten Sie Nginx neu:' => [
            'de' => 'Fügen Sie den folgenden include-Befehl in den server {} Block Ihrer Nginx-Konfiguration (z.B. in AApanel) ein und starten Sie Nginx neu:',
            'en' => 'Add the following include directive into your Nginx server {} configuration block (e.g. in aaPanel) and reload Nginx:'
        ],
        'IDENTITY CAMOUFLAGE & ANTI-RECONNAISSANCE' => ['de' => 'IDENTITÄTS-TARNUNG & ANTI-AUFKLÄRUNG', 'en' => 'IDENTITY CAMOUFLAGE & ANTI-RECONNAISSANCE'],
        'VGT_OS SERVER SPOOFING'     => ['de' => 'VGT_OS SERVER-SPOOFING', 'en' => 'VGT_OS SERVER SPOOFING'],
        'Überschreibt den HTTP Server Header mit "VGT_OS/1.0.0". Macht OS- und Server-Fingerprinting unmöglich.' => [
            'de' => 'Überschreibt den HTTP Server Header mit "VGT_OS/1.0.0". Macht OS- und Server-Fingerprinting unmöglich.',
            'en' => 'Overrides the HTTP Server header with "VGT_OS/1.0.0". Prevents operating system and web server fingerprinting.'
        ],
        'USER ENUMERATION KILL'      => ['de' => 'BENUTZER-AUFZÄHLUNG STOPPEN', 'en' => 'USER ENUMERATION KILL'],
        'Blockiert /?author=1 und schließt den /wp/v2/users REST-Endpunkt. Verhindert Brute-Force Vorbereitungen.' => [
            'de' => 'Blockiert /?author=1 und schließt den /wp/v2/users REST-Endpunkt. Verhindert Brute-Force Vorbereitungen.',
            'en' => 'Blocks /?author=1 and seals the /wp/v2/users REST endpoint. Prevents reconnaissance for brute-force attacks.'
        ],
        'VERSION STRING STRIPPING'   => ['de' => 'VERSIONS-STRINGS ENTFERNEN', 'en' => 'VERSION STRING STRIPPING'],
        'Entfernt alle ?ver=x.x.x Anhängsel aus CSS/JS. Verhindert CVE-Matching durch Scanner.' => [
            'de' => 'Entfernt alle ?ver=x.x.x Anhängsel aus CSS/JS. Verhindert CVE-Matching durch Scanner.',
            'en' => 'Strips ?ver=x.x.x parameters from CSS/JS assets. Prevents automated CVE matching by scanners.'
        ],
        'FRAMEWORK SPOOFING'         => ['de' => 'FRAMEWORK-SPOOFING', 'en' => 'FRAMEWORK SPOOFING'],
        'Injiziert Fake-Meta-Tags (Laravel, Drupal), um Wappalyzer & Bots in die Irre zu führen.' => [
            'de' => 'Injiziert Fake-Meta-Tags (Laravel, Drupal), um Wappalyzer & Bots in die Irre zu führen.',
            'en' => 'Injects decoy meta tags (Laravel, Drupal) to confuse Wappalyzer and intelligence crawlers.'
        ],
        'Deaktiviert (Standard)'     => ['de' => 'Deaktiviert (Standard)', 'en' => 'Disabled (Default)'],
        'Laravel Framework (Header Only)' => ['de' => 'Laravel Framework (Header Only)', 'en' => 'Laravel Framework (Header Only)'],
        'Drupal 9 (Header & Meta Tags)' => ['de' => 'Drupal 9 (Header & Meta Tags)', 'en' => 'Drupal 9 (Header & Meta Tags)'],
        'TACTICAL ACTIVE DEFENSE'    => ['de' => 'TAKTIK & AKTIVE ABWEHR', 'en' => 'TACTICAL ACTIVE DEFENSE'],
        'THE LOGIN GATEKEEPER'       => ['de' => 'DER LOGIN-TORWÄCHTER', 'en' => 'THE LOGIN GATEKEEPER'],
        'Versteckt den Login. wp-login.php wirft einen 403-Fehler, außer mit Geheim-Parameter:' => [
            'de' => 'Versteckt den Login. wp-login.php wirft einen 403-Fehler, außer mit Geheim-Parameter:',
            'en' => 'Hides login endpoints. wp-login.php returns 403 Forbidden unless accompanied by secret parameter:'
        ],
        'XML-RPC HONEYPOT (LETHAL TRAP)' => ['de' => 'XML-RPC HONEYPOT (TÖDLICHE FALLE)', 'en' => 'XML-RPC HONEYPOT (LETHAL TRAP)'],
        'Bleibt als Falle offen. Jeder Scanner, der die Datei berührt, wird <strong>sofort und lebenslang gebannt</strong>.' => [
            'de' => 'Bleibt als Falle offen. Jeder Scanner, der die Datei berührt, wird <strong>sofort und lebenslang gebannt</strong>.',
            'en' => 'Remains open as a lethal trap. Any scanner probing the file is <strong>instantly and permanently banned</strong>.'
        ],
        'WP-INCLUDES SENTINEL'       => ['de' => 'WP-INCLUDES SENTINEL', 'en' => 'WP-INCLUDES SENTINEL'],
        'Legt eine extrem strikte Firewall um /wp-includes/, die direkte PHP-Aufrufe serverseitig eliminiert.' => [
            'de' => 'Legt eine extrem strikte Firewall um /wp-includes/, die direkte PHP-Aufrufe serverseitig eliminiert.',
            'en' => 'Deploys a rigid perimeter around /wp-includes/, preventing direct PHP execution on the server.'
        ],
        'PERFORMANCE & API RESTRICTION' => ['de' => 'PERFORMANCE & API-EINSCHRÄNKUNG', 'en' => 'PERFORMANCE & API RESTRICTION'],
        'HEARTBEAT FLATLINE'         => ['de' => 'HEARTBEAT STILLLEGEN', 'en' => 'HEARTBEAT FLATLINE'],
        'Deaktiviert die ressourcenintensive Heartbeat-API, die oft für DDoS-Amplification missbraucht wird.' => [
            'de' => 'Deaktiviert die ressourcenintensive Heartbeat-API, die oft für DDoS-Amplification missbraucht wird.',
            'en' => 'Disables the resource-intensive Heartbeat API frequently abused for DDoS amplification.'
        ],
        'XML-RPC BLOCKIEREN (PASSIV)' => ['de' => 'XML-RPC BLOCKIEREN (PASSIV)', 'en' => 'BLOCK XML-RPC (PASSIVE)'],
        'Schließt die Schnittstelle komplett. (Wird vom Honeypot oben überschrieben).' => [
            'de' => 'Schließt die Schnittstelle komplett. (Wird vom Honeypot oben überschrieben).',
            'en' => 'Shuts down the endpoint entirely. (Overridden when lethal honeypot is active).'
        ],
        'REST API EINSCHRÄNKEN'      => ['de' => 'REST API EINSCHRÄNKEN', 'en' => 'RESTRICT REST API'],
        'Erlaubt Zugriff auf die REST API nur für eingeloggte Benutzer.' => [
            'de' => 'Erlaubt Zugriff auf die REST API nur für eingeloggte Benutzer.',
            'en' => 'Restricts REST API endpoints to authenticated user sessions.'
        ],
        'RSS & ATOM FEEDS DEAKTIVIEREN' => ['de' => 'RSS & ATOM FEEDS DEAKTIVIEREN', 'en' => 'DISABLE RSS & ATOM FEEDS'],
        'Verhindert Content-Scraping. Gibt "403 Forbidden" bei Feed-Zugriff zurück.' => [
            'de' => 'Verhindert Content-Scraping. Gibt "403 Forbidden" bei Feed-Zugriff zurück.',
            'en' => 'Prevents scraper bots from harvesting content. Returns "403 Forbidden" on all feed requests.'
        ],

        // HADES STEALTH VIEW STRINGS
        'HADES GHOST PROTOCOL'       => ['de' => 'HADES GHOST PROTOKOLL', 'en' => 'HADES GHOST PROTOCOL'],
        'Cloaking Engine:'           => ['de' => 'Tarnkappen-Engine:', 'en' => 'Cloaking Engine:'],
        'STEALTH ACTIVE'             => ['de' => 'TARNUNG AKTIV', 'en' => 'STEALTH ACTIVE'],
        'VISIBLE (UNPROTECTED)'      => ['de' => 'SICHTBAR (UNGESCHÜTZT)', 'en' => 'VISIBLE (UNPROTECTED)'],
        'CRITICAL ACTION REQUIRED:'  => ['de' => 'KRITISCHE AKTION ERFORDERLICH:', 'en' => 'CRITICAL ACTION REQUIRED:'],
        'Nach der Aktivierung oder Deaktivierung des Stealth Modes MÜSSEN Sie zwingend die Permalinks neu generieren (Einstellungen > Permalinks > Speichern klicken), andernfalls kommt es zu 404 Fehlern, da die .htaccess Rewrite-Regeln nicht kompiliert wurden.' => [
            'de' => 'Nach der Aktivierung oder Deaktivierung des Stealth Modes MÜSSEN Sie zwingend die Permalinks neu generieren (Einstellungen > Permalinks > Speichern klicken), andernfalls kommt es zu 404 Fehlern, da die .htaccess Rewrite-Regeln nicht kompiliert wurden.',
            'en' => 'After toggling Stealth Mode, you MUST regenerate permalinks (Settings > Permalinks > click Save), otherwise 404 errors will occur because rewrite rules were not yet compiled.'
        ],
        'NGINX SERVER DETECTED:'     => ['de' => 'NGINX-SERVER ERKANNT:', 'en' => 'NGINX SERVER DETECTED:'],
        'Wir haben festgestellt, dass dieses System auf NGINX läuft. Die automatische .htaccess Generierung greift hier nicht. Sie müssen folgenden Code-Block manuell in Ihren NGINX server {} Block kopieren und den Server neu laden (nginx -s reload).' => [
            'de' => 'Wir haben festgestellt, dass dieses System auf NGINX läuft. Die automatische .htaccess Generierung greift hier nicht. Sie müssen folgenden Code-Block manuell in Ihren NGINX server {} Block kopieren und den Server neu laden (nginx -s reload).',
            'en' => 'This host is running NGINX. Dynamic .htaccess rules do not apply. You must copy the following code block into your NGINX server {} directive and reload the daemon (nginx -s reload).'
        ],
        'ACTIVATE STEALTH MODE'      => ['de' => 'TARNMODUS AKTIVIEREN', 'en' => 'ACTIVATE STEALTH MODE'],
        'Überschreibt die Standard-URLs von WordPress und verbirgt eindeutige Pfade wie /wp-content/, /plugins/ oder /themes/ vor externen Scannern und Wappalyzer.' => [
            'de' => 'Überschreibt die Standard-URLs von WordPress und verbirgt eindeutige Pfade wie /wp-content/, /plugins/ oder /themes/ vor externen Scannern und Wappalyzer.',
            'en' => 'Rewrites default WordPress URLs, hiding distinctive paths like /wp-content/, /plugins/, or /themes/ from automated scanners.'
        ],
        'ADMIN ACCESS MATRIX (404 MIMICRY)' => ['de' => 'ADMIN-ZUGANGSMATRIX (404 MIMIKRY)', 'en' => 'ADMIN ACCESS MATRIX (404 MIMICRY)'],
        'Isoliert /wp-admin und /wp-login.php. Ohne dieses kryptographische Parameter-Paar in der URL wird ein hartes 404 Not Found simuliert. Scans und Brute-Force-Angriffe laufen ins Leere.' => [
            'de' => 'Isoliert /wp-admin und /wp-login.php. Ohne dieses kryptographische Parameter-Paar in der URL wird ein hartes 404 Not Found simuliert. Scans und Brute-Force-Angriffe laufen ins Leere.',
            'en' => 'Isolates /wp-admin and /wp-login.php. Without this URL parameter token, a hard 404 Not Found is returned. Automated brute-force attempts hit a dead end.'
        ],
        'Access Route:'              => ['de' => 'Zugangs-Route:', 'en' => 'Access Route:'],
        'ACTIVE CLOAKING VECTORS (DIRECTORY MAPPING)' => ['de' => 'AKTIVE TARNVEKTOREN (VERZEICHNIS-MAPPING)', 'en' => 'ACTIVE CLOAKING VECTORS (DIRECTORY MAPPING)'],
        'EXPOSED PATH (FRONTEND)'    => ['de' => 'ÖFFENTLICHER PFAD (FRONTEND)', 'en' => 'EXPOSED PATH (FRONTEND)'],
        'PHYSICAL TARGET (BACKEND)'  => ['de' => 'PHYSISCHES ZIEL (BACKEND)', 'en' => 'PHYSICAL TARGET (BACKEND)'],

        // NEMESIS DECEPTION VIEW STRINGS
        'NEMESIS ENGINE'             => ['de' => 'NEMESIS ENGINE', 'en' => 'NEMESIS ENGINE'],
        'Advanced Deception, Tarpitting & Counterintelligence Protocol' => [
            'de' => 'Fortschrittliches Täuschungs-, Tarpit- & Spionageabwehr-Protokoll',
            'en' => 'Advanced Deception, Tarpitting & Counterintelligence Protocol'
        ],
        'SHIELD OFFLINE'             => ['de' => 'SCHILD OFFLINE', 'en' => 'SHIELD OFFLINE'],
        'ACTIVE STRIKE: ARMED'       => ['de' => 'AKTIVER STRIKE: SCHARF', 'en' => 'ACTIVE STRIKE: ARMED'],
        'DECEPTION MATRIX: ENGAGED'  => ['de' => 'TÄUSCHUNGSMATRIX: AKTIV', 'en' => 'DECEPTION MATRIX: ENGAGED'],
        'Nemesis Protocol Authorization' => ['de' => 'Nemesis-Protokoll Autorisierung', 'en' => 'Nemesis Protocol Authorization'],
        'Aktiviert die asymmetrische <strong>Verteidigungs- und Täuschungsmatrix (100% Legal)</strong>. Das System leitet Angreifer in langsame Endlosschleifen, markiert Content-Diebstahl und liefert Scrapern mutierte Fake-Daten aus, um deren Datenbanken wertlos zu machen.' => [
            'de' => 'Aktiviert die asymmetrische <strong>Verteidigungs- und Täuschungsmatrix (100% Legal)</strong>. Das System leitet Angreifer in langsame Endlosschleifen, markiert Content-Diebstahl und liefert Scrapern mutierte Fake-Daten aus, um deren Datenbanken wertlos zu machen.',
            'en' => 'Engages the asymmetric <strong>defense & deception grid (100% Legal)</strong>. Traps scrapers in slow endless loops, watermarks stolen assets, and feeds mutated entropy to invalidate attacker databases.'
        ],
        'ENGAGED'                    => ['de' => 'AKTIV', 'en' => 'ENGAGED'],
        'Tarpit Strikes (All-Time)'  => ['de' => 'Tarpit-Abwehren (Gesamt)', 'en' => 'Tarpit Strikes (All-Time)'],
        'Canary Traps Triggered'     => ['de' => 'Ausgelöste Kanarienvogel-Fallen', 'en' => 'Canary Traps Triggered'],
        'Scrapers Poisoned / Sabotaged' => ['de' => 'Vergiftete / Sabotierte Scraper', 'en' => 'Scrapers Poisoned / Sabotaged'],
        'Tarpit Mode'                => ['de' => 'Tarpit-Modus', 'en' => 'Tarpit Mode'],
        'Simuliert kritische Schwachstellen (`.env`, `wp-config`). Legal Defense liefert extrem langsame Fake-Hashes aus, um gegnerische Threads an den Server zu binden (Ressourcen-Erschöpfung durch Zeit).' => [
            'de' => 'Simuliert kritische Schwachstellen (`.env`, `wp-config`). Legal Defense liefert extrem langsame Fake-Hashes aus, um gegnerische Threads an den Server zu binden (Ressourcen-Erschöpfung durch Zeit).',
            'en' => 'Emulates high-value targets (.env, wp-config). Legal Defense serves throttled dummy hashes to tie up adversary threads (time-based resource exhaustion).'
        ],
        'Cryptographic Canary'       => ['de' => 'Kryptographischer Kanarienvogel', 'en' => 'Cryptographic Canary'],
        'Verdeckte Dom-Injektion kryptographisch signierter Tracking-Tokens (HMAC-SHA256). Ermöglicht präzise Forensik und Data-Leak-Attribution bei unautorisiertem Scraping.' => [
            'de' => 'Verdeckte Dom-Injektion kryptographisch signierter Tracking-Tokens (HMAC-SHA256). Ermöglicht präzise Forensik und Data-Leak-Attribution bei unautorisiertem Scraping.',
            'en' => 'Covert DOM injection of cryptographically signed tracking tokens (HMAC-SHA256). Enables forensic leak attribution on unauthorized scraping.'
        ],
        'Polymorphic Poisoning'      => ['de' => 'Polymorphe Datenvergiftung', 'en' => 'Polymorphic Poisoning'],
        'Mutiert echten Content On-The-Fly bei erkannten Bot-Signaturen. Verhindert das Auslesen valider E-Mail-Adressen durch dynamische Injektion von 3-Byte Hex-Entropie.' => [
            'de' => 'Mutiert echten Content On-The-Fly bei erkannten Bot-Signaturen. Verhindert das Auslesen valider E-Mail-Adressen durch dynamische Injektion von 3-Byte Hex-Entropie.',
            'en' => 'Mutates payload content on-the-fly when bot signatures are recognized. Prevents harvesting of valid email addresses via 3-byte hex entropy injection.'
        ],
        'Kinetische Sabotage Aktiv'  => ['de' => 'Kinetische Sabotage Aktiv', 'en' => 'Kinetic Sabotage Active'],
        '<strong>Bounded Response</strong> liefert endliche Täuschungsdaten ohne PHP-Worker zu blockieren.' => [
            'de' => '<strong>Bounded Response</strong> liefert endliche Täuschungsdaten ohne PHP-Worker zu blockieren.',
            'en' => '<strong>Bounded Response</strong> returns finite decoy data without retaining PHP workers.'
        ],
        'Cookie Bombing Aktiv'       => ['de' => 'Cookie Bombing Aktiv', 'en' => 'Cookie Bombing Active'],
        '<strong>State Exhaustion:</strong> Flutet den Scraper mit hunderten gigantischen Session-Cookies. Führt bei automatisierten Bots zum sofortigen Out-of-Memory Absturz.' => [
            'de' => '<strong>State Exhaustion:</strong> Flutet den Scraper mit hunderten gigantischen Session-Cookies. Führt bei automatisierten Bots zum sofortigen Out-of-Memory Absturz.',
            'en' => '<strong>State Exhaustion:</strong> Floods the scraper with hundreds of giant session cookies, causing automated bots to terminate out of memory.'
        ],
        'Aggressive DB-Corruption Aktiv' => ['de' => 'Aggressive DB-Vergiftung Aktiv', 'en' => 'Aggressive DB Corruption Active'],
        '<strong>Database Overloader:</strong> Generiert bei jedem Aufruf on-the-fly 50 hochrealistische Honeypot-Adressen. Dies maximiert die Datenbank-Kosten des Angreifers ins Unermessliche.' => [
            'de' => '<strong>Database Overloader:</strong> Generiert bei jedem Aufruf on-the-fly 50 hochrealistische Honeypot-Adressen. Dies maximiert die Datenbank-Kosten des Angreifers ins Unermessliche.',
            'en' => '<strong>Database Overloader:</strong> Generates 50 synthetic high-value honeypot entries on-the-fly per request, multiplying the adversary database storage cost.'
        ],

        // MORPHEUS VIEW STRINGS
        'Morpheus AI Builder'        => ['de' => 'Morpheus KI-Builder', 'en' => 'Morpheus AI Builder'],
        'Zero-Trust Runtime Sandbox. KI-gestützte O(1) Matrix Kompilierung.' => [
            'de' => 'Zero-Trust Runtime Sandbox. KI-gestützte O(1) Matrix Kompilierung.',
            'en' => 'Zero-Trust Runtime Sandbox. AI-driven O(1) matrix compilation.'
        ],
        'Audit'                      => ['de' => 'Audit', 'en' => 'Audit'],
        'Strict'                     => ['de' => 'Strikt', 'en' => 'Strict'],
        'ENFORCEMENT ACTIVE'         => ['de' => 'DURCHSETZUNG AKTIV', 'en' => 'ENFORCEMENT ACTIVE'],
        'LEARNING MODE'              => ['de' => 'LERNMODUS', 'en' => 'LEARNING MODE'],
        'ACTION REQUIRED'            => ['de' => 'HANDLUNG ERFORDERLICH', 'en' => 'ACTION REQUIRED'],
        'Pending AI Approvals'       => ['de' => 'Ausstehende KI-Freigaben', 'en' => 'Pending AI Approvals'],
        'Keine offenen KI-Vorschläge. Die Matrix ist synchron.' => [
            'de' => 'Keine offenen KI-Vorschläge. Die Matrix ist synchron.',
            'en' => 'No pending AI proposals. The matrix is fully synchronized.'
        ],
        'Active Audit Loggers'       => ['de' => 'Aktive Audit-Logger', 'en' => 'Active Audit Loggers'],
        'Keine Plugins gefunden. Warte auf Systemereignisse.' => [
            'de' => 'Keine Plugins gefunden. Warte auf Systemereignisse.',
            'en' => 'No plugins detected. Awaiting system activity.'
        ],
        'Sammelt Zugriffsvektoren für KI-Analyse...' => [
            'de' => 'Sammelt Zugriffsvektoren für KI-Analyse...',
            'en' => 'Harvesting execution vectors for AI analysis...'
        ],
        'Groq Llama-3.3-70B hat eine sichere Matrix erstellt.' => [
            'de' => 'Groq Llama-3.3-70B hat eine sichere Matrix erstellt.',
            'en' => 'Groq Llama-3.3-70B has synthesized a hardened security matrix.'
        ],
        'Preview JSON'               => ['de' => 'JSON Vorschau', 'en' => 'Preview JSON'],
        'Approve'                    => ['de' => 'Freigeben', 'en' => 'Approve'],
        'AI Build Ready'             => ['de' => 'KI-Build Bereit', 'en' => 'AI Build Ready'],
        'Force AI Build'             => ['de' => 'KI-Build Erzwingen', 'en' => 'Force AI Build'],
        'AWAITING TRAFFIC'           => ['de' => 'WARTE AUF TRAFFIC', 'en' => 'AWAITING TRAFFIC'],
        'Matrix manuell über Groq berechnen lassen' => [
            'de' => 'Matrix manuell über Groq berechnen lassen',
            'en' => 'Calculate runtime matrix manually via Groq'
        ],

        // CERBERUS VIEW STRINGS
        'Active Opcache Bans'        => ['de' => 'Aktive Opcache-Sperren', 'en' => 'Active Opcache Bans'],
        'Threats Eliminated (24h)'   => ['de' => 'Eliminierte Bedrohungen (24h)', 'en' => 'Threats Eliminated (24h)'],
        'Active Threat Roster'       => ['de' => 'Aktive Bedrohungs-Liste', 'en' => 'Active Threat Roster'],
        'Terminated Payload (XSS-Isolated)' => ['de' => 'Terminierte Payload (XSS-Isoliert)', 'en' => 'Terminated Payload (XSS-Isolated)'],
        'Command'                    => ['de' => 'Befehl', 'en' => 'Command'],
        'Perimeter clear. No active blockades.' => [
            'de' => 'Perimeter sauber. Keine aktiven Sperren.',
            'en' => 'Perimeter clear. No active blockades.'
        ],
        'SECURITY OVERRIDE REQUIRED' => ['de' => 'SICHERHEITS-ÜBERSCHREIBUNG ERFORDERLICH', 'en' => 'SECURITY OVERRIDE REQUIRED'],
        'Sie sind im Begriff, eine Perimeter-Sperre manuell aufzuheben. Dadurch erhält die betroffene Einheit sofortigen Zugriff auf die Systemressourcen.' => [
            'de' => 'Sie sind im Begriff, eine Perimeter-Sperre manuell aufzuheben. Dadurch erhält die betroffene Einheit sofortigen Zugriff auf die Systemressourcen.',
            'en' => 'You are about to manually lift a perimeter ban. This unit will regain immediate access to system resources.'
        ],

        // =========================================================================
        // 11. ADD-ON HUB & SETUP WIZARD STRINGS
        // =========================================================================
        'GeDefense WP Add-On Hub & Modul-Verwaltung' => ['de' => 'GeDefense WP Add-On Hub & Modulverwaltung', 'en' => 'GeDefense WP Add-On Hub & Module Manager'],
        'OPEN CORE ARCHITECTURE'     => ['de' => 'OPEN CORE ARCHITEKTUR', 'en' => 'OPEN CORE ARCHITECTURE'],
        'Der GeDefense Security Core läuft standardmäßig schlank und eigenständig. Erweiterte Business-Module (z. B. Datenschutz/VLP, Lightweight Builder, SEO Architect) können hier als Add-On ZIP-Pakete hochgeladen und sicher verwaltet werden.' => [
            'de' => 'Der GeDefense Security Core läuft standardmäßig schlank und eigenständig. Erweiterte Business-Module (z. B. Datenschutz/VLP, Lightweight Builder, SEO Architect) können hier als Add-On ZIP-Pakete hochgeladen und sicher verwaltet werden.',
            'en' => 'The GeDefense Security Core runs lean and self-contained by default. Advanced business modules (e.g. Privacy/VLP, Lightweight Builder, SEO Architect) can be uploaded here as Add-On ZIP packages and securely managed.'
        ],
        'Offizielles GeDefense Add-On Paket (.zip) hochladen' => [
            'de' => 'Offizielles GeDefense Add-On Paket (.zip) hochladen',
            'en' => 'Upload Official GeDefense Add-On Package (.zip)'
        ],
        'Datei hierher ziehen oder klicken, um eine ZIP-Datei auszuwählen' => [
            'de' => 'Datei hierher ziehen oder klicken, um eine ZIP-Datei auszuwählen',
            'en' => 'Drag & drop file here or click to select a ZIP package'
        ],
        'NICHT INSTALLIERT'          => ['de' => 'NICHT INSTALLIERT', 'en' => 'NOT INSTALLED'],
        'Optionales Add-On'          => ['de' => 'Optionales Add-On', 'en' => 'Optional Add-On'],
        'Add-On löschen'             => ['de' => 'Add-On löschen', 'en' => 'Delete Add-On'],
        'Laden Sie das ZIP-Paket oben hoch, um dieses Modul zu aktivieren.' => [
            'de' => 'Laden Sie das ZIP-Paket oben hoch, um dieses Modul zu aktivieren.',
            'en' => 'Upload the ZIP package above to activate this module.'
        ],
        'GeDefense WP Setup Wizard'  => ['de' => 'GeDefense WP Einrichtungsassistent', 'en' => 'GeDefense WP Setup Wizard'],
        'Initialisierung & Konfiguration des Abwehr-Kernels' => [
            'de' => 'Initialisierung & Konfiguration des Abwehr-Kernels',
            'en' => 'Initialization & Configuration of the Defense Kernel'
        ],
        'IP-Schutz'                  => ['de' => 'IP-Schutz', 'en' => 'IP Protection'],
        'Firewall & WAF'             => ['de' => 'Firewall & WAF', 'en' => 'Firewall & WAF'],
        'Malware & RASP'             => ['de' => 'Malware & RASP', 'en' => 'Malware & RASP'],
        'Täuschung & Fallen'         => ['de' => 'Täuschung & Fallen', 'en' => 'Deception & Traps'],
        'Härtung & Stealth'          => ['de' => 'Härtung & Stealth', 'en' => 'Hardening & Stealth'],
        'Autopilot & AI'             => ['de' => 'Autopilot & AI', 'en' => 'Autopilot & AI'],
        'Scharfschaltung'            => ['de' => 'Scharfschaltung', 'en' => 'Final Ignition'],
        'Weiter &rarr;'              => ['de' => 'Weiter &rarr;', 'en' => 'Next &rarr;'],
        '&larr; Zurück'              => ['de' => '&larr; Zurück', 'en' => '&larr; Back'],
        'SICHERHEITSMODULE AKTIVIEREN &rarr;' => ['de' => 'SICHERHEITSMODULE AKTIVIEREN &rarr;', 'en' => 'ACTIVATE SECURITY MODULES &rarr;'],
        'GeDefense WP erfolgreich scharfgeschaltet!' => ['de' => 'GeDefense WP erfolgreich scharfgeschaltet!', 'en' => 'GeDefense WP successfully activated!'],
        'ZUM COMMAND CENTER &rarr;'  => ['de' => 'ZUM KONTROLLZENTRUM &rarr;', 'en' => 'TO COMMAND CENTER &rarr;'],
        'Schritt 1: System-Check & IP-Whitelisting' => ['de' => 'Schritt 1: System-Check & IP-Whitelisting', 'en' => 'Step 1: System Check & IP Whitelisting'],
        'Um zu verhindern, dass du während Sicherheitsprüfungen versehentlich ausgesperrt wirst, hinterlegt GeDefense deine IP-Adresse auf den internen Whitelists von AEGIS, Cerberus und Prometheus.' => [
            'de' => 'Um zu verhindern, dass du während Sicherheitsprüfungen versehentlich ausgesperrt wirst, hinterlegt GeDefense deine IP-Adresse auf den internen Whitelists von AEGIS, Cerberus und Prometheus.',
            'en' => 'To prevent accidental lockouts during security audits, GeDefense adds your IP address to the internal whitelists of AEGIS, Cerberus, and Prometheus.'
        ],
        'Erkannte Administrator-IP: %s' => ['de' => 'Erkannte Administrator-IP: %s', 'en' => 'Detected Administrator IP: %s'],
        'AEGIS & CERBERUS IP-Whitelist (eine pro Zeile)' => ['de' => 'AEGIS & CERBERUS IP-Whitelist (eine pro Zeile)', 'en' => 'AEGIS & CERBERUS IP Whitelist (one per line)'],
        'Diese IPs werden niemals von der Firewall oder dem Ban-System blockiert.' => [
            'de' => 'Diese IPs werden niemals von der Firewall oder dem Ban-System blockiert.',
            'en' => 'These IPs will never be blocked by the firewall or ban engine.'
        ],
        'PROMETHEUS Scanner Whitelist (eine pro Zeile)' => ['de' => 'PROMETHEUS Scanner Whitelist (eine pro Zeile)', 'en' => 'PROMETHEUS Scanner Whitelist (one per line)'],
        'IPs, deren Dateioperationen nicht heuristisch überwacht werden.' => [
            'de' => 'IPs, deren Dateioperationen nicht heuristisch überwacht werden.',
            'en' => 'IPs whose file operations are exempted from heuristic surveillance.'
        ],
        'Schritt 2: Firewall- & WAF-Schichten (AEGIS, Zeus & Cerberus)' => [
            'de' => 'Schritt 2: Firewall- & WAF-Schichten (AEGIS, Zeus & Cerberus)',
            'en' => 'Step 2: Firewall & WAF Layers (AEGIS, Zeus & Cerberus)'
        ],
        'Konfiguriere den mehrstufigen WAF-Schutz gegen SQL-Injections, Cross-Site Scripting (XSS), RCE und bösartige Botnetze.' => [
            'de' => 'Konfiguriere den mehrstufigen WAF-Schutz gegen SQL-Injections, Cross-Site Scripting (XSS), RCE und bösartige Botnetze.',
            'en' => 'Configure multi-tier WAF defense against SQL Injections, Cross-Site Scripting (XSS), RCE, and malicious botnets.'
        ],
        'AEGIS Deep Packet Inspection (DPI)' => ['de' => 'AEGIS Deep Packet Inspection (DPI)', 'en' => 'AEGIS Deep Packet Inspection (DPI)'],
        'Untersucht eingehende GET-, POST- und Header-Payloads in Echtzeit auf Angriffsvektoren (SQLi, XSS, LFI, RCE, Object-Injection).' => [
            'de' => 'Untersucht eingehende GET-, POST- und Header-Payloads in Echtzeit auf Angriffsvektoren (SQLi, XSS, LFI, RCE, Object-Injection).',
            'en' => 'Inspects incoming GET, POST, and Header payloads in real-time for attack vectors (SQLi, XSS, LFI, RCE, Object Injection).'
        ],
        'AEGIS Betriebsmodus:'       => ['de' => 'AEGIS Betriebsmodus:', 'en' => 'AEGIS Operating Mode:'],
        'Learning Mode (Empfohlen für den Start – Protokollierung ohne permanente IP-Bans)' => [
            'de' => 'Learning Mode (Empfohlen für den Start – Protokollierung ohne permanente IP-Bans)',
            'en' => 'Learning Mode (Recommended for startup – logging without permanent IP bans)'
        ],
        'Strict Mode (Zero-Trust – Sofortiger Request-Abbruch und automatischer IP-Ban)' => [
            'de' => 'Strict Mode (Zero-Trust – Sofortiger Request-Abbruch und automatischer IP-Ban)',
            'en' => 'Strict Mode (Zero-Trust – Immediate request termination and automatic IP ban)'
        ],
        'Zeus Pre-Boot & 6G Blacklist WAF' => ['de' => 'Zeus Pre-Boot & 6G Blacklist WAF', 'en' => 'Zeus Pre-Boot & 6G Blacklist WAF'],
        'Extrem schlanker WAF-Wächter, der bösartige Bots und 6G-Query-Angriffe blockiert, bevor WordPress komplexe Datenbankabfragen lädt.' => [
            'de' => 'Extrem schlanker WAF-Wächter, der bösartige Bots und 6G-Query-Angriffe blockiert, bevor WordPress komplexe Datenbankabfragen lädt.',
            'en' => 'Ultra-lean WAF sentinel blocking malicious bots and 6G query attacks before WordPress loads database queries.'
        ],
        'Basis Firewall-Regeln'      => ['de' => 'Basis Firewall-Regeln', 'en' => 'Basic Firewall Rules'],
        '6G Blacklist Matrix'        => ['de' => '6G Blacklist Matrix', 'en' => '6G Blacklist Matrix'],
        'Cerberus Instant Drop & Rate Limiter' => ['de' => 'Cerberus Instant Drop & Rate Limiter', 'en' => 'Cerberus Instant Drop & Rate Limiter'],
        'Verwaltet die zentrale IP-Sperrliste. Blockierte Angreifer werden sofort mit HTTP 403 abgewiesen, ohne CPU- oder DB-Ressourcen zu verbrauchen.' => [
            'de' => 'Verwaltet die zentrale IP-Sperrliste. Blockierte Angreifer werden sofort mit HTTP 403 abgewiesen, ohne CPU- oder DB-Ressourcen zu verbrauchen.',
            'en' => 'Manages the central IP ban list. Blocked attackers are instantly rejected with HTTP 403 without consuming CPU or DB resources.'
        ],
        'Schritt 3: Malware-Schutz & RASP-Isolation (Prometheus & Morpheus)' => [
            'de' => 'Schritt 3: Malware-Schutz & RASP-Isolation (Prometheus & Morpheus)',
            'en' => 'Step 3: Malware Protection & RASP Isolation (Prometheus & Morpheus)'
        ],
        'Schütze dein Dateisystem und isoliere ausgeführten PHP-Code durch moderne Runtime Application Self-Protection (RASP).' => [
            'de' => 'Schütze dein Dateisystem und isoliere ausgeführten PHP-Code durch moderne Runtime Application Self-Protection (RASP).',
            'en' => 'Protect your filesystem and isolate executed PHP code through modern Runtime Application Self-Protection (RASP).'
        ],
        'Prometheus Malware & Signature Engine' => ['de' => 'Prometheus Malware & Signatur Engine', 'en' => 'Prometheus Malware & Signature Engine'],
        'Überwacht PHP-Dateien kontinuierlich auf verdächtigen Code (Webshells, c99, r57, eval(base64), unautorisierte File-Dropper) und verhindert deren Ausführung.' => [
            'de' => 'Überwacht PHP-Dateien kontinuierlich auf verdächtigen Code (Webshells, c99, r57, eval(base64), unautorisierte File-Dropper) und verhindert deren Ausführung.',
            'en' => 'Continuously monitors PHP files for suspicious code (webshells, c99, r57, eval(base64), unauthorized droppers) and prevents execution.'
        ],
        'Morpheus Sandbox & Call-Stack Isolation' => ['de' => 'Morpheus Sandbox & Call-Stack Isolation', 'en' => 'Morpheus Sandbox & Call-Stack Isolation'],
        'Verfolgt Plugin-Aufrufe zur Laufzeit. Verhindert SSRF-Netzwerkangriffe, blockiert direkte SQL-Manipulationen an Tabellen wie wp_users und wehrt Option-Hijacking ab.' => [
            'de' => 'Verfolgt Plugin-Aufrufe zur Laufzeit. Verhindert SSRF-Netzwerkangriffe, blockiert direkte SQL-Manipulationen an Tabellen wie wp_users und wehrt Option-Hijacking ab.',
            'en' => 'Tracks plugin executions at runtime. Prevents SSRF network attacks, blocks direct SQL manipulations on wp_users, and counters option hijacking.'
        ],
        'Strikte Durchsetzung (Enforcement Mode) – Standard: Audit/Lernmodus' => [
            'de' => 'Strikte Durchsetzung (Enforcement Mode) – Standard: Audit/Lernmodus',
            'en' => 'Strict Enforcement Mode – Default: Audit/Learning Mode'
        ],
        'Schritt 4: Cyber-Täuschung & Honigtöpfe (Nemesis & Ghost Trap)' => [
            'de' => 'Schritt 4: Cyber-Täuschung & Honigtöpfe (Nemesis & Ghost Trap)',
            'en' => 'Step 4: Cyber Deception & Honeypots (Nemesis & Ghost Trap)'
        ],
        'Locke automatisierte Hacker-Bots gezielt in virtuelle Fallen und enttarne Scanner frühzeitig.' => [
            'de' => 'Locke automatisierte Hacker-Bots gezielt in virtuelle Fallen und enttarne Scanner frühzeitig.',
            'en' => 'Lure automated hacker bots into virtual traps and expose scanners early.'
        ],
        'Nemesis Deception Grid'     => ['de' => 'Nemesis Täuschungs-Gitter', 'en' => 'Nemesis Deception Grid'],
        'Injiziert unsichtbare Fake-Login-Felder und fingierte Fehlermeldungen. Bots, die diese Felder ausfüllen, enttarnen sich sofort als Angreifer.' => [
            'de' => 'Injiziert unsichtbare Fake-Login-Felder und fingierte Fehlermeldungen. Bots, die diese Felder ausfüllen, enttarnen sich sofort als Angreifer.',
            'en' => 'Injects invisible fake login fields and fabricated error responses. Bots filling these fields expose themselves instantly as attackers.'
        ],
        'Aktive Gegenmaßnahme: Täter-IP sofort dauerhaft in Cerberus sperren' => [
            'de' => 'Aktive Gegenmaßnahme: Täter-IP sofort dauerhaft in Cerberus sperren',
            'en' => 'Active Countermeasure: Instantly ban offender IP permanently in Cerberus'
        ],
        'Bounded Response: Defensive Täuschung ohne Worker-Blocking' => [
            'de' => 'Bounded Response: Defensive Täuschung ohne Worker-Blocking',
            'en' => 'Bounded Response: Defensive deception without worker retention'
        ],
        'Ghost Trap Honeypot'        => ['de' => 'Ghost Trap Honigtopf', 'en' => 'Ghost Trap Honeypot'],
        'Erzeugt dynamische Köder-Dateien (.bak, .sql, config-dumps), die nur bösartige Scanner ansteuern. Bei Zugriff wird die IP sofort isoliert.' => [
            'de' => 'Erzeugt dynamische Köder-Dateien (.bak, .sql, config-dumps), die nur bösartige Scanner ansteuern. Bei Zugriff wird die IP sofort isoliert.',
            'en' => 'Generates dynamic decoy files (.bak, .sql, config dumps) targeted solely by malicious scanners. IP is immediately isolated upon access.'
        ],
        'Schritt 5: Systemhärtung & Stealth (Titan & Hades)' => [
            'de' => 'Schritt 5: Systemhärtung & Stealth (Titan & Hades)',
            'en' => 'Step 5: System Hardening & Stealth (Titan & Hades)'
        ],
        'Schließe Standard-Sicherheitslücken in WordPress und verstecke den administrativen Login-Pfad.' => [
            'de' => 'Schließe Standard-Sicherheitslücken in WordPress und verstecke den administrativen Login-Pfad.',
            'en' => 'Close standard WordPress vulnerabilities and conceal the administrative login path.'
        ],
        'Titan Kernel Hardening Shield' => ['de' => 'Titan Kernel-Härtungsschild', 'en' => 'Titan Kernel Hardening Shield'],
        'XML-RPC sperren (DDoS-Schutz)' => ['de' => 'XML-RPC sperren (DDoS-Schutz)', 'en' => 'Block XML-RPC (DDoS Protection)'],
        'REST-API User Enumeration blockieren' => ['de' => 'REST-API User Enumeration blockieren', 'en' => 'Block REST API User Enumeration'],
        'WP-Versions-Header entfernen' => ['de' => 'WP-Versions-Header entfernen', 'en' => 'Strip WP Version Headers'],
        'RSS/Atom-Feeds deaktivieren' => ['de' => 'RSS/Atom-Feeds deaktivieren', 'en' => 'Disable RSS/Atom Feeds'],
        'Hades Login-Verschleierung (Stealth URL)' => ['de' => 'Hades Login-Verschleierung (Stealth URL)', 'en' => 'Hades Login Concealment (Stealth URL)'],
        'Sperrt den regulären /wp-admin und /wp-login.php Zugriff. Der Login wird nur freigegeben, wenn ein geheimer URL-Parameter mitgegeben wird.' => [
            'de' => 'Sperrt den regulären /wp-admin und /wp-login.php Zugriff. Der Login wird nur freigegeben, wenn ein geheimer URL-Parameter mitgegeben wird.',
            'en' => 'Blocks regular /wp-admin and /wp-login.php access. Login is only granted when a secret URL parameter is provided.'
        ],
        'Hades URL Schlüssel (Param)' => ['de' => 'Hades URL Schlüssel (Param)', 'en' => 'Hades URL Key (Param)'],
        'Hades URL Passwort (Secret)' => ['de' => 'Hades URL Passwort (Secret)', 'en' => 'Hades URL Password (Secret)'],
        'Schritt 6: Automatisierung, Vault & AI-Oracle (Chronos & Styx)' => [
            'de' => 'Schritt 6: Automatisierung, Vault & AI-Oracle (Chronos & Styx)',
            'en' => 'Step 6: Automation, Vault & AI Oracle (Chronos & Styx)'
        ],
        'Aktiviere autonome Hintergrundprüfungen und optionale KI-Angriffsklassifikation.' => [
            'de' => 'Aktiviere autonome Hintergrundprüfungen und optionale KI-Angriffsklassifikation.',
            'en' => 'Enable autonomous background scans and optional AI attack classification.'
        ],
        'Chronos Autonomer Scanner'  => ['de' => 'Chronos Autonomer Scanner', 'en' => 'Chronos Autonomous Scanner'],
        'Führt zeitgesteuerte Hintergrund-Audits durch und benachrichtigt bei Integritätsabweichungen.' => [
            'de' => 'Führt zeitgesteuerte Hintergrund-Audits durch und benachrichtigt bei Integritätsabweichungen.',
            'en' => 'Runs scheduled background audits and notifies on integrity anomalies.'
        ],
        'Styx Telemetrie-Sperre'     => ['de' => 'Styx Telemetrie-Sperre', 'en' => 'Styx Telemetry Guard'],
        'Blockiert ungefragte externe Telemetriedaten und schützt System-Invariants.' => [
            'de' => 'Blockiert ungefragte externe Telemetriedaten und schützt System-Invariants.',
            'en' => 'Blocks unsolicited outbound telemetry data and protects system invariants.'
        ],
        'Groq AI Integration (Optional)' => ['de' => 'Groq KI-Integration (Optional)', 'en' => 'Groq AI Integration (Optional)'],
        'Verbinde das Aegis Oracle mit der Groq API, um unbekannte Zero-Day-Muster durch LLMs in Echtzeit klassifizieren zu lassen.' => [
            'de' => 'Verbinde das Aegis Oracle mit der Groq API, um unbekannte Zero-Day-Muster durch LLMs in Echtzeit klassifizieren zu lassen.',
            'en' => 'Connect the Aegis Oracle to the Groq API to classify unknown zero-day patterns in real-time via LLMs.'
        ],
        'Groq API Key (Wird hardware-verschlüsselt im Vault gespeichert)' => [
            'de' => 'Groq API Key (Wird hardware-verschlüsselt im Vault gespeichert)',
            'en' => 'Groq API Key (Hardware-encrypted in vault)'
        ],
        'Schritt 7: Scharfschaltung & Zusammenfassung' => [
            'de' => 'Schritt 7: Scharfschaltung & Zusammenfassung',
            'en' => 'Step 7: Final Ignition & Summary'
        ],
        'Überprüfe die Übersicht aller konfigurierten Schutzmodule. Mit Klick auf den Button unten werden alle Firewall-Filter, RASP-Schilde und Sicherheitsregeln sofort aktiviert.' => [
            'de' => 'Überprüfe die Übersicht aller konfigurierten Schutzmodule. Mit Klick auf den Button unten werden alle Firewall-Filter, RASP-Schilde und Sicherheitsregeln sofort aktiviert.',
            'en' => 'Review the overview of all configured defense modules. Clicking the button below immediately activates all firewall filters, RASP shields, and security rules.'
        ],
        'Aktivierungs-Status der Schutzschichten:' => ['de' => 'Aktivierungs-Status der Schutzschichten:', 'en' => 'Protection Layers Activation Status:'],
        'Zukünftiger Administrator-Zugang:' => ['de' => 'Zukünftiger Administrator-Zugang:', 'en' => 'Future Administrator Access:'],
        'Geheime Hades Login-URL (Unbedingt speichern!):' => ['de' => 'Geheime Hades Login-URL (Unbedingt speichern!):', 'en' => 'Secret Hades Login URL (Save immediately!):'],
        'Standard-Anmeldewege bleiben aktiv (keine Login-Verschleierung gewählt).' => [
            'de' => 'Standard-Anmeldewege bleiben aktiv (keine Login-Verschleierung gewählt).',
            'en' => 'Standard login paths remain active (no login concealment selected).'
        ],
        'Klicke jetzt auf "Sicherheitsmodule aktivieren", um die Konfiguration dauerhaft zu speichern.' => [
            'de' => 'Klicke jetzt auf "Sicherheitsmodule aktivieren", um die Konfiguration dauerhaft zu speichern.',
            'en' => 'Click "Activate Security Modules" now to permanently save the configuration.'
        ],
        'Alle konfigurierten Sicherheitsmodule (WAF, RASP, Malware-Scanner, Honeypots & Härtungs-Schilde) wurden in den WordPress-Kernel kompiliert und sind ab sofort aktiv.' => [
            'de' => 'Alle konfigurierten Sicherheitsmodule (WAF, RASP, Malware-Scanner, Honeypots & Härtungs-Schilde) wurden in den WordPress-Kernel kompiliert und sind ab sofort aktiv.',
            'en' => 'All configured security modules (WAF, RASP, malware scanner, honeypots & hardening shields) are compiled into the WordPress kernel and active immediately.'
        ],
        'Deine Administrator-Zugangsdaten (Wichtig!):' => ['de' => 'Deine Administrator-Zugangsdaten (Wichtig!):', 'en' => 'Your Administrator Credentials (Important!):'],
        'Geheime Hades Admin-URL:'   => ['de' => 'Geheime Hades Admin-URL:', 'en' => 'Secret Hades Admin URL:'],
        'Zeus Login-Pfad:'           => ['de' => 'Zeus Login-Pfad:', 'en' => 'Zeus Login Path:'],
        'Standard-Zugangswege verbleiben unverändert (keine Login-Verschleierung aktiv).' => [
            'de' => 'Standard-Zugangswege verbleiben unverändert (keine Login-Verschleierung aktiv).',
            'en' => 'Standard access routes remain unchanged (no login cloaking active).'
        ],

        // =========================================================================
        // 12. PROMETHEUS & COGNITIVE AI
        // =========================================================================
        'Cognitive Threat Assessment' => ['de' => 'Kognitive Bedrohungs-Analyse', 'en' => 'Cognitive Threat Assessment'],
        'Aktiviert die verhaltensbasierte Analyse in Echtzeit. Das System berechnet einen dynamischen Threat-Score für jede IP und jedes Subnetz. Übersteigt der Score den Horizont, wird ein präemptiver Strike ausgeführt.' => [
            'de' => 'Aktiviert die verhaltensbasierte Analyse in Echtzeit. Das System berechnet einen dynamischen Threat-Score für jede IP und jedes Subnetz. Übersteigt der Score den Horizont, wird ein präemptiver Strike ausgeführt.',
            'en' => 'Engages real-time behavioral analysis. The engine computes a dynamic threat score for every IP and subnet. Exceeding the horizon threshold triggers a preemptive defensive strike.'
        ],
        'Feinjustierung der neuronalen Bewertungsparameter. Manipulation dieser Werte verändert die Aggressivität des Systems.' => [
            'de' => 'Feinjustierung der neuronalen Bewertungsparameter. Manipulation dieser Werte verändert die Aggressivität des Systems.',
            'en' => 'Fine-tuning of neural evaluation parameters. Adjusting these values modifies system defense aggressiveness.'
        ],
        'Predictive Strikes'         => ['de' => 'Präventive Abwehren', 'en' => 'Predictive Strikes'],
        'Behavioral Anomalies'       => ['de' => 'Verhaltens-Anomalien', 'en' => 'Behavioral Anomalies'],
        'Global Threat Entropy (24h)'=> ['de' => 'Globale Bedrohungs-Entropie (24h)', 'en' => 'Global Threat Entropy (24h)'],
        'Cognitive Tuning Matrix'    => ['de' => 'Kognitive Feinabstimmungs-Matrix', 'en' => 'Cognitive Tuning Matrix'],
        'Engine Thresholds'          => ['de' => 'Engine Schwellenwerte', 'en' => 'Engine Thresholds'],
        'Decay Algorithm'            => ['de' => 'Abkling-Algorithmus (Decay)', 'en' => 'Decay Algorithm'],
        'Tactical Penalty Weights'   => ['de' => 'Taktische Strafgewichte', 'en' => 'Tactical Penalty Weights'],
        'IP Event Horizon Score'     => ['de' => 'IP Event-Horizon Score', 'en' => 'IP Event Horizon Score'],
        'Subnet Event Horizon Score' => ['de' => 'Subnetz Event-Horizon Score', 'en' => 'Subnet Event Horizon Score'],
        'Subnet Cooldown (Sekunden)' => ['de' => 'Subnetz Cooldown (Sekunden)', 'en' => 'Subnet Cooldown (Seconds)'],
        'Score Decay Rate (pro Sekunde)' => ['de' => 'Score Abklingrate (pro Sekunde)', 'en' => 'Score Decay Rate (per Second)'],
        'Memory Cooldown Window (Sekunden)' => ['de' => 'RAM Cooldown-Fenster (Sekunden)', 'en' => 'Memory Cooldown Window (Seconds)'],

        // =========================================================================
        // 17. ORACLE SYSTEM AUDIT & CHECKS
        // =========================================================================
        'CRITICAL: wp-config.php ist beschreibbar (RCE Vektor)!' => ['de' => 'KRITISCH: wp-config.php ist beschreibbar (RCE Vektor)!', 'en' => 'CRITICAL: wp-config.php is writable (RCE vector)!'],
        'wp-config.php ist read-only (Sicher).' => ['de' => 'wp-config.php ist schreibgeschützt (Sicher).', 'en' => 'wp-config.php is read-only (Secure).'],
        'Kein öffentliches debug.log gefunden.' => ['de' => 'Kein öffentliches debug.log gefunden.', 'en' => 'No public debug.log exposed.'],
        'CRITICAL: debug.log ist öffentlich zugänglich (Info Leak).' => ['de' => 'KRITISCH: debug.log ist öffentlich zugänglich (Info Leak).', 'en' => 'CRITICAL: debug.log is publicly accessible (Info leak).'],
        'Integrierter Datei-Editor ist deaktiviert.' => ['de' => 'Integrierter Datei-Editor ist deaktiviert.', 'en' => 'Integrated file editor is disabled.'],
        'Editor aktiv (Erhebliches RCE Risiko).' => ['de' => 'Editor aktiv (Erhebliches RCE Risiko).', 'en' => 'File editor active (Severe RCE risk).'],
        'Alle kryptographischen Keys sind individuell konfiguriert.' => ['de' => 'Alle kryptographischen Keys sind individuell konfiguriert.', 'en' => 'All cryptographic keys are uniquely configured.'],
        'CRITICAL: Der Key "%s" fehlt komplett.' => ['de' => 'KRITISCH: Der Key "%s" fehlt komplett.', 'en' => 'CRITICAL: The key "%s" is missing completely.'],
        'CRITICAL: Der Key "%s" nutzt Standardwerte oder ist zu kurz.' => ['de' => 'KRITISCH: Der Key "%s" nutzt Standardwerte oder ist zu kurz.', 'en' => 'CRITICAL: The key "%s" uses default values or is too short.'],
        'Custom Prefix aktiv (%s).' => ['de' => 'Individueller Tabellen-Präfix aktiv (%s).', 'en' => 'Custom table prefix active (%s).'],
        'Standard "wp_" Prefix gefunden (Brute-Force Risk).' => ['de' => 'Standard "wp_" Präfix gefunden (Brute-Force Risiko).', 'en' => 'Standard "wp_" prefix found (Brute-force risk).'],
        'Standard-User "admin" existiert nicht.' => ['de' => 'Standard-User "admin" existiert nicht.', 'en' => 'Default user "admin" does not exist.'],
        'User "admin" existiert (Primäres Brute-Force Ziel).' => ['de' => 'User "admin" existiert (Primäres Brute-Force Ziel).', 'en' => 'User "admin" exists (Primary brute-force target).'],
        'User ID 1 existiert (Enumeration Risk).' => ['de' => 'User ID 1 existiert (Enumeration Risiko).', 'en' => 'User ID 1 exists (Enumeration risk).'],
        'User ID 1 ist nicht belegt (Hardened).' => ['de' => 'User ID 1 ist nicht belegt (Gehärtet).', 'en' => 'User ID 1 is unassigned (Hardened).'],
        'User ID 1 Ghosting ist über Titan Hardening (Anti-Enumeration) aktiv und geschützt.' => ['de' => 'User ID 1 Ghosting ist über Titan Hardening (Anti-Enumeration) aktiv und geschützt.', 'en' => 'User ID 1 Ghosting is active and protected via Titan Hardening (Anti-Enumeration).'],
        'SSL/TLS Verschlüsselung aktiv.' => ['de' => 'SSL/TLS Verschlüsselung aktiv.', 'en' => 'SSL/TLS encryption active.'],
        'Verbindung unverschlüsselt (HTTP) - Man-in-the-Middle Gefahr.' => ['de' => 'Verbindung unverschlüsselt (HTTP) - Man-in-the-Middle Gefahr.', 'en' => 'Unencrypted connection (HTTP) - Man-in-the-middle risk.'],
        'Server gibt via Header oder Environment exakte Versionen preis (Targeting Risk).' => ['de' => 'Server gibt via Header oder Environment exakte Versionen preis (Targeting Risk).', 'en' => 'Server exposes exact versions via headers/environment (Targeting risk).'],
        'Server-Signatur und Versionen sind unterdrückt.' => ['de' => 'Server-Signatur und Versionen sind unterdrückt.', 'en' => 'Server signature and version headers are suppressed.'],
        'CRITICAL: Display Errors aktiv (Full Path Disclosure möglich).' => ['de' => 'KRITISCH: Display Errors aktiv (Full Path Disclosure möglich).', 'en' => 'CRITICAL: Display Errors active (Full path disclosure risk).'],
        'Fehlermeldungen sind im Frontend verborgen.' => ['de' => 'Fehlermeldungen sind im Frontend verborgen.', 'en' => 'Error messages are concealed in frontend.'],
        'Basisschutz (index.php) in Uploads/Content aktiv.' => ['de' => 'Basisschutz (index.php) in Uploads/Content aktiv.', 'en' => 'Basic guard files (index.php) active in Uploads/Content.'],
        'Mögliches Directory Listing (Schutzdateien fehlen).' => ['de' => 'Mögliches Directory Listing (Schutzdateien fehlen).', 'en' => 'Possible directory listing (Protection files missing).'],
        'Authorization Headers werden korrekt propagiert.' => ['de' => 'Authorization Headers werden korrekt propagiert.', 'en' => 'Authorization headers are correctly propagated.'],
        'Authorization Headers fehlen (Mögliche API-Blockade durch Server-Config).' => ['de' => 'Authorization Headers fehlen (Mögliche API-Blockade durch Server-Config).', 'en' => 'Authorization headers missing (Possible API block by host config).'],
        'Config Protection' => ['de' => 'Konfigurationsschutz', 'en' => 'Config Protection'],
        'Debug Log Secrecy' => ['de' => 'Debug-Log Geheimhaltung', 'en' => 'Debug Log Secrecy'],
        'File Editor Lockdown' => ['de' => 'Datei-Editor Sperre', 'en' => 'File Editor Lockdown'],
        'Security Keys (Salts)' => ['de' => 'Sicherheitsschlüssel (Salts)', 'en' => 'Security Keys (Salts)'],
        'DB Prefix Hardening' => ['de' => 'DB-Präfix Härtung', 'en' => 'DB Prefix Hardening'],
        'Default Admin Blacklist' => ['de' => 'Standard Admin-Sperre', 'en' => 'Default Admin Blacklist'],
        'User ID 1 Ghosting' => ['de' => 'User ID 1 Ghosting', 'en' => 'User ID 1 Ghosting'],
        'Transport Layer Security' => ['de' => 'Transportschicht-Sicherheit (TLS)', 'en' => 'Transport Layer Security'],
        'Server Signature Extraction' => ['de' => 'Server-Signatur Extraktion', 'en' => 'Server Signature Extraction'],
        'PHP Display Errors' => ['de' => 'PHP Display Errors', 'en' => 'PHP Display Errors'],
        'Directory Browsing' => ['de' => 'Verzeichnis-Browsing', 'en' => 'Directory Browsing'],
        'Auth Header Propagation' => ['de' => 'Auth-Header Weiterleitung', 'en' => 'Auth Header Propagation'],
        'TOTAL CHECKS' => ['de' => 'GESAMT-PRÜFUNGEN', 'en' => 'TOTAL CHECKS'],
        'PASSED VECTORS' => ['de' => 'BESTANDENE VEKTOREN', 'en' => 'PASSED VECTORS'],
        'ANOMALIES FOUND' => ['de' => 'GEFUNDENE ANOMALIEN', 'en' => 'ANOMALIES FOUND'],
        'SECURITY CHECK DEFINITION' => ['de' => 'SICHERHEITSPRÜFUNG', 'en' => 'SECURITY CHECK DEFINITION'],
        'ANALYSIS RESULT (PROPHECY)' => ['de' => 'ANALYSE-ERGEBNIS (PROPHEZEIUNG)', 'en' => 'ANALYSIS RESULT (PROPHECY)'],
        'ORACLE SYSTEM AUDIT' => ['de' => 'ORAKEL SYSTEM AUDIT', 'en' => 'ORACLE SYSTEM AUDIT'],
        'Prophecy Engine:' => ['de' => 'Prophezeiungs-Engine:', 'en' => 'Prophecy Engine:'],
        'ALL VECTORS NOMINAL' => ['de' => 'ALLE VEKTOREN NOMINAL', 'en' => 'ALL VECTORS NOMINAL'],
        'ANOMALIES DETECTED' => ['de' => 'ANOMALIEN ERKANNT', 'en' => 'ANOMALIES DETECTED'],

        // =========================================================================
        // 18. TRINITY GRID & TOPOLOGY
        // =========================================================================
        'TRINITY GRID V7.6.0' => ['de' => 'TRINITY GRID V7.6.0', 'en' => 'TRINITY GRID V7.6.0'],
        'Coordinated Real-Time Defense Interlock Matrix & Visual Topology' => ['de' => 'Koordinierte Echtzeit-Abwehrkopplungsmatrix & Visuelle Topologie', 'en' => 'Coordinated Real-Time Defense Interlock Matrix & Visual Topology'],
        'TRINITY: INTERLOCKED' => ['de' => 'TRINITY: GEKOPPELT', 'en' => 'TRINITY: INTERLOCKED'],
        'TRINITY: DEGRADED' => ['de' => 'TRINITY: EINGESCHRÄNKT', 'en' => 'TRINITY: DEGRADED'],
        'Scoring-Kopplung' => ['de' => 'Scoring-Kopplung', 'en' => 'Scoring Coupling'],
        'SCORING-KOPPLUNG' => ['de' => 'SCORING-KOPPLUNG', 'en' => 'SCORING COUPLING'],
        'Strafpunkte bei WAF-Strike' => ['de' => 'Strafpunkte bei WAF-Strike', 'en' => 'Penalty points on WAF strike'],
        'Klebeschwelle' => ['de' => 'Klebeschwelle', 'en' => 'Tarpit Stickiness Threshold'],
        'KLEBESCHWELLE' => ['de' => 'KLEBESCHWELLE', 'en' => 'TARPIT STICKINESS THRESHOLD'],
        'Score für Micro-Tarpit (5s)' => ['de' => 'Score für Micro-Tarpit (5s)', 'en' => 'Score for Micro-Tarpit (5s)'],
        'Tarpit Limit' => ['de' => 'Tarpit-Limit', 'en' => 'Tarpit Limit'],
        'TARPIT LIMIT' => ['de' => 'TARPIT-LIMIT', 'en' => 'TARPIT LIMIT'],
        'Decay Connection Duration' => ['de' => 'Verbindungsverzögerungs-Dauer', 'en' => 'Decay Connection Duration'],
        'Verdächtige Aktivitäten' => ['de' => 'Verdächtige Aktivitäten', 'en' => 'Suspicious Activities'],
        'VERDÄCHTIGE AKTIVITÄTEN' => ['de' => 'VERDÄCHTIGE AKTIVITÄTEN', 'en' => 'SUSPICIOUS ACTIVITIES'],
        'Erfasste Anomalien & Warnungen' => ['de' => 'Erfasste Anomalien & Warnungen', 'en' => 'Recorded Anomalies & Warnings'],
        'Trinity Lockouts' => ['de' => 'Trinity Sperren', 'en' => 'Trinity Lockouts'],
        'TRINITY LOCKOUTS' => ['de' => 'TRINITY SPERREN', 'en' => 'TRINITY LOCKOUTS'],
        'Aktive interlock IP-Sperren' => ['de' => 'Aktive Interlock IP-Sperren', 'en' => 'Active Interlock IP Bans'],
        'RAM Cache Coverage' => ['de' => 'RAM-Cache Abdeckung', 'en' => 'RAM Cache Coverage'],
        'RAM CACHE COVERAGE' => ['de' => 'RAM-CACHE ABDECKUNG', 'en' => 'RAM CACHE COVERAGE'],
        'Perimeter Shield RAM check' => ['de' => 'Perimeter Shield RAM Prüfung', 'en' => 'Perimeter Shield RAM check'],
        'TRINITY REAL-TIME INTERLOCK TOPOLOGY' => ['de' => 'TRINITY ECHTZEIT-VERZAHNUNGS-TOPOLOGIE', 'en' => 'TRINITY REAL-TIME INTERLOCK TOPOLOGY'],
        'MATRIX ACTIVE (0-LATENCY)' => ['de' => 'MATRIX AKTIV (0-LATENZ)', 'en' => 'MATRIX ACTIVE (0-LATENCY)'],
        'PARTIAL INTERLOCK' => ['de' => 'TEILWEISE VERZAHNUNG', 'en' => 'PARTIAL INTERLOCK'],
        'INTERLOCK THREAT VECTOR DISTRIBUTION' => ['de' => 'INTERLOCK BEDROHUNGSVEKTOR-VERTEILUNG', 'en' => 'INTERLOCK THREAT VECTOR DISTRIBUTION'],
        'LIVE TRINITY INTERCEPT STREAM' => ['de' => 'LIVE TRINITY ABFANG-STREAM', 'en' => 'LIVE TRINITY INTERCEPT STREAM'],
        'REALTIME AUDIT FEED' => ['de' => 'ECHTZEIT-AUDIT FEED', 'en' => 'REALTIME AUDIT FEED'],
        'Zeitpunkt' => ['de' => 'Zeitpunkt', 'en' => 'Timestamp'],
        'Ziel IP' => ['de' => 'Ziel-IP', 'en' => 'Target IP'],
        'Interlock Modul' => ['de' => 'Interlock Modul', 'en' => 'Interlock Module'],
        'Vektor / VGT Grund' => ['de' => 'Vektor / VGT Grund', 'en' => 'Vector / VGT Reason'],
        'Aktion' => ['de' => 'Aktion', 'en' => 'Action'],
        'Keine aktiven Trinity Lockouts verzeichnet. System befindet sich im Ruhezustand (0 Infiltrationen).' => ['de' => 'Keine aktiven Trinity Lockouts verzeichnet. System befindet sich im Ruhezustand (0 Infiltrationen).', 'en' => 'No active Trinity lockouts recorded. System is resting (0 infiltrations).'],
        'Trinity Matrix Tuning & Configurations' => ['de' => 'Trinity Matrix Tuning & Konfigurationen', 'en' => 'Trinity Matrix Tuning & Configurations'],
        'omega Interlock-Verbindung aktivieren' => ['de' => 'OMEGA Interlock-Verbindung aktivieren', 'en' => 'Enable OMEGA Interlock Connection'],
        'Schaltet die intelligente Vernetzung zwischen WAF (Aegis), Verhaltenskontrolle (Prometheus), Klebefalle (Nemesis) und Perimeter-Sperre (Cerberus) scharf.' => ['de' => 'Schaltet die intelligente Vernetzung zwischen WAF (Aegis), Verhaltenskontrolle (Prometheus), Klebefalle (Nemesis) und Perimeter-Sperre (Cerberus) scharf.', 'en' => 'Engages smart interlock coupling between WAF (Aegis), behavioral telemetry (Prometheus), tarpit trap (Nemesis), and perimeter firewall (Cerberus).'],
        'AEGIS WAF-Strike Penalty' => ['de' => 'AEGIS WAF-Strike Strafpunkte', 'en' => 'AEGIS WAF-Strike Penalty'],
        'Zusätzlicher Anstieg des Bedrohungsscores in Prometheus, wenn die WAF einen Request blockiert.' => ['de' => 'Zusätzlicher Anstieg des Bedrohungsscores in Prometheus, wenn die WAF einen Request blockiert.', 'en' => 'Additional threat score penalty in Prometheus when WAF blocks a malicious request.'],
        'Micro-Tarpit Aktivierungsschwelle' => ['de' => 'Micro-Tarpit Aktivierungsschwelle', 'en' => 'Micro-Tarpit Activation Threshold'],
        'Verhaltens-Score ab dem die IP vor dem endgültigen Bann temporär verlangsamt wird (5 Sekunden Verzögerung).' => ['de' => 'Verhaltens-Score ab dem die IP vor dem endgültigen Bann temporär verlangsamt wird (5 Sekunden Verzögerung).', 'en' => 'Behavioral threat score at which the IP is temporarily slowed down before total lockout (5s delay).'],
        'Nemesis Tarpit Loop Limit' => ['de' => 'Nemesis Tarpit Loop Limit', 'en' => 'Nemesis Tarpit Loop Limit'],
        'Maximale Durchläufe der Klebefalle (1 Durchlauf = ~30s Delay). Reduziert die Belastung durch persistente Angreifer.' => ['de' => 'Maximale Durchläufe der Klebefalle (1 Durchlauf = ~30s Delay). Reduziert die Belastung durch persistente Angreifer.', 'en' => 'Maximum cycles for the tarpit trap (1 cycle = ~30s delay). Defuses server load against persistent scrapers.'],

        // =========================================================================
        // 19. NEMESIS MODALS & PROTOCOLS
        // =========================================================================
        'KINETIC STRIKE AUTHORIZATION REQUIRED' => ['de' => 'KINETISCHER SCHLAG: AUTORISIERUNG ERFORDERLICH', 'en' => 'KINETIC STRIKE AUTHORIZATION REQUIRED'],
        'Sie sind dabei, das <strong>Active Strike (Hack Back) Protokoll</strong> zu initialisieren.' => ['de' => 'Sie sind dabei, das <strong>Active Strike (Hack Back) Protokoll</strong> zu initialisieren.', 'en' => 'You are about to initialize the <strong>Active Strike (Hack Back) Protocol</strong>.'],
        'Diese Aktion verwandelt passive Verteidigung in aktive System-Sabotage (GZIP-Bombs, Memory Exhaustion) gegen die Infrastruktur des Angreifers.' => ['de' => 'Diese Aktion verwandelt passive Verteidigung in aktive System-Sabotage (GZIP-Bombs, Memory Exhaustion) gegen die Infrastruktur des Angreifers.', 'en' => 'This action converts passive defense into active counter-sabotage (GZIP bombs, memory exhaustion) against the attacker infrastructure.'],
        'Gemäß <strong>§ 303a / § 303b StGB (Computersabotage)</strong> ist der unautorisierte Einsatz dieser Waffen im deutschen Rechtsraum strikt illegal. Bestätigen Sie, dass Sie dieses System in einer autorisierten Umgebung betreiben und die volle rechtliche Haftung übernehmen.' => ['de' => 'Gemäß <strong>§ 303a / § 303b StGB (Computersabotage)</strong> ist der unautorisierte Einsatz dieser Waffen im deutschen Rechtsraum strikt illegal. Bestätigen Sie, dass Sie dieses System in einer autorisierten Umgebung betreiben und die volle rechtliche Haftung übernehmen.', 'en' => 'Pursuant to <strong>§ 303a / § 303b StGB (Computer Sabotage)</strong>, the unauthorized deployment of offensive countermeasures is strictly restricted. Confirm that you operate this system in an authorized environment and accept full legal responsibility.'],
        'ABORT SEQUENCE' => ['de' => 'SEQUENZ ABBRECHEN', 'en' => 'ABORT SEQUENCE'],
        'AUTHORIZE STRIKE' => ['de' => 'SCHLAG AUTORISIEREN', 'en' => 'AUTHORIZE STRIKE'],
        'EXPERIMENTAL PROTOCOLS' => ['de' => 'EXPERIMENTELLE PROTOKOLLE', 'en' => 'EXPERIMENTAL PROTOCOLS'],
        'STRIKTE RECHTLICHE WARNUNG (DEUTSCHLAND)' => ['de' => 'STRIKTE RECHTLICHE WARNUNG (DEUTSCHLAND)', 'en' => 'STRICT LEGAL WARNING'],
        'Die Ausführung aktiver Denial-of-Service (DoS) oder Sabotage-Maßnahmen gegen fremde IT-Systeme (Hack-Back) ist in der Bundesrepublik Deutschland nach <strong>§ 303a StGB (Datenveränderung)</strong> und <strong>§ 303b StGB (Computersabotage)</strong> strafbar und kann mit Freiheitsstrafen geahndet werden.<br><br><strong>Nutzung ausschließlich in isolierten Sandbox-Umgebungen, im Rahmen autorisierter Penetration Tests oder auf eigene, vollumfängliche rechtliche Verantwortung. VGT übernimmt keinerlei Haftung für den Missbrauch dieser Protokolle.</strong>' => ['de' => 'Die Ausführung aktiver Denial-of-Service (DoS) oder Sabotage-Maßnahmen gegen fremde IT-Systeme (Hack-Back) ist in der Bundesrepublik Deutschland nach <strong>§ 303a StGB (Datenveränderung)</strong> und <strong>§ 303b StGB (Computersabotage)</strong> strafbar und kann mit Freiheitsstrafen geahndet werden.<br><br><strong>Nutzung ausschließlich in isolierten Sandbox-Umgebungen, im Rahmen autorisierter Penetration Tests oder auf eigene, vollumfängliche rechtliche Verantwortung. VGT übernimmt keinerlei Haftung für den Missbrauch dieser Protokolle.</strong>', 'en' => 'Executing active Denial-of-Service (DoS) or sabotage measures against third-party IT systems (hack-back) is criminalized under computer sabotage statutes.<br><br><strong>Deployment solely inside isolated sandboxes, authorized penetration tests, or under your own full legal liability. VGT assumes zero liability for misuse of these protocols.</strong>'],

        // =========================================================================
        // 20. KERNEL UPLINK
        // =========================================================================
        'AEGIS KERNEL UPLINK' => ['de' => 'AEGIS KERNEL UPLINK', 'en' => 'AEGIS KERNEL UPLINK'],
        'Uplink nicht initialisiert' => ['de' => 'Uplink nicht initialisiert', 'en' => 'Uplink Not Initialized'],
        'Der Server sendet aktuell keine Telemetrie-Daten. Führe die folgenden Schritte als root auf deinem Server aus. Der Pfad wurde bereits dynamisch angepasst.' => ['de' => 'Der Server sendet aktuell keine Telemetrie-Daten. Führe die folgenden Schritte als root auf deinem Server aus. Der Pfad wurde bereits dynamisch angepasst.', 'en' => 'The server is currently not transmitting telemetry data. Execute the following steps as root on your server. The path has already been configured dynamically.'],
        'Auditd installieren & konfigurieren' => ['de' => 'Auditd installieren & konfigurieren', 'en' => 'Install & Configure Auditd'],
        'Kopieren' => ['de' => 'Kopieren', 'en' => 'Copy'],
        'KOPIEREN' => ['de' => 'KOPIEREN', 'en' => 'COPY'],
        'Füge diese Regeln ans Ende der Datei /etc/audit/rules.d/audit.rules ein und starte den Service neu:' => ['de' => 'Füge diese Regeln ans Ende der Datei /etc/audit/rules.d/audit.rules ein und starte den Service neu:', 'en' => 'Append these rules to the end of /etc/audit/rules.d/audit.rules and restart the service:'],
        'Aegis Bridge Script anlegen' => ['de' => 'Aegis Bridge Script anlegen', 'en' => 'Create Aegis Bridge Script'],
        'Erstelle die Datei /root/visiongaia_aegis.sh und füge diesen Code ein:' => ['de' => 'Erstelle die Datei /root/visiongaia_aegis.sh und füge diesen Code ein:', 'en' => 'Create the file /root/visiongaia_aegis.sh and paste this code:'],
        'Ausführbar machen & Cronjob setzen' => ['de' => 'Ausführbar machen & Cronjob setzen', 'en' => 'Make Executable & Set Cronjob'],
        'AUDITD DAEMON' => ['de' => 'AUDITD DAEMON', 'en' => 'AUDITD DAEMON'],
        'ONLINE / SYNCED' => ['de' => 'ONLINE / SYNCHRONISIERT', 'en' => 'ONLINE / SYNCED'],
        'KERNEL INTEGRITY' => ['de' => 'KERNEL INTEGRITÄT', 'en' => 'KERNEL INTEGRITY'],
        'Echtzeit-Telemetrie aktiv. Keine Anomalien detektiert.' => ['de' => 'Echtzeit-Telemetrie aktiv. Keine Anomalien detektiert.', 'en' => 'Real-time telemetry active. No anomalies detected.'],
        'LAST SYNC: %s' => ['de' => 'LETZTER SYNC: %s', 'en' => 'LAST SYNC: %s'],
        'KERNEL BREACH DETECTED' => ['de' => 'KERNEL SICHERHEITSVERLETZUNG ERKANNT', 'en' => 'KERNEL BREACH DETECTED'],
        'Alarm muss per SSH-Root-Zugang durch Löschen der Datei aegis-signal.json zurückgesetzt werden.' => ['de' => 'Alarm muss per SSH-Root-Zugang durch Löschen der Datei aegis-signal.json zurückgesetzt werden.', 'en' => 'Alarm must be reset via root SSH access by removing the file aegis-signal.json.'],

        // =========================================================================
        // 21. STYX CONTROLLER & OUTBOUND EXFILTRATION
        // =========================================================================
        'STYX EXECUTIONER' => ['de' => 'STYX EXECUTIONER', 'en' => 'STYX EXECUTIONER'],
        'Outbound Exfiltration Shield & Shadow-Router' => ['de' => 'Ausgehender Exfiltrationsschutz & Shadow-Router', 'en' => 'Outbound Exfiltration Shield & Shadow-Router'],
        'Outbound Telemetry Control' => ['de' => 'Ausgehende Telemetrie-Kontrolle', 'en' => 'Outbound Telemetry Control'],
        'Blockiert unautorisierte ausgehende HTTP-Requests. Verhindert, dass gehackte Plugins Daten an externe C&C-Server exfiltrieren. Nutze den Audit Mode, um das System zunächst im Lernmodus laufen zu lassen.' => ['de' => 'Blockiert unautorisierte ausgehende HTTP-Requests. Verhindert, dass gehackte Plugins Daten an externe C&C-Server exfiltrieren. Nutze den Audit Mode, um das System zunächst im Lernmodus laufen zu lassen.', 'en' => 'Blocks unauthorized outbound HTTP requests. Prevents compromised plugins from exfiltrating data to external C&C servers. Use Audit Mode to let the system learn allowed destinations first.'],
        'WP CORE TELEMETRY:' => ['de' => 'WP CORE TELEMETRIE:', 'en' => 'WP CORE TELEMETRY:'],
        'Schalte diesen Switch ein, um native Verbindungen zur wp.org API zu kappen (Blockt Supply-Chain Leaks & Core-Updates).' => ['de' => 'Schalte diesen Switch ein, um native Verbindungen zur wp.org API zu kappen (Blockt Supply-Chain Leaks & Core-Updates).', 'en' => 'Enable this switch to sever native connections to the wp.org API (Blocks supply-chain leaks & automated core updates).'],
        'AUDIT MODE' => ['de' => 'AUDIT MODUS', 'en' => 'AUDIT MODE'],
        'BLOCK WP CORE' => ['de' => 'WP CORE BLOCKIEREN', 'en' => 'BLOCK WP CORE'],
        'Blocked Exfiltrations' => ['de' => 'Blockierte Exfiltrationen', 'en' => 'Blocked Exfiltrations'],
        'BLOCKED EXFILTRATIONS' => ['de' => 'BLOCKIERTE EXFILTRATIONEN', 'en' => 'BLOCKED EXFILTRATIONS'],
        'Authorized Calls' => ['de' => 'Autorisierte Anfragen', 'en' => 'Authorized Calls'],
        'AUTHORIZED CALLS' => ['de' => 'AUTORISIERTE ANFRAGEN', 'en' => 'AUTHORIZED CALLS'],
        'Active Internal Origins' => ['de' => 'Aktive interne Ursprünge', 'en' => 'Active Internal Origins'],
        'ACTIVE INTERNAL ORIGINS' => ['de' => 'AKTIVE INTERNE URSPRÜNGE', 'en' => 'ACTIVE INTERNAL ORIGINS'],
        'ZERO-TRUST WHITELIST (Allowed Destinations)' => ['de' => 'ZERO-TRUST POSITIVLISTE (Erlaubte Ziele)', 'en' => 'ZERO-TRUST WHITELIST (Allowed Destinations)'],
        'Trage hier die Domains ein, die kontaktiert werden dürfen (z.B. Lizenzen). Alles andere wird terminiert. Wildcards erlaubt (*.google.com).' => ['de' => 'Trage hier die Domains ein, die kontaktiert werden dürfen (z.B. Lizenzen). Alles andere wird terminiert. Wildcards erlaubt (*.google.com).', 'en' => 'Enter authorized destination domains (e.g. licensing). Everything else is terminated. Wildcards supported (*.google.com).'],
        'Hinweis: Core WordPress APIs (api.wordpress.org) werden nativ zugelassen, es sei denn der \'BLOCK WP CORE\' Switch ist aktiviert.' => ['de' => 'Hinweis: Core WordPress APIs (api.wordpress.org) werden nativ zugelassen, es sei denn der \'BLOCK WP CORE\' Switch ist aktiviert.', 'en' => 'Note: Core WordPress APIs (api.wordpress.org) are permitted by default unless the \'BLOCK WP CORE\' switch is enabled.'],
        '[SYSTEM] Styx Executioner initialized. Outbound matrix is clean.' => ['de' => '[SYSTEM] Styx Executioner initialisiert. Ausgehende Matrix ist sauber.', 'en' => '[SYSTEM] Styx Executioner initialized. Outbound matrix is clean.'],
        '[SYSTEM] Monitoring internal processes...' => ['de' => '[SYSTEM] Überwache interne Prozesse...', 'en' => '[SYSTEM] Monitoring internal processes...'],
        '[ERROR] Styx Executioner shutdown. Outbound traffic is completely unmonitored.' => ['de' => '[FEHLER] Styx Executioner heruntergefahren. Ausgehender Datenverkehr ist unüberwacht.', 'en' => '[ERROR] Styx Executioner shutdown. Outbound traffic is completely unmonitored.'],

        // =========================================================================
        // 22. CHRONOS AUTOPILOT
        // =========================================================================
        'CHRONOS' => ['de' => 'CHRONOS', 'en' => 'CHRONOS'],
        'AUTONOMOUS SCHEDULER' => ['de' => 'AUTONOMER ZEITPLANER', 'en' => 'AUTONOMOUS SCHEDULER'],
        'Orchestriert den OMEGA Scanner-Kernel im Hintergrund. Führt ressourcenschonende, zeitgesteuerte Deep-Scans der Dateisystem-Integrität durch und informiert dich über Modifikationen.' => ['de' => 'Orchestriert den OMEGA Scanner-Kernel im Hintergrund. Führt ressourcenschonende, zeitgesteuerte Deep-Scans der Dateisystem-Integrität durch und informiert dich über Modifikationen.', 'en' => 'Orchestrates the OMEGA scanner kernel in the background. Performs lightweight scheduled deep scans of filesystem integrity and notifies you of unexpected modifications.'],
        'Temporal Engine' => ['de' => 'Zeitgesteuerte Engine', 'en' => 'Temporal Engine'],
        'Auto-Scan aktivieren' => ['de' => 'Auto-Scan aktivieren', 'en' => 'Enable Auto-Scan'],
        'Aktiviert den Hintergrund-Daemon.' => ['de' => 'Aktiviert den Hintergrund-Daemon.', 'en' => 'Activates background daemon scheduler.'],
        'Scan Intervall' => ['de' => 'Scan-Intervall', 'en' => 'Scan Interval'],
        'Aggressiv (Alle 15 Minuten)' => ['de' => 'Aggressiv (Alle 15 Minuten)', 'en' => 'Aggressive (Every 15 minutes)'],
        'Hoch (Alle 30 Minuten)' => ['de' => 'Hoch (Alle 30 Minuten)', 'en' => 'High (Every 30 minutes)'],
        'Standard (Stündlich)' => ['de' => 'Standard (Stündlich)', 'en' => 'Standard (Hourly)'],
        'Ausbalanciert (Alle 12 Stunden)' => ['de' => 'Ausbalanciert (Alle 12 Stunden)', 'en' => 'Balanced (Every 12 hours)'],
        'Ökonomisch (1x Täglich)' => ['de' => 'Ökonomisch (1x Täglich)', 'en' => 'Economic (Once daily)'],
        'Legt fest, wie oft das gesamte Dateisystem (Core, Plugins, Themes) verifiziert wird.' => ['de' => 'Legt fest, wie oft das gesamte Dateisystem (Core, Plugins, Themes) verifiziert wird.', 'en' => 'Specifies how often the entire filesystem (Core, Plugins, Themes) is integrity-verified.'],
        'Next Scheduled Ignition' => ['de' => 'Nächster Geplanter Scan', 'en' => 'Next Scheduled Ignition'],
        'Offline / Not Scheduled' => ['de' => 'Offline / Nicht Geplant', 'en' => 'Offline / Not Scheduled'],
        'Alerting Matrix' => ['de' => 'Alarmierungs-Matrix', 'en' => 'Alerting Matrix'],
        'Empfänger E-Mail' => ['de' => 'Empfänger E-Mail', 'en' => 'Recipient Email'],
        'E-Mail Betreff' => ['de' => 'E-Mail Betreff', 'en' => 'Email Subject'],
        'E-Mail Template' => ['de' => 'E-Mail Vorlage', 'en' => 'Email Template'],
        'Verfügbare Variablen: %s, %s, %s' => ['de' => 'Verfügbare Variablen: %s, %s, %s', 'en' => 'Available variables: %s, %s, %s'],
        'Chronos Timing & Alerts Speichern' => ['de' => 'Chronos Timing & Alarme Speichern', 'en' => 'Save Chronos Timing & Alerts'],

        // =========================================================================
        // 23. DATA INTEGRITY (FILESYSTEM PERMISSIONS)
        // =========================================================================
        'DATEISYSTEM SICHERHEIT' => ['de' => 'DATEISYSTEM-SICHERHEIT', 'en' => 'DATA INTEGRITY & PERMISSIONS'],
        'DATENSICHERHEIT' => ['de' => 'DATENSICHERHEIT', 'en' => 'DATA INTEGRITY'],
        'ALL PERMISSIONS SECURE' => ['de' => 'ALLE RECHTE SICHER', 'en' => 'ALL PERMISSIONS SECURE'],
        '%d ANOMALY DETECTED' => ['de' => '%d ANOMALIE ERKANNT', 'en' => '%d ANOMALY DETECTED'],
        '%d ANOMALIES DETECTED' => ['de' => '%d ANOMALIEN ERKANNT', 'en' => '%d ANOMALIES DETECTED'],
        'Permission Audit:' => ['de' => 'Rechte-Audit:', 'en' => 'Permission Audit:'],
        'CHMOD KONTROLLE:' => ['de' => 'CHMOD KONTROLLE:', 'en' => 'CHMOD CONTROL:'],
        'Dieses Modul prüft kritische WordPress-Dateien auf korrekte Datei- und Ordnerrechte (Linux/Unix Standard). Fehlerhafte Berechtigungen (z.B. 0777) sind ein massives Sicherheitsrisiko.' => ['de' => 'Dieses Modul prüft kritische WordPress-Dateien auf korrekte Datei- und Ordnerrechte (Linux/Unix Standard). Fehlerhafte Berechtigungen (z.B. 0777) sind ein massives Sicherheitsrisiko.', 'en' => 'This module audits critical WordPress files for proper file and directory permissions (Linux/Unix standard). Insecure permissions (e.g. 0777) pose a major security vulnerability.'],
        'Empfehlung:' => ['de' => 'Empfehlung:', 'en' => 'Recommendation:'],
        'Ordner auf 0755, reguläre Dateien auf 0644, und die wp-config.php zwingend auf 0600 setzen.' => ['de' => 'Ordner auf 0755, reguläre Dateien auf 0644, und die wp-config.php zwingend auf 0600 setzen.', 'en' => 'Set folders to 0755, regular files to 0644, and wp-config.php strictly to 0600.'],
        'DATEI / ORDNER' => ['de' => 'DATEI / ORDNER', 'en' => 'FILE / DIRECTORY'],
        'PFAD (ABSOLUT)' => ['de' => 'PFAD (ABSOLUT)', 'en' => 'PATH (ABSOLUTE)'],
        'AKTUELL' => ['de' => 'AKTUELL', 'en' => 'CURRENT'],
        'SOLL' => ['de' => 'SOLL', 'en' => 'RECOMMENDED'],
        'EINGRIFF ERFORDERLICH:' => ['de' => 'EINGRIFF ERFORDERLICH:', 'en' => 'INTERVENTION REQUIRED:'],
        'Wenn Rechte als "Warning" markiert sind, ändern Sie diese bitte manuell über Ihren FTP-Client (z.B. FileZilla) oder Ihr Hosting-Panel. Die VGT Engine greift aus Stabilitätsgründen nicht direkt in die Dateirechte ein.' => ['de' => 'Wenn Rechte als "Warning" markiert sind, ändern Sie diese bitte manuell über Ihren FTP-Client (z.B. FileZilla) oder Ihr Hosting-Panel. Die VGT Engine greift aus Stabilitätsgründen nicht direkt in die Dateirechte ein.', 'en' => 'If permissions are marked as "Warning", please adjust them manually via your FTP client (e.g. FileZilla) or hosting panel. The VGT Engine does not alter filesystem permissions directly to preserve system stability.'],

        // =========================================================================
        // 24. KEY VAULT
        // =========================================================================
        'CRITICAL: VIS_Key_Vault Module not loaded.' => ['de' => 'KRITISCH: VIS_Key_Vault Modul nicht geladen.', 'en' => 'CRITICAL: VIS_Key_Vault module not loaded.'],
        'VGT KRYPTO VAULT' => ['de' => 'VGT KRYPTO VAULT', 'en' => 'VGT CRYPTO VAULT'],
        'AES-256-GCM Verschlüsselung | AAD-Binding Active' => ['de' => 'AES-256-GCM Verschlüsselung | AAD-Binding Aktiv', 'en' => 'AES-256-GCM Encryption | AAD-Binding Active'],
        'Asset erfolgreich im Vault kryptographisch versiegelt.' => ['de' => 'Asset erfolgreich im Vault kryptographisch versiegelt.', 'en' => 'Asset successfully sealed cryptographically in the vault.'],
        'Asset irreversibel aus der Matrix gelöscht.' => ['de' => 'Asset irreversibel aus der Matrix gelöscht.', 'en' => 'Asset irreversibly deleted from the matrix.'],
        'NEUEN KEY VERSCHLÜSSELN - Für AEGIS als vis_aegis_ai_key speichern!' => ['de' => 'NEUEN SCHLÜSSEL VERSCHLÜSSELN - Für AEGIS als vis_aegis_ai_key speichern!', 'en' => 'ENCRYPT NEW KEY - Save as vis_aegis_ai_key for AEGIS!'],
        'Key Identifier (Unique ID)' => ['de' => 'Schlüssel-Kennung (Eindeutige ID)', 'en' => 'Key Identifier (Unique ID)'],
        'z.B. vis_api_key_groq' => ['de' => 'z.B. vis_api_key_groq', 'en' => 'e.g. vis_api_key_groq'],
        'Wird als System-ID und AAD-Salt verwendet.' => ['de' => 'Wird als System-ID und AAD-Salt verwendet.', 'en' => 'Used as system ID and AAD salt.'],
        'Raw API Key (Plaintext)' => ['de' => 'Roher API-Schlüssel (Klartext)', 'en' => 'Raw API Key (Plaintext)'],
        'IN VAULT VERSIEGELN' => ['de' => 'IM TRESOR VERSIEGELN', 'en' => 'SEAL IN VAULT'],
        'VERSIEGELTE ASSETS (REGISTRY)' => ['de' => 'VERSIEGELTE SCHLÜSSEL (REGISTRY)', 'en' => 'SEALED ASSETS (REGISTRY)'],
        'Der Vault ist derzeit leer.' => ['de' => 'Der Schlüsseltresor ist derzeit leer.', 'en' => 'The vault is currently empty.'],
        'AES-256-GCM SECURED' => ['de' => 'AES-256-GCM GESICHERT', 'en' => 'AES-256-GCM SECURED'],
        'Usage: VIS_Key_Vault::get_key(\'%s\')' => ['de' => 'Verwendung: VIS_Key_Vault::get_key(\'%s\')', 'en' => 'Usage: VIS_Key_Vault::get_key(\'%s\')'],
        'WARNUNG: Das Löschen des Keys ist irreversibel. Angeschlossene Systeme (Morpheus AI etc.) könnten ausfallen. Fortfahren?' => ['de' => 'WARNUNG: Das Löschen des Keys ist irreversibel. Angeschlossene Systeme (Morpheus AI etc.) könnten ausfallen. Fortfahren?', 'en' => 'WARNING: Deleting this key is irreversible. Connected systems (Morpheus AI etc.) may fail. Continue?'],
        'TERMINIEREN' => ['de' => 'TERMINIEREN', 'en' => 'TERMINATE'],

        // =========================================================================
        // 25. ADD-ON MANAGER
        // =========================================================================
        'ADD-ON MANAGER & EXPANSION HUB' => ['de' => 'ADD-ON MANAGER & ERWEITERUNGEN', 'en' => 'ADD-ON MANAGER & EXPANSION HUB'],
        'GeDefense WP Open Core Architektur' => ['de' => 'GeDefense WP Open Core Architektur', 'en' => 'GeDefense WP Open Core Architecture'],
        'GeDefense WP ist als <strong>Open-Core System</strong> konzipiert: Die Kern-Sicherheitsschilde und die gesamte Abwehr-Matrix sind quelloffen und frei verfügbar. Erweiterte Module wie VLP (Privacy), Builder und SEO können als signierte ZIP-Pakete nachgeladen und nahtlos integriert werden.' => ['de' => 'GeDefense WP ist als <strong>Open-Core System</strong> konzipiert: Die Kern-Sicherheitsschilde und die gesamte Abwehr-Matrix sind quelloffen und frei verfügbar. Erweiterte Module wie VLP (Privacy), Builder und SEO können als signierte ZIP-Pakete nachgeladen und nahtlos integriert werden.', 'en' => 'GeDefense WP is built upon an <strong>Open-Core Architecture</strong>: Core defense shields and behavioral engines are open source and freely available. Advanced modules such as VLP (Privacy), Builder, and SEO can be loaded as signed ZIP packages and seamlessly integrated.'],
        'Add-On Paket hochladen (.zip)' => ['de' => 'Add-On Paket hochladen (.zip)', 'en' => 'Upload Add-On Package (.zip)'],
        'ZIP-Datei hierher ziehen oder' => ['de' => 'ZIP-Datei hierher ziehen oder', 'en' => 'Drag and drop ZIP file here or'],
        'Datei auswählen' => ['de' => 'Datei auswählen', 'en' => 'Select File'],
        'Unterstützt: Vision Legal Pro (VLP), Builder, GEO Architect (SEO)' => ['de' => 'Unterstützt: Vision Legal Pro (VLP), Builder, GEO Architect (SEO)', 'en' => 'Supports: Vision Legal Pro (VLP), Builder, GEO Architect (SEO)'],
        'INSTALLIERTE & VERFÜGBARE ADD-ONS' => ['de' => 'INSTALLIERTE & VERFÜGBARE ADD-ONS', 'en' => 'INSTALLED & AVAILABLE ADD-ONS'],
        'Add-On löschen' => ['de' => 'Add-On löschen', 'en' => 'Delete Add-On'],
        'Laden Sie das ZIP-Paket oben hoch, um dieses Modul zu aktivieren.' => ['de' => 'Laden Sie das ZIP-Paket oben hoch, um dieses Modul zu aktivieren.', 'en' => 'Upload the ZIP package above to activate this module.'],
        'BEREIT (v%s)' => ['de' => 'BEREIT (v%s)', 'en' => 'READY (v%s)'],
        'AKTIV' => ['de' => 'AKTIV', 'en' => 'ACTIVE'],
        'DEAKTIVIERT' => ['de' => 'DEAKTIVIERT', 'en' => 'DISABLED'],
        'FEHLT' => ['de' => 'FEHLT', 'en' => 'MISSING'],
        'Vision Legal Pro (VLP)' => ['de' => 'Vision Legal Pro (VLP)', 'en' => 'Vision Legal Pro (VLP)'],
        'GDPR & Datenschutz-Manager. Blockiert CDN-Outbounds, spiegelt Assets lokal auf dem Server und bietet einen revisionssicheren Telemetry-Vault.' => ['de' => 'GDPR & Datenschutz-Manager. Blockiert CDN-Outbounds, spiegelt Assets lokal auf dem Server und bietet einen revisionssicheren Telemetry-Vault.', 'en' => 'GDPR & Privacy Manager. Blocks third-party CDN outbounds, mirrors assets locally on the server, and provides an audit-proof telemetry vault.'],
        'Lightweight Builder' => ['de' => 'Lightweight Builder', 'en' => 'Lightweight Builder'],
        'Hochleistungs-HTML/CSS Injektions-Engine. Dient als schlanker, extrem schneller Ersatz für Elementor, ohne den Server-Arbeitsspeicher zu überlasten.' => ['de' => 'Hochleistungs-HTML/CSS Injektions-Engine. Dient als schlanker, extrem schneller Ersatz für Elementor, ohne den Server-Arbeitsspeicher zu überlasten.', 'en' => 'High-performance HTML/CSS injection engine. Serves as a lightweight, blazing fast replacement for Elementor without exhausting server memory.'],
        'GEO Architect (SEO)' => ['de' => 'GEO Architect (SEO)', 'en' => 'GEO Architect (SEO)'],
        'Generative Engine Optimization (GEO) & Entity Injection. Optimiert die Webseiten-Struktur semantisch für KI-Suchmaschinen.' => ['de' => 'Generative Engine Optimization (GEO) & Entity Injection. Optimiert die Webseiten-Struktur semantisch für KI-Suchmaschinen.', 'en' => 'Generative Engine Optimization (GEO) & Entity Injection. Semantically optimizes website structure for AI search engines.']
    ];

    public static function init(): void {
        if (isset($_GET['vis_lang'])) {
            $req_lang = strtolower(trim((string)$_GET['vis_lang']));
            if (in_array($req_lang, ['de', 'en'], true)) {
                self::set_language($req_lang);
            }
        }

        add_filter('gettext_vgt-sentinel', [__CLASS__, 'filter_gettext'], 20, 3);
        add_filter('ngettext_vgt-sentinel', [__CLASS__, 'filter_ngettext'], 20, 5);

        // Delayed user meta sync on 'init' when pluggable functions are loaded
        add_action('init', [__CLASS__, 'sync_user_language']);
    }

    public static function sync_user_language(): void {
        if (isset($_GET['vis_lang'])) {
            $req_lang = strtolower(trim((string)$_GET['vis_lang']));
            if (in_array($req_lang, ['de', 'en'], true)) {
                if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('get_current_user_id')) {
                    $uid = (int) get_current_user_id();
                    if ($uid > 0) {
                        update_user_meta($uid, 'vis_dashboard_lang', $req_lang);
                    }
                }
            }
        }
    }

    public static function get_language(): string {
        if (self::$current_lang !== null) {
            return self::$current_lang;
        }

        if (isset($_COOKIE['vis_lang']) && in_array($_COOKIE['vis_lang'], ['de', 'en'], true)) {
            self::$current_lang = $_COOKIE['vis_lang'];
            return self::$current_lang;
        }

        if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('get_current_user_id')) {
            $uid = (int) get_current_user_id();
            if ($uid > 0) {
                $user_lang = get_user_meta($uid, 'vis_dashboard_lang', true);
                if (in_array($user_lang, ['de', 'en'], true)) {
                    self::$current_lang = $user_lang;
                    return self::$current_lang;
                }
            }
        }

        $opt = get_option('vis_config', []);
        if (!empty($opt['dashboard_lang']) && in_array($opt['dashboard_lang'], ['de', 'en'], true)) {
            self::$current_lang = $opt['dashboard_lang'];
            return self::$current_lang;
        }

        // Default: German
        self::$current_lang = 'de';
        return self::$current_lang;
    }

    public static function set_language(string $lang): void {
        if (!in_array($lang, ['de', 'en'], true)) {
            return;
        }
        self::$current_lang = $lang;

        if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('get_current_user_id')) {
            $uid = (int) get_current_user_id();
            if ($uid > 0) {
                update_user_meta($uid, 'vis_dashboard_lang', $lang);
            }
        }

        $opt = get_option('vis_config', []);
        $opt['dashboard_lang'] = $lang;
        update_option('vis_config', $opt);

        if (!headers_sent()) {
            $cookie_path = defined('COOKIEPATH') && is_string(COOKIEPATH) ? COOKIEPATH : '/';
            $cookie_domain = defined('COOKIE_DOMAIN') && is_string(COOKIE_DOMAIN) ? COOKIE_DOMAIN : '';
            setcookie('vis_lang', $lang, time() + 31536000, $cookie_path, $cookie_domain);
        }
    }

    public static function filter_gettext(string $translation, string $text, string $domain): string {
        if ($domain !== 'vgt-sentinel') {
            return $translation;
        }

        $lang = self::get_language(); // 'de' or 'en'
        $lookup = trim($text);

        // 1. Direct match in dictionary
        if (isset(self::$dictionary[$lookup][$lang])) {
            return self::$dictionary[$lookup][$lang];
        }

        // 2. Bidirectional & case-insensitive search
        foreach (self::$dictionary as $key => $entry) {
            $match = (strcasecmp($key, $lookup) === 0)
                || (isset($entry['de']) && strcasecmp($entry['de'], $lookup) === 0)
                || (isset($entry['en']) && strcasecmp($entry['en'], $lookup) === 0);
            
            if ($match && isset($entry[$lang])) {
                return $entry[$lang];
            }
        }

        return $translation;
    }

    public static function filter_ngettext(string $translation, string $single, string $plural, int $number, string $domain): string {
        if ($domain !== 'vgt-sentinel') {
            return $translation;
        }

        $lang = self::get_language();
        if ($lang === 'en') {
            return $number === 1 ? $single : $plural;
        }

        return $translation;
    }

    public static function render_language_switcher(string $class = ''): string {
        $curr = self::get_language();
        $base_url = remove_query_arg('vis_lang');
        $de_url = add_query_arg('vis_lang', 'de', $base_url);
        $en_url = add_query_arg('vis_lang', 'en', $base_url);

        $html = '<div class="vis-lang-switcher ' . esc_attr($class) . '" style="display:inline-flex; align-items:center; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:2px 4px; font-size:11px; font-weight:700;">';
        
        // DE Pill
        $de_active = ($curr === 'de');
        $de_style = $de_active ? 'background:rgba(16,185,129,0.2); color:#10b981; border:1px solid rgba(16,185,129,0.4);' : 'color:#94a3b8; border:1px solid transparent;';
        $html .= '<a href="' . esc_url($de_url) . '" style="text-decoration:none; padding:3px 8px; border-radius:14px; transition:all 0.2s; ' . $de_style . '">🇩🇪 DE</a>';
        
        // EN Pill
        $en_active = ($curr === 'en');
        $en_style = $en_active ? 'background:rgba(59,130,246,0.2); color:#3b82f6; border:1px solid rgba(59,130,246,0.4);' : 'color:#94a3b8; border:1px solid transparent;';
        $html .= '<a href="' . esc_url($en_url) . '" style="text-decoration:none; padding:3px 8px; border-radius:14px; transition:all 0.2s; ' . $en_style . '">🇬🇧 EN</a>';
        
        $html .= '</div>';
        return $html;
    }
}
