<?php

namespace App\Enums\Communication;

/** Homework is never hard-deleted: it is archived (hidden from teachers' lists, parents and feeds) and can be restored. */
enum HomeworkStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Archived => 'neutral',
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
