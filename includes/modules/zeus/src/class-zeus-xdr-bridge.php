<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

namespace VisionGaia\GeDefense\Modules\Zeus;

if (!defined('ABSPATH') && !defined('VGT_ZEUS_PREBOOT')) exit('VGT_ACCESS_DENIED');

final class Zeus_Xdr_Bridge {

    public const OPTION_CONTAINMENT = 'vis_zeus_xdr_containment';

    /**
     * Applies Virtual Emergency Route Containment.
     *
     * @param string $routePrefix e.g. '/wp-json/vulnerable-plugin/v1/'
     * @param string $incidentId
     * @param int $ttl Default 1800s
     * @param string|null $responseId
     * @return void
     */
    public static function containRoute(
        string $routePrefix,
        string $incidentId,
        int $ttl = 1800,
        ?string $responseId = null
    ): void {
        $cleanRoute = '/' . ltrim($routePrefix, '/');
        $containments = (function_exists('get_option') ? get_option(self::OPTION_CONTAINMENT, []) : []);
        if (!is_array($containments)) $containments = [];

        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + $ttl);
        $respId = $responseId ?? substr(hash('sha256', "{$incidentId}|ROUTE_CONTAIN|{$cleanRoute}"), 0, 32);

        $containments['routes'][$cleanRoute] = [
            'incident_id' => $incidentId,
            'response_id' => $respId,
            'route' => $cleanRoute,
            'created_at' => $now,
            'expires_at' => $expires,
            'status' => 'ACTIVE'
        ];

        update_option(self::OPTION_CONTAINMENT, $containments);

        // Recompile Zeus WAF for instant L0 enforcement
        if (class_exists('\VIS_Zeus')) {
            $zeus = new \VIS_Zeus();
            $zeus->deploy_perimeter_shield();
        }
    }

    /**
     * Removes temporary route containment by incident ID, route, or response ID.
     */
    public static function removeRouteContainment(string $identifier, ?string $responseId = null): void {
        $containments = (function_exists('get_option') ? get_option(self::OPTION_CONTAINMENT, []) : []);
        if (!is_array($containments) || empty($containments['routes'])) return;

        $modified = false;
        $cleanRoute = '/' . ltrim($identifier, '/');

        foreach ($containments['routes'] as $route => $info) {
            $matches = (($info['incident_id'] ?? '') === $identifier)
                || ($route === $cleanRoute)
                || (($info['response_id'] ?? '') === $identifier);

            if ($matches) {
                if ($responseId !== null && ($info['response_id'] ?? '') !== $responseId) {
                    continue; // Owned by different response
                }
                unset($containments['routes'][$route]);
                $modified = true;
            }
        }

        if ($modified) {
            update_option(self::OPTION_CONTAINMENT, $containments);
            if (class_exists('\VIS_Zeus')) {
                $zeus = new \VIS_Zeus();
                $zeus->deploy_perimeter_shield();
            }
        }
    }

    /**
     * Checks if a route path is currently contained under Hard Semantic TTL.
     */
    public static function isRouteContained(string $path): bool {
        $active = self::getActiveRouteContainments();
        $cleanPath = '/' . ltrim($path, '/');
        foreach ($active as $prefix => $info) {
            if (str_starts_with($cleanPath, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieves all actively contained routes where TTL has not expired.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getActiveRouteContainments(): array {
        $containments = (function_exists('get_option') ? get_option(self::OPTION_CONTAINMENT, []) : []);
        if (!is_array($containments) || empty($containments['routes'])) return [];

        $now = gmdate('Y-m-d H:i:s');
        $active = [];

        foreach ($containments['routes'] as $route => $info) {
            if (is_array($info) && ($info['expires_at'] ?? '') > $now && ($info['status'] ?? '') === 'ACTIVE') {
                $active[$route] = $info;
            }
        }

        return $active;
    }
}
