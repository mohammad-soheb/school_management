<?php

/*
| Fee rules (docs/fees/FEE_LEDGER_DESIGN.md). Defaults follow the "Proposed starting default" column of the decision
| register (master plan §21.4) and still need accountant sign-off before real money is handled (D03, D04, D05, D14).
| Read with $school->setting('fees.<key>'); a school may override any key in its settings.
*/

return [
    'receipt_prefix' => 'RCP',
    // How the receipt sequence restarts: 'academic_year' (RCP/2026-27/000123) or 'never' (RCP/000123). [SIGN-OFF 9]
    'receipt_scope' => 'academic_year',
    'receipt_padding' => 6,
    // How an unallocated payment settles charges: 'oldest_due_first' or 'selected_only'. [SIGN-OFF 4]
    'allocation_order' => 'oldest_due_first',
    'payment_modes' => ['cash', 'upi', 'bank_transfer', 'cheque', 'other'],
    // Modes for which a transaction / cheque reference is mandatory. [SIGN-OFF 12]
    'reference_required_for' => ['upi', 'bank_transfer', 'cheque'],
    // Largest single payment accepted (paise). ₹10,00,000.
    'max_payment_paise' => 100_000_000,
    // How many days back a payment may be dated (0 = today only). Future dates are always refused. [SIGN-OFF 13]
    'max_backdate_days' => 7,
    // Accept more than the outstanding amount and hold the rest as student credit. [SIGN-OFF 6]
    'allow_advance' => true,
    // Apply held credit automatically when new dues are posted. [SIGN-OFF 7]
    'auto_apply_credit' => true,
    // May the user who requested an adjustment also approve it? [SIGN-OFF 14]
    'adjustments_self_approval' => false,
    // Largest single fine (paise); null = no cap. [SIGN-OFF 16]
    'fine_max_paise' => null,
];
