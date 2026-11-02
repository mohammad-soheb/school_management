<?php

namespace App\Contracts;

use App\Models\Platform\School;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides whether a school may use the app right now (Active / Grace / Expired / Suspended - master plan FR-805..807).
 *
 * EnsureSubscriptionAccess calls check() on every school/parent request. Return null to let the request through,
 * or a Response (for example a redirect to a "renew your subscription" page, or a 403) to stop it.
 * The subscriptions module replaces the default pass-through binding with a real implementation.
 */
interface SubscriptionAccessPolicy
{
    public function check(School $school, Request $request): ?Response;
}
