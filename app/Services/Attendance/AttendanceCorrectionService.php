<?php

namespace App\Services\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\AttendanceSheet;
use App\Models\Attendance\StaffAttendance;
use App\Models\Attendance\StudentAttendance;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The authorised correction process for submitted attendance (FR-304, FR-307).
 *
 * Records are never deleted or silently edited: every changed record gets an attendance_corrections row (old -> new,
 * reason, who) and an audit event, and the record itself keeps the last corrector and time. Only rows whose status
 * or remark actually changed are touched, so re-sending the same form changes nothing.
 */
class AttendanceCorrectionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AttendanceAccess $access,
    ) {}

    /**
     * Correct records of a submitted class register.
     *
     * @param  array<int|string, array{status?: string, remark?: ?string}>  $entries  keyed by student_attendance id
     * @return int number of records changed
     */
    public function correctSheet(AttendanceSheet $sheet, array $entries, string $reason, User $actor): int
    {
        if (! $this->access->canCorrect($actor, (int) $sheet->section_id)) {
            throw ValidationException::withMessages(['reason' => 'You are not allowed to correct attendance for this class.']);
        }

        $sheet->loadMissing('academicYear');
        if ($sheet->academicYear->isLocked()) {
            throw ValidationException::withMessages(['reason' => "The academic year {$sheet->academicYear->name} is locked; its attendance can no longer be changed."]);
        }

        $reason = $this->reason($reason);

        return DB::transaction(function () use ($sheet, $entries, $reason, $actor) {
            /** @var Collection<int, StudentAttendance> $records */
            $records = StudentAttendance::query()
                ->where('attendance_sheet_id', $sheet->getKey())
                ->whereIn('id', array_map('intval', array_keys($entries)))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $changes = $this->changes($records, $entries);

            foreach ($changes as [$record, $status, $remark]) {
                $this->apply($record, $status, $remark, $reason, $actor, AttendanceCorrection::TYPE_STUDENT, [
                    'student_id' => $record->student_id,
                ], [
                    'sheet' => $sheet->getKey(),
                    'section' => $sheet->section_id,
                ]);
            }

            return count($changes);
        });
    }

    /**
     * Correct staff attendance records of one date.
     *
     * @param  array<int|string, array{status?: string, remark?: ?string}>  $entries  keyed by staff_attendance id
     * @return int number of records changed
     */
    public function correctStaff(CarbonImmutable $date, array $entries, string $reason, User $actor): int
    {
        if (Gate::forUser($actor)->denies('attendance.staff.mark')) {
            throw ValidationException::withMessages(['reason' => 'You are not allowed to correct staff attendance.']);
        }

        $date = AttendanceCalendar::day($date);
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($date, $entries, $reason, $actor) {
            /** @var Collection<int, StaffAttendance> $records */
            $records = StaffAttendance::query()
                ->whereDate('date', $date->toDateString())
                ->whereIn('id', array_map('intval', array_keys($entries)))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $changes = $this->changes($records, $entries);

            foreach ($changes as [$record, $status, $remark]) {
                $this->apply($record, $status, $remark, $reason, $actor, AttendanceCorrection::TYPE_STAFF, [
                    'staff_profile_id' => $record->staff_profile_id,
                ], []);
            }

            return count($changes);
        });
    }

    /**
     * The correction history of a sheet, newest first.
     *
     * @return Collection<int, AttendanceCorrection>
     */
    public function historyForSheet(AttendanceSheet $sheet): Collection
    {
        $recordIds = StudentAttendance::query()->where('attendance_sheet_id', $sheet->getKey())->pluck('id');

        return AttendanceCorrection::query()
            ->where('record_type', AttendanceCorrection::TYPE_STUDENT)
            ->whereIn('record_id', $recordIds)
            ->with(['corrector:id,name', 'student:id,first_name,last_name,admission_no'])
            ->latest('id')
            ->get();
    }

    /**
     * Staff corrections made for a date, newest first.
     *
     * @return Collection<int, AttendanceCorrection>
     */
    public function historyForStaffDay(CarbonImmutable $date): Collection
    {
        return AttendanceCorrection::query()
            ->where('record_type', AttendanceCorrection::TYPE_STAFF)
            ->whereDate('date', AttendanceCalendar::day($date)->toDateString())
            ->with(['corrector:id,name', 'staffProfile:id,first_name,last_name,employee_id'])
            ->latest('id')
            ->get();
    }

    private function reason(string $reason): string
    {
        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Give a reason for the correction (at least 5 characters).']);
        }

        return mb_substr($reason, 0, 500);
    }

    /**
     * @param  Collection<int, StudentAttendance|StaffAttendance>  $records
     * @param  array<int|string, array{status?: string, remark?: ?string}>  $entries
     * @return list<array{0: StudentAttendance|StaffAttendance, 1: AttendanceStatus, 2: ?string}>
     */
    private function changes(Collection $records, array $entries): array
    {
        $changes = [];

        foreach ($entries as $id => $entry) {
            $record = $records->get((int) $id);
            $status = AttendanceStatus::tryFrom((string) ($entry['status'] ?? ''));

            if ($record === null || $status === null) {
                throw ValidationException::withMessages(['attendance' => 'The list changed while you were editing. Reload the page and try again.']);
            }

            $remark = trim((string) ($entry['remark'] ?? ''));
            $remark = $remark === '' ? null : mb_substr($remark, 0, 255);

            if ($record->status !== $status || $record->remark !== $remark) {
                $changes[] = [$record, $status, $remark];
            }
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['attendance' => 'Nothing was changed. Change at least one status or remark.']);
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $subjectColumns
     * @param  array<string, mixed>  $meta
     */
    private function apply(StudentAttendance|StaffAttendance $record, AttendanceStatus $status, ?string $remark, string $reason, User $actor, string $type, array $subjectColumns, array $meta): void
    {
        $old = ['status' => $record->status->value, 'remark' => $record->remark];
        $new = ['status' => $status->value, 'remark' => $remark];

        AttendanceCorrection::create([
            'record_type' => $type,
            'record_id' => $record->getKey(),
            ...$subjectColumns,
            'date' => $record->date->toDateString(),
            'old_status' => $old['status'],
            'new_status' => $new['status'],
            'old_remark' => $old['remark'],
            'new_remark' => $new['remark'],
            'reason' => $reason,
            'corrected_by' => $actor->getKey(),
        ]);

        $record->forceFill([
            'status' => $status,
            'remark' => $remark,
            'corrected_by' => $actor->getKey(),
            'corrected_at' => now(),
        ])->save();

        $this->audit->record('attendance.corrected', $record, $old, $new, [
            'type' => $type,
            'date' => $record->date->toDateString(),
            'reason' => $reason,
            ...$meta,
        ], $actor);
    }
}
