<?php

namespace Database\Factories\Platform;

use App\Enums\Subscriptions\SubscriptionPaymentMode;
use App\Models\Platform\SchoolSubscription;
use App\Models\Platform\SubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Raw payment rows for list/rendering tests. Real payments are recorded through RenewalService::recordPayment().
 *
 * @extends Factory<SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    protected $model = SubscriptionPayment::class;

    public function definition(): array
    {
        return [
            'school_subscription_id' => SchoolSubscription::factory(),
            'school_id' => fn (array $a) => SchoolSubscription::query()->whereKey($a['school_subscription_id'])->value('school_id'),
            'subscription_plan_id' => fn (array $a) => SchoolSubscription::query()->whereKey($a['school_subscription_id'])->value('subscription_plan_id'),
            'amount_paise' => 99900,
            'expected_paise' => 99900,
            'paid_on' => school_today()->toDateString(),
            'mode' => SubscriptionPaymentMode::Upi,
            'reference' => 'UTR'.fake()->numerify('##########'),
            'months' => 1,
            'period_starts_on' => school_today()->toDateString(),
            'period_ends_on' => school_today()->addMonth()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
