<?php

namespace App\Enums\Fees;

/** Derived status of a charge line (design §2.1). Never stored, so it can never go stale. */
enum LineStatus: string
{
    case Due = 'due';
    case Overdue = 'overdue';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Waived = 'waived';
    case CarriedForward = 'carried_forward';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'Due',
            self::Overdue => 'Overdue',
            self::PartiallyPaid => 'Partly paid',
            self::Paid => 'Paid',
            self::Waived => 'Waived',
            self::CarriedForward => 'Carried forward',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Due => 'info',
            self::Overdue => 'danger',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Waived => 'brand',
            self::CarriedForward => 'neutral',
        };
    }

    public static function derive(int $outstanding, int $allocated, int $reductions, bool $carried, bool $pastDue): self
    {
        if ($outstanding > 0) {
            if ($pastDue) {
                return self::Overdue;
            }

            return $allocated > 0 ? self::PartiallyPaid : self::Due;
        }

        if ($carried) {
            return self::CarriedForward;
        }

        return $allocated > 0 || $reductions === 0 ? self::Paid : self::Waived;
    }
}
