<?php

namespace App\Services\Academics;

use App\Enums\Academics\MarkStatus;

/**
 * Pure result arithmetic (FR-503, FR-509). No database, no settings lookups: everything comes in as arguments,
 * so a result can be reproduced from a publication's stored options and checked by hand.
 *
 * Rules
 *  - Marks are handled as integer hundredths (12.5 -> 1250): no floating-point drift.
 *  - Percentage = obtained / maximum x 100 over the APPLICABLE subjects that count in the total:
 *      present          obtained out of max
 *      absent           0 out of max when absent_counts_in_total (default), otherwise left out; the subject fails
 *      not_applicable,
 *      exempt           left out of obtained, maximum and pass/fail
 *      pending          nothing entered: the result is INCOMPLETE (no percentage, no grade, no pass/fail)
 *    A maximum of 0 gives no percentage: the denominator is never guessed.
 *  - Rounding is half-up to `percentage_decimals` (0..2). Grades are looked up on the rounded percentage.
 *  - Bands: min inclusive, max exclusive; the band whose max is 100 also includes 100.
 *  - A subject with components: obtained = sum of the components; every component must be entered; the subject
 *    passes when the total reaches the subject pass mark AND every component with its own pass mark reaches it.
 *  - Overall pass: complete, something counted, and every counted subject passed.
 *  - Rank: standard competition ranking on the percentage (1, 2, 2, 4); incomplete results are not ranked.
 */
final class ResultCalculator
{
    /**
     * @param  list<array{id: int, name: string, code?: ?string, max: string|int|float, pass: string|int|float, counts_in_total?: bool, components?: list<array{id: int, name: string, max: string|int|float, pass?: string|int|float|null}>}>  $subjects
     * @param  array<int, array{status: MarkStatus|string, obtained?: string|int|float|null, components?: array<int|string, string|int|float|null>}>  $marks  keyed by subject id
     * @param  list<array{min: string|int|float, max: string|int|float, grade: string, grade_point?: string|int|float|null, remark?: ?string}>  $bands
     * @param  array{percentage_decimals?: int, absent_counts_in_total?: bool}  $options
     * @return array{subjects: list<array<string, mixed>>, total_obtained: string, total_max: string, percentage: ?string, percentage_hundredths: ?int, grade: ?string, grade_point: ?string, grade_remark: ?string, passed: ?bool, complete: bool, pending_subjects: list<string>, failed_subjects: list<string>}
     */
    public function calculate(array $subjects, array $marks, array $bands, array $options = []): array
    {
        $decimals = max(0, min(2, (int) ($options['percentage_decimals'] ?? 2)));
        $absentCounts = (bool) ($options['absent_counts_in_total'] ?? true);

        $lines = [];
        $obtainedTotal = 0;
        $maxTotal = 0;
        $complete = true;
        $countedSomething = false;
        $allCountedPassed = true;
        $pending = [];
        $failed = [];

        foreach ($subjects as $subject) {
            $line = $this->subjectLine($subject, $marks[$subject['id']] ?? null, $bands, $decimals);
            $counts = (bool) ($subject['counts_in_total'] ?? true);
            $status = MarkStatus::from($line['status']);

            if ($status === MarkStatus::Pending) {
                $complete = false;
                $pending[] = $subject['name'];
            }

            if ($line['passed'] === false) {
                $failed[] = $subject['name'];
            }

            $included = false;
            if ($counts) {
                if ($status === MarkStatus::Present) {
                    $obtainedTotal += $line['obtained_hundredths'];
                    $maxTotal += $line['max_hundredths'];
                    $included = true;
                } elseif ($status === MarkStatus::Absent && $absentCounts) {
                    $maxTotal += $line['max_hundredths'];
                    $included = true;
                }

                if ($status === MarkStatus::Present || $status === MarkStatus::Absent) {
                    $countedSomething = true;
                    $allCountedPassed = $allCountedPassed && $line['passed'] === true;
                }
            }

            $line['included_in_total'] = $included;
            $line['counts_in_total'] = $counts;
            unset($line['obtained_hundredths'], $line['max_hundredths']);
            $lines[] = $line;
        }

        $percentage = $complete && $maxTotal > 0 ? $this->percentHundredths($obtainedTotal, $maxTotal, $decimals) : null;
        $band = $percentage !== null ? $this->band($percentage, $bands) : null;

        return [
            'subjects' => $lines,
            'total_obtained' => self::format($obtainedTotal),
            'total_max' => self::format($maxTotal),
            'percentage' => $percentage !== null ? self::formatPercent($percentage, $decimals) : null,
            'percentage_hundredths' => $percentage,
            'grade' => $band['grade'] ?? null,
            'grade_point' => isset($band['grade_point']) ? self::format(self::hundredths($band['grade_point'])) : null,
            'grade_remark' => $band['remark'] ?? null,
            'passed' => $complete && $countedSomething ? $allCountedPassed : null,
            'complete' => $complete,
            'pending_subjects' => $pending,
            'failed_subjects' => $failed,
        ];
    }

