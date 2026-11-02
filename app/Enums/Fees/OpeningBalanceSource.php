<?php

namespace App\Enums\Fees;

/** Where an opening balance came from (FR-416). carry_forward is written by the promotion workflow only. */
enum OpeningBalanceSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case CarryForward = 'carry_forward';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual entry',
            self::Import => 'Imported from earlier system',
            self::CarryForward => 'Carried forward',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Manual => 'neutral',
            self::Import => 'info',
            self::CarryForward => 'brand',
        };
    }

    /** Sources a person may choose. @return array<string, string> */
    public static function options(): array
    {
        return [self::Manual->value => self::Manual->label(), self::Import->value => self::Import->label()];
    }
}
