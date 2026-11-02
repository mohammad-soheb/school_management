<?php

namespace App\Enums\Fees;

enum ChargeSource: string
{
    case Plan = 'plan';
    case OpeningBalance = 'opening_balance';

    public function label(): string
    {
        return match ($this) {
            self::Plan => 'Fee plan',
            self::OpeningBalance => 'Opening balance',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Plan => 'neutral',
            self::OpeningBalance => 'info',
        };
    }
}
