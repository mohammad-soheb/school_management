<?php

namespace App\Services\SchoolSetup;

use App\Enums\SchoolSetup\HolidayType;
use App\Models\SchoolSetup\SchoolHoliday;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Working-day arithmetic for the active school (FR-202), for attendance percentages, salary working days and fee
 * fines. Other modules call it instead of reading holidays themselves:
 *
 *   app(SchoolCalendar::class)->isWorkingDay(school_today());
 *   app(SchoolCalendar::class)->workingDaysBetween($monthStart, $monthEnd);   // inclusive
 *
 * A day is NOT a working day when its weekday is in the school's `attendance.weekly_off` setting (0 = Sunday ...
 * 6 = Saturday) or when a calendar entry of type "holiday" covers it. Events and exam periods are school days.
 * Only the calendar date of the argument is used (pass school-local dates such as school_today(), not UTC instants).
 * Requires an active tenant context.
 */
class SchoolCalendar
{
    /** Longest range accepted by the range methods (about ten years), a guard against runaway loops. */
    private const MAX_RANGE_DAYS = 3700;

    public function __construct(private readonly TenantContext $context) {}

    /** @return list<int> weekdays that are always closed (0 = Sunday ... 6 = Saturday), sorted */
    public function weeklyOffDays(): array
    {
        $days = array_map('intval', (array) $this->context->requireSchool()->setting('attendance.weekly_off', [0]));
        $days = array_values(array_unique(array_filter($days, fn (int $d): bool => $d >= 0 && $d <= 6)));
        sort($days);

        return $days;
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return $this->nonWorkingReason($date) === null;
    }

    /** Why $date is closed ("Weekly off (Sunday)" or the holiday title), or null when it is a working day. */
    public function nonWorkingReason(CarbonInterface $date): ?string
    {
        $day = $this->dateOnly($date);

        if (in_array($day->dayOfWeek, $this->weeklyOffDays(), true)) {
            return 'Weekly off ('.$day->format('l').')';
        }

        return SchoolHoliday::query()
            ->where('type', HolidayType::Holiday->value)
            ->where('starts_on', '<=', $day->toDateString())
            ->where('ends_on', '>=', $day->toDateString())
            ->orderBy('starts_on')
            ->value('title');
    }

    /** Number of working days from $from to $to, both inclusive (0 when $to is before $from). */
    public function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return count($this->workingDatesBetween($from, $to));
    }

    /**
     * The working days from $from to $to inclusive, as Y-m-d strings in order. One query for the whole range.
     *
     * @return list<string>
     */
    public function workingDatesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        [$start, $end] = $this->range($from, $to);

        if ($start === null) {
            return [];
        }

        $closed = $this->holidayDatesBetween($start, $end);
        $weeklyOff = $this->weeklyOffDays();
        $dates = [];

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $key = $day->toDateString();
            if (! isset($closed[$key]) && ! in_array($day->dayOfWeek, $weeklyOff, true)) {
                $dates[] = $key;
            }
        }

        return $dates;
    }

    /**
     * Dates covered by holidays (type "holiday" only) between $from and $to inclusive: Y-m-d => holiday title.
     *
     * @return array<string, string>
     */
    public function holidayDatesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        [$start, $end] = $this->range($from, $to);

        if ($start === null) {
            return [];
        }

        $holidays = SchoolHoliday::query()
            ->where('type', HolidayType::Holiday->value)
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->orderBy('starts_on')
            ->get(['id', 'title', 'starts_on', 'ends_on']);

        $dates = [];
        foreach ($holidays as $holiday) {
            $first = max($start, $this->dateOnly($holiday->starts_on));
            $last = min($end, $this->dateOnly($holiday->ends_on));

            for ($day = $first; $day <= $last; $day = $day->addDay()) {
                $dates[$day->toDateString()] ??= $holiday->title;
            }
        }

        return $dates;
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    private function range(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $this->dateOnly($from);
        $end = $this->dateOnly($to);

        if ($end < $start) {
            return [null, null];
        }

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            throw new \InvalidArgumentException('The date range is too long for working-day calculations.');
        }

        return [$start, $end];
    }

    private function dateOnly(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date->format('Y-m-d'), 'UTC');
    }
}
