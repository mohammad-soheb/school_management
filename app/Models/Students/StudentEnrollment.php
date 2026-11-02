<?php

namespace App\Models\Students;

use App\Enums\Students\EnrollmentStatus;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Students\StudentEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's place in one academic year (class, section, roll number). Attendance, fees and marks attach to
 * the enrollment so they keep their original year after promotion.
 */
#[Fillable(['student_id', 'academic_year_id', 'school_class_id', 'section_id', 'roll_no', 'status', 'joined_on', 'left_on', 'exit_reason', 'previous_enrollment_id', 'promotion_run_id'])]
class StudentEnrollment extends Model
{
    /** @use HasFactory<StudentEnrollmentFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function previousEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'previous_enrollment_id');
    }
}
