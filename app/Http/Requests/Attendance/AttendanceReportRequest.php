<?php

namespace App\Http\Requests\Attendance;

use App\Models\SchoolSetup\Section;
use App\Services\Attendance\AttendanceAccess;
use App\Services\Attendance\AttendanceCalendar;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the attendance reports: section_id (a section the user may view), month=Y-m, from/to=Y-m-d, date.
 * Route middleware requires attendance.reports.view; this adds the teacher's section scope (FR-303).
 */
class AttendanceReportRequest extends FormRequest
{
    /** Exports of a class report need a section; pages render the filter without one. */
    protected bool $sectionRequired = false;

    public function authorize(): bool
    {
        $sectionId = $this->query('section_id');

        if ($sectionId === null || $sectionId === '' || ! ctype_digit((string) $sectionId)) {
            return true; // validation decides
        }

        return app(AttendanceAccess::class)->canView($this->user(), (int) $sectionId);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'section_id' => [$this->sectionRequired ? 'required' : 'nullable', 'integer', TenantRule::exists('sections')],
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['section_id.required' => 'Choose a class.', 'to.after_or_equal' => 'The end date must be on or after the start date.'];
    }

    public function section(): ?Section
    {
        $id = $this->validated('section_id');

        return $id ? Section::query()->with('schoolClass:id,name')->findOrFail((int) $id) : null;
    }

    public function month(): CarbonImmutable
    {
        $month = $this->validated('month');

        return $month ? AttendanceCalendar::day($month.'-01') : AttendanceCalendar::today()->startOfMonth();
    }

    public function date(): CarbonImmutable
    {
        $date = $this->validated('date');

        return $date ? AttendanceCalendar::day($date) : AttendanceCalendar::today();
    }

    /** Default range: the first of this month up to today. */
    public function from(): CarbonImmutable
    {
        $from = $this->validated('from');

        return $from ? AttendanceCalendar::day($from) : AttendanceCalendar::today()->startOfMonth();
    }

    public function to(): CarbonImmutable
    {
        $to = $this->validated('to');

        return $to ? AttendanceCalendar::day($to) : AttendanceCalendar::today();
    }
}
