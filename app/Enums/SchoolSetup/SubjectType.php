<?php

namespace App\Enums\SchoolSetup;

enum SubjectType: string
{
    case Scholastic = 'scholastic';
    case CoScholastic = 'co_scholastic';

    public function label(): string
    {
        return match ($this) {
            self::Scholastic => 'Scholastic',
            self::CoScholastic => 'Co-scholastic',
        };
    }
}
