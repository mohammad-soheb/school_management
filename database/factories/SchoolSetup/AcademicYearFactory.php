<?php

namespace Database\Factories\SchoolSetup;

use App\Enums\SchoolSetup\AcademicYearStatus;
use App\Models\SchoolSetup\AcademicYear;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<AcademicYear>
 */
class AcademicYearFactory extends TenantFactory
{
    protected $model = AcademicYear::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $start = 2000 + (self::$sequence++ % 90);

        return [
            'school_id' => $this->schoolId(),
            'name' => sprintf('%d-%02d', $start, ($start + 1) % 100),
            'starts_on' => "{$start}-04-01",
            'ends_on' => ($start + 1).'-03-31',
            'is_current' => false,
            'status' => AcademicYearStatus::Open,
        ];
    }

    /** The school's current year. A school can have only one: the database enforces it. */
    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => AcademicYearStatus::Locked, 'locked_at' => now()]);
    }

    /** A year that contains today, so date-based rules (attendance, fees) work in tests. */
    public function containingToday(): static
    {
        return $this->state(function () {
            $today = now(config('app.display_timezone'));
            $start = $today->month >= 4 ? $today->year : $today->year - 1;

            return [
                'name' => sprintf('%d-%02d', $start, ($start + 1) % 100),
                'starts_on' => "{$start}-04-01",
                'ends_on' => ($start + 1).'-03-31',
            ];
        });
    }
}
