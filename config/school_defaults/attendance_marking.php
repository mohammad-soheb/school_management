<?php

/*
| Attendance marking window (read with $school->setting('attendance_marking.backdate_days')).
|
| backdate_days               how many days back a marker may still take a missed register (0 = today only).
|                             Older sheets can only be changed through a correction.
| mark_all_ignores_backdate   users holding attendance.students.mark_all (School Admin) may mark any past working
|                             day of the current academic year.
|
| Future dates, holidays, weekly offs and dates outside the current academic year are always refused.
*/

return [
    'backdate_days' => 7,
    'mark_all_ignores_backdate' => true,
];
