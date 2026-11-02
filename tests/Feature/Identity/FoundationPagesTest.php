<?php

namespace Tests\Feature\Identity;

use App\Models\Documents\StoredFile;
use App\Models\Identity\Invitation;
use App\Models\Identity\Role;
use App\Models\Identity\SchoolMembership;
use App\Models\Platform\School;
use App\Services\Documents\PrivateFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssertsTenantIsolation;
use Tests\TestCase;

class FoundationPagesTest extends TestCase
{
    use AssertsTenantIsolation;

    public function test_school_staff_see_the_dashboard_with_only_their_shortcuts(): void
    {
        $school = $this->makeSchool(['name' => 'Sunrise Public School']);
        $admin = $this->makeMember($school, 'school_admin', ['name' => 'Asha Verma']);
        $teacher = $this->makeMember($school, 'teacher');

        $this->signInTo($school, $admin)->get('/app')->assertOk()->assertSee('Asha')->assertSee('Sunrise Public School')->assertSee('Users');
        $this->signInTo($school, $teacher)->get('/app')->assertOk()->assertDontSee('Audit log');
    }

    public function test_home_sends_each_kind_of_user_to_the_right_place(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->actingAs($this->makePlatformAdmin())->get('/')->assertRedirect(route('platform.dashboard'));
        $this->actingAs($admin)->get('/')->assertRedirect(route('school.dashboard'));
        $this->actingAs(\App\Models\User::factory()->create())->get('/')->assertOk()->assertSee('No school linked yet');
    }

    public function test_members_page_lists_only_this_schools_users_and_invites(): void
    {
        Mail::fake();
        $school = $this->makeSchool();
        $other = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $this->makeMember($school, 'teacher', ['name' => 'Visible Teacher']);
        $this->makeMember($other, 'teacher', ['name' => 'Hidden Teacher']);

        $this->signInTo($school, $admin)->get(route('school.access.members.index'))
            ->assertOk()->assertSee('Visible Teacher')->assertDontSee('Hidden Teacher');

        $teacherRole = $this->inSchool($school, fn () => Role::query()->where('slug', 'teacher')->value('id'));
        $this->signInTo($school, $admin)
            ->post(route('school.access.members.invite'), ['email' => 'new@example.com', 'roles' => [$teacherRole]])
            ->assertRedirect()->assertSessionHas('invitation_url');

        $this->assertDatabaseHas('invitations', ['school_id' => $school->id, 'email' => 'new@example.com']);
    }

    public function test_cannot_invite_with_another_schools_role(): void
    {
        $school = $this->makeSchool();
        $other = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $foreignRole = $this->inSchool($other, fn () => Role::query()->where('slug', 'teacher')->value('id'));

        $this->signInTo($school, $admin)
            ->post(route('school.access.members.invite'), ['email' => 'x@example.com', 'roles' => [$foreignRole]])
            ->assertSessionHasErrors('roles.0');
    }

    public function test_roles_pages_and_permission_editing(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $role = $this->inSchool($school, fn () => Role::query()->where('slug', 'teacher')->firstOrFail());

        $this->signInTo($school, $admin)->get(route('school.access.roles.index'))->assertOk()->assertSee('Teacher');
        $this->signInTo($school, $admin)->get(route('school.access.roles.edit', $role))->assertOk()->assertSee('Mark student attendance');
        $this->signInTo($school, $admin)->put(route('school.access.roles.update', $role), ['permissions' => ['students.view']])
            ->assertRedirect(route('school.access.roles.index'));

        $this->assertSame(['students.view'], $this->inSchool($school, fn () => $role->permissionNames()));
    }

    public function test_member_and_role_records_of_another_school_are_unreachable(): void
    {
        $school = $this->makeSchool();
        $this->makeMember($school, 'school_admin');
        $teacher = $this->makeMember($school, 'teacher');
        [$membership, $role] = $this->inSchool($school, fn () => [
            SchoolMembership::query()->where('user_id', $teacher->id)->firstOrFail(),
            Role::query()->where('slug', 'teacher')->firstOrFail(),
        ]);

        $this->assertUnreachableFromOtherSchool([
            ['PUT', route('school.access.members.roles', $membership), ['roles' => [1]]],
            ['POST', route('school.access.members.suspend', $membership)],
            ['GET', route('school.access.roles.edit', $role)],
            ['PUT', route('school.access.roles.update', $role), ['permissions' => []]],
        ]);
    }

    public function test_audit_page_shows_this_schools_events(): void
    {
        Mail::fake();
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $teacherRole = $this->inSchool($school, fn () => Role::query()->where('slug', 'teacher')->value('id'));
        $this->signInTo($school, $admin)->post(route('school.access.members.invite'), ['email' => 'audit@example.com', 'roles' => [$teacherRole]]);

        $this->signInTo($school, $admin)->get(route('school.access.audit.index'))->assertOk()->assertSee('invitation.sent');
        $this->signInTo($school, $admin)->get(route('school.access.audit.index', ['event' => 'nothing.']))->assertOk()->assertSee('No matching events');
    }

