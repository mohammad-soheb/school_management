<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Disabled accounts cannot sign in, and the failure message never reveals which part was wrong.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', Str::lower((string) $request->input('email')))->first();

            if ($user !== null && $user->isActive() && Hash::check((string) $request->input('password'), $user->password)) {
                return $user;
            }

            return null;
        });
    }
}
