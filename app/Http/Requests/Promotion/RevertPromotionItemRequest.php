<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;

class RevertPromotionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('correct', $this->route('item'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
