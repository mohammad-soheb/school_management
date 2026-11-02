<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Services\Attendance\AttendanceCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Correct staff attendance of a date: reason + records[<staff_attendance id>][status|remark]. Needs attendance.staff.mark. */
class CorrectStaffAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.staff.mark');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'records' => ['required', 'array', 'max:1000'],
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

    public function date(): CarbonImmutable
    {
        return AttendanceCalendar::day($this->validated('date'));
    }
}
