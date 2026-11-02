<?php

namespace App\Http\Middleware;

use App\Models\Students\Guardian;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The parent portal is only for a login that holds the Parent role in the active school AND is linked to a
 * guardian profile there. Which children it may see is decided per request by the guardian-link checks
 * (student_guardians: active + verified), never by this middleware.
 */
class EnsureGuardianAccount
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $membership = $this->context->membership();

        abort_unless($membership !== null && $membership->isGuardian(), 403, 'The parent portal is for parents and guardians only.');
        abort_unless(Guardian::query()->where('user_id', $request->user()->getKey())->exists(), 403, 'No guardian profile is linked to this account.');

        return $next($request);
    }
}
