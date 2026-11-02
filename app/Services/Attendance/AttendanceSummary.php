<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceSheet;
use App\Models\Attendance\StaffAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Models\Students\Student;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Read-only attendance figures for other modules (student profile, parent portal, dashboard, report cards).
 * All numbers follow the documented rule in AttendanceCalculator and the school's attendance settings.
 */
class AttendanceSummary
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AttendanceCalendar $calendar,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * One student's attendance between $from and $to (inclusive). Defaults: the current academic year's start
     * (or 365 days back when no year is set) up to today.
     *
     * @return array{present: int, absent: int, late: int, leave: int, half_day: int, days: int, percentage: float|null}
     */
    public function forStudent(Student $student, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $today = AttendanceCalendar::today();
        $year = $this->context->academicYear();

        $from = AttendanceCalendar::day($from ?? ($year?->starts_on ?? $today->subDays(365)));
        $to = AttendanceCalendar::day($to ?? $today);

        $empty = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'half_day' => 0, 'days' => 0, 'percentage' => null];
        if ($to->lt($from)) {
            return $empty;
        }

        $closed = $this->calendar->nonWorkingDates($from, $to);
        $statuses = StudentAttendance::query()
            ->where('student_id', $student->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'date', 'status'])
            ->reject(fn (StudentAttendance $record) => isset($closed[$record->date->toDateString()]))
            ->map(fn (StudentAttendance $record) => $record->status);

        $summary = AttendanceCalculator::forSchool($this->context->requireSchool())->summariseStatuses($statuses);

        return array_intersect_key($summary, $empty);
    }

    /**
     * Today across the whole school (dashboard): class registers marked, student and staff counts.
     *
     * @return array{
     *     date: CarbonImmutable, non_working: ?string,
     *     sections: int, marked: int, pending: int,
     *     students: array{present: int, absent: int, late: int, leave: int, half_day: int, days: int, percentage: float|null},
     *     staff: array{total: int, marked: int, present: int, absent: int, late: int, leave: int, half_day: int, days: int, percentage: float|null}
     * }
     */
    public function todayForSchool(): array
    {
        $date = AttendanceCalendar::today();
        $calculator = AttendanceCalculator::forSchool($this->context->requireSchool());
        $year = $this->context->academicYear();

        $sectionsWithStudents = $year === null ? [] : $this->attendance->rosterQuery($year, $date)
            ->distinct()
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $markedSections = AttendanceSheet::query()
            ->whereDate('date', $date->toDateString())
            ->where('session', AttendanceSheet::SESSION_DAY)
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $expected = array_values(array_unique([...$sectionsWithStudents, ...$markedSections]));
        $marked = count(array_intersect($expected, $markedSections));

        $studentCounts = StudentAttendance::query()
            ->whereDate('date', $date->toDateString())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->toBase()
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $staffCounts = StaffAttendance::query()
            ->whereDate('date', $date->toDateString())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->toBase()
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $keys = array_flip(['present', 'absent', 'late', 'leave', 'half_day', 'days', 'percentage']);
        $staffTotal = app(StaffAttendanceService::class)->rosterQuery($date)->count();

        return [
            'date' => $date,
            'non_working' => $this->calendar->nonWorkingReason($date),
            'sections' => count($expected),
            'marked' => $marked,
            'pending' => count($expected) - $marked,
            'students' => array_intersect_key($calculator->summarise($studentCounts), $keys),
            'staff' => ['total' => $staffTotal, 'marked' => array_sum($staffCounts)] + array_intersect_key($calculator->summarise($staffCounts), $keys),
        ];
    }
}
