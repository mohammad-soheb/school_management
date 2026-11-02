<?php

namespace App\Http\Requests\Staff;

use App\Models\Staff\StaffProfile;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/** Invite a staff member to create a login with staff roles (never the Parent role). */
class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof StaffProfile && $this->user()->can('invite', $staff);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', TenantRule::exists('roles')->whereNot('slug', 'parent')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['roles.*.exists' => 'Choose staff roles of this school.'];
    }
}
