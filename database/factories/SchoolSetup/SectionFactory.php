<?php

namespace Database\Factories\SchoolSetup;

use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<Section>
 */
class SectionFactory extends TenantFactory
{
    protected $model = Section::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $n = self::$sequence++;

        return [
            'school_id' => $this->schoolId(),
            'school_class_id' => fn (array $a) => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            'name' => chr(65 + ($n % 26)).($n >= 26 ? intdiv($n, 26) : ''),
            'capacity' => 40,
            'sort_order' => $n,
        ];
    }
}
