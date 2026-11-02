<?php

namespace App\Services\Platform\Subscriptions;

use App\Enums\Subscriptions\SubscriptionState;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Pure subscription status resolver (FR-805, FR-806). No database, no clock: every input is passed in, so the
 * date rules are unit-tested as a matrix (tests/Unit/Subscriptions/SubscriptionStatusTest).
 *
 * Dates are calendar days (the expiry date is the LAST day of paid access, inclusive):
 *
 *   suspended                                   -> Suspended   (manual, wins over every date rule)
 *   today <= expiry - soonDays - 1              -> Active
 *   expiry - soonDays <= today <= expiry        -> Expiring soon   (soonDays = 15 by default)
 *   expiry < today <= expiry + graceDays        -> Grace period     (full access, with a warning)
 *   today > expiry + graceDays                  -> Expired          (read-only)
 *
 *   $status = SubscriptionStatus::resolve(expiresOn: $sub->expires_on, graceDays: 7, suspended: false, today: school_today());
 *   $status->state; $status->daysLeft; $status->graceEndsOn;
 */
final readonly class SubscriptionStatus
{
    private function __construct(
        public SubscriptionState $state,
        public CarbonImmutable $expiresOn,
        public CarbonImmutable $graceEndsOn,
        /** Days from today until the expiry date: 0 on the expiry day, negative once past. */
        public int $daysLeft,
        /** Days of grace remaining (0 outside the grace period). */
        public int $graceDaysLeft,
    ) {}

    public static function resolve(DateTimeInterface $expiresOn, int $graceDays, bool $suspended, DateTimeInterface $today, int $expiringSoonDays = 15): self
    {
        if ($graceDays < 0 || $expiringSoonDays < 0) {
            throw new InvalidArgumentException('Grace and warning days cannot be negative.');
        }

        $expiry = CarbonImmutable::instance($expiresOn)->startOfDay();
        $day = CarbonImmutable::parse($today->format('Y-m-d'), $expiry->getTimezone())->startOfDay();
        $graceEnds = $expiry->addDays($graceDays);

        $daysLeft = (int) $day->diffInDays($expiry, false);
        $state = match (true) {
            $suspended => SubscriptionState::Suspended,
            $daysLeft > $expiringSoonDays => SubscriptionState::Active,
            $daysLeft >= 0 => SubscriptionState::ExpiringSoon,
            $day->lessThanOrEqualTo($graceEnds) => SubscriptionState::Grace,
            default => SubscriptionState::Expired,
        };

        $graceDaysLeft = $state === SubscriptionState::Grace ? (int) $day->diffInDays($graceEnds, false) : 0;

        return new self($state, $expiry, $graceEnds, $daysLeft, $graceDaysLeft);
    }

    public function allowsWrites(): bool
    {
        return $this->state->allowsWrites();
    }

    /** "Expires in 3 days", "Expires today", "Expired 4 days ago" for badges and banners. */
    public function relativeLabel(): string
    {
        return match (true) {
            $this->daysLeft > 1 => "Expires in {$this->daysLeft} days",
            $this->daysLeft === 1 => 'Expires tomorrow',
            $this->daysLeft === 0 => 'Expires today',
            $this->daysLeft === -1 => 'Expired yesterday',
            default => 'Expired '.abs($this->daysLeft).' days ago',
        };
    }
}
