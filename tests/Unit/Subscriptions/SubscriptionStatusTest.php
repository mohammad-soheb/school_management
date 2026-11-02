<?php

namespace Tests\Unit\Subscriptions;

use App\Enums\Subscriptions\SubscriptionState;
use App\Services\Platform\Subscriptions\SubscriptionStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Date matrix for FR-805 / FR-806. Expiry 30 Nov 2026 (last paid day), 15 warning days, 7 grace days unless stated. */
class SubscriptionStatusTest extends TestCase
{
    /** @return array<string, array{0: string, 1: SubscriptionState, 2: int, 3?: int, 4?: bool}> */
    public static function matrix(): array
    {
        return [
            'months ahead' => ['2026-09-01', SubscriptionState::Active, 90],
            'day before warning window' => ['2026-11-14', SubscriptionState::Active, 16],
            'first warning day (15 left)' => ['2026-11-15', SubscriptionState::ExpiringSoon, 15],
            'day before expiry' => ['2026-11-29', SubscriptionState::ExpiringSoon, 1],
            'expiry day is still paid' => ['2026-11-30', SubscriptionState::ExpiringSoon, 0],
            'first grace day' => ['2026-12-01', SubscriptionState::Grace, -1],
            'last grace day' => ['2026-12-07', SubscriptionState::Grace, -7],
            'first expired day' => ['2026-12-08', SubscriptionState::Expired, -8],
            'long expired' => ['2027-06-01', SubscriptionState::Expired, -183],
            'no grace: day after expiry is expired' => ['2026-12-01', SubscriptionState::Expired, -1, 0],
            'long grace' => ['2026-12-20', SubscriptionState::Grace, -20, 30],
            'suspended wins over active dates' => ['2026-09-01', SubscriptionState::Suspended, 90, 7, true],
            'suspended wins over expired dates' => ['2027-06-01', SubscriptionState::Suspended, -183, 7, true],
        ];
    }

    #[DataProvider('matrix')]
    public function test_state_for_each_day(string $today, SubscriptionState $expected, int $daysLeft, int $grace = 7, bool $suspended = false): void
    {
        $status = SubscriptionStatus::resolve(
            expiresOn: CarbonImmutable::parse('2026-11-30'),
            graceDays: $grace,
            suspended: $suspended,
            today: CarbonImmutable::parse($today),
        );

        $this->assertSame($expected, $status->state);
        $this->assertSame($daysLeft, $status->daysLeft);
    }

    public function test_grace_end_and_days_left_in_grace(): void
    {
        $status = SubscriptionStatus::resolve(CarbonImmutable::parse('2026-11-30'), 7, false, CarbonImmutable::parse('2026-12-03'));

        $this->assertSame('2026-12-07', $status->graceEndsOn->toDateString());
        $this->assertSame(4, $status->graceDaysLeft);
        $this->assertTrue($status->allowsWrites());
    }

    public function test_only_expired_and_suspended_block_writes(): void
    {
        $this->assertTrue(SubscriptionState::Active->allowsWrites());
        $this->assertTrue(SubscriptionState::ExpiringSoon->allowsWrites());
        $this->assertTrue(SubscriptionState::Grace->allowsWrites());
        $this->assertFalse(SubscriptionState::Expired->allowsWrites());
        $this->assertFalse(SubscriptionState::Suspended->allowsWrites());
    }

    public function test_the_warning_window_is_configurable(): void
    {
        $status = SubscriptionStatus::resolve(CarbonImmutable::parse('2026-11-30'), 7, false, CarbonImmutable::parse('2026-11-25'), expiringSoonDays: 3);

        $this->assertSame(SubscriptionState::Active, $status->state);
    }

    public function test_today_in_another_timezone_is_compared_by_calendar_day(): void
    {
        // 30 Nov 23:30 in Kolkata is still the expiry day even though it is 18:00 UTC.
        $today = CarbonImmutable::parse('2026-11-30 23:30', 'Asia/Kolkata');
        $status = SubscriptionStatus::resolve(CarbonImmutable::parse('2026-11-30', 'UTC'), 7, false, $today);

        $this->assertSame(SubscriptionState::ExpiringSoon, $status->state);
        $this->assertSame(0, $status->daysLeft);
    }

    public function test_relative_labels(): void
    {
        $label = fn (string $today) => SubscriptionStatus::resolve(CarbonImmutable::parse('2026-11-30'), 7, false, CarbonImmutable::parse($today))->relativeLabel();

        $this->assertSame('Expires in 3 days', $label('2026-11-27'));
        $this->assertSame('Expires tomorrow', $label('2026-11-29'));
        $this->assertSame('Expires today', $label('2026-11-30'));
        $this->assertSame('Expired yesterday', $label('2026-12-01'));
        $this->assertSame('Expired 5 days ago', $label('2026-12-05'));
    }

    public function test_negative_grace_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SubscriptionStatus::resolve(CarbonImmutable::parse('2026-11-30'), -1, false, CarbonImmutable::parse('2026-11-01'));
    }
}
