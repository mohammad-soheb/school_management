<?php

return [
    [
        'group' => 'Platform',
        'order' => 0,
        'items' => [
            ['label' => 'Renewals', 'route' => 'platform.subscriptions.index', 'icon' => 'arrow-path', 'can' => 'platform.subscriptions.manage',
                'active' => ['platform.subscriptions.index', 'platform.subscriptions.schools.*'], 'order' => 20],
            ['label' => 'Plans', 'route' => 'platform.subscriptions.plans.index', 'icon' => 'rectangle-stack', 'can' => 'platform.subscriptions.manage',
                'active' => 'platform.subscriptions.plans.*', 'order' => 30],
        ],
    ],
];
