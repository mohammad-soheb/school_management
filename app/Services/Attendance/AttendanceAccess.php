<?php

namespace App\Services\Attendance;

use App\Models\User;
use App\Support\Identity\AssignmentScope;
use Illuminate\Support\Facades\Gate;

/**
 * Data-level rules for student attendance (FR-303), on top of the module permissions:
 *
 *   view     students.view (assigned sections) | students.view_all (every section)
 *   mark     students.mark (assigned sections) | students.mark_all (every section); marking implies viewing
 *   correct  students.correct, for the sections the user may view
 *
 * Section lists: null = unrestricted, [] = none, [ids] = only these.
 */
class AttendanceAccess
{
    public function __construct(private readonly AssignmentScope $scope) {}

    /** @return list<int>|null */
    public function viewableSectionIds(User $user): ?array
    {
        $gate = Gate::forUser($user);

        if ($gate->any(['attendance.students.view_all', 'attendance.students.mark_all'])) {
            return null;
        }

        if (! $gate->any(['attendance.students.view', 'attendance.students.mark'])) {
            return [];
        }

        return $this->scope->sectionIds($user, 'attendance.students.view_all');
    }

    /** @return list<int>|null */
    public function markableSectionIds(User $user): ?array
    {
        $gate = Gate::forUser($user);

        if ($gate->allows('attendance.students.mark_all')) {
            return null;
        }

        if ($gate->denies('attendance.students.mark')) {
            return [];
        }

        return $this->scope->sectionIds($user, 'attendance.students.mark_all');
    }

    public function canView(User $user, int $sectionId): bool
    {
        $ids = $this->viewableSectionIds($user);

        return $ids === null || in_array($sectionId, $ids, true);
    }

    public function canMark(User $user, int $sectionId): bool
    {
        $ids = $this->markableSectionIds($user);

        return $ids === null || in_array($sectionId, $ids, true);
    }

    public function canCorrect(User $user, int $sectionId): bool
    {
        return Gate::forUser($user)->allows('attendance.students.correct') && $this->canView($user, $sectionId);
    }

    /** May the user skip the back-dating window (still limited to the current academic year)? */
    public function ignoresBackdateWindow(User $user, bool $schoolAllows): bool
    {
        return $schoolAllows && Gate::forUser($user)->allows('attendance.students.mark_all');
    }
}
