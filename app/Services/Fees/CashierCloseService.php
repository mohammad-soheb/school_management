<?php

namespace App\Services\Fees;

use App\Enums\Fees\CashierDayStatus;
use App\Enums\Fees\PaymentMode;
use App\Models\Fees\FeeCashierDay;
use App\Models\Fees\FeePayment;
use App\Models\Fees\FeePaymentReversal;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Daily cashier close (FR-407, FR-414, D14, design §10). Every payment and reversal locks its day row FOR UPDATE, so
 * a close and a payment for the same date are serialised: a payment cannot slip in after the close.
 */
final class CashierCloseService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    /** Create the day row if missing (autocommit INSERT IGNORE before the business transaction). */
    public function ensure(CarbonImmutable $date): void
    {
        DB::table('fee_cashier_days')->insertOrIgnore([
            'school_id' => $this->context->requireId(),
            'business_date' => $date->toDateString(),
            'status' => CashierDayStatus::Open->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Lock the day row (inside a transaction); refuse when the day is closed. */
    public function lockOpen(CarbonImmutable $date, string $field = 'paid_on'): FeeCashierDay
    {
        $day = $this->lock($date);

        if ($day->isClosed()) {
            throw ValidationException::withMessages([$field => 'The collection for '.$date->format('d M Y').' is closed. Use an open date.']);
        }

        return $day;
    }

    public function isClosed(CarbonImmutable $date): bool
    {
        return FeeCashierDay::query()->where('business_date', $date->toDateString())->where('status', CashierDayStatus::Closed->value)->exists();
    }

    public function find(CarbonImmutable $date): ?FeeCashierDay
    {
        return FeeCashierDay::query()->with(['closer', 'reviewer'])->where('business_date', $date->toDateString())->first();
    }

    /**
     * System totals for a date: payments dated that day by mode, minus reversals dated that day (by the reversed
     * payment's mode), with a receiving-user x mode breakdown. All paise.
     *
     * @return array{modes: array<string, array{gross: int, reversed: int, net: int}>, gross: int, reversed: int, net: int, receipts: int, reversals: int, breakdown: list<array{user_id: int, user: string, mode: string, received: int, reversed: int, net: int}>, payments: \Illuminate\Support\Collection<int, FeePayment>, reversal_rows: \Illuminate\Support\Collection<int, FeePaymentReversal>}
     */
    public function totals(CarbonImmutable $date): array
    {
        $payments = FeePayment::query()->with(['student', 'receiver'])->where('paid_on', $date->toDateString())->orderBy('receipt_number')->orderBy('id')->get();
        $reversals = FeePaymentReversal::query()->with(['payment.student', 'reverser'])->where('reversed_on', $date->toDateString())->orderBy('id')->get();

        $modes = [];
        foreach (PaymentMode::cases() as $mode) {
            $modes[$mode->value] = ['gross' => 0, 'reversed' => 0, 'net' => 0];
        }

        $breakdown = [];
        $row = function (int $userId, string $user, string $mode) use (&$breakdown): string {
            $key = $userId.'|'.$mode;
            $breakdown[$key] ??= ['user_id' => $userId, 'user' => $user, 'mode' => $mode, 'received' => 0, 'reversed' => 0, 'net' => 0];

            return $key;
        };

        foreach ($payments as $payment) {
            $mode = $payment->mode->value;
            $modes[$mode]['gross'] += $payment->amount_paise;
            $key = $row((int) $payment->received_by, (string) $payment->receiver?->name, $mode);
            $breakdown[$key]['received'] += $payment->amount_paise;
        }

        foreach ($reversals as $reversal) {
            $mode = $reversal->payment->mode->value;
            $modes[$mode]['reversed'] += $reversal->amount_paise;
            $key = $row((int) $reversal->reversed_by, (string) $reversal->reverser?->name, $mode);
            $breakdown[$key]['reversed'] += $reversal->amount_paise;
        }

        foreach ($modes as $mode => $values) {
            $modes[$mode]['net'] = $values['gross'] - $values['reversed'];
        }
        foreach ($breakdown as $key => $values) {
            $breakdown[$key]['net'] = $values['received'] - $values['reversed'];
        }

        $gross = array_sum(array_column($modes, 'gross'));
        $reversed = array_sum(array_column($modes, 'reversed'));

        usort($breakdown, fn ($a, $b) => [$a['user'], $a['mode']] <=> [$b['user'], $b['mode']]);

        return [
            'modes' => $modes,
            'gross' => $gross,
            'reversed' => $reversed,
            'net' => $gross - $reversed,
            'receipts' => $payments->count(),
            'reversals' => $reversals->count(),
            'breakdown' => array_values($breakdown),
            'payments' => $payments,
            'reversal_rows' => $reversals,
        ];
    }

    /**
     * Close a business date: snapshot the totals, record counted cash and the difference, lock the date.
     *
     * @param  array{business_date: string, counted_cash_paise: int, notes?: string|null}  $data
     */
    public function close(array $data, User $actor): FeeCashierDay
    {
        $date = CarbonImmutable::parse($data['business_date'])->startOfDay();

        if ($date->gt(school_today())) {
            throw ValidationException::withMessages(['business_date' => 'A future date cannot be closed.']);
        }

        $counted = (int) $data['counted_cash_paise'];
        $notes = trim((string) ($data['notes'] ?? ''));

        $this->ensure($date);

        return DB::transaction(function () use ($date, $counted, $notes, $actor) {
            $day = $this->lock($date);
            if ($day->isClosed()) {
                throw ValidationException::withMessages(['business_date' => 'This day is already closed.']);
            }

            $totals = $this->totals($date);
            $difference = $counted - $totals['modes'][PaymentMode::Cash->value]['net'];

            if ($difference !== 0 && $notes === '') {
                throw ValidationException::withMessages(['notes' => 'Explain the difference between counted cash and the system total.']);
            }

            $day->forceFill([
                'status' => CashierDayStatus::Closed,
                'cash_paise' => $totals['modes']['cash']['net'],
                'upi_paise' => $totals['modes']['upi']['net'],
                'bank_transfer_paise' => $totals['modes']['bank_transfer']['net'],
                'cheque_paise' => $totals['modes']['cheque']['net'],
                'other_paise' => $totals['modes']['other']['net'],
                'gross_paise' => $totals['gross'],
                'reversed_paise' => $totals['reversed'],
                'net_paise' => $totals['net'],
                'receipts_count' => $totals['receipts'],
                'reversals_count' => $totals['reversals'],
                'counted_cash_paise' => $counted,
                'difference_paise' => $difference,
                'notes' => $notes === '' ? null : $notes,
                'breakdown' => $totals['breakdown'],
                'closed_by' => $actor->getKey(),
                'closed_at' => now(),
            ])->save();

            $this->audit->record('fee_cashier_day.closed', $day, [], [
                'business_date' => $date->toDateString(),
                'net_paise' => $totals['net'],
                'cash_paise' => $totals['modes']['cash']['net'],
                'counted_cash_paise' => $counted,
                'difference_paise' => $difference,
            ], actor: $actor);

            return $day;
        }, 3);
    }

    public function review(FeeCashierDay $day, ?string $note, User $actor): FeeCashierDay
    {
        return DB::transaction(function () use ($day, $note, $actor) {
            $locked = $this->lock($day->business_date);

            if (! $locked->isClosed()) {
                throw ValidationException::withMessages(['review' => 'Only a closed day can be reviewed.']);
            }
            if ($locked->reviewed_at !== null) {
                throw ValidationException::withMessages(['review' => 'This day has already been reviewed.']);
            }
            if ((int) $locked->closed_by === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['review' => 'The person who closed the day cannot also review it.']);
            }

            $locked->forceFill(['reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $note])->save();
            $this->audit->record('fee_cashier_day.reviewed', $locked, [], ['business_date' => $locked->business_date->toDateString(), 'review_note' => $note], actor: $actor);

            return $locked;
        }, 3);
    }

    /** @return LengthAwarePaginator<int, FeeCashierDay> */
    public function recent(int $perPage = 20): LengthAwarePaginator
    {
        return FeeCashierDay::query()->with(['closer', 'reviewer'])->orderByDesc('business_date')->paginate($perPage)->withQueryString();
    }

    private function lock(CarbonImmutable $date): FeeCashierDay
    {
        $day = FeeCashierDay::query()->where('business_date', $date->toDateString())->lockForUpdate()->first();

        if ($day === null) {
            $this->ensure($date);
            $day = FeeCashierDay::query()->where('business_date', $date->toDateString())->lockForUpdate()->firstOrFail();
        }

        return $day;
    }
}
