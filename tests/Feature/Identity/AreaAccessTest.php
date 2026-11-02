<?php

namespace Tests\Feature\Identity;

use App\Enums\Identity\MembershipStatus;
use App\Models\User;
use Tests\TestCase;

/** Who can reach which of the three areas (platform, school, parent) - decided by middleware before any controller runs. */
class AreaAccessTest extends TestCase
{
    public function test_guests_are_sent_to_sign_in_for_every_area(): void
    {
        foreach (['/app', '/app/access/members', '/platform', '/platform/schools', '/account/security', '/select-school'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_a_login_with_no_school_gets_403_in_the_school_area(): void
    {
        $this->actingAs(User::factory()->create())->get('/app')->assertForbidden();
    }

    public function test_school_staff_cannot_open_the_platform_backoffice(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->signInTo($school, $admin);
        $this->get('/platform')->assertForbidden();
        $this->get('/platform/schools')->assertForbidden();
        $this->post('/platform/schools', ['name' => 'Sneaky School'])->assertForbidden();
    }

    public function test_the_super_admin_must_set_up_two_factor_before_using_the_backoffice(): void
    {
        $super = $this->makePlatformAdmin(withTwoFactor: false);

        $this->actingAs($super)->get('/platform')->assertRedirect(route('account.security'));
        $this->actingAs($super)->get('/platform/schools')->assertRedirect(route('account.security'));
    }

    public function test_the_super_admin_has_no_school_data_access(): void
    {
        $this->makeSchool();
        $super = $this->makePlatformAdmin();

        $this->actingAs($super)->get('/app')->assertForbidden();
    }

    public function test_a_parent_only_login_is_kept_out_of_the_staff_area(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeMember($school, 'parent');

        $this->signInTo($school, $parent);
        $this->get('/app')->assertForbidden();
        $this->get('/app/access/members')->assertForbidden();
    }

    public function test_a_teacher_cannot_open_screens_that_need_other_permissions(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeMember($school, 'teacher');

        $this->signInTo($school, $teacher);
        $this->get('/app/access/members')->assertForbidden();
        $this->get('/app/access/roles')->assertForbidden();
        $this->get('/app/access/audit')->assertForbidden();
        $this->post('/app/access/members/invite', ['email' => 'x@example.com', 'roles' => [1]])->assertForbidden();
    }

    public function test_a_browser_supplied_school_id_the_user_does_not_belong_to_is_refused(): void
    {
        $mine = $this->makeSchool();
        $other = $this->makeSchool();
        $admin = $this->makeMember($mine, 'school_admin');

        // The session claims school "other"; the server re-checks the membership and refuses.
        $this->actingAs($admin)->withSession(['school_id' => $other->id])->get('/app/access/members')
            ->assertRedirect(route('school.select'))
            ->assertSessionMissing('school_id');
    }

    public function test_switching_to_a_school_without_membership_is_rejected(): void
    {
        $mine = $this->makeSchool();
        $other = $this->makeSchool();
        $admin = $this->makeMember($mine, 'school_admin');

        $this->signInTo($mine, $admin)
            ->post('/select-school', ['school_id' => $other->id])
            ->assertSessionHasErrors('school_id');

        $this->assertSame($mine->id, session('school_id'));
    }

    public function test_switching_between_own_schools_works(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $user = $this->makeMember($a, 'school_admin');
        $this->tenant()->run($b, function () use ($user) {
            $m = \App\Models\Identity\SchoolMembership::create(['user_id' => $user->id, 'joined_at' => now()]);
            $m->syncRoles(\App\Models\Identity\Role::query()->where('slug', 'teacher')->pluck('id')->all());
        });

        $this->signInTo($a, $user)->post('/select-school', ['school_id' => $b->id])->assertRedirect(route('home'));

        $this->assertSame($b->id, session('school_id'));
    }

    public function test_a_suspended_membership_loses_access(): void
    {
        $school = $this->makeSchool();
        $this->makeMember($school, 'school_admin'); // keeps the school valid
        $suspended = $this->makeMember($school, 'teacher', status: MembershipStatus::Suspended);

        $this->signInTo($school, $suspended)->get('/app')->assertForbidden();
    }

    public function test_a_suspended_school_is_blocked(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $school->forceFill(['status' => \App\Enums\Platform\SchoolStatus::Suspended])->save();

        $this->signInTo($school, $admin)->get('/app')->assertForbidden();
    }

    public function test_a_disabled_account_is_signed_out(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeMember($school, 'school_admin');
        $user->forceFill(['status' => 'disabled'])->save();

        $this->signInTo($school, $user)->get('/app')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_school_that_requires_two_factor_for_a_role_enforces_it(): void
    {
        $school = $this->makeSchool(['settings' => ['security' => ['require_two_factor_for' => ['accountant']]]]);
        $accountant = $this->makeMember($school, 'accountant');
        $teacher = $this->makeMember($school, 'teacher');

        $this->signInTo($school, $accountant)->get('/app')->assertRedirect(route('account.security'));
        $this->signInTo($school, $teacher)->get('/app')->assertOk();
    }

    public function test_security_headers_are_sent_and_signed_in_pages_are_not_cached(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $response = $this->signInTo($school, $admin)->get('/app');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }
}
