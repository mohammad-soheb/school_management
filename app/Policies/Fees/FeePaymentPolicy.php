<?php

namespace App\Policies\Fees;

use App\Models\Fees\FeePayment;
use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Model;

/** Receipts are viewed with fees.payments.view, posted with fees.payments.post, reversed with fees.payments.reverse. */
class FeePaymentPolicy extends TenantPolicy
{
    protected string $module = 'fees.payments';

    protected function managePermission(): string
    {
        return 'fees.payments.post';
    }

    public function reverse(User $user, FeePayment $payment): bool
    {
        return $this->sameSchool($payment) && ! $payment->isReversed() && $user->hasPermission('fees.payments.reverse');
    }

    /** Posted payments are immutable (FR-415). */
    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
