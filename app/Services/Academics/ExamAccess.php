<?php

namespace App\Services\Academics;

use App\Models\Academics\ExamSubject;
use App\Models\User;
use App\Support\Identity\AssignmentScope;
use Illuminate\Support\Facades\Gate;

/**
 * Data-level rules for exams (on top of the route / policy permission checks):
 *
 *   exams.view            results and report cards of the teacher's assigned sections only
 *   exams.view_all        every section
 *   exams.marks.enter     marks of the subjects the teacher teaches in a section (AssignmentScope::subjectIds),
 *                         remarks of the sections the teacher is assigned to
 *   exams.marks.enter_all every section and subject
 *
 * Assignments are read for the school's CURRENT academic year (AssignmentScope), so a teacher without _all
 * permissions cannot enter marks for an exam of another year.
 */
class ExamAccess
{
    public function __construct(private readonly AssignmentScope $scope) {}

    /** Holds any exams permission (menu, index, exam overview). */
    public function canUseExams(User $user): bool
    {
        foreach (['exams.view', 'exams.view_all', 'exams.manage', 'exams.marks.enter', 'exams.marks.enter_all', 'exams.results.publish', 'exams.report_cards.print'] as $permission) {
            if (Gate::forUser($user)->allows($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canEnterAnyMarks(User $user): bool
    {
        return Gate::forUser($user)->allows('exams.marks.enter') || Gate::forUser($user)->allows('exams.marks.enter_all');
    }

    /**
     * Sections whose results / report cards the user may see. null = every section.
     *
     * @return list<int>|null
     */
    public function viewableSectionIds(User $user): ?array
    {
        if (Gate::forUser($user)->allows('exams.view_all') || Gate::forUser($user)->allows('exams.marks.enter_all') || Gate::forUser($user)->allows('exams.manage')) {
            return null;
        }

        return $this->scope->sectionIds($user, 'exams.view_all');
    }

    public function canViewSection(User $user, int $sectionId): bool
    {
        $ids = $this->viewableSectionIds($user);

        return $ids === null || in_array($sectionId, $ids, true);
    }

    /** @return list<int>|null section ids where the user may enter marks or remarks; null = every section */
    public function markableSectionIds(User $user): ?array
    {
        if (Gate::forUser($user)->allows('exams.marks.enter_all')) {
            return null;
        }

        if (! Gate::forUser($user)->allows('exams.marks.enter')) {
            return [];
        }

        return $this->scope->sectionIds($user, 'exams.marks.enter_all');
    }

    /** @return list<int>|null subject ids the user may enter in the section; null = every subject */
    public function markableSubjectIds(User $user, int $sectionId): ?array
    {
        if (Gate::forUser($user)->allows('exams.marks.enter_all')) {
            return null;
        }

        if (! Gate::forUser($user)->allows('exams.marks.enter')) {
            return [];
        }

        return $this->scope->subjectIds($user, $sectionId, 'exams.marks.enter_all');
    }

    public function canEnterMarks(User $user, int $sectionId, ExamSubject $examSubject): bool
    {
        $subjects = $this->markableSubjectIds($user, $sectionId);

        return $subjects === null || in_array((int) $examSubject->subject_id, $subjects, true);
    }

    public function canEnterRemarks(User $user, int $sectionId): bool
    {
        $sections = $this->markableSectionIds($user);

        return $sections === null || in_array($sectionId, $sections, true);
    }
}
