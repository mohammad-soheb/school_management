<?php

namespace App\Http\Middleware;

use App\Contracts\SubscriptionAccessPolicy;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the school's subscription state (grace period, expiry, suspension) to every school/parent request.
 * The decision itself lives behind SubscriptionAccessPolicy so the subscriptions module can own the rules.
 */
class EnsureSubscriptionAccess
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SubscriptionAccessPolicy $policy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = $this->context->school();

        if ($school !== null && ($response = $this->policy->check($school, $request)) !== null) {
            return $response;
        }

        return $next($request);
    }
}
