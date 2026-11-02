<?php

return [
    'label' => 'School setup',
    'actions' => [
        'view' => 'View classes, sections, subjects and academic years',
        'profile.update' => 'Edit the school profile and logo',
        'years.manage' => 'Create academic years and set the current year',
        'years.lock' => 'Lock and reopen academic years',
        'classes.manage' => 'Manage classes and sections',
        'subjects.manage' => 'Manage subjects',
        'holidays.manage' => 'Manage the school calendar and holidays',
        'settings.manage' => 'Change school settings (attendance rules, receipts, security)',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'teacher' => ['view'],
        'accountant' => ['view'],
        'staff' => ['view'],
    ],
];
