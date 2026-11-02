<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Sign-in and two-factor activity in the audit trail (FR-107). Records who and when - never passwords, codes or secrets.
 * Registered in AppServiceProvider::boot().
 */
class RecordAuthEvents
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
            $this->audit->record('auth.login', $user, actor: $user);
        }
    }

    public function failed(Failed $event): void
    {
        $this->audit->record('auth.login_failed', $event->user instanceof User ? $event->user : null, meta: [
            'email' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 255),
        ]);
    }

    public function twoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->record('auth.two_factor_started', $event->user);
    }

    public function twoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->record('auth.two_factor_enabled', $event->user);
    }

    public function twoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->record('auth.two_factor_disabled', $event->user);
    }

    public function twoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->record('auth.two_factor_failed', $event->user);
    }

    public function recoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->record('auth.recovery_codes_regenerated', $event->user);
    }

    public function recoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        $this->record('auth.recovery_code_used', $event->user);
    }

    private function record(string $event, mixed $user): void
    {
        if ($user instanceof User) {
            $this->audit->record($event, $user, actor: $user);
        }
    }
}
