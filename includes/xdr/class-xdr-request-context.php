<?php
// STATUS: PLATIN
declare(strict_types=1);

namespace VisionGaia\GeDefense\Xdr;

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class RequestContext {
    private static ?string $requestId = null;

    public static function id(): string {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(16));
        }
        return self::$requestId;
    }
}
