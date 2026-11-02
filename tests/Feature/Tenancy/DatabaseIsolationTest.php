<?php

namespace Tests\Feature\Tenancy;

use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Level 6 of the isolation design: even code that bypasses Eloquent (raw SQL, a future bug) cannot make a row in one
 * school point at a row in another, because the foreign keys include school_id.
 */
class DatabaseIsolationTest extends TestCase
{
    public function test_an_enrollment_cannot_reference_another_schools_student(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        [$year, $class, $section] = $this->inSchool($a, fn () => [
            AcademicYear::factory()->create(),
            $c = SchoolClass::factory()->create(),
            Section::factory()->create(['school_class_id' => $c->id]),
        ]);
        $foreignStudent = $this->inSchool($b, fn () => Student::factory()->create());

        $this->expectException(QueryException::class);

        DB::table('student_enrollments')->insert([
            'school_id' => $a->id,
            'student_id' => $foreignStudent->id, // belongs to school B
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_section_cannot_belong_to_another_schools_class(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();
        $foreignClass = $this->inSchool($b, fn () => SchoolClass::factory()->create());

        $this->expectException(QueryException::class);

        DB::table('sections')->insert([
            'school_id' => $a->id,
            'school_class_id' => $foreignClass->id,
            'name' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_school_can_have_only_one_current_academic_year(): void
    {
        $school = $this->makeSchool();
        $this->inSchool($school, fn () => AcademicYear::factory()->current()->create());

        $this->expectException(QueryException::class);

        $this->inSchool($school, fn () => AcademicYear::factory()->current()->create());
    }

    public function test_two_schools_can_each_have_a_current_academic_year(): void
    {
        $a = $this->makeSchool();
        $b = $this->makeSchool();

        $this->inSchool($a, fn () => AcademicYear::factory()->current()->create());
        $this->inSchool($b, fn () => AcademicYear::factory()->current()->create());

        $this->assertDatabaseCount('academic_years', 2);
    }

    public function test_a_student_can_be_enrolled_only_once_per_year(): void
    {
        $school = $this->makeSchool();

        $this->expectException(QueryException::class);

        $this->inSchool($school, function () {
            $year = AcademicYear::factory()->create();
            $class = SchoolClass::factory()->create();
            $student = Student::factory()->create();

            foreach ([1, 2] as $ignored) {
                \App\Models\Students\StudentEnrollment::factory()->create([
                    'student_id' => $student->id, 'academic_year_id' => $year->id, 'school_class_id' => $class->id, 'section_id' => null,
                ]);
            }
        });
    }
}
