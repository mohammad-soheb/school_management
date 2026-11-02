<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionItemState;
use App\Enums\Promotion\PromotionOutcome;
use App\Enums\Students\EnrollmentStatus;
use App\Enums\Students\StudentStatus;
use App\Models\Promotion\PromotionRun;
use App\Models\Promotion\PromotionRunItem;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\Section;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrections after a run is committed (FR-2B07; docs/promotion/ROLLOVER_RULES.md, rules C1-C6).
 *
 *   correct()  change one student's outcome, target section or roll no
 *   revert()   undo one student's promotion: the source enrollment becomes active again
 *
 * Both need both years open (a locked year is reopened first, with a reason, by promotion.reopen), and refuse to
 * move or void a target-year enrollment that already has records against it (EnrollmentUsageInspector).
 * Nothing is deleted: a target enrollment that is taken back is voided (RolloverWriter::void()).
 */
class PromotionCorrectionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PromotionPlacement $placement,
        private readonly RolloverWriter $writer,
        private readonly EnrollmentUsageInspector $usage,
    ) {}

    /**
     * @param  array{outcome: string, target_section_id?: int|string|null, target_roll_no?: string|null, reason: string}  $data
     */
    public function correct(PromotionRunItem $item, array $data, User $actor): PromotionRunItem
    {
        return DB::transaction(function () use ($item, $data, $actor): PromotionRunItem {
            [$run, $item, $from, $to, $source, $student, $target] = $this->lockApplied($item);

            $old = $item->outcome;
            $new = PromotionOutcome::from($data['outcome']);
            $reason = trim($data['reason']);
            $before = $this->snapshot($item);
            $created = $target !== null && (int) $target->promotion_run_id === (int) $run->getKey() && ! $this->writer->isVoided($target);

            $targetClassId = $this->placement->targetClassId($new, $item->fromClass);
            if ($new === PromotionOutcome::Promote && $targetClassId === null) {
                throw ValidationException::withMessages(['outcome' => "{$item->fromClass->name} has no next class, so the student cannot be promoted."]);
            }

            $sectionId = null;
            $rollNo = null;
            if ($targetClassId !== null) {
                $sections = $this->placement->sectionsFor([$targetClassId])->get($targetClassId, collect());
                $requested = $data['target_section_id'] ?? null;
                $sectionId = $requested !== null && $requested !== '' && $sections->contains('id', (int) $requested)
                    ? (int) $requested
                    : $this->placement->defaultSectionId($sections, $source->section_id ? Section::query()->find($source->section_id) : null, $targetClassId === (int) $item->from_class_id);
                $rollNo = isset($data['target_roll_no']) && trim((string) $data['target_roll_no']) !== '' ? trim((string) $data['target_roll_no']) : null;
            }

            if ($old->createsEnrollment() && $target !== null && ! $created) {
                // C4: the student was linked to an enrollment someone else made; this run does not own it.
                throw ValidationException::withMessages(['outcome' => 'This student was linked to an enrollment that was created outside this run. Change that enrollment from the student profile instead.']);
            }

            if ($new->createsEnrollment()) {
                if ($old->createsEnrollment()) {
                    $moves = (int) $target->school_class_id !== $targetClassId || (int) $target->section_id !== (int) $sectionId;
                    if ($moves) {
                        $this->assertUnused($target);
                    }
                    $target->fill(['school_class_id' => $targetClassId, 'section_id' => $sectionId, 'roll_no' => $rollNo])->save();
                } else {
                    $this->restoreStudent($item, $student, $old, $source, $reason, $actor);
                    [$target, $placed] = $this->writer->place($source, $run, $to, $targetClassId, $sectionId, $rollNo);
                    if ($placed === RolloverWriter::PLACED_CONFLICT) {
                        throw ValidationException::withMessages(['outcome' => "The student already has an enrollment in {$to->name}. Resolve it from the student profile first."]);
                    }
                }
            } else {
                if ($old->createsEnrollment()) {
                    if ($student->status !== StudentStatus::Active) {
                        throw ValidationException::withMessages(['outcome' => "The student's status is now {$student->status->label()}. Change it from the student profile first."]);
                    }
                    $this->assertUnused($target);
                    $this->writer->void($target);
                    $item->previous_student_status = $student->status->value;
                } elseif ($student->status !== $old->studentStatus()) {
                    throw ValidationException::withMessages(['outcome' => "The student's status was changed after the run (now {$student->status->label()}). Correct it from the student profile."]);
                }

                $leftOn = $this->placement->leftOn($new, $from, $source, school_today());
                $this->writer->changeStudentStatus($student, $new->studentStatus(), $source, $leftOn, "Promotion correction (run #{$run->getKey()}): {$reason}", $actor);
            }

            $source->fill([
                'status' => $new->enrollmentStatus(),
                'left_on' => $this->placement->leftOn($new, $from, $source, school_today()),
            ])->save();

            $item->fill([
                'outcome' => $new,
                'target_class_id' => $targetClassId,
                'target_section_id' => $sectionId,
                'target_roll_no' => $rollNo,
                'target_enrollment_id' => $new->createsEnrollment() ? $target?->getKey() : $item->target_enrollment_id,
                'corrected_at' => now(),
            ]);

            if (! $item->isDirty(['outcome', 'target_class_id', 'target_section_id', 'target_roll_no'])) {
                throw ValidationException::withMessages(['outcome' => 'Nothing to change: choose a different outcome, section or roll number.']);
            }

            $item->save();
            $this->bumpCount($run, 'corrected');

            [$oldDiff, $newDiff] = $this->audit->diff($before, $this->snapshot($item));
            $this->audit->record('promotion.item_corrected', $item, $oldDiff, $newDiff, [
                'run_id' => $run->getKey(),
                'student_id' => $student->getKey(),
                'reason' => $reason,
            ], $actor);

            return $item;
        });
    }

    /** C5: undo one student's promotion. The source enrollment becomes active again; the student keeps no target-year place made by this run. */
    public function revert(PromotionRunItem $item, string $reason, User $actor): PromotionRunItem
    {
        return DB::transaction(function () use ($item, $reason, $actor): PromotionRunItem {
            [$run, $item, , , $source, $student, $target] = $this->lockApplied($item);
            $outcome = $item->outcome;
            $reason = trim($reason);

            if ($outcome->createsEnrollment() && $target !== null && (int) $target->promotion_run_id === (int) $run->getKey() && ! $this->writer->isVoided($target)) {
                $this->assertUnused($target);
                $this->writer->void($target);
            }

            if (! $outcome->createsEnrollment()) {
                $this->restoreStudent($item, $student, $outcome, $source, $reason, $actor);
            }

            $source->fill(['status' => EnrollmentStatus::Active, 'left_on' => null])->save();

            $item->fill(['state' => PromotionItemState::Reverted, 'corrected_at' => now()])->save();
            $this->bumpCount($run, 'reverted');

            $this->audit->record('promotion.item_reverted', $item, ['state' => 'applied', 'outcome' => $outcome->value], ['state' => 'reverted'], [
                'run_id' => $run->getKey(),
                'student_id' => $student->getKey(),
                'reason' => $reason,
            ], $actor);

            return $item;
        });
    }

    /**
     * Lock the run, item, source enrollment, student and target enrollment, and check the C1/C2 preconditions.
     *
     * @return array{0: PromotionRun, 1: PromotionRunItem, 2: AcademicYear, 3: AcademicYear, 4: StudentEnrollment, 5: Student, 6: StudentEnrollment|null}
     */
    private function lockApplied(PromotionRunItem $item): array
    {
        /** @var PromotionRun $run */
        $run = PromotionRun::query()->whereKey($item->promotion_run_id)->lockForUpdate()->firstOrFail();
        /** @var PromotionRunItem $item */
        $item = PromotionRunItem::query()->with('fromClass')->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

        if (! $run->isCommitted() || ! $item->isApplied()) {
            throw ValidationException::withMessages(['outcome' => 'Only students whose promotion was applied by a committed run can be corrected.']);
        }

        $from = AcademicYear::query()->whereKey($run->from_academic_year_id)->lockForUpdate()->firstOrFail();
        $to = AcademicYear::query()->whereKey($run->to_academic_year_id)->lockForUpdate()->firstOrFail();

        foreach ([$from, $to] as $year) {
            if ($year->isLocked()) {
                throw ValidationException::withMessages(['outcome' => "{$year->name} is locked. Reopen it (with a reason) before correcting this student."]);
            }
        }

        $source = StudentEnrollment::query()->whereKey($item->student_enrollment_id)->lockForUpdate()->firstOrFail();
        $student = Student::query()->whereKey($item->student_id)->lockForUpdate()->firstOrFail();
        $target = $item->target_enrollment_id === null
            ? null
            : StudentEnrollment::query()->whereKey($item->target_enrollment_id)->lockForUpdate()->first();

        if ($source->status !== $item->outcome->enrollmentStatus()) {
            throw ValidationException::withMessages(['outcome' => "The {$from->name} enrollment was changed after the run (now {$source->status->label()}). Correct it from the student profile."]);
        }

        return [$run, $item, $from, $to, $source, $student, $target];
    }

    /** C3: a target enrollment with attendance, fees, marks ... (or a later enrollment) cannot be moved or voided. */
    private function assertUnused(StudentEnrollment $target): void
    {
        $records = $this->usage->recordsAgainst($target);

        if ($records !== []) {
            $list = collect($records)->map(fn (int $n, string $where) => str_replace('_', ' ', $where)." ({$n})")->implode(', ');

            throw ValidationException::withMessages([
                'outcome' => "Records already exist against the new enrollment: {$list}. Correct those first, or change only the roll number.",
            ]);
        }
    }

    /** Put the permanent status back to what it was before the run (only if nobody changed it since). */
    private function restoreStudent(PromotionRunItem $item, Student $student, PromotionOutcome $old, StudentEnrollment $source, string $reason, User $actor): void
    {
        if ($student->status !== $old->studentStatus()) {
            throw ValidationException::withMessages(['outcome' => "The student's status was changed after the run (now {$student->status->label()}). Correct it from the student profile."]);
        }

        $previous = StudentStatus::tryFrom((string) $item->previous_student_status) ?? StudentStatus::Active;

        $this->writer->changeStudentStatus($student, $previous, $source, school_today()->toDateString(), "Promotion correction (run #{$item->promotion_run_id}): {$reason}", $actor);
    }

    private function bumpCount(PromotionRun $run, string $key): void
    {
        $counts = $run->counts ?? [];
        $counts[$key] = (int) ($counts[$key] ?? 0) + 1;
        $run->forceFill(['counts' => $counts])->save();
    }

    /** @return array<string, mixed> */
    private function snapshot(PromotionRunItem $item): array
    {
        return [
            'outcome' => $item->outcome->value,
            'target_class_id' => $item->target_class_id,
            'target_section_id' => $item->target_section_id,
            'target_roll_no' => $item->target_roll_no,
        ];
    }
}
