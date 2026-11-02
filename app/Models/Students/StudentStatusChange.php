<?php

namespace App\Models\Students;

use App\Enums\Students\StudentStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One row per student status change (FR-207). Written only by StudentService::changeStatus(); never edited or
 * deleted, so the profile can always show how and when a student left or came back.
 */
#[Fillable(['student_id', 'student_enrollment_id', 'from_status', 'to_status', 'effective_on', 'reason', 'changed_by'])]
class StudentStatusChange extends Model
{
    use BelongsToSchool;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Status history is append-only.'));
        static::deleting(fn () => throw new LogicException('Status history is append-only.'));
    }

    protected function casts(): array
    {
        return [
            'from_status' => StudentStatus::class,
            'to_status' => StudentStatus::class,
            'effective_on' => 'date',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
