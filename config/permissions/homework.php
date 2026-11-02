<?php

return [
    'label' => 'Homework',
    'actions' => [
        'view' => 'View homework',
        'manage' => 'Set, edit and remove homework for assigned classes',
        'manage_all' => 'Set, edit and remove homework for any class',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'teacher' => ['view', 'manage'],
        'staff' => ['view'],
    ],
];
