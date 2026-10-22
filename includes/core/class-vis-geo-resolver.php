<?php
/**
 * GEODEFENSE LOCAL GEOIP & ASN RESOLVER
 * STATUS: DIAMANT VGT SUPREME
 * 
 * 100% Local BGP/GeoIP Binary Search Engine. Zero external network requests, zero CDN leaks.
 * Fast O(log N) binary fseek search over sorted CIDR database (sub-millisecond resolution).
 */

declare(strict_types=1);

namespace VisionGaia\GeDefense\Core;

if (!defined('ABSPATH')) {
    exit('VGT_ACCESS_DENIED');
}

final class GeoResolver {

    /**
     * In-memory cache for the current request.
     * @var array<string, array>
     */
    private static array $memory_cache = [];

    /**
     * Map dimensions & Mercator bounding box matching world-merc.js.
     */
    public const MAP_WIDTH = 900.0;
    public const MAP_HEIGHT = 583.0802520919394;
    private const BBOX_X0 = -20004297.151525836;
    private const BBOX_X1 = 20026572.394749384;
    private const BBOX_Y0 = -18449355.69035302;
    private const BBOX_Y1 = 7485321.539093307;
    private const RADIUS = 6381372.0;

    /**
     * Resolve an IPv4 address to Country, Geo Coordinates, ASN, and Organization.
     *
     * @param string $ip
     * @return array
     */
    public static function resolve(string $ip): array {
        $ip = trim($ip);
        if (empty($ip)) {
            return self::empty_result($ip, 'Empty IP');
        }

        if (isset(self::$memory_cache[$ip])) {
            return self::$memory_cache[$ip];
        }

        // Check for private / bogon / loopback
        if (self::is_private_ip($ip)) {
            $local = [
                'ip'           => $ip,
                'country_code' => 'LOCAL',
                'country_name' => 'Internal / Loopback',
                'latitude'     => 51.1657,
                'longitude'    => 10.4515,
                'asn'          => 'AS-INTERNAL',
                'as_name'      => 'Internal Network Node',
                'is_private'   => true,
                'known'        => true,
            ];
            self::$memory_cache[$ip] = $local;
            return $local;
        }

        // Check WordPress transient cache (7-day TTL)
        $transient_key = 'vis_geo_' . substr(md5($ip), 0, 16);
        $cached = get_transient($transient_key);
        if (is_array($cached) && !empty($cached['country_code'])) {
            self::$memory_cache[$ip] = $cached;
            return $cached;
        }

        // Perform local binary search on geoip.csv
        $csv_path = self::get_csv_path();
        if ($csv_path && file_exists($csv_path)) {
            $lookup = self::binary_search_csv($ip, $csv_path);
            if ($lookup !== null) {
                $res = [
                    'ip'           => $ip,
                    'country_code' => strtoupper($lookup['country_code'] ?? 'UN'),
                    'country_name' => $lookup['country_name'] ?? 'Unknown Region',
                    'latitude'     => (float)($lookup['latitude'] ?? 0.0),
                    'longitude'    => (float)($lookup['longitude'] ?? 0.0),
                    'asn'          => $lookup['asn'] ?? 'AS-UNKNOWN',
                    'as_name'      => $lookup['as_name'] ?? 'Autonomous System',
                    'is_private'   => false,
                    'known'        => true,
                ];
                set_transient($transient_key, $res, 7 * DAY_IN_SECONDS);
                self::$memory_cache[$ip] = $res;
                return $res;
            }
        }

        // Fallback if not resolved
        $fallback = self::deterministic_fallback($ip);
        self::$memory_cache[$ip] = $fallback;
        return $fallback;
    }

    /**
     * Resolves the server origin (host location).
     *
     * @return array
     */
    public static function get_origin(): array {
        $server_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname() ?: 'localhost');
        if (!empty($server_ip) && !self::is_private_ip($server_ip)) {
            $resolved = self::resolve($server_ip);
            if ($resolved['known'] && $resolved['latitude'] != 0.0) {
                return $resolved;
            }
        }

