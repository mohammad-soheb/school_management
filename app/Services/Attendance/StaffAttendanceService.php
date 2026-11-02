<?php

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\Staff\StaffStatus;
use App\Models\Attendance\StaffAttendance;
use App\Models\Staff\StaffProfile;
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
 * Daily staff attendance (FR-302): one sheet per date listing every active staff member.
 *
 * Submitting saves a status for every staff member who has none yet for that date; staff already marked are left
 * untouched (changes go through AttendanceCorrectionService). A double submit therefore creates nothing new, and a
 * staff member who joins later in the day can still be added to an already-marked date.
 */
class StaffAttendanceService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly AttendanceCalendar $calendar,
    ) {}

    /**
     * The sheet for a date: each active staff member with their record (if marked).
     *
     * @return array{
     *     date: CarbonImmutable, non_working: ?string, marking_error: ?string,
     *     rows: list<array{staff: StaffProfile, record: ?StaffAttendance}>,
     *     marked: int, unmarked: int, counts: array<string, int>, summary: array<string, mixed>
     * }
     */
    public function day(CarbonImmutable $date): array
    {
        $date = AttendanceCalendar::day($date);

        $records = StaffAttendance::query()
            ->whereDate('date', $date->toDateString())
            ->with(['marker:id,name', 'corrector:id,name'])
            ->get()
            ->keyBy('staff_profile_id');

        // Everyone on the roster, plus anyone who already has a record for the date (e.g. left the school since).
        $staff = $this->rosterQuery($date)
            ->orWhereIn('id', $records->keys()->all())
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get(['id', 'employee_id', 'first_name', 'last_name', 'designation', 'department', 'status']);

        $rows = [];
        $counts = [];
        foreach ($staff as $member) {
            $record = $records->get($member->getKey());
            if ($record !== null) {
                $counts[$record->status->value] = ($counts[$record->status->value] ?? 0) + 1;
            }
            $rows[] = ['staff' => $member, 'record' => $record];
        }

        $marked = $records->count();

        return [
            'date' => $date,
            'non_working' => $this->calendar->nonWorkingReason($date),
            'marking_error' => $this->markingError($date),
            'rows' => $rows,
            'marked' => $marked,
            'unmarked' => count($rows) - $marked,
            'counts' => $counts,
            'summary' => AttendanceCalculator::forSchool($this->context->requireSchool())->summarise($counts),
            'previous' => $this->calendar->adjacentWorkingDay($date, -1),
            'next' => $this->calendar->adjacentWorkingDay($date, 1),
        ];
    }

    /** Why staff attendance cannot be taken for $date (null = it can). */
    public function markingError(CarbonImmutable $date): ?string
    {
        $date = AttendanceCalendar::day($date);

        if ($date->gt(AttendanceCalendar::today())) {
            return 'Attendance cannot be marked for a future date.';
        }

        $year = $this->context->academicYear();
        if ($year === null) {
            return 'No current academic year is set. Ask the school admin to set one up first.';
        }

        if (! $year->contains($date)) {
            return "This date is outside the current academic year ({$year->name}).";
        }

        if (($reason = $this->calendar->nonWorkingReason($date)) !== null) {
            return "{$date->format('D, d M Y')} is not a school day: {$reason}.";
        }

        return null;
    }

    /**
     * Save statuses for staff not yet marked on $date.
     *
     * @param  array<int|string, array{status?: string, remark?: ?string}>  $entries  keyed by staff_profile_id
     * @return array{created: int, skipped: int}
     */
    public function submit(CarbonImmutable $date, array $entries, User $actor): array
    {
        $date = AttendanceCalendar::day($date);

        if (($error = $this->markingError($date)) !== null) {
            throw ValidationException::withMessages(['date' => $error]);
        }

        try {
            return DB::transaction(function () use ($date, $entries, $actor) {
                $roster = $this->rosterQuery($date)->get(['id'])->keyBy('id');

                $existing = StaffAttendance::query()
                    ->whereDate('date', $date->toDateString())
                    ->lockForUpdate()
                    ->pluck('staff_profile_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $now = now();
                $schoolId = $this->context->requireId();
                $rows = [];
                $skipped = 0;

                foreach ($entries as $staffId => $entry) {
                    $staffId = (int) $staffId;
                    $status = AttendanceStatus::tryFrom((string) ($entry['status'] ?? ''));

                    if (! $roster->has($staffId) || $status === null) {
                        throw ValidationException::withMessages(['attendance' => 'The staff list changed while you were marking. Reload the page and try again.']);
                    }

                    if (in_array($staffId, $existing, true)) {
                        $skipped++;

                        continue;
                    }

                    $remark = trim((string) ($entry['remark'] ?? ''));
                    $rows[] = [
                        'school_id' => $schoolId,
                        'staff_profile_id' => $staffId,
                        'date' => $date->toDateString(),
                        'status' => $status->value,
                        'remark' => $remark === '' ? null : mb_substr($remark, 0, 255),
                        'marked_by' => $actor->getKey(),
                        'marked_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    StaffAttendance::query()->insert($rows);

                    $this->audit->record('attendance.staff_submitted', null, new: [
                        'date' => $date->toDateString(),
                        'staff' => count($rows),
                        'counts' => array_count_values(array_column($rows, 'status')),
                    ], actor: $actor);
                }

                return ['created' => count($rows), 'skipped' => $skipped];
            }, 3);
        } catch (QueryException $e) {
            // A parallel submit inserted the same staff/date first: nothing of ours was saved, theirs stands.
            if (($e->errorInfo[0] ?? null) === '23000') {
                return ['created' => 0, 'skipped' => count($entries)];
            }

            throw $e;
        }
    }

    /**
     * Staff expected at work on $date: active, joined on or before it.
     *
     * @return Builder<StaffProfile>
     */
    public function rosterQuery(CarbonImmutable $date): Builder
    {
        return StaffProfile::query()->where(fn (Builder $q) => $q
            ->where('status', StaffStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('joined_on')->orWhereDate('joined_on', '<=', $date->toDateString())));
    }

    /**
     * Records of one staff member over a range (for a profile panel).
     *
     * @return Collection<int, StaffAttendance>
     */
    public function recordsFor(StaffProfile $staff, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return StaffAttendance::query()
            ->where('staff_profile_id', $staff->getKey())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();
    }
}
