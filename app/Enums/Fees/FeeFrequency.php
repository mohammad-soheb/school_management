<?php

namespace App\Enums\Fees;

/** How often a plan line is charged (FR-402). The plan line amount is the amount of ONE instalment. */
enum FeeFrequency: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One time',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::HalfYearly => 'Half-yearly',
            self::Yearly => 'Yearly',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::OneTime => 'neutral',
            self::Monthly => 'brand',
            self::Quarterly, self::HalfYearly => 'info',
            self::Yearly => 'success',
        };
    }

    /** The largest (and default) number of instalments in one academic year. */
    public function maxInstallments(): int
    {
        return match ($this) {
            self::OneTime, self::Yearly => 1,
            self::HalfYearly => 2,
            self::Quarterly => 4,
            self::Monthly => 12,
        };
    }

    /** Months between two instalments. */
    public function stepMonths(): int
    {
        return match ($this) {
            self::OneTime, self::Yearly => 12,
            self::HalfYearly => 6,
            self::Quarterly => 3,
            self::Monthly => 1,
        };
    }

    /** Periodic lines are not charged for periods before a mid-year joiner arrived. */
    public function isPeriodic(): bool
    {
        return in_array($this, [self::Monthly, self::Quarterly, self::HalfYearly], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $f) => ['v' => $f->value, 'l' => $f->label()], self::cases()), 'l', 'v');
    }
}
