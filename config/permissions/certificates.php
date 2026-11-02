<?php

/* Conditional module (master plan §18.1): transfer certificates and ID cards, enabled per school. */

return [
    'label' => 'Certificates & ID cards',
    'actions' => [
        'issue' => 'Issue transfer certificates and print ID cards',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'staff' => ['issue'],
    ],
];
