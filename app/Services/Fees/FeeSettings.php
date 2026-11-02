<?php

namespace App\Services\Fees;

use App\Support\Tenancy\TenantContext;

/**
 * Typed access to the active school's fee settings (config/school_defaults/fees.php, overridable per school).
 * Every value here is a proposed default awaiting accountant sign-off (docs/fees/FEE_LEDGER_DESIGN.md §13).
 */
final class FeeSettings
{
    public function __construct(private readonly TenantContext $context) {}

    public function receiptPrefix(): string
    {
        return (string) $this->get('receipt_prefix', 'RCP');
    }

    /** 'academic_year' or 'never'. */
    public function receiptScope(): string
    {
        return $this->get('receipt_scope', 'academic_year') === 'never' ? 'never' : 'academic_year';
    }

    public function receiptPadding(): int
    {
        return max(1, min(12, (int) $this->get('receipt_padding', 6)));
    }

    /** 'oldest_due_first' or 'selected_only'. */
    public function allocationOrder(): string
    {
        return $this->get('allocation_order', 'oldest_due_first') === 'selected_only' ? 'selected_only' : 'oldest_due_first';
    }

    /** @return list<string> */
    public function paymentModes(): array
    {
        return array_values(array_map('strval', (array) $this->get('payment_modes', ['cash', 'upi', 'bank_transfer', 'cheque', 'other'])));
    }

    /** @return list<string> */
    public function referenceRequiredFor(): array
    {
        return array_values(array_map('strval', (array) $this->get('reference_required_for', ['upi', 'bank_transfer', 'cheque'])));
    }

    public function maxPaymentPaise(): int
    {
        return (int) $this->get('max_payment_paise', 100_000_000);
    }

    public function maxBackdateDays(): int
    {
        return max(0, (int) $this->get('max_backdate_days', 7));
    }

    public function allowAdvance(): bool
    {
        return (bool) $this->get('allow_advance', true);
    }

    public function autoApplyCredit(): bool
    {
        return (bool) $this->get('auto_apply_credit', true);
    }

    public function selfApproval(): bool
    {
        return (bool) $this->get('adjustments_self_approval', false);
    }

    public function fineMaxPaise(): ?int
    {
        $value = $this->get('fine_max_paise');

        return $value === null ? null : (int) $value;
    }

    private function get(string $key, mixed $default = null): mixed
    {
        return $this->context->requireSchool()->setting("fees.{$key}", $default);
    }
}
