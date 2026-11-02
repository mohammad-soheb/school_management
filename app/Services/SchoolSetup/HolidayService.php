<?php

namespace App\Services\SchoolSetup;

use App\Enums\SchoolSetup\HolidayType;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolHoliday;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The school calendar (FR-202): holidays, events and exam periods inside an academic year.
 *
 * An entry belongs to the academic year that contains its dates (derived here, never taken from the browser); it may
 * not cross the year boundary, and entries of a locked year cannot be added, changed or removed. Only type "holiday"
 * closes the school (see SchoolCalendar); events and exam periods are informational.
 */
class HolidayService
{
    /** Longest single entry (a long vacation), a guard against date typos. */
    public const MAX_LENGTH_DAYS = 120;

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** The year shown by default: the requested one, else the current one, else the most recent one. */
    public function resolveYear(?int $yearId): ?AcademicYear
    {
        if ($yearId !== null) {
            $year = AcademicYear::query()->whereKey($yearId)->first();
            if ($year !== null) {
                return $year;
            }
        }

        return $this->context->academicYear() ?? AcademicYear::query()->orderByDesc('starts_on')->first();
    }

    /**
     * Entries of $year grouped by month ("2026-08" => entries), in date order.
     *
     * @return array<string, Collection<int, SchoolHoliday>>
     */
    public function byMonth(AcademicYear $year, ?HolidayType $type = null): array
    {
        $entries = SchoolHoliday::query()
            ->where('academic_year_id', $year->getKey())
            ->when($type !== null, fn ($q) => $q->where('type', $type->value))
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();

        $months = [];
        foreach ($entries as $entry) {
            $key = $entry->starts_on->format('Y-m');
            $months[$key] ??= new Collection;
            $months[$key]->push($entry);
        }

        return $months;
    }

    /**
     * Upcoming entries from today (school-local), for the overview page and dashboards.
     *
     * @return Collection<int, SchoolHoliday>
     */
    public function upcoming(int $limit = 5): Collection
    {
        return SchoolHoliday::query()
            ->where('ends_on', '>=', school_today()->toDateString())
            ->orderBy('starts_on')
            ->limit($limit)
            ->get();
    }

    /** @param  array{title: string, starts_on: string, ends_on?: ?string, type: string}  $data */
    public function create(array $data, User $actor): SchoolHoliday
    {
        return DB::transaction(function () use ($data, $actor): SchoolHoliday {
            [$year, $start, $end] = $this->placeInYear($data);

            $holiday = SchoolHoliday::create([
                'academic_year_id' => $year->getKey(),
                'title' => trim($data['title']),
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'type' => HolidayType::from($data['type']),
            ]);

            $this->audit->record('holiday.created', $holiday, new: $this->snapshot($holiday), actor: $actor);

            return $holiday;
        });
    }

    /** @param  array{title: string, starts_on: string, ends_on?: ?string, type: string}  $data */
    public function update(SchoolHoliday $holiday, array $data, User $actor): SchoolHoliday
    {
        return DB::transaction(function () use ($holiday, $data, $actor): SchoolHoliday {
            $this->assertYearOpen($holiday);
            [$year, $start, $end] = $this->placeInYear($data);

            $before = $this->snapshot($holiday);
            $holiday->fill([
                'academic_year_id' => $year->getKey(),
                'title' => trim($data['title']),
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'type' => HolidayType::from($data['type']),
            ])->save();

            [$old, $new] = $this->audit->diff($before, $this->snapshot($holiday));
            if ($new !== []) {
                $this->audit->record('holiday.updated', $holiday, $old, $new, actor: $actor);
            }

            return $holiday;
        });
    }

    public function delete(SchoolHoliday $holiday, User $actor): void
    {
        DB::transaction(function () use ($holiday, $actor): void {
            $this->assertYearOpen($holiday);
            $holiday->delete();
            $this->audit->record('holiday.deleted', $holiday, $this->snapshot($holiday), actor: $actor);
        });
    }

    /**
     * Find the academic year that contains the entry and check the dates.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: AcademicYear, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function placeInYear(array $data): array
    {
        $start = CarbonImmutable::parse($data['starts_on'])->startOfDay();
        $end = filled($data['ends_on'] ?? null) ? CarbonImmutable::parse($data['ends_on'])->startOfDay() : $start;

        if ($end < $start) {
            throw ValidationException::withMessages(['ends_on' => 'The end date cannot be before the start date.']);
        }

        if ($start->diffInDays($end) > self::MAX_LENGTH_DAYS) {
            throw ValidationException::withMessages(['ends_on' => 'A calendar entry cannot be longer than '.self::MAX_LENGTH_DAYS.' days. Check the dates.']);
        }

        $year = AcademicYear::query()
            ->where('starts_on', '<=', $start->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->lockForUpdate()
            ->first();

        if ($year === null) {
            throw ValidationException::withMessages(['starts_on' => 'This date is not inside any academic year. Create the academic year first.']);
        }

        if ($end > CarbonImmutable::parse($year->ends_on->toDateString())) {
            throw ValidationException::withMessages(['ends_on' => "The entry must end by {$year->ends_on->format('d M Y')}, the last day of {$year->name}. Split it across the two years."]);
        }

        if ($year->isLocked()) {
            throw ValidationException::withMessages(['starts_on' => "{$year->name} is locked. Reopen it to change its calendar."]);
        }

        return [$year, $start, $end];
    }

    private function assertYearOpen(SchoolHoliday $holiday): void
    {
        if ($holiday->academic_year_id === null) {
            return;
        }

        $year = AcademicYear::query()->whereKey($holiday->academic_year_id)->lockForUpdate()->first();

        if ($year?->isLocked()) {
            throw ValidationException::withMessages(['holiday' => "{$year->name} is locked. Reopen it to change its calendar."]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(SchoolHoliday $holiday): array
    {
        return [
            'title' => $holiday->title,
            'type' => $holiday->type instanceof HolidayType ? $holiday->type->value : $holiday->type,
            'starts_on' => $holiday->starts_on?->toDateString(),
            'ends_on' => $holiday->ends_on?->toDateString(),
            'academic_year_id' => $holiday->academic_year_id,
        ];
    }
}
