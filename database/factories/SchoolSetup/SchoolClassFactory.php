<?php

namespace Database\Factories\SchoolSetup;

use App\Models\SchoolSetup\SchoolClass;
use Database\Factories\TenantFactory;

/**
 * @extends TenantFactory<SchoolClass>
 */
class SchoolClassFactory extends TenantFactory
{
    protected $model = SchoolClass::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $n = ++self::$sequence;

        return [
            'school_id' => $this->schoolId(),
            'name' => "Class {$n}",
            'code' => (string) $n,
            'sort_order' => $n,
        ];
    }
}
