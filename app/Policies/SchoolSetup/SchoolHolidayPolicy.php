<?php

namespace App\Policies\SchoolSetup;

/** Calendar entries (holidays, events, exam periods): "setup.holidays.manage". */
class SchoolHolidayPolicy extends SetupPolicy
{
    protected string $module = 'setup.holidays';
}
