<?php

namespace App\Enums\Students;

enum StudentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Transferred = 'transferred';
    case Withdrawn = 'withdrawn';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Transferred => 'Transferred',
            self::Withdrawn => 'Withdrawn',
            self::Graduated => 'Graduated',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'neutral',
            self::Transferred => 'info',
            self::Withdrawn => 'warning',
            self::Graduated => 'brand',
        };
    }

    /** Students who still attend, appear on rosters and are charged fees. */
    public function isOnRoll(): bool
    {
        return $this === self::Active;
    }
}
