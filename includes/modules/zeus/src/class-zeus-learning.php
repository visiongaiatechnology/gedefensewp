<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Learning {

    public const OPTION_CANDIDATES = 'vis_zeus_learned_candidates';
    public const MAX_OBSERVED_ROUTES = 50;

    /**
     * Passively observes non-sensitive structural metadata of incoming request.
     * Never observes or persists payload content.
     *
     * @param string $path
     * @param string $method
     * @param string $contentType
     * @param int $bodyBytes
     * @param int $paramCount
     * @return void
     */
    public static function observe(
        string $path,
        string $method,
        string $contentType,
        int $bodyBytes,
        int $paramCount
    ): void {
        $config = get_option('vis_zeus_config', []);
        if (empty($config['learning_mode_enabled'])) {
            return;
        }

        $cleanPath = '/' . ltrim((string)parse_url($path, PHP_URL_PATH), '/');
        // Group REST and deep URLs into route families
        if (str_starts_with($cleanPath, '/wp-json/')) {
            $parts = explode('/', trim($cleanPath, '/'));
            if (count($parts) >= 3) {
                $cleanPath = '/' . $parts[0] . '/' . $parts[1] . '/' . $parts[2] . '/';
            }
        } elseif (str_starts_with($cleanPath, '/wp-admin/')) {
            $cleanPath = '/wp-admin/';
        }

        $candidates = get_option(self::OPTION_CANDIDATES, []);
        if (!is_array($candidates)) $candidates = [];

        if (count($candidates) >= self::MAX_OBSERVED_ROUTES && !isset($candidates[$cleanPath])) {
            return; // Bounded storage
        }

        if (!isset($candidates[$cleanPath])) {
            $candidates[$cleanPath] = [
                'route' => $cleanPath,
                'methods' => [],
                'content_types' => [],
                'max_observed_body' => 0,
                'max_observed_params' => 0,
                'hit_count' => 0,
                'first_seen' => gmdate('Y-m-d H:i:s'),
                'last_seen' => gmdate('Y-m-d H:i:s'),
                'status' => 'LEARNING'
            ];
        }

        $candidates[$cleanPath]['methods'][$method] = true;
        if ($contentType !== '') {
            $baseMime = strtolower(trim(explode(';', $contentType)[0]));
            if ($baseMime !== '') $candidates[$cleanPath]['content_types'][$baseMime] = true;
        }
        $candidates[$cleanPath]['max_observed_body'] = max((int)$candidates[$cleanPath]['max_observed_body'], $bodyBytes);
        $candidates[$cleanPath]['max_observed_params'] = max((int)$candidates[$cleanPath]['max_observed_params'], $paramCount);
        $candidates[$cleanPath]['hit_count'] = (int)$candidates[$cleanPath]['hit_count'] + 1;
        $candidates[$cleanPath]['last_seen'] = gmdate('Y-m-d H:i:s');

        // Transition to CANDIDATE if observed enough traffic
        if ($candidates[$cleanPath]['hit_count'] >= 20 && $candidates[$cleanPath]['status'] === 'LEARNING') {
            $candidates[$cleanPath]['status'] = 'CANDIDATE';
        }

        update_option(self::OPTION_CANDIDATES, $candidates, false);
    }

    /**
     * Retrieves all learned candidates.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getCandidates(): array {
        $candidates = get_option(self::OPTION_CANDIDATES, []);
        return is_array($candidates) ? $candidates : [];
    }
}
