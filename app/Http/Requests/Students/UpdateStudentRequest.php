<?php

namespace App\Http\Requests\Students;

use App\Models\Students\Student;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/** Edit a student's personal details and this year's class/section/roll number. */
class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student && $this->user()->can('update', $student);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...AdmitStudentRequest::studentRules($this->route('student')),
            'school_class_id' => ['nullable', 'integer', TenantRule::exists('school_classes')],
            'section_id' => ['nullable', 'integer', TenantRule::exists('sections')],
            'roll_no' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['school_class_id' => 'class', 'section_id' => 'section'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'admission_no.unique' => 'This admission number is already used in this school.',
            'admission_no.regex' => 'Use letters, digits and / - _ . only.',
            'phone.regex' => 'Enter a valid phone number.',
        ];
    }
}
