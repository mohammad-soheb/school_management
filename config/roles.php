<?php

/*
|--------------------------------------------------------------------------
| Default roles
|--------------------------------------------------------------------------
| Seeded into every new school (RoleService::seedDefaultRoles). Which permissions each role starts with is
| declared per module in config/permissions/*.php under 'defaults'. Schools can edit every role except
| "School Admin", which always holds every school permission so a school can never lock itself out.
*/

return [
    'school_admin' => [
        'name' => 'School Admin',
        'description' => 'Runs the school account. Full access to every school module.',
    ],
    'teacher' => [
        'name' => 'Teacher',
        'description' => 'Marks attendance, enters marks and sets homework for assigned classes.',
    ],
    'accountant' => [
        'name' => 'Accountant',
        'description' => 'Collects fees, issues receipts and runs financial reports.',
    ],
    'staff' => [
        'name' => 'Staff',
        'description' => 'Office staff with minimal read access. Extend as needed.',
    ],
    'parent' => [
        'name' => 'Parent / Guardian',
        'description' => 'Parent portal access to linked children only. Holds no school-staff permissions.',
    ],
];
