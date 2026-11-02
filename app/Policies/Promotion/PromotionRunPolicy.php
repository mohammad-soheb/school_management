<?php

namespace App\Policies\Promotion;

use App\Models\Promotion\PromotionRun;
use App\Models\User;
use App\Policies\TenantPolicy;

/**
 * promotion.view    see runs and their results
 * promotion.run     prepare drafts, edit outcomes, commit, cancel, lock the source year
 * promotion.correct correct or revert individual committed items
 * State rules (only drafts are editable, ...) are enforced by PromotionService.
 */
class PromotionRunPolicy extends TenantPolicy
{
    protected string $module = 'promotion';

    protected function managePermission(): string
    {
        return 'promotion.run';
    }

    public function commit(User $user, PromotionRun $run): bool
    {
        return $this->update($user, $run);
    }

    public function correct(User $user, PromotionRun $run): bool
    {
        return $this->sameSchool($run) && $user->hasPermission('promotion.correct');
    }
}
