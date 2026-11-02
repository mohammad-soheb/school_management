<?php

namespace App\Http\Requests\Communication;

use App\Models\Communication\Homework;
use App\Services\Communication\HomeworkService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters of the homework list. Unknown values are rejected rather than silently passed to the query. */
class HomeworkIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Homework::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'due' => ['nullable', Rule::in(['today', 'week', 'upcoming', 'overdue'])],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'mine' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(HomeworkService::SORTABLE)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $data = $this->validated();

        foreach (['class_id', 'section_id', 'subject_id'] as $key) {
            $data[$key] = isset($data[$key]) ? (int) $data[$key] : null;
        }
        $data['mine'] = $this->boolean('mine');

        return $data;
    }
}
