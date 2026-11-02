<?php

namespace App\Models\Academics;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A part of an exam subject with its own maximum (theory, practical, internal assessment ...). */
#[Fillable(['exam_subject_id', 'name', 'max_marks', 'pass_marks', 'sort_order'])]
class ExamComponent extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'max_marks' => 'decimal:2',
            'pass_marks' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ExamSubject, $this> */
    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }
}
