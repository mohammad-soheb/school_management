<?php

namespace App\Enums\Academics;

/** One section x one exam subject: saved as a draft, or submitted as complete by the teacher. */
enum MarkSheetStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Submitted => 'success',
        };
    }
}
