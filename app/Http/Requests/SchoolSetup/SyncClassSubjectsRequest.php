<?php

namespace App\Http\Requests\SchoolSetup;

use App\Models\SchoolSetup\SchoolClass;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The subjects taught in a class. The form posts one row per subject of the school:
 *   subjects[<subject id>][selected] = 1, subjects[<id>][is_optional] = 1, subjects[<id>][sort_order] = 3
 * Only selected rows are kept; validated() returns them as a list of {subject_id, is_optional, sort_order}.
 */
class SyncClassSubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = $this->route('class');

        return $class instanceof SchoolClass && $this->user()->can('update', $class);
    }

    protected function prepareForValidation(): void
    {
        $rows = [];
        foreach ((array) $this->input('subjects', []) as $subjectId => $row) {
            if (! is_array($row) || ! filter_var($row['selected'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $rows[] = [
                'subject_id' => $subjectId,
                'is_optional' => $row['is_optional'] ?? false,
                'sort_order' => $row['sort_order'] ?? null,
            ];
        }

        usort($rows, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 999)) <=> ((int) ($b['sort_order'] ?? 999)));

        $this->merge(['items' => $rows]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'subjects' => ['nullable', 'array'],
            'items' => ['present', 'array', 'max:60'],
            'items.*.subject_id' => ['required', 'integer', 'distinct', TenantRule::exists('subjects')],
            'items.*.is_optional' => ['boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'between:0,999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['items.*.subject_id.exists' => 'Choose subjects of this school.'];
    }

    /** @return list<array{subject_id: int, is_optional: bool, sort_order: ?int}> */
    public function items(): array
    {
        return array_map(fn (array $row): array => [
            'subject_id' => (int) $row['subject_id'],
            'is_optional' => filter_var($row['is_optional'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => isset($row['sort_order']) && $row['sort_order'] !== '' ? (int) $row['sort_order'] : null,
        ], array_values($this->validated('items', [])));
    }
}
