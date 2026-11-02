<?php

namespace App\Enums\Identity;

enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
        };
    }
}
