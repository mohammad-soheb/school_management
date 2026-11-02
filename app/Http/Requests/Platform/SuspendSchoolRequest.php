<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class SuspendSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.schools.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
