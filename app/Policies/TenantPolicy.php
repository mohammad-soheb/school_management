<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for model policies. A policy answers two questions:
 *   1. does the user hold the module permission ("<module>.<action>") in the active school?
 *   2. does this record belong to the active school (a second layer behind the query scope)?
 * Data-level rules (a teacher's assigned sections, a parent's linked children) are added in subclasses.
 *
 * Subclasses set $module and may override any ability. Policies are attached to models with
 * #[UsePolicy(StudentPolicy::class)], so nothing needs registering.
 */
abstract class TenantPolicy
{
    /** Permission prefix, e.g. 'setup.classes' -> setup.classes.manage. */
    protected string $module;

    /** Permission used for read abilities (viewAny/view), default "<module>.view". */
    protected function viewPermission(): string
    {
        return "{$this->module}.view";
    }

    /** Permission used for write abilities (create/update/delete), default "<module>.manage". */
    protected function managePermission(): string
    {
        return "{$this->module}.manage";
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->viewPermission());
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameSchool($model) && $user->hasPermission($this->viewPermission());
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->managePermission());
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameSchool($model) && $user->hasPermission($this->managePermission());
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    protected function sameSchool(Model $model): bool
    {
        $schoolId = app(TenantContext::class)->id();

        return $schoolId !== null && (int) $model->getAttribute('school_id') === $schoolId;
    }
}
