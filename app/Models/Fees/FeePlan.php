<?php

namespace App\Models\Fees;

use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\User;
use App\Policies\Fees\FeeSetupPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Fees\FeePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The fee structure of one class in one academic year (FR-402). */
#[Fillable(['academic_year_id', 'school_class_id', 'name', 'notes', 'is_active', 'created_by'])]
#[UsePolicy(FeeSetupPolicy::class)]
class FeePlan extends Model
{
    /** @use HasFactory<FeePlanFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /** @return HasMany<FeePlanLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FeePlanLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
