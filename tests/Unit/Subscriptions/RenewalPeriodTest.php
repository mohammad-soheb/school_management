<?php

namespace Tests\Unit\Subscriptions;

use App\Services\Platform\Subscriptions\RenewalPeriod;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Renewal maths (§10.5): extend from max(today, current expiry). The expiry date is the last paid day. */
class RenewalPeriodTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: int, 3: string, 4: string}> currentExpiry, today, months, starts, ends */
    public static function cases(): array
    {
        return [
            'early renewal keeps the remaining paid days' => ['2026-12-15', '2026-11-01', 1, '2026-12-16', '2027-01-15'],
            'renewal on the expiry day continues seamlessly' => ['2026-11-15', '2026-11-15', 1, '2026-11-16', '2026-12-15'],
            'renewal the day after expiry is not back-dated' => ['2026-11-15', '2026-11-16', 1, '2026-11-16', '2026-12-15'],
            'late renewal during grace starts today' => ['2026-11-15', '2026-11-20', 1, '2026-11-20', '2026-12-19'],
            'renewal long after expiry starts today' => ['2026-01-31', '2026-10-29', 3, '2026-10-29', '2027-01-28'],
            'several months at once' => ['2026-12-15', '2026-12-01', 12, '2026-12-16', '2027-12-15'],
            'month-end expiry stays at month end' => ['2026-11-30', '2026-11-20', 1, '2026-12-01', '2026-12-31'],
            'january end into short february' => ['2027-01-31', '2027-01-20', 1, '2027-02-01', '2027-02-28'],
            'leap february end into march end' => ['2028-02-29', '2028-02-20', 1, '2028-03-01', '2028-03-31'],
            'mid-month 31st does not overflow' => ['2026-12-30', '2026-12-01', 2, '2026-12-31', '2027-02-28'],
        ];
    }

    #[DataProvider('cases')]
    public function test_extend(string $expiry, string $today, int $months, string $starts, string $ends): void
    {
        $period = RenewalPeriod::extend($expiry, $today, $months);

        $this->assertSame($starts, $period->startsOn->toDateString());
        $this->assertSame($ends, $period->endsOn->toDateString());
    }

    public function test_extending_twice_by_one_month_equals_once_by_two_months_when_early(): void
    {
        $once = RenewalPeriod::extend('2026-11-30', '2026-11-01', 2);
        $first = RenewalPeriod::extend('2026-11-30', '2026-11-01', 1);
        $second = RenewalPeriod::extend($first->endsOn, '2026-11-01', 1);

        $this->assertSame($once->endsOn->toDateString(), $second->endsOn->toDateString());
    }

    public function test_starting_gives_a_first_period(): void
    {
        $period = RenewalPeriod::starting('2026-11-01', 1);

        $this->assertSame('2026-11-01', $period->startsOn->toDateString());
        $this->assertSame('2026-11-30', $period->endsOn->toDateString());
        $this->assertSame('2027-10-31', RenewalPeriod::starting('2026-11-01', 12)->endsOn->toDateString());
    }

    public function test_at_least_one_month(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RenewalPeriod::extend('2026-11-30', '2026-11-01', 0);
    }
}
