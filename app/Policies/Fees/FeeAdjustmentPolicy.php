<?php

namespace App\Policies\Fees;

use App\Models\Fees\FeeAdjustment;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Maker-checker for discounts, scholarships, waivers and fines (design §8, sign-off rule 14): requested with
 * fees.adjustments.create, decided with fees.adjustments.approve by a different user unless the school allows
 * self-approval.
 */
class FeeAdjustmentPolicy extends TenantPolicy
{
    protected string $module = 'fees.adjustments';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fees.adjustments.create') || $user->hasPermission('fees.adjustments.approve');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameSchool($model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fees.adjustments.create');
    }

    public function approve(User $user, FeeAdjustment $adjustment): bool
    {
        return $this->decide($user, $adjustment) && ($this->selfApprovalAllowed() || (int) $adjustment->requested_by !== (int) $user->getKey());
    }

    public function reject(User $user, FeeAdjustment $adjustment): bool
    {
        return $this->decide($user, $adjustment);
    }

    /** The requester may withdraw their own request; an approver may cancel any pending one. */
    public function cancel(User $user, FeeAdjustment $adjustment): bool
    {
        return $this->sameSchool($adjustment) && $adjustment->isPending()
            && ((int) $adjustment->requested_by === (int) $user->getKey() || $user->hasPermission('fees.adjustments.approve'));
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    private function decide(User $user, FeeAdjustment $adjustment): bool
    {
        return $this->sameSchool($adjustment) && $adjustment->isPending() && $user->hasPermission('fees.adjustments.approve');
    }

    private function selfApprovalAllowed(): bool
    {
        return (bool) app(TenantContext::class)->school()?->setting('fees.adjustments_self_approval', false);
    }
}
