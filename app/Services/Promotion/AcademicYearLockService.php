<?php

namespace App\Services\Promotion;

use App\Models\Promotion\PromotionRun;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\User;
use App\Services\SchoolSetup\AcademicYearService;
use App\Support\Tenancy\TenantContext;

/**
 * Year locking as seen from the rollover (FR-2B07, docs/promotion/ROLLOVER_RULES.md rules L1-L3).
 *
 * The lock itself is owned by school setup (AcademicYearService: school-row mutex, audit "academic_year.locked" /
 * "academic_year.reopened", and "the current year cannot be locked"). This class only adds the rollover rules:
 * when a source year may be locked after a commit, and that holders of promotion.reopen may reopen a year for
 * corrections with a mandatory reason.
 */
class AcademicYearLockService
{
    public function __construct(
        private readonly AcademicYearService $years,
        private readonly TenantContext $context,
    ) {}

    /**
     * L1: lock the run's source year if it is not locked yet and is no longer the current year.
     * Returns true when the year is locked afterwards, false when it had to stay open (it is still the current year).
     */
    public function lockSourceYear(PromotionRun $run, User $actor): bool
    {
        /** @var AcademicYear $year */
        $year = AcademicYear::query()->findOrFail($run->from_academic_year_id);

        if ($year->isLocked()) {
            return true;
        }

        if ($year->is_current) {
            return false;
        }

        $this->years->lock($year, "Closed after year-end promotion run #{$run->getKey()}.", $actor);
        $this->context->forgetMemoized();

        return true;
    }

    /** L2: reopen a locked year. The reason is mandatory and audited by AcademicYearService. */
    public function reopen(AcademicYear $year, string $reason, User $actor): AcademicYear
    {
        $year = $this->years->reopen($year, trim($reason), $actor);
        $this->context->forgetMemoized();

        return $year;
    }
}
