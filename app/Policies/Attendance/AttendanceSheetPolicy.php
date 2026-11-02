<?php

namespace App\Policies\Attendance;

use App\Models\Attendance\AttendanceSheet;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Services\Attendance\AttendanceAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * A submitted register: visible to users who may view its section; changeable only through a correction by a
 * holder of attendance.students.correct who may also view the section. Nobody edits or deletes a sheet directly.
 */
class AttendanceSheetPolicy extends TenantPolicy
{
    protected string $module = 'attendance.students';

    public function viewAny(User $user): bool
    {
        return $user->can('attendance.students.access');
    }

    public function view(User $user, Model $model): bool
    {
        /** @var AttendanceSheet $model */
        return $this->sameSchool($model) && $this->access()->canView($user, (int) $model->section_id);
    }

    public function correct(User $user, AttendanceSheet $sheet): bool
    {
        return $this->sameSchool($sheet) && $this->access()->canCorrect($user, (int) $sheet->section_id);
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.students.mark_any');
    }

    public function update(User $user, Model $model): bool
    {
        /** @var AttendanceSheet $model */
        return $this->correct($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    private function access(): AttendanceAccess
    {
        return app(AttendanceAccess::class);
    }
}
