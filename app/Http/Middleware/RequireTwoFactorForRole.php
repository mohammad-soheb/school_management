<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces two-factor authentication for the role slugs a school lists under security.require_two_factor_for
 * (for example accountants who post and reverse payments). Empty by default: recommended, not forced.
 */
class RequireTwoFactorForRole
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = $this->context->school();
        $membership = $this->context->membership();
        $user = $request->user();

        if ($school !== null && $membership !== null && $user !== null && ! $user->hasConfirmedTwoFactor()) {
            $required = (array) $school->setting('security.require_two_factor_for', []);

            if ($required !== [] && $membership->roles->pluck('slug')->intersect($required)->isNotEmpty()) {
                return redirect()->route('account.security')
                    ->with('warning', 'Your school requires two-factor authentication for your role. Set it up to continue.');
            }
        }

        return $next($request);
    }
}
