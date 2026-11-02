<?php

// School setup (FR-201, FR-202). See App\Support\Navigation\Menu.

return [
    [
        'group' => 'School',
        'order' => 500,
        'items' => [
            ['label' => 'Setup overview', 'route' => 'school.setup.index', 'icon' => 'building-library', 'can' => 'setup.view', 'order' => 10],
            ['label' => 'Academic years', 'route' => 'school.setup.years.index', 'icon' => 'calendar', 'can' => 'setup.view', 'active' => 'school.setup.years.*', 'order' => 20],
            ['label' => 'Classes & sections', 'route' => 'school.setup.classes.index', 'icon' => 'rectangle-group', 'can' => 'setup.view', 'active' => ['school.setup.classes.*', 'school.setup.sections.*'], 'order' => 30],
            ['label' => 'Subjects', 'route' => 'school.setup.subjects.index', 'icon' => 'book-open', 'can' => 'setup.view', 'active' => 'school.setup.subjects.*', 'order' => 40],
            ['label' => 'Calendar', 'route' => 'school.setup.holidays.index', 'icon' => 'calendar-days', 'can' => 'setup.view', 'active' => 'school.setup.holidays.*', 'order' => 50],
            ['label' => 'School profile', 'route' => 'school.setup.profile.edit', 'icon' => 'identification', 'can' => 'setup.profile.update', 'active' => 'school.setup.profile.*', 'order' => 60],
            ['label' => 'Settings', 'route' => 'school.setup.settings.edit', 'icon' => 'cog-6-tooth', 'can' => 'setup.settings.manage', 'active' => 'school.setup.settings.*', 'order' => 70],
        ],
    ],
];
