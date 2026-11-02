<?php

namespace App\Enums\Fees;

/** A payment is posted, and may later be reversed (terminal). There is no draft and no delete. */
enum PaymentStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Posted => 'Posted',
            self::Reversed => 'Reversed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Posted => 'success',
            self::Reversed => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [self::Posted->value => self::Posted->label(), self::Reversed->value => self::Reversed->label()];
    }
}
