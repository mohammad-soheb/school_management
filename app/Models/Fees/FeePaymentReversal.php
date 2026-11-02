<?php

namespace App\Models\Fees;

use App\Models\Fees\Concerns\ImmutableLedgerRow;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The compensating event for a mistaken payment (FR-409, FR-415): who, when, why. One per payment. */
#[Fillable(['fee_payment_id', 'amount_paise', 'reason', 'reversed_on', 'reversed_by'])]
class FeePaymentReversal extends Model
{
    use BelongsToSchool, ImmutableLedgerRow;

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'reversed_on' => 'immutable_date'];
    }

    /** @return BelongsTo<FeePayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    /** @return HasMany<FeeAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(FeeAllocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
