<?php

namespace App\Models\Platform;

use App\Enums\Subscriptions\SubscriptionState;
use App\Models\User;
use App\Services\Platform\Subscriptions\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Platform\SchoolSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A school's current ERP subscription (one row per school). Platform-owned: NOT BelongsToSchool. School-facing code
 * must filter by the active school explicitly (where school_id = TenantContext::requireId()).
 * Its state is computed from the dates by SubscriptionStatus; last_status only remembers the last state seen.
 */
#[Fillable(['subscription_plan_id', 'starts_on', 'expires_on', 'grace_days', 'notes'])]
class SchoolSubscription extends Model
{
    /** @use HasFactory<SchoolSubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'expires_on' => 'date',
            'grace_days' => 'integer',
            'suspended_at' => 'datetime',
            'last_status' => SubscriptionState::class,
            'last_status_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /** @return HasMany<SubscriptionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /** @return HasOne<SubscriptionPayment, $this> */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(SubscriptionPayment::class)->latestOfMany();
    }

    /** @return HasMany<RenewalEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RenewalEvent::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function status(?CarbonImmutable $today = null): SubscriptionStatus
    {
        return SubscriptionStatus::resolve(
            expiresOn: $this->expires_on,
            graceDays: $this->grace_days,
            suspended: $this->isSuspended(),
            today: $today ?? school_today(),
            expiringSoonDays: (int) config('subscriptions.expiring_soon_days', 15),
        );
    }

    /**
     * SQL mirror of SubscriptionStatus for lists and dashboard counts (MySQL). Kept in step with the resolver by
     * tests/Feature/Subscriptions/RenewalListTest.
     *
     * @param  Builder<SchoolSubscription>  $query
     */
    public function scopeInState(Builder $query, SubscriptionState $state, ?CarbonImmutable $today = null): void
    {
        $day = ($today ?? school_today())->toDateString();
        $soon = ($today ?? school_today())->addDays((int) config('subscriptions.expiring_soon_days', 15))->toDateString();
        $table = $this->getTable();

        if ($state === SubscriptionState::Suspended) {
            $query->whereNotNull("{$table}.suspended_at");

            return;
        }

        $query->whereNull("{$table}.suspended_at");

        match ($state) {
            SubscriptionState::Active => $query->where("{$table}.expires_on", '>', $soon),
            SubscriptionState::ExpiringSoon => $query->whereBetween("{$table}.expires_on", [$day, $soon]),
            SubscriptionState::Grace => $query->where("{$table}.expires_on", '<', $day)
                ->whereRaw("DATE_ADD({$table}.expires_on, INTERVAL {$table}.grace_days DAY) >= ?", [$day]),
            SubscriptionState::Expired => $query->whereRaw("DATE_ADD({$table}.expires_on, INTERVAL {$table}.grace_days DAY) < ?", [$day]),
        };
    }
}
