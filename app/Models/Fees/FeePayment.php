<?php

namespace App\Models\Fees;

use App\Enums\Fees\PaymentMode;
use App\Enums\Fees\PaymentStatus;
use App\Models\Fees\Concerns\ImmutableLedgerRow;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\Students\Student;
use App\Models\User;
use App\Policies\Fees\FeePaymentPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Money received, with exactly one receipt number (FR-405, FR-413). Immutable except for the single
 * posted -> reversed transition done by ReversalService. receipt_no, receipt_number and status are not
 * mass-assignable: PaymentPostingService sets them under the sequence lock.
 */
#[Fillable(['student_id', 'academic_year_id', 'amount_paise', 'mode', 'reference', 'instrument_date', 'paid_on', 'received_by', 'remarks', 'idempotency_key'])]
#[UsePolicy(FeePaymentPolicy::class)]
class FeePayment extends Model
{
    use BelongsToSchool, ImmutableLedgerRow;

    protected $attributes = ['status' => 'posted'];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'receipt_number' => 'integer',
            'mode' => PaymentMode::class,
            'status' => PaymentStatus::class,
            'paid_on' => 'immutable_date',
            'instrument_date' => 'immutable_date',
            'reversed_at' => 'immutable_datetime',
        ];
    }

    /** Only posted -> reversed, touching nothing but the reversal columns. */
    protected function allowsLedgerUpdate(): bool
    {
        return $this->getRawOriginal('status') === PaymentStatus::Posted->value
            && $this->status === PaymentStatus::Reversed
            && array_diff(array_keys($this->getDirty()), ['status', 'reversed_at', 'reversed_by', 'updated_at']) === [];
    }

    public function isReversed(): bool
    {
        return $this->status === PaymentStatus::Reversed;
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return HasMany<FeeAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(FeeAllocation::class);
    }

    /** @return HasOne<FeePaymentReversal, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(FeePaymentReversal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
