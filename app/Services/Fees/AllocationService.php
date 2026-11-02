<?php

namespace App\Services\Fees;

use App\Enums\Fees\AdjustmentStatus;
use App\Enums\Fees\AdjustmentType;
use App\Enums\Fees\AllocationKind;
use App\Enums\Fees\PaymentStatus;
use App\Models\Fees\FeeAdjustment;
use App\Models\Fees\FeeAllocation;
use App\Models\Fees\FeeChargeLine;
use App\Models\Fees\FeePayment;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Fees\Data\LineBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Line balances, the allocation order (D03, design §6.3) and advance credit (§6.4).
 * Methods that write must be called inside a transaction that holds the student's row lock (FeeLocks::student()).
 */
final class AllocationService
{
    public function __construct(private readonly FeeSettings $settings) {}

    /**
     * Every charge line of a student (or only $lineIds) with its derived amounts, in allocation order:
     * due date, then charge id, then line id.
     *
     * @param  list<int>|null  $lineIds
     * @return Collection<int, LineBalance>
     */
    public function lineBalances(int $studentId, ?array $lineIds = null, ?int $enrollmentId = null): Collection
    {
        $approved = AdjustmentStatus::Approved->value;

        $query = FeeChargeLine::query()
            ->with(['head', 'charge'])
            ->where('fee_charge_lines.student_id', $studentId)
            ->select('fee_charge_lines.*')
            ->selectSub(FeeAdjustment::query()->selectRaw('COALESCE(SUM(CASE WHEN effect_paise > 0 THEN effect_paise ELSE 0 END), 0)')
                ->whereColumn('fee_adjustments.fee_charge_line_id', 'fee_charge_lines.id')->where('status', $approved), 'fines_sum')
            ->selectSub(FeeAdjustment::query()->selectRaw('COALESCE(SUM(CASE WHEN effect_paise < 0 THEN -effect_paise ELSE 0 END), 0)')
                ->whereColumn('fee_adjustments.fee_charge_line_id', 'fee_charge_lines.id')->where('status', $approved), 'reductions_sum')
            ->selectSub(FeeAdjustment::query()->selectRaw('COALESCE(SUM(amount_paise), 0)')
                ->whereColumn('fee_adjustments.fee_charge_line_id', 'fee_charge_lines.id')->where('status', $approved)
                ->where('type', AdjustmentType::CarryForward->value), 'carried_sum')
            ->selectSub(FeeAllocation::query()->selectRaw('COALESCE(SUM(amount_paise), 0)')
                ->whereColumn('fee_allocations.fee_charge_line_id', 'fee_charge_lines.id'), 'allocated_sum')
            ->orderBy('fee_charge_lines.due_on')
            ->orderBy('fee_charge_lines.fee_charge_id')
            ->orderBy('fee_charge_lines.id');

        if ($lineIds !== null) {
            $query->whereIn('fee_charge_lines.id', $lineIds);
        }

        if ($enrollmentId !== null) {
            $query->whereHas('charge', fn ($q) => $q->where('student_enrollment_id', $enrollmentId));
        }

        return $query->get()->map(fn (FeeChargeLine $line) => new LineBalance(
            line: $line,
            amount: (int) $line->amount_paise,
            fines: (int) $line->getAttribute('fines_sum'),
            reductions: (int) $line->getAttribute('reductions_sum'),
            carried: (int) $line->getAttribute('carried_sum'),
            allocated: (int) $line->getAttribute('allocated_sum'),
        ))->values();
    }

    /** @return Collection<int, LineBalance> */
    public function openLines(int $studentId): Collection
    {
        return $this->lineBalances($studentId)->filter(fn (LineBalance $b) => $b->outstanding() > 0)->values();
    }

    /**
     * Pure allocation plan: oldest due first over $open (already ordered), or only over $selectedIds (still in due
     * order). Returns [line id => paise]. Whatever is not planned stays unallocated (credit).
     *
     * @param  Collection<int, LineBalance>  $open
     * @param  list<int>|null  $selectedIds
     * @return array<int, int>
     */
    public function plan(Collection $open, int $amount, ?array $selectedIds = null): array
    {
        $plan = [];
        $remaining = $amount;

        foreach ($open as $balance) {
            if ($remaining <= 0) {
                break;
            }
            if ($selectedIds !== null && ! in_array($balance->id(), $selectedIds, true)) {
                continue;
            }

            $take = min($remaining, $balance->outstanding());
            if ($take > 0) {
                $plan[$balance->id()] = $take;
                $remaining -= $take;
            }
        }

        return $plan;
    }

