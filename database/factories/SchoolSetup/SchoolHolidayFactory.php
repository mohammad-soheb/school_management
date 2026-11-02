<?php

namespace Database\Factories\SchoolSetup;

use App\Enums\SchoolSetup\HolidayType;
use App\Models\SchoolSetup\SchoolHoliday;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<SchoolHoliday>
 */
class SchoolHolidayFactory extends TenantFactory
{
    protected $model = SchoolHoliday::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('now', '+60 days')->format('Y-m-d');

        return [
            'school_id' => $this->schoolId(),
            'title' => fake()->randomElement(['Diwali Break', 'Holi', 'Republic Day', 'Founders Day']),
            'starts_on' => $date,
            'ends_on' => $date,
            'type' => HolidayType::Holiday,
        ];
    }
}
