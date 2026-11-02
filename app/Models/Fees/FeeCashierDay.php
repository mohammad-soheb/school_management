<?php

namespace App\Models\Fees;

use App\Enums\Fees\CashierDayStatus;
use App\Models\User;
use App\Policies\Fees\FeeCashierDayPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One business date of collection (FR-407, FR-414). Open until closed; closing snapshots the totals and locks the
 * date. A closed day may only receive its one review sign-off. Nothing is mass-assignable: CashierCloseService
 * writes every column.
 */
#[UsePolicy(FeeCashierDayPolicy::class)]
class FeeCashierDay extends Model
{
    use BelongsToSchool;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'business_date' => 'immutable_date',
            'status' => CashierDayStatus::class,
            'cash_paise' => 'integer',
            'upi_paise' => 'integer',
            'bank_transfer_paise' => 'integer',
            'cheque_paise' => 'integer',
            'other_paise' => 'integer',
            'gross_paise' => 'integer',
            'reversed_paise' => 'integer',
            'net_paise' => 'integer',
            'receipts_count' => 'integer',
            'reversals_count' => 'integer',
            'counted_cash_paise' => 'integer',
            'difference_paise' => 'integer',
            'breakdown' => 'array',
            'closed_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (FeeCashierDay $day): void {
            $wasClosed = $day->getRawOriginal('status') === CashierDayStatus::Closed->value;
            $reviewOnly = array_diff(array_keys($day->getDirty()), ['reviewed_by', 'reviewed_at', 'review_note', 'updated_at']) === [];

            if ($wasClosed && (! $reviewOnly || $day->getRawOriginal('reviewed_at') !== null)) {
                throw new Exceptions\ImmutableRecordException('A closed cashier day cannot be changed.');
            }
        });

        static::deleting(fn () => throw new Exceptions\ImmutableRecordException('A cashier day can never be deleted.'));
    }

    public function isClosed(): bool
    {
        return $this->status === CashierDayStatus::Closed;
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
