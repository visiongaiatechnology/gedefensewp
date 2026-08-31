<?php
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Morpheus;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Morpheus_AI {

    private Morpheus $core;

    public function __construct( Morpheus $core ) {
        $this->core = $core;
        add_action( 'vgt_morpheus_async_ai_build', [ $this, 'cron_trigger_ai' ] );
    }

    public function log_audit( string $caller, string $violation, string $details, string $incident_hash ): void {
        $safe_caller = Morpheus_Path_Jail::validate_slug($caller);
        $log_file = Morpheus_Path_Jail::file('audit', $safe_caller, '.log');
        $submitted_file = Morpheus_Path_Jail::file('audit', $safe_caller, '.log.submitted');
        $processing_file = Morpheus_Path_Jail::file('audit', $safe_caller, '.log.processing');
        $proposed_file = Morpheus_Path_Jail::file('proposed', $safe_caller, '.json');
        $htaccess = Morpheus_Path_Jail::root_file('.htaccess');
        if ( ! file_exists( $htaccess ) ) {
            if (file_put_contents($htaccess, "Require all denied\n", LOCK_EX) === false) {
                throw new \StorageException('Morpheus access policy write failed.');
            }
            @chmod($htaccess, 0600);
        }

        if (file_exists($submitted_file) || file_exists($proposed_file)) {
            return;
        }

        $search_string = sprintf( "VIOLATION: %s | DETAILS: %s", $violation, $details );
        
        if ( file_exists( $log_file ) ) {
            $current_log = file_get_contents( $log_file );
            if ( $current_log !== false && strpos( $current_log, $search_string ) !== false ) {
                return; 
            }
        }

        $entry = sprintf( "[%s] %s\n", gmdate('Y-m-d H:i:s'), $search_string );
        
        if ( file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX ) === false ) {
            throw new \StorageException('Morpheus audit write failed.');
        }
        @chmod($log_file, 0600);

        $line_count = 0;
        if ( is_readable( $log_file ) ) {
            $handle = fopen( $log_file, "r" );
            if ( $handle ) {
                while ( ! feof( $handle ) ) {
                    $line = fgets( $handle );
                    if ( $line !== false && trim( $line ) !== '' ) {
                        $line_count++;
                    }
                }
                fclose( $handle );
            }
        }

        if ($line_count >= 200 && !file_exists($processing_file)) {
            Morpheus::ai_debug("Auto-Limit (200) erreicht für [$safe_caller]. Plane asynchrone WP-Cron Analyse.");
            
            if ( ! wp_next_scheduled( 'vgt_morpheus_async_ai_build', [ $safe_caller ] ) ) {
                wp_schedule_single_event( time(), 'vgt_morpheus_async_ai_build', [ $safe_caller ] );
            }
        }
    }

    public function cron_trigger_ai( string $slug ): void {
        $slug = Morpheus_Path_Jail::validate_slug($slug);
        $log_file = Morpheus_Path_Jail::file('audit', $slug, '.log');
        $processing_file = Morpheus_Path_Jail::file('audit', $slug, '.log.processing');

        if (file_exists($processing_file) || file_exists($log_file)) {
            Morpheus::ai_debug("==== ASYNC CRON AI TRIGGER FOR [$slug] ====");
            $this->trigger_ai($slug, false);
        }
    }

    public function trigger_ai(string $caller, bool $is_manual = false): array {
        $caller = Morpheus_Path_Jail::validate_slug($caller);
        $log_file = Morpheus_Path_Jail::file('audit', $caller, '.log');
        $processing_file = Morpheus_Path_Jail::file('audit', $caller, '.log.processing');
        $submitted_file = Morpheus_Path_Jail::file('audit', $caller, '.log.submitted');
        $proposed_file = Morpheus_Path_Jail::file('proposed', $caller, '.json');

        if (is_file($processing_file)) {
            $active_log = $processing_file;
        } elseif (is_readable($log_file) && rename($log_file, $processing_file)) {
            @chmod($processing_file, 0600);
            $active_log = $processing_file;
        } else {
            return ['success' => false, 'message' => 'Audit log file is unreadable or missing.'];
        }
        
        $log_content = file_get_contents($active_log);
        if ( $log_content === false ) {
            return ['success' => false, 'message' => 'Failed to read content from log file.'];
        }

        if ( strlen( $log_content ) > 24000 ) {
            $log_content = substr( $log_content, 0, 24000 ) . "\n...[TRUNCATED_BY_VGT_TOKEN_LIMIT]...";
        }

        Morpheus::$is_internal_action = true;
        try {
            Morpheus::ai_debug("Starte I/O Request zu Groq für [$caller]...");
            
            $system_prompt = <<<EOT
ROLE: You are AEGIS, a strict cyber-defense AI.
TASK: Analyze the provided Audit-Logs of a WordPress plugin ("{$caller}") and create a strict Permission Matrix.

CRITICAL RULES:
1. network: Array of real hostnames only (e.g. "api.stripe.com"), no raw IPs.
2. db_write: Array of SPECIFIC table prefixes belonging to the plugin. 
 * FATAL ERROR PREVENTION: NEVER use the global site prefix alone (like "wp_" or "wp_fb78f3_"). You MUST include the plugins unique identifier.
 * NOISE FILTER: Ignore obvious SQL keywords (SELECT, WHERE, AND, ORDER, LIMIT, ASC, DESC, UPDATE).
3. options: Identify options belonging to the plugin by matching abbreviations. Ignore cross-plugin pollution.
4. core_protection: NEVER allow access to options/tables containing "options", "users", "usermeta", "capabilities". IGNORE THEM entirely.
5. You MUST output ONLY valid JSON. No markdown, no explanations, no trailing text.

OUTPUT FORMAT (JSON ONLY):
{
  "network": ["api.stripe.com"],
  "db_write": ["wp_fb78f3_vis_"],
  "options": ["vis_"]
}
EOT;

            $prompt = "Plugin: {$caller}\n\nAudit Log:\n{$log_content}";
            $api_response = $this->call_ai_api( $system_prompt, $prompt );

            if ( $api_response['success'] ) {
                try {
                    $parsed_json = json_decode(
                        (string)$api_response['data'],
                        true,
                        32,
                        JSON_THROW_ON_ERROR
                    );
                } catch (\JsonException $e) {
                    $parsed_json = null;
                }
                if (is_array($parsed_json) && Morpheus::validate_matrix($parsed_json)) {
                    Morpheus::ai_debug("Erfolg! Groq hat valide JSON-Matrix für [$caller] generiert.");
                    $encoded = wp_json_encode($parsed_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    if (!is_string($encoded) || file_put_contents($proposed_file, $encoded, LOCK_EX) === false) {
                        throw new \StorageException('Proposed matrix write failed.');
                    }
                    @chmod($proposed_file, 0600);
                    if (!rename($processing_file, $submitted_file)) {
                        throw new \StorageException('Morpheus audit state commit failed.');
                    }
                    @chmod($submitted_file, 0600);
                    set_transient( 'morpheus_pending_review', true, DAY_IN_SECONDS );

                    return ['success' => true, 'message' => 'Matrix successfully built.'];
                } else {
                    Morpheus::ai_debug("FEHLER: Groq hat korruptes JSON geliefert. Verwerfe Antwort.");
                    self::restore_processing_log($processing_file, $log_file);
                    return ['success' => false, 'message' => 'AI returned invalid JSON structure.'];
                }
            } else {
                Morpheus::ai_debug("FEHLER: " . $api_response['message']);
                self::restore_processing_log($processing_file, $log_file);
                return ['success' => false, 'message' => $api_response['message']];
            }
        } finally {
            Morpheus::$is_internal_action = false;
        }
    }

    private static function restore_processing_log(string $processing_file, string $log_file): void {
        if (is_file($processing_file) && !rename($processing_file, $log_file)) {
            throw new \StorageException('Morpheus audit rollback failed.');
        }
        if (is_file($log_file)) {
            @chmod($log_file, 0600);
        }
    }

    private function call_ai_api( string $system, string $user ): array {
        try {
            $api_key = '';
            // VGT SUPREME FIX: Integration mit OMEGA HARDENED V3.1 Vault
            if ( class_exists( '\VIS_Key_Vault' ) ) {
                $api_key = \VIS_Key_Vault::get_key( 'vis_aegis_ai_key' );
            } else {
                return ['success' => false, 'message' => 'VGT KERNEL PANIC: VIS_Key_Vault subsystem offline oder nicht geladen.'];
            }
        } catch ( \Throwable $e ) {
            return ['success' => false, 'message' => 'Vault Decryption Error: ' . $e->getMessage()];
        }

        if ( empty( $api_key ) ) {
            return ['success' => false, 'message' => 'API Key nicht gefunden im VGT Vault.'];
        }

        $body = [
            'model'       => 'openai/gpt-oss-120b', // VGT PLATINUM: Restored High-Speed/Low-Cost Model
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user]
            ],
            'temperature' => 0.0, 
            'response_format' => ['type' => 'json_object']
        ];

        $request_body = wp_json_encode( $body );
        if ( $request_body === false ) {
            return ['success' => false, 'message' => 'Request payload rejected.'];
        }

        $response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', [
            'body'        => $request_body,
            'headers'     => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'VGT-Sentinel-Morpheus/7.4.0',
            ],
            'timeout'     => 30, // Timeout bleibt erhöht für absolute Resilienz
            'blocking'    => true,
            'sslverify'   => true,
            'redirection' => 0,
        ]);

        if ( is_wp_error( $response ) ) {
            $err = $response->get_error_message();
            Morpheus::ai_debug('Groq network failure: ' . $err);
            return ['success' => false, 'message' => 'Netzwerk/cURL Fehler: Upstream request failed.'];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            // Groq gibt bei Fehlern oft JSON zurück, extrahieren für bessere UX
            $parsed_err = json_decode($raw_body, true);
            $err_msg = isset($parsed_err['error']['message']) ? $parsed_err['error']['message'] : $raw_body;
            Morpheus::ai_debug("Groq API HTTP $status_code: " . $err_msg);
            return ['success' => false, 'message' => "Groq API HTTP $status_code: Upstream request rejected."];
        }

        $data = json_decode( $raw_body, true );
        return [
            'success' => true, 
            'data' => trim( $data['choices'][0]['message']['content'] ?? '' ),
            'message' => 'Success'
        ];
    }
}
