<?php

namespace App\Enums\Promotion;

enum PromotionRunStatus: string
{
    case Draft = 'draft';
    case Committed = 'committed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Committed => 'Committed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Committed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
