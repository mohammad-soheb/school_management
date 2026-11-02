<?php

/* Conditional module (master plan FR-708): only reachable when the school enables the "salary" feature. */

return [
    'label' => 'Salary',
    'actions' => [
        'view' => 'View salary structures, slips and payments',
        'manage' => 'Configure salaries and record salary payments',
    ],
    'defaults' => [
        'school_admin' => ['*'],
    ],
];
