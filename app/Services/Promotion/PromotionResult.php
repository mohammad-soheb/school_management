<?php

namespace App\Services\Promotion;

use App\Models\Promotion\PromotionRun;

/** Outcome of PromotionService::commit(). A retry of a committed run returns the stored result with $alreadyCommitted. */
final readonly class PromotionResult
{
    /** @param  array<string, int>  $counts */
    public function __construct(
        public PromotionRun $run,
        public array $counts,
        public bool $alreadyCommitted = false,
    ) {}

    public function count(string $key): int
    {
        return (int) ($this->counts[$key] ?? 0);
    }
}
