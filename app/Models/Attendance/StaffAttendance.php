<?php

namespace App\Models\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Staff\StaffProfile;
use App\Models\User;
use App\Policies\Attendance\StaffAttendancePolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A staff member's status for one date. Never deleted; changed only by AttendanceCorrectionService. */
#[Fillable(['staff_profile_id', 'date', 'status', 'remark', 'marked_by', 'marked_at', 'corrected_by', 'corrected_at'])]
#[UsePolicy(StaffAttendancePolicy::class)]
class StaffAttendance extends Model
{
    use BelongsToSchool;

    protected $table = 'staff_attendance';

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => AttendanceStatus::class,
            'marked_at' => 'datetime',
            'corrected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /** @return BelongsTo<User, $this> */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
