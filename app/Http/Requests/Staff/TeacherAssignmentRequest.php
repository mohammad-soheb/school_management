<?php

namespace App\Http\Requests\Staff;

use App\Models\Staff\StaffProfile;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/** Assign a staff member to a class/section/subject for an academic year. */
class TeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof StaffProfile && $this->user()->can('manageAssignments', $staff);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', TenantRule::exists('academic_years')],
            'school_class_id' => ['required', 'integer', TenantRule::exists('school_classes')],
            'section_id' => ['nullable', 'integer', TenantRule::exists('sections')],
            'subject_id' => ['nullable', 'integer', TenantRule::exists('subjects')],
            'is_class_teacher' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['academic_year_id' => 'academic year', 'school_class_id' => 'class', 'section_id' => 'section', 'subject_id' => 'subject'];
    }
}
