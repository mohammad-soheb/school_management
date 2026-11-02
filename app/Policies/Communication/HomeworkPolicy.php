<?php

namespace App\Policies\Communication;

use App\Models\Communication\Homework;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Services\Communication\HomeworkAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * Homework (FR-505):
 *   view     - any holder of homework.view / manage / manage_all sees ACTIVE homework of the school;
 *              ARCHIVED homework only by someone who could edit it (its class/section/subject is theirs, or manage_all).
 *   create   - homework.manage (own classes, re-checked per target in HomeworkService) or homework.manage_all.
 *   update / archive / restore - the target must be within the user's teaching assignments, or manage_all.
 *   delete   - never (rule 5: archive instead).
 * Attachments inherit the homework's view rule (HomeworkController::attachment).
 */
class HomeworkPolicy extends TenantPolicy
{
    protected string $module = 'homework';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('homework.view') || $this->access()->canAuthor($user);
    }

    public function view(User $user, Model $model): bool
    {
        /** @var Homework $model */
        if (! $this->sameSchool($model) || ! $this->viewAny($user)) {
            return false;
        }

        return ! $model->isArchived()
            || (int) $model->created_by === (int) $user->getKey()
            || $this->access()->allowsRecord($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->access()->canAuthor($user);
    }

    public function update(User $user, Model $model): bool
    {
        /** @var Homework $model */
        return $this->sameSchool($model) && $this->access()->allowsRecord($user, $model);
    }

    public function archive(User $user, Homework $homework): bool
    {
        return $this->update($user, $homework);
    }

    public function restore(User $user, Homework $homework): bool
    {
        return $this->update($user, $homework);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    private function access(): HomeworkAccess
    {
        return app(HomeworkAccess::class);
    }
}
