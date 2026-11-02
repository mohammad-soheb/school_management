<?php

namespace App\Models\Academics;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One band of a grading scheme: min_percent inclusive, max_percent exclusive (the top band includes 100). */
#[Fillable(['grading_scheme_id', 'min_percent', 'max_percent', 'grade', 'grade_point', 'remark', 'sort_order'])]
class GradeBand extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'min_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'grade_point' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<GradingScheme, $this> */
    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }
}
