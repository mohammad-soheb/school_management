<?php

namespace App\Services\Platform\Subscriptions;

use App\Enums\Subscriptions\RenewalEventType;
use App\Enums\Subscriptions\SubscriptionState;
use App\Models\Platform\RenewalEvent;
use App\Models\Platform\School;
use App\Models\Platform\SchoolSubscription;
use App\Models\Platform\SubscriptionPayment;
use App\Models\Platform\SubscriptionPlan;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A school's ERP subscription (FR-803, FR-805, FR-806, FR-808): assigning a plan, adjusting its terms, manual
 * suspension, and the read models for the backoffice lists, the platform dashboard and the school-facing page.
 * Recording a payment / renewing lives in RenewalService. Every change writes a renewal_events row and an audit
 * event carrying the school's id. Nothing here touches the school's own student-fee ledger (FR-809).
 */
class SubscriptionService
{
    /** Sortable columns of the renewals list (whitelist). */
    public const SORTS = ['expires_on' => 'school_subscriptions.expires_on', 'school' => 'schools.name', 'starts_on' => 'school_subscriptions.starts_on'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {}

    // ------------------------------------------------------------------ reads

    public function forSchool(School $school): ?SchoolSubscription
    {
        return SchoolSubscription::query()
            ->with(['plan', 'latestPayment', 'suspendedBy'])
            ->where('school_id', $school->getKey())
            ->first();
    }

    /** @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the school has no subscription (404) */
    public function requireForSchool(School $school): SchoolSubscription
    {
        return $this->forSchool($school) ?? throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(SchoolSubscription::class);
    }

    /** School-facing: the ACTIVE school's subscription (explicit school filter; these are platform-owned rows). */
    public function forActiveSchool(): ?SchoolSubscription
    {
        return SchoolSubscription::query()
            ->with(['plan', 'latestPayment'])
            ->where('school_id', $this->context->requireId())
            ->first();
    }

    /**
     * School-facing renewal history of the ACTIVE school.
     *
     * @return array{payments: Collection<int, SubscriptionPayment>, events: Collection<int, RenewalEvent>}
     */
    public function historyForActiveSchool(int $limit = 24): array
    {
        $schoolId = $this->context->requireId();

        return [
            'payments' => SubscriptionPayment::query()->with('plan')->where('school_id', $schoolId)
                ->latest('paid_on')->latest('id')->limit($limit)->get(),
            'events' => RenewalEvent::query()->with(['newPlan', 'previousPlan'])->where('school_id', $schoolId)
                ->latest('created_at')->latest('id')->limit($limit)->get(),
        ];
    }

    /**
     * Full history of one subscription for the backoffice.
     *
     * @return array{payments: Collection<int, SubscriptionPayment>, events: Collection<int, RenewalEvent>}
     */
    public function history(SchoolSubscription $subscription, ?int $limit = null): array
    {
        return [
            'payments' => SubscriptionPayment::query()->with(['plan', 'recordedBy'])
                ->where('school_subscription_id', $subscription->getKey())
                ->latest('paid_on')->latest('id')->when($limit, fn ($q) => $q->limit($limit))->get(),
            'events' => RenewalEvent::query()->with(['newPlan', 'previousPlan', 'actor', 'payment'])
                ->where('school_subscription_id', $subscription->getKey())
                ->latest('created_at')->latest('id')->when($limit, fn ($q) => $q->limit($limit))->get(),
        ];
    }

    /**
     * The renewals list: every subscription with its school, filterable by state, plan and school name/code.
     *
     * @param  array{q?: ?string, state?: ?string, plan_id?: int|string|null, sort?: ?string, dir?: ?string}  $filters
     * @return LengthAwarePaginator<int, SchoolSubscription>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $state = SubscriptionState::tryFrom((string) ($filters['state'] ?? ''));
        $planId = (int) ($filters['plan_id'] ?? 0);
        $sort = self::SORTS[$filters['sort'] ?? ''] ?? self::SORTS['expires_on'];
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return SchoolSubscription::query()
            ->select('school_subscriptions.*')
            ->join('schools', 'schools.id', '=', 'school_subscriptions.school_id')
            ->with(['school', 'plan', 'latestPayment'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('schools.name', 'like', $like)->orWhere('schools.code', 'like', $like)->orWhere('schools.city', 'like', $like));
            })
            ->when($state !== null, fn ($query) => $query->inState($state))
            ->when($planId > 0, fn ($query) => $query->where('school_subscriptions.subscription_plan_id', $planId))
            ->orderBy($sort, $dir)
            ->orderBy('schools.name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array<string, int> state value => number of subscriptions (every state present, 0 when none) */
    public function stateCounts(): array
    {
        $counts = [];
        foreach (SubscriptionState::cases() as $state) {
            $counts[$state->value] = SchoolSubscription::query()->inState($state)->count();
        }

        return $counts;
    }

    /**
     * Lists for the platform dashboard (FR-806): who needs a renewal call.
     *
     * @return array{expiring: Collection<int, SchoolSubscription>, grace: Collection<int, SchoolSubscription>, expired: Collection<int, SchoolSubscription>, counts: array<string, int>, unmanaged: int}
     */
    public function dashboard(int $limit = 6): array
    {
        $list = fn (SubscriptionState $state, string $dir) => SchoolSubscription::query()
            ->with(['school', 'plan'])
            ->inState($state)
            ->orderBy('expires_on', $dir)
            ->limit($limit)
            ->get();

        return [
            'expiring' => $list(SubscriptionState::ExpiringSoon, 'asc'),
            'grace' => $list(SubscriptionState::Grace, 'asc'),
            'expired' => $list(SubscriptionState::Expired, 'desc'),
            'counts' => $this->stateCounts(),
            'unmanaged' => School::query()->whereNotIn('id', SchoolSubscription::query()->select('school_id'))->count(),
        ];
    }

    // ------------------------------------------------------------------ writes

    /**
     * Assign a plan to a school that has no subscription yet (FR-803, "activate its initial access").
     * expires_on defaults to one billing period of the plan from starts_on.
     *
     * @param  array{subscription_plan_id: int, starts_on: string, expires_on?: ?string, grace_days?: ?int, notes?: ?string}  $data
     */
    public function assign(School $school, array $data, User $actor): SchoolSubscription
    {
        $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);

        if (! $plan->is_active) {
            throw ValidationException::withMessages(['subscription_plan_id' => 'This plan is retired. Choose an active plan.']);
        }

        $startsOn = CarbonImmutable::parse($data['starts_on']);
        $expiresOn = filled($data['expires_on'] ?? null)
            ? CarbonImmutable::parse($data['expires_on'])
            : RenewalPeriod::starting($startsOn, max(1, $plan->billing_period_months))->endsOn;

        $this->assertDates($startsOn, $expiresOn);

        try {
            return DB::transaction(function () use ($school, $plan, $startsOn, $expiresOn, $data, $actor): SchoolSubscription {
                // Serialise concurrent "assign" clicks for the same school on the school row.
                School::query()->whereKey($school->getKey())->lockForUpdate()->first();

                if (SchoolSubscription::query()->where('school_id', $school->getKey())->exists()) {
                    throw ValidationException::withMessages(['subscription_plan_id' => 'This school already has a subscription. Change its plan or record a payment instead.']);
                }

                $subscription = new SchoolSubscription([
                    'subscription_plan_id' => $plan->getKey(),
                    'starts_on' => $startsOn->toDateString(),
                    'expires_on' => $expiresOn->toDateString(),
                    'grace_days' => $data['grace_days'] ?? (int) config('subscriptions.default_grace_days', 7),
                    'notes' => $data['notes'] ?? null,
                ]);
                $subscription->forceFill(['school_id' => $school->getKey()]);
                $this->rememberState($subscription);
                $subscription->save();

                $this->event($subscription, RenewalEventType::Activated, $actor, [
                    'new_plan_id' => $plan->getKey(),
                    'new_expires_on' => $subscription->expires_on,
                ]);

                $this->audit->record('subscription.activated', $subscription, new: [
                    'plan' => $plan->name,
                    'starts_on' => $subscription->starts_on->toDateString(),
                    'expires_on' => $subscription->expires_on->toDateString(),
                    'grace_days' => $subscription->grace_days,
                ], actor: $actor, schoolId: $school->getKey());

                return $subscription;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['subscription_plan_id' => 'This school already has a subscription.']);
        }
    }

    /**
     * Change the plan, dates, grace days or notes of a subscription (FR-803). A plan change and a date change are
     * recorded as separate history lines; each is audited (FR-808).
     *
     * @param  array{subscription_plan_id: int, starts_on: string, expires_on: string, grace_days: int, notes?: ?string, reason?: ?string}  $data
     */
    public function updateTerms(SchoolSubscription $subscription, array $data, User $actor): SchoolSubscription
    {
        return DB::transaction(function () use ($subscription, $data, $actor): SchoolSubscription {
            /** @var SchoolSubscription $locked */
            $locked = SchoolSubscription::query()->with('plan')->lockForUpdate()->findOrFail($subscription->getKey());

            $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);
            $planChanged = $plan->getKey() !== $locked->subscription_plan_id;

            if ($planChanged && ! $plan->is_active) {
                throw ValidationException::withMessages(['subscription_plan_id' => 'This plan is retired. Choose an active plan.']);
            }

            $startsOn = CarbonImmutable::parse($data['starts_on']);
            $expiresOn = CarbonImmutable::parse($data['expires_on']);
            $this->assertDates($startsOn, $expiresOn);

            $before = [
                'plan' => $locked->plan->name,
                'starts_on' => $locked->starts_on->toDateString(),
                'expires_on' => $locked->expires_on->toDateString(),
                'grace_days' => $locked->grace_days,
                'notes' => $locked->notes,
            ];
            $previousPlanId = $locked->subscription_plan_id;
            $previousExpiry = $locked->expires_on;

            $locked->fill([
                'subscription_plan_id' => $plan->getKey(),
                'starts_on' => $startsOn->toDateString(),
                'expires_on' => $expiresOn->toDateString(),
                'grace_days' => (int) $data['grace_days'],
                'notes' => $data['notes'] ?? null,
            ]);

            $after = [
                'plan' => $plan->name,
                'starts_on' => $startsOn->toDateString(),
                'expires_on' => $expiresOn->toDateString(),
                'grace_days' => (int) $data['grace_days'],
                'notes' => $data['notes'] ?? null,
            ];
            [$old, $new] = $this->audit->diff($before, $after);

            if ($new === []) {
                return $locked;
            }

            $this->rememberState($locked);
            $locked->save();

            $reason = $data['reason'] ?? null;
            $schoolId = $locked->school_id;

            if ($planChanged) {
                $this->event($locked, RenewalEventType::PlanChanged, $actor, [
                    'previous_plan_id' => $previousPlanId,
                    'new_plan_id' => $plan->getKey(),
                    'notes' => $reason,
                ]);
                $this->audit->record('subscription.plan_changed', $locked, ['plan' => $before['plan']], ['plan' => $plan->name], meta: array_filter(['reason' => $reason]), actor: $actor, schoolId: $schoolId);
            }

            $termKeys = array_diff(array_keys($new), ['plan']);
            if ($termKeys !== []) {
                $this->event($locked, RenewalEventType::Adjusted, $actor, [
                    'previous_expires_on' => $previousExpiry,
                    'new_expires_on' => $locked->expires_on,
                    'notes' => $reason,
                ]);
                $keep = array_flip($termKeys);
                $this->audit->record('subscription.adjusted', $locked, array_intersect_key($old, $keep), array_intersect_key($new, $keep), meta: array_filter(['reason' => $reason]), actor: $actor, schoolId: $schoolId);
            }

            return $locked;
        });
    }

