<?php

namespace App\Models\Platform;

use Database\Factories\Platform\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A plan the platform sells to schools (FR-803). Platform-owned: not scoped to a school.
 * Plans are never deleted (payments reference them); retire one by switching is_active off.
 */
#[Fillable(['name', 'code', 'description', 'monthly_price_paise', 'billing_period_months', 'student_limit', 'is_hypothesis', 'is_active'])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'monthly_price_paise' => 'integer',
            'billing_period_months' => 'integer',
            'student_limit' => 'integer',
            'is_hypothesis' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<SchoolSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SchoolSubscription::class);
    }

    /** @param  Builder<SubscriptionPlan>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Price of one billing period (monthly price x months), in paise. */
    public function periodPricePaise(): int
    {
        return $this->monthly_price_paise * max(1, $this->billing_period_months);
    }

    /** Price of $months months, in paise. */
    public function priceForMonths(int $months): int
    {
        return $this->monthly_price_paise * $months;
    }
}
