<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final readonly class VIS_Scan_Verdict {
    /** @param list<VIS_Scan_Finding> $findings */
    public function __construct(
        public string $sha256,
        public int $risk,
        public int $confidence,
        public array $findings,
        public bool $truncated
    ) {}

    public function shouldBlock(): bool {
        return $this->risk >= 80 && $this->confidence >= 75;
    }

    public function shouldQuarantine(): bool {
        if ($this->risk < 95 || $this->confidence < 90) return false;
        foreach ($this->findings as $finding) {
            if ($finding->quarantineEligible) return true;
        }
        return false;
    }

    /** @return array{sha256:string,risk:int,confidence:int,truncated:bool,findings:list<array<string,mixed>>} */
    public function toArray(): array {
        return [
            'sha256' => $this->sha256,
            'risk' => $this->risk,
            'confidence' => $this->confidence,
            'truncated' => $this->truncated,
            'findings' => array_map(static fn(VIS_Scan_Finding $finding): array => $finding->toArray(), $this->findings),
        ];
    }
}