    /** Manually suspend (FR-805 "Suspended"). Users of the school then see only the subscription notice. No data is touched. */
    public function suspend(SchoolSubscription $subscription, string $reason, User $actor): SchoolSubscription
    {
        return DB::transaction(function () use ($subscription, $reason, $actor): SchoolSubscription {
            $locked = SchoolSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if ($locked->isSuspended()) {
                throw ValidationException::withMessages(['suspension_reason' => 'This subscription is already suspended.']);
            }

            $locked->forceFill(['suspended_at' => now(), 'suspension_reason' => $reason, 'suspended_by' => $actor->getKey()]);
            $this->rememberState($locked);
            $locked->save();

            $this->event($locked, RenewalEventType::Suspended, $actor, ['notes' => $reason]);
            $this->audit->record('subscription.suspended', $locked, new: ['reason' => $reason], actor: $actor, schoolId: $locked->school_id);

            return $locked;
        });
    }

    public function resume(SchoolSubscription $subscription, User $actor): SchoolSubscription
    {
        return DB::transaction(function () use ($subscription, $actor): SchoolSubscription {
            $locked = SchoolSubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if (! $locked->isSuspended()) {
                throw ValidationException::withMessages(['subscription' => 'This subscription is not suspended.']);
            }

            $reason = $locked->suspension_reason;
            $locked->forceFill(['suspended_at' => null, 'suspension_reason' => null, 'suspended_by' => null]);
            $this->rememberState($locked);
            $locked->save();

            $this->event($locked, RenewalEventType::Resumed, $actor);
            $this->audit->record('subscription.resumed', $locked, ['reason' => $reason], ['state' => $locked->status()->state->value], actor: $actor, schoolId: $locked->school_id);

            return $locked;
        });
    }

