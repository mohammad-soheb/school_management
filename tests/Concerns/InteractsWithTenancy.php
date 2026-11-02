<?php

namespace Tests\Concerns;

use App\Enums\Identity\MembershipStatus;
use App\Models\Identity\Role;
use App\Models\Identity\SchoolMembership;
use App\Models\Platform\School;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\User;
use App\Services\Identity\RoleService;
use App\Support\Tenancy\TenantContext;
use Closure;

/**
 * Helpers for tests that involve more than one school.
 *
 *   $a = $this->makeSchool();                              // school with default roles seeded
 *   $teacher = $this->makeMember($a, 'teacher');           // user + active membership + role
 *   $this->signInTo($a, $teacher)->get(route('school.dashboard'));
 *   $this->inSchool($a, fn () => Student::factory()->create());   // run code inside school A's tenant context
 *
 * Tenant models fail closed without a school context, so wrap queries in inSchool(), call actAsTenant(), or use
 * assertDatabaseHas() (raw table check) for assertions across schools.
 */
trait InteractsWithTenancy
{
    protected function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function makeSchool(array $attributes = []): School
    {
        $school = School::factory()->create($attributes);
        app(RoleService::class)->seedDefaultRoles($school);

        return $school;
    }

    /**
     * A user with an active membership in $school holding the given role slug(s).
     *
     * @param  string|list<string>  $roles
     * @param  array<string, mixed>  $userAttributes
     */
    protected function makeMember(School $school, string|array $roles = 'school_admin', array $userAttributes = [], MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create($userAttributes);

        $this->tenant()->run($school, function () use ($user, $roles, $status): void {
            $membership = SchoolMembership::create([
                'user_id' => $user->getKey(),
                'status' => $status,
                'joined_at' => now(),
            ]);

            $membership->syncRoles(Role::query()->whereIn('slug', (array) $roles)->pluck('id')->all());
        });

        return $user;
    }

    protected function makePlatformAdmin(bool $withTwoFactor = true): User
    {
        $factory = User::factory()->platformAdmin();

        return ($withTwoFactor ? $factory->withTwoFactor() : $factory)->create();
    }

    /** Sign in as $user with $school as the active school (the server still validates the membership). */
    protected function signInTo(School $school, User $user): static
    {
        return $this->actingAs($user)->withSession(['school_id' => $school->getKey()]);
    }

    /**
     * Run $callback with $school as the active tenant. Use for creating and reading tenant data in tests.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function inSchool(School $school, Closure $callback): mixed
    {
        return $this->tenant()->run($school, $callback);
    }

    /**
     * Run $callback as $user acting inside $school: the tenant context carries that user's membership, so
     * $user->can('students.create') answers for that school's roles.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function asMember(School $school, User $user, Closure $callback): mixed
    {
        $membership = $this->tenant()->withoutTenant(
            fn () => SchoolMembership::query()->where('school_id', $school->getKey())->where('user_id', $user->getKey())->firstOrFail(),
        );

        return $this->tenant()->run($school, $callback, $membership);
    }

    /**
     * Read across every school (or platform-level rows with no school), for assertions only.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function unscoped(Closure $callback): mixed
    {
        return $this->tenant()->withoutTenant($callback);
    }

    /** Make $school the active tenant for the rest of the test (clears itself between tests). */
    protected function actAsTenant(School $school): void
    {
        $this->tenant()->set($school);
    }

    /** The school's current academic year (a year that contains today), created on demand. */
    protected function currentYear(School $school): AcademicYear
    {
        return $this->inSchool($school, fn () => AcademicYear::query()->current()->first()
            ?? AcademicYear::factory()->current()->containingToday()->create());
    }
}
