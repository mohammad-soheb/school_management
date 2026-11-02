<?php

namespace App\Http\Requests\Students;

use App\Enums\Shared\Gender;
use App\Enums\Students\GuardianRelation;
use App\Models\Students\Student;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admission form: the student, this year's class/section/roll number and one or two guardians. */
class AdmitStudentRequest extends FormRequest
{
    public const PHONE_REGEX = '/^[0-9+\-\s()]{7,20}$/';

    public const ADMISSION_NO_REGEX = '/^[A-Za-z0-9\/\-_.]+$/';

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    /** Blank second-guardian rows are dropped, so "one guardian" is a valid admission. */
    protected function prepareForValidation(): void
    {
        $guardians = $this->input('guardians');
        if (! is_array($guardians)) {
            return;
        }

        $kept = array_values(array_filter($guardians, fn ($g) => is_array($g)
            && (filled($g['id'] ?? null) || filled($g['name'] ?? null) || filled($g['phone'] ?? null))));

        $this->merge(['guardians' => $kept]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...self::studentRules(null),
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes')],
            'section_id' => ['nullable', 'integer', TenantRule::exists('sections')],
            'roll_no' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/'],
            'guardians' => ['required', 'array', 'min:1', 'max:2'],
            'guardians.*.id' => ['nullable', 'integer', TenantRule::exists('guardians')],
            'guardians.*.name' => ['required_without:guardians.*.id', 'nullable', 'string', 'max:120'],
            'guardians.*.relation' => ['required', Rule::enum(GuardianRelation::class)],
            'guardians.*.phone' => ['required_without:guardians.*.id', 'nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'guardians.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:100'],
            'guardians.*.is_emergency_contact' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Personal fields shared by admission and edit.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function studentRules(?Student $ignore): array
    {
        return [
            'admission_no' => ['required', 'string', 'max:30', 'regex:'.self::ADMISSION_NO_REGEX, TenantRule::unique('students', 'admission_no')->ignore($ignore)],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'blood_group' => ['nullable', Rule::in(self::BLOOD_GROUPS)],
            'admission_date' => ['nullable', 'date', 'before_or_equal:'.school_today()->toDateString(), 'after:date_of_birth'],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'previous_school' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'school_class_id' => 'class',
            'section_id' => 'section',
            'guardians.*.name' => 'guardian name',
            'guardians.*.phone' => 'guardian phone',
            'guardians.*.relation' => 'relation',
            'guardians.*.email' => 'guardian email',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'guardians.required' => 'Add at least one parent or guardian.',
            'guardians.*.name.required_without' => 'Enter the guardian\'s name, or pick an existing guardian.',
            'guardians.*.phone.required_without' => 'Enter the guardian\'s phone number.',
            'admission_no.unique' => 'This admission number is already used in this school.',
            'admission_no.regex' => 'Use letters, digits and / - _ . only.',
            'phone.regex' => 'Enter a valid phone number.',
            'guardians.*.phone.regex' => 'Enter a valid phone number.',
        ];
    }
}
