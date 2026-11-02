<?php

namespace App\Models\SchoolSetup;

use App\Enums\SchoolSetup\AcademicYearStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\SchoolSetup\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'starts_on', 'ends_on', 'is_current', 'status', 'locked_at', 'locked_by'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'status' => AcademicYearStatus::class,
            'locked_at' => 'datetime',
        ];
    }

    /** @param  Builder<AcademicYear>  $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    public function isLocked(): bool
    {
        return $this->status === AcademicYearStatus::Locked;
    }

    /** True when $date falls inside this academic year. */
    public function contains(\DateTimeInterface $date): bool
    {
        return $date >= $this->starts_on->startOfDay() && $date <= $this->ends_on->endOfDay();
    }

    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
