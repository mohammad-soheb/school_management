<?php

namespace App\Models\Fees;

use App\Enums\Fees\FeeFrequency;
use App\Policies\Fees\FeeSetupPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\Fees\FeePlanLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One head in a plan: amount of ONE instalment, frequency, number of instalments and first due date.
 * Frozen once charges have been posted from it (FeePlanService enforces this; it can still be deactivated).
 */
#[Fillable(['fee_plan_id', 'fee_head_id', 'amount_paise', 'frequency', 'installments', 'first_due_on', 'is_active', 'sort_order'])]
#[UsePolicy(FeeSetupPolicy::class)]
class FeePlanLine extends Model
{
    /** @use HasFactory<FeePlanLineFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'frequency' => FeeFrequency::class,
            'installments' => 'integer',
            'first_due_on' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<FeePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class, 'fee_plan_id');
    }

    /** @return BelongsTo<FeeHead, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }

    /** @return HasMany<FeeChargeLine, $this> */
    public function chargeLines(): HasMany
    {
        return $this->hasMany(FeeChargeLine::class);
    }

    /** Due date of instalment $k (1-based): first_due_on + (k-1) x step months, without day overflow. */
    public function dueOn(int $installment): CarbonImmutable
    {
        return $this->first_due_on->addMonthsNoOverflow(($installment - 1) * $this->frequency->stepMonths());
    }

    public function totalPerYearPaise(): int
    {
        return $this->amount_paise * $this->installments;
    }
}
