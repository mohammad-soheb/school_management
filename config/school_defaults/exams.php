<?php

/*
| Exam & report card rules. Every value can be overridden per school on Exams > Settings (stored in exam_settings)
| or by $school->settings['exams'][...]. Report card grading varies by pilot school (decision D06), so nothing here
| is hard-coded into the calculation: the ResultCalculator receives these values as options.
|
| Percentage = sum(obtained) / sum(max marks) * 100 over the APPLICABLE subjects only: subjects marked
| "not applicable" or "exempt" are left out of both sides; subjects marked "absent" count as 0 out of their
| maximum when absent_counts_in_total is true. Missing marks never get a guessed value: the result is incomplete
| and cannot be published.
*/

return [
    // Decimal places for percentages (0, 1 or 2). Rounding is half-up. Grades are looked up on the rounded value.
    'percentage_decimals' => 2,

    // Absent = 0 marks out of the subject maximum (true) or left out of the total like "not applicable" (false).
    'absent_counts_in_total' => true,

    // Show a rank on result sheets / report cards. Ties share a rank (1, 2, 2, 4).
    'rank_enabled' => true,
    'rank_scope' => 'class', // class | section

    // Pre-filled for new exam subjects (editable per exam and class).
    'default_max_marks' => 100,
    'default_pass_marks' => 33,

    // Report card layout (a small fixed catalogue: classic | modern).
    'report_card_template' => 'classic',
    'show_grade_points' => false,
    'show_rank_on_report_card' => true,
    'signature_labels' => ['Class Teacher', 'Exam Coordinator', 'Principal'],
    'pass_remark' => 'Passed',
    'fail_remark' => 'Needs improvement',
    'footer_note' => 'This is a computer-generated report card.',

    // Seeded as the school's default grading scheme the first time exams are used. min inclusive, max exclusive
    // (the top band includes 100).
    'default_grading_scheme' => [
        'name' => 'Standard (8-point)',
        'bands' => [
            ['min' => 91, 'max' => 100, 'grade' => 'A1', 'grade_point' => 10, 'remark' => 'Outstanding'],
            ['min' => 81, 'max' => 91, 'grade' => 'A2', 'grade_point' => 9, 'remark' => 'Excellent'],
            ['min' => 71, 'max' => 81, 'grade' => 'B1', 'grade_point' => 8, 'remark' => 'Very good'],
            ['min' => 61, 'max' => 71, 'grade' => 'B2', 'grade_point' => 7, 'remark' => 'Good'],
            ['min' => 51, 'max' => 61, 'grade' => 'C1', 'grade_point' => 6, 'remark' => 'Above average'],
            ['min' => 41, 'max' => 51, 'grade' => 'C2', 'grade_point' => 5, 'remark' => 'Average'],
            ['min' => 33, 'max' => 41, 'grade' => 'D', 'grade_point' => 4, 'remark' => 'Fair'],
            ['min' => 0, 'max' => 33, 'grade' => 'E', 'grade_point' => 0, 'remark' => 'Needs improvement'],
        ],
    ],
];
