<?php

namespace App\Models\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One student's status on a submitted sheet. Never deleted; changed only by AttendanceCorrectionService. */
#[Fillable(['attendance_sheet_id', 'student_enrollment_id', 'student_id', 'date', 'session', 'status', 'remark', 'corrected_by', 'corrected_at'])]
class StudentAttendance extends Model
{
    use BelongsToSchool;

    protected $table = 'student_attendance';

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => AttendanceStatus::class,
            'corrected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AttendanceSheet, $this> */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(AttendanceSheet::class, 'attendance_sheet_id');
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
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
