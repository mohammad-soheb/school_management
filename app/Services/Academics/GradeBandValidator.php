<?php

namespace App\Services\Academics;

/**
 * Checks that grade bands cover 0..100 exactly once: min inclusive, max exclusive, the top band ends at 100,
 * no gaps, no overlaps, unique grade letters. Pure; returns human messages (empty list = valid).
 */
final class GradeBandValidator
{
    /**
     * @param  list<array{min: mixed, max: mixed, grade: mixed}>  $bands
     * @return list<string>
     */
    public function errors(array $bands): array
    {
        if ($bands === []) {
            return ['Add at least one grade band.'];
        }

        $errors = [];
        $grades = [];
        $rows = [];

        foreach (array_values($bands) as $i => $band) {
            $n = $i + 1;
            $grade = trim((string) ($band['grade'] ?? ''));
            if (! is_numeric($band['min'] ?? null) || ! is_numeric($band['max'] ?? null)) {
                $errors[] = "Band {$n}: enter both a minimum and a maximum percentage.";

                continue;
            }

            $min = ResultCalculator::hundredths($band['min']);
            $max = ResultCalculator::hundredths($band['max']);

            if ($grade === '') {
                $errors[] = "Band {$n}: enter a grade.";
            } elseif (in_array(mb_strtoupper($grade), $grades, true)) {
                $errors[] = "Grade \"{$grade}\" is used by more than one band.";
            } else {
                $grades[] = mb_strtoupper($grade);
            }

            if ($min < 0 || $max > 10000) {
                $errors[] = "Band {$n} ({$grade}): percentages must be between 0 and 100.";
            }
            if ($min >= $max) {
                $errors[] = "Band {$n} ({$grade}): the minimum must be lower than the maximum.";
            }

            $rows[] = ['min' => $min, 'max' => $max, 'grade' => $grade];
        }

        if ($errors !== []) {
            return $errors;
        }

        usort($rows, fn ($a, $b) => $a['min'] <=> $b['min']);

        if ($rows[0]['min'] !== 0) {
            $errors[] = 'The lowest band must start at 0%.';
        }

        for ($i = 1, $count = count($rows); $i < $count; $i++) {
            $previous = $rows[$i - 1];
            $current = $rows[$i];
            if ($current['min'] > $previous['max']) {
                $errors[] = sprintf('Gap between %s%% and %s%% (after grade %s).', ResultCalculator::format($previous['max']), ResultCalculator::format($current['min']), $previous['grade']);
            } elseif ($current['min'] < $previous['max']) {
                $errors[] = sprintf('Grades %s and %s overlap between %s%% and %s%%.', $previous['grade'], $current['grade'], ResultCalculator::format($current['min']), ResultCalculator::format($previous['max']));
            }
        }

        if (end($rows)['max'] !== 10000) {
            $errors[] = 'The highest band must end at 100%.';
        }

        return $errors;
    }
}
