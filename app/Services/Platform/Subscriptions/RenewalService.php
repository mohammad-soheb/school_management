<?php

namespace App\Services\Platform\Subscriptions;

use App\Enums\Subscriptions\RenewalEventType;
use App\Enums\Subscriptions\SubscriptionPaymentMode;
use App\Models\Platform\SchoolSubscription;
use App\Models\Platform\SubscriptionPayment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a verified subscription payment and renews the subscription in ONE transaction (master plan §10.5,
 * FR-804). The payment is verified outside the ERP (no gateway); the Super Admin records what was received.
 *
 *  - Idempotent: the form's idempotency_key is unique; a double click / retry returns the first payment
 *    ($payment->wasRecentlyCreated === false) and extends nothing a second time.
 *  - The subscription row is locked, then the new period is computed by RenewalPeriod::extend() from
 *    max(today, current expiry), so early renewals lose no paid days and late ones are not back-dated.
 *  - A renewal-history line and an audit event (with the school's id) are written.
 *  - A manual suspension is NOT lifted by a payment: resuming is a separate, deliberate action.
 */
class RenewalService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{amount_paise: int, paid_on: string, mode: string|SubscriptionPaymentMode, reference?: ?string, months: int, notes?: ?string, idempotency_key: string}  $data
     */
    public function recordPayment(SchoolSubscription $subscription, array $data, User $actor, ?CarbonImmutable $today = null): SubscriptionPayment
    {
        $key = (string) $data['idempotency_key'];

        if (($existing = $this->existing($key, $subscription)) !== null) {
            return $existing;
        }

        $today ??= school_today();
        $months = (int) $data['months'];
        $paidOn = CarbonImmutable::parse($data['paid_on']);

        if ($paidOn->toDateString() > $today->toDateString()) {
            throw ValidationException::withMessages(['paid_on' => 'The payment date cannot be in the future.']);
        }

        try {
            return DB::transaction(function () use ($subscription, $data, $actor, $today, $months, $paidOn, $key): SubscriptionPayment {
                /** @var SchoolSubscription $locked */
                $locked = SchoolSubscription::query()->with('plan')->lockForUpdate()->findOrFail($subscription->getKey());

                // Re-check under the lock: a concurrent request with the same key may have committed meanwhile.
                if (($existing = $this->existing($key, $locked)) !== null) {
                    return $existing;
                }

                $previousExpiry = $locked->expires_on;
                $period = RenewalPeriod::extend($previousExpiry, $today, $months);

                $payment = new SubscriptionPayment([
                    'amount_paise' => (int) $data['amount_paise'],
                    'expected_paise' => $locked->plan->priceForMonths($months),
                    'paid_on' => $paidOn->toDateString(),
                    'mode' => $data['mode'],
                    'reference' => $data['reference'] ?? null,
                    'months' => $months,
                    'period_starts_on' => $period->startsOn->toDateString(),
                    'period_ends_on' => $period->endsOn->toDateString(),
                    'notes' => $data['notes'] ?? null,
                    'idempotency_key' => $key,
                ]);
                $payment->forceFill([
                    'school_id' => $locked->school_id,
                    'school_subscription_id' => $locked->getKey(),
                    'subscription_plan_id' => $locked->subscription_plan_id,
                    'recorded_by' => $actor->getKey(),
                ]);
                $payment->save();

                $locked->forceFill(['expires_on' => $period->endsOn->toDateString()]);
                $this->subscriptions->rememberState($locked);
                $locked->save();

                $this->subscriptions->event($locked, RenewalEventType::Renewed, $actor, [
                    'new_plan_id' => $locked->subscription_plan_id,
                    'previous_expires_on' => $previousExpiry,
                    'new_expires_on' => $period->endsOn,
                ], $payment);

                $this->audit->record('subscription.renewed', $locked,
                    ['expires_on' => $previousExpiry->toDateString()],
                    [
                        'expires_on' => $period->endsOn->toDateString(),
                        'amount_paise' => $payment->amount_paise,
                        'expected_paise' => $payment->expected_paise,
                        'months' => $months,
                        'mode' => $payment->mode->value,
                        'reference' => $payment->reference,
                        'paid_on' => $payment->paid_on->toDateString(),
                    ],
                    meta: ['payment_id' => $payment->getKey(), 'plan' => $locked->plan->name],
                    actor: $actor,
                    schoolId: $locked->school_id,
                );

                return $payment;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two requests with the same key raced past both checks: the loser returns the winner's payment.
            return $this->existing($key, $subscription) ?? throw $e;
        }
    }

    /** A payment already recorded with this key. A key reused for ANOTHER school is rejected, never returned. */
    private function existing(string $key, SchoolSubscription $subscription): ?SubscriptionPayment
    {
        $payment = SubscriptionPayment::query()->where('idempotency_key', $key)->first();

        if ($payment !== null && $payment->school_subscription_id !== $subscription->getKey()) {
            throw ValidationException::withMessages(['idempotency_key' => 'This form was already used for another school. Reload the page and try again.']);
        }

        return $payment;
    }
}
