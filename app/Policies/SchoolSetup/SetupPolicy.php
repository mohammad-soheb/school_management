<?php

namespace App\Policies\SchoolSetup;

use App\Policies\TenantPolicy;

/**
 * Base for school-setup records. Everyone with "setup.view" may read the structure (teachers and accountants need
 * class/section/subject lists); writes need the area's own "...manage" permission.
 */
abstract class SetupPolicy extends TenantPolicy
{
    protected function viewPermission(): string
    {
        return 'setup.view';
    }
}
