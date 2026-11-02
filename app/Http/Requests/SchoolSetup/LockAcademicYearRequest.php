<?php

namespace App\Http\Requests\SchoolSetup;

use App\Models\SchoolSetup\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;

/** Lock or reopen an academic year: both need setup.years.lock and a written reason (audited). */
class LockAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $year = $this->route('year');

        return $year instanceof AcademicYear && $this->user()->can('lock', $year);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
