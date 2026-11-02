<?php

namespace App\Policies\SchoolSetup;

use App\Models\SchoolSetup\AcademicYear;
use App\Models\User;

/**
 * Academic years. "setup.years.manage" creates, edits and switches the current year; locking and reopening is a
 * separate, more sensitive permission ("setup.years.lock", FR-2B07). Whether a locked year may be edited is a business
 * rule enforced (with a message) by AcademicYearService, not a permission.
 */
class AcademicYearPolicy extends SetupPolicy
{
    protected string $module = 'setup.years';

    public function setCurrent(User $user, AcademicYear $year): bool
    {
        return $this->update($user, $year);
    }

    public function lock(User $user, AcademicYear $year): bool
    {
        return $this->sameSchool($year) && $user->hasPermission('setup.years.lock');
    }

    public function reopen(User $user, AcademicYear $year): bool
    {
        return $this->lock($user, $year);
    }
}
