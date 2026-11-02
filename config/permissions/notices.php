<?php

return [
    'label' => 'Notices',
    'actions' => [
        'view' => 'View notices and circulars',
        'manage' => 'Publish and edit notices and circulars',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'teacher' => ['view'],
        'accountant' => ['view'],
        'staff' => ['view'],
    ],
];
