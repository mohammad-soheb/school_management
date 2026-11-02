<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Sign-in security: password change and two-factor authentication. The enable / confirm / disable / regenerate
 * actions are Fortify's own endpoints (POST /user/two-factor-authentication etc.); this page renders their state.
 */
class SecurityController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $enabled = $user->two_factor_secret !== null;
        $confirmed = $user->hasConfirmedTwoFactor();
        $pending = $enabled && ! $confirmed;

        // Recovery codes are only shown right after they are created, never on later visits.
        $showCodes = in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true)
            && $user->two_factor_recovery_codes !== null;

        return view('account.security', [
            'user' => $user,
            'twoFactor' => [
                'enabled' => $enabled,
                'confirmed' => $confirmed,
                'pending' => $pending,
                'qrSvg' => $pending ? $user->twoFactorQrCodeSvg() : null,
                'secret' => $pending ? decrypt($user->two_factor_secret) : null,
                'recoveryCodes' => $showCodes ? json_decode(decrypt($user->two_factor_recovery_codes), true) : [],
                'required' => $user->isPlatformAdmin(),
            ],
        ]);
    }
}
