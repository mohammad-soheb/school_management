<?php

namespace Database\Factories\SchoolSetup;

use App\Enums\SchoolSetup\SubjectType;
use App\Models\SchoolSetup\Subject;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<Subject>
 */
class SubjectFactory extends TenantFactory
{
    protected $model = Subject::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $n = ++self::$sequence;

        return [
            'school_id' => $this->schoolId(),
            'name' => fake()->randomElement(['Mathematics', 'English', 'Hindi', 'Science', 'Social Studies', 'Computer', 'Art', 'Sanskrit']).' '.$n,
            'code' => 'SUB'.$n,
            'type' => SubjectType::Scholastic,
            'sort_order' => $n,
            'is_active' => true,
        ];
    }
}
