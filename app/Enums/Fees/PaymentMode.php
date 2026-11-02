<?php

namespace App\Enums\Fees;

/** How money was received (FR-405). No payment gateway in V1 (FR-410). */
enum PaymentMode: string
{
    case Cash = 'cash';
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Upi => 'UPI',
            self::BankTransfer => 'Bank transfer',
            self::Cheque => 'Cheque',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Upi => 'brand',
            self::BankTransfer => 'info',
            self::Cheque => 'warning',
            self::Other => 'neutral',
        };
    }

    public function referenceLabel(): string
    {
        return match ($this) {
            self::Upi => 'UPI transaction id',
            self::BankTransfer => 'Bank reference (UTR)',
            self::Cheque => 'Cheque number',
            default => 'Reference',
        };
    }

    /**
     * Modes the school accepts (setting fees.payment_modes), value => label.
     *
     * @param  list<string>|null  $allowed
     * @return array<string, string>
     */
    public static function options(?array $allowed = null): array
    {
        $out = [];
        foreach (self::cases() as $mode) {
            if ($allowed === null || in_array($mode->value, $allowed, true)) {
                $out[$mode->value] = $mode->label();
            }
        }

        return $out;
    }
}
