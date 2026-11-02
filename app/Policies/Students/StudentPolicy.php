<?php

namespace App\Policies\Students;

use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Support\Identity\AssignmentScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may see and change a student.
 *
 *  - students.view_all (office roles, School Admin): every student of the school.
 *  - students.view alone (teachers): only students whose CURRENT-year enrollment is in a section the teacher is
 *    assigned to (teacher_assignments, via AssignmentScope). No current enrollment = not visible.
 *  - every write also requires being able to view the student, so a narrowly-scoped custom role cannot edit
 *    records it cannot see. Students are never deleted (status changes instead).
 *
 * Registered for the core Student model in PeopleServiceProvider. StoredFilePolicy delegates student photo and
 * document downloads to view().
 */
class StudentPolicy extends TenantPolicy
{
    public const VIEW_ALL = 'students.view_all';

    protected string $module = 'students';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('students.view') || $user->hasPermission(self::VIEW_ALL);
    }

    public function view(User $user, Model $student): bool
    {
        if (! $this->sameSchool($student) || ! $this->viewAny($user)) {
            return false;
        }

        if ($user->hasPermission(self::VIEW_ALL)) {
            return true;
        }

        return app(AssignmentScope::class)->canAccessSection($user, $this->currentSectionId($student), self::VIEW_ALL);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('students.create');
    }

    public function update(User $user, Model $student): bool
    {
        return $user->hasPermission('students.update') && $this->view($user, $student);
    }

    public function changeStatus(User $user, Student $student): bool
    {
        return $user->hasPermission('students.status') && $this->view($user, $student);
    }

    public function manageGuardians(User $user, Student $student): bool
    {
        return $user->hasPermission('students.guardians.manage') && $this->view($user, $student);
    }

    public function viewDocuments(User $user, Student $student): bool
    {
        return $user->hasPermission('students.documents.view') && $this->view($user, $student);
    }

    public function uploadDocuments(User $user, Student $student): bool
    {
        return $user->hasPermission('students.documents.upload') && $this->view($user, $student);
    }

    /** Students are never deleted (CLAUDE.md rule 5): withdraw or transfer them instead. */
    public function delete(User $user, Model $student): bool
    {
        return false;
    }

    private function currentSectionId(Model $student): ?int
    {
        if ($student->relationLoaded('currentEnrollment')) {
            $sectionId = $student->getRelation('currentEnrollment')?->section_id;

            return $sectionId === null ? null : (int) $sectionId;
        }

        $yearId = app(TenantContext::class)->academicYear()?->getKey();
        if ($yearId === null) {
            return null;
        }

        $sectionId = StudentEnrollment::query()
            ->where('student_id', $student->getKey())
            ->where('academic_year_id', $yearId)
            ->value('section_id');

        return $sectionId === null ? null : (int) $sectionId;
    }
}
