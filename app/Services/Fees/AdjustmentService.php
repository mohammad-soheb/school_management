<?php

namespace App\Services\Fees;

use App\Enums\Fees\AdjustmentStatus;
use App\Enums\Fees\AdjustmentType;
use App\Models\Fees\FeeAdjustment;
use App\Models\Fees\FeeChargeLine;
use App\Models\Students\Student;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Discounts, scholarships, waivers and fines (FR-404, design §8). Requested -> approved/rejected/cancelled by a
 * different user (maker-checker). Only approved adjustments change a balance; nothing posted is ever edited.
 */
final class AdjustmentService
{
    public function __construct(
        private readonly FeeSettings $settings,
        private readonly FeeLocks $locks,
        private readonly AllocationService $allocation,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{fee_charge_line_id: int|string, type: string, amount_paise: int, reason: string, idempotency_key?: string|null}  $data
     */
    public function request(Student $student, array $data, User $actor): FeeAdjustment
    {
        $type = AdjustmentType::from($data['type']);
        if ($type === AdjustmentType::CarryForward) {
            throw ValidationException::withMessages(['type' => 'Carry-forward is recorded by the promotion workflow.']);
        }

        $amount = (int) $data['amount_paise'];
        $line = FeeChargeLine::query()->whereKey((int) $data['fee_charge_line_id'])->where('student_id', $student->getKey())->first();
        if ($line === null) {
            throw ValidationException::withMessages(['fee_charge_line_id' => 'Choose a fee line of this student.']);
        }
        $this->assertAmount($type, $amount, (int) $line->getKey(), (int) $student->getKey(), 'amount');

        $key = $data['idempotency_key'] ?? null;
        if ($key && ($existing = FeeAdjustment::query()->where('idempotency_key', $key)->first())) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($student, $line, $type, $amount, $data, $key, $actor) {
                $adjustment = FeeAdjustment::create([
                    'student_id' => $student->getKey(),
                    'fee_charge_line_id' => $line->getKey(),
                    'type' => $type,
                    'amount_paise' => $amount,
                    'effect_paise' => $type->sign() * $amount,
                    'reason' => trim($data['reason']),
                    'requested_by' => $actor->getKey(),
                    'idempotency_key' => $key ?: null,
                ]);

                $this->audit->record('fee_adjustment.requested', $adjustment, [], [
                    'student_id' => $student->getKey(),
                    'fee_charge_line_id' => $line->getKey(),
                    'type' => $type->value,
                    'amount_paise' => $amount,
                    'reason' => $adjustment->reason,
                ], actor: $actor);

                return $adjustment;
            });
        } catch (UniqueConstraintViolationException $e) {
            return FeeAdjustment::query()->where('idempotency_key', $key)->first() ?? throw $e;
        }
    }

    public function approve(FeeAdjustment $adjustment, ?string $note, User $actor): FeeAdjustment
    {
        return DB::transaction(function () use ($adjustment, $note, $actor) {
            $this->locks->student((int) $adjustment->student_id);
            $locked = $this->lockPending($adjustment);

            if (! $this->settings->selfApproval() && (int) $locked->requested_by === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['decision' => 'You requested this adjustment, so another user must approve it.']);
            }

            $this->assertAmount($locked->type, $locked->amount_paise, (int) $locked->fee_charge_line_id, (int) $locked->student_id, 'decision');

            $this->decide($locked, AdjustmentStatus::Approved, $note, $actor);

            if ($locked->type === AdjustmentType::Fine) {
                $this->allocation->autoApplyCredit((int) $locked->student_id, $actor);
            }

            return $locked;
        }, 3);
    }

    public function reject(FeeAdjustment $adjustment, ?string $note, User $actor): FeeAdjustment
    {
        return DB::transaction(function () use ($adjustment, $note, $actor) {
            $locked = $this->lockPending($adjustment);
            if (trim((string) $note) === '') {
                throw ValidationException::withMessages(['decision_note' => 'Say why the request is rejected.']);
            }
            $this->decide($locked, AdjustmentStatus::Rejected, $note, $actor);

            return $locked;
        }, 3);
    }

    public function cancel(FeeAdjustment $adjustment, User $actor): FeeAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor) {
            $locked = $this->lockPending($adjustment);
            $this->decide($locked, AdjustmentStatus::Cancelled, null, $actor);

            return $locked;
        }, 3);
    }

    /** @return LengthAwarePaginator<int, FeeAdjustment> */
    public function paginate(?string $status, int $perPage = 25): LengthAwarePaginator
    {
        return FeeAdjustment::query()
            ->with(['student', 'line.head', 'requester', 'decider'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->where('type', '!=', AdjustmentType::CarryForward->value)
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function pendingCount(): int
    {
        return FeeAdjustment::query()->where('status', AdjustmentStatus::Pending->value)->count();
    }

    /** @return Collection<int, FeeAdjustment> */
    public function forStudent(Student $student): Collection
    {
        return FeeAdjustment::query()->with(['line.head', 'requester', 'decider'])->where('student_id', $student->getKey())->orderByDesc('id')->get();
    }

    private function lockPending(FeeAdjustment $adjustment): FeeAdjustment
    {
        $locked = FeeAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw ValidationException::withMessages(['decision' => 'This request was already '.strtolower($locked->status->label()).'.']);
        }

        return $locked;
    }

    private function decide(FeeAdjustment $adjustment, AdjustmentStatus $status, ?string $note, User $actor): void
    {
        $note = trim((string) $note);
        $adjustment->forceFill([
            'status' => $status,
            'decided_by' => $actor->getKey(),
            'decided_at' => now(),
            'decision_note' => $note === '' ? null : $note,
        ])->save();

        $this->audit->record('fee_adjustment.'.$status->value, $adjustment, ['status' => AdjustmentStatus::Pending->value], [
            'status' => $status->value,
            'type' => $adjustment->type->value,
            'amount_paise' => $adjustment->amount_paise,
            'fee_charge_line_id' => $adjustment->fee_charge_line_id,
            'decision_note' => $adjustment->decision_note,
        ], actor: $actor);
    }

    /** Reductions may not exceed the line's outstanding (sign-off 15); fines respect the optional cap (sign-off 16). */
    private function assertAmount(AdjustmentType $type, int $amount, int $lineId, int $studentId, string $field): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([$field => 'Enter an amount greater than zero.']);
        }

        if ($type->isReduction()) {
            $balance = $this->allocation->lineBalances($studentId, [$lineId])->first();
            $outstanding = $balance?->outstanding() ?? 0;
            if ($amount > $outstanding) {
                throw ValidationException::withMessages([$field => 'A '.strtolower($type->label()).' cannot be more than what is still due on the line ('.Money::format($outstanding).'). Reverse the payment first if the line is already paid.']);
            }
        } elseif (($cap = $this->settings->fineMaxPaise()) !== null && $amount > $cap) {
            throw ValidationException::withMessages([$field => 'A fine cannot be more than '.Money::format($cap).'.']);
        }
    }
}
