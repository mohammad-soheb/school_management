<?php

namespace App\Services\Communication;

use App\Models\Communication\Notice;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Public read API (FR-507, FR-605): the notices currently showing for a guardian or a staff member - published,
 * past publish_at, not expired, and addressed to them (see NoticeAudienceResolver). Pinned first, then newest.
 * Attachments and audiences are eager loaded. Must run inside the school's tenant context.
 */
class NoticeFeed
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly NoticeAudienceResolver $resolver,
    ) {}

    /**
     * Notices for the guardian of these children: whole school, all parents, or one of the children's classes.
     * Callers pass only enrollments the guardian may see (verified, active links); enrollments of another school
     * are ignored.
     *
     * @param  iterable<StudentEnrollment>  $enrollments
     * @return Collection<int, Notice>
     */
    public function forGuardianStudents(iterable $enrollments, int $limit = 20): Collection
    {
        $schoolId = $this->context->requireId();
        $classIds = [];

        foreach ($enrollments as $enrollment) {
            if ((int) $enrollment->school_id === $schoolId) {
                $classIds[] = (int) $enrollment->school_class_id;
            }
        }

        return $this->query($limit)
            ->tap(fn (Builder $q) => $this->resolver->scopeForGuardians($q, array_values(array_unique($classIds))))
            ->get()
            ->toBase();
    }

    /**
     * Notices for a staff member: whole school, all staff, one of their roles, or a class they teach this year.
     *
     * @return Collection<int, Notice>
     */
    public function forStaff(User $user, int $limit = 20): Collection
    {
        return $this->query($limit)
            ->tap(fn (Builder $q) => $this->resolver->scopeForStaff($q, $user))
            ->get()
            ->toBase();
    }

    /** @return Builder<Notice> */
    private function query(int $limit): Builder
    {
        return Notice::query()
            ->visibleNow()
            ->with(['attachments', 'audiences.schoolClass', 'audiences.role'])
            ->boardOrder()
            ->limit(max(1, $limit));
    }
}
