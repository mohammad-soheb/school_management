<?php

namespace Database\Factories\Staff;

use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Staff\StaffProfile;
use App\Models\Staff\TeacherAssignment;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends TenantFactory
{
    protected $model = TeacherAssignment::class;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'staff_profile_id' => fn (array $a) => StaffProfile::factory()->create(['school_id' => $a['school_id']])->id,
            'academic_year_id' => fn (array $a) => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            'school_class_id' => fn (array $a) => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            'section_id' => fn (array $a) => Section::factory()->create(['school_id' => $a['school_id'], 'school_class_id' => $a['school_class_id']])->id,
            'subject_id' => null,
            'is_class_teacher' => false,
        ];
    }
}
