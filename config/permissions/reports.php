<?php

return [
    'label' => 'Reports & dashboard',
    'actions' => [
        'dashboard.view' => 'See the school dashboard',
        'view' => 'View operational reports',
        'export' => 'Export reports to CSV and Excel',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'teacher' => ['dashboard.view'],
        'accountant' => ['dashboard.view', 'view', 'export'],
        'staff' => ['dashboard.view'],
    ],
];
