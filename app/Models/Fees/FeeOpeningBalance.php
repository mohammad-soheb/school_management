<?php

namespace App\Models\Fees;

use App\Enums\Fees\OpeningBalanceSource;
use App\Models\Fees\Concerns\ImmutableLedgerRow;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A previous balance with its recorded source (FR-416, D05). Insert-only; it owns one opening-balance charge. */
#[Fillable(['student_id', 'student_enrollment_id', 'academic_year_id', 'amount_paise', 'source', 'source_reference', 'notes', 'due_on', 'carried_from_enrollment_id', 'fee_charge_id', 'idempotency_key', 'created_by'])]
class FeeOpeningBalance extends Model
{
    use BelongsToSchool, ImmutableLedgerRow;

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'source' => OpeningBalanceSource::class,
            'due_on' => 'immutable_date',
        ];
    }

    /** The opening-balance charge is created after this row, then linked once (the only permitted update). */
    protected function allowsLedgerUpdate(): bool
    {
        return $this->getRawOriginal('fee_charge_id') === null
            && array_diff(array_keys($this->getDirty()), ['fee_charge_id', 'updated_at']) === [];
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

    /** @return BelongsTo<FeeCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(FeeCharge::class, 'fee_charge_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
