<?php

namespace App\Models\Academics;

use App\Policies\Academics\GradingSchemePolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A school's grading scale: percentage bands -> grade. Table: grading_schemes. */
#[Fillable(['name', 'description', 'is_active', 'default_flag', 'created_by', 'updated_by'])]
#[UsePolicy(GradingSchemePolicy::class)]
class GradingScheme extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_flag' => 'integer',
        ];
    }

    /** Highest band first (the order they are printed in). @return HasMany<GradeBand, $this> */
    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderByDesc('min_percent');
    }

    /** @return HasMany<Exam, $this> */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function isDefault(): bool
    {
        return $this->default_flag === 1;
    }

    /** @param  Builder<GradingScheme>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Bands as plain arrays for the ResultCalculator.
     *
     * @return list<array{min: string, max: string, grade: string, grade_point: ?string, remark: ?string}>
     */
    public function bandRules(): array
    {
        return $this->bands->map(fn (GradeBand $band) => [
            'min' => (string) $band->min_percent,
            'max' => (string) $band->max_percent,
            'grade' => $band->grade,
            'grade_point' => $band->grade_point !== null ? (string) $band->grade_point : null,
            'remark' => $band->remark,
        ])->values()->all();
    }
}
