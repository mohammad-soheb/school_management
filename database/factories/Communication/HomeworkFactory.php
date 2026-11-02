<?php

namespace Database\Factories\Communication;

use App\Enums\Communication\HomeworkStatus;
use App\Models\Communication\Homework;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\SchoolSetup\Subject;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<Homework>
 */
class HomeworkFactory extends TenantFactory
{
    protected $model = Homework::class;

    public function definition(): array
    {
        $assigned = school_today();

        return [
            'school_id' => $this->schoolId(),
            'academic_year_id' => fn (array $a) => AcademicYear::query()->where('school_id', $a['school_id'])->where('is_current', true)->value('id')
                ?? AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            'school_class_id' => fn (array $a) => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            'section_id' => fn (array $a) => Section::factory()->create(['school_id' => $a['school_id'], 'school_class_id' => $a['school_class_id']])->id,
            'subject_id' => fn (array $a) => Subject::factory()->create(['school_id' => $a['school_id']])->id,
            'title' => fake()->randomElement(['Read chapter 4', 'Practice worksheet', 'Write an essay', 'Solve exercise 3.2', 'Learn the poem']),
            'description' => fake()->sentence(12),
            'assigned_on' => $assigned->toDateString(),
            'due_on' => $assigned->addDays(3)->toDateString(),
            'status' => HomeworkStatus::Active,
        ];
    }

    public function wholeClass(): static
    {
        return $this->state(fn () => ['section_id' => null]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => HomeworkStatus::Archived, 'archived_at' => now()]);
    }

    public function due(string $date): static
    {
        return $this->state(fn () => ['due_on' => $date, 'assigned_on' => min($date, school_today()->toDateString())]);
    }
}
