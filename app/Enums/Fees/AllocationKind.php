<?php

namespace App\Enums\Fees;

/** payment: settled when the payment was posted; credit: held advance applied later; reversal: negative undo row. */
enum AllocationKind: string
{
    case Payment = 'payment';
    case Credit = 'credit';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Payment',
            self::Credit => 'Advance applied',
            self::Reversal => 'Reversal',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Payment => 'success',
            self::Credit => 'brand',
            self::Reversal => 'danger',
        };
    }
}
