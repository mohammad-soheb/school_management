<?php

// Year-end promotion / rollover. Hidden automatically from users without promotion.view.

return [
    [
        'group' => 'School',
        'order' => 500,
        'items' => [
            ['label' => 'Year-end promotion', 'route' => 'school.promotion.index', 'icon' => 'arrow-trending-up', 'can' => 'promotion.view', 'active' => 'school.promotion.*', 'order' => 50],
        ],
    ],
];
