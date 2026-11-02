<?php

namespace App\Enums\Shared;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> value => label, for select inputs */
    public static function options(): array
    {
        return array_column(array_map(fn (self $g) => ['v' => $g->value, 'l' => $g->label()], self::cases()), 'l', 'v');
    }
}
