<?php

namespace App\Models\Promotion;

use App\Enums\Promotion\PromotionRunStatus;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\User;
use App\Policies\Promotion\PromotionRunPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Promotion\PromotionRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One year-end rollover from a source academic year to a target year (draft -> committed | cancelled).
 * Workflows live in App\Services\Promotion\PromotionService.
 */
#[Fillable(['from_academic_year_id', 'to_academic_year_id', 'status', 'options', 'idempotency_key', 'counts', 'created_by', 'committed_by', 'committed_at', 'cancelled_by', 'cancelled_at'])]
#[UsePolicy(PromotionRunPolicy::class)]
class PromotionRun extends Model
{
    /** @use HasFactory<PromotionRunFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PromotionRunStatus::class,
            'options' => 'array',
            'counts' => 'array',
            'committed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === PromotionRunStatus::Draft;
    }

    public function isCommitted(): bool
    {
        return $this->status === PromotionRunStatus::Committed;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return ($this->options ?? [])[$key] ?? $default;
    }

    /** @return list<int> */
    public function classIds(): array
    {
        return array_values(array_map('intval', (array) $this->option('class_ids', [])));
    }

    public function count(string $key): int
    {
        return (int) (($this->counts ?? [])[$key] ?? 0);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function fromYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function toYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
    }

    /** @return HasMany<PromotionRunItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PromotionRunItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function committer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by');
    }
}
