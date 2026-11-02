<?php

namespace App\Models\Academics;

use App\Enums\Academics\MarkStatus;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's mark in one exam subject. Never deleted: clearing a cell sets the status back to pending.
 * component_marks: {"<exam_component_id>": "12.50"} for subjects with components.
 */
#[Fillable(['exam_id', 'exam_subject_id', 'mark_sheet_id', 'student_enrollment_id', 'student_id', 'status', 'marks_obtained', 'component_marks', 'entered_by', 'updated_by'])]
class Mark extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'status' => MarkStatus::class,
            'marks_obtained' => 'decimal:2',
            'component_marks' => 'array',
        ];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<ExamSubject, $this> */
    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    /** @return BelongsTo<MarkSheet, $this> */
    public function markSheet(): BelongsTo
    {
        return $this->belongsTo(MarkSheet::class);
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

    /** @return BelongsTo<User, $this> */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Input for the ResultCalculator.
     *
     * @return array{status: MarkStatus, obtained: ?string, components: array<int|string, string|null>}
     */
    public function rule(): array
    {
        return [
            'status' => $this->status,
            'obtained' => $this->marks_obtained !== null ? (string) $this->marks_obtained : null,
            'components' => $this->component_marks ?? [],
        ];
    }
}
