<?php

namespace App\Http\Requests\Staff;

use App\Models\Staff\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;

/** Staff photo (school.staff.photo) or document (school.staff.documents.store); PrivateFileService re-checks everything. */
class UploadStaffFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof StaffProfile && $this->user()->can('update', $staff);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $purpose = $this->routeIs('school.staff.photo') ? 'staff_photo' : 'staff_document';
        $config = config("documents.purposes.{$purpose}");

        return [
            'file' => ['required', 'file', 'max:'.$config['max_kb'], 'extensions:'.implode(',', $config['extensions'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['file.extensions' => 'This file type is not allowed.'];
    }
}
