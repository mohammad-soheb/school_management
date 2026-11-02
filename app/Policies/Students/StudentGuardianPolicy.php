<?php

namespace App\Policies\Students;

use App\Models\Students\Student;
use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * A student-guardian link (relation, primary/emergency flags, verification, revocation). Changing a link needs
 * students.guardians.manage AND the right to view the student it belongs to.
 */
class StudentGuardianPolicy extends TenantPolicy
{
    protected string $module = 'students.guardians';

    public function view(User $user, Model $link): bool
    {
        return $this->sameSchool($link) && $this->canSeeStudent($user, $link);
    }

    public function update(User $user, Model $link): bool
    {
        return $this->sameSchool($link)
            && $user->hasPermission('students.guardians.manage')
            && $this->canSeeStudent($user, $link);
    }

    public function verify(User $user, Model $link): bool
    {
        return $this->update($user, $link);
    }

    public function revoke(User $user, Model $link): bool
    {
        return $this->update($user, $link);
    }

    /** Links are revoked, never deleted: the history of who could see a child must survive. */
    public function delete(User $user, Model $link): bool
    {
        return false;
    }

    private function canSeeStudent(User $user, Model $link): bool
    {
        $student = $link->relationLoaded('student') ? $link->getRelation('student') : Student::query()->find($link->getAttribute('student_id'));

        return $student !== null && Gate::forUser($user)->allows('view', $student);
    }
}
