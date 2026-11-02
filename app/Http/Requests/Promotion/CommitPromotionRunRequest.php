<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;

/** FR-2B05: the commit needs an explicit confirmation of the summary. */
class CommitPromotionRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commit', $this->route('run'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'confirm' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['confirm.accepted' => 'Tick the box to confirm you have reviewed the summary.'];
    }
}
