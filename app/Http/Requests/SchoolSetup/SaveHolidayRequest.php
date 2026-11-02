<?php

namespace App\Http\Requests\SchoolSetup;

use App\Enums\SchoolSetup\HolidayType;
use App\Models\SchoolSetup\SchoolHoliday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A calendar entry. Its academic year is derived from the dates by HolidayService (never posted by the browser). */
class SaveHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $holiday = $this->route('holiday');

        return $holiday instanceof SchoolHoliday
            ? $this->user()->can('update', $holiday)
            : $this->user()->can('create', SchoolHoliday::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(HolidayType::class)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ends_on.after_or_equal' => 'The end date cannot be before the start date.'];
    }
}
