<?php

// The school's own ERP subscription (read-only; owned by the platform subscriptions module).

return [
    [
        'group' => 'Administration',
        'order' => 900,
        'items' => [
            ['label' => 'ERP subscription', 'route' => 'school.subscription.show', 'icon' => 'credit-card', 'can' => 'billing.view', 'order' => 90],
        ],
    ],
];
