<?php

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Attendance\SheetStatus;
use App\Enums\Students\EnrollmentStatus;
use App\Enums\Students\StudentStatus;
use App\Models\Attendance\AttendanceSheet;
use App\Models\Attendance\StudentAttendance;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\Section;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student attendance: the teacher's "today" list, the class roster, and submitting a register (FR-301, FR-303, FR-304).
 *
 * Submitting is idempotent: a second submit for the same section/date/session (double click, retry, a colleague)
 * returns the sheet that already exists and changes nothing. Changes after submission go through
 * AttendanceCorrectionService.
 */
class AttendanceService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly AttendanceAccess $access,
        private readonly AttendanceCalendar $calendar,
        private readonly AttendanceCorrectionService $corrections,
    ) {}

    /**
     * Sections the user may see on $date, with their register state.
     *
     * @return array{
     *     date: CarbonImmutable, non_working: ?string, marking_error: ?string,
     *     rows: list<array{section: Section, roster: int, sheet: ?AttendanceSheet, counts: array<string, int>, summary: array<string, mixed>, can_mark: bool}>,
     *     totals: array{sections: int, marked: int, pending: int, summary: array<string, mixed>}
     * }
     */
    public function overview(User $user, CarbonImmutable $date): array
    {
        $date = AttendanceCalendar::day($date);
        $viewable = $this->access->viewableSectionIds($user);
        $markable = $this->access->markableSectionIds($user);
        $calculator = AttendanceCalculator::forSchool($this->context->requireSchool());

        $sections = $this->sections($viewable);
        $sectionIds = $sections->modelKeys();
        $rosters = $this->rosterCounts($sectionIds, $date);

        $sheets = AttendanceSheet::query()
            ->with('submitter:id,name')
            ->whereDate('date', $date->toDateString())
            ->where('session', AttendanceSheet::SESSION_DAY)
            ->whereIn('section_id', $sectionIds)
            ->get()
            ->keyBy('section_id');

        $counts = $this->statusCountsBySheet($sheets->modelKeys());
        $markingError = $this->markingError($user, $date);

        $rows = [];
        $totalCounts = [];
        foreach ($sections as $section) {
            $sheet = $sheets->get($section->getKey());
            $sheetCounts = $sheet ? ($counts[$sheet->getKey()] ?? []) : [];
            foreach ($sheetCounts as $status => $n) {
                $totalCounts[$status] = ($totalCounts[$status] ?? 0) + $n;
            }

            $rows[] = [
                'section' => $section,
                'roster' => $rosters[$section->getKey()] ?? 0,
                'sheet' => $sheet,
                'counts' => $sheetCounts,
                'summary' => $calculator->summarise($sheetCounts),
                'can_mark' => $markable === null || in_array($section->getKey(), $markable, true),
            ];
        }

        // Sections without students (and without a sheet) have nothing to mark: they are not "pending".
        $expected = array_filter($rows, fn (array $row) => $row['sheet'] !== null || $row['roster'] > 0);
        $marked = count(array_filter($expected, fn (array $row) => $row['sheet'] !== null));

        return [
            'date' => $date,
            'non_working' => $this->calendar->nonWorkingReason($date),
            'marking_error' => $markingError,
            'previous' => $this->calendar->adjacentWorkingDay($date, -1),
            'next' => $this->calendar->adjacentWorkingDay($date, 1),
            'rows' => $rows,
            'totals' => [
                'sections' => count($expected),
                'marked' => $marked,
                'pending' => count($expected) - $marked,
                'summary' => $calculator->summarise($totalCounts),
            ],
        ];
    }

    /**
     * Why $user cannot mark a register for $date (null = they can). Section rights are checked separately.
     */
    public function markingError(User $user, CarbonImmutable $date): ?string
    {
        $date = AttendanceCalendar::day($date);
        $today = AttendanceCalendar::today();

        if ($date->gt($today)) {
            return 'Attendance cannot be marked for a future date.';
        }

        $year = $this->context->academicYear();
        if ($year === null) {
            return 'No current academic year is set. Ask the school admin to set one up first.';
        }

        if (! $year->contains($date)) {
            return "This date is outside the current academic year ({$year->name}).";
        }

        if ($year->isLocked()) {
            return "The academic year {$year->name} is locked.";
        }

        if (($reason = $this->calendar->nonWorkingReason($date)) !== null) {
            return "{$date->format('D, d M Y')} is not a school day: {$reason}.";
        }

        $school = $this->context->requireSchool();
        $window = max(0, (int) $school->setting('attendance_marking.backdate_days', 7));

        if ($date->lt($today->subDays($window)) && ! $this->access->ignoresBackdateWindow($user, (bool) $school->setting('attendance_marking.mark_all_ignores_backdate', true))) {
            return $window === 0
                ? 'Attendance can only be marked for today.'
                : "Attendance can only be marked up to {$window} days back. Ask the school admin to mark older days.";
        }

        return null;
    }

    /**
     * Everything the register page needs: the marking grid (no sheet yet) or the submitted sheet with its
     * correction history (read-only, with a correction path for authorised users).
     *
     * @return array<string, mixed>
     */
    public function page(User $user, Section $section, CarbonImmutable $date): array
    {
        $date = AttendanceCalendar::day($date);
        $section->loadMissing('schoolClass:id,name');
        $sheet = $this->sheetFor($section, $date);
        $canMark = $this->access->canMark($user, (int) $section->getKey());
        $calculator = AttendanceCalculator::forSchool($this->context->requireSchool());

        $records = $sheet ? $this->records($sheet) : null;
        $counts = $records ? $records->countBy(fn (StudentAttendance $r) => $r->status->value)->all() : [];

        $canCorrect = false;
        if ($sheet !== null) {
            $sheet->loadMissing('academicYear:id,name,status');
            $canCorrect = $this->access->canCorrect($user, (int) $section->getKey()) && ! $sheet->academicYear->isLocked();
        }

        return [
            'section' => $section,
            'date' => $date,
            'sheet' => $sheet,
            'records' => $records,
            'roster' => $sheet ? null : $this->roster($section, $date),
            'can_mark' => $canMark,
            'marking_error' => $sheet !== null ? null : ($canMark ? $this->markingError($user, $date) : 'You can view this class but you are not assigned to mark it.'),
            'can_correct' => $canCorrect,
            'corrections' => $sheet ? $this->corrections->historyForSheet($sheet) : new Collection,
            'summary' => $calculator->summarise($counts),
            'previous' => $this->calendar->adjacentWorkingDay($date, -1),
            'next' => $this->calendar->adjacentWorkingDay($date, 1),
        ];
    }

    /** The submitted sheet for a section and date, if any. */
    public function sheetFor(Section $section, CarbonImmutable $date): ?AttendanceSheet
    {
        return AttendanceSheet::query()
            ->with('submitter:id,name')
            ->where('section_id', $section->getKey())
            ->whereDate('date', $date->toDateString())
            ->where('session', AttendanceSheet::SESSION_DAY)
            ->first();
    }

    /**
     * The students to mark: active enrollments of the current academic year in the section on $date, by roll number.
     * Withdrawn / transferred students and students who joined after $date are left out.
     *
     * @return Collection<int, StudentEnrollment>
     */
    public function roster(Section $section, CarbonImmutable $date): Collection
    {
        $year = $this->context->academicYear();

        if ($year === null) {
            return new Collection;
        }

        return $this->rosterQuery($year, $date)
            ->where('section_id', $section->getKey())
            ->with('student:id,admission_no,first_name,last_name,gender,status')
            ->orderByRaw('roll_no IS NULL, LENGTH(roll_no), roll_no')
            ->orderBy('id')
            ->get();
    }

    /**
     * The records of a submitted sheet, by roll number.
     *
     * @return Collection<int, StudentAttendance>
     */
    public function records(AttendanceSheet $sheet): Collection
    {
        return StudentAttendance::query()
            ->where('attendance_sheet_id', $sheet->getKey())
            ->with(['student:id,admission_no,first_name,last_name', 'enrollment:id,roll_no', 'corrector:id,name'])
            ->get()
            ->sortBy(fn (StudentAttendance $record) => [
                $record->enrollment->roll_no === null ? 1 : 0,
                strlen((string) $record->enrollment->roll_no),
                (string) $record->enrollment->roll_no,
                $record->id,
            ])
            ->values();
    }

    /**
     * Submit a section's register. Every student on the roster needs a status.
     *
     * @param  array<int|string, array{status: string, remark?: ?string}>  $entries  keyed by student_enrollment_id
     * @return array{sheet: AttendanceSheet, created: bool}
     */
    public function submit(Section $section, CarbonImmutable $date, array $entries, User $actor): array
    {
        $date = AttendanceCalendar::day($date);

        if (($error = $this->markingError($actor, $date)) !== null) {
            throw ValidationException::withMessages(['date' => $error]);
        }

        if (! $this->access->canMark($actor, (int) $section->getKey())) {
            throw ValidationException::withMessages(['section' => 'You are not assigned to this class.']);
        }

        if (($existing = $this->sheetFor($section, $date)) !== null) {
            return ['sheet' => $existing, 'created' => false];
        }

        $year = $this->context->academicYear();

        try {
            return DB::transaction(function () use ($section, $date, $entries, $actor, $year) {
                // Re-check inside the transaction with a lock: the unique key is the final guard.
                $existing = AttendanceSheet::query()
                    ->where('section_id', $section->getKey())
                    ->whereDate('date', $date->toDateString())
                    ->where('session', AttendanceSheet::SESSION_DAY)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return ['sheet' => $existing, 'created' => false];
                }

                $roster = $this->roster($section, $date)->keyBy('id');
                $statuses = $this->normaliseEntries($entries, $roster);
                $this->assertNotMarkedElsewhere($roster, $date);

                $sheet = AttendanceSheet::create([
                    'academic_year_id' => $year->getKey(),
                    'school_class_id' => $section->school_class_id,
                    'section_id' => $section->getKey(),
                    'date' => $date->toDateString(),
                    'session' => AttendanceSheet::SESSION_DAY,
                    'status' => SheetStatus::Submitted,
                    'submitted_by' => $actor->getKey(),
                    'submitted_at' => now(),
                ]);

                $now = now();
                $schoolId = $this->context->requireId();
                $rows = [];
                foreach ($roster as $enrollmentId => $enrollment) {
                    $rows[] = [
                        'school_id' => $schoolId,
                        'attendance_sheet_id' => $sheet->getKey(),
                        'student_enrollment_id' => $enrollmentId,
                        'student_id' => $enrollment->student_id,
                        'date' => $date->toDateString(),
                        'session' => AttendanceSheet::SESSION_DAY,
                        'status' => $statuses[$enrollmentId]['status'],
                        'remark' => $statuses[$enrollmentId]['remark'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                StudentAttendance::query()->insert($rows);

                $counts = array_count_values(array_column($rows, 'status'));
                $this->audit->record('attendance.submitted', $sheet, new: [
                    'section' => $section->getKey(),
                    'date' => $date->toDateString(),
                    'students' => count($rows),
                    'counts' => $counts,
                ], actor: $actor);

                return ['sheet' => $sheet, 'created' => true];
            }, 3);
        } catch (QueryException $e) {
            // A parallel submit won the race for the unique key: return its sheet instead of failing.
            if (($e->errorInfo[0] ?? null) === '23000' && ($sheet = $this->sheetFor($section, $date)) !== null) {
                return ['sheet' => $sheet, 'created' => false];
            }

            throw $e;
        }
    }

    /**
     * @param  array<int|string, array{status?: string, remark?: ?string}>  $entries
     * @param  \Illuminate\Support\Collection<int, StudentEnrollment>  $roster
     * @return array<int, array{status: string, remark: ?string}>
     */
    private function normaliseEntries(array $entries, \Illuminate\Support\Collection $roster): array
    {
        if ($roster->isEmpty()) {
            throw ValidationException::withMessages(['attendance' => 'No students are enrolled in this section yet.']);
        }

        $out = [];
        foreach ($entries as $enrollmentId => $entry) {
            $enrollmentId = (int) $enrollmentId;
            $status = AttendanceStatus::tryFrom((string) ($entry['status'] ?? ''));

            if (! $roster->has($enrollmentId) || $status === null) {
                throw ValidationException::withMessages(['attendance' => 'The class list changed while you were marking. Reload the page and try again.']);
            }

            $remark = trim((string) ($entry['remark'] ?? ''));
            $out[$enrollmentId] = ['status' => $status->value, 'remark' => $remark === '' ? null : mb_substr($remark, 0, 255)];
        }

        $missing = $roster->keys()->diff(array_keys($out));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['attendance' => 'Every student needs a status ('.$missing->count().' missing). Reload the page and try again.']);
        }

        return $out;
    }

    /** @param  \Illuminate\Support\Collection<int, StudentEnrollment>  $roster */
    private function assertNotMarkedElsewhere(\Illuminate\Support\Collection $roster, CarbonImmutable $date): void
    {
        $already = StudentAttendance::query()
            ->whereDate('date', $date->toDateString())
            ->where('session', AttendanceSheet::SESSION_DAY)
            ->whereIn('student_id', $roster->pluck('student_id'))
            ->with('student:id,first_name,last_name')
            ->first();

        if ($already !== null) {
            throw ValidationException::withMessages([
                'attendance' => $already->student->fullName().' is already marked for this date in another section.',
            ]);
        }
    }

    /**
     * @param  list<int>|null  $ids  null = every section
     * @return Collection<int, Section>
     */
    public function sections(?array $ids): Collection
    {
        return Section::query()
            ->with('schoolClass:id,name,sort_order')
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('id', $ids))
            ->get()
            ->sortBy(fn (Section $section) => [$section->schoolClass?->sort_order ?? 0, $section->schoolClass?->name, $section->sort_order, $section->name])
            ->values();
    }

    /** @return Builder<StudentEnrollment> */
    public function rosterQuery(AcademicYear $year, CarbonImmutable $date): Builder
    {
        return StudentEnrollment::query()
            ->where('academic_year_id', $year->getKey())
            ->where('status', EnrollmentStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('joined_on')->orWhereDate('joined_on', '<=', $date->toDateString()))
            ->whereHas('student', fn (Builder $q) => $q->where('status', StudentStatus::Active->value));
    }

    /**
     * @param  list<int>  $sectionIds
     * @return array<int, int> section id => students on the roster
     */
    private function rosterCounts(array $sectionIds, CarbonImmutable $date): array
    {
        $year = $this->context->academicYear();

        if ($year === null || $sectionIds === []) {
            return [];
        }

        return $this->rosterQuery($year, $date)
            ->whereIn('section_id', $sectionIds)
            ->selectRaw('section_id, COUNT(*) as aggregate')
            ->groupBy('section_id')
            ->pluck('aggregate', 'section_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * @param  list<int>  $sheetIds
     * @return array<int, array<string, int>> sheet id => status => count
     */
    private function statusCountsBySheet(array $sheetIds): array
    {
        if ($sheetIds === []) {
            return [];
        }

        $out = [];
        StudentAttendance::query()
            ->whereIn('attendance_sheet_id', $sheetIds)
            ->selectRaw('attendance_sheet_id, status, COUNT(*) as aggregate')
            ->groupBy('attendance_sheet_id', 'status')
            ->toBase()
            ->get()
            ->each(function ($row) use (&$out): void {
                $out[(int) $row->attendance_sheet_id][$row->status] = (int) $row->aggregate;
            });

        return $out;
    }
}
