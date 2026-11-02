<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionOutcome;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\StudentEnrollment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Placement rules shared by drafting, committing and correcting (docs/promotion/ROLLOVER_RULES.md, rules P1-P4). */
final class PromotionPlacement
{
    /** P1: promote -> the class's next class; retain -> the same class; leaving outcomes have no target class. */
    public function targetClassId(PromotionOutcome $outcome, SchoolClass $from): ?int
    {
        return match ($outcome) {
            PromotionOutcome::Promote => $from->next_class_id === null ? null : (int) $from->next_class_id,
            PromotionOutcome::Retain => (int) $from->getKey(),
            default => null,
        };
    }

    /** P0: classes without a next class graduate by default; the rest are promoted. */
    public function defaultOutcome(SchoolClass $from): PromotionOutcome
    {
        return $from->next_class_id === null ? PromotionOutcome::Graduate : PromotionOutcome::Promote;
    }

    /**
     * P2: retained students keep their section; promoted students go to the section with the same name in the next
     * class (A -> A), otherwise the first section. Null when the target class has no sections.
     *
     * @param  Collection<int, Section>  $sections  sections of the target class, in display order
     */
    public function defaultSectionId(Collection $sections, ?Section $current, bool $sameClass): ?int
    {
        if ($sameClass && $current !== null && $sections->contains('id', $current->getKey())) {
            return $current->getKey();
        }

        if ($current !== null) {
            $match = $sections->first(fn (Section $s): bool => strcasecmp($s->name, $current->name) === 0);
            if ($match !== null) {
                return $match->getKey();
            }
        }

        return $sections->first()?->getKey();
    }

    /**
     * P3: left_on of the closed source enrollment. Promote/retain/graduate close on the last day of the source year;
     * transfer/withdraw close today (or the year end if that is earlier). Never before the student joined.
     */
    public function leftOn(PromotionOutcome $outcome, AcademicYear $from, StudentEnrollment $enrollment, CarbonInterface $today): string
    {
        $end = $from->ends_on->toDateString();
        $date = in_array($outcome, [PromotionOutcome::Transfer, PromotionOutcome::Withdraw], true)
            ? min($today->toDateString(), $end)
            : $end;

        $joined = $enrollment->joined_on?->toDateString();

        return $joined !== null && $date < $joined ? $joined : $date;
    }

    /**
     * Sections of the given classes, grouped by class id, in display order.
     *
     * @param  iterable<int|null>  $classIds
     * @return Collection<int, Collection<int, Section>>
     */
    public function sectionsFor(iterable $classIds): Collection
    {
        $ids = collect($classIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Section::query()
            ->whereIn('school_class_id', $ids)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('school_class_id');
    }
}
