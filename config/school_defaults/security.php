<?php

return [
    // Role slugs whose members must have two-factor authentication before using the school app.
    // Empty by default: recommended for 'school_admin' and 'accountant', mandatory only for the platform Super Admin.
    'require_two_factor_for' => [],
];
