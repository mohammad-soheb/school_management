<?php

namespace App\Models\Academics;

use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable per-student result snapshot written when results are published (FR-510). Never updated or deleted:
 * a correction is a new publication with new rows. `subjects` holds the per-subject lines as printed.
 */
#[Fillable(['result_publication_id', 'exam_id', 'school_class_id', 'section_id', 'student_enrollment_id', 'student_id', 'student_name', 'admission_no', 'roll_no', 'total_obtained', 'total_max', 'percentage', 'grade', 'grade_point', 'grade_remark', 'passed', 'rank', 'subjects', 'teacher_remark'])]
class ExamResult extends Model
{
    use BelongsToSchool;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published exam results are immutable; publish a new version instead.'));
        static::deleting(fn () => throw new LogicException('Published exam results are never deleted.'));
    }

    protected function casts(): array
    {
        return [
            'total_obtained' => 'decimal:2',
            'total_max' => 'decimal:2',
            'percentage' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'passed' => 'boolean',
            'rank' => 'integer',
            'subjects' => 'array',
        ];
    }

    /** @return BelongsTo<ResultPublication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(ResultPublication::class, 'result_publication_id');
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
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
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
