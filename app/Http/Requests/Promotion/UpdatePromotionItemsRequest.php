<?php

namespace App\Http\Requests\Promotion;

use App\Enums\Promotion\PromotionOutcome;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Per-student overrides on a draft run. Items are keyed by id; the service checks they belong to the run. */
class UpdatePromotionItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('run'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'max:500'],
            'items.*' => ['array:outcome,target_section_id,target_roll_no,note'],
            'items.*.outcome' => ['required', Rule::enum(PromotionOutcome::class)],
            'items.*.target_section_id' => ['nullable', 'integer', TenantRule::exists('sections')],
            'items.*.target_roll_no' => ['nullable', 'string', 'max:10'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
