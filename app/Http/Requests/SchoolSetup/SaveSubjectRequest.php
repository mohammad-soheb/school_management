<?php

namespace App\Http\Requests\SchoolSetup;

use App\Enums\SchoolSetup\SubjectType;
use App\Models\SchoolSetup\Subject;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject
            ? $this->user()->can('update', $subject)
            : $this->user()->can('create', Subject::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $subject = $this->route('subject');
        $id = $subject instanceof Subject ? $subject->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:100', TenantRule::unique('subjects', 'name')->ignore($id)],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9\-]*$/', TenantRule::unique('subjects', 'code')->ignore($id)],
            'type' => ['required', Rule::enum(SubjectType::class)],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'A subject with this name already exists.',
            'code.unique' => 'Another subject already uses this code.',
            'code.regex' => 'Use capital letters, digits and hyphens only.',
        ];
    }
}
