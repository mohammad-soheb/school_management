<?php

namespace App\Http\Controllers\Platform\Subscriptions;

use App\Enums\Subscriptions\SubscriptionPaymentMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Subscriptions\RecordPaymentRequest;
use App\Models\Platform\School;
use App\Services\Platform\Subscriptions\RenewalPeriod;
use App\Services\Platform\Subscriptions\RenewalService;
use App\Services\Platform\Subscriptions\SubscriptionService;
use App\Support\Money\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Super Admin: record a verified payment and renew (FR-804, master plan §10.5). */
class PaymentController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly RenewalService $renewals,
    ) {}

    public function create(School $school): View
    {
        $subscription = $this->subscriptions->requireForSchool($school);
        $months = max(1, $subscription->plan->billing_period_months);

        return view('platform.subscriptions.payment', [
            'school' => $school,
            'subscription' => $subscription,
            'status' => $subscription->status(),
            'months' => $months,
            'preview' => RenewalPeriod::extend($subscription->expires_on, school_today(), $months),
            'modeOptions' => SubscriptionPaymentMode::options(),
        ]);
    }

    public function store(RecordPaymentRequest $request, School $school): RedirectResponse
    {
        $payment = $this->renewals->recordPayment($this->subscriptions->requireForSchool($school), $request->paymentData(), $request->user());

        if (! $payment->wasRecentlyCreated) {
            return redirect()->route('platform.schools.show', $school)
                ->with('info', 'This payment was already recorded; nothing was added twice.');
        }

        return redirect()->route('platform.schools.show', $school)->with('status', sprintf(
            'Payment of %s recorded. Access now runs until %s.',
            Money::format($payment->amount_paise),
            $payment->period_ends_on->format('d M Y'),
        ));
    }
}
