<?php

namespace App\Services\Promotion;

use App\Enums\Students\EnrollmentStatus;
use App\Enums\Students\StudentStatus;
use App\Models\Promotion\PromotionRun;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\Students\StudentStatusChange;
use App\Models\User;

/**
 * The low-level writes shared by commit and correction (docs/promotion/ROLLOVER_RULES.md, rules E1-E4).
 * Callers hold the row locks and the transaction; this class never opens one.
 */
final class RolloverWriter
{
    /** exit_reason that marks a target-year enrollment voided by a correction (E3). */
    public const VOID_REASON = 'Voided: year-end promotion corrected';

    public const PLACED_CREATED = 'created';

    public const PLACED_ADOPTED = 'adopted';

    public const PLACED_REACTIVATED = 'reactivated';

    public const PLACED_CONFLICT = 'conflict';

    /**
     * E1/E2: give the student a place in the target year.
     *  - no enrollment in the target year     -> create one (active, previous_enrollment_id, promotion_run_id)
     *  - an active enrollment already exists  -> adopt it untouched (it was made by hand, e.g. an early re-admission)
     *  - a voided enrollment exists (E3)      -> reactivate it with the new placement
     *  - any other enrollment                 -> conflict (left for a person to resolve)
     *
     * @return array{0: StudentEnrollment|null, 1: string}
     */
    public function place(StudentEnrollment $source, PromotionRun $run, AcademicYear $to, int $classId, ?int $sectionId, ?string $rollNo): array
    {
        /** @var StudentEnrollment|null $existing */
        $existing = StudentEnrollment::query()
            ->where('student_id', $source->student_id)
            ->where('academic_year_id', $to->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            $enrollment = StudentEnrollment::create([
                'student_id' => $source->student_id,
                'academic_year_id' => $to->getKey(),
                'school_class_id' => $classId,
                'section_id' => $sectionId,
                'roll_no' => $rollNo,
                'status' => EnrollmentStatus::Active,
                'joined_on' => $to->starts_on->toDateString(),
                'previous_enrollment_id' => $source->getKey(),
                'promotion_run_id' => $run->getKey(),
            ]);

            return [$enrollment, self::PLACED_CREATED];
        }

        if ($existing->status === EnrollmentStatus::Active) {
            return [$existing, self::PLACED_ADOPTED];
        }

        if ($this->isVoided($existing)) {
            $existing->fill([
                'school_class_id' => $classId,
                'section_id' => $sectionId,
                'roll_no' => $rollNo,
                'status' => EnrollmentStatus::Active,
                'joined_on' => $to->starts_on->toDateString(),
                'left_on' => null,
                'exit_reason' => null,
                'previous_enrollment_id' => $source->getKey(),
                'promotion_run_id' => $run->getKey(),
            ])->save();

            return [$existing, self::PLACED_REACTIVATED];
        }

        return [null, self::PLACED_CONFLICT];
    }

    /**
     * E3: a target-year enrollment made by the rollover is never deleted (CLAUDE.md rule 5). When a correction takes
     * it back it is closed as withdrawn on the day it started, with a marker reason, and can be reactivated later.
     */
    public function void(StudentEnrollment $target): void
    {
        $target->fill([
            'status' => EnrollmentStatus::Withdrawn,
            'left_on' => $target->joined_on?->toDateString(),
            'exit_reason' => self::VOID_REASON,
        ])->save();
    }

    public function isVoided(StudentEnrollment $enrollment): bool
    {
        return $enrollment->status === EnrollmentStatus::Withdrawn
            && $enrollment->promotion_run_id !== null
            && $enrollment->exit_reason === self::VOID_REASON;
    }

    /**
     * E4: change the permanent student status and append the history row (student_status_changes, FR-207).
     */
    public function changeStudentStatus(Student $student, StudentStatus $to, ?StudentEnrollment $enrollment, string $effectiveOn, string $reason, User $actor): void
    {
        $from = $student->status;

        if ($from === $to) {
            return;
        }

        $student->forceFill(['status' => $to])->save();

        StudentStatusChange::create([
            'student_id' => $student->getKey(),
            'student_enrollment_id' => $enrollment?->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'effective_on' => $effectiveOn,
            'reason' => mb_substr($reason, 0, 500),
            'changed_by' => $actor->getKey(),
        ]);
    }
}
