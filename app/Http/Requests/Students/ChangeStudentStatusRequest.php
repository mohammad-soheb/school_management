<?php

namespace App\Http\Requests\Students;

use App\Enums\Students\StudentStatusAction;
use App\Models\Students\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Withdraw / transfer / deactivate / reactivate, with the effective date and a reason (FR-207). */
class ChangeStudentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student && $this->user()->can('changeStatus', $student);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(StudentStatusAction::class)],
            'effective_on' => ['required', 'date', 'before_or_equal:'.school_today()->toDateString()],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['effective_on' => 'date'];
    }

    public function action(): StudentStatusAction
    {
        return StudentStatusAction::from($this->validated('action'));
    }
}
