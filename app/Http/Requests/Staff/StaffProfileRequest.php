<?php

namespace App\Http\Requests\Staff;

use App\Enums\Shared\Gender;
use App\Http\Requests\Students\AdmitStudentRequest;
use App\Models\Staff\StaffProfile;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or edit a staff profile (FR-208). Employee id is unique within the school only (FR-209). */
class StaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof StaffProfile
            ? $this->user()->can('update', $staff)
            : $this->user()->can('create', StaffProfile::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'employee_id' => ['required', 'string', 'max:30', 'regex:'.AdmitStudentRequest::ADMISSION_NO_REGEX, TenantRule::unique('staff_profiles', 'employee_id')->ignore($staff instanceof StaffProfile ? $staff : null)],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1940-01-01'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:'.AdmitStudentRequest::PHONE_REGEX],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'joined_on' => ['nullable', 'date', 'before_or_equal:'.school_today()->addYear()->toDateString()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['employee_id' => 'employee ID', 'joined_on' => 'joining date'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employee_id.unique' => 'This employee ID is already used in this school.',
            'employee_id.regex' => 'Use letters, digits and / - _ . only.',
            'phone.regex' => 'Enter a valid phone number.',
        ];
    }
}
