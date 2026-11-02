<?php

namespace App\Http\Requests\Staff;

use App\Enums\Staff\StaffStatus;
use App\Models\Staff\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStaffStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof StaffProfile && $this->user()->can('update', $staff);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StaffStatus::class)],
            'left_on' => ['nullable', 'required_if:status,'.StaffStatus::Left->value, 'date', 'before_or_equal:'.school_today()->toDateString()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['left_on' => 'leaving date'];
    }
}
