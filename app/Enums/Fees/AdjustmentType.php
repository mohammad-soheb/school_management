<?php

namespace App\Enums\Fees;

/** Approved changes to what a charge line owes (FR-404). Carry-forward is created by the system only. */
enum AdjustmentType: string
{
    case Discount = 'discount';
    case Scholarship = 'scholarship';
    case Waiver = 'waiver';
    case Fine = 'fine';
    case CarryForward = 'carry_forward';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'Discount',
            self::Scholarship => 'Scholarship',
            self::Waiver => 'Waiver',
            self::Fine => 'Late fine',
            self::CarryForward => 'Carried forward',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Discount, self::Scholarship => 'info',
            self::Waiver => 'brand',
            self::Fine => 'warning',
            self::CarryForward => 'neutral',
        };
    }

    /** +1 raises the due (fine), -1 lowers it. */
    public function sign(): int
    {
        return $this === self::Fine ? 1 : -1;
    }

    public function isReduction(): bool
    {
        return $this->sign() < 0;
    }

    /** Types a person may request (carry-forward is system-only). @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach ([self::Discount, self::Scholarship, self::Waiver, self::Fine] as $type) {
            $out[$type->value] = $type->label();
        }

        return $out;
    }
}
