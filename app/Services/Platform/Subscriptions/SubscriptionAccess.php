<?php

namespace App\Services\Platform\Subscriptions;

use App\Contracts\SubscriptionAccessPolicy;
use App\Enums\Subscriptions\SubscriptionState;
use App\Models\Platform\School;
use App\Models\Platform\SchoolSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The expiry policy (FR-807, decision D12 - recommended default, pending sign-off). Bound over the pass-through
 * policy by SubscriptionServiceProvider; EnsureSubscriptionAccess calls it on every school and parent request.
 *
 *   no subscription row          -> allowed (unmanaged / trial school; nothing to enforce yet)
 *   Active, Expiring soon, Grace -> allowed (the school dashboard widget shows the warning)
 *   Expired                      -> READ-ONLY: GET/HEAD/OPTIONS pass, every other method is sent back with a flash
 *                                   error explaining renewal. Nothing is deleted or hidden.
 *   Suspended (manual)           -> only the subscription page; staff who may see it (billing.view) are redirected
 *                                   there, everyone else (other staff, parents) gets a 403 notice page.
 *
 * Account, logout and school-switching routes live outside the school/parent areas, so they are never blocked;
 * they are listed below as well so the rule survives a route move.
 */
final class SubscriptionAccess implements SubscriptionAccessPolicy
{
    /** Routes that always stay open (renewal path, account, sign-out). */
    private const ALWAYS_OPEN = ['school.subscription.*', 'account.*', 'logout', 'school.select', 'school.switch'];

    /** Writes that only change navigation state, so a read-only (expired) school may still use them. */
    private const NAVIGATION_WRITES = ['parent.children.switch'];

    public function check(School $school, Request $request): ?Response
    {
        $subscription = SchoolSubscription::query()->where('school_id', $school->getKey())->first();

        if ($subscription === null || $request->routeIs(...self::ALWAYS_OPEN)) {
            return null;
        }

        $status = $subscription->status();

        return match ($status->state) {
            SubscriptionState::Active, SubscriptionState::ExpiringSoon, SubscriptionState::Grace => null,
            SubscriptionState::Expired => $this->readOnly($request),
            SubscriptionState::Suspended => $this->suspended($request, $subscription),
        };
    }

    private function readOnly(Request $request): ?Response
    {
        if ($request->isMethodSafe() || $request->routeIs(...self::NAVIGATION_WRITES)) {
            return null;
        }

        $message = 'This school\'s ERP subscription has expired, so the app is read-only. You can still view and export records. '
            .'Ask your school administrator to renew with '.config('subscriptions.renewal_contact').'. No data has been deleted.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->to(url()->previous($this->home($request)))->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']))
            ->with('error', $message);
    }

    private function suspended(Request $request, SchoolSubscription $subscription): Response
    {
        $message = 'This school\'s ERP access is paused by the provider. No data has been deleted. Contact '.config('subscriptions.renewal_contact').' to restore access.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        if ($request->routeIs('school.*') && Route::has('school.subscription.show') && $request->user()?->can('billing.view')) {
            return redirect()->route('school.subscription.show')->with('error', $message);
        }

        return response()->view('school.subscription.notice', ['message' => $message], 403);
    }

    private function home(Request $request): string
    {
        if ($request->routeIs('parent.*') && Route::has('parent.dashboard')) {
            return route('parent.dashboard');
        }

        return route('school.dashboard');
    }
}
