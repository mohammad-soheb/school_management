<?php

namespace App\Http\Controllers\School\Subscription;

use App\Http\Controllers\Controller;
use App\Services\Platform\Subscriptions\SubscriptionService;
use Illuminate\Contracts\View\View;

/** School-facing, read-only: this school's ERP subscription and renewal history (billing.view). */
class SubscriptionController extends Controller
{
    public function __invoke(SubscriptionService $subscriptions): View
    {
        $subscription = $subscriptions->forActiveSchool();

        return view('school.subscription.show', [
            'subscription' => $subscription,
            'status' => $subscription?->status(),
        ] + ($subscription ? $subscriptions->historyForActiveSchool() : ['payments' => collect(), 'events' => collect()]));
    }
}
