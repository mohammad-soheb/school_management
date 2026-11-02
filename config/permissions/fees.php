<?php

// Fees module permissions (docs/fees/FEE_LEDGER_DESIGN.md). School Admin holds all of them automatically.
// Adjustment approval is deliberately NOT given to the accountant by default: maker-checker (§8, sign-off rule 14).

return [
    'label' => 'Fees',
    'actions' => [
        'setup.manage' => 'Manage fee heads and fee plans',
        'charges.view' => 'View student charges, dues and ledgers',
        'charges.post' => 'Post fee charges and opening balances to students',
        'payments.view' => 'View payments and receipts',
        'payments.post' => 'Record fee payments and apply advance credit',
        'payments.reverse' => 'Reverse mistaken payments',
        'adjustments.create' => 'Request discounts, scholarships, waivers and fines',
        'adjustments.approve' => 'Approve or reject discounts, scholarships, waivers and fines',
        'close.manage' => 'Close the daily collection and reconcile cash',
        'close.review' => 'Review (sign off) a closed collection day',
        'reports.view' => 'View collection, dues and defaulter reports and exports',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'accountant' => [
            'setup.manage', 'charges.view', 'charges.post', 'payments.view', 'payments.post',
            'payments.reverse', 'adjustments.create', 'close.manage', 'reports.view',
        ],
    ],
];
