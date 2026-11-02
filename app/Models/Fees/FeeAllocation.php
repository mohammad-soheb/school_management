<?php

namespace App\Models\Fees;

use App\Enums\Fees\AllocationKind;
use App\Models\Fees\Concerns\ImmutableLedgerRow;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "x paise of payment P settle line L" (FR-411). Signed; reversals add negative rows. Never moved or edited. */
#[Fillable(['fee_payment_id', 'fee_charge_line_id', 'student_id', 'amount_paise', 'kind', 'allocated_on', 'fee_payment_reversal_id', 'created_by'])]
class FeeAllocation extends Model
{
    use BelongsToSchool, ImmutableLedgerRow;

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'kind' => AllocationKind::class,
            'allocated_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<FeePayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    /** @return BelongsTo<FeeChargeLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(FeeChargeLine::class, 'fee_charge_line_id');
    }
}
