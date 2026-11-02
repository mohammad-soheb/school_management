<?php

namespace App\Services\Communication;

use App\Models\Communication\Homework;
use App\Models\Students\StudentEnrollment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Public read API for the parent portal and other modules (FR-507, FR-605): the homework that applies to one
 * enrollment - set for its section or for its whole class, in the enrollment's academic year, active only.
 *
 * Callers are responsible for deciding that the viewer may see this enrollment (e.g. GuardianStudentPolicy);
 * this class only refuses enrollments from another school. Must run inside the enrollment's school context.
 */
class HomeworkFeed
{
    /** How far back "recent" homework (already past its due date) reaches. */
    public const RECENT_DAYS = 14;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Current and upcoming homework (due today or later, soonest first), then recent homework (due in the last
     * RECENT_DAYS days, latest first), at most $limit items. Attachments, subject, class and section are eager loaded.
     *
     * @return Collection<int, Homework>
     */
    public function forEnrollment(StudentEnrollment $enrollment, int $limit = 20): Collection
    {
        if ((int) $enrollment->school_id !== $this->context->requireId() || $limit < 1) {
            return new Collection;
        }

        $today = school_today();
        $base = fn () => Homework::query()
            ->active()
            ->with(['subject', 'schoolClass', 'section', 'attachments'])
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->forSection((int) $enrollment->school_class_id, $enrollment->section_id === null ? null : (int) $enrollment->section_id)
            ->where('assigned_on', '<=', $today->toDateString());

        $upcoming = $base()
            ->where('due_on', '>=', $today->toDateString())
            ->orderBy('due_on')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $recent = $upcoming->count() >= $limit ? collect() : $base()
            ->where('due_on', '<', $today->toDateString())
            ->where('due_on', '>=', $today->subDays(self::RECENT_DAYS)->toDateString())
            ->orderByDesc('due_on')
            ->orderByDesc('id')
            ->limit($limit - $upcoming->count())
            ->get();

        return collect($upcoming->all())->concat($recent->all())->values();
    }
}
