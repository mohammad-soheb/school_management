<?php

namespace App\Policies\Staff;

use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Model;

/** Teacher assignments feed every teacher-scoped rule (students, attendance, marks, homework). */
class TeacherAssignmentPolicy extends TenantPolicy
{
    protected string $module = 'staff';

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.assignments.manage');
    }

    public function update(User $user, Model $assignment): bool
    {
        return $this->sameSchool($assignment) && $user->hasPermission('staff.assignments.manage');
    }

    public function delete(User $user, Model $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
