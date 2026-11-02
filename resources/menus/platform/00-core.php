<?php

return [
    [
        'group' => 'Platform',
        'order' => 0,
        'items' => [
            ['label' => 'Dashboard', 'route' => 'platform.dashboard', 'icon' => 'squares-2x2', 'order' => 0],
            ['label' => 'Schools', 'route' => 'platform.schools.index', 'icon' => 'building-library', 'can' => 'platform.schools.manage', 'active' => 'platform.schools.*', 'order' => 10],
        ],
    ],
];
