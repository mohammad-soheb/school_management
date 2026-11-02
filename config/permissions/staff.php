<?php

/*
| Staff profiles and teacher assignments. Inviting a staff member to sign in also needs access.members.invite
| (roles are an access decision). Staff documents and photos are opened with staff.view (StoredFilePolicy).
*/

return [
    'label' => 'Staff',
    'actions' => [
        'view' => 'View staff profiles and teacher assignments',
        'create' => 'Add staff',
        'update' => 'Edit staff profiles, status, photos, documents and linked logins',
        'assignments.manage' => 'Assign teachers to classes, sections and subjects',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'staff' => ['view'],
    ],
];