    /**
     * Competition ranking: equal scores share a rank and the next rank skips (1, 2, 2, 4). Null scores get no rank.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, int|null>  $scores  higher is better (e.g. percentage in hundredths)
     * @return array<TKey, int|null>
     */
    public function rank(array $scores): array
    {
        $ranked = array_filter($scores, fn ($score) => $score !== null);
        arsort($ranked);

        $ranks = array_map(fn () => null, $scores);
        $position = 0;
        $previous = null;
        $currentRank = 0;

        foreach ($ranked as $key => $score) {
            $position++;
            if ($score !== $previous) {
                $currentRank = $position;
                $previous = $score;
            }
            $ranks[$key] = $currentRank;
        }

        return $ranks;
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>|null  $mark
     * @param  list<array<string, mixed>>  $bands
     * @return array<string, mixed>
     */
    private function subjectLine(array $subject, ?array $mark, array $bands, int $decimals): array
    {
        $status = $mark['status'] ?? MarkStatus::Pending;
        $status = $status instanceof MarkStatus ? $status : MarkStatus::from((string) $status);

        $components = $subject['components'] ?? [];
        $max = $components === []
            ? self::hundredths($subject['max'])
            : array_sum(array_map(fn ($c) => self::hundredths($c['max']), $components));
        $pass = self::hundredths($subject['pass']);

        $componentLines = [];
        $obtained = null;
        $passed = null;

        if ($status === MarkStatus::Present) {
            if ($components === []) {
                $raw = $mark['obtained'] ?? null;
                if ($raw === null || $raw === '') {
                    $status = MarkStatus::Pending;
                } else {
                    $obtained = self::hundredths($raw);
                }
            } else {
                $values = $mark['components'] ?? [];
                $sum = 0;
                $componentsPassed = true;
                foreach ($components as $component) {
                    $raw = $values[$component['id']] ?? $values[(string) $component['id']] ?? null;
                    if ($raw === null || $raw === '') {
                        $status = MarkStatus::Pending;
                        $componentLines[] = ['id' => $component['id'], 'name' => $component['name'], 'max' => self::format(self::hundredths($component['max'])), 'obtained' => null, 'passed' => null];

                        continue;
                    }
                    $value = self::hundredths($raw);
                    $componentPass = isset($component['pass']) && $component['pass'] !== '' ? self::hundredths($component['pass']) : null;
                    $ok = $componentPass === null || $value >= $componentPass;
                    $componentsPassed = $componentsPassed && $ok;
                    $sum += $value;
                    $componentLines[] = ['id' => $component['id'], 'name' => $component['name'], 'max' => self::format(self::hundredths($component['max'])), 'obtained' => self::format($value), 'passed' => $componentPass === null ? null : $ok];
                }
                if ($status === MarkStatus::Present) {
                    $obtained = $sum;
                    $passed = $componentsPassed;
                }
            }

            if ($status === MarkStatus::Present) {
                $passed = ($passed ?? true) && $obtained >= $pass;
            }
        } elseif ($status === MarkStatus::Absent) {
            $passed = false;
            foreach ($components as $component) {
                $componentLines[] = ['id' => $component['id'], 'name' => $component['name'], 'max' => self::format(self::hundredths($component['max'])), 'obtained' => null, 'passed' => null];
            }
        }

        if ($status === MarkStatus::Pending) {
            $obtained = null;
            $passed = null;
        }

        $percentage = $status === MarkStatus::Present && $max > 0 ? $this->percentHundredths($obtained, $max, $decimals) : null;
        $band = $percentage !== null ? $this->band($percentage, $bands) : null;

        return [
            'id' => $subject['id'],
            'name' => $subject['name'],
            'code' => $subject['code'] ?? null,
            'status' => $status->value,
            'max' => self::format($max),
            'pass' => self::format($pass),
            'obtained' => $obtained !== null ? self::format($obtained) : null,
            'percentage' => $percentage !== null ? self::formatPercent($percentage, $decimals) : null,
            'grade' => $band['grade'] ?? null,
            'grade_point' => isset($band['grade_point']) ? self::format(self::hundredths($band['grade_point'])) : null,
            'passed' => $passed,
            'components' => $componentLines,
            'obtained_hundredths' => $obtained ?? 0,
            'max_hundredths' => $max,
        ];
    }

    /** obtained / max x 100, rounded half-up to $decimals, returned in hundredths of a percent (66.67% -> 6667). */
    public function percentHundredths(int $obtained, int $max, int $decimals): int
    {
        $scale = 10 ** $decimals;
        $numerator = $obtained * 100 * $scale;
        $rounded = intdiv(2 * $numerator + $max, 2 * $max);

        return $rounded * (10 ** (2 - $decimals));
    }

    /**
     * @param  list<array<string, mixed>>  $bands
     * @return array<string, mixed>|null
     */
    public function band(int $percentHundredths, array $bands): ?array
    {
        foreach ($bands as $band) {
            $min = self::hundredths($band['min']);
            $max = self::hundredths($band['max']);
            if ($percentHundredths >= $min && ($percentHundredths < $max || ($max === 10000 && $percentHundredths === 10000))) {
                return $band;
            }
        }

        return null;
    }

    /** "12.5" -> 1250. Inputs carry at most two decimals (validated before they get here). */
    public static function hundredths(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round(((float) $value) * 100);
    }

    /** 1250 -> "12.5", 4500 -> "45", 4525 -> "45.25". */
    public static function format(int $hundredths): string
    {
        $text = number_format($hundredths / 100, 2, '.', '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    /** 6667 at 2 decimals -> "66.67"; 6700 at 0 decimals -> "67". */
    public static function formatPercent(int $hundredths, int $decimals): string
    {
        return number_format($hundredths / 100, $decimals, '.', '');
    }
}
