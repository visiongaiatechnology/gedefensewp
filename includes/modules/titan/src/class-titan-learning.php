<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Titan_Learning {
    private const SUPPORTED_TYPES = ['script', 'style'];

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public static function stageCandidate(array $config): array {
        $records = get_option('vis_titan_learned_origins', []);
        $records = is_array($records) ? $records : [];
        $origins = ['script' => [], 'style' => []];
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            $type = strtolower((string)($record['type'] ?? ''));
            $origin = self::validatedOrigin((string)($record['origin'] ?? ''));
            if (!in_array($type, self::SUPPORTED_TYPES, true) || $origin === null) continue;
            $origins[$type][$origin] = true;
        }
        if ($origins['script'] === [] && $origins['style'] === []) throw new ValidationException('No eligible TITAN learning observations exist.');
        foreach ($origins as $type => $learned) {
            $key = 'titan_' . $type . '_origins';
            $configured = preg_split('/\R+/', (string)($config[$key] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $merged = [];
            foreach (is_array($configured) ? $configured : [] as $value) {
                $valid = self::validatedOrigin(trim($value));
                if ($valid !== null) $merged[$valid] = true;
            }
            foreach (array_keys($learned) as $origin) $merged[$origin] = true;
            ksort($merged, SORT_STRING);
            $config[$key] = implode("\n", array_keys($merged));
        }
        $config['titan_csp_mode'] = 'report_only';
        return VIS_Titan_Policy_Store::stage($config);
    }

    private static function validatedOrigin(string $origin): ?string {
        if (strlen($origin) < 9 || strlen($origin) > 512) return null;
        $parts = wp_parse_url($origin);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) return null;
        $host = strtolower((string)($parts['host'] ?? ''));
        if (preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host) !== 1) return null;
        if (!empty($parts['user']) || !empty($parts['pass']) || !empty($parts['path']) || !empty($parts['query']) || !empty($parts['fragment'])) return null;
        $port = isset($parts['port']) ? (int)$parts['port'] : 0;
        if ($port !== 0 && ($port < 1 || $port > 65535)) return null;
        return strtolower((string)$parts['scheme']) . '://' . $host . ($port !== 0 ? ':' . $port : '');
    }
}
