<?php

namespace App\Providers\Modules;

use App\Models\Documents\StoredFile;
use App\Models\Identity\Invitation;
use App\Models\Staff\StaffProfile;
use App\Models\Staff\TeacherAssignment;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\StudentGuardian;
use App\Models\Students\StudentStatusChange;
use App\Policies\Staff\StaffProfilePolicy;
use App\Policies\Staff\TeacherAssignmentPolicy;
use App\Policies\Students\GuardianPolicy;
use App\Policies\Students\StudentGuardianPolicy;
use App\Policies\Students\StudentPolicy;
use App\Services\Staff\StaffService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * People module (students, guardians, staff, teacher assignments). Core models are frozen, so their policies and
 * the extra relations this module needs are registered here instead of on the models.
 */
class PeopleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(Guardian::class, GuardianPolicy::class);
        Gate::policy(StudentGuardian::class, StudentGuardianPolicy::class);
        Gate::policy(StaffProfile::class, StaffProfilePolicy::class);
        Gate::policy(TeacherAssignment::class, TeacherAssignmentPolicy::class);

        $this->registerRelations();

        // A staff invitation is matched back to its profile when the invitee accepts (InvitationService::accept
        // runs inside the school's tenant context). Parent invitations carry guardian_id and are skipped.
        Invitation::updated(function (Invitation $invitation): void {
            if ($invitation->wasChanged('accepted_at') && $invitation->accepted_at !== null && $invitation->guardian_id === null) {
                app(StaffService::class)->linkAcceptedInvitation($invitation);
            }
        });
    }

    private function registerRelations(): void
    {
        // Student-guardian link rows (with status/verification), used for nested {guardianLink} route binding.
        Student::resolveRelationUsing('guardianLinks', fn (Student $student) => $student->hasMany(StudentGuardian::class));

        Student::resolveRelationUsing('documents', fn (Student $student) => $student
            ->morphMany(StoredFile::class, 'attachable')->where('purpose', 'student_document'));

        Student::resolveRelationUsing('statusChanges', fn (Student $student) => $student->hasMany(StudentStatusChange::class));

        Guardian::resolveRelationUsing('links', fn (Guardian $guardian) => $guardian->hasMany(StudentGuardian::class));

        StaffProfile::resolveRelationUsing('documents', fn (StaffProfile $staff) => $staff
            ->morphMany(StoredFile::class, 'attachable')->where('purpose', 'staff_document'));
    }
}
