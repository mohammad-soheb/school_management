<?php

namespace App\Services\Fees;

use App\Enums\Fees\AllocationKind;
use App\Enums\Fees\PaymentMode;
use App\Models\Fees\FeePayment;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\User;
use App\Services\Fees\Data\LineBalance;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a payment (FR-405, FR-413, design §6.1). ONE transaction creates the payment, its receipt number, its
 * allocations and the audit event; the same idempotency key returns the first payment instead of a second one.
 */
final class PaymentPostingService
{
    public function __construct(
        private readonly FeeSettings $settings,
        private readonly FeeLocks $locks,
        private readonly AllocationService $allocation,
        private readonly ReceiptNumberService $receipts,
        private readonly CashierCloseService $days,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{student_id: int|string, amount_paise: int, mode: string, reference?: string|null, instrument_date?: string|null, paid_on: string, remarks?: string|null, lines?: list<int|string>|null, idempotency_key: string}  $data
     */
    public function post(array $data, User $actor): FeePayment
    {
        $studentId = (int) $data['student_id'];
        $amount = (int) $data['amount_paise'];
        $key = (string) $data['idempotency_key'];
        $selected = isset($data['lines']) && $data['lines'] !== [] ? array_values(array_unique(array_map('intval', (array) $data['lines']))) : null;

        // A retry of an already committed form: answer before taking any lock.
        if ($existing = FeePayment::query()->where('idempotency_key', $key)->first()) {
            return $this->sameRequestOrFail($existing, $studentId, $amount);
        }

        $paidOn = CarbonImmutable::parse($data['paid_on'])->startOfDay();
        $mode = PaymentMode::from($data['mode']);
        $this->assertRules($amount, $mode, $data['reference'] ?? null, $paidOn);
        $year = $this->yearContaining($paidOn);
        $scope = $this->receipts->scopeFor($year);

        $this->days->ensure($paidOn);
        $this->receipts->ensure($scope);

        try {
            return DB::transaction(function () use ($studentId, $amount, $key, $selected, $paidOn, $mode, $year, $scope, $data, $actor) {
                // 1. day row (refuses a closed day and serialises with the close)
                $this->days->lockOpen($paidOn);

                // 2. idempotency under the lock: a twin that committed while we waited is returned as-is
                if ($existing = FeePayment::query()->where('idempotency_key', $key)->lockForUpdate()->first()) {
                    return $this->sameRequestOrFail($existing, $studentId, $amount);
                }

                // 3. student row: serialises all money for this student
                $student = $this->locks->student($studentId);

                // 4. allocation plan from the current open lines
                $open = $this->allocation->openLines($studentId);
                if ($selected !== null) {
                    $this->allocation->assertSelectable($selected, $open);
                } elseif ($this->settings->allocationOrder() === 'selected_only' && $open->isNotEmpty()) {
                    throw ValidationException::withMessages(['lines' => 'Select the fee lines this payment settles.']);
                }

                $plan = $this->allocation->plan($open, $amount, $selected);
                $outstanding = (int) $open->sum(fn (LineBalance $b) => $b->outstanding());
                if (! $this->settings->allowAdvance() && $amount > array_sum($plan)) {
                    throw ValidationException::withMessages(['amount' => 'The amount is more than the dues ('.\App\Support\Money\Money::format($outstanding).'). Advance payments are not accepted.']);
                }

                // 5. receipt number (sequence row locked last)
                [$receiptNo, $number] = $this->receipts->next($scope);

                // 6. payment, allocations, audit
                $payment = new FeePayment([
                    'student_id' => $student->getKey(),
                    'academic_year_id' => $year->getKey(),
                    'amount_paise' => $amount,
                    'mode' => $mode,
                    'reference' => $this->clean($data['reference'] ?? null),
                    'instrument_date' => $data['instrument_date'] ?? null,
                    'paid_on' => $paidOn->toDateString(),
                    'received_by' => $actor->getKey(),
                    'remarks' => $this->clean($data['remarks'] ?? null),
                    'idempotency_key' => $key,
                ]);
                $payment->forceFill(['receipt_no' => $receiptNo, 'receipt_number' => $number])->save();

                $allocated = $this->allocation->write($payment, $plan, AllocationKind::Payment, $actor, $paidOn);

                $this->audit->record('fee_payment.posted', $payment, [], [
                    'receipt_no' => $receiptNo,
                    'student_id' => $student->getKey(),
                    'amount_paise' => $amount,
                    'mode' => $mode->value,
                    'paid_on' => $paidOn->toDateString(),
                    'allocated_paise' => $allocated,
                    'credit_paise' => $amount - $allocated,
                    'allocations' => $plan,
                ], actor: $actor);

                return $payment;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            // A racing twin with the same key committed first (H3): return its receipt.
            $existing = FeePayment::query()->where('idempotency_key', $key)->first();
            if ($existing === null) {
                throw $e;
            }

            return $this->sameRequestOrFail($existing, $studentId, $amount);
        }
    }

    /** The academic year that contains a date (receipt scope and payment year). */
    public function yearContaining(CarbonImmutable $date, string $field = 'paid_on'): AcademicYear
    {
        $year = AcademicYear::query()
            ->where('starts_on', '<=', $date->toDateString())
            ->where('ends_on', '>=', $date->toDateString())
            ->first();

        if ($year === null) {
            throw ValidationException::withMessages([$field => 'No academic year covers '.$date->format('d M Y').'. Set up the academic year first.']);
        }

        return $year;
    }

    private function assertRules(int $amount, PaymentMode $mode, ?string $reference, CarbonImmutable $paidOn): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }
        if ($amount > $this->settings->maxPaymentPaise()) {
            throw ValidationException::withMessages(['amount' => 'The amount is above the limit of '.\App\Support\Money\Money::format($this->settings->maxPaymentPaise()).' for one receipt.']);
        }
        if (! in_array($mode->value, $this->settings->paymentModes(), true)) {
            throw ValidationException::withMessages(['mode' => 'This school does not accept '.$mode->label().' payments.']);
        }
        if (in_array($mode->value, $this->settings->referenceRequiredFor(), true) && trim((string) $reference) === '') {
            throw ValidationException::withMessages(['reference' => 'Enter the '.strtolower($mode->referenceLabel()).'.']);
        }

        $today = school_today();
        if ($paidOn->gt($today)) {
            throw ValidationException::withMessages(['paid_on' => 'A payment cannot be dated in the future.']);
        }
        if ($paidOn->lt($today->subDays($this->settings->maxBackdateDays()))) {
            throw ValidationException::withMessages(['paid_on' => 'A payment can be back-dated by at most '.$this->settings->maxBackdateDays().' days.']);
        }
    }

    private function sameRequestOrFail(FeePayment $existing, int $studentId, int $amount): FeePayment
    {
        if ((int) $existing->student_id !== $studentId || $existing->amount_paise !== $amount) {
            throw ValidationException::withMessages(['amount' => 'This form was already used for receipt '.$existing->receipt_no.'. Open the collection screen again to record a new payment.']);
        }

        return $existing;
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
