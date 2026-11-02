<?php

namespace App\Services\Fees\Data;

use App\Enums\Fees\LineStatus;
use App\Models\Fees\FeeChargeLine;
use Carbon\CarbonImmutable;

/**
 * A charge line with its derived amounts (design §2): due = amount + fines - reductions (approved only),
 * outstanding = due - net allocations. All paise.
 */
final readonly class LineBalance
{
    public function __construct(
        public FeeChargeLine $line,
        public int $amount,
        public int $fines,
        public int $reductions,
        public int $carried,
        public int $allocated,
    ) {}

    public function id(): int
    {
        return (int) $this->line->getKey();
    }

    public function adjusted(): int
    {
        return $this->fines - $this->reductions;
    }

    public function due(): int
    {
        return $this->amount + $this->adjusted();
    }

    public function outstanding(): int
    {
        return $this->due() - $this->allocated;
    }

    public function dueOn(): CarbonImmutable
    {
        return $this->line->due_on;
    }

    public function status(?CarbonImmutable $today = null): LineStatus
    {
        $today ??= school_today();

        return LineStatus::derive($this->outstanding(), $this->allocated, $this->reductions, $this->carried > 0, $this->line->due_on->lt($today));
    }

    /** Days past the due date (0 when not yet due). */
    public function daysPastDue(?CarbonImmutable $today = null): int
    {
        $today ??= school_today();

        return $this->line->due_on->lt($today) ? (int) $this->line->due_on->diffInDays($today) : 0;
    }
}
