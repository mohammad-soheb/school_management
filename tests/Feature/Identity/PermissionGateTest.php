<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Permissions are answered per school: the same login can be an admin in one school and a teacher in another. */
class PermissionGateTest extends TestCase
{
    public function test_abilities_follow_the_role_in_the_active_school(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $teacher = $this->makeMember($school, 'teacher');
        $accountant = $this->makeMember($school, 'accountant');

        $this->asMember($school, $admin, function () use ($admin) {
            $this->assertTrue($admin->can('students.create'));
            $this->assertTrue($admin->can('fees.payments.reverse'));
        });

        $this->asMember($school, $teacher, function () use ($teacher) {
            $this->assertTrue($teacher->can('attendance.students.mark'));
            $this->assertFalse($teacher->can('students.create'));
            $this->assertFalse($teacher->can('fees.payments.post'));
        });

        $this->asMember($school, $accountant, function () use ($accountant) {
            $this->assertTrue($accountant->can('fees.payments.post'));
            $this->assertFalse($accountant->can('attendance.students.mark'));
        });
    }

    public function test_the_same_login_has_different_rights_in_each_of_its_schools(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $user = $this->makeMember($a, 'school_admin');
        $this->tenant()->run($b, function () use ($user) {
            $m = \App\Models\Identity\SchoolMembership::create(['user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);
            $m->syncRoles(\App\Models\Identity\Role::query()->where('slug', 'teacher')->pluck('id')->all());
        });

        $this->asMember($a, $user, fn () => $this->assertTrue($user->can('students.create')));
        $this->asMember($b, $user, fn () => $this->assertFalse($user->can('students.create')));
    }

    public function test_a_membership_in_the_context_cannot_be_borrowed_by_another_user(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $stranger = $this->makeMember($this->makeSchool(), 'school_admin');

        $this->asMember($school, $admin, function () use ($stranger) {
            $this->assertFalse($stranger->can('students.create'), 'Only the user who owns the active membership gets its permissions.');
        });
    }

    public function test_nobody_has_school_permissions_without_a_school_context(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->assertFalse($admin->can('students.view'));
    }

    public function test_platform_abilities_belong_to_the_super_admin_only(): void
    {
        $school = $this->makeSchool();
        $schoolAdmin = $this->makeMember($school, 'school_admin');
        $super = $this->makePlatformAdmin();

        $this->assertTrue($super->can('platform.schools.manage'));
        $this->assertFalse($schoolAdmin->can('platform.schools.manage'));

        $this->asMember($school, $schoolAdmin, fn () => $this->assertFalse($schoolAdmin->can('platform.schools.manage')));
        $this->assertFalse($super->can('students.view'), 'The Super Admin has no school permissions by default.');
    }

    public function test_every_catalogue_permission_is_a_registered_gate_ability(): void
    {
        foreach (\App\Support\Identity\Permissions::allAbilities() as $ability) {
            $this->assertTrue(Gate::has($ability), "Gate ability {$ability} is not defined.");
        }
    }
}
