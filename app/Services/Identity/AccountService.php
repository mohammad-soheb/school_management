<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** A signed-in person managing their own login (profile and password). */
class AccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array{name: string, phone?: ?string}  $data */
    public function updateProfile(User $user, array $data): User
    {
        $user->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null]);
        [$old, $new] = $this->audit->diff($user->getOriginal(), $user->getDirty());
        $user->save();

        if ($new !== []) {
            $this->audit->record('account.profile_updated', $user, $old, $new, actor: $user);
        }

        return $user;
    }

    /**
     * Change the password after proving knowledge of the current one. The new hash also signs out every other
     * browser session (AuthenticateSession compares the password hash stored in the session).
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        $user->forceFill(['password' => $newPassword])->save();

        $this->audit->record('account.password_changed', $user, actor: $user);
    }
}
