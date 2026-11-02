<?php

/*
|--------------------------------------------------------------------------
| Platform subscriptions (master plan Phase 8, FR-801 .. FR-809)
|--------------------------------------------------------------------------
| The ERP subscription a school pays the platform owner. It is completely separate from the school's own
| student-fee ledger (FR-809). Payments are verified outside the ERP and recorded manually by the Super Admin.
|
| The grace length and the expiry behaviour are D12 decisions: change them here once the subscription terms are
| signed. The plans below are the master-plan §20.1 PRICING HYPOTHESES, not final offers (decision D10).
*/

return [
    // A subscription is "Expiring soon" from this many days before its expiry date (inclusive).
    'expiring_soon_days' => (int) env('SUBSCRIPTION_EXPIRING_SOON_DAYS', 15),

    // Days after the expiry date during which the school keeps full access (per-school value can override it).
    'default_grace_days' => (int) env('SUBSCRIPTION_DEFAULT_GRACE_DAYS', 7),

    // Upper bound accepted by the forms.
    'max_grace_days' => 90,
    'max_months_per_payment' => 36,

    // Where a school is told to send its renewal (shown on the read-only / suspended notices).
    'renewal_contact' => env('SUBSCRIPTION_RENEWAL_CONTACT', 'your ERP provider'),

    // Hypothesis plans offered by "Load hypothesis plans" on the plans page and by the demo seeder.
    // monthly_price_paise is integer paise (₹999 = 99900).
    'hypothesis_plans' => [
        ['code' => 'starter', 'name' => 'Starter', 'monthly_price_paise' => 99900, 'billing_period_months' => 1, 'student_limit' => 250,
            'description' => 'Core academic, fees and attendance workflows with defined remote support.'],
        ['code' => 'standard', 'name' => 'Standard', 'monthly_price_paise' => 149900, 'billing_period_months' => 1, 'student_limit' => 500,
            'description' => 'Starter plus a higher student limit and an agreed service allotment.'],
        ['code' => 'growth', 'name' => 'Growth', 'monthly_price_paise' => 249900, 'billing_period_months' => 1, 'student_limit' => 1000,
            'description' => 'More capacity and a negotiated onboarding and support allotment.'],
    ],
];
