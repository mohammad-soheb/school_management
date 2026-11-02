<?php

namespace Database\Factories\Students;

use App\Enums\Students\GuardianRelation;
use App\Models\Students\Guardian;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<Guardian>
 */
class GuardianFactory extends TenantFactory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'name' => fake()->name(),
            'relation' => fake()->randomElement([GuardianRelation::Father, GuardianRelation::Mother]),
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
