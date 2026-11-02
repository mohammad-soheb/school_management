<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Attendance\StaffAttendance;
use App\Services\Attendance\AttendanceCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mark staff attendance for a date: staff[<staff_profile id>][status|remark]. Needs attendance.staff.mark. */
class SubmitStaffAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StaffAttendance::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'staff' => ['required', 'array', 'max:1000'],
            'staff.*' => ['required', 'array:status,remark'],
            'staff.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'staff.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['staff.required' => 'Everyone on this list is already marked.'];
    }

    public function date(): CarbonImmutable
    {
        return AttendanceCalendar::day($this->validated('date'));
    }
}
