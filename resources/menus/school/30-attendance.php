<?php

// Attendance menu (see App\Support\Navigation\Menu). Items hide themselves without the permission.

return [
    'group' => 'Academics',
    'order' => 200,
    'items' => [
        ['label' => 'Attendance', 'route' => 'school.attendance.index', 'icon' => 'clipboard-document-check', 'can' => 'attendance.students.access', 'active' => ['school.attendance.index', 'school.attendance.mark', 'school.attendance.sheets.*'], 'order' => 10],
        ['label' => 'Staff attendance', 'route' => 'school.attendance.staff.index', 'icon' => 'identification', 'can' => 'attendance.staff.access', 'active' => 'school.attendance.staff.*', 'order' => 12],
        ['label' => 'Attendance reports', 'route' => 'school.attendance.reports.daily', 'icon' => 'chart-bar', 'can' => 'attendance.reports.view', 'active' => 'school.attendance.reports.*', 'order' => 14],
    ],
];
