<?php

namespace App\Enums\Subscriptions;

/** How a school paid the platform owner for its ERP subscription (verified outside the ERP). */
enum SubscriptionPaymentMode: string
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

    /** Modes whose payment can be traced only by a reference (UTR, cheque no ...). */
    public function needsReference(): bool
    {
        return in_array($this, [self::Upi, self::BankTransfer, self::Cheque], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
