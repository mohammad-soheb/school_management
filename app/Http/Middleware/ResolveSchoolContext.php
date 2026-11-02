<?php

namespace App\Http\Middleware;

use App\Enums\Identity\MembershipStatus;
use App\Models\Identity\SchoolMembership;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ONLY place a request's school is decided (master plan §17.2). It never trusts a school id from the browser:
 * the id kept in the session is re-validated against the user's active memberships on every request.
 *
 *  - no remembered school and exactly one membership -> that school is selected;
 *  - several memberships -> the user picks one (school.select);
 *  - remembered school but the membership is gone/suspended, or the school is suspended -> forgotten / blocked.
 */
class ResolveSchoolContext
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  string  $mode  'strict' (default) refuses requests with no valid school; 'soft' carries on without a school
     *                        (account pages, which must also work for the Super Admin and for people between schools)
     */
    public function handle(Request $request, Closure $next, string $mode = 'strict'): Response
    {
        $user = $request->user();
        $session = $request->session();
        $soft = $mode === 'soft';

        // Membership lookups are user-centred, so they run before any school is active.
        $membership = $this->context->withoutTenant(function () use ($user, $session): ?SchoolMembership {
            $query = $user->memberships()
                ->where('status', MembershipStatus::Active->value)
                ->with('school');

            $selected = $session->get('school_id');

            if ($selected !== null) {
                return (clone $query)->where('school_id', $selected)->first();
            }

            $all = $query->limit(2)->get();

            return $all->count() === 1 ? $all->first() : null;
        });

        if ($membership === null) {
            if ($soft) {
                return $next($request);
            }

            $session->forget('school_id');

            $hasAny = $this->context->withoutTenant(
                fn () => $user->activeMemberships()->exists(),
            );

            return $hasAny
                ? redirect()->route('school.select')
                : abort(403, 'Your account is not linked to any school.');
        }

        if ($soft && ! $membership->school->isActive()) {
            return $next($request);
        }

        abort_unless($membership->school->isActive(), 403, 'This school account is not active. Please contact support.');

        $session->put('school_id', $membership->school_id);
        $this->context->set($membership->school, $membership);

        if ($membership->last_active_at === null || $membership->last_active_at->diffInMinutes(now(), true) >= 5) {
            $membership->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
