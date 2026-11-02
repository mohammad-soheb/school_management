<?php

namespace App\Services\Fees;

use App\Models\Fees\FeeReceiptSequence;
use App\Models\SchoolSetup\AcademicYear;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Receipt numbers per school and scope (D04, design §6.2): RCP/2026-27/000123, or RCP/000123 when the scope is
 * 'never'. The scope is the academic year that CONTAINS the payment date. The counter row is read FOR UPDATE inside
 * the payment transaction, so a rolled-back payment also rolls back its number (no gaps), and
 * UNIQUE(school_id, receipt_no) on fee_payments makes a duplicate impossible even if locking were bypassed.
 */
final class ReceiptNumberService
{
    public function __construct(private readonly FeeSettings $settings, private readonly TenantContext $context) {}

    public function scopeFor(AcademicYear $year): string
    {
        return $this->settings->receiptScope() === 'never' ? 'all' : (string) $year->name;
    }

    /**
     * Create the counter row if it is missing. Runs BEFORE the payment transaction (autocommit INSERT IGNORE), so two
     * first-ever payments never deadlock on creating the same row; inside the transaction it is only locked.
     */
    public function ensure(string $scope): void
    {
        DB::table('fee_receipt_sequences')->insertOrIgnore([
            'school_id' => $this->context->requireId(),
            'scope_key' => $scope,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Take the next number. Must run inside the payment transaction.
     *
     * @return array{0: string, 1: int} [receipt_no, number]
     */
    public function next(string $scope): array
    {
        $sequence = FeeReceiptSequence::query()->where('scope_key', $scope)->lockForUpdate()->first();

        if ($sequence === null) {
            // Defensive: ensure() was not called (e.g. a caller outside PaymentPostingService).
            $this->ensure($scope);
            $sequence = FeeReceiptSequence::query()->where('scope_key', $scope)->lockForUpdate()->firstOrFail();
        }

        $number = $sequence->last_number + 1;
        $sequence->forceFill(['last_number' => $number])->save();

        return [$this->format($scope, $number), $number];
    }

    public function format(string $scope, int $number): string
    {
        $padded = str_pad((string) $number, $this->settings->receiptPadding(), '0', STR_PAD_LEFT);

        return $scope === 'all'
            ? $this->settings->receiptPrefix().'/'.$padded
            : $this->settings->receiptPrefix().'/'.$scope.'/'.$padded;
    }
}
