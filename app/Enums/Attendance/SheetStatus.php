<?php

namespace App\Enums\Attendance;

/**
 * Lifecycle of a class register. V1 has no drafts: a sheet exists only once it is submitted, after which it changes
 * only through audited corrections. The column is a string so a later "locked" (month close) state needs no migration.
 */
enum SheetStatus: string
{
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'success',
        };
    }
}
