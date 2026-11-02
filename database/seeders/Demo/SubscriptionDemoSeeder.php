<?php

namespace Database\Seeders\Demo;

use App\Enums\Subscriptions\SubscriptionPaymentMode;
use App\Models\Platform\School;
use App\Models\Platform\SchoolSubscription;
use App\Models\Platform\SubscriptionPlan;
use App\Models\User;
use App\Services\Platform\Subscriptions\PlanService;
use App\Services\Platform\Subscriptions\RenewalService;
use App\Services\Platform\Subscriptions\SubscriptionService;
use Illuminate\Database\Seeder;

/**
 * Demo subscriptions (synthetic): the §20.1 hypothesis plans, Sunrise on Standard and renewed (Active), Green Valley
 * on Starter expiring in 9 days (Expiring soon). Subscriptions are platform-owned rows, so no tenant context is needed.
 * Safe to run again: existing plans and subscriptions are left alone.
 */
class SubscriptionDemoSeeder extends Seeder
{
    public function run(PlanService $plans, SubscriptionService $subscriptions, RenewalService $renewals): void
    {
        $plans->installHypothesisPlans();

        $actor = User::query()->where('is_platform_admin', true)->orderBy('id')->first();
        if ($actor === null) {
            $this->command?->warn('No Super Admin found: demo subscriptions skipped.');

            return;
        }

        $today = school_today();

        if (($sunrise = $this->school('sunrise')) !== null && ! $this->hasSubscription($sunrise)) {
            $subscription = $subscriptions->assign($sunrise, [
                'subscription_plan_id' => $this->plan('standard'),
                'starts_on' => $today->subMonths(3)->toDateString(),
                'expires_on' => $today->addDays(10)->toDateString(),
                'grace_days' => 7,
                'notes' => 'Demo school (synthetic data).',
            ], $actor);

            $renewals->recordPayment($subscription, [
                'amount_paise' => 3 * 149900,
                'paid_on' => $today->toDateString(),
                'mode' => SubscriptionPaymentMode::Upi->value,
                'reference' => 'DEMO-UTR-000123',
                'months' => 3,
                'notes' => 'Quarterly renewal (demo).',
                'idempotency_key' => 'demo-sunrise-renewal-1',
            ], $actor);
        }

        if (($greenValley = $this->school('greenvalley')) !== null && ! $this->hasSubscription($greenValley)) {
            $subscriptions->assign($greenValley, [
                'subscription_plan_id' => $this->plan('starter'),
                'starts_on' => $today->subMonths(1)->addDays(9)->toDateString(),
                'expires_on' => $today->addDays(9)->toDateString(),
                'grace_days' => 7,
            ], $actor);
        }
    }

    private function school(string $code): ?School
    {
        return School::query()->where('code', $code)->first();
    }

    private function hasSubscription(School $school): bool
    {
        return SchoolSubscription::query()->where('school_id', $school->getKey())->exists();
    }

    private function plan(string $code): int
    {
        return (int) SubscriptionPlan::query()->where('code', $code)->value('id');
    }
}
