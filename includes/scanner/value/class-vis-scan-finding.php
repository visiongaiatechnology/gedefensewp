<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final readonly class VIS_Scan_Finding {
    public function __construct(
        public string $code,
        public int $risk,
        public int $confidence,
        public string $message,
        public bool $quarantineEligible = false
    ) {
        if (preg_match('/^[A-Z0-9_]{3,64}$/D', $code) !== 1
            || $risk < 0 || $risk > 100
            || $confidence < 0 || $confidence > 100) {
            throw new ValidationException('Invalid malware finding.');
        }
    }

    /** @return array{code:string,risk:int,confidence:int,message:string,quarantine_eligible:bool} */
    public function toArray(): array {
        return [
            'code' => $this->code,
            'risk' => $this->risk,
            'confidence' => $this->confidence,
            'message' => $this->message,
            'quarantine_eligible' => $this->quarantineEligible,
        ];
    }
}
