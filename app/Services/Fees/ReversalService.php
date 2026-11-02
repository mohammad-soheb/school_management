<?php

namespace App\Services\Fees;

use App\Enums\Fees\AllocationKind;
use App\Enums\Fees\PaymentStatus;
use App\Models\Fees\FeeAllocation;
use App\Models\Fees\FeePayment;
use App\Models\Fees\FeePaymentReversal;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reverses a mistaken payment (FR-409, FR-415, design §6.5). Nothing is edited or deleted: a reversal row is written,
 * every positive net allocation of the payment gets a compensating negative allocation, and the payment is marked
 * reversed (its receipt keeps its number). Dated today, on an open day; full reversal only.
 */
final class ReversalService
{
    public function __construct(
        private readonly FeeLocks $locks,
        private readonly CashierCloseService $days,
        private readonly AuditLogger $audit,
    ) {}

    public function reverse(FeePayment $payment, string $reason, User $actor): FeePaymentReversal
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Give a reason of at least 5 characters.']);
        }
        if ($payment->isReversed()) {
            throw ValidationException::withMessages(['reason' => 'Receipt '.$payment->receipt_no.' is already reversed.']);
        }

        $today = school_today();
        $this->days->ensure($today);

        try {
            return DB::transaction(function () use ($payment, $reason, $actor, $today) {
                $this->days->lockOpen($today, 'reason');
                $this->locks->student((int) $payment->student_id);

                $locked = FeePayment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->isReversed()) {
                    throw ValidationException::withMessages(['reason' => 'Receipt '.$locked->receipt_no.' is already reversed.']);
                }

                $reversal = FeePaymentReversal::create([
                    'fee_payment_id' => $locked->getKey(),
                    'amount_paise' => $locked->amount_paise,
                    'reason' => $reason,
                    'reversed_on' => $today->toDateString(),
                    'reversed_by' => $actor->getKey(),
                ]);

                // Net allocation per line from this payment (payment + credit rows, less earlier reversals).
                $net = FeeAllocation::query()
                    ->where('fee_payment_id', $locked->getKey())
                    ->groupBy('fee_charge_line_id')
                    ->selectRaw('fee_charge_line_id, SUM(amount_paise) AS net_paise')
                    ->pluck('net_paise', 'fee_charge_line_id');

                $undone = [];
                foreach ($net as $lineId => $amount) {
                    $amount = (int) $amount;
                    if ($amount <= 0) {
                        continue;
                    }
                    FeeAllocation::create([
                        'fee_payment_id' => $locked->getKey(),
                        'fee_charge_line_id' => (int) $lineId,
                        'student_id' => $locked->student_id,
                        'amount_paise' => -$amount,
                        'kind' => AllocationKind::Reversal,
                        'allocated_on' => $today->toDateString(),
                        'fee_payment_reversal_id' => $reversal->getKey(),
                        'created_by' => $actor->getKey(),
                    ]);
                    $undone[(int) $lineId] = $amount;
                }

                $locked->forceFill([
                    'status' => PaymentStatus::Reversed,
                    'reversed_at' => now(),
                    'reversed_by' => $actor->getKey(),
                ])->save();

                $this->audit->record('fee_payment.reversed', $locked, ['status' => PaymentStatus::Posted->value], [
                    'status' => PaymentStatus::Reversed->value,
                    'receipt_no' => $locked->receipt_no,
                    'amount_paise' => $locked->amount_paise,
                    'reason' => $reason,
                    'reversed_on' => $today->toDateString(),
                    'unallocated' => $undone,
                ], actor: $actor);

                return $reversal;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // A double click on "Reverse": the first one won (UNIQUE(school_id, fee_payment_id)).
            throw ValidationException::withMessages(['reason' => 'Receipt '.$payment->receipt_no.' is already reversed.']);
        }
    }
}
