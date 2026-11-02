<?php

/*
| Platform (Super Admin) abilities. These are NOT school permissions: they are not stored on roles, never appear
| in the role editor, and are granted purely by users.is_platform_admin (see AppServiceProvider gate definitions).
*/

return [
    'scope' => 'platform',
    'label' => 'Platform',
    'actions' => [
        'dashboard' => 'View the platform dashboard',
        'schools.manage' => 'Create, edit, activate and suspend schools',
        'subscriptions.manage' => 'Manage plans, record payments and renew subscriptions',
        'audit.view' => 'View platform audit events',
    ],
    'defaults' => [],
];
