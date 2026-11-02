<?php

namespace App\Http\Requests\Promotion;

use App\Enums\Promotion\PromotionOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** "Promote all", "Retain selected" ...: one outcome (or "default") for many students of a draft run. */
class BulkPromotionItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('run'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([...array_column(PromotionOutcome::cases(), 'value'), 'default'])],
            'item_ids' => ['required', 'array', 'min:1', 'max:500'],
            'item_ids.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['item_ids.required' => 'Select at least one student.'];
    }
}
