<?php

namespace App\Models\Platform;

use App\Enums\Subscriptions\RenewalEventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only renewal history of a school's subscription (FR-803): activation, renewals, plan changes, date
 * adjustments, suspension and resumption. Platform-owned: NOT BelongsToSchool.
 */
#[Fillable(['type', 'previous_plan_id', 'new_plan_id', 'previous_expires_on', 'new_expires_on', 'notes'])]
class RenewalEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Renewal events are immutable.'));
        static::deleting(fn () => throw new LogicException('Renewal events cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'type' => RenewalEventType::class,
            'previous_expires_on' => 'date',
            'new_expires_on' => 'date',
        ];
    }

    /** @return BelongsTo<SchoolSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SchoolSubscription::class, 'school_subscription_id');
    }

    /** @return BelongsTo<SubscriptionPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'previous_plan_id');
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function newPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'new_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
