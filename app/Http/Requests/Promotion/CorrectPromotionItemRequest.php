<?php

namespace App\Http\Requests\Promotion;

use App\Enums\Promotion\PromotionOutcome;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectPromotionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('correct', $this->route('item'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::enum(PromotionOutcome::class)],
            'target_section_id' => ['nullable', 'integer', TenantRule::exists('sections')],
            'target_roll_no' => ['nullable', 'string', 'max:10'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
