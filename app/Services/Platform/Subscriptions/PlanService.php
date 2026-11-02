<?php

namespace App\Services\Platform\Subscriptions;

use App\Models\Platform\SubscriptionPlan;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Super Admin management of the plans sold to schools (FR-803, FR-808). Plans are retired, never deleted. */
class PlanService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, SubscriptionPlan> every plan with its subscriber count, active first */
    public function all(): Collection
    {
        return SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderByDesc('is_active')
            ->orderBy('monthly_price_paise')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, string> id => "Starter · ₹999/month" for selects (active plans, plus $includeId even if retired; every plan with $includeRetired) */
    public function options(?int $includeId = null, bool $includeRetired = false): array
    {
        return SubscriptionPlan::query()
            ->unless($includeRetired, fn ($query) => $query->where(fn ($q) => $q->where('is_active', true)->when($includeId, fn ($q) => $q->orWhere('id', $includeId))))
            ->orderBy('monthly_price_paise')
            ->get()
            ->mapWithKeys(fn (SubscriptionPlan $p) => [$p->id => $p->name.' · '.Money::format($p->monthly_price_paise, decimals: false).'/month'.($p->is_active ? '' : ' (retired)')])
            ->all();
    }

    /** @param  array<string, mixed>  $data  validated plan fields (monthly_price_paise already in paise) */
    public function create(array $data, User $actor): SubscriptionPlan
    {
        return DB::transaction(function () use ($data, $actor): SubscriptionPlan {
            $plan = SubscriptionPlan::create($data);

            $this->audit->record('subscription_plan.created', $plan, new: $this->auditFields($plan), actor: $actor);

            return $plan;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(SubscriptionPlan $plan, array $data, User $actor): SubscriptionPlan
    {
        unset($data['code']); // the code is the stable identifier

        return DB::transaction(function () use ($plan, $data, $actor): SubscriptionPlan {
            $plan->fill($data);
            [$old, $new] = $this->audit->diff($plan->getOriginal(), $plan->getDirty());
            $plan->save();

            if ($new !== []) {
                $this->audit->record('subscription_plan.updated', $plan, $old, $new, actor: $actor);
            }

            return $plan;
        });
    }

    /**
     * Create the master-plan §20.1 hypothesis plans (config/subscriptions.php) that do not exist yet.
     * Existing plans with the same code are left untouched. Returns how many were created.
     */
    public function installHypothesisPlans(?User $actor = null): int
    {
        $created = 0;

        foreach (config('subscriptions.hypothesis_plans', []) as $spec) {
            if (SubscriptionPlan::query()->where('code', $spec['code'])->exists()) {
                continue;
            }

            $plan = SubscriptionPlan::create($spec + ['is_hypothesis' => true, 'is_active' => true]);
            $this->audit->record('subscription_plan.created', $plan, new: $this->auditFields($plan), meta: ['source' => 'hypothesis_defaults'], actor: $actor);
            $created++;
        }

        return $created;
    }

    /** @return array<string, mixed> */
    private function auditFields(SubscriptionPlan $plan): array
    {
        // "code" is a redacted key in AuditLogger (2FA codes), so the plan code is logged as plan_code.
        return ['plan_code' => $plan->code] + $plan->only(['name', 'monthly_price_paise', 'billing_period_months', 'student_limit', 'is_hypothesis', 'is_active']);
    }
}
