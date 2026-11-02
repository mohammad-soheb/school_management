<?php

namespace Database\Factories;

use App\Models\Platform\School;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Base for factories of tenant-owned models.
 *
 * school_id defaults to the active school (TenantContext) or, with no context, to a fresh school. Related rows must be
 * created from the already-resolved school id, so a factory never builds a row that points across schools:
 *
 *   'school_class_id' => fn (array $a) => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
 *
 * In tests either set the tenant context first, pass 'school_id' explicitly, or use ->for($school).
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Factory<TModel>
 */
abstract class TenantFactory extends Factory
{
    protected function schoolId(): Closure
    {
        return fn () => app(TenantContext::class)->id() ?? School::factory()->create()->id;
    }
}