        // Default Central Europe Hub (Frankfurt / Cyber Defense Sentinel Datacenter)
        return [
            'ip'           => $server_ip ?: '127.0.0.1',
            'country_code' => 'DE',
            'country_name' => 'Host Sovereign Hub (DE-FRA)',
            'latitude'     => 50.1109,
            'longitude'    => 8.6821,
            'asn'          => 'VGT-SOVEREIGN',
            'as_name'      => 'VisionGaia Core Infrastructure',
            'is_private'   => true,
            'known'        => true,
        ];
    }

    /**
     * Convert Latitude & Longitude to SVG (x, y) coordinates on the 900x583 Mercator grid.
     *
     * @param float $lat
     * @param float $lng
     * @return array{x: float, y: float}
     */
    public static function lat_lng_to_point(float $lat, float $lng): array {
        // Clamp latitudes to avoid infinite tangent asymptotes at poles
        $lat = max(-84.0, min(84.0, $lat));
        $lng = max(-180.0, min(180.0, $lng));

        $rad_deg = M_PI / 180.0;
        $raw_x = self::RADIUS * $lng * $rad_deg;
        $raw_y = - self::RADIUS * log(tan(M_PI / 4.0 + ($lat * M_PI / 360.0)));

        $x = (($raw_x - self::BBOX_X0) / (self::BBOX_X1 - self::BBOX_X0)) * self::MAP_WIDTH;
        $y = (($raw_y - self::BBOX_Y0) / (self::BBOX_Y1 - self::BBOX_Y0)) * self::MAP_HEIGHT;

        return [
            'x' => round($x, 2),
            'y' => round($y, 2),
        ];
    }

    /**
     * Check if an IP is private, bogon, or loopback.
     */
    public static function is_private_ip(string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Locate the local GeoIP CSV file.
     */
    public static function get_csv_path(): ?string {
        $paths = [
            defined('VIS_PATH') ? VIS_PATH . 'data/geoip.csv' : '',
            dirname(__DIR__, 2) . '/data/geoip.csv',
            defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads/gedefense/geoip.csv' : '',
        ];

        if (function_exists('wp_upload_dir')) {
            $uploads = wp_upload_dir();
            if (!empty($uploads['basedir'])) {
                $paths[] = $uploads['basedir'] . '/gedefense/geoip.csv';
            }
        }

        if (function_exists('apply_filters')) {
            $paths = (array)apply_filters('vis_geoip_csv_paths', $paths);
        }

        foreach ($paths as $path) {
            if (!empty($path) && file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * High-speed binary search over sorted CSV.
     */
    private static function binary_search_csv(string $ip_str, string $file_path): ?array {
        $long = ip2long($ip_str);
        if ($long === false) {
            return null;
        }
        $ip_num = (float)sprintf('%u', $long);

        $fp = @fopen($file_path, 'r');
        if (!$fp) {
            return null;
        }

        $file_size = (int)filesize($file_path);
        $low = 0;
        $high = $file_size;
        $best = null;
        $steps = 0;

        while ($low <= $high && $steps < 36) {
            $steps++;
            $mid = (int)(($low + $high) / 2);
            fseek($fp, $mid);

            // Discard partial line
            if ($mid > 0) {
                fgets($fp);
            }

            $line = fgets($fp);
            if ($line === false) {
                $high = $mid - 1;
                continue;
            }

            // Skip header if landed at top of file
            if ($mid === 0 && strpos($line, 'network') === 0) {
                $line = fgets($fp);
                if ($line === false) {
                    break;
                }
            }

            $row = str_getcsv($line);
            if (empty($row[0]) || !str_contains($row[0], '/')) {
                $low = $mid + 1;
                continue;
            }

            [$start, $end] = self::cidr_to_range($row[0]);
            if ($ip_num >= $start && $ip_num <= $end) {
                $best = [
                    'network'      => $row[0],
                    'country_code' => $row[1] ?? '',
                    'country_name' => $row[2] ?? '',
                    'latitude'     => isset($row[3]) && is_numeric($row[3]) ? (float)$row[3] : 0.0,
                    'longitude'    => isset($row[4]) && is_numeric($row[4]) ? (float)$row[4] : 0.0,
                    'asn'          => $row[5] ?? '',
                    'as_name'      => $row[6] ?? '',
                ];
                break;
            }

            if ($ip_num < $start) {
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        fclose($fp);
        return $best;
    }

    /**
     * Converts a CIDR prefix (e.g., 1.1.1.0/24) to [start_float, end_float].
     */
    private static function cidr_to_range(string $cidr): array {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) {
            return [0.0, 0.0];
        }

        $ip = ip2long($parts[0]);
        if ($ip === false) {
            return [0.0, 0.0];
        }

        $ip = (float)sprintf('%u', $ip);
        $mask_len = (int)$parts[1];
        if ($mask_len < 0 || $mask_len > 32) {
            return [0.0, 0.0];
        }

        $mask = ~((1 << (32 - $mask_len)) - 1);
        $start = (float)((int)$ip & $mask);
        $end = $start + (float)((1 << (32 - $mask_len)) - 1);

        return [$start, $end];
    }

    /**
     * Deterministic fallback coordinates for unmapped public IPs.
     */
    private static function deterministic_fallback(string $ip): array {
        $hash = crc32($ip);
        // Distribute across plausible continental coordinates
        $regions = [
            ['US', 'United States', 37.751, -122.419, 'AS15169', 'Cloud Telemetry Node'],
            ['DE', 'Germany', 50.1109, 8.6821, 'AS3320', 'European Backbone Gateway'],
            ['NL', 'Netherlands', 52.3676, 4.9041, 'AS1103', 'Amsterdam Internet Exchange'],
            ['GB', 'United Kingdom', 51.5074, -0.1278, 'AS2856', 'British Telecom Gateway'],
            ['FR', 'France', 48.8566, 2.3522, 'AS12322', 'Free SAS Transit'],
            ['RU', 'Russia', 55.7558, 37.6173, 'AS12389', 'Rostelecom Backbone'],
            ['CN', 'China', 39.9042, 116.4074, 'AS4134', 'Chinanet Backbone'],
            ['JP', 'Japan', 35.6762, 139.6503, 'AS2516', 'KDDI Corporation'],
            ['SG', 'Singapore', 1.3521, 103.8198, 'AS4657', 'StarHub Ltd'],
            ['BR', 'Brazil', -23.5505, -46.6333, 'AS27699', 'Telefonica Brasil'],
            ['AU', 'Australia', -33.8688, 151.2093, 'AS13335', 'Cloudflare Edge'],
        ];
        $selected = $regions[abs($hash) % count($regions)];

        return [
            'ip'           => $ip,
            'country_code' => $selected[0],
            'country_name' => $selected[1],
            'latitude'     => $selected[2],
            'longitude'    => $selected[3],
            'asn'          => $selected[4],
            'as_name'      => $selected[5],
            'is_private'   => false,
            'known'        => false,
        ];
    }

    private static function empty_result(string $ip, string $reason): array {
        return [
            'ip'           => $ip,
            'country_code' => 'UN',
            'country_name' => $reason,
            'latitude'     => 0.0,
            'longitude'    => 0.0,
            'asn'          => 'AS-UNKNOWN',
            'as_name'      => 'Unknown',
            'is_private'   => true,
            'known'        => false,
        ];
    }
}

// Alias for global legacy backwards compatibility
if (!class_exists('VIS_Geo_Resolver')) {
    class_alias(GeoResolver::class, 'VIS_Geo_Resolver');
}
