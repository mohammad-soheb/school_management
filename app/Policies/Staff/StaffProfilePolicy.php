<?php

namespace App\Policies\Staff;

use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff profiles. A staff member may always open their own profile; everything else follows the staff.*
 * permissions. Staff are never deleted (status "left" instead).
 */
class StaffProfilePolicy extends TenantPolicy
{
    protected string $module = 'staff';

    public function view(User $user, Model $staff): bool
    {
        if (! $this->sameSchool($staff)) {
            return false;
        }

        return $user->hasPermission('staff.view') || (int) $staff->getAttribute('user_id') === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.create');
    }

    public function update(User $user, Model $staff): bool
    {
        return $this->sameSchool($staff) && $user->hasPermission('staff.update');
    }

    /** Invite a profile without a login: needs both staff.update and the access module's invite permission. */
    public function invite(User $user, Model $staff): bool
    {
        return $this->update($user, $staff) && $user->hasPermission('access.members.invite');
    }

    public function manageAssignments(User $user, Model $staff): bool
    {
        return $this->sameSchool($staff) && $user->hasPermission('staff.assignments.manage');
    }

    public function delete(User $user, Model $staff): bool
    {
        return false;
    }
}
