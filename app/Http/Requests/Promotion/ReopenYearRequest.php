<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;

/** FR-2B07: reopening a locked year for corrections needs promotion.reopen and a reason (audited). */
class ReopenYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('promotion.reopen');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
