<?php

namespace App\Enums\Students;

enum GuardianRelation: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Guardian => 'Guardian',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> value => label, for select inputs */
    public static function options(): array
    {
        return array_column(array_map(fn (self $r) => ['v' => $r->value, 'l' => $r->label()], self::cases()), 'l', 'v');
    }
}
