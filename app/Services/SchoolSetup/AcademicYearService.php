<?php

namespace App\Services\SchoolSetup;

use App\Enums\SchoolSetup\AcademicYearStatus;
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
 * Academic years (FR-202, FR-2B07): create/edit with non-overlapping dates, exactly one current year per school
 * (also enforced by the uq_academic_years_one_current index), and lock / reopen with a reason.
 *
 * A locked year is read-only: its dates and name cannot change and its calendar cannot be edited. Locking the
 * current year is refused so day-to-day work (attendance, fees) is never blocked by accident.
 */
class AcademicYearService
{
    /** A school year longer than this is almost certainly a typo in the dates. */
    public const MAX_LENGTH_DAYS = 550;

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<int, AcademicYear> newest first */
    public function all(): Collection
    {
        return AcademicYear::query()->with('lockedBy:id,name')->orderByDesc('starts_on')->get();
    }

    /**
     * Pre-filled values for the "new year" form: the year after the latest one, or the April-March year containing
     * today when the school has none yet.
     *
     * @return array{name: string, starts_on: string, ends_on: string}
     */
    public function suggestNext(): array
    {
        $latest = AcademicYear::query()->orderByDesc('ends_on')->first(['id', 'ends_on']);

        if ($latest !== null) {
            $start = CarbonImmutable::parse($latest->ends_on->toDateString())->addDay();
            $end = $start->addYear()->subDay();
        } else {
            $today = school_today();
            $start = CarbonImmutable::create($today->month >= 4 ? $today->year : $today->year - 1, 4, 1);
            $end = $start->addYear()->subDay();
        }

        return [
            'name' => $end->year === $start->year ? (string) $start->year : $start->year.'-'.$end->format('y'),
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
        ];
    }

