<?php

namespace App\Enums\Platform;

enum SchoolStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Archived => 'Archived',
        };
    }

    /** Badge tone understood by <x-ui.badge>. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Archived => 'neutral',
        };
    }
}
