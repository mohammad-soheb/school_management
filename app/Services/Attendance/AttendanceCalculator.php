<?php

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Attendance\LeaveTreatment;
use App\Models\Platform\School;

/**
 * The documented attendance percentage (FR-306). Pure: no database, no clock.
 *
 *   counted days  = present + absent + late + half_day (+ leave, unless leave_counts_as = excluded)
 *   attended      = present
 *                 + late                     when late_counts_as_present (otherwise a late day counts as absent)
 *                 + half_day x half_day_weight
 *                 + leave                    when leave_counts_as = present
 *   percentage    = attended / counted days x 100, rounded to 2 decimals (null when there are no counted days)
 *
 * Only MARKED school days enter the counts: holidays and weekly offs are never counted, and a day nobody marked is
 * not assumed to be absent. Callers pass counts of records that fall on working days (see AttendanceCalendar).
 */
final class AttendanceCalculator
{
    public function __construct(
        private readonly LeaveTreatment $leave = LeaveTreatment::Excluded,
        private readonly bool $lateCountsAsPresent = true,
        private readonly float $halfDayWeight = 0.5,
    ) {
        if ($halfDayWeight < 0 || $halfDayWeight > 1) {
            throw new \InvalidArgumentException('half_day_weight must be between 0 and 1.');
        }
    }

    /** The calculator configured by a school's settings (attendance.* with config/school_defaults/attendance.php). */
    public static function forSchool(School $school): self
    {
        return new self(
            LeaveTreatment::tryFrom((string) $school->setting('attendance.leave_counts_as', 'excluded')) ?? LeaveTreatment::Excluded,
            (bool) $school->setting('attendance.late_counts_as_present', true),
            (float) $school->setting('attendance.half_day_weight', 0.5),
        );
    }

    /**
     * @param  array<string, int>  $counts  status value => number of days (missing statuses count as 0)
     * @return array{present: int, absent: int, late: int, leave: int, half_day: int, days: int, attended: float, percentage: float|null}
     */
    public function summarise(array $counts): array
    {
        $present = (int) ($counts[AttendanceStatus::Present->value] ?? 0);
        $absent = (int) ($counts[AttendanceStatus::Absent->value] ?? 0);
        $late = (int) ($counts[AttendanceStatus::Late->value] ?? 0);
        $leave = (int) ($counts[AttendanceStatus::Leave->value] ?? 0);
        $halfDay = (int) ($counts[AttendanceStatus::HalfDay->value] ?? 0);

        $days = $present + $absent + $late + $halfDay + ($this->leave === LeaveTreatment::Excluded ? 0 : $leave);

        $attended = $present
            + ($this->lateCountsAsPresent ? $late : 0)
            + $halfDay * $this->halfDayWeight
            + ($this->leave === LeaveTreatment::Present ? $leave : 0);

        return [
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'half_day' => $halfDay,
            'days' => $days,
            'attended' => round($attended, 2),
            'percentage' => $days > 0 ? round($attended / $days * 100, 2) : null,
        ];
    }

    /**
     * Summarise a list of statuses (e.g. one student's month).
     *
     * @param  iterable<AttendanceStatus|string>  $statuses
     * @return array{present: int, absent: int, late: int, leave: int, half_day: int, days: int, attended: float, percentage: float|null}
     */
    public function summariseStatuses(iterable $statuses): array
    {
        $counts = [];
        foreach ($statuses as $status) {
            $value = $status instanceof AttendanceStatus ? $status->value : (string) $status;
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $this->summarise($counts);
    }

    /** A one-line description of the active rules, printed under reports. */
    public function describe(): string
    {
        return sprintf(
            'Percentage = attended ÷ counted school days. Holidays and weekly offs are never counted. Leave: %s. Late: %s. Half day = %s of a day.',
            strtolower($this->leave->label()),
            $this->lateCountsAsPresent ? 'counted as present' : 'counted as absent',
            rtrim(rtrim(number_format($this->halfDayWeight, 2), '0'), '.'),
        );
    }
}
