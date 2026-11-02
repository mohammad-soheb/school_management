<?php

namespace App\Models\Fees;

use App\Enums\Fees\AdjustmentStatus;
use App\Enums\Fees\AdjustmentType;
use App\Models\Fees\Concerns\ImmutableLedgerRow;
use App\Models\Students\Student;
use App\Models\User;
use App\Policies\Fees\FeeAdjustmentPolicy;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Discount / scholarship / waiver / fine / carry-forward on one charge line (FR-404). A pending adjustment has no
 * effect; it is decided exactly once (approved, rejected or cancelled) and is immutable afterwards.
 * status, decided_by/at and decision_note are not mass-assignable: AdjustmentService sets them.
 */
#[Fillable(['student_id', 'fee_charge_line_id', 'type', 'amount_paise', 'effect_paise', 'reason', 'requested_by', 'fee_opening_balance_id', 'idempotency_key'])]
#[UsePolicy(FeeAdjustmentPolicy::class)]
class FeeAdjustment extends Model
{
    use BelongsToSchool, ImmutableLedgerRow;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'type' => AdjustmentType::class,
            'status' => AdjustmentStatus::class,
            'amount_paise' => 'integer',
            'effect_paise' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /** Only a pending adjustment may change, and only into a decision. */
    protected function allowsLedgerUpdate(): bool
    {
        $original = $this->getRawOriginal('status');

        return $original === AdjustmentStatus::Pending->value
            && array_diff(array_keys($this->getDirty()), ['status', 'decided_by', 'decided_at', 'decision_note', 'updated_at']) === [];
    }

    public function isPending(): bool
    {
        return $this->status === AdjustmentStatus::Pending;
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<FeeChargeLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(FeeChargeLine::class, 'fee_charge_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
