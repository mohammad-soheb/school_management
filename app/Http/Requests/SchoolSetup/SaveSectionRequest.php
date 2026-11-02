<?php

namespace App\Http\Requests\SchoolSetup;

use App\Enums\Staff\StaffStatus;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a section in a class (route {class}) or edit one (route {section}). The name is unique within its class and
 * the class teacher must be an active staff member of this school (or the section's existing class teacher).
 */
class SaveSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');
        $class = $this->route('class');

        if ($section instanceof Section) {
            return $this->user()->can('update', $section);
        }

        return $class instanceof SchoolClass && $this->user()->can('update', $class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $section = $this->route('section');
        $classId = $section instanceof Section ? $section->school_class_id : $this->route('class')?->getKey();
        $currentTeacher = $section instanceof Section ? $section->class_teacher_id : null;

        return [
            'name' => [
                'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]*$/',
                TenantRule::unique('sections', 'name')->where('school_class_id', $classId)->ignore($section instanceof Section ? $section->getKey() : null),
            ],
            'capacity' => ['nullable', 'integer', 'between:1,500'],
            'class_teacher_id' => [
                'nullable', 'integer',
                TenantRule::exists('staff_profiles')->where(fn ($q) => $q->where('status', StaffStatus::Active->value)
                    ->when($currentTeacher !== null, fn ($w) => $w->orWhere('id', $currentTeacher))),
            ],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'This class already has a section with that name.',
            'name.regex' => 'Use letters, digits, spaces or hyphens.',
            'class_teacher_id.exists' => 'Choose an active staff member of this school.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['class_teacher_id' => 'class teacher'];
    }
}
