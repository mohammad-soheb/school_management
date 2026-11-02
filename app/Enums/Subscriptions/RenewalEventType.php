<?php

namespace App\Enums\Subscriptions;

/** One line of a school's renewal history (FR-803). */
enum RenewalEventType: string
{
    case Activated = 'activated';
    case Renewed = 'renewed';
    case PlanChanged = 'plan_changed';
    case Adjusted = 'adjusted';
    case Suspended = 'suspended';
    case Resumed = 'resumed';

    public function label(): string
    {
        return match ($this) {
            self::Activated => 'Plan assigned',
            self::Renewed => 'Renewed',
            self::PlanChanged => 'Plan changed',
            self::Adjusted => 'Dates adjusted',
            self::Suspended => 'Suspended',
            self::Resumed => 'Resumed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Activated, self::Resumed => 'success',
            self::Renewed => 'brand',
            self::PlanChanged, self::Adjusted => 'info',
            self::Suspended => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Activated => 'play-circle',
            self::Renewed => 'arrow-path',
            self::PlanChanged => 'arrows-right-left',
            self::Adjusted => 'calendar-days',
            self::Suspended => 'pause-circle',
            self::Resumed => 'play-circle',
        };
    }
}
