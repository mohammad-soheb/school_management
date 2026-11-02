<?php

namespace App\Http\Controllers\Platform\Subscriptions;

use App\Enums\Subscriptions\SubscriptionState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Subscriptions\AssignSubscriptionRequest;
use App\Http\Requests\Platform\Subscriptions\SuspendSubscriptionRequest;
use App\Http\Requests\Platform\Subscriptions\UpdateSubscriptionRequest;
use App\Models\Platform\School;
use App\Services\Platform\Subscriptions\PlanService;
use App\Services\Platform\Subscriptions\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Super Admin: renewals list and one school's subscription (FR-803, FR-805, FR-806, FR-808). */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PlanService $plans,
    ) {}

    public function index(Request $request): View
    {
        return view('platform.subscriptions.index', [
            'subscriptions' => $this->subscriptions->paginate($request->only(['q', 'state', 'plan_id', 'sort', 'dir'])),
            'counts' => $this->subscriptions->stateCounts(),
            'planOptions' => $this->plans->options(includeRetired: true),
            'stateOptions' => SubscriptionState::options(),
        ]);
    }

    public function show(School $school): View
    {
        $subscription = $this->subscriptions->requireForSchool($school);

        return view('platform.subscriptions.show', [
            'school' => $school,
            'subscription' => $subscription,
            'status' => $subscription->status(),
        ] + $this->subscriptions->history($subscription));
    }

    public function create(School $school): View|RedirectResponse
    {
        if ($this->subscriptions->forSchool($school) !== null) {
            return redirect()->route('platform.subscriptions.schools.edit', $school)->with('info', 'This school already has a subscription.');
        }

        return view('platform.subscriptions.assign', ['school' => $school, 'planOptions' => $this->plans->options()]);
    }

    public function store(AssignSubscriptionRequest $request, School $school): RedirectResponse
    {
        $subscription = $this->subscriptions->assign($school, $request->validated(), $request->user());

        return redirect()->route('platform.schools.show', $school)
            ->with('status', 'Plan assigned. Access runs until '.$subscription->expires_on->format('d M Y').'.');
    }

    public function edit(School $school): View
    {
        $subscription = $this->subscriptions->requireForSchool($school);

        return view('platform.subscriptions.edit', [
            'school' => $school,
            'subscription' => $subscription,
            'planOptions' => $this->plans->options($subscription->subscription_plan_id),
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, School $school): RedirectResponse
    {
        $this->subscriptions->updateTerms($this->subscriptions->requireForSchool($school), $request->validated(), $request->user());

        return redirect()->route('platform.schools.show', $school)->with('status', 'Subscription updated.');
    }

    public function suspend(SuspendSubscriptionRequest $request, School $school): RedirectResponse
    {
        $this->subscriptions->suspend($this->subscriptions->requireForSchool($school), $request->validated('suspension_reason'), $request->user());

        return back()->with('status', 'Subscription suspended. The school now sees only the subscription notice; no data was changed.');
    }

    public function resume(Request $request, School $school): RedirectResponse
    {
        $this->authorize('platform.subscriptions.manage');
        $this->subscriptions->resume($this->subscriptions->requireForSchool($school), $request->user());

        return back()->with('status', 'Subscription resumed.');
    }
}
