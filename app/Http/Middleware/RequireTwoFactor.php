<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-factor authentication is mandatory for the Super Admin (master plan §4.1, §19.1). Until a confirmed
 * authenticator is set up the user is sent to the security page, which is outside this middleware's routes.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasConfirmedTwoFactor()) {
            return redirect()->route('account.security')
                ->with('warning', 'Two-factor authentication is required for this area. Set it up to continue.');
        }

        return $next($request);
    }
}
