<?php

namespace App\Enums\Academics;

/**
 * One publication of an exam's results for a class.
 *  active      visible (report cards, parent portal). At most one per exam + class.
 *  withdrawn   unpublished by an authorised user (reason recorded). Its snapshot rows are kept as history.
 */
enum PublicationStatus: string
{
    case Active = 'active';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Published',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Withdrawn => 'neutral',
        };
    }
}
