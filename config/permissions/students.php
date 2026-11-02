<?php

/*
| "view" is restricted by policy to the sections a teacher is assigned to (teacher_assignments);
| "view_all" lifts that restriction for office roles. See App\Support\Identity\AssignmentScope and
| App\Policies\Students\StudentPolicy. Documents and guardian contact lists are separate, narrower permissions
| (child data minimisation, master plan §19.3).
*/

return [
    'label' => 'Students',
    'actions' => [
        'view' => 'View students in assigned classes',
        'view_all' => 'View all students in the school',
        'create' => 'Admit students',
        'update' => 'Edit student records and photos',
        'status' => 'Change student status (withdraw, transfer, reactivate)',
        'guardians.view' => 'View the guardian directory and contact details',
        'guardians.manage' => 'Manage guardians, verify parent links and invite parents to the portal',
        'documents.view' => 'View student documents',
        'documents.upload' => 'Upload and remove student documents',
        'export' => 'Export student lists',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'teacher' => ['view'],
        'accountant' => ['view', 'view_all', 'guardians.view'],
        'staff' => ['view', 'view_all', 'guardians.view', 'documents.view'],
    ],
];
