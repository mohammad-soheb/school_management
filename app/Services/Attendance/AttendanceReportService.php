<?php

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Staff\StaffStatus;
use App\Models\Attendance\AttendanceSheet;
use App\Models\Attendance\StaffAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Models\SchoolSetup\Section;
use App\Models\Staff\StaffProfile;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Attendance reports (FR-305) using the documented percentage (FR-306, AttendanceCalculator):
 * daily summary per section, monthly register, student-wise percentage over a range, staff monthly summary,
 * and the same data as CSV rows.
 *
 * Section access is decided by the caller (form request + AttendanceAccess); every query here is tenant-scoped.
 */
class AttendanceReportService
{
    /** Longest range for the student-wise report. */
    public const MAX_RANGE_DAYS = 366;

    public function __construct(
        private readonly TenantContext $context,
        private readonly AttendanceService $attendance,
        private readonly AttendanceAccess $access,
        private readonly AttendanceCalendar $calendar,
    ) {}

    /**
     * Sections the user may report on, for the filter.
     *
     * @return array<int, string> id => "Class 5 - A"
     */
    public function sectionOptions(User $user): array
    {
        return $this->attendance->sections($this->access->viewableSectionIds($user))
            ->mapWithKeys(fn (Section $section) => [$section->getKey() => $section->label()])
            ->all();
    }

    /** Daily summary per section: the "today" overview for any date. */
    public function daily(User $user, CarbonImmutable $date): array
    {
        return $this->attendance->overview($user, $date);
    }

    public function calculator(): AttendanceCalculator
    {
        return AttendanceCalculator::forSchool($this->context->requireSchool());
    }

    /**
     * Monthly register: students x days.
     *
     * @return array{
     *     section: Section, month: CarbonImmutable,
     *     days: list<array{date: string, day: int, weekday: string, closed: ?string}>,
     *     rows: list<array{enrollment: StudentEnrollment, cells: array<string, ?AttendanceStatus>, summary: array<string, mixed>}>,
     *     day_totals: array<string, array{in: int, marked: int}>, totals: array<string, mixed>, rule: string, marked_days: int
     * }
     */
    public function monthly(Section $section, CarbonImmutable $month): array
    {
        $from = AttendanceCalendar::day($month->startOfMonth());
        $to = AttendanceCalendar::day($month->endOfMonth());
        $section->loadMissing('schoolClass:id,name');

        $closed = $this->calendar->nonWorkingDates($from, $to);
        $calculator = $this->calculator();
        [$enrollments, $byEnrollment] = $this->sectionRecords($section, $from, $to);

        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key = $day->toDateString();
            $days[] = ['date' => $key, 'day' => (int) $day->day, 'weekday' => $day->format('D'), 'closed' => $closed[$key] ?? null];
        }

