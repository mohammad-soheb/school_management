<?php

// Sidebar fragments: see App\Support\Navigation\Menu. Items vanish automatically when the route is missing
// or the signed-in user lacks the 'can' permission.

return [
    [
        'group' => 'Overview',
        'order' => 0,
        'items' => [
            ['label' => 'Dashboard', 'route' => 'school.dashboard', 'icon' => 'squares-2x2', 'order' => 0],
        ],
    ],
    [
        'group' => 'Administration',
        'order' => 900,
        'items' => [
            ['label' => 'Users', 'route' => 'school.access.members.index', 'icon' => 'users', 'can' => 'access.members.view', 'active' => 'school.access.members.*', 'order' => 10],
            ['label' => 'Roles & permissions', 'route' => 'school.access.roles.index', 'icon' => 'shield-check', 'can' => 'access.roles.manage', 'active' => 'school.access.roles.*', 'order' => 20],
            ['label' => 'Audit log', 'route' => 'school.access.audit.index', 'icon' => 'clipboard-document-list', 'can' => 'access.audit.view', 'order' => 30],
        ],
    ],
];
