<?php

namespace Database\Factories\Students;

use App\Enums\Shared\Gender;
use App\Enums\Students\StudentStatus;
use App\Models\Students\Student;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<Student>
 */
class StudentFactory extends TenantFactory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'admission_no' => 'ADM'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-5 years')->format('Y-m-d'),
            'admission_date' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'status' => StudentStatus::Active,
            'phone' => fake()->numerify('9#########'),
            'address' => fake()->streetAddress().', Indore',
        ];
    }

    public function withStatus(StudentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