        $rows = [];
        $dayTotals = [];
        $allStatuses = [];
        foreach ($enrollments as $enrollment) {
            $cells = [];
            $counted = [];
            foreach ($days as $day) {
                $status = $byEnrollment[$enrollment->getKey()][$day['date']] ?? null;
                $cells[$day['date']] = $status;

                if ($status !== null && $day['closed'] === null) {
                    $counted[] = $status;
                    $allStatuses[] = $status;
                    // "In school": present, late or half day (absent and leave are out).
                    $in = in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::HalfDay], true) ? 1 : 0;
                    $dayTotals[$day['date']]['marked'] = ($dayTotals[$day['date']]['marked'] ?? 0) + 1;
                    $dayTotals[$day['date']]['in'] = ($dayTotals[$day['date']]['in'] ?? 0) + $in;
                }
            }

            $rows[] = ['enrollment' => $enrollment, 'cells' => $cells, 'summary' => $calculator->summariseStatuses($counted)];
        }

        $markedDays = AttendanceSheet::query()
            ->where('section_id', $section->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->filter(fn ($date) => ! isset($closed[$date->toDateString()]))
            ->count();

        return [
            'section' => $section,
            'month' => $from,
            'days' => $days,
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'totals' => $calculator->summariseStatuses($allStatuses),
            'rule' => $calculator->describe(),
            'marked_days' => $markedDays,
        ];
    }

    /**
     * Student-wise percentage for a section over [from, to].
     *
     * @return array{section: Section, from: CarbonImmutable, to: CarbonImmutable, rows: list<array{enrollment: StudentEnrollment, summary: array<string, mixed>}>, totals: array<string, mixed>, rule: string}
     */
    public function studentWise(Section $section, CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$from, $to] = $this->range($from, $to);
        $section->loadMissing('schoolClass:id,name');

        $closed = $this->calendar->nonWorkingDates($from, $to);
        $calculator = $this->calculator();
        [$enrollments, $byEnrollment] = $this->sectionRecords($section, $from, $to);

        $rows = [];
        $all = [];
        foreach ($enrollments as $enrollment) {
            $statuses = [];
            foreach ($byEnrollment[$enrollment->getKey()] ?? [] as $date => $status) {
                if (! isset($closed[$date])) {
                    $statuses[] = $status;
                    $all[] = $status;
                }
            }
            $rows[] = ['enrollment' => $enrollment, 'summary' => $calculator->summariseStatuses($statuses)];
        }

        return [
            'section' => $section,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => $calculator->summariseStatuses($all),
            'rule' => $calculator->describe(),
        ];
    }

    /**
     * Staff monthly summary.
     *
     * @return array{month: CarbonImmutable, rows: list<array{staff: StaffProfile, summary: array<string, mixed>}>, totals: array<string, mixed>, working_days: int, rule: string}
     */
    public function staffMonthly(CarbonImmutable $month): array
    {
        $from = AttendanceCalendar::day($month->startOfMonth());
        $to = AttendanceCalendar::day($month->endOfMonth());
        $closed = $this->calendar->nonWorkingDates($from, $to);
        $calculator = $this->calculator();

        $records = StaffAttendance::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'staff_profile_id', 'date', 'status']);

        $byStaff = [];
        foreach ($records as $record) {
            if (! isset($closed[$record->date->toDateString()])) {
                $byStaff[$record->staff_profile_id][] = $record->status;
            }
        }

        $staff = StaffProfile::query()
            ->where(fn ($q) => $q->where('status', StaffStatus::Active->value)->orWhereIn('id', array_keys($byStaff)))
            ->orderBy('first_name')->orderBy('last_name')->orderBy('id')
            ->get(['id', 'employee_id', 'first_name', 'last_name', 'designation', 'status']);

        $rows = [];
        $all = [];
        foreach ($staff as $member) {
            $statuses = $byStaff[$member->getKey()] ?? [];
            array_push($all, ...$statuses);
            $rows[] = ['staff' => $member, 'summary' => $calculator->summariseStatuses($statuses)];
        }

        // School days so far in the month (the whole month once it is over).
        $until = $to->min(AttendanceCalendar::today());
        $workingDays = $until->lt($from) ? 0 : count($this->calendar->workingDays($from, $until));

        return [
            'month' => $from,
            'rows' => $rows,
            'totals' => $calculator->summariseStatuses($all),
            'working_days' => $workingDays,
            'rule' => $calculator->describe(),
        ];
    }

    // ---- CSV ------------------------------------------------------------------------------------------------------

    /** @return array{filename: string, rows: list<list<string|int|float|null>>} */
    public function dailyCsv(User $user, CarbonImmutable $date): array
    {
        $report = $this->daily($user, $date);
        $rows = [['Date', 'Class / section', 'Students', 'Status', 'Present', 'Absent', 'Late', 'Leave', 'Half day', 'Percentage', 'Submitted by']];

        foreach ($report['rows'] as $row) {
            $s = $row['summary'];
            $rows[] = [
                $report['date']->toDateString(),
                $row['section']->label(),
                $row['roster'],
                $row['sheet'] ? 'Marked' : 'Not marked',
                $s['present'], $s['absent'], $s['late'], $s['leave'], $s['half_day'],
                $s['percentage'],
                $row['sheet']?->submitter?->name,
            ];
        }

        return ['filename' => 'attendance-daily-'.$report['date']->toDateString().'.csv', 'rows' => $rows];
    }

    /** @return array{filename: string, rows: list<list<string|int|float|null>>} */
    public function monthlyCsv(Section $section, CarbonImmutable $month): array
    {
        $report = $this->monthly($section, $month);
        $header = ['Roll no', 'Admission no', 'Student'];
        foreach ($report['days'] as $day) {
            $header[] = (string) $day['day'];
        }
        array_push($header, 'Present', 'Absent', 'Late', 'Leave', 'Half day', 'Counted days', 'Percentage');

        $rows = [$header];
        foreach ($report['rows'] as $row) {
            $student = $row['enrollment']->student;
            $line = [$row['enrollment']->roll_no, $student->admission_no, $student->fullName()];
            foreach ($report['days'] as $day) {
                $line[] = $day['closed'] !== null ? 'H' : ($row['cells'][$day['date']]?->code() ?? '');
            }
            $s = $row['summary'];
            array_push($line, $s['present'], $s['absent'], $s['late'], $s['leave'], $s['half_day'], $s['days'], $s['percentage']);
            $rows[] = $line;
        }

        return [
            'filename' => 'attendance-register-'.str($section->label())->slug().'-'.$report['month']->format('Y-m').'.csv',
            'rows' => $rows,
        ];
    }

    /** @return array{filename: string, rows: list<list<string|int|float|null>>} */
    public function studentWiseCsv(Section $section, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $report = $this->studentWise($section, $from, $to);
        $rows = [['Roll no', 'Admission no', 'Student', 'Present', 'Absent', 'Late', 'Leave', 'Half day', 'Counted days', 'Percentage']];

        foreach ($report['rows'] as $row) {
            $student = $row['enrollment']->student;
            $s = $row['summary'];
            $rows[] = [$row['enrollment']->roll_no, $student->admission_no, $student->fullName(), $s['present'], $s['absent'], $s['late'], $s['leave'], $s['half_day'], $s['days'], $s['percentage']];
        }

        return [
            'filename' => 'attendance-students-'.str($section->label())->slug().'-'.$report['from']->toDateString().'-to-'.$report['to']->toDateString().'.csv',
            'rows' => $rows,
        ];
    }

    /** @return array{filename: string, rows: list<list<string|int|float|null>>} */
    public function staffMonthlyCsv(CarbonImmutable $month): array
    {
        $report = $this->staffMonthly($month);
        $rows = [['Employee id', 'Name', 'Designation', 'Present', 'Absent', 'Late', 'Leave', 'Half day', 'Counted days', 'Percentage']];

        foreach ($report['rows'] as $row) {
            $s = $row['summary'];
            $rows[] = [$row['staff']->employee_id, $row['staff']->fullName(), $row['staff']->designation, $s['present'], $s['absent'], $s['late'], $s['leave'], $s['half_day'], $s['days'], $s['percentage']];
        }

        return ['filename' => 'attendance-staff-'.$report['month']->format('Y-m').'.csv', 'rows' => $rows];
    }

    // ---- internals --------------------------------------------------------------------------------------------------

    /**
     * Enrollments to list for a section over a range (anyone with a record there, plus today's roster when the range
     * includes today's academic year) and their statuses by date.
     *
     * @return array{0: Collection<int, StudentEnrollment>, 1: array<int, array<string, AttendanceStatus>>}
     */
    private function sectionRecords(Section $section, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sheetIds = AttendanceSheet::query()
            ->where('section_id', $section->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('id');

        $byEnrollment = [];
        StudentAttendance::query()
            ->whereIn('attendance_sheet_id', $sheetIds)
            ->get(['id', 'student_enrollment_id', 'date', 'status'])
            ->each(function (StudentAttendance $record) use (&$byEnrollment): void {
                $byEnrollment[$record->student_enrollment_id][$record->date->toDateString()] = $record->status;
            });

        $enrollmentIds = array_keys($byEnrollment);

        $year = $this->context->academicYear();
        if ($year !== null && $from->lte($year->ends_on) && $to->gte($year->starts_on)) {
            $asOf = $to->min(AttendanceCalendar::today())->max($from);
            $rosterIds = $this->attendance->rosterQuery($year, $asOf)->where('section_id', $section->getKey())->pluck('id')->all();
            $enrollmentIds = array_values(array_unique([...$enrollmentIds, ...$rosterIds]));
        }

        $enrollments = StudentEnrollment::query()
            ->whereIn('id', $enrollmentIds)
            ->with('student:id,admission_no,first_name,last_name')
            ->get(['id', 'student_id', 'section_id', 'roll_no', 'status'])
            ->sortBy(fn (StudentEnrollment $e) => [$e->roll_no === null ? 1 : 0, strlen((string) $e->roll_no), (string) $e->roll_no, $e->id])
            ->values();

        return [$enrollments, $byEnrollment];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = AttendanceCalendar::day($from);
        $to = AttendanceCalendar::day($to);

        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'Choose a range of at most '.self::MAX_RANGE_DAYS.' days.']);
        }

        return [$from, $to];
    }
}
