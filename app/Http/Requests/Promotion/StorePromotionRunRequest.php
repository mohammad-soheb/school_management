<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion\PromotionRun;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PromotionRun::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'from_academic_year_id' => ['required', 'integer', TenantRule::exists('academic_years')],
            'to_academic_year_id' => ['required', 'integer', 'different:from_academic_year_id', TenantRule::exists('academic_years')],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer', 'distinct', TenantRule::exists('school_classes')],
            'carry_forward_dues' => ['boolean'],
            'lock_source_year' => ['boolean'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'class_ids.required' => 'Choose at least one class to promote.',
            'to_academic_year_id.different' => 'The target year must be different from the source year.',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            ...$this->safe()->only(['from_academic_year_id', 'to_academic_year_id', 'class_ids', 'idempotency_key']),
            'carry_forward_dues' => $this->boolean('carry_forward_dues'),
            'lock_source_year' => $this->boolean('lock_source_year'),
        ];
    }
}
