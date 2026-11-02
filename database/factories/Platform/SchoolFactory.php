<?php

namespace Database\Factories\Platform;

use App\Enums\Platform\SchoolStatus;
use App\Models\Platform\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' School';

        return [
            'name' => $name,
            'code' => Str::limit(Str::slug($name), 28, '').'-'.fake()->unique()->numerify('####'),
            'board' => fake()->randomElement(['CBSE', 'MP Board', 'ICSE']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9#########'),
            'city' => 'Indore',
            'state' => 'Madhya Pradesh',
            'pincode' => '452010',
            'status' => SchoolStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => SchoolStatus::Suspended]);
    }
}
