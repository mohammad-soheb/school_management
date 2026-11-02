<?php

namespace App\Http\Requests\SchoolSetup;

use App\Models\SchoolSetup\SchoolClass;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or edit a class. The "promotes to" loop check runs in ClassStructureService. */
class SaveClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = $this->route('class');

        return $class instanceof SchoolClass
            ? $this->user()->can('update', $class)
            : $this->user()->can('create', SchoolClass::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $class = $this->route('class');
        $id = $class instanceof SchoolClass ? $class->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:60', TenantRule::unique('school_classes', 'name')->ignore($id)],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
            'next_class_id' => ['nullable', 'integer', TenantRule::exists('school_classes'), Rule::notIn(array_filter([$id]))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'A class with this name already exists.',
            'code.regex' => 'Use letters, digits and hyphens only.',
            'next_class_id.not_in' => 'A class cannot promote to itself.',
            'next_class_id.exists' => 'Choose a class of this school.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['next_class_id' => 'promotes to'];
    }
}
