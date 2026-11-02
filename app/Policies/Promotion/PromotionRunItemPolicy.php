<?php

namespace App\Policies\Promotion;

use App\Models\Promotion\PromotionRunItem;
use App\Models\User;
use App\Policies\TenantPolicy;

/** Committed items are corrected one at a time by holders of promotion.correct. */
class PromotionRunItemPolicy extends TenantPolicy
{
    protected string $module = 'promotion';

    protected function managePermission(): string
    {
        return 'promotion.run';
    }

    public function correct(User $user, PromotionRunItem $item): bool
    {
        return $this->sameSchool($item) && $user->hasPermission('promotion.correct');
    }
}
