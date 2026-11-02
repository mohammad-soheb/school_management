<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\SchoolSetup\Section;
use App\Services\Attendance\AttendanceCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Submit a class register. Only users who may mark this section (assigned teacher, or attendance.students.mark_all).
 * attendance[<enrollment id>][status|remark]; the service checks the ids against the roster.
 */
class SubmitAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section && $this->user()->can('attendance.mark-section', $section);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'attendance' => ['required', 'array', 'max:500'],
            'attendance.*' => ['required', 'array:status,remark'],
            'attendance.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'attendance.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attendance.required' => 'There are no students to save.',
            'attendance.*.status.required' => 'Every student needs a status.',
        ];
    }

    public function date(): CarbonImmutable
    {
        return AttendanceCalendar::day($this->validated('date'));
    }
}
