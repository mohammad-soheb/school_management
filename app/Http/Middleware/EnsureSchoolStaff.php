<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the staff app (/app) and the parent portal (/parent) apart: a login whose only role in the active school is
 * Parent is never allowed into the staff area, whatever permissions might later be attached to that role.
 */
class EnsureSchoolStaff
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $membership = $this->context->membership();

        $isStaff = $membership !== null && $membership->roles->contains(fn ($role) => $role->slug !== 'parent');

        abort_unless($isStaff, 403, 'This area is for school staff. Parents use the parent portal.');

        return $next($request);
    }
}
