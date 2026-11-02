<?php

namespace Tests\Feature\Identity;

use App\Mail\InvitationMail;
use App\Models\Identity\Invitation;
use App\Models\Identity\Role;
use App\Models\Identity\SchoolMembership;
use App\Models\Students\Guardian;
use App\Models\User;
use App\Services\Identity\InvitationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvitationServiceTest extends TestCase
{
    private function teacherRoleId(): int
    {
        return Role::query()->where('slug', 'teacher')->value('id');
    }

    public function test_an_invitation_stores_only_the_hash_of_its_token_and_emails_the_link(): void
    {
        Mail::fake();
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $result = $this->inSchool($school, fn () => app(InvitationService::class)->invite('New.Teacher@Example.com', [$this->teacherRoleId()], $admin));

        $token = basename(parse_url($result['url'], PHP_URL_PATH));
        $invitation = $result['invitation'];

        $this->assertSame('new.teacher@example.com', $invitation->email, 'Emails are normalised to lower case.');
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('invitations', ['token_hash' => $token]);
        $this->assertTrue($invitation->isPending());
        Mail::assertSent(InvitationMail::class, fn (InvitationMail $m) => $m->hasTo('new.teacher@example.com') && $m->url === $result['url']);
    }

    public function test_a_second_invitation_replaces_the_first(): void
    {
        Mail::fake();
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->inSchool($school, function () use ($admin) {
            $service = app(InvitationService::class);
            $first = $service->invite('a@example.com', [$this->teacherRoleId()], $admin);
            $second = $service->invite('a@example.com', [$this->teacherRoleId()], $admin);

            $this->assertFalse($first['invitation']->fresh()->isPending());
            $this->assertTrue($second['invitation']->fresh()->isPending());
        });
    }

    public function test_cannot_invite_someone_who_already_has_access(): void
    {
        Mail::fake();
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $existing = $this->makeMember($school, 'teacher', ['email' => 'already@example.com']);

        $this->expectException(ValidationException::class);

        $this->inSchool($school, fn () => app(InvitationService::class)->invite('already@example.com', [$this->teacherRoleId()], $admin));
    }

    public function test_roles_from_another_school_are_rejected(): void
    {
        Mail::fake();
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $admin = $this->makeMember($a, 'school_admin');
        $foreignRoleId = $this->inSchool($b, fn () => $this->teacherRoleId());

        $this->expectException(ValidationException::class);

        $this->inSchool($a, fn () => app(InvitationService::class)->invite('x@example.com', [$foreignRoleId], $admin));
    }

    public function test_accepting_creates_a_verified_login_and_a_membership_with_the_invited_roles(): void
    {
        Mail::fake();
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $service = app(InvitationService::class);

        $result = $this->inSchool($school, fn () => $service->invite('fresh@example.com', [$this->teacherRoleId()], $admin));
        $token = basename(parse_url($result['url'], PHP_URL_PATH));

        $invitation = $service->findPending($token);
        $this->assertNotNull($invitation);

        $user = $service->accept($invitation, ['name' => 'Fresh Teacher', 'password' => 'a-strong-Passw0rd!']);

        $this->assertSame('fresh@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame('a-strong-Passw0rd!', $user->password, 'Password must be hashed.');

        $this->asMember($school, $user, function () use ($user) {
            $membership = SchoolMembership::query()->where('user_id', $user->id)->firstOrFail();
            $this->assertSame(['teacher'], $membership->roles->pluck('slug')->all());
            $this->assertTrue($user->can('attendance.students.mark'));
        });

        $this->assertNull($service->findPending($token), 'A token works once.');
    }

    public function test_an_expired_or_wrong_token_is_not_found(): void
    {
        Mail::fake();
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $service = app(InvitationService::class);

        $result = $this->inSchool($school, fn () => $service->invite('late@example.com', [$this->teacherRoleId()], $admin));
        $token = basename(parse_url($result['url'], PHP_URL_PATH));

        $this->assertNull($service->findPending('not-the-token'));

        $this->travel(InvitationService::VALID_DAYS + 1)->days();
        $this->assertNull($service->findPending($token));
    }

    public function test_an_existing_login_can_accept_but_only_with_the_matching_email(): void
    {
        Mail::fake();
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $admin = $this->makeMember($a, 'school_admin');
        $teacherInB = $this->makeMember($b, 'teacher', ['email' => 'shared@example.com']);
        $someoneElse = User::factory()->create();
        $service = app(InvitationService::class);

        $result = $this->inSchool($a, fn () => $service->invite('shared@example.com', [$this->teacherRoleId()], $admin));
        $invitation = $service->findPending(basename(parse_url($result['url'], PHP_URL_PATH)));

        try {
            $service->accept($invitation, [], $someoneElse);
            $this->fail('A different account accepted an invitation meant for another email.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $service->accept($invitation, [], $teacherInB);

        $this->assertSame(2, $this->tenant()->withoutTenant(fn () => SchoolMembership::query()->where('user_id', $teacherInB->id)->count()));
    }

    public function test_a_guardian_invitation_links_the_guardian_profile_to_the_new_login(): void
    {
        Mail::fake();
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $service = app(InvitationService::class);
        $guardian = $this->inSchool($school, fn () => Guardian::factory()->create(['email' => 'parent@example.com']));
        $parentRoleId = $this->inSchool($school, fn () => Role::query()->where('slug', 'parent')->value('id'));

        $result = $this->inSchool($school, fn () => $service->invite('parent@example.com', [$parentRoleId], $admin, $guardian));
        $user = $service->accept($service->findPending(basename(parse_url($result['url'], PHP_URL_PATH))), ['name' => 'Parent One', 'password' => 'a-strong-Passw0rd!']);

        $this->assertSame($user->id, $this->inSchool($school, fn () => $guardian->fresh()->user_id));
    }
}
