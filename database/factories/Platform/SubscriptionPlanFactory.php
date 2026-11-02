<?php

namespace Database\Factories\Platform;

use App\Models\Platform\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => 'Plan '.fake()->unique()->numerify('###'),
            'code' => 'plan-'.fake()->unique()->numerify('#####'),
            'description' => null,
            'monthly_price_paise' => 99900,
            'billing_period_months' => 1,
            'student_limit' => 250,
            'is_hypothesis' => true,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
