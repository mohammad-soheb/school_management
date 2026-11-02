<?php

namespace App\Http\Requests\Staff;

use App\Models\Staff\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;

/** Link an existing school login to a staff profile. StaffService checks the user is a member without a profile. */
class LinkStaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof StaffProfile && $this->user()->can('update', $staff);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['user_id' => ['required', 'integer']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['user_id' => 'user'];
    }
}