    // ------------------------------------------------------------------ shared helpers (also used by RenewalService)

    /**
     * Append one line to the renewal history.
     *
     * @param  array<string, mixed>  $attributes  previous/new plan ids, previous/new expiry, notes
     */
    public function event(SchoolSubscription $subscription, RenewalEventType $type, ?User $actor, array $attributes = [], ?SubscriptionPayment $payment = null): RenewalEvent
    {
        $event = new RenewalEvent(['type' => $type] + $attributes);
        $event->forceFill([
            'school_id' => $subscription->school_id,
            'school_subscription_id' => $subscription->getKey(),
            'subscription_payment_id' => $payment?->getKey(),
            'actor_id' => $actor?->getKey(),
        ]);
        $event->save();

        return $event;
    }

    /** Store the state as of today so the daily refresh only reports real transitions. */
    public function rememberState(SchoolSubscription $subscription): void
    {
        $state = $subscription->status()->state;

        if ($subscription->last_status !== $state) {
            $subscription->forceFill(['last_status' => $state, 'last_status_at' => now()]);
        }
    }

    private function assertDates(CarbonImmutable $startsOn, CarbonImmutable $expiresOn): void
    {
        if ($expiresOn->lessThan($startsOn)) {
            throw ValidationException::withMessages(['expires_on' => 'The expiry date cannot be before the start date.']);
        }
    }
}
