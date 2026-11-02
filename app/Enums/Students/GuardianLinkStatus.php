<?php

namespace App\Enums\Students;

enum GuardianLinkStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Revoked => 'neutral',
        };
    }
}
