<?php

namespace Tests\Feature\Identity;

use App\Models\Audit\AuditEvent;
use App\Models\Identity\Role;
use App\Models\Identity\SchoolMembership;
use App\Services\Identity\MembershipService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipServiceTest extends TestCase
{
    private function membershipOf($school, $user): SchoolMembership
    {
        // Roles are tenant data, so they are loaded here while the school context is active.
        return $this->inSchool($school, fn () => SchoolMembership::query()->with('roles')->where('user_id', $user->id)->firstOrFail());
    }

    private function roleId($school, string $slug): int
    {
        return $this->inSchool($school, fn () => Role::query()->where('slug', $slug)->value('id'));
    }

    public function test_roles_can_be_changed_and_the_change_is_audited(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $teacher = $this->makeMember($school, 'teacher');

        $this->inSchool($school, function () use ($school, $admin, $teacher) {
            app(MembershipService::class)->updateRoles(
                $this->membershipOf($school, $teacher),
                [$this->roleId($school, 'accountant'), $this->roleId($school, 'teacher')],
                $admin,
            );

            $roles = $this->membershipOf($school, $teacher)->roles->pluck('slug')->all();
            $this->assertEqualsCanonicalizing(['accountant', 'teacher'], $roles);
            $this->assertTrue(AuditEvent::query()->where('event', 'membership.roles_changed')->where('actor_id', $admin->id)->exists());
        });
    }

    public function test_the_last_school_admin_cannot_be_demoted(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        try {
            $this->inSchool($school, fn () => app(MembershipService::class)->updateRoles(
                $this->membershipOf($school, $admin), [$this->roleId($school, 'teacher')], $admin,
            ));
            $this->fail('The only School Admin lost the role.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('member', $e->errors());
        }

        $this->assertSame(['school_admin'], $this->membershipOf($school, $admin)->roles->pluck('slug')->all(), 'The failed change must roll back.');
    }

    public function test_an_admin_can_be_demoted_when_another_admin_remains(): void
    {
        $school = $this->makeSchool();
        $first = $this->makeMember($school, 'school_admin');
        $second = $this->makeMember($school, 'school_admin');

        $this->inSchool($school, fn () => app(MembershipService::class)->updateRoles(
            $this->membershipOf($school, $first), [$this->roleId($school, 'staff')], $second,
        ));

        $this->assertSame(['staff'], $this->membershipOf($school, $first)->roles->pluck('slug')->all());
    }

    public function test_at_least_one_role_is_required(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $teacher = $this->makeMember($school, 'teacher');

        $this->expectException(ValidationException::class);

        $this->inSchool($school, fn () => app(MembershipService::class)->updateRoles($this->membershipOf($school, $teacher), [], $admin));
    }

    public function test_you_cannot_suspend_yourself(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $this->makeMember($school, 'school_admin'); // a second admin so only the self-check can fire

        $this->expectException(ValidationException::class);

        $this->inSchool($school, fn () => app(MembershipService::class)->suspend($this->membershipOf($school, $admin), $admin));
    }

    public function test_the_last_active_admin_cannot_be_suspended_by_anyone_else(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $accountant = $this->makeMember($school, 'accountant');

        $this->expectException(ValidationException::class);

        $this->inSchool($school, fn () => app(MembershipService::class)->suspend($this->membershipOf($school, $admin), $accountant));
    }

    public function test_a_suspended_member_loses_access_and_can_be_restored(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $teacher = $this->makeMember($school, 'teacher');

        $this->inSchool($school, function () use ($school, $admin, $teacher) {
            $service = app(MembershipService::class);

            $service->suspend($this->membershipOf($school, $teacher), $admin);
            $this->assertSame('suspended', $this->membershipOf($school, $teacher)->status->value);

            $service->activate($this->membershipOf($school, $teacher), $admin);
            $this->assertSame('active', $this->membershipOf($school, $teacher)->status->value);
        });
    }

    public function test_search_only_returns_members_of_the_active_school(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $this->makeMember($a, 'teacher', ['name' => 'Priya Sharma']);
        $this->makeMember($b, 'teacher', ['name' => 'Priya Verma']);

        $names = $this->inSchool($a, fn () => app(MembershipService::class)->paginate('Priya')->map(fn ($m) => $m->user->name)->all());

        $this->assertSame(['Priya Sharma'], $names);
    }
}
