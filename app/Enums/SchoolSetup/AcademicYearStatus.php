<?php

namespace App\Enums\SchoolSetup;

enum AcademicYearStatus: string
{
    case Open = 'open';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Locked => 'Locked',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Locked => 'neutral',
        };
    }
}
