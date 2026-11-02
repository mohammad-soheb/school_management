<?php

namespace App\Enums\Academics;

/**
 * The small, fixed report card catalogue (master plan §18.2: no arbitrary designer layouts). Schools choose one and
 * configure the fields (logo, signatures, remarks, rank, grade points) on Exams > Settings.
 */
enum ReportCardTemplate: string
{
    case Classic = 'classic';
    case Modern = 'modern';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Modern => 'Modern',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Centred school header, ruled subject table, formal black-and-white look.',
            self::Modern => 'Brand-coloured header band, rounded summary tiles, airy subject table.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
