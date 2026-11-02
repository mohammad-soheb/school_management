<?php

return [
    'label' => 'Users & access',
    'actions' => [
        'members.view' => 'View school users',
        'members.invite' => 'Invite users',
        'members.update' => 'Change user roles and suspend users',
        'roles.manage' => 'Edit role permissions',
        'audit.view' => 'View the audit log',
    ],
    'defaults' => [
        'school_admin' => ['*'],
    ],
];
