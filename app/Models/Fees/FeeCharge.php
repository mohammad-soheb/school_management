<?php

namespace App\Models\Fees;

use App\Enums\Fees\ChargeSource;
use App\Models\Fees\Concerns\ImmutableLedgerRow;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A posted fee bill for one student (FR-411). Insert-only. */
#[Fillable(['fee_charge_batch_id', 'student_id', 'student_enrollment_id', 'academic_year_id', 'source', 'total_paise', 'posted_on', 'posted_by'])]
class FeeCharge extends Model
{
    use BelongsToSchool, ImmutableLedgerRow;

    protected function casts(): array
    {
        return [
            'source' => ChargeSource::class,
            'total_paise' => 'integer',
            'posted_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<FeeChargeBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(FeeChargeBatch::class, 'fee_charge_batch_id');
    }

    /** @return HasMany<FeeChargeLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FeeChargeLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
