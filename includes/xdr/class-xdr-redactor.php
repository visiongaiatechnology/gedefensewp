<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class Redactor {
    private const SENSITIVE_KEY = '/(?:password|passwd|pwd|token|access_token|refresh_token|authorization|cookie|secret|api[_-]?key|apikey|private_key)/i';
    private const MAX_DEPTH = 4;
    private const MAX_ITEMS = 32;
    private const MAX_STRING = 512;

    /** @return array<string, scalar|null|array> */
    public static function sanitize(array $input, int $depth = 0): array {
        if ($depth >= self::MAX_DEPTH) return [];
        $safe = [];
        foreach (array_slice($input, 0, self::MAX_ITEMS, true) as $key => $value) {
            $name = substr(preg_replace('/[^a-zA-Z0-9_.:-]/', '_', (string)$key) ?? 'field', 0, 64);
            if (preg_match(self::SENSITIVE_KEY, $name) === 1) {
                $safe[$name] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $safe[$name] = self::sanitize($value, $depth + 1);
            } elseif (is_string($value)) {
                $safe[$name] = substr(preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '', 0, self::MAX_STRING);
            } elseif (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $safe[$name] = $value;
            }
        }
        return $safe;
    }
}
