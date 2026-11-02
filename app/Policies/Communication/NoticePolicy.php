<?php

namespace App\Policies\Communication;

use App\Models\Communication\Notice;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Services\Communication\NoticeAudienceResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Notices (FR-506):
 *   notices.manage - sees every notice in any state; creates, edits, publishes, archives.
 *   notices.view   - sees a notice only while it is live (published, past publish_at, not expired) AND its audience
 *                    includes them (whole school, all staff, one of their roles, or a class they teach).
 *   delete         - never (archive instead).
 * Attachments inherit the notice's view rule (NoticeController::attachment).
 */
class NoticePolicy extends TenantPolicy
{
    protected string $module = 'notices';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('notices.view') || $user->hasPermission('notices.manage');
    }

    public function view(User $user, Model $model): bool
    {
        /** @var Notice $model */
        if (! $this->sameSchool($model)) {
            return false;
        }

        if ($user->hasPermission('notices.manage')) {
            return true;
        }

        return $user->hasPermission('notices.view')
            && $model->isVisibleNow()
            && app(NoticeAudienceResolver::class)->appliesToStaff($model, $user);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
