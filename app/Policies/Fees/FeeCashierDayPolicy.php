<?php

namespace App\Policies\Fees;

use App\Models\Fees\FeeCashierDay;
use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Model;

/** Daily close: fees.close.manage closes; fees.close.review signs off, and never the person who closed (D14). */
class FeeCashierDayPolicy extends TenantPolicy
{
    protected string $module = 'fees.close';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fees.close.manage') || $user->hasPermission('fees.close.review');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameSchool($model) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fees.close.manage');
    }

    public function review(User $user, FeeCashierDay $day): bool
    {
        return $this->sameSchool($day) && $day->isClosed() && $day->reviewed_at === null
            && (int) $day->closed_by !== (int) $user->getKey()
            && $user->hasPermission('fees.close.review');
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