    /**
     * Write allocation rows for a plan.
     *
     * @param  array<int, int>  $plan
     */
    public function write(FeePayment $payment, array $plan, AllocationKind $kind, User $actor, CarbonImmutable $on): int
    {
        $total = 0;
        foreach ($plan as $lineId => $amount) {
            FeeAllocation::create([
                'fee_payment_id' => $payment->getKey(),
                'fee_charge_line_id' => $lineId,
                'student_id' => $payment->student_id,
                'amount_paise' => $amount,
                'kind' => $kind,
                'allocated_on' => $on->toDateString(),
                'created_by' => $actor->getKey(),
            ]);
            $total += $amount;
        }

        return $total;
    }

    /** Unapplied advance of a student: posted payments minus what they have settled. Never negative. */
    public function creditFor(int $studentId): int
    {
        return (int) $this->paymentsWithUnallocated($studentId)->sum(fn (FeePayment $p) => $p->amount_paise - (int) $p->getAttribute('allocated_sum'));
    }

    /**
     * Apply held credit to open lines: oldest payment first, oldest due line first, as kind=credit allocations dated
     * $on (no new receipt). Caller holds the student lock. Returns paise applied.
     */
    public function applyCredit(int $studentId, User $actor, ?CarbonImmutable $on = null): int
    {
        $on ??= school_today();
        $payments = $this->paymentsWithUnallocated($studentId);
        if ($payments->isEmpty()) {
            return 0;
        }

        $open = $this->openLines($studentId);
        $applied = 0;

        foreach ($payments as $payment) {
            $available = $payment->amount_paise - (int) $payment->getAttribute('allocated_sum');
            if ($available <= 0 || $open->isEmpty()) {
                continue;
            }

            $plan = $this->plan($open, $available);
            $applied += $this->write($payment, $plan, AllocationKind::Credit, $actor, $on);

            // Re-read the lines touched so the next payment sees what is left.
            $open = $this->openLines($studentId);
        }

        return $applied;
    }

    /** Auto-apply credit when the school setting is on (sign-off rule 7). */
    public function autoApplyCredit(int $studentId, User $actor): int
    {
        return $this->settings->autoApplyCredit() ? $this->applyCredit($studentId, $actor) : 0;
    }

    /**
     * Validate a cashier's selected lines: each must belong to the student and still be outstanding.
     *
     * @param  list<int>  $selectedIds
     * @param  Collection<int, LineBalance>  $open
     */
    public function assertSelectable(array $selectedIds, Collection $open): void
    {
        $openIds = $open->map(fn (LineBalance $b) => $b->id())->all();
        foreach ($selectedIds as $id) {
            if (! in_array($id, $openIds, true)) {
                throw ValidationException::withMessages(['lines' => 'A selected fee line is already settled or does not belong to this student. Reload and try again.']);
            }
        }
    }

    /** @return Collection<int, FeePayment> posted payments with something left to allocate, oldest first */
    private function paymentsWithUnallocated(int $studentId): Collection
    {
        return FeePayment::query()
            ->where('student_id', $studentId)
            ->where('status', PaymentStatus::Posted->value)
            ->select('fee_payments.*')
            ->selectSub(FeeAllocation::query()->selectRaw('COALESCE(SUM(amount_paise), 0)')
                ->whereColumn('fee_allocations.fee_payment_id', 'fee_payments.id'), 'allocated_sum')
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get()
            ->filter(fn (FeePayment $p) => $p->amount_paise > (int) $p->getAttribute('allocated_sum'))
            ->values();
    }

    /** Convenience for views: a student's balances keyed by line id. @return Collection<int, LineBalance> */
    public function forStudent(Student $student): Collection
    {
        return $this->lineBalances((int) $student->getKey())->keyBy(fn (LineBalance $b) => $b->id());
    }
}
