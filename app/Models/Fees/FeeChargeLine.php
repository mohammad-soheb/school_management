<?php

namespace App\Models\Fees;

use App\Enums\Fees\AdjustmentStatus;
use App\Models\Fees\Concerns\ImmutableLedgerRow;
use App\Models\Students\Student;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One dated obligation (head, instalment, amount, due date). Everything else points at it: adjustments change what it
 * owes, allocations settle it. Insert-only; the outstanding amount is always derived (AllocationService).
 *
 * @property int|null $due_paise          set by AllocationService::lineBalances()
 * @property int|null $allocated_paise
 * @property int|null $outstanding_paise
 */
#[Fillable(['fee_charge_id', 'student_id', 'fee_head_id', 'fee_plan_line_id', 'installment_no', 'description', 'amount_paise', 'due_on'])]
class FeeChargeLine extends Model
{
    use BelongsToSchool, ImmutableLedgerRow;

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'installment_no' => 'integer',
            'due_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<FeeCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(FeeCharge::class, 'fee_charge_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<FeeHead, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }

    /** @return BelongsTo<FeePlanLine, $this> */
    public function planLine(): BelongsTo
    {
        return $this->belongsTo(FeePlanLine::class, 'fee_plan_line_id');
    }

    /** @return HasMany<FeeAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(FeeAllocation::class);
    }

    /** @return HasMany<FeeAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(FeeAdjustment::class);
    }

    /** @return HasMany<FeeAdjustment, $this> */
    public function approvedAdjustments(): HasMany
    {
        return $this->hasMany(FeeAdjustment::class)->where('status', AdjustmentStatus::Approved->value);
    }
}
