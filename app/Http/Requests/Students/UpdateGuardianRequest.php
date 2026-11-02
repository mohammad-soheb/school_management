<?php

namespace App\Http\Requests\Students;

use App\Enums\Students\GuardianRelation;
use App\Models\Students\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Edit a guardian's contact details. */
class UpdateGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guardian = $this->route('guardian');

        return $guardian instanceof Guardian && $this->user()->can('update', $guardian);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'relation' => ['required', Rule::enum(GuardianRelation::class)],
            'phone' => ['required', 'string', 'regex:'.AdmitStudentRequest::PHONE_REGEX],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid phone number.'];
    }
}
