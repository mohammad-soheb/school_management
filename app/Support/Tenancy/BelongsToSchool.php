<?php

namespace App\Support\Tenancy;

use App\Models\Platform\School;
use App\Support\Tenancy\Exceptions\CrossTenantWrite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned (the table has a school_id column).
 *
 *  - every query is filtered to the active school (SchoolScope, fails closed);
 *  - school_id is filled from the active school on create and is never mass-assignable;
 *  - writing a row into another school, or moving a row between schools, throws CrossTenantWrite.
 *
 * Route-model binding goes through the same scope, so another school's id resolves to 404.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('school_id') === null) {
                $model->setAttribute('school_id', $context->requireId());

                return;
            }

            if ($context->has() && ! $context->isBypassed() && (int) $model->getAttribute('school_id') !== $context->id()) {
                throw new CrossTenantWrite(sprintf(
                    'Refusing to create %s for school %d while acting for school %d.',
                    $model::class,
                    $model->getAttribute('school_id'),
                    $context->id(),
                ));
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('school_id')) {
                throw new CrossTenantWrite(sprintf('school_id of %s cannot be changed.', $model::class));
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
