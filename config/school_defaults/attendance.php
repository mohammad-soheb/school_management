<?php

/*
| Attendance rules (decision §14.4 is still open with the pilot schools, so every rule is a setting).
| Percentage = (present + late*(late_counts_as_present) + half_day*half_day_weight [+ leave if 'present'])
|              / (counted days), where counted days exclude holidays/weekly offs and, by default, approved leave.
*/

return [
    'leave_counts_as' => 'excluded', // excluded | absent | present
    'late_counts_as_present' => true,
    'half_day_weight' => 0.5,
    'weekly_off' => [0], // 0 = Sunday ... 6 = Saturday
];
