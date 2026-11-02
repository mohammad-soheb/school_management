<?php

namespace App\Models\Academics;

use App\Enums\Academics\MarkSheetStatus;
use App\Models\SchoolSetup\Section;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One section x one exam subject: the unit a teacher saves as a draft and submits. */
#[Fillable(['exam_id', 'exam_subject_id', 'section_id', 'status', 'submitted_at', 'submitted_by', 'updated_by'])]
class MarkSheet extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'status' => MarkSheetStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<ExamSubject, $this> */
    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return HasMany<Mark, $this> */
    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
