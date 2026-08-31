<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final readonly class VIS_Scan_Budget {
    public function __construct(
        public int $maxBytes,
        public int $maxMilliseconds,
        public int $maxFindings,
        public int $maxArchiveEntries,
        public int $maxArchiveUncompressedBytes,
        public float $maxArchiveExpansionRatio
    ) {
        if ($maxBytes < 1024 || $maxBytes > 33554432
            || $maxMilliseconds < 10 || $maxMilliseconds > 5000
            || $maxFindings < 1 || $maxFindings > 100
            || $maxArchiveEntries < 1 || $maxArchiveEntries > 10000
            || $maxArchiveUncompressedBytes < 1048576 || $maxArchiveUncompressedBytes > 1073741824
            || $maxArchiveExpansionRatio < 1.0 || $maxArchiveExpansionRatio > 1000.0) {
            throw new ValidationException('Invalid scanner budget.');
        }
    }

    public static function fastUpload(int $fileLimit): self {
        return new self(min(max($fileLimit, 1048576), 33554432), 250, 12, 256, 67108864, 40.0);
    }

    public static function deepFilesystem(): self {
        return new self(16777216, 1500, 32, 2000, 268435456, 80.0);
    }
}
