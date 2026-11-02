<?php

namespace App\Support\Identity;

use App\Models\Staff\TeacherAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/**
 * Data-level scoping for teachers: a permission such as "attendance.students.view" only reaches the sections
 * the teacher is assigned to, while its "..._all" sibling (or being School Admin) reaches every section.
 *
 *   $sections = $scope->sectionIds($user, 'attendance.students.view_all');
 *   // null  -> unrestricted (do not filter)
 *   // [3,7] -> restrict to these section ids (an empty array means "none")
 */
final class AssignmentScope
{
    /** @var array<string, list<int>> */
    private array $memo = [];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return list<int>|null null when the user holds $unrestrictedPermission
     */
    public function sectionIds(User $user, string $unrestrictedPermission): ?array
    {
        if (Gate::forUser($user)->allows($unrestrictedPermission)) {
            return null;
        }

        $yearId = $this->context->academicYear()?->getKey();
        $key = $this->context->id().':'.$user->getKey().':'.$yearId;

        return $this->memo[$key] ??= TeacherAssignment::query()
            ->where('academic_year_id', $yearId)
            ->whereHas('staffProfile', fn ($q) => $q->where('user_id', $user->getKey()))
            ->whereNotNull('section_id')
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function canAccessSection(User $user, ?int $sectionId, string $unrestrictedPermission): bool
    {
        $allowed = $this->sectionIds($user, $unrestrictedPermission);

        return $allowed === null || ($sectionId !== null && in_array($sectionId, $allowed, true));
    }

    /**
     * Subject ids the teacher teaches in a section (null = unrestricted), for marks entry and homework.
     *
     * @return list<int>|null
     */
    public function subjectIds(User $user, int $sectionId, string $unrestrictedPermission): ?array
    {
        if (Gate::forUser($user)->allows($unrestrictedPermission)) {
            return null;
        }

        return TeacherAssignment::query()
            ->where('academic_year_id', $this->context->academicYear()?->getKey())
            ->where('section_id', $sectionId)
            ->whereNotNull('subject_id')
            ->whereHas('staffProfile', fn ($q) => $q->where('user_id', $user->getKey()))
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
