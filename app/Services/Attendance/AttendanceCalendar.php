<?php

namespace App\Services\Attendance;

use App\Enums\SchoolSetup\HolidayType;
use App\Models\SchoolSetup\SchoolHoliday;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Working days for attendance, from the school's weekly offs (setting attendance.weekly_off, 0 = Sunday .. 6 = Saturday)
 * and its calendar entries of type "holiday" in school_holidays. Events and exam periods are school days.
 *
 * Kept inside the attendance module on purpose (no dependency on another module's calendar service).
 */
class AttendanceCalendar
{
    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * A business date as midnight in the application timezone (UTC), the same form Eloquent gives `date` columns.
     * Accepts "Y-m-d" or any Carbon (school_today() is in the display timezone; only its calendar date is used).
     */
    public static function day(CarbonInterface|string $date): CarbonImmutable
    {
        $value = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
    }

    /** Today's school date (display timezone) as an application-timezone date. */
    public static function today(): CarbonImmutable
    {
        return self::day(school_today());
    }

    /** @return list<int> weekday numbers (0 = Sunday) the school is closed every week */
    public function weeklyOff(): array
    {
        $raw = $this->context->requireSchool()->setting('attendance.weekly_off', [0]);

        return array_values(array_unique(array_filter(
            array_map('intval', (array) $raw),
            fn (int $day) => $day >= 0 && $day <= 6,
        )));
    }

    /**
     * Why $date is not a school day, or null when it is one.
     */
    public function nonWorkingReason(CarbonInterface $date): ?string
    {
        return $this->nonWorkingDates($date, $date)[$date->toDateString()] ?? null;
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return $this->nonWorkingReason($date) === null;
    }

    /**
     * Every closed date in [from, to] with its reason ("Sunday (weekly off)", "Holiday: Diwali Break").
     *
     * @return array<string, string> Y-m-d => reason
     */
    public function nonWorkingDates(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::parse($from->toDateString());
        $to = CarbonImmutable::parse($to->toDateString());

        if ($to->lt($from)) {
            return [];
        }

        $closed = [];
        $weeklyOff = $this->weeklyOff();

        foreach (CarbonPeriod::create($from, $to) as $day) {
            if (in_array($day->dayOfWeek, $weeklyOff, true)) {
                $closed[$day->toDateString()] = self::DAY_NAMES[$day->dayOfWeek].' (weekly off)';
            }
        }

        $holidays = SchoolHoliday::query()
            ->where('type', HolidayType::Holiday->value)
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->orderBy('starts_on')
            ->get(['id', 'title', 'starts_on', 'ends_on']);

        foreach ($holidays as $holiday) {
            $start = CarbonImmutable::parse($holiday->starts_on->toDateString())->max($from);
            $end = CarbonImmutable::parse($holiday->ends_on->toDateString())->min($to);

            foreach (CarbonPeriod::create($start, $end) as $day) {
                $closed[$day->toDateString()] = 'Holiday: '.$holiday->title;
            }
        }

        ksort($closed);

        return $closed;
    }

    /** The nearest school day before (-1) or after (+1) $date, within two weeks; never after today. */
    public function adjacentWorkingDay(CarbonInterface $date, int $direction): ?CarbonImmutable
    {
        $date = self::day($date);
        $from = $direction < 0 ? $date->subDays(14) : $date->addDay();
        $to = $direction < 0 ? $date->subDay() : $date->addDays(14)->min(self::today());

        if ($to->lt($from)) {
            return null;
        }

        $days = $this->workingDays($from, $to);

        return $direction < 0 ? ($days[count($days) - 1] ?? null) : ($days[0] ?? null);
    }

    /**
     * School days in [from, to], oldest first.
     *
     * @return list<CarbonImmutable>
     */
    public function workingDays(CarbonInterface $from, CarbonInterface $to): array
    {
        $closed = $this->nonWorkingDates($from, $to);
        $days = [];

        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $day) {
            if (! isset($closed[$day->toDateString()])) {
                $days[] = CarbonImmutable::parse($day->toDateString());
            }
        }

        return $days;
    }
}
