<?php

namespace Database\Factories\Staff;

use App\Enums\Shared\Gender;
use App\Enums\Staff\StaffStatus;
use App\Models\Staff\StaffProfile;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<StaffProfile>
 */
class StaffProfileFactory extends TenantFactory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'employee_id' => 'EMP'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-24 years')->format('Y-m-d'),
            'designation' => fake()->randomElement(['Teacher', 'Senior Teacher', 'Clerk', 'Accountant']),
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
            'joined_on' => fake()->dateTimeBetween('-8 years', '-1 month')->format('Y-m-d'),
            'status' => StaffStatus::Active,
        ];
    }
}
