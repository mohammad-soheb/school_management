<?php

namespace App\Support\Subscriptions;

use App\Contracts\SubscriptionAccessPolicy;
use App\Models\Platform\School;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Default policy: no subscription restrictions. Replaced by the subscriptions module. */
final class AllowAllSubscriptionAccess implements SubscriptionAccessPolicy
{
    public function check(School $school, Request $request): ?Response
    {
        return null;
    }
}
