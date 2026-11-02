<?php

namespace App\Enums\Staff;

enum StaffStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Left => 'Left school',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Left => 'neutral',
        };
    }
}
