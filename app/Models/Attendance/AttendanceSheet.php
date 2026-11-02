<?php

namespace App\Models\Attendance;

use App\Enums\Attendance\SheetStatus;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\User;
use App\Policies\Attendance\AttendanceSheetPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A submitted class register: one per section, date and session. Table: attendance_sheets. */
#[Fillable(['academic_year_id', 'school_class_id', 'section_id', 'date', 'session', 'status', 'submitted_by', 'submitted_at'])]
#[UsePolicy(AttendanceSheetPolicy::class)]
class AttendanceSheet extends Model
{
    use BelongsToSchool;

    public const SESSION_DAY = 'day';

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => SheetStatus::class,
            'submitted_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return HasMany<StudentAttendance, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }
}
