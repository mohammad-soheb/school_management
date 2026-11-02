<?php

namespace App\Models\Fees;

use App\Policies\Fees\FeeSetupPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Fees\FeeHeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A kind of fee: Tuition, Annual charges, Exam fee, Transport ... (FR-401). Deactivated, never deleted. */
#[Fillable(['name', 'code', 'description', 'is_active', 'sort_order'])]
#[UsePolicy(FeeSetupPolicy::class)]
class FeeHead extends Model
{
    /** @use HasFactory<FeeHeadFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @param  Builder<FeeHead>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<FeeHead>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasMany<FeePlanLine, $this> */
    public function planLines(): HasMany
    {
        return $this->hasMany(FeePlanLine::class);
    }
}
