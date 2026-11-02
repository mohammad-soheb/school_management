<?php

namespace App\Http\Requests\Identity;

use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access.members.update');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', TenantRule::exists('roles')],
        ];
    }
}
