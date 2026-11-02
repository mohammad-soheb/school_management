<?php

namespace App\Services\Fees;

use App\Models\Students\Student;
use Illuminate\Validation\ValidationException;

/**
 * Row locks that serialise all money movement for a student (design §7). Lock order everywhere:
 * cashier day row -> student row(s) by ascending id -> payment row -> receipt sequence row.
 * Call inside DB::transaction().
 */
final class FeeLocks
{
    public function student(int $studentId): Student
    {
        $student = Student::query()->whereKey($studentId)->lockForUpdate()->first();

        if ($student === null) {
            throw ValidationException::withMessages(['student_id' => 'Choose a student of this school.']);
        }

        return $student;
    }

    /**
     * @param  list<int>  $studentIds
     */
    public function students(array $studentIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $studentIds)));
        sort($ids);

        if ($ids !== []) {
            Student::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }
}
