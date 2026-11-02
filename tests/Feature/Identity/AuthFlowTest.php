<?php

namespace Tests\Feature\Identity;

use App\Models\Audit\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    private const PASSWORD = 'correct-Horse-9';

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['password' => self::PASSWORD] + $attributes);
    }

    private function totp(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    }

    public function test_a_user_can_sign_in_and_is_sent_to_the_landing_route(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_sign_ins_and_failures_are_audited_and_last_login_is_recorded(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $this->assertNotNull($user->fresh()->last_login_at);
        $events = $this->unscoped(fn () => AuditEvent::query()->orderBy('id')->get(['event', 'actor_id', 'meta']));
        $this->assertSame(['auth.login_failed', 'auth.login'], $events->pluck('event')->all());
        $this->assertSame($user->email, $events[0]->meta['email']);
        $this->assertSame($user->id, $events[1]->actor_id);
        $this->assertStringNotContainsString('wrong', json_encode($events->toArray()), 'The attempted password must never be logged.');
    }

    public function test_email_matching_ignores_case(): void
    {
        $user = $this->user(['email' => 'teacher@example.com']);

        $this->post('/login', ['email' => 'Teacher@Example.COM', 'password' => self::PASSWORD])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_without_saying_which_part_was_wrong(): void
    {
        $user = $this->user();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_disabled_account_cannot_sign_in(): void
    {
        $user = $this->user(['status' => 'disabled']);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failures_are_throttled(): void
    {
        $user = $this->user();

        foreach (range(1, 5) as $ignored) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_a_user_with_two_factor_must_pass_the_challenge(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => self::PASSWORD]);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->totp($user)])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => self::PASSWORD]);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/two-factor-challenge', ['recovery_code' => 'aaaaaaaaaa-bbbbbbbbbb'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->flushSession();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/two-factor-challenge', ['recovery_code' => 'aaaaaaaaaa-bbbbbbbbbb'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_two_factor_can_be_enabled_confirmed_and_is_audited_free_of_secrets(): void
    {
        $user = $this->user();

        // Fortify asks for the password to be re-confirmed before touching two-factor settings.
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $this->post('/user/two-factor-authentication')->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasConfirmedTwoFactor(), 'Not active until a code from the authenticator is confirmed.');

        $this->post('/user/confirmed-two-factor-authentication', ['code' => '123456'])->assertSessionHasErrors();
        $this->post('/user/confirmed-two-factor-authentication', ['code' => $this->totp($user)])->assertRedirect();

        $this->assertTrue($user->fresh()->hasConfirmedTwoFactor());

        $this->assertStringNotContainsString(
            decrypt($user->fresh()->two_factor_secret),
            json_encode($this->unscoped(fn () => AuditEvent::query()->get()->toArray())),
            'Secrets must never reach the audit log.',
        );
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'Brand-new-pass-77', 'password_confirmation' => 'Brand-new-pass-77',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Brand-new-pass-77', $user->fresh()->password));
        $this->assertTrue($this->unscoped(fn () => AuditEvent::query()->where('event', 'auth.password_reset')->exists()));
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'Another-pass-88', 'password_confirmation' => 'Another-pass-88'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));

        $this->put('/account/password', ['current_password' => self::PASSWORD, 'password' => 'Another-pass-88', 'password_confirmation' => 'Another-pass-88'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Another-pass-88', $user->fresh()->password));
        $this->assertTrue($this->unscoped(fn () => AuditEvent::query()->where('event', 'account.password_changed')->where('actor_id', $user->id)->exists()));
    }

    public function test_the_new_password_must_differ_from_the_old_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/account/password', ['current_password' => self::PASSWORD, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertSessionHasErrors('password');
    }

    public function test_profile_can_be_updated_and_audited(): void
    {
        $user = $this->user(['name' => 'Old Name']);

        $this->actingAs($user)->put('/account/profile', ['name' => 'New Name', 'phone' => '9876543210'])->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('Old Name', $this->unscoped(fn () => AuditEvent::query()->where('event', 'account.profile_updated')->firstOrFail()->old['name']));
    }

    public function test_sign_out_ends_the_session(): void
    {
        $this->actingAs($this->user())->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }
}
