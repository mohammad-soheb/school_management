<?php

namespace App\Services\Platform\Subscriptions;

use App\Enums\Subscriptions\SubscriptionState;
use App\Models\Platform\SchoolSubscription;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily job behind `erp:subscriptions:refresh` (FR-806, FR-808). Recomputes every subscription's state for today,
 * logs each transition once, stores it in last_status, and writes an audit event when a school enters the grace
 * period or expires. The state itself is always computed from the dates; this only REPORTS changes.
 */
class StatusRefresher
{
    /** States whose arrival is audited (the moments a school's access changes or is about to). */
    private const AUDITED = [SubscriptionState::Grace, SubscriptionState::Expired];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array{checked: int, changed: list<array{school_id: int, from: ?string, to: string}>} */
    public function refresh(?CarbonImmutable $today = null): array
    {
        $today ??= school_today();
        $checked = 0;
        $changed = [];

        SchoolSubscription::query()->orderBy('id')->chunkById(200, function ($subscriptions) use ($today, &$checked, &$changed): void {
            foreach ($subscriptions as $subscription) {
                $checked++;
                $state = $subscription->status($today)->state;
                $previous = $subscription->last_status;

                if ($previous === $state) {
                    continue;
                }

                DB::transaction(function () use ($subscription, $state, $previous, $today): void {
                    $subscription->forceFill(['last_status' => $state, 'last_status_at' => now()])->save();

                    if (in_array($state, self::AUDITED, true)) {
                        $this->audit->record(
                            $state === SubscriptionState::Grace ? 'subscription.grace_started' : 'subscription.expired',
                            $subscription,
                            ['state' => $previous?->value],
                            ['state' => $state->value, 'expires_on' => $subscription->expires_on->toDateString()],
                            meta: ['source' => 'erp:subscriptions:refresh', 'as_of' => $today->toDateString()],
                            schoolId: $subscription->school_id,
                        );
                    }
                });

                Log::info('Subscription state changed', [
                    'school_id' => $subscription->school_id,
                    'from' => $previous?->value,
                    'to' => $state->value,
                    'expires_on' => $subscription->expires_on->toDateString(),
                ]);

                $changed[] = ['school_id' => (int) $subscription->school_id, 'from' => $previous?->value, 'to' => $state->value];
            }
        });

        return ['checked' => $checked, 'changed' => $changed];
    }
}
