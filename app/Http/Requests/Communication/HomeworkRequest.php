<?php

namespace App\Http\Requests\Communication;

use App\Models\Communication\Homework;
use App\Services\Communication\HomeworkService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Set or edit homework. Ids are checked against the active school here; whether THIS user may set homework for the
 * chosen class / section / subject (teacher assignments), and that the section belongs to the class, is checked in
 * HomeworkService so the rule holds however the request was built.
 */
class HomeworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $homework = $this->route('homework');

        return $homework instanceof Homework
            ? $this->user()->can('update', $homework)
            : $this->user()->can('create', Homework::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes')],
            'section_id' => ['nullable', 'integer', TenantRule::exists('sections')],
            'subject_id' => ['required', 'integer', TenantRule::exists('subjects')],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'assigned_on' => ['required', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:assigned_on'],
            'attachments' => ['nullable', 'array', 'max:'.HomeworkService::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'max:'.config('documents.purposes.homework.max_kb')],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'school_class_id' => 'class',
            'section_id' => 'section',
            'subject_id' => 'subject',
            'assigned_on' => 'assigned date',
            'due_on' => 'due date',
            'attachments.*' => 'attachment',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['due_on.after_or_equal' => 'The due date cannot be before the assigned date.'];
    }
}