    /** @param  array{name: string, starts_on: string, ends_on: string, make_current?: bool}  $data */
    public function create(array $data, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($data, $actor): AcademicYear {
            $this->lockYears();
            [$start, $end] = $this->validateDates($data['starts_on'], $data['ends_on']);

            $year = AcademicYear::create([
                'name' => $data['name'],
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'is_current' => false,
                'status' => AcademicYearStatus::Open,
            ]);

            $this->audit->record('academic_year.created', $year, new: $this->snapshot($year), actor: $actor);

            // The first year a school creates becomes current automatically; later ones only when asked.
            $isFirst = AcademicYear::query()->count() === 1;
            if (($data['make_current'] ?? false) || $isFirst) {
                $this->switchCurrent($year, $actor);
            }

            return $year;
        });
    }

    /** @param  array{name: string, starts_on: string, ends_on: string}  $data */
    public function update(AcademicYear $year, array $data, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($year, $data, $actor): AcademicYear {
            $this->lockYears();
            $year->refresh();
            $this->assertOpen($year);

            [$start, $end] = $this->validateDates($data['starts_on'], $data['ends_on'], $year);

            $outside = SchoolHoliday::query()
                ->where('academic_year_id', $year->getKey())
                ->where(fn ($q) => $q->where('starts_on', '<', $start->toDateString())->orWhere('ends_on', '>', $end->toDateString()))
                ->count();

            if ($outside > 0) {
                throw ValidationException::withMessages([
                    'starts_on' => "{$outside} calendar ".($outside === 1 ? 'entry falls' : 'entries fall').' outside the new dates. Move or remove them first.',
                ]);
            }

            $before = $this->snapshot($year);
            $year->fill(['name' => $data['name'], 'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString()])->save();

            [$old, $new] = $this->audit->diff($before, $this->snapshot($year));
            if ($new !== []) {
                $this->audit->record('academic_year.updated', $year, $old, $new, actor: $actor);
            }

            return $year;
        });
    }

    /** Make $year the school's current year. Exactly one year is current afterwards. */
    public function setCurrent(AcademicYear $year, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($year, $actor): AcademicYear {
            $this->lockYears();
            $year->refresh();

            if ($year->is_current) {
                return $year;
            }

            if ($year->isLocked()) {
                throw ValidationException::withMessages(['year' => "{$year->name} is locked. Reopen it before making it the current year."]);
            }

            return $this->switchCurrent($year, $actor);
        });
    }

    public function lock(AcademicYear $year, string $reason, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($year, $reason, $actor): AcademicYear {
            $this->lockYears();
            $year->refresh();

            if ($year->isLocked()) {
                throw ValidationException::withMessages(['year' => "{$year->name} is already locked."]);
            }

            if ($year->is_current) {
                throw ValidationException::withMessages(['year' => "{$year->name} is the current year. Make another year current before locking it."]);
            }

            $year->fill(['status' => AcademicYearStatus::Locked, 'locked_at' => now(), 'locked_by' => $actor->getKey()])->save();

            $this->audit->record('academic_year.locked', $year, ['status' => 'open'], ['status' => 'locked'], ['reason' => $reason], $actor);

            return $year;
        });
    }

    public function reopen(AcademicYear $year, string $reason, User $actor): AcademicYear
    {
        return DB::transaction(function () use ($year, $reason, $actor): AcademicYear {
            $this->lockYears();
            $year->refresh();

            if (! $year->isLocked()) {
                throw ValidationException::withMessages(['year' => "{$year->name} is not locked."]);
            }

            $year->fill(['status' => AcademicYearStatus::Open, 'locked_at' => null, 'locked_by' => null])->save();

            $this->audit->record('academic_year.reopened', $year, ['status' => 'locked'], ['status' => 'open'], ['reason' => $reason], $actor);

            return $year;
        });
    }

    /** Throws when $year is locked (for other services that write data belonging to a year). */
    public function assertOpen(AcademicYear $year): void
    {
        if ($year->isLocked()) {
            throw ValidationException::withMessages(['year' => "{$year->name} is locked. Reopen it to make changes."]);
        }
    }

    private function switchCurrent(AcademicYear $year, User $actor): AcademicYear
    {
        $previous = AcademicYear::query()->where('is_current', true)->whereKeyNot($year->getKey())->first();

        // Clear first: the unique index allows only one current row at any moment.
        AcademicYear::query()->where('is_current', true)->update(['is_current' => false]);
        $year->forceFill(['is_current' => true])->save();

        $this->audit->record(
            'academic_year.current_changed',
            $year,
            ['current' => $previous?->name],
            ['current' => $year->name],
            actor: $actor,
        );

        $this->context->forgetMemoized();

        return $year;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function validateDates(string $startsOn, string $endsOn, ?AcademicYear $ignore = null): array
    {
        $start = CarbonImmutable::parse($startsOn)->startOfDay();
        $end = CarbonImmutable::parse($endsOn)->startOfDay();

        if ($end <= $start) {
            throw ValidationException::withMessages(['ends_on' => 'The year must end after it starts.']);
        }

        if ($start->diffInDays($end) > self::MAX_LENGTH_DAYS) {
            throw ValidationException::withMessages(['ends_on' => 'An academic year cannot be longer than 18 months. Check the dates.']);
        }

        $overlap = AcademicYear::query()
            ->when($ignore !== null, fn ($q) => $q->whereKeyNot($ignore->getKey()))
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->orderBy('starts_on')
            ->first(['id', 'name']);

        if ($overlap !== null) {
            throw ValidationException::withMessages(['starts_on' => "These dates overlap {$overlap->name}. Academic years cannot overlap."]);
        }

        return [$start, $end];
    }

    /**
     * Serialise concurrent year changes for this school (one current year, no overlapping dates). The school row is
     * the mutex, so even the very first year (no rows to lock yet) is protected.
     */
    private function lockYears(): void
    {
        DB::table('schools')->where('id', $this->context->requireId())->lockForUpdate()->first(['id']);
    }

    /** @return array<string, mixed> */
    private function snapshot(AcademicYear $year): array
    {
        return [
            'name' => $year->name,
            'starts_on' => $year->starts_on?->toDateString(),
            'ends_on' => $year->ends_on?->toDateString(),
        ];
    }
}
