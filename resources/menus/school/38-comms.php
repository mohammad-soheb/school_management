<?php

// Homework & notices (FR-505 - FR-508).

return [
    'group' => 'Academics',
    'order' => 200,
    'items' => [
        ['label' => 'Homework', 'route' => 'school.homework.index', 'icon' => 'book-open', 'can' => 'homework.access', 'active' => 'school.homework.*', 'order' => 40],
        ['label' => 'Notice board', 'route' => 'school.notices.index', 'icon' => 'megaphone', 'can' => 'notices.access', 'active' => 'school.notices.*', 'order' => 50],
    ],
];
