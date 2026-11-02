<?php

namespace App\Enums\Promotion;

use App\Enums\Students\EnrollmentStatus;
use App\Enums\Students\StudentStatus;

/** What happens to one student at year end. */
enum PromotionOutcome: string
{
    case Promote = 'promote';
    case Retain = 'retain';
    case Graduate = 'graduate';
    case Transfer = 'transfer';
    case Withdraw = 'withdraw';

    public function label(): string
    {
        return match ($this) {
            self::Promote => 'Promote',
            self::Retain => 'Retain',
            self::Graduate => 'Graduate',
            self::Transfer => 'Transfer out',
            self::Withdraw => 'Withdraw',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Promote => 'success',
            self::Retain => 'warning',
            self::Graduate => 'brand',
            self::Transfer => 'info',
            self::Withdraw => 'danger',
        };
    }

    /** @return array<string, string> value => label, for selects */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** Promote and retain continue in the target year with a new enrollment; the others leave the school. */
    public function createsEnrollment(): bool
    {
        return $this === self::Promote || $this === self::Retain;
    }

    /** Status written to the closed source-year enrollment. */
    public function enrollmentStatus(): EnrollmentStatus
    {
        return match ($this) {
            self::Promote => EnrollmentStatus::Promoted,
            self::Retain => EnrollmentStatus::Retained,
            self::Graduate => EnrollmentStatus::Graduated,
            self::Transfer => EnrollmentStatus::Transferred,
            self::Withdraw => EnrollmentStatus::Withdrawn,
        };
    }

    /** Status written to the permanent student record, or null when the student stays active. */
    public function studentStatus(): ?StudentStatus
    {
        return match ($this) {
            self::Promote, self::Retain => null,
            self::Graduate => StudentStatus::Graduated,
            self::Transfer => StudentStatus::Transferred,
            self::Withdraw => StudentStatus::Withdrawn,
        };
    }

    /** Key used in run counts ("promoted", "retained", ...). */
    public function countKey(): string
    {
        return $this->enrollmentStatus()->value;
    }
}
