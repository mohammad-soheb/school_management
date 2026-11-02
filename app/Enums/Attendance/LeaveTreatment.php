<?php

namespace App\Enums\Attendance;

/** How a day on approved leave enters the attendance percentage (setting attendance.leave_counts_as). */
enum LeaveTreatment: string
{
    /** Leave days are left out of both the numerator and the denominator. */
    case Excluded = 'excluded';
    /** Leave days count as days the student was expected but did not attend. */
    case Absent = 'absent';
    /** Leave days count as attended. */
    case Present = 'present';

    public function label(): string
    {
        return match ($this) {
            self::Excluded => 'Not counted',
            self::Absent => 'Counted as absent',
            self::Present => 'Counted as present',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Excluded => 'neutral',
            self::Absent => 'danger',
            self::Present => 'success',
        };
    }
}
