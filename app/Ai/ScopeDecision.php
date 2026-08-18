<?php

namespace App\Ai;

/**
 * Hasil scope guard.
 */
final class ScopeDecision
{
    public function __construct(
        public readonly bool $inScope,
        public readonly string $intent, // definition|numeric_statistic|publication|metadata_methodology|bps_service|navigation|out_of_scope
        public readonly array $missing = [], // parameter wajib yang kurang (indicator/geography/period)
    ) {}

    public static function inScope(string $intent, array $missing = []): self
    {
        return new self(true, $intent, $missing);
    }

    public static function outOfScope(): self
    {
        return new self(false, 'out_of_scope', []);
    }
}
