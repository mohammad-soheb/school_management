<?php

namespace Database\Factories\Promotion;

use App\Enums\Promotion\PromotionRunStatus;
use App\Models\Promotion\PromotionRun;
use App\Models\SchoolSetup\AcademicYear;
use Database\Factories\TenantFactory;
use Illuminate\Support\Str;

/**
 * An empty draft run between two fresh years. Real runs are built by PromotionService::createDraft().
 *
 * @extends TenantFactory<PromotionRun>
 */
class PromotionRunFactory extends TenantFactory
{
    protected $model = PromotionRun::class;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'from_academic_year_id' => fn (array $a) => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            'to_academic_year_id' => fn (array $a) => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            'status' => PromotionRunStatus::Draft,
            'options' => ['class_ids' => [], 'carry_forward_dues' => true, 'lock_source_year' => false],
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
