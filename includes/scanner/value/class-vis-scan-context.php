<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final readonly class VIS_Scan_Context {
    public const PROFILE_FAST_UPLOAD = 'FAST_UPLOAD';
    public const PROFILE_DEEP_FILESYSTEM = 'DEEP_FILESYSTEM';

    public function __construct(
        public string $profile,
        public string $source,
        public string $relativePath,
        public string $originalName,
        public string $extension,
        public string $detectedMime,
        public ?string $expectedMime = null,
        public string $changeType = 'UNKNOWN'
    ) {
        if (!in_array($profile, [self::PROFILE_FAST_UPLOAD, self::PROFILE_DEEP_FILESYSTEM], true)) {
            throw new ValidationException('Invalid scan profile.');
        }
    }

    public function isExecutableExtension(): bool {
        return in_array($this->extension, ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar'], true);
    }
}
