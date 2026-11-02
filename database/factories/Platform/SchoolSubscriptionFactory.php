<?php

namespace Database\Factories\Platform;

use App\Models\Platform\School;
use App\Models\Platform\SchoolSubscription;
use App\Models\Platform\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Platform-owned (not a TenantFactory). Use the states to put a school in a given status relative to today:
 *
 *   SchoolSubscription::factory()->for($school)->expiringInDays(5)->create();
 *   SchoolSubscription::factory()->for($school)->expiredDaysAgo(30)->create();   // past grace -> Expired
 *
 * @extends Factory<SchoolSubscription>
 */
class SchoolSubscriptionFactory extends Factory
{
    protected $model = SchoolSubscription::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'starts_on' => school_today()->subMonths(2)->toDateString(),
            'expires_on' => school_today()->addMonths(2)->toDateString(),
            'grace_days' => 7,
        ];
    }

    /** Expiry is $days from today (0 = today; negative = in the past). */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn () => ['expires_on' => school_today()->addDays($days)->toDateString()]);
    }

    public function expiredDaysAgo(int $days): static
    {
        return $this->expiringInDays(-$days);
    }

    public function suspended(string $reason = 'Payment overdue'): static
    {
        return $this->state(fn () => ['suspended_at' => now(), 'suspension_reason' => $reason]);
    }
}
