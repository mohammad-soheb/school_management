<?php

namespace Tests\Feature\Identity;

use App\Models\Audit\AuditEvent;
use App\Models\Identity\Role;
use App\Models\Identity\RolePermission;
use App\Services\Identity\RoleService;
use App\Support\Identity\Permissions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    public function test_a_new_school_gets_the_five_default_roles_with_module_defaults(): void
    {
        $school = $this->makeSchool();

        $this->inSchool($school, function () {
            $roles = Role::query()->get()->keyBy('slug');

            $this->assertEqualsCanonicalizing(['school_admin', 'teacher', 'accountant', 'staff', 'parent'], $roles->keys()->all());
            $this->assertTrue($roles->every(fn (Role $r) => $r->is_system));

            $teacher = $roles['teacher']->permissionNames();
            $this->assertContains('attendance.students.mark', $teacher);
            $this->assertContains('exams.marks.enter', $teacher);
            $this->assertNotContains('fees.payments.post', $teacher, 'Teachers must not touch fees.');
            $this->assertNotContains('students.create', $teacher);

            $accountant = $roles['accountant']->permissionNames();
            $this->assertContains('fees.payments.post', $accountant);
            $this->assertContains('fees.payments.reverse', $accountant);
            $this->assertNotContains('fees.adjustments.approve', $accountant, 'Approving discounts stays with the School Admin.');

            $this->assertSame([], $roles['parent']->permissionNames(), 'Parents hold no staff permissions.');
        });
    }

    public function test_school_admin_holds_every_school_permission_dynamically(): void
    {
        $school = $this->makeSchool();

        $this->inSchool($school, function () {
            $admin = Role::query()->where('slug', 'school_admin')->firstOrFail();

            $this->assertEqualsCanonicalizing(Permissions::allSchool(), $admin->permissionNames());
            $this->assertSame(0, RolePermission::query()->where('role_id', $admin->id)->count(), 'Nothing is stored, so new modules apply automatically.');
        });
    }

    public function test_each_school_has_its_own_roles(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        $idsA = $this->inSchool($a, fn () => Role::query()->pluck('id')->all());
        $idsB = $this->inSchool($b, fn () => Role::query()->pluck('id')->all());

        $this->assertCount(5, $idsA);
        $this->assertCount(5, $idsB);
        $this->assertEmpty(array_intersect($idsA, $idsB));
    }

    public function test_permissions_can_be_replaced_and_the_change_is_audited(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->inSchool($school, function () use ($admin) {
            $role = Role::query()->where('slug', 'teacher')->firstOrFail();
            app(RoleService::class)->syncPermissions($role, ['students.view', 'reports.export'], $admin);

            $this->assertEqualsCanonicalizing(['students.view', 'reports.export'], $role->permissionNames());

            // The audit trail records what was taken away (old) and what was newly granted (new).
            $event = AuditEvent::query()->where('event', 'role.permissions_changed')->firstOrFail();
            $this->assertSame($admin->id, $event->actor_id);
            $this->assertSame(['reports.export'], $event->new['permissions']);
            $this->assertContains('attendance.students.mark', $event->old['permissions']);
            $this->assertNotContains('students.view', $event->old['permissions'], 'Unchanged permissions are not part of the diff.');
        });
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->expectException(ValidationException::class);

        $this->inSchool($school, fn () => app(RoleService::class)->syncPermissions(
            Role::query()->where('slug', 'teacher')->firstOrFail(), ['fees.payments.steal'], $admin,
        ));
    }

    public function test_school_admin_cannot_be_edited(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->expectException(ValidationException::class);

        $this->inSchool($school, fn () => app(RoleService::class)->syncPermissions(
            Role::query()->where('slug', 'school_admin')->firstOrFail(), [], $admin,
        ));
    }

    public function test_built_in_roles_cannot_be_deleted_but_unused_custom_roles_can(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->inSchool($school, function () use ($admin) {
            $service = app(RoleService::class);

            $custom = $service->create(['name' => 'Librarian'], $admin);
            $service->delete($custom, $admin);
            $this->assertNull(Role::query()->find($custom->id));

            try {
                $service->delete(Role::query()->where('slug', 'teacher')->firstOrFail(), $admin);
                $this->fail('A built-in role was deleted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        });
    }

    public function test_grant_missing_defaults_restores_new_default_permissions_without_removing_customisations(): void
    {
        $school = $this->makeSchool();

        $this->inSchool($school, function () use ($school) {
            $teacher = Role::query()->where('slug', 'teacher')->firstOrFail();
            RolePermission::query()->where('role_id', $teacher->id)->where('permission', 'homework.manage')->delete();
            RolePermission::query()->create(['role_id' => $teacher->id, 'permission' => 'reports.export']); // a school customisation

            $added = app(RoleService::class)->grantMissingDefaults($school);

            $this->assertSame(1, $added);
            $this->assertContains('homework.manage', $teacher->permissionNames());
            $this->assertContains('reports.export', $teacher->permissionNames(), 'Customisations must survive.');
        });
    }
}