    public function test_platform_school_lifecycle(): void
    {
        Mail::fake();
        $super = $this->makePlatformAdmin();

        $this->actingAs($super)->get(route('platform.dashboard'))->assertOk()->assertSee('Platform overview');
        $this->actingAs($super)->get(route('platform.schools.create'))->assertOk();

        $this->actingAs($super)->post(route('platform.schools.store'), [
            'name' => 'Green Valley Academy', 'code' => 'Green Valley', 'admin_email' => 'principal@gva.test', 'city' => 'Indore',
        ])->assertRedirect()->assertSessionHas('invitation_url');

        $school = School::query()->where('code', 'green-valley')->firstOrFail();
        $this->assertSame(5, $this->inSchool($school, fn () => Role::query()->count()));
        $this->assertSame(1, $this->inSchool($school, fn () => Invitation::query()->count()));

        $this->actingAs($super)->get(route('platform.schools.index', ['q' => 'Green']))->assertOk()->assertSee('Green Valley Academy');
        $this->actingAs($super)->get(route('platform.schools.show', $school))->assertOk();
        $this->actingAs($super)->put(route('platform.schools.update', $school), ['name' => 'Green Valley School'])->assertRedirect();
        $this->assertSame('Green Valley School', $school->fresh()->name);

        $this->actingAs($super)->post(route('platform.schools.suspend', $school), [])->assertSessionHasErrors('reason');
        $this->actingAs($super)->post(route('platform.schools.suspend', $school), ['reason' => 'Unpaid'])->assertRedirect();
        $this->assertFalse($school->fresh()->isActive());
        $this->actingAs($super)->post(route('platform.schools.activate', $school))->assertRedirect();
        $this->assertTrue($school->fresh()->isActive());
    }

    public function test_school_code_must_be_unique(): void
    {
        $super = $this->makePlatformAdmin();
        $this->makeSchool(['code' => 'taken']);

        $this->actingAs($super)->post(route('platform.schools.store'), ['name' => 'X', 'code' => 'taken', 'admin_email' => 'a@b.test'])
            ->assertSessionHasErrors('code');
    }

    public function test_account_pages_render_for_every_kind_of_user(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');

        $this->signInTo($school, $admin)->get(route('account.profile'))->assertOk()->assertSee('My profile');
        $this->signInTo($school, $admin)->get(route('account.security'))->assertOk()->assertSee('Two-factor authentication');

        $super = $this->makePlatformAdmin(withTwoFactor: false);
        $this->actingAs($super)->get(route('account.security'))->assertOk()->assertSee('Required for your account');
    }

    public function test_select_school_page_lists_memberships(): void
    {
        $a = $this->makeSchool(['name' => 'Alpha School']);
        $b = $this->makeSchool(['name' => 'Beta School']);
        $user = $this->makeMember($a, 'teacher');
        $this->tenant()->run($b, function () use ($user) {
            SchoolMembership::create(['user_id' => $user->id, 'joined_at' => now()])->syncRoles(Role::query()->where('slug', 'teacher')->pluck('id')->all());
        });

        $this->actingAs($user)->get('/app')->assertRedirect(route('school.select'));
        $this->actingAs($user)->get(route('school.select'))->assertOk()->assertSee('Alpha School')->assertSee('Beta School');
    }

    public function test_invitation_pages_from_link_to_signed_in_member(): void
    {
        Mail::fake();
        $school = $this->makeSchool(['name' => 'Sunrise']);
        $admin = $this->makeMember($school, 'school_admin');
        $teacherRole = $this->inSchool($school, fn () => Role::query()->where('slug', 'teacher')->value('id'));
        $url = $this->signInTo($school, $admin)
            ->post(route('school.access.members.invite'), ['email' => 'invitee@example.com', 'roles' => [$teacherRole]])
            ->getSession()->get('invitation_url');
        auth()->logout();

        $this->get($url)->assertOk()->assertSee('Join Sunrise');
        $this->post($url, ['name' => 'In Vitee', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post($url, ['name' => 'In Vitee', 'password' => 'Good-pass-123', 'password_confirmation' => 'Good-pass-123'])->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->get($url)->assertOk()->assertSee("can't be used");
        $this->get('/invitations/forged-token')->assertOk()->assertSee("can't be used");
    }

    public function test_private_files_are_served_only_to_authorised_users_of_the_same_school(): void
    {
        Storage::fake('private');
        $school = $this->makeSchool();
        $other = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $teacher = $this->makeMember($school, 'teacher');

        $logo = $this->asMember($school, $admin, fn () => app(PrivateFileService::class)->store(UploadedFile::fake()->image('logo.png'), 'school_logo', actor: $admin));
        $import = $this->asMember($school, $admin, fn () => app(PrivateFileService::class)->store(UploadedFile::fake()->createWithContent('students.csv', "admission_no,first_name\nA1,Asha\n"), 'import', actor: $admin));

        $this->assertStringStartsWith("schools/{$school->id}/school_logo/", $logo->path);
        $this->assertStringNotContainsString('logo', basename($logo->path), 'Stored names are random.');

        $this->signInTo($school, $teacher)->get(route('school.files.show', $logo))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->signInTo($school, $teacher)->get(route('school.files.show', $import))->assertForbidden();
        $this->signInTo($school, $admin)->get(route('school.files.show', $import))->assertOk();

        $this->assertUnreachableFromOtherSchool([['GET', route('school.files.show', $logo)]], $other);
    }

    public function test_uploads_are_checked_by_extension_size_and_real_content(): void
    {
        Storage::fake('private');
        $school = $this->makeSchool();
        $admin = $this->makeMember($school, 'school_admin');
        $service = app(PrivateFileService::class);

        $cases = [
            'svg not allowed' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
            'php disguised' => UploadedFile::fake()->createWithContent('photo.png', '<?php echo "x";'),
            'too large' => UploadedFile::fake()->image('big.png')->size(3000),
        ];

        foreach ($cases as $label => $file) {
            try {
                $this->asMember($school, $admin, fn () => $service->store($file, 'school_logo', actor: $admin));
                $this->fail("Upload accepted: {$label}");
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, $this->inSchool($school, fn () => StoredFile::query()->count()));
    }
}
