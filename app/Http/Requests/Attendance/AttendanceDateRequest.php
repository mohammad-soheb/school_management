<?php

namespace App\Http\Requests\Attendance;

use App\Services\Attendance\AttendanceCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Read pages that take an optional ?date=Y-m-d (default: today's school date). Access is decided by the route's
 * can: middleware and, for a section, by the controller's authorize() call.
 */
class AttendanceDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
        ];
    }

    public function date(): CarbonImmutable
    {
        $date = $this->validated('date');

        return $date ? AttendanceCalendar::day($date) : AttendanceCalendar::today();
    }
}
