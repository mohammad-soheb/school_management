<?php

/*
| Attendance permissions (master plan FR-301..FR-307).
|
| Teacher scoping: the plain students.view / students.mark permissions reach only the sections the user is assigned
| to for the current academic year (App\Support\Identity\AssignmentScope); the "_all" siblings reach every section.
| students.correct changes a submitted sheet (reason required, audited) for the sections the user may view.
| reports.view shows the reports for the sections the user may view; the staff report also needs staff.view or staff.mark.
|
| Derived abilities (not stored on roles, defined in AttendanceServiceProvider):
|   attendance.students.access  any of students.view / view_all / mark / mark_all
|   attendance.students.mark_any students.mark or students.mark_all
|   attendance.staff.access      staff.view or staff.mark
*/

return [
    'label' => 'Attendance',
    'actions' => [
        'students.view' => 'View student attendance for assigned classes',
        'students.view_all' => 'View student attendance for every class',
        'students.mark' => 'Mark student attendance for assigned classes',
        'students.mark_all' => 'Mark student attendance for every class',
        'students.correct' => 'Correct submitted student attendance',
        'staff.view' => 'View staff attendance',
        'staff.mark' => 'Mark and correct staff attendance',
        'reports.view' => 'View attendance reports',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'teacher' => ['students.view', 'students.mark', 'reports.view'],
        'staff' => ['students.view_all'],
    ],
];
