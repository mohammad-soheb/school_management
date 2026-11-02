<?php

namespace App\Policies\SchoolSetup;

/** Sections are managed with their class: "setup.classes.manage". */
class SectionPolicy extends SetupPolicy
{
    protected string $module = 'setup.classes';
}
