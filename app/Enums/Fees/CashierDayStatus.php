<?php

namespace App\Enums\Fees;

enum CashierDayStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Closed => 'success',
        };
    }
}
