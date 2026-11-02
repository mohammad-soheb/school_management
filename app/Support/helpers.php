<?php

use Carbon\CarbonImmutable;

if (! function_exists('school_today')) {
    /** Today's date in the school's display timezone (business dates such as attendance and fees use this). */
    function school_today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.display_timezone'))->startOfDay();
    }
}

if (! function_exists('local_now')) {
    /** The current moment in the display timezone, for showing times to people. Storage stays UTC. */
    function local_now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.display_timezone'));
    }
}
