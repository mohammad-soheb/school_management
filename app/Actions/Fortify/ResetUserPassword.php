<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Validate and reset the user's forgotten password. Changing the hash also signs out every other session
     * (the AuthenticateSession middleware compares the stored hash).
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        $user->forceFill(['password' => $input['password']])->save();

        $this->audit->record('auth.password_reset', $user, actor: $user);
    }
}
