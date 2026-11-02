<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on a tenant-owned model to the active school.
 *
 * Fails closed: with no active school (and no explicit TenantContext::withoutTenant()) the query throws
 * TenantContextMissing instead of quietly returning every school's rows.
 */
final class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $builder->where($model->qualifyColumn('school_id'), $context->requireId());
    }
}
