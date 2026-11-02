<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class SwitchSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The id is only a request: SchoolContextService::switchTo() checks the user's active membership before honouring it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['school_id' => ['required', 'integer']];
    }
}
