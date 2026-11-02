<?php

namespace App\Providers\Modules;

use App\Models\Attendance\StaffAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Models\SchoolSetup\Section;
use App\Models\Staff\StaffProfile;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Services\Attendance\AttendanceAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Attendance module wiring: derived abilities for routes/menus, section-level abilities, and read-only relations on
 * the frozen core models (other modules may use $student->attendanceRecords etc.).
 */
class AttendanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->defineAbilities();
        $this->defineRelations();
    }

    private function defineAbilities(): void
    {
        // Derived abilities (not stored on roles): "holds any of these permissions".
        Gate::define('attendance.students.access', fn (User $user): bool => $user->hasPermission('attendance.students.view')
            || $user->hasPermission('attendance.students.view_all')
            || $user->hasPermission('attendance.students.mark')
            || $user->hasPermission('attendance.students.mark_all'));

        Gate::define('attendance.students.mark_any', fn (User $user): bool => $user->hasPermission('attendance.students.mark')
            || $user->hasPermission('attendance.students.mark_all'));

        Gate::define('attendance.staff.access', fn (User $user): bool => $user->hasPermission('attendance.staff.view')
            || $user->hasPermission('attendance.staff.mark'));

        // Record-level abilities for a section (Section is a core model, so no policy is registered for it here).
        Gate::define('attendance.view-section', fn (User $user, Section $section): bool => app(AttendanceAccess::class)->canView($user, (int) $section->getKey()));
        Gate::define('attendance.mark-section', fn (User $user, Section $section): bool => app(AttendanceAccess::class)->canMark($user, (int) $section->getKey()));
    }

    private function defineRelations(): void
    {
        Student::resolveRelationUsing('attendanceRecords', fn (Student $student) => $student->hasMany(StudentAttendance::class));
        StudentEnrollment::resolveRelationUsing('attendanceRecords', fn (StudentEnrollment $enrollment) => $enrollment->hasMany(StudentAttendance::class, 'student_enrollment_id'));
        StaffProfile::resolveRelationUsing('attendanceRecords', fn (StaffProfile $staff) => $staff->hasMany(StaffAttendance::class));
    }
}
