<?php

namespace Database\Factories\Students;

use App\Enums\Students\EnrollmentStatus;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<StudentEnrollment>
 */
class StudentEnrollmentFactory extends TenantFactory
{
    protected $model = StudentEnrollment::class;

    private static int $roll = 0;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'academic_year_id' => fn (array $a) => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            'school_class_id' => fn (array $a) => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            'section_id' => fn (array $a) => Section::factory()->create(['school_id' => $a['school_id'], 'school_class_id' => $a['school_class_id']])->id,
            'roll_no' => (string) ++self::$roll,
            'status' => EnrollmentStatus::Active,
            'joined_on' => now()->subMonths(2)->toDateString(),
        ];
    }
}
