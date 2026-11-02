<?php

namespace App\Models\Fees;

use App\Models\Fees\Concerns\ImmutableLedgerRow;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One "post charges" run. Its idempotency key makes a double click return the same batch. */
#[Fillable(['academic_year_id', 'fee_plan_id', 'due_until', 'idempotency_key', 'students_count', 'lines_count', 'total_paise', 'posted_by'])]
class FeeChargeBatch extends Model
{
    use BelongsToSchool, ImmutableLedgerRow;

    protected function casts(): array
    {
        return [
            'due_until' => 'immutable_date',
            'students_count' => 'integer',
            'lines_count' => 'integer',
            'total_paise' => 'integer',
        ];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<FeePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class, 'fee_plan_id');
    }

    /** @return HasMany<FeeCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(FeeCharge::class);
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
