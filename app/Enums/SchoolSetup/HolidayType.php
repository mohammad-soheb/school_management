<?php

namespace App\Enums\SchoolSetup;

enum HolidayType: string
{
    case Holiday = 'holiday';
    case Event = 'event';
    case Exam = 'exam';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Holiday',
            self::Event => 'Event',
            self::Exam => 'Exam period',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Holiday => 'danger',
            self::Event => 'info',
            self::Exam => 'warning',
        };
    }
}
