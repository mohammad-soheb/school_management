<?php

namespace App\Enums\Students;

/**
 * The status changes a school user can make from the student profile. Graduation is done by year-end promotion.
 */
enum StudentStatusAction: string
{
    case Withdraw = 'withdraw';
    case Transfer = 'transfer';
    case Deactivate = 'inactive';
    case Reactivate = 'reactivate';

    public function label(): string
    {
        return match ($this) {
            self::Withdraw => 'Withdraw',
            self::Transfer => 'Transfer out',
            self::Deactivate => 'Mark inactive',
            self::Reactivate => 'Reactivate',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Withdraw => 'The student has left the school. This year\'s enrollment is closed on the date you give.',
            self::Transfer => 'The student moved to another school. This year\'s enrollment is closed on the date you give.',
            self::Deactivate => 'Temporarily off the roll (long leave, pending dues). The class place is kept.',
            self::Reactivate => 'The student is back on the roll. A closed enrollment for this year is reopened.',
        };
    }

    public function targetStatus(): StudentStatus
    {
        return match ($this) {
            self::Withdraw => StudentStatus::Withdrawn,
            self::Transfer => StudentStatus::Transferred,
            self::Deactivate => StudentStatus::Inactive,
            self::Reactivate => StudentStatus::Active,
        };
    }

    /** The enrollment status the current-year enrollment moves to, or null when the enrollment stays as it is. */
    public function enrollmentStatus(): ?EnrollmentStatus
    {
        return match ($this) {
            self::Withdraw => EnrollmentStatus::Withdrawn,
            self::Transfer => EnrollmentStatus::Transferred,
            self::Deactivate => null,
            self::Reactivate => EnrollmentStatus::Active,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Withdraw, self::Transfer => 'danger',
            self::Deactivate => 'warning',
            self::Reactivate => 'success',
        };
    }

    /**
     * Actions that make sense for a student currently in $status. Graduation is final here: it is undone, if ever,
     * by the promotion module.
     *
     * @return list<self>
     */
    public static function availableFor(StudentStatus $status): array
    {
        return match ($status) {
            StudentStatus::Active => [self::Withdraw, self::Transfer, self::Deactivate],
            StudentStatus::Inactive => [self::Reactivate, self::Withdraw, self::Transfer],
            StudentStatus::Withdrawn, StudentStatus::Transferred => [self::Reactivate],
            StudentStatus::Graduated => [],
        };
    }

    /** @return array<string, string> value => label, for select inputs */
    public static function options(StudentStatus $status): array
    {
        $options = [];
        foreach (self::availableFor($status) as $action) {
            $options[$action->value] = $action->label();
        }

        return $options;
    }
}
