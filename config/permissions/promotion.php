<?php

/*
| Year-end promotion / rollover (docs/promotion/ROLLOVER_RULES.md). Only School Admin holds these by default:
| a rollover touches every student's enrollment, so schools grant it to other roles deliberately.
*/

return [
    'label' => 'Year-end promotion',
    'actions' => [
        'view' => 'View promotion runs and their results',
        'run' => 'Prepare, commit and cancel year-end promotion runs; lock the source year',
        'correct' => 'Correct or revert an individual student after a run is committed',
        'reopen' => 'Reopen a locked academic year for corrections',
    ],
    'defaults' => [
        'school_admin' => ['*'],
    ],
];
