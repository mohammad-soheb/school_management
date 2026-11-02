<?php

namespace App\Models\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Staff\StaffProfile;
use App\Models\Students\Student;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history row: one per changed attendance record (FR-304, FR-307). */
#[Fillable(['record_type', 'record_id', 'student_id', 'staff_profile_id', 'date', 'old_status', 'new_status', 'old_remark', 'new_remark', 'reason', 'corrected_by'])]
class AttendanceCorrection extends Model
{
    use BelongsToSchool;

    public const TYPE_STUDENT = 'student';

    public const TYPE_STAFF = 'staff';

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'old_status' => AttendanceStatus::class,
            'new_status' => AttendanceStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
