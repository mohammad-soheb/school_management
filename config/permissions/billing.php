<?php

/*
| School-side view of the school's own ERP subscription (the plan it pays the platform for). This is NOT the
| student fee ledger (see fees.php). Managing plans and renewals is a platform ability (platform.subscriptions.manage).
*/

return [
    'label' => 'ERP subscription',
    'actions' => [
        'view' => 'See this school\'s ERP subscription, expiry date and renewal history',
    ],
    'defaults' => [
        'school_admin' => ['*'],
    ],
];
