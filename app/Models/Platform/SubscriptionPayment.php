<?php

namespace App\Models\Platform;

use App\Enums\Subscriptions\SubscriptionPaymentMode;
use App\Models\User;
use Database\Factories\Platform\SubscriptionPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A verified ERP subscription payment from a school to the platform owner (FR-804). Completely separate from the
 * school's student-fee ledger (FR-809). Posted rows are immutable: a mistake is corrected by a follow-up record,
 * never by editing or deleting this one. Platform-owned: NOT BelongsToSchool.
 */
#[Fillable(['amount_paise', 'expected_paise', 'paid_on', 'mode', 'reference', 'months', 'period_starts_on', 'period_ends_on', 'notes', 'idempotency_key'])]
class SubscriptionPayment extends Model
{
    /** @use HasFactory<SubscriptionPaymentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Subscription payments are immutable.'));
        static::deleting(fn () => throw new LogicException('Subscription payments cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'expected_paise' => 'integer',
            'paid_on' => 'date',
            'mode' => SubscriptionPaymentMode::class,
            'months' => 'integer',
            'period_starts_on' => 'date',
            'period_ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<SchoolSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SchoolSubscription::class, 'school_subscription_id');
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Received minus expected, in paise (negative = short payment / discount). */
    public function differencePaise(): int
    {
        return $this->amount_paise - $this->expected_paise;
    }
}
