<?php

namespace App\Http\Requests\SchoolSetup;

use App\Models\SchoolSetup\AcademicYear;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/** Create (no route year) or edit an academic year. Overlap and lock rules are checked by AcademicYearService. */
class SaveAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $year = $this->route('year');

        return $year instanceof AcademicYear
            ? $this->user()->can('update', $year)
            : $this->user()->can('create', AcademicYear::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $year = $this->route('year');

        return [
            'name' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \/\-]*$/', TenantRule::unique('academic_years', 'name')->ignore($year instanceof AcademicYear ? $year->getKey() : null)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'make_current' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'Use letters, digits, spaces, "/" or "-" (for example 2026-27).',
            'name.unique' => 'An academic year with this name already exists.',
            'ends_on.after' => 'The year must end after it starts.',
        ];
    }
}
