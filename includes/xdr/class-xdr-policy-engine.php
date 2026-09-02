<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class PolicyEngine {
    /** @return array{tier:string,containment_allowed:bool,reason:string} */
    public static function decide(int $confidence, int $independentCategories, bool $decisive = false): array {
        $confidence = max(0, min(100, $confidence));
        $tier = match (true) {
            $confidence >= 95 => 'CRITICAL',
            $confidence >= 90 => 'HIGH_CONFIDENCE_CONTAINMENT',
            $confidence >= 80 => 'CONTAIN',
            $confidence >= 65 => 'DEFENSIVE',
            $confidence >= 40 => 'ENRICH',
            default => 'OBSERVE',
        };

        // Multi-sensor containment gate: Requires >= 80% confidence AND at least 2 independent detection categories (or a decisive single-shot event like confirmed webshell or canary touch)
        $allowed = $confidence >= 80 && ($independentCategories >= 2 || $decisive);

        return [
            'tier' => $tier,
            'containment_allowed' => $allowed,
            'reason' => $allowed ? 'MULTI_SENSOR_POLICY' : 'INSUFFICIENT_INDEPENDENT_EVIDENCE',
        ];
    }
}
