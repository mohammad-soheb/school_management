<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Attendance\AttendanceSheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correct a submitted class register (FR-304, FR-307): attendance.students.correct for a section the user may view,
 * a reason, and records[<student_attendance id>][status|remark]. The service ignores unchanged rows.
 */
class CorrectAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sheet = $this->route('sheet');

        return $sheet instanceof AttendanceSheet && $this->user()->can('correct', $sheet);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'records' => ['required', 'array', 'max:500'],
            'records.*' => ['required', 'array:status,remark'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Give a reason for the correction.',
            'reason.min' => 'Give a reason for the correction (at least 5 characters).',
        ];
    }
}
