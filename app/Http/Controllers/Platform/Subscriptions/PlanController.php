<?php

namespace App\Http\Controllers\Platform\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Subscriptions\PlanRequest;
use App\Models\Platform\SubscriptionPlan;
use App\Services\Platform\Subscriptions\PlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Super Admin: the plans sold to schools (FR-803). Plans are retired, never deleted. */
class PlanController extends Controller
{
    public function __construct(private readonly PlanService $plans) {}

    public function index(): View
    {
        return view('platform.subscriptions.plans.index', ['plans' => $this->plans->all()]);
    }

    public function create(): View
    {
        return view('platform.subscriptions.plans.create');
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = $this->plans->create($request->planData(), $request->user());

        return redirect()->route('platform.subscriptions.plans.index')->with('status', "Plan \"{$plan->name}\" created.");
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('platform.subscriptions.plans.edit', ['plan' => $plan]);
    }

    public function update(PlanRequest $request, SubscriptionPlan $plan): RedirectResponse
    {
        $this->plans->update($plan, $request->planData(), $request->user());

        return redirect()->route('platform.subscriptions.plans.index')->with('status', "Plan \"{$plan->name}\" updated.");
    }

    /** Create the master-plan §20.1 hypothesis plans that do not exist yet. */
    public function installHypotheses(Request $request): RedirectResponse
    {
        $this->authorize('platform.subscriptions.manage');
        $created = $this->plans->installHypothesisPlans($request->user());

        return redirect()->route('platform.subscriptions.plans.index')->with(
            $created > 0 ? 'status' : 'info',
            $created > 0 ? "{$created} hypothesis plan(s) added. Prices are hypotheses until validated (D10)." : 'All hypothesis plans already exist.',
        );
    }
}
