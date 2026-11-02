<?php

namespace App\Http\Requests\Identity;

use App\Support\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access.roles.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::allSchool())],
        ];
    }
}
