<?php

namespace App\Policies\Attendance;

use App\Models\Attendance\StaffAttendance;
use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Model;

/** Staff attendance: staff.view reads it; staff.mark marks and corrects it. Records are never deleted. */
class StaffAttendancePolicy extends TenantPolicy
{
    protected string $module = 'attendance.staff';

    public function viewAny(User $user): bool
    {
        return $user->can('attendance.staff.access');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameSchool($model) && $user->can('attendance.staff.access');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.staff.mark');
    }

    public function correct(User $user, StaffAttendance $record): bool
    {
        return $this->sameSchool($record) && $user->hasPermission('attendance.staff.mark');
    }

    public function update(User $user, Model $model): bool
    {
        /** @var StaffAttendance $model */
        return $this->correct($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
