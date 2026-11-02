<?php

namespace App\Policies\Students;

use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * The guardian directory (contact details of parents) is visible with students.guardians.view or .manage.
 * Teachers see the guardians of their own students only through the student profile (StudentPolicy::view).
 */
class GuardianPolicy extends TenantPolicy
{
    protected string $module = 'students.guardians';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('students.guardians.view') || $user->hasPermission('students.guardians.manage');
    }

    public function view(User $user, Model $guardian): bool
    {
        return $this->sameSchool($guardian) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('students.guardians.manage');
    }

    public function update(User $user, Model $guardian): bool
    {
        return $this->sameSchool($guardian) && $user->hasPermission('students.guardians.manage');
    }

    /** Send a parent-portal invitation. */
    public function invite(User $user, Model $guardian): bool
    {
        return $this->update($user, $guardian);
    }

    /** Guardians are kept for history; links are revoked instead. */
    public function delete(User $user, Model $guardian): bool
    {
        return false;
    }
}
