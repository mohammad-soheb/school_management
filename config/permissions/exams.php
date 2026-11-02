<?php

/*
| Exams, marks, results and report cards (master plan FR-501 .. FR-504, FR-508 .. FR-511).
|
| Teacher scoping: the plain "view" / "marks.enter" permissions reach only the sections (and, for marks, the
| subjects) the teacher is assigned to in teacher_assignments; the "_all" siblings reach every class.
*/

return [
    'label' => 'Exams & results',
    'actions' => [
        'view' => 'View exams and results for assigned classes',
        'view_all' => 'View exams and results for every class',
        'manage' => 'Create exams, grading schemes and report card settings',
        'marks.enter' => 'Enter marks and remarks for assigned classes and subjects',
        'marks.enter_all' => 'Enter marks and remarks for any class and subject',
        'results.publish' => 'Publish, unpublish and lock results',
        'report_cards.print' => 'Print and download report cards',
    ],
    'defaults' => [
        'school_admin' => ['*'],
        'teacher' => ['view', 'marks.enter', 'report_cards.print'],
    ],
];
