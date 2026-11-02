<?php

namespace App\Http\Requests\Identity;

use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access.members.invite');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', TenantRule::exists('roles')],
        ];
    }
}
