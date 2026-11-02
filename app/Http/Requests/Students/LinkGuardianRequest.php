<?php

namespace App\Http\Requests\Students;

use App\Enums\Students\GuardianRelation;
use App\Models\Students\Student;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Link an existing guardian (id, e.g. a sibling's parent) or a new one to a student. */
class LinkGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student && $this->user()->can('manageGuardians', $student);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', TenantRule::exists('guardians')],
            'name' => ['required_without:id', 'nullable', 'string', 'max:120'],
            'relation' => ['required', Rule::enum(GuardianRelation::class)],
            'phone' => ['required_without:id', 'nullable', 'string', 'regex:'.AdmitStudentRequest::PHONE_REGEX],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_primary' => ['nullable', 'boolean'],
            'is_emergency_contact' => ['nullable', 'boolean'],
            'verify' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id.exists' => 'Choose a guardian of this school.',
            'name.required_without' => 'Enter the guardian\'s name, or pick an existing guardian.',
            'phone.required_without' => 'Enter the guardian\'s phone number.',
            'phone.regex' => 'Enter a valid phone number.',
        ];
    }
}
