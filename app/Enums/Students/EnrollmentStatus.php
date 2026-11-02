<?php

namespace App\Enums\Students;

enum EnrollmentStatus: string
{
    /** Currently attending in this academic year. */
    case Active = 'active';
    /** Year closed, moved up to the next class (a new enrollment exists for the next year). */
    case Promoted = 'promoted';
    /** Year closed, repeating the same class next year. */
    case Retained = 'retained';
    case Transferred = 'transferred';
    case Withdrawn = 'withdrawn';
    case Graduated = 'graduated';
    /** Year closed with no further outcome recorded. */
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Promoted => 'Promoted',
            self::Retained => 'Retained',
            self::Transferred => 'Transferred',
            self::Withdrawn => 'Withdrawn',
            self::Graduated => 'Graduated',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Promoted => 'brand',
            self::Retained => 'warning',
            self::Transferred => 'info',
            self::Withdrawn => 'danger',
            self::Graduated => 'brand',
            self::Completed => 'neutral',
        };
    }
}
