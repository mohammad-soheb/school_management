<?php

namespace App\Models\Academics;

use App\Enums\Academics\PublicationStatus;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One publish of an exam's results for a class (history: who, when, why). Withdrawing keeps the row and its
 * snapshot; the next publish is a new version.
 */
#[Fillable(['exam_id', 'school_class_id', 'version', 'status', 'active_flag', 'students_count', 'reason', 'options', 'published_by', 'published_at', 'withdrawn_by', 'withdrawn_at', 'withdraw_reason'])]
class ResultPublication extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'active_flag' => 'integer',
            'options' => 'array',
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** @param  Builder<ResultPublication>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', PublicationStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === PublicationStatus::Active;
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /** @return HasMany<ExamResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return BelongsTo<User, $this> */
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }
}
