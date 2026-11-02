<?php

namespace App\Services\Platform\Subscriptions;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Pure renewal date maths (master plan §10.5). The expiry date is the last day of paid access (inclusive).
 *
 * A renewal of N months extends from max(today, current expiry): the new period starts on the later of
 *   - the day after the current expiry (renewing early or on time loses no paid days), and
 *   - today (renewing late, during grace or after expiry, does not back-date the period).
 * It ends N calendar months later, minus one day. A month-end anchor stays at month end
 * (30 Nov + 1 month = 31 Dec; 31 Jan + 1 month = 28/29 Feb).
 *
 *   RenewalPeriod::extend(currentExpiry: '2026-11-30', today: '2026-10-29', months: 1)  // 2026-12-01 .. 2026-12-31
 *   RenewalPeriod::extend(currentExpiry: '2026-08-31', today: '2026-10-29', months: 1)  // 2026-10-29 .. 2026-11-28
 */
final readonly class RenewalPeriod
{
    private function __construct(
        public CarbonImmutable $startsOn,
        public CarbonImmutable $endsOn,
    ) {}

    public static function extend(DateTimeInterface|string $currentExpiry, DateTimeInterface|string $today, int $months): self
    {
        $expiry = self::day($currentExpiry);
        $day = self::day($today);

        // The "virtual previous expiry" the new months are added to.
        $anchor = $expiry->max($day->subDay());

        return self::fromAnchor($anchor, $months);
    }

    /** A first period that starts on $startsOn and lasts $months months (used to default a new assignment's expiry). */
    public static function starting(DateTimeInterface|string $startsOn, int $months): self
    {
        return self::fromAnchor(self::day($startsOn)->subDay(), $months);
    }

    private static function fromAnchor(CarbonImmutable $anchor, int $months): self
    {
        if ($months < 1) {
            throw new InvalidArgumentException('A renewal must be for at least one month.');
        }

        $end = $anchor->addMonthsNoOverflow($months);

        if ($anchor->isLastOfMonth()) {
            $end = $end->endOfMonth()->startOfDay();
        }

        return new self($anchor->addDay(), $end);
    }

    private static function day(DateTimeInterface|string $value): CarbonImmutable
    {
        $date = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;

        return CarbonImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10), 'UTC');
    }
}
