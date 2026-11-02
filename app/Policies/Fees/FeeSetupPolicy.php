<?php

namespace App\Policies\Fees;

use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Database\Eloquent\Model;

/** Fee heads, fee plans and plan lines: one permission (fees.setup.manage) for reading and writing. Never deleted. */
class FeeSetupPolicy extends TenantPolicy
{
    protected string $module = 'fees.setup';

    protected function viewPermission(): string
    {
        return 'fees.setup.manage';
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
